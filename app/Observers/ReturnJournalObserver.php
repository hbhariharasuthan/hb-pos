<?php

namespace App\Observers;

use App\Models\ReturnModel;
use App\Services\JournalPostingService;

class ReturnJournalObserver
{
    public function __construct(
        protected JournalPostingService $posting
    ) {}

    public function created(ReturnModel $return): void
    {
        if ($return->status !== 'approved') {
            return;
        }
        $this->posting->postReturn($return);
    }

    public function updated(ReturnModel $return): void
    {
        // updateReturn unposts then re-posts unless status is cancelled
        $this->posting->updateReturn($return);
    }

    public function deleted(ReturnModel $return): void
    {
        $this->posting->unpost($return);
    }
}
