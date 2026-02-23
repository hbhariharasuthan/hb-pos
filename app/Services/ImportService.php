<?php
namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;

class ImportService
{
    public function import($file, $importClass): array
    {
        $import = new $importClass;
        Excel::import($import, $file);
        return $import->result();
    }
}
