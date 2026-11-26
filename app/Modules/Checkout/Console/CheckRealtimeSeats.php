<?php

namespace App\Modules\Checkout\Console;

use App\Modules\Checkout\Events\SeatsChanged;
use App\Modules\Checkout\Support\RedisSeatLocks;
use App\Modules\Events\Models\EventoReservaBoleto;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Revisa de punta a punta los apartados de asientos en tiempo real: la
 * configuración de Reverb, que el servidor acepte conexiones WebSocket, que
 * un aviso de cambio llegue a un suscriptor, los apartados en Redis y que el
 * scheduler libere los vencidos. No toca asientos reales.
 */
class CheckRealtimeSeats extends Command
{
    protected $signature = 'checkout:check-realtime
        {--funcion= : pk_evento_funcion a revisar (por omisión usa el canal de prueba funcion.0)}
        {--url= : URL pública del socket, p. ej. wss://deboleto.mx (por omisión REVERB_SCHEME://REVERB_HOST:REVERB_PORT)}';

    protected $description = 'Valida que Reverb, los avisos y los apartados de asientos en tiempo real funcionen';

    private bool $failed = false;

    public function handle(RedisSeatLocks $locks): int
    {
        $functionId = (int) ($this->option('funcion') ?? 0);

        if ($this->configuration()) {
            $this->reverbApi($functionId);
            $this->websocket($functionId);
        }

        $this->holds($locks, $functionId);
        $this->scheduler();
        $this->mirror($functionId);

        $this->newLine();
        $this->failed ? $this->error('Hay partes del tiempo real con problemas.') : $this->info('El tiempo real de los asientos funciona.');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function configuration(): bool
    {
        $this->line('<options=bold>Configuración</>');
        $config = config('broadcasting.connections.reverb');
        $default = config('broadcasting.default');

        if ($default !== 'reverb') {
            $this->bad('BROADCAST_CONNECTION', "vale \"{$default}\"; debe ser reverb o no se envía ningún aviso.");

            return false;
        }

        $missing = array_keys(array_filter([
            'REVERB_APP_ID' => $config['app_id'] ?? null,
            'REVERB_APP_KEY' => $config['key'] ?? null,
            'REVERB_APP_SECRET' => $config['secret'] ?? null,
            'REVERB_HOST' => $config['options']['host'] ?? null,
        ], fn ($value) => blank($value)));

        if ($missing !== []) {
            $this->bad('Reverb', 'faltan '.implode(', ', $missing).' en el .env (o hay que correr php artisan config:clear).');

            return false;
        }

        $this->ok('Reverb', "app {$config['app_id']} en {$this->serverUrl()}");
        $this->frontendBuild((string) $config['key']);

        return true;
    }

    private function frontendBuild(string $key): void
    {
        if (is_file(public_path('hot'))) {
            $this->warn('  ! Frontend: Vite está en modo dev; revisa que VITE_REVERB_APP_KEY esté en el .env.');

            return;
        }

        $assets = glob(public_path('build/assets/*.js')) ?: [];

        if ($assets === []) {
            $this->bad('Frontend', 'no hay build en public/build; corre npm run build.');

            return;
        }

        foreach ($assets as $asset) {
            if (str_contains((string) file_get_contents($asset), $key)) {
                $this->ok('Frontend', 'el build incluye VITE_REVERB_APP_KEY');

                return;
            }
        }

        $this->bad('Frontend', 'el build no tiene REVERB_APP_KEY: los mapas no se conectan al socket. Revisa VITE_REVERB_APP_KEY y corre npm run build.');
    }

    private function reverbApi(int $functionId): void
    {
        $this->newLine();
        $this->line('<options=bold>Servidor Reverb (API)</>');

        try {
            $result = Broadcast::connection('reverb')->getPusher()->get('/channels', ['filter_by_prefix' => 'funcion.'], true);
            $channels = array_keys((array) ($result['channels'] ?? []));
        } catch (Throwable $exception) {
            $this->bad('API', 'Laravel no pudo hablar con Reverb: '.$exception->getMessage().'. ¿Está corriendo reverb:start / supervisor?');

            return;
        }

        $this->ok('API', 'Reverb responde y acepta las llaves');
        $this->ok('Canales', count($channels).' funciones con mapas abiertos ahora'
            .($functionId > 0 ? ' · funcion.'.$functionId.(in_array("funcion.{$functionId}", $channels, true) ? ' tiene mapas abiertos' : ' sin mapas abiertos') : ''));
    }

    private function websocket(int $functionId): void
    {
        $this->newLine();
        $url = (string) ($this->option('url') ?: $this->serverUrl());
        $this->line("<options=bold>WebSocket</> ({$url})");
        $channel = "funcion.{$functionId}";

        try {
            $socket = $this->connect($url, (string) config('broadcasting.connections.reverb.key'));
            $this->ok('Conexión', 'handshake 101 aceptado');

            $this->expect($socket, 'pusher:connection_established', 5);
            $this->ok('Pusher', 'connection_established');

            $this->send($socket, json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => $channel]]));
            $this->expect($socket, 'pusher_internal:subscription_succeeded', 5);
            $this->ok('Suscripción', $channel);

            $started = microtime(true);
            event(new SeatsChanged($functionId));
            $this->expect($socket, 'seats.changed', 5);
            $this->ok('Aviso', 'seats.changed llegó en '.round((microtime(true) - $started) * 1000).' ms');

            fclose($socket);
        } catch (Throwable $exception) {
            $this->bad('WebSocket', $exception->getMessage());
        }
    }

    private function holds(RedisSeatLocks $locks, int $functionId): void
    {
        $this->newLine();
        $this->line('<options=bold>Apartados en Redis</>');
        $owner = 'check:'.Str::uuid();
        $locality = '__check__';

        try {
            [$result] = $locks->hold($functionId, $locality, ['X1'], $owner, 1, (int) floor(microtime(true) * 1000) + 30000);
            $held = $result === 'ok' && ($locks->active($functionId, $locality)['X1'] ?? null) === $owner;
            $released = $locks->release($functionId, $owner, [RedisSeatLocks::field($locality, 'X1')]) === 1;
            $locks->forgetOwner($owner);
        } catch (Throwable $exception) {
            $this->bad('Redis', 'no se pudo usar la conexión '.config('checkout.holds_redis_connection', 'default').': '.$exception->getMessage());

            return;
        }

        $held && $released
            ? $this->ok('Apartar y liberar', 'un asiento de prueba se apartó y se liberó')
            : $this->bad('Apartar y liberar', "resultado inesperado ({$result}).");

        $this->stale();
    }

    private function stale(): void
    {
        $redis = Redis::connection(config('checkout.holds_redis_connection', 'default'));
        $now = (int) floor(microtime(true) * 1000);
        $active = 0;
        $stale = 0;

        foreach ($redis->smembers('seat_holds:functions') as $functionId) {
            foreach ($redis->hgetall("seat_holds:{$functionId}") as $value) {
                $expires = (int) Str::before($value, ':');

                if ($expires > $now) {
                    $active++;
                } elseif ($expires < $now - 60000) {
                    $stale++;
                }
            }
        }

        $this->ok('Vigentes', "{$active} asientos apartados ahora");

        $stale > 0
            ? $this->bad('Vencidos', "{$stale} apartados vencieron hace más de 1 minuto y siguen en Redis: el scheduler no está corriendo checkout:sweep-holds.")
            : $this->ok('Vencidos', 'no hay apartados vencidos pendientes de liberar');
    }

    private function scheduler(): void
    {
        $this->newLine();
        $this->line('<options=bold>Scheduler</>');

        $scheduled = collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains((string) $event->command, 'checkout:sweep-holds'));

        $scheduled
            ? $this->ok('checkout:sweep-holds', 'programado; necesita el cron de schedule:run (o schedule:work en local)')
            : $this->bad('checkout:sweep-holds', 'no está programado en routes/console.php.');
    }

    private function mirror(int $functionId): void
    {
        $this->newLine();
        $this->line('<options=bold>Copia en deboletox (eventos_reserva_boleto)</>');

        try {
            $count = EventoReservaBoleto::query()
                ->when($functionId > 0, fn ($query) => $query->where('pk_evento_funcion', $functionId))
                ->count();
        } catch (Throwable $exception) {
            $this->bad('Tabla', $exception->getMessage());

            return;
        }

        $this->ok('Tabla', "{$count} filas".($functionId > 0 ? " de la función {$functionId}" : ''));
    }

    private function serverUrl(): string
    {
        $options = config('broadcasting.connections.reverb.options');
        $scheme = ($options['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';

        return "{$scheme}://{$options['host']}:{$options['port']}";
    }

    /**
     * @return resource
     */
    private function connect(string $url, string $key)
    {
        $parts = parse_url($url);
        $tls = in_array($parts['scheme'] ?? '', ['wss', 'https'], true);
        $host = $parts['host'] ?? throw new RuntimeException("URL inválida: {$url}");
        $port = $parts['port'] ?? ($tls ? 443 : 80);
        $path = rtrim($parts['path'] ?? '', '/')."/app/{$key}?protocol=7&client=php&version=1.0&flash=false";

        $socket = @stream_socket_client(
            ($tls ? 'ssl' : 'tcp')."://{$host}:{$port}", $errno, $error, 5, STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['SNI_enabled' => true, 'peer_name' => $host]]),
        );

        if ($socket === false) {
            throw new RuntimeException("no se pudo abrir {$host}:{$port} ({$error}). ¿Está corriendo Reverb y abierto el puerto?");
        }

        stream_set_timeout($socket, 5);

        fwrite($socket, implode("\r\n", [
            "GET {$path} HTTP/1.1",
            "Host: {$host}:{$port}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: '.base64_encode(random_bytes(16)),
            'Sec-WebSocket-Version: 13',
            'Origin: '.config('app.url'),
        ])."\r\n\r\n");

        $headers = '';

        while (! str_contains($headers, "\r\n\r\n") && ! feof($socket)) {
            $line = fgets($socket);

            if ($line === false) {
                break;
            }

            $headers .= $line;
        }

        $status = strtok($headers, "\r\n") ?: 'sin respuesta';

        if (! str_contains($status, ' 101 ')) {
            throw new RuntimeException("el servidor respondió \"{$status}\" en vez de 101. Revisa el proxy de nginx para /app y REVERB_ALLOWED_ORIGINS.");
        }

        return $socket;
    }

    /**
     * @param  resource  $socket
     */
    private function expect($socket, string $event, int $seconds): void
    {
        $deadline = microtime(true) + $seconds;

        while (microtime(true) < $deadline) {
            [$opcode, $payload] = $this->frame($socket);

            if ($opcode === 9) {
                $this->send($socket, $payload, 0xA);
            } elseif ($opcode === 8) {
                throw new RuntimeException("Reverb cerró la conexión esperando {$event}.");
            } elseif ($opcode === 1) {
                $message = json_decode($payload, true) ?? [];

                if (($message['event'] ?? null) === 'pusher:error') {
                    throw new RuntimeException('Reverb respondió un error: '.json_encode($message['data'] ?? null));
                }

                if (($message['event'] ?? null) === $event) {
                    return;
                }
            }
        }

        throw new RuntimeException("no llegó {$event} en {$seconds} s.");
    }

    /**
     * @param  resource  $socket
     * @return array{0: int, 1: string}
     */
    private function frame($socket): array
    {
        [$first, $second] = array_values(unpack('C2', $this->read($socket, 2)));
        $length = $second & 0x7F;

        if ($length === 126) {
            $length = unpack('n', $this->read($socket, 2))[1];
        } elseif ($length === 127) {
            $length = unpack('J', $this->read($socket, 8))[1];
        }

        return [$first & 0x0F, $length > 0 ? $this->read($socket, $length) : ''];
    }

    /**
     * @param  resource  $socket
     */
    private function read($socket, int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException(stream_get_meta_data($socket)['timed_out'] ? 'el socket no respondió a tiempo.' : 'el socket se cerró.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * @param  resource  $socket
     */
    private function send($socket, string $payload, int $opcode = 0x1): void
    {
        $length = strlen($payload);
        $mask = random_bytes(4);
        $header = chr(0x80 | $opcode).match (true) {
            $length < 126 => chr(0x80 | $length),
            $length < 65536 => chr(0x80 | 126).pack('n', $length),
            default => chr(0x80 | 127).pack('J', $length),
        };

        fwrite($socket, $header.$mask.($payload ^ substr(str_repeat($mask, intdiv($length, 4) + 1), 0, $length)));
    }

    private function ok(string $label, string $detail): void
    {
        $this->line("  <fg=green>✓</> {$label}: {$detail}");
    }

    private function bad(string $label, string $detail): void
    {
        $this->failed = true;
        $this->line("  <fg=red>✗</> {$label}: {$detail}");
    }
}
