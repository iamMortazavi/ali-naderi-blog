<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Panel accounts: admin only. An admin cannot delete their own account or the last admin. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }
        if ($user->is($model)) {
            return Response::deny('حساب خودتان را نمی‌توانید حذف کنید.');
        }
        if ($model->isAdmin() && self::isLastAdmin($model)) {
            return Response::deny('آخرین مدیر کل را نمی‌توانید حذف کنید.');
        }

        return Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** True when $model is the only admin account left. */
    public static function isLastAdmin(User $model): bool
    {
        return $model->isAdmin()
            && ! User::query()->where('role', UserRole::Admin)->whereKeyNot($model->getKey())->exists();
    }
}
