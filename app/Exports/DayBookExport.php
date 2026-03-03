<?php

namespace App\Exports;

use App\Http\Controllers\API\ReportController;
use Illuminate\Support\LazyCollection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DayBookExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected ?string $dateFrom,
        protected ?string $dateTo,
        protected ReportController $reportController
    ) {}

    /**
     * Stream rows in chunks so large record sets don't load into memory.
     */
    public function collection(): LazyCollection
    {
        $chunkSize = ReportController::DAY_BOOK_CHUNK_SIZE;
        $offset = 0;

        return LazyCollection::make(function () use ($chunkSize, &$offset) {
            do {
                $rows = $this->reportController->getDayBookChunk(
                    $this->dateFrom,
                    $this->dateTo,
                    $chunkSize,
                    $offset
                );
                foreach ($rows as $row) {
                    yield $row;
                }
                $offset += $chunkSize;
            } while ($rows->count() === $chunkSize);
        });
    }

    public function headings(): array
    {
        return ['Date', 'Reference', 'Type', 'Amount', 'User ID'];
    }

    /**
     * @param object $row { date, ref, type, amount, user_id }
     */
    public function map($row): array
    {
        return [
            $row->date ?? '',
            $row->ref ?? '',
            $row->type ?? '',
            (float) ($row->amount ?? 0),
            $row->user_id ?? '',
        ];
    }
}
