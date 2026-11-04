<?php

namespace App\Policies;

use App\Models\Series;
use App\Models\User;

/** Editors may add and change; only admins may delete. */
class SeriesPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Series $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Series $model): bool
    {
        return true;
    }

    public function delete(User $user, Series $model): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
