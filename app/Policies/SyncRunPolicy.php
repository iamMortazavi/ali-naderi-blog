<?php

namespace App\Policies;

use App\Models\SyncRun;
use App\Models\User;

/** Import history: admins may read and delete it; runs are only written by the sync itself. */
class SyncRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SyncRun $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SyncRun $model): bool
    {
        return false;
    }

    public function delete(User $user, SyncRun $model): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
