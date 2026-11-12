<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Products are private: only their owner can see or change them.
 */
class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        return $product->user_id === $user->id;
    }

    public function update(User $user, Product $product): bool
    {
        return $product->user_id === $user->id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $product->user_id === $user->id;
    }
}
