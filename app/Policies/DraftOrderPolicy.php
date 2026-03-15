<?php

namespace App\Policies;

use App\Models\DraftOrder;
use App\Models\User;

class DraftOrderPolicy
{
    public function view(User $user, DraftOrder $draftOrder): bool
    {
        return (int) $draftOrder->user_id === (int) $user->id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }
}
