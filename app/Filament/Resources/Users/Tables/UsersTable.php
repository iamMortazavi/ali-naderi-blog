<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('email')->label('ایمیل')->searchable(),
                TextColumn::make('role')->label('نقش')->badge(),
                TextColumn::make('created_at')->label('ساخته‌شده')->since(),
            ])
            ->recordActions([
                EditAction::make(),
                // Hidden for your own account and for the last admin (UserPolicy::delete).
                DeleteAction::make(),
            ]);
    }
}
