<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\JournalPostingService;

class ExpenseJournalObserver
{
    public function __construct(
        protected JournalPostingService $posting
    ) {}

    public function created(Expense $expense): void
    {
        $this->posting->postExpense($expense);
    }

    public function updated(Expense $expense): void
    {
        $this->posting->updateExpense($expense);
    }

    public function deleted(Expense $expense): void
    {
        $this->posting->unpost($expense);
    }
}
