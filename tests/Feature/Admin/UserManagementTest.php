<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Policies\UserPolicy;
use Livewire\Livewire;

class UserManagementTest extends AdminTestCase
{
    public function test_admin_cannot_demote_or_delete_themself(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(ManageUsers::class)
            ->assertTableActionHidden('delete', $admin)
            ->callTableAction('edit', $admin, data: ['name' => 'نام تازه'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('نام تازه', $admin->refresh()->name);

        // The role field is disabled for your own account: a forged value is never saved,
        // even when another admin exists.
        $this->admin();
        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $admin, data: ['role' => UserRole::Editor->value]);
        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_admin_manages_other_accounts(): void
    {
        $this->actingAs($this->admin());
        $editor = $this->editor();

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $editor, data: ['role' => UserRole::Admin->value])
            ->assertHasNoTableActionErrors();
        $this->assertSame(UserRole::Admin, $editor->refresh()->role);

        Livewire::test(ManageUsers::class)
            ->callTableAction('delete', $editor);
        $this->assertModelMissing($editor);
    }

    public function test_the_last_admin_is_protected(): void
    {
        $first = $this->admin();
        $this->assertTrue(UserPolicy::isLastAdmin($first));
        $this->assertFalse($first->can('delete', $first));

        $second = $this->admin();
        $this->assertFalse(UserPolicy::isLastAdmin($first));
        $this->assertTrue($second->can('delete', $first));
        $this->assertFalse(UserPolicy::isLastAdmin($this->editor()));
    }

    public function test_passwords_need_twelve_characters(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'x', 'email' => 'x@example.com', 'role' => UserRole::Editor->value, 'password' => 'short-pass'])
            ->assertHasActionErrors(['password']);

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'x', 'email' => 'x@example.com', 'role' => UserRole::Editor->value, 'password' => 'a-long-enough-password'])
            ->assertHasNoActionErrors();
        $this->assertSame(UserRole::Editor, User::query()->where('email', 'x@example.com')->first()->role);
    }
}
