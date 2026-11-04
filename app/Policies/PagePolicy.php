<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/** Editors may add and change; only admins may delete. */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Page $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Page $model): bool
    {
        return true;
    }

    public function delete(User $user, Page $model): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
