<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class InventoryExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private Collection $data, private string $title = 'Laporan') {}

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        if ($this->data->isEmpty()) {
            return [];
        }

        $first = $this->data->first();
        return array_keys(is_array($first) ? $first : $first->toArray());
    }

    public function map($row): array
    {
        return is_array($row) ? array_values($row) : array_values($row->toArray());
    }

    public function title(): string
    {
        return $this->title;
    }
}
