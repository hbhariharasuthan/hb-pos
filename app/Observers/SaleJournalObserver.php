<?php

namespace App\Observers;

use App\Models\Sale;
use App\Services\JournalPostingService;

class SaleJournalObserver
{
    public function __construct(
        protected JournalPostingService $posting
    ) {}

    public function created(Sale $sale): void
    {
        $this->posting->postSale($sale);
    }

    public function updated(Sale $sale): void
    {
        $this->posting->updateSale($sale);
    }

    public function deleted(Sale $sale): void
    {
        $this->posting->unpost($sale);
    }
}
