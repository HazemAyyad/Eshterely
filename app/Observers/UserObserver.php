<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Users\CustomerCodeService;

class UserObserver
{
    public function __construct(
        protected CustomerCodeService $customerCodeService
    ) {}

    public function created(User $user): void
    {
        if (trim((string) $user->customer_code) === '') {
            $this->customerCodeService->assignNextCode($user);
        }
    }
}
