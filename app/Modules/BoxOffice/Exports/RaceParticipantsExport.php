<?php

namespace App\Modules\BoxOffice\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Registro de participantes de una carrera, incluidos los boletos sin registrar.
 */
class RaceParticipantsExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    private readonly array $catalog;

    public function __construct(
        private readonly int $eventId,
        private readonly Collection $registered,
        private readonly Collection $pending,
    ) {
        $rules = config('boxoffice.rules.race_registration');
        $this->catalog = ($rules['events'][$eventId] ?? []) + $rules['default'];
    }

    public function headings(): array
    {
        return array_keys($this->row(null));
    }

    public function array(): array
    {
        $rows = $this->registered->map(fn ($participant) => $this->row($participant))->all();

        foreach ($this->pending as $ticket) {
            $rows[] = array_merge(array_fill_keys($this->headings(), ''), [
                'Folio' => $ticket->pk_evento_funcion_boleto,
                'Categoría' => $ticket->evento_localidad,
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    private function row(?object $p): array
    {
        $row = [
            'Folio' => $p?->pk_evento_funcion_boleto,
            'Categoría' => $p?->evento_localidad,
        ];

        if (isset($this->catalog['extra']) && ($this->catalog['extra_first'] ?? isset($this->catalog['extra_fixed_outside']))) {
            $row[$this->catalog['extra']['label']] = $p ? $this->extra($p) : null;
        }

        $row += [
            'No. participante' => $p?->num_participante,
            'Nombre' => $p?->nombre,
            'Correo' => $p?->correo,
            'Teléfono' => $p?->telefono,
            'Fecha de registro' => $p?->creacion_fecha,
            'Fecha de nacimiento' => $p?->fecha_nacimiento,
            'Edad' => $p && $p->fecha_nacimiento ? Carbon::parse($p->fecha_nacimiento)->age : null,
            'Talla' => $p ? $this->pick('sizes', $p->talla) : null,
        ];

        if (! ($this->catalog['partner'] ?? false)) {
            $row['Género'] = $p ? $this->pick('genders', $p->genero) : null;
            $row['Tipo de sangre'] = $p ? $this->pick('blood', $p->tipo_sangre) : null;
        }

        $row[($this->catalog['partner'] ?? false) ? 'Nombre de pareja' : 'Contacto de emergencia'] = $p?->nombre_emergencia;
        $row['Teléfono de emergencia'] = $p?->telefono_emergencia;
        $row['Discapacidad'] = $p?->discapacidad;

        if (isset($this->catalog['extra']) && ! isset($this->catalog['extra_fixed_outside'])) {
            $row[$this->catalog['extra']['label']] = $p ? $this->extra($p) : null;
        }

        if (isset($this->catalog['extra_b'])) {
            $row[$this->catalog['extra_b']] = $p?->tipo_extrab;
        }

        return $row;
    }

    private function extra(object $participant): ?string
    {
        $option = max(1, (int) $participant->tipo_extra);
        $fixed = $this->catalog['extra_fixed_outside'] ?? null;

        if ($fixed && ! in_array((int) $participant->pk_evento_localidad, $fixed['localities'], true)) {
            $option = $fixed['option'];
        }

        return $this->catalog['extra']['options'][$option - 1] ?? null;
    }

    private function pick(string $list, mixed $value): ?string
    {
        $index = (int) $value - 1;

        return $index >= 0 ? ($this->catalog[$list][$index] ?? null) : null;
    }
}
