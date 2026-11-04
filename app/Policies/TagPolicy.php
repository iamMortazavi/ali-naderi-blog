<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

/** Editors may add and change; only admins may delete. */
class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $model): bool
    {
        return true;
    }

    public function delete(User $user, Tag $model): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
