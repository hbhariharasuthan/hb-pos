<?php
namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

abstract class BaseImport implements ToCollection, WithHeadingRow
{
    protected int $success = 0;
    protected array $errors = [];
    protected array $skippedRows = [];

    abstract protected function model(array $row): void;

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                if (empty(array_filter($row->toArray()))) {
                    $this->skippedRows[] = $index + 2; // store row number
                    continue;
                }
    
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
            'skipped' => $this->skippedRows,
            'errors'  => $this->errors,
        ];
    }
}
