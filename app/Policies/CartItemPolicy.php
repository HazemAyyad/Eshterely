<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;

class CartItemPolicy
{
    /**
     * Users can only update their own cart items.
     */
    public function update(User $user, CartItem $cartItem): bool
    {
        return (int) $cartItem->user_id === (int) $user->id;
    }

    /**
     * Users can only delete their own cart items.
     */
    public function delete(User $user, CartItem $cartItem): bool
    {
        return (int) $cartItem->user_id === (int) $user->id;
    }
}
