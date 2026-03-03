<?php

namespace App\Observers;

use App\Models\Purchase;
use App\Services\JournalPostingService;

class PurchaseJournalObserver
{
    public function __construct(
        protected JournalPostingService $posting
    ) {}

    public function created(Purchase $purchase): void
    {
        $this->posting->postPurchase($purchase);
    }

    public function updated(Purchase $purchase): void
    {
        $this->posting->updatePurchase($purchase);
    }

    public function deleted(Purchase $purchase): void
    {
        $this->posting->unpost($purchase);
    }
}
