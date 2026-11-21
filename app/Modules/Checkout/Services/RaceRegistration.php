<?php

namespace App\Modules\Checkout\Services;

use App\Modules\Events\Models\CuestionariosPreguntas;
use App\Modules\Events\Models\EventosCuestionariosPreguntas;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Tickets\Models\EventoBoletoCarrera;
use App\Modules\Tickets\Models\EventoBoletoCarreraRespuestas;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registro de participantes de las carreras en la compra web: cada boleto
 * lleva el cuestionario del evento (eventos_cuestionarios_preguntas) y la
 * zona (distancia) del participante. Se guarda con los boletos pendientes en
 * evento_boleto_carrera y sus respuestas en evento_boleto_carrera_respuestas.
 */
class RaceRegistration
{
    /**
     * Preguntas cuya respuesta también va en las columnas fijas de
     * evento_boleto_carrera, que leen deboletox y el reporte de participantes.
     */
    private const COLUMNS = [
        'nombre completo' => ['nombre', 200],
        'nombre' => ['nombre', 200],
        'telefono' => ['telefono', 15],
        'correo electronico' => ['correo', 100],
        'correo' => ['correo', 100],
        'fecha de nacimiento' => ['fecha_nacimiento', null],
        'nombre del contacto de emergencia' => ['nombre_emergencia', 200],
        'contacto de emergencia' => ['nombre_emergencia', 200],
        'telefono del contacto de emergencia' => ['telefono_emergencia', 15],
        'telefono de emergencia' => ['telefono_emergencia', 15],
    ];

    /**
     * Cuestionario del evento listo para la página.
     *
     * @return list<array{id: int, label: string, type: string, required: bool, field: string|null, options: list<array{id: int, label: string}>}>
     */
    public function questionnaire(int $eventId): array
    {
        return $this->questions($eventId)->map(fn (EventosCuestionariosPreguntas $question) => [
            'id' => $question->pk_evento_cuestionario_pregunta,
            'label' => $question->pregunta->pregunta,
            'type' => $question->pregunta->tipo,
            'required' => $question->obligatoria,
            'field' => self::COLUMNS[$this->normalize($question->pregunta->pregunta)][0] ?? null,
            'options' => $question->pregunta->esSeleccion()
                ? $question->pregunta->opciones->map(fn ($option) => [
                    'id' => $option->pk_cuestionario_pregunta_opcion,
                    'label' => $option->opcion,
                ])->values()->all()
                : [],
        ])->values()->all();
    }

