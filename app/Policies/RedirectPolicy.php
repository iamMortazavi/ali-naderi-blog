<?php

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;

/** Admin only. */
class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Redirect $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Redirect $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Redirect $model): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
