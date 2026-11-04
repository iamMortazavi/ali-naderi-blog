# راه‌اندازی روی هاست سی‌پنل (بدون SSH)

این راهنما سایت را روی یک هاست سی‌پنل با PHP و MySQL بالا می‌آورد. به SSH، Node.js یا GitHub Actions نیازی نیست.

## پیش‌نیازهای هاست

- PHP نسخه‌ی ۸٫۳ یا بالاتر. در سی‌پنل: **Select PHP Version**.
- این اکستنشن‌ها روشن باشند: `intl`، `gd`، `pdo_mysql`، `mbstring`، `fileinfo`، `zip`، `openssl`، `dom`.
- یک دیتابیس MySQL. در سی‌پنل: **MySQL Databases** → یک دیتابیس و یک کاربر بسازید و کاربر را با همه‌ی دسترسی‌ها به دیتابیس اضافه کنید.

## ۱. ساختن بسته روی کامپیوتر خودتان

```bash
scripts/package.sh
```

فایل `build/blog-release.zip` ساخته می‌شود. این بسته کد و کتابخانه‌های لازم را دارد، ولی `.env`، تست‌ها و فایل‌های آپلودشده را ندارد.

## ۲. آپلود

1. در **File Manager** سی‌پنل، فایل zip را در پوشه‌ی خانه (کنار `public_html`، نه داخل آن) آپلود و Extract کنید. یک پوشه‌ی `blog` ساخته می‌شود.
2. در **Domains**، برای دامنه‌ی سایت، **Document Root** را روی `blog/public` بگذارید.
   - اگر سی‌پنل اجازه‌ی تغییر Document Root دامنه‌ی اصلی را نمی‌دهد: محتویات `blog/public` را داخل `public_html` کپی کنید. بعد در `public_html/index.php` مسیرهای `__DIR__.'/../` را به `__DIR__.'/../blog/` تغییر دهید. همان‌جا قبل از `$app->handleRequest` این خط را اضافه کنید: `$app->usePublicPath(__DIR__);`

## ۳. فایل تنظیمات `.env`

در پوشه‌ی `blog`، فایل `.env.example` را کپی کنید، اسمش را `.env` بگذارید و این‌ها را پر کنید:

| کلید | مقدار |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | آدرس سایت، مثلاً `https://alinaderi.ir` |
| `DB_DATABASE`، `DB_USERNAME`، `DB_PASSWORD` | از مرحله‌ی MySQL |
| `ADMIN_EMAIL`، `ADMIN_PASSWORD` | ایمیل و رمز ورود به پنل؛ رمز طولانی انتخاب کنید |
| `TELEGRAM_BOT_TOKEN`، `TELEGRAM_CHAT_ID` | اختیاری؛ ربات از @BotFather که در کانال ادمین باشد |
| `TELEGRAM_NOTIFY_CHAT_ID` | اختیاری؛ چت خصوصی برای پیام «منتشر شد» و «خطا» |
| `VAULT_GITHUB_TOKEN` | اختیاری؛ توکن fine-grained فقط‌خواندنی برای مخزن Obsidian |

`APP_KEY` را خالی بگذارید. مرحله‌ی بعد آن را می‌سازد.

## ۴. نصب با Cron Job (به‌جای SSH)

در سی‌پنل، **Cron Jobs** را باز کنید و یک کار با زمان «Once Per Minute» بسازید:

```bash
cd /home/USERNAME/blog && /usr/local/bin/php artisan blog:install >> storage/logs/install.log 2>&1
```

یک دقیقه صبر کنید. بعد در File Manager فایل `blog/storage/logs/install.log` را باز کنید. اگر آخرش `Done.` آمده:

1. این Cron Job را **حذف کنید**.
2. یک Cron Job دائمی با زمان «Once Per Minute» بسازید:

```bash
/usr/local/bin/php /home/USERNAME/blog/artisan schedule:run >> /dev/null 2>&1
```

این کار هر دقیقه نوشته‌های زمان‌بندی‌شده را منتشر می‌کند و نوشته‌های جدید را به تلگرام می‌فرستد. هر ۱۰ دقیقه هم یادداشت‌های Obsidian را همگام می‌کند.

اگر در `install.log` کلمه‌ی `MISSING` آمد، همان مورد را در Select PHP Version روشن کنید و دوباره همان Cron Job نصب را بگذارید.

## ۵. ورود به پنل

- آدرس: `https://دامنه/admin`
- نام کاربری و رمز: همان `ADMIN_EMAIL` و `ADMIN_PASSWORD` در `.env`

اول از همه در **تنظیمات سایت** آدرس سایت، عنوان‌ها، نویسنده و لینک‌ها را تنظیم کنید. بعد در تب **Obsidian**، مخزن گیت‌هاب یادداشت‌ها را وارد کنید.

## به‌روزرسانی

1. روی کامپیوتر خودتان: `scripts/package.sh`
2. فایل zip را آپلود و روی پوشه‌ی `blog` Extract کنید. فایل `.env` و پوشه‌ی `public/uploads` دست نمی‌خورند.
3. Cron Job نصب (مرحله‌ی ۴) را یک بار دیگر اجرا کنید تا دیتابیس و کش‌ها به‌روز شوند.

## پشتیبان‌گیری

از دو چیز پشتیبان بگیرید: دیتابیس (سی‌پنل → **Backup** یا **phpMyAdmin → Export**) و پوشه‌ی `blog/public/uploads`. یادداشت‌های Obsidian در مخزن گیت‌هاب خودشان هستند.
