<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ImportService;
use App\Imports\BrandImport;
use App\Imports\CategoryImport;
use App\Imports\CustomerImport;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    /**
     * Import uploaded CSV / Excel file
     */
    public function import(Request $request, ImportService $service)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv'
        ]);

        // Determine import class based on type
        $type = $request->route('type'); // e.g., 'brands' or 'products'

        $importClass = match($type) {
            'brands' => BrandImport::class,
            'products' => ProductImport::class,
            'categories' => CategoryImport::class,
            'customers' => CustomerImport::class,
            default => null
        };

        if (!$importClass) {
            return response()->json(['message' => 'Invalid import type'], 400);
        }

        $result = $service->import($request->file('file'), $importClass);

        return response()->json([
            'message' => 'Import completed',
            'result' => $result
        ]);
    }

    /**
     * Download CSV sample file for the given type
     */
    public function downloadSample(string $type)
    {
        $config = config("import_samples.$type");
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        $sheet->fromArray($config['headers'], null, 'A1');
        $sheet->fromArray($config['rows'], null, 'A2');
    
        $writer = new Xlsx($spreadsheet);
    
        $fileName = "{$type}_sample.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$fileName}\"");
        $writer->save('php://output');
        exit;
    }
}