    /**
     * Valida los participantes y devuelve sus respuestas normalizadas.
     *
     * @param  list<array{localidad?: mixed, respuestas?: array}>  $participants
     * @param  list<int>  $localityIds  Zonas a la venta.
     * @return list<array{locality_id: int, answers: list<array{question_id: int, option_id: int|null, value: string}>}>
     *
     * @throws ValidationException
     */
    public function validate(int $eventId, array $participants, array $localityIds): array
    {
        if ($participants === []) {
            throw ValidationException::withMessages(['participantes' => 'Agrega al menos un participante.']);
        }

        $questions = $this->questions($eventId);
        $errors = [];
        $normalized = [];

        foreach (array_values($participants) as $index => $participant) {
            $number = $index + 1;
            $localityId = (int) ($participant['localidad'] ?? 0);
            $given = (array) ($participant['respuestas'] ?? []);
            $answers = [];

            if (! in_array($localityId, $localityIds, true)) {
                $errors["participantes.{$index}.localidad"] = "Elige la categoría del participante {$number}.";
            }

            foreach ($questions as $question) {
                $key = "participantes.{$index}.respuestas.{$question->pk_evento_cuestionario_pregunta}";
                $raw = $given[$question->pk_evento_cuestionario_pregunta] ?? null;
                $value = is_scalar($raw) ? trim((string) $raw) : '';

                if ($value === '') {
                    if ($question->obligatoria) {
                        $errors[$key] = "Responde «{$question->pregunta->pregunta}» del participante {$number}.";
                    }

                    continue;
                }

                [$answer, $error] = $this->answer($question, $value);

                if ($error !== null) {
                    $errors[$key] = "{$error} (participante {$number}).";

                    continue;
                }

                $answers[] = $answer;
            }

            $normalized[] = ['locality_id' => $localityId, 'answers' => $answers];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * Cantidad de boletos por zona que piden los participantes.
     *
     * @param  list<array{locality_id: int}>  $participants
     * @return array<int, int>
     */
    public function quantities(array $participants): array
    {
        return collect($participants)->countBy('locality_id')->all();
    }

    /**
     * Guarda a cada participante con uno de los boletos de su zona.
     *
     * @param  list<array{locality_id: int, answers: list<array{question_id: int, option_id: int|null, value: string}>}>  $participants
     * @param  list<EventosFuncionesBoletos>  $tickets
     */
    public function register(int $purchaseId, array $participants, array $tickets): void
    {
        if ($participants === []) {
            return;
        }

        $byLocality = collect($tickets)
            ->groupBy(fn (EventosFuncionesBoletos $ticket) => (int) $ticket->pk_evento_localidad)
            ->map(fn (Collection $group) => $group->values());
        $taken = [];
        $now = Carbon::now(LegacyModel::legacyTimezone())->toDateTimeString();
        $fields = $this->fieldsByQuestion($participants);

        foreach ($participants as $participant) {
            $localityId = $participant['locality_id'];
            $position = $taken[$localityId] = ($taken[$localityId] ?? -1) + 1;
            $ticket = $byLocality->get($localityId)?->get($position);

            if ($ticket === null) {
                continue;
            }

            $row = [
                'pk_evento_compra' => $purchaseId,
                'pk_evento_funcion_boleto' => $ticket->pk_evento_funcion_boleto,
                'creacion_fecha' => $now,
                'modificacion_fecha' => $now,
            ];

            foreach ($participant['answers'] as $answer) {
                [$column, $length] = $fields[$answer['question_id']] ?? [null, null];

                if ($column !== null) {
                    $row[$column] = $length === null ? $answer['value'] : mb_substr($this->latin1($answer['value']), 0, $length);
                }
            }

            $registrationId = EventoBoletoCarrera::query()->insertGetId($row);

            if ($participant['answers'] !== []) {
                EventoBoletoCarreraRespuestas::query()->insert(array_map(fn (array $answer) => [
                    'pk_evento_boleto_carrera' => $registrationId,
                    'pk_evento_cuestionario_pregunta' => $answer['question_id'],
                    'pk_cuestionario_pregunta_opcion' => $answer['option_id'],
                    'respuesta' => $answer['value'],
                    'creacion_fecha' => $now,
                    'modificacion_fecha' => $now,
                ], $participant['answers']));
            }
        }
    }

    /**
     * Borra el registro de participantes de una compra que no se pagó.
     *
     * @param  list<int>  $purchaseIds
     */
    public function forget(array $purchaseIds): void
    {
        $registrations = EventoBoletoCarrera::query()->whereIn('pk_evento_compra', $purchaseIds)->pluck('pk_evento_boleto_carrera');

        if ($registrations->isEmpty()) {
            return;
        }

        EventoBoletoCarreraRespuestas::query()->whereIn('pk_evento_boleto_carrera', $registrations)->delete();
        EventoBoletoCarrera::query()->whereIn('pk_evento_boleto_carrera', $registrations)->delete();
    }

    /**
     * @return Collection<int, EventosCuestionariosPreguntas>
     */
    private function questions(int $eventId): Collection
    {
        return EventosCuestionariosPreguntas::query()
            ->with('pregunta.opciones')
            ->where('pk_evento', $eventId)
            ->where('eliminado', 0)
            ->whereHas('pregunta', fn ($query) => $query->where('eliminado', 0))
            ->orderBy('orden')
            ->get();
    }

    /**
     * @return array{0: array{question_id: int, option_id: int|null, value: string}|null, 1: string|null}
     */
    private function answer(EventosCuestionariosPreguntas $question, string $value): array
    {
        $label = $question->pregunta->pregunta;
        $answer = fn (string $text, ?int $optionId = null) => [[
            'question_id' => $question->pk_evento_cuestionario_pregunta,
            'option_id' => $optionId,
            'value' => $text,
        ], null];

        switch ($question->pregunta->tipo) {
            case CuestionariosPreguntas::TYPE_SELECT:
                $option = $question->pregunta->opciones->firstWhere('pk_cuestionario_pregunta_opcion', (int) $value);

                return $option === null ? [null, "Elige una opción válida en «{$label}»"] : $answer($option->opcion, $option->pk_cuestionario_pregunta_opcion);

            case CuestionariosPreguntas::TYPE_NUMBER:
                return is_numeric($value) && strlen($value) <= 20 ? $answer($value) : [null, "«{$label}» debe ser un número"];

            case CuestionariosPreguntas::TYPE_DATE:
                $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? rescue(fn () => Carbon::createFromFormat('!Y-m-d', $value), null, false) : null;

                if (! $date instanceof Carbon || $date->format('Y-m-d') !== $value || $date->year < 1900 || $date->isFuture()) {
                    return [null, "«{$label}» debe ser una fecha válida"];
                }

                return $answer($value);

            default:
                if (mb_strlen($value) > 200) {
                    return [null, "«{$label}» admite hasta 200 caracteres"];
                }

                $column = self::COLUMNS[$this->normalize($label)][0] ?? null;

                if ($column === 'correo' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    return [null, "«{$label}» debe ser un correo válido"];
                }

                if (in_array($column, ['telefono', 'telefono_emergencia'], true) && ! preg_match('/^\+?[\d\s()-]{7,20}$/', $value)) {
                    return [null, "«{$label}» debe ser un teléfono válido"];
                }

                return $answer($value);
        }
    }

    /**
     * Columna fija de cada pregunta que la tiene.
     *
     * @return array<int, array{0: string, 1: int|null}>
     */
    private function fieldsByQuestion(array $participants): array
    {
        $questionIds = collect($participants)->flatMap(fn (array $participant) => array_column($participant['answers'], 'question_id'))->unique()->all();

        return EventosCuestionariosPreguntas::query()
            ->with('pregunta')
            ->whereIn('pk_evento_cuestionario_pregunta', $questionIds)
            ->get()
            ->mapWithKeys(fn (EventosCuestionariosPreguntas $question) => [
                $question->pk_evento_cuestionario_pregunta => self::COLUMNS[$this->normalize($question->pregunta->pregunta)] ?? [null, null],
            ])
            ->all();
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($text))));
    }

    /**
     * evento_boleto_carrera es latin1: se quitan los caracteres que no caben.
     */
    private function latin1(string $text): string
    {
        return (string) preg_replace('/[^\x{0000}-\x{00FF}]/u', '', $text);
    }
}
