<?php

namespace App\Modules\Dashboard\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación simple: los encabezados son las llaves de la primera fila.
 */
class RowsExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $headings  Encabezados si no hay filas.
     */
    public function __construct(private readonly array $rows, private readonly array $headings = []) {}

    public function array(): array
    {
        return array_map('array_values', $this->rows);
    }

    public function headings(): array
    {
        return $this->rows === [] ? $this->headings : array_keys($this->rows[0]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
