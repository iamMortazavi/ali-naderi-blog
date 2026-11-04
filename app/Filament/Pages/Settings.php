<?php

namespace App\Filament\Pages;

use App\Services\Obsidian\VaultSync;
use App\Services\Telegram\TelegramPublisher;
use App\Support\SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * Every setting the site owner may change, saved to the settings table (App\Support\SiteSettings).
 * Secrets (Telegram bot token, GitHub token, FTP) stay in .env and are never shown here.
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'تنظیمات';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'تنظیمات سایت';

    protected static ?string $title = 'تنظیمات سایت';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(SiteSettings::all());
    }

    public function form(Schema $schema): Schema
    {
        $ltr = ['dir' => 'ltr'];

        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('سایت')->icon(Heroicon::OutlinedGlobeAlt)->schema([
                    TextInput::make('site.url')->label('آدرس سایت')->url()->required()
                        ->placeholder('https://alinaderi.ir')->extraInputAttributes($ltr)
                        ->helperText('بدون / در انتها. در لینک‌های اصلی صفحه‌ها، نقشه‌ی سایت و پیام‌های تلگرام به کار می‌رود.'),
                    Grid::make(2)->schema([
                        TextInput::make('site.title_fa')->label('عنوان سایت (فارسی)')->required()->maxLength(60),
                        TextInput::make('site.title_en')->label('عنوان سایت (انگلیسی)')->required()->maxLength(60)->extraInputAttributes($ltr),
                    ]),
                ]),
                Tab::make('ظاهر')->icon(Heroicon::OutlinedSwatch)->schema([
                    Radio::make('look.name')->label('ظاهر سایت')->required()
                        ->options(['paper' => 'کاغذی: کارت‌های سفید، شبیه سایت نمونه', 'ink' => 'جوهری: رنگ کاغذ کرم، بدون کارت، رنگ اصلی فیروزه‌ای'])
                        ->helperText('هر دو حالت روشن و تاریک دارند. برای دیدن قبل از تغییر، به آخر آدرس هر صفحه ?look=ink اضافه کنید.'),
                    Section::make('منوی بالا')->schema([
                        Repeater::make('menu.items')->label('')->reorderable()->collapsible()
                            ->itemLabel(fn (array $state) => ($state['label_fa'] ?? '').' → '.($state['path'] ?? ''))
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('label_fa')->label('عنوان فارسی')->required(),
                                    TextInput::make('label_en')->label('عنوان انگلیسی')->required()->extraInputAttributes($ltr),
                                    TextInput::make('path')->label('آدرس')->required()->extraInputAttributes($ltr)
                                        ->regex('#^(/[a-z0-9/_-]*|https?://\S+)$#')
                                        ->helperText('/tags، /archives، /search، /about یا یک لینک کامل.'),
                                ]),
                            ]),
                    ]),
                ]),
                Tab::make('نویسنده')->icon(Heroicon::OutlinedUser)->schema([
                    Grid::make(2)->schema([
                        TextInput::make('author.name_fa')->label('نام (فارسی)')->required(),
                        TextInput::make('author.name_en')->label('نام (انگلیسی)')->required()->extraInputAttributes($ltr),
                        Textarea::make('author.bio_fa')->label('معرفی کوتاه (فارسی)')->rows(2)->maxLength(300),
                        Textarea::make('author.bio_en')->label('معرفی کوتاه (انگلیسی)')->rows(2)->maxLength(300)->extraInputAttributes($ltr),
                    ]),
                    Repeater::make('social.links')->label('لینک‌های شبکه‌های اجتماعی')->reorderable()
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('name')->label('شبکه')->required()->options([
                                    'telegram' => 'Telegram', 'github' => 'GitHub', 'x' => 'X', 'linkedin' => 'LinkedIn',
                                    'instagram' => 'Instagram', 'youtube' => 'YouTube', 'email' => 'ایمیل',
                                ]),
                                TextInput::make('url')->label('آدرس')->required()->extraInputAttributes($ltr)
                                    ->regex('#^(https?://|mailto:)\S+$#')->placeholder('https://… یا mailto:…'),
                            ]),
                        ]),
                ]),
                Tab::make('تلگرام')->icon(Heroicon::OutlinedPaperAirplane)->schema([
                    Callout::make(fn () => app(TelegramPublisher::class)->configured() ? 'ربات تلگرام وصل است' : 'ربات تلگرام هنوز تنظیم نشده')
                        ->description('توکن ربات و شناسه‌ی کانال در فایل .env سرور هستند (TELEGRAM_BOT_TOKEN و TELEGRAM_CHAT_ID)، نه این‌جا.')
                        ->status(fn () => app(TelegramPublisher::class)->configured() ? 'success' : 'warning'),
                    TextInput::make('telegram.channel_url')->label('لینک عمومی کانال')->extraInputAttributes($ltr)
                        ->regex('#^(https://t\.me/\S+)?$#')->placeholder('https://t.me/…')
                        ->helperText('در پایین سایت به‌عنوان «کانال تلگرام» نمایش داده می‌شود.'),
                    CheckboxList::make('telegram.languages')->label('نوشته‌های کدام زبان به کانال برود؟')
                        ->options(['fa' => 'فارسی', 'en' => 'انگلیسی'])->columns(2),
                    Toggle::make('telegram.hashtags')->label('برچسب‌ها به‌صورت هشتگ'),
                    Grid::make(2)->schema([
                        TextInput::make('telegram.footer_fa')->label('خط پایانی پیام‌های فارسی')->maxLength(200)->placeholder('@channel'),
                        TextInput::make('telegram.footer_en')->label('خط پایانی پیام‌های انگلیسی')->maxLength(200)->extraInputAttributes($ltr),
                    ]),
                ]),
                Tab::make('سئو')->icon(Heroicon::OutlinedMagnifyingGlass)->schema([
                    FileUpload::make('seo.default_image')->label('تصویر پیش‌فرض پیش‌نمایش لینک‌ها')
                        ->disk('public')->directory('site')->image()->maxSize(4096)
                        ->helperText('برای صفحه‌ها و نوشته‌هایی که تصویر کاور ندارند. به ۱۲۰۰×۶۳۰ برش داده می‌شود.'),
                    Grid::make(2)->schema([
                        TextInput::make('seo.google_verification')->label('کد Google Search Console')->extraInputAttributes($ltr)
                            ->regex('/^[A-Za-z0-9_-]*$/')->helperText('فقط مقدار content در روش «HTML tag».'),
                        TextInput::make('seo.bing_verification')->label('کد Bing Webmaster')->extraInputAttributes($ltr)
                            ->regex('/^[A-Za-z0-9_-]*$/'),
                    ]),
                ]),
                Tab::make('Obsidian')->icon(Heroicon::OutlinedArrowPath)->schema([
                    Radio::make('sync.driver')->label('یادداشت‌ها از کجا خوانده شوند؟')->required()->live()
                        ->options([
                            'none' => 'خاموش: نوشته‌ها فقط در پنل نوشته می‌شوند',
                            'github' => 'مخزن GitHub: Obsidian با افزونه‌ی obsidian-git در آن ذخیره می‌کند',
                            'local' => 'پوشه‌ای روی همین سرور',
                        ]),
                    Grid::make(2)->visible(fn (Get $get) => $get('sync.driver') === 'github')->schema([
                        TextInput::make('sync.github_repo')->label('مخزن')->placeholder('owner/blog-vault')->extraInputAttributes($ltr)
                            ->regex('#^[\w.-]+/[\w.-]+$#')->required(fn (Get $get) => $get('sync.driver') === 'github')
                            ->helperText('برای مخزن خصوصی، توکن فقط‌خواندنی را در .env با نام VAULT_GITHUB_TOKEN بگذارید.'),
                        TextInput::make('sync.github_branch')->label('شاخه')->default('main')->extraInputAttributes($ltr),
                    ]),
                    TextInput::make('sync.local_path')->label('مسیر پوشه')->extraInputAttributes($ltr)
                        ->visible(fn (Get $get) => $get('sync.driver') === 'local')
                        ->required(fn (Get $get) => $get('sync.driver') === 'local')
                        ->helperText('مسیر کامل، یا نسبت به پوشه‌ی برنامه؛ مثلاً example-vault'),
                    TextInput::make('sync.posts_folder')->label('پوشه‌ی نوشته‌ها در vault')->default('posts')->extraInputAttributes($ltr)
                        ->visible(fn (Get $get) => $get('sync.driver') !== 'none')
                        ->helperText('فقط یادداشت‌های این پوشه که publish: true دارند منتشر می‌شوند. هر ۱۰ دقیقه خودکار بررسی می‌شود.'),
                ]),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('ذخیره‌ی تنظیمات')->submit('save')->keyBindings(['mod+s']),
                        Action::make('testSync')->label('آزمایش اتصال Obsidian')->color('gray')
                            ->action(fn () => $this->testSync()),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        foreach (array_keys(SiteSettings::DEFAULTS) as $group) {
            if (isset($data[$group])) {
                $value = $data[$group];
                if ($group === 'site') {
                    $value['url'] = rtrim((string) $value['url'], '/');
                }
                SiteSettings::put($group, $value);
            }
        }
        Notification::make()->title('تنظیمات ذخیره شد')->success()->send();
    }

    public function testSync(): void
    {
        $this->save();
        try {
            $vault = VaultSync::fromSettings();
            if (! $vault) {
                Notification::make()->title('همگام‌سازی خاموش است')->warning()->send();

                return;
            }
            $files = $vault->files();
            $folder = trim(SiteSettings::get('sync.posts_folder', 'posts'), '/').'/';
            $notes = collect(array_keys($files))->filter(fn ($p) => str_starts_with($p, $folder) && str_ends_with($p, '.md'))->count();
            Notification::make()->title('اتصال برقرار است')
                ->body(count($files)." فایل در vault، {$notes} یادداشت در پوشه‌ی {$folder}")->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('اتصال برقرار نشد')->body($e->getMessage())->danger()->send();
        }
    }
}
