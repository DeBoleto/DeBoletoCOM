<?php

namespace App\Modules\Checkout\Support;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Apartados de asientos en Redis. Cada función tiene un hash
 * `seat_holds:{función}` cuyo campo es "localidad␟asiento" y cuyo valor es
 * "caducidad_ms:dueño". Los scripts Lua hacen cada operación atómica, así
 * dos personas nunca apartan el mismo asiento aunque lleguen al mismo tiempo.
 */
class RedisSeatLocks
{
    public const SEPARATOR = "\x1F";

    private const FUNCTIONS = 'seat_holds:functions';

    private const HOLD = <<<'LUA'
local now = tonumber(ARGV[1])
local owner = ARGV[3]
local requested = {}
for i = 6, #ARGV do requested[ARGV[i]] = true end
local mine = 0
local entries = redis.call('HGETALL', KEYS[1])
for i = 1, #entries, 2 do
    local value = entries[i + 1]
    local sep = string.find(value, ':', 1, true)
    if tonumber(string.sub(value, 1, sep - 1)) > now then
        local holder = string.sub(value, sep + 1)
        if requested[entries[i]] then
            if holder ~= owner then return {'taken', entries[i]} end
        elseif holder == owner then
            mine = mine + 1
        end
    end
end
if mine + #ARGV - 5 > tonumber(ARGV[4]) then return {'limit', ''} end
for i = 6, #ARGV do redis.call('HSET', KEYS[1], ARGV[i], ARGV[2] .. ':' .. owner) end
redis.call('SADD', KEYS[2], ARGV[5])
redis.call('SADD', KEYS[3], ARGV[5])
redis.call('PEXPIREAT', KEYS[1], tonumber(ARGV[2]) + 600000)
redis.call('PEXPIREAT', KEYS[3], tonumber(ARGV[2]) + 600000)
return {'ok', ''}
LUA;

    private const RELEASE = <<<'LUA'
local owner = ARGV[1]
local only = ARGV[2] == '1'
local listed = {}
for i = 3, #ARGV do listed[ARGV[i]] = true end
local removed = {}
local entries = redis.call('HGETALL', KEYS[1])
for i = 1, #entries, 2 do
    local value = entries[i + 1]
    local sep = string.find(value, ':', 1, true)
    if string.sub(value, sep + 1) == owner and (listed[entries[i]] or false) == only then
        redis.call('HDEL', KEYS[1], entries[i])
        removed[#removed + 1] = entries[i]
    end
end
return removed
LUA;

    private const REFRESH = <<<'LUA'
local owner = ARGV[1]
local entries = redis.call('HGETALL', KEYS[1])
for i = 1, #entries, 2 do
    local value = entries[i + 1]
    local sep = string.find(value, ':', 1, true)
    if string.sub(value, sep + 1) == owner then
        redis.call('HSET', KEYS[1], entries[i], ARGV[2] .. ':' .. owner)
    end
end
redis.call('PEXPIREAT', KEYS[1], tonumber(ARGV[2]) + 600000)
return 1
LUA;

    private const SWEEP = <<<'LUA'
local now = tonumber(ARGV[1])
local removed = 0
local entries = redis.call('HGETALL', KEYS[1])
for i = 1, #entries, 2 do
    local value = entries[i + 1]
    local sep = string.find(value, ':', 1, true)
    if tonumber(string.sub(value, 1, sep - 1)) <= now then
        redis.call('HDEL', KEYS[1], entries[i])
        removed = removed + 1
    end
end
if redis.call('HLEN', KEYS[1]) == 0 then redis.call('SREM', KEYS[2], ARGV[2]) end
return removed
LUA;

    /**
     * Aparta todos los asientos o ninguno.
     *
     * @param  list<string>  $seats
     * @return array{0: 'ok'|'taken'|'limit', 1: string} Resultado y, si está ocupado, el asiento.
     */
    public function hold(int $functionId, string $localityName, array $seats, string $owner, int $limit, int $expiresAtMs): array
    {
        $fields = array_map(fn (string $seat) => self::field($localityName, $seat), $seats);

        [$result, $field] = $this->redis()->eval(
            self::HOLD, 3,
            self::key($functionId), self::FUNCTIONS, self::ownerKey($owner),
            $this->nowMs(), $expiresAtMs, $owner, $limit, $functionId, ...$fields,
        );

        return [$result, $field === '' ? '' : self::seatOf($field)];
    }

    /**
     * Libera los asientos indicados del dueño, o todos salvo esos con
     * `$except`. Devuelve cuántos liberó.
     *
     * @param  list<string>  $fields  Campos "localidad␟asiento".
     */
    public function release(int $functionId, string $owner, array $fields, bool $except = false): int
    {
        return count((array) $this->redis()->eval(
            self::RELEASE, 1,
            self::key($functionId),
            $owner, $except ? '0' : '1', ...$fields,
        ));
    }

    /**
     * Funciones donde el dueño tiene o tuvo apartados recientes.
     *
     * @return list<int>
     */
    public function functionsOf(string $owner): array
    {
        return array_map('intval', $this->redis()->smembers(self::ownerKey($owner)));
    }

    public function forgetOwner(string $owner): void
    {
        $this->redis()->del(self::ownerKey($owner));
    }

    public function refresh(string $owner, int $expiresAtMs): void
    {
        foreach ($this->functionsOf($owner) as $functionId) {
            $this->redis()->eval(self::REFRESH, 1, self::key($functionId), $owner, $expiresAtMs);
        }

        $this->redis()->pexpireat(self::ownerKey($owner), $expiresAtMs + 600000);
    }

    /**
     * Asientos apartados vigentes de una localidad: asiento => dueño.
     *
     * @return array<string, string>
     */
    public function active(int $functionId, string $localityName): array
    {
        $now = $this->nowMs();
        $prefix = $localityName.self::SEPARATOR;
        $holds = [];

        foreach ($this->redis()->hgetall(self::key($functionId)) as $field => $value) {
            if (! str_starts_with($field, $prefix)) {
                continue;
            }

            [$expires, $owner] = explode(':', $value, 2);

            if ((int) $expires > $now) {
                $holds[self::seatOf($field)] = $owner;
            }
        }

        return $holds;
    }

    /**
     * Quita los apartados vencidos y devuelve las funciones que cambiaron.
     *
     * @return list<int>
     */
    public function sweep(): array
    {
        $changed = [];
        $now = $this->nowMs();

        foreach ($this->redis()->smembers(self::FUNCTIONS) as $functionId) {
            $removed = (int) $this->redis()->eval(self::SWEEP, 2, self::key((int) $functionId), self::FUNCTIONS, $now, $functionId);

            if ($removed > 0) {
                $changed[] = (int) $functionId;
            }
        }

        return $changed;
    }

    public static function field(string $localityName, string $seat): string
    {
        return $localityName.self::SEPARATOR.$seat;
    }

    private static function seatOf(string $field): string
    {
        $position = strrpos($field, self::SEPARATOR);

        return $position === false ? $field : substr($field, $position + 1);
    }

    private static function key(int $functionId): string
    {
        return "seat_holds:{$functionId}";
    }

    private static function ownerKey(string $owner): string
    {
        return 'seat_holds:owner:'.sha1($owner);
    }

    private function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function redis(): Connection
    {
        return Redis::connection(config('checkout.holds_redis_connection', 'default'));
    }
}
