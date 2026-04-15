<?php

namespace App\Policies;

use App\Models\PurchaseAssistantRequest;
use App\Models\User;

class PurchaseAssistantRequestPolicy
{
    public function view(User $user, PurchaseAssistantRequest $purchaseAssistantRequest): bool
    {
        return (int) $user->id === (int) $purchaseAssistantRequest->user_id;
    }

    public function update(User $user, PurchaseAssistantRequest $purchaseAssistantRequest): bool
    {
        return $this->view($user, $purchaseAssistantRequest);
    }
}
