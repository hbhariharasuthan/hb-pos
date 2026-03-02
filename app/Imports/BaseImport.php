<?php
namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

abstract class BaseImport implements ToCollection, WithHeadingRow
{
    protected int $success = 0;
    protected array $errors = [];
    protected array $skippedRows = [];

    abstract protected function model(array $row): void;

    protected function formatExceptionMessage(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if ($e instanceof QueryException || str_contains($msg, 'Duplicate entry') || str_contains($msg, '23000')) {
            if (preg_match("/Duplicate entry '([^']*)' for key '([^']+)'/", $msg, $m)) {
                $value = $m[1];
                $key = $m[2];
                if (str_contains($key, 'gst_number')) {
                    return "Duplicate GST number: \"{$value}\". A customer with this GST number already exists.";
                }
                if (str_contains($key, 'email')) {
                    return "Duplicate email: \"{$value}\". A customer with this email already exists.";
                }
                if (str_contains($key, 'phone')) {
                    return "Duplicate phone: \"{$value}\". A customer with this phone already exists.";
                }
                return "Duplicate value \"{$value}\". This record already exists.";
            }
        }
        return $msg;
    }

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
                    'error' => $this->formatExceptionMessage($e),
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
