<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Admins manage every post; editors manage the panel posts they wrote.
 * Posts from Obsidian are edited only in Obsidian, so nobody may update them here.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): Response
    {
        if ($post->isFromObsidian()) {
            return Response::deny('نوشته‌های Obsidian فقط در Obsidian ویرایش می‌شوند.');
        }

        return $user->isAdmin() || $this->isAuthor($user, $post)
            ? Response::allow()
            : Response::deny('فقط نوشته‌های خودتان را می‌توانید ویرایش کنید.');
    }

    public function delete(User $user, Post $post): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }
        if ($post->isFromObsidian()) {
            return Response::deny('نوشته‌های Obsidian را فقط مدیر کل می‌تواند حذف کند.');
        }

        return $this->isAuthor($user, $post)
            ? Response::allow()
            : Response::deny('فقط نوشته‌های خودتان را می‌توانید حذف کنید.');
    }

    /** Bulk delete is offered to everyone; each selected post is then checked with delete(). */
    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function restore(User $user, Post $post): Response
    {
        return $user->isAdmin() || $this->isAuthor($user, $post)
            ? Response::allow()
            : Response::deny('فقط نوشته‌های خودتان را می‌توانید بازگردانید.');
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function replicate(User $user, Post $post): bool
    {
        return false;
    }

    private function isAuthor(User $user, Post $post): bool
    {
        return $post->author_id !== null && (int) $post->author_id === (int) $user->getKey();
    }
}
