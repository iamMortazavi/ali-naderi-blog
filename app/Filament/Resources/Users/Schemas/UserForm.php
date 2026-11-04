<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(255),
            TextInput::make('email')->label('ایمیل')->email()->required()->maxLength(255)->unique(ignoreRecord: true)
                ->extraInputAttributes(['dir' => 'ltr']),
            Select::make('role')->label('نقش')->options(UserRole::class)->default(UserRole::Editor)->required()
                // Your own role cannot be changed here, so an admin never locks themself out.
                ->disabled(fn (?User $record) => $record?->is(auth()->user()) ?? false)
                ->rule(fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    $role = $value instanceof UserRole ? $value : UserRole::tryFrom((string) $value);
                    if ($record && $role !== UserRole::Admin && UserPolicy::isLastAdmin($record)) {
                        $fail('این تنها مدیر کل است؛ اول کاربر دیگری را مدیر کل کنید.');
                    }
                })
                ->helperText(fn (?User $record) => $record?->is(auth()->user())
                    ? 'نقش خودتان را نمی‌توانید تغییر دهید.'
                    : 'مدیر کل: همه‌چیز. نویسنده: نوشته‌های خودش، صفحه‌ها، برچسب‌ها و مجموعه‌ها.'),
            TextInput::make('password')->label('رمز عبور')->password()->revealable()
                ->rule(Password::min(12))
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                ->helperText('حداقل ۱۲ حرف. برای تغییر ندادن، خالی بگذارید.'),
        ]);
    }
}
