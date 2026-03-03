<?php

namespace App\Policies;

use App\Models\ReportExport;
use App\Models\User;

class ReportExportPolicy
{
    public function view(User $user, ReportExport $export): bool
    {
        return $export->user_id === $user->id;
    }
}

