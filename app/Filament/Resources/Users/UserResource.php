<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/** Panel accounts. Only admins see this. */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'تنظیمات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'کاربر';

    protected static ?string $pluralModelLabel = 'کاربران';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(255),
            TextInput::make('email')->label('ایمیل')->email()->required()->unique(ignoreRecord: true)
                ->extraInputAttributes(['dir' => 'ltr']),
            Select::make('role')->label('نقش')->options(User::ROLES)->default('editor')->required()
                ->helperText('مدیر کل: همه‌چیز. نویسنده: نوشته‌ها، صفحه‌ها و برچسب‌ها.'),
            TextInput::make('password')->label('رمز عبور')->password()->revealable()
                ->rule(Password::min(12))
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                ->helperText('حداقل ۱۲ حرف. برای تغییر ندادن، خالی بگذارید.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('email')->label('ایمیل')->searchable(),
                TextColumn::make('role')->label('نقش')->badge()->formatStateUsing(fn ($state) => User::ROLES[$state] ?? $state),
                TextColumn::make('created_at')->label('ساخته‌شده')->since(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
