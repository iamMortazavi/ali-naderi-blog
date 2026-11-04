<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Actions\TelegramActions;
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
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class PostForm
{
    /** Images the panel accepts for covers and share images. No SVG: it can carry scripts. */
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
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
                            ->prefix(fn (Get $get) => Locales::path($get('locale') ?: Locales::DEFAULT, '/posts/'))
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
                            ->extraAttributes(fn (Get $get) => ['dir' => Locales::dir($get('locale') ?: Locales::DEFAULT)])
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
                                ->acceptedFileTypes(self::IMAGE_TYPES)
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
                                ->maxSize(102400)
                                ->helperText('MP3، M4A، OGG یا WAV. حداکثر حجم را هاست تعیین می‌کند.'),
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
                            ->options(Locales::options())
                            ->default(Locales::DEFAULT)
                            ->required()
                            ->live()
                            // A post can only be the translation of a post in the other language.
                            ->afterStateUpdated(fn (Set $set) => $set('translation_key', null)),
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
                            ->relationship('tags', 'name', fn ($query, Get $get) => $query->where('locale', $get('locale') ?: Locales::DEFAULT))
                            ->preload()
                            ->searchable()
                            ->createOptionForm([TextInput::make('name')->label('نام برچسب')->required()->maxLength(60)])
                            ->createOptionUsing(fn (array $data, Get $get) => Tag::findOrCreateFor($get('locale') ?: Locales::DEFAULT, $data['name'])->getKey()),
                        Select::make('series_id')
                            ->label('مجموعه')
                            ->relationship('series', 'name', fn ($query, Get $get) => $query->where('locale', $get('locale') ?: Locales::DEFAULT))
                            ->searchable()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->label('نام مجموعه')->required()->maxLength(120)])
                            ->createOptionUsing(fn (array $data, Get $get) => Series::findOrCreateFor($get('locale') ?: Locales::DEFAULT, $data['name'])->getKey()),
                        Select::make('translation_key')
                            ->label('ترجمه‌ی کدام نوشته است؟')
                            ->helperText('اگر این نوشته ترجمه‌ی نوشته‌ای در زبان دیگر است، آن را انتخاب کنید تا به هم لینک شوند. خالی یعنی ترجمه نیست.')
                            ->options(fn (Get $get, ?Post $record) => self::translationOptions($get('locale') ?: Locales::DEFAULT, $record))
                            ->searchable()
                            // Shown only when a translation really exists; the stored key of an unlinked post is kept as is.
                            ->formatStateUsing(fn (?Post $record) => $record ? self::linkedTranslationKey($record) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('locale', $get('locale') ?: Locales::DEFAULT))
                            ->validationMessages(['unique' => 'نوشته‌ی دیگری در همین زبان قبلاً ترجمه‌ی این نوشته شده است.']),
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
                    ])->hiddenOn('create'),
                    Section::make('تلگرام')->schema([
                        TextEntry::make('telegram_status')
                            ->label('وضعیت ارسال')
                            ->state(fn (?Post $record) => $record?->telegramMessage?->status)
                            ->badge()
                            ->placeholder('ارسال نشده'),
                        TextEntry::make('telegram_sent_at')
                            ->label('زمان ارسال')
                            ->state(fn (?Post $record) => $record?->telegramMessage?->sent_at)
                            ->formatStateUsing(fn ($state) => Dates::human($state, 'fa').' '.$state->format('H:i'))
                            ->visible(fn (?Post $record) => $record?->telegramMessage?->sent_at !== null),
                        TextEntry::make('telegram_error')
                            ->label('آخرین خطا')
                            ->state(fn (?Post $record) => TelegramActions::lastError($record))
                            ->color('danger')
                            ->visible(fn (?Post $record) => TelegramActions::lastError($record) !== null),
                    ])->hiddenOn('create'),
                ])->columnSpan(1),
            ]);
    }

    /**
     * Other-language posts this post may be linked to, as translation_key → title. A key already used by
     * another post in this post's language is left out: (translation_key, locale) is unique.
     *
     * @return array<string,string>
     */
    public static function translationOptions(string $locale, ?Post $record): array
    {
        $taken = Post::withTrashed()->select('translation_key')->where('locale', $locale)
            ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->getKey()));

        return Post::query()
            ->where('locale', '!=', $locale)
            ->whereNotIn('translation_key', $taken)
            ->newestFirst()
            ->pluck('title', 'translation_key')
            ->all();
    }

    /** The post's key when another-language post shares it (they are translations), else null. */
    public static function linkedTranslationKey(Post $post): ?string
    {
        $key = $post->getOriginal('translation_key');
        if (blank($key)) {
            return null;
        }
        $linked = Post::query()->where('translation_key', $key)->where('locale', '!=', $post->getOriginal('locale'))->exists();

        return $linked ? $key : null;
    }

    /** A translation key no other post uses in any language: the slug, or the slug with a number. */
    public static function unusedTranslationKey(string $slug, ?Post $ignore = null): string
    {
        $base = $slug !== '' ? $slug : Str::lower(Str::random(8));
        $key = $base;
        for ($n = 2; Post::withTrashed()->where('translation_key', $key)
            ->when($ignore?->exists, fn ($query) => $query->whereKeyNot($ignore->getKey()))->exists(); $n++) {
            $key = "{$base}-{$n}";
        }

        return $key;
    }
}
