<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;
use App\Services\Obsidian\Note;
use App\Support\Dates;
use App\Support\Locales;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->disabled(fn (?Post $record) => $record?->isFromObsidian())
            ->columns(3)
            ->components([
                Callout::make('این نوشته از Obsidian می‌آید')
                    ->description('متن و مشخصاتش فقط در Obsidian ویرایش می‌شود و با هر همگام‌سازی جایگزین می‌شود. برای تغییر، یادداشت را در Obsidian ویرایش کنید.')
                    ->warning()
                    ->visible(fn (?Post $record) => $record?->isFromObsidian())
                    ->columnSpanFull(),

                Group::make([
                    Section::make()->schema([
                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->maxLength(160),
                        TextInput::make('slug')
                            ->label('آدرس (لاتین)')
                            ->helperText('حروف کوچک انگلیسی، عدد و خط تیره؛ مثلاً my-first-post. بعد از انتشار عوضش نکنید: آدرس قبلی خودکار به آدرس جدید هدایت می‌شود.')
                            ->prefix(fn (Get $get) => Locales::path($get('locale') ?: 'fa', '/posts/'))
                            ->required()
                            ->maxLength(80)
                            ->regex(Note::SLUG)
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('locale', $get('locale'))),
                        MarkdownEditor::make('body')
                            ->label('متن')
                            ->required()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('posts/attachments')
                            ->extraAttributes(fn (Get $get) => ['dir' => Locales::dir($get('locale') ?: 'fa')])
                            ->helperText('Markdown: ## تیتر، **پررنگ**، ==هایلایت==، `کد`، $فرمول$، > [!tip] کادر رنگی، و جدول.'),
                    ]),

                    Tabs::make()->tabs([
                        Tab::make('سئو')->schema([
                            Textarea::make('description')
                                ->label('توضیح کوتاه')
                                ->helperText('برای گوگل، پیش‌نمایش لینک‌ها و تلگرام. بین ۵۰ تا ۱۶۰ حرف بهترین است.')
                                ->rows(2)
                                ->maxLength(300)
                                ->live(debounce: 400)
                                ->hint(fn (?string $state) => mb_strlen((string) $state).' / 160'),
                        ]),
                        Tab::make('تصویر و رسانه')->schema([
                            FileUpload::make('cover_path')
                                ->label('تصویر کاور')
                                ->disk('public')
                                ->directory('covers')
                                ->image()
                                ->maxSize(5120)
                                ->helperText('برای پیش‌نمایش لینک‌ها به ۱۲۰۰×۶۳۰ برش داده می‌شود.'),
                            TextInput::make('cover_alt')
                                ->label('توضیح تصویر (alt)')
                                ->maxLength(200),
                            FileUpload::make('audio_url')
                                ->label('فایل صوتی / پادکست')
                                ->disk('public')
                                ->directory('audio')
                                ->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav'])
                                ->maxSize(102400),
                            TextInput::make('video_url')
                                ->label('ویدیو')
                                ->url()
                                ->placeholder('https://www.aparat.com/v/… یا https://youtu.be/…')
                                ->extraInputAttributes(['dir' => 'ltr']),
                        ]),
                    ]),
                ])->columnSpan(2),

                Group::make([
                    Section::make('انتشار')->schema([
                        Select::make('locale')
                            ->label('زبان')
                            ->options(collect(Locales::ALL)->map(fn ($l) => $l['label']))
                            ->default('fa')
                            ->required()
                            ->live(),
                        Select::make('status')
                            ->label('وضعیت')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('زمان انتشار')
                            ->seconds(false)
                            ->default(now())
                            ->live()
                            ->helperText(fn ($state) => $state ? Dates::human(Carbon::parse($state), 'fa').' — زمان آینده یعنی زمان‌بندی‌شده.' : null),
                        Toggle::make('send_to_telegram')
                            ->label('ارسال به کانال تلگرام')
                            ->default(true)
                            ->helperText('هر نوشته فقط یک بار ارسال می‌شود.'),
                    ]),
                    Section::make('دسته‌بندی')->schema([
                        Select::make('tags')
                            ->label('برچسب‌ها')
                            ->multiple()
                            ->relationship('tags', 'name', fn ($query, Get $get) => $query->where('locale', $get('locale') ?: 'fa'))
                            ->preload()
                            ->searchable()
                            ->createOptionForm([TextInput::make('name')->label('نام برچسب')->required()->maxLength(60)])
                            ->createOptionUsing(fn (array $data, Get $get) => Tag::findOrCreateFor($get('locale') ?: 'fa', $data['name'])->getKey()),
                        Select::make('series_id')
                            ->label('مجموعه')
                            ->relationship('series', 'name', fn ($query, Get $get) => $query->where('locale', $get('locale') ?: 'fa'))
                            ->searchable()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->label('نام مجموعه')->required()->maxLength(120)])
                            ->createOptionUsing(fn (array $data, Get $get) => Series::findOrCreateFor($get('locale') ?: 'fa', $data['name'])->getKey()),
                        Select::make('translation_key')
                            ->label('ترجمه‌ی کدام نوشته است؟')
                            ->helperText('اگر این نوشته ترجمه‌ی نوشته‌ای در زبان دیگر است، آن را انتخاب کنید تا به هم لینک شوند.')
                            ->options(fn (Get $get) => Post::query()
                                ->where('locale', '!=', $get('locale') ?: 'fa')
                                ->pluck('title', 'translation_key'))
                            ->searchable(),
                        Toggle::make('show_toc')
                            ->label('نمایش فهرست مطالب')
                            ->default(true),
                    ]),
                    Section::make('اطلاعات')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('word_count')->label('تعداد کلمات')->disabled()->dehydrated(false),
                            TextInput::make('reading_minutes')->label('زمان مطالعه (دقیقه)')->disabled()->dehydrated(false),
                        ]),
                        TextInput::make('source_path')->label('فایل در Obsidian')->disabled()->dehydrated(false)
                            ->visible(fn (?Post $record) => $record?->isFromObsidian()),
                    ])->visibleOn('edit'),
                ])->columnSpan(1),
            ]);
    }
}
