<?php
namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

abstract class BaseImport implements ToCollection, WithHeadingRow
{
    protected int $success = 0;
    protected array $errors = [];

    abstract protected function model(array $row): void;

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                $this->model($row->toArray());
                $this->success++;
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'row' => $index + 2,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    public function result(): array
    {
        return [
            'success' => $this->success,
            'errors'  => $this->errors,
        ];
    }
}
