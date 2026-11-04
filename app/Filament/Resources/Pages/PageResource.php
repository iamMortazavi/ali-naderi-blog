<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Models\Page;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Fixed pages such as "about", one per language. Address: /{slug} and /en/{slug}. */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'صفحه';

    protected static ?string $pluralModelLabel = 'صفحه‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('عنوان')->required()->maxLength(160),
            Select::make('locale')->label('زبان')->options(collect(Locales::ALL)->map(fn ($l) => $l['label']))
                ->default('fa')->required()->live(),
            TextInput::make('slug')->label('آدرس (لاتین)')->required()->maxLength(80)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->notIn(['posts', 'tags', 'series', 'archives', 'search', 'en', 'admin', 'sitemap', 'up'])
                ->prefix(fn (Get $get) => Locales::path($get('locale') ?: 'fa', '/'))
                ->extraInputAttributes(['dir' => 'ltr'])
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('locale', $get('locale'))),
            TextInput::make('description')->label('توضیح کوتاه (سئو)')->maxLength(300),
            MarkdownEditor::make('body')->label('متن')->required()->columnSpanFull()
                ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('pages')
                ->extraAttributes(fn (Get $get) => ['dir' => Locales::dir($get('locale') ?: 'fa')]),
            Toggle::make('in_sitemap')->label('در نقشه‌ی سایت')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable()->description(fn (Page $record) => $record->path()),
                TextColumn::make('locale')->label('زبان')->badge()->formatStateUsing(fn ($state) => Locales::label($state)),
                TextColumn::make('updated_at')->label('آخرین تغییر')->since(),
            ])
            ->recordActions([
                Action::make('view')->label('مشاهده')->icon(Heroicon::OutlinedEye)
                    ->url(fn (Page $record) => $record->path(), shouldOpenInNewTab: true),
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePages::route('/')];
    }
}
