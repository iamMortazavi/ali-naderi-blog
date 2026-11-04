# راه‌اندازی روی هاست سی‌پنل (بدون SSH)

این راهنما سایت را روی یک هاست اشتراکی سی‌پنل (Apache، PHP و MySQL) بالا می‌آورد. به SSH، Node.js، queue worker یا GitHub Actions نیازی نیست: نصب و به‌روزرسانی با یک **Cron Job** یک‌باره انجام می‌شود و کارهای دوره‌ای با یک Cron Job دائمی.

مراحل: پیش‌نیازها → ساختن بسته → آپلود → `.env` → Cron نصب → Cron دائمی → ورود به پنل → Obsidian → تلگرام. بعد از آن: به‌روزرسانی، پشتیبان‌گیری و رفع اشکال.

## پیش‌نیازهای هاست

### نسخه‌ی PHP

- **PHP نسخه‌ی ۸٫۴ یا بالاتر.** نسخه‌های پایین‌تر کار نمی‌کنند (کتابخانه‌های Symfony 8 و tempest/highlight به ۸٫۴ نیاز دارند).
- نسخه را در سی‌پنل انتخاب کنید: **Select PHP Version** (هاست‌های CloudLinux) یا **MultiPHP Manager** (هاست‌های EasyApache).

### اکستنشن‌ها

در **Select PHP Version → Extensions** این‌ها باید روشن باشند:

`intl`، `gd`، `pdo_mysql`، `mbstring`، `fileinfo`، `zip`، `openssl`، `ctype`، `dom`، `tokenizer`، `xmlreader`، `iconv`، `session`، `filter`، `curl`

دستور نصب (مرحله‌ی ۴) همه را بررسی می‌کند و هر کدام که نباشد را با کلمه‌ی `MISSING` گزارش می‌دهد.

### تنظیمات php.ini

در **Select PHP Version → Options** (یا **MultiPHP INI Editor** در هاست‌های EasyApache) این مقدارها را بگذارید تا آپلود فایل صوتی پادکست (تا ۱۰۰ مگابایت) کار کند:

| تنظیم | مقدار |
| --- | --- |
| `upload_max_filesize` | `128M` |
| `post_max_size` | `128M` |
| `memory_limit` | `256M` |
| `max_input_time` | `300` (اختیاری؛ برای آپلود با اینترنت کند) |

### دیتابیس

- **MySQL 8** یا **MariaDB 10.6** و بالاتر.
- در سی‌پنل: **MySQL Databases** → یک دیتابیس و یک کاربر بسازید و کاربر را با **ALL PRIVILEGES** به دیتابیس اضافه کنید. سی‌پنل جلوی اسم‌ها نام کاربری هاست را می‌گذارد، مثلاً `USER_blog`.

### SSL

سایت همیشه روی `https` باز می‌شود (فایل `public/.htaccess` درخواست‌های `http` را منتقل می‌کند). در سی‌پنل **SSL/TLS Status** را باز کنید و مطمئن شوید AutoSSL برای دامنه گواهی گرفته است.

### پیدا کردن مسیر دقیق PHP برای Cron

Cron Job همیشه با نسخه‌ای که برای سایت انتخاب کرده‌اید اجرا **نمی‌شود**؛ `php` یا `/usr/local/bin/php` ممکن است نسخه‌ی پیش‌فرض سرور باشد. برای همین در همه‌ی دستورهای Cron مسیر کامل PHP ۸٫۴ را بنویسید. معمولاً یکی از این دو است:

| نوع هاست | مسیر |
| --- | --- |
| EasyApache / MultiPHP (`ea-php84`) | `/opt/cpanel/ea-php84/root/usr/bin/php` |
| CloudLinux / Select PHP Version (`alt-php84`) | `/opt/alt/php84/usr/bin/php` |

اگر مطمئن نیستید، این Cron Job را با زمان «Once Per Minute» بسازید:

```bash
(ls -1 /opt/cpanel/ea-php*/root/usr/bin/php /opt/alt/php*/usr/bin/php; php -v) > $HOME/php-paths.txt 2>&1
```

یک دقیقه بعد فایل `php-paths.txt` را در پوشه‌ی خانه (File Manager) باز کنید، مسیری را که `84` دارد بردارید و **این Cron Job را حذف کنید**. در ادامه‌ی این راهنما به‌جای `PHP` همان مسیر را بنویسید؛ مثلاً:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php
```

`USER` هم همه‌جا نام کاربری سی‌پنل شماست.

## ۱. ساختن بسته روی کامپیوتر خودتان

روی کامپیوتری که Git، Composer و PHP ۸٫۴ دارد، در پوشه‌ی پروژه:

```bash
scripts/package.sh
```

- فایل `build/blog-release.zip` ساخته می‌شود (حدود ۲۰ مگابایت). داخلش یک پوشه‌ی `blog` است.
- بسته از **آخرین commit** ساخته می‌شود. اگر تغییر commit‌نشده داشته باشید، اسکریپت متوقف می‌شود.
- بسته کد، کتابخانه‌های لازم و فایل‌های ظاهر پنل را دارد؛ ولی `.env`، فایل‌های آپلودشده، تست‌ها و وبلاگ نمونه‌ی Obsidian را **ندارد**.

## ۲. آپلود و Document Root

1. در **File Manager**، به پوشه‌ی خانه بروید (`/home/USER`، کنار `public_html`، **نه داخل آن**).
2. فایل zip را آپلود کنید، روی آن راست‌کلیک کنید و **Extract** را بزنید. پوشه‌ی `/home/USER/blog` ساخته می‌شود.
3. فایل zip را پاک کنید.

حالا وب‌سرور باید فقط پوشه‌ی `blog/public` را ببیند. دو راه دارد:

### راه پیشنهادی: تغییر Document Root

در سی‌پنل **Domains** را باز کنید، کنار دامنه **Manage** را بزنید و **Document Root** را روی `blog/public` بگذارید. همین؛ به مرحله‌ی ۳ بروید.

### راه جایگزین: کپی در `public_html`

اگر سی‌پنل اجازه‌ی تغییر Document Root دامنه‌ی اصلی را نمی‌دهد:

1. در File Manager از **Settings** گزینه‌ی **Show Hidden Files** را روشن کنید (فایل `.htaccess` مخفی است).
2. **همه‌ی** محتویات `blog/public` (از جمله `.htaccess` و پوشه‌ی `uploads` با `.htaccess` داخلش) را در `public_html` کپی کنید.
3. فایل `public_html/index.php` را باز کنید (Edit) و:
   - در سه جا، `__DIR__.'/../` را به `__DIR__.'/../blog/` تغییر دهید (مسیرهای `storage/framework/maintenance.php`، `vendor/autoload.php` و `bootstrap/app.php`).
   - درست قبل از خط `$app->handleRequest(Request::capture());` این خط را اضافه کنید:

     ```php
     $app->usePublicPath(__DIR__);
     ```

   نتیجه باید این‌طور باشد:

   ```php
   if (file_exists($maintenance = __DIR__.'/../blog/storage/framework/maintenance.php')) {
       require $maintenance;
   }

   require __DIR__.'/../blog/vendor/autoload.php';

   $app = require_once __DIR__.'/../blog/bootstrap/app.php';

   $app->usePublicPath(__DIR__);

   $app->handleRequest(Request::capture());
   ```

4. در `.env` (مرحله‌ی ۳) این خط را اضافه کنید تا فایل‌های آپلودشده در `public_html/uploads` ذخیره شوند، هم از پنل و هم از همگام‌سازی Obsidian که با Cron اجرا می‌شود:

   ```dotenv
   UPLOADS_PATH=/home/USER/public_html/uploads
   ```

5. **در هر به‌روزرسانی** محتویات `blog/public` را دوباره در `public_html` کپی کنید، به‌جز `index.php` (نسخه‌ی ویرایش‌شده‌ی شما بماند) و پوشه‌ی `uploads` (فایل‌های شما). اگر یادداشت نسخه گفت `index.php` عوض شده، آن را کپی کنید و ویرایش بند ۳ را دوباره انجام دهید.

> پوشه‌ی `uploads` یک فایل `.htaccess` دارد که اجرای PHP و اسکریپت را در آن خاموش می‌کند و فایل‌های HTML و SVG را سرو نمی‌کند. هیچ‌وقت آن را پاک نکنید.

## ۳. فایل تنظیمات `.env`

1. در File Manager، با **Show Hidden Files** روشن، در پوشه‌ی `blog` فایل `.env.example` را **Copy** کنید و اسم کپی را `.env` بگذارید.
2. `.env` را باز کنید (Edit) و این‌ها را پر کنید. مقدارهای پیش‌فرض فایل برای هاست اشتراکی تنظیم شده‌اند؛ فقط ردیف‌هایی را که «باید پر شود» دارند حتماً عوض کنید.

| کلید | مقدار |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (اگر `true` باشد، جزئیات خطا و تنظیمات به همه نشان داده می‌شود) |
| `APP_KEY` | خالی بگذارید؛ مرحله‌ی ۴ آن را می‌سازد |
| `APP_URL` | باید پر شود: آدرس سایت با `https` و بدون `/` آخر، مثلاً `https://alinaderi.ir` |
| `APP_TIMEZONE` | `Asia/Tehran` |
| `DB_HOST` | معمولاً `localhost` |
| `DB_DATABASE`، `DB_USERNAME`، `DB_PASSWORD` | باید پر شود: از بخش «دیتابیس» بالا |
| `DB_TIMEZONE` | `+03:30` (ساعت تهران برای دیتابیس) |
| `SESSION_DRIVER`، `CACHE_STORE` | `file` |
| `QUEUE_CONNECTION` | `sync` (هاست اشتراکی queue worker ندارد) |
| `SESSION_SECURE_COOKIE` | `true`: کوکی ورود پنل فقط روی `https` فرستاده می‌شود. اگر سایت هنوز SSL ندارد، موقتاً `false` بگذارید وگرنه ورود به پنل ممکن نیست |
| `LOG_STACK` | `daily`: هر روز یک فایل لاگ در `storage/logs` |
| `LOG_DAILY_DAYS` | `14`: لاگ‌های قدیمی‌تر از ۱۴ روز پاک می‌شوند |
| `LOG_LEVEL` | `warning`: فقط هشدارها و خطاها ثبت می‌شوند |
| `UPLOADS_PATH` | فقط در «راه جایگزین» مرحله‌ی ۲: `/home/USER/public_html/uploads` |
| `ADMIN_NAME`، `ADMIN_EMAIL`، `ADMIN_PASSWORD` | باید پر شود: حساب مدیر کل که در نصب اول ساخته می‌شود؛ رمز طولانی انتخاب کنید |
| `TELEGRAM_BOT_TOKEN`، `TELEGRAM_CHAT_ID` | اختیاری؛ بخش ۸ |
| `TELEGRAM_NOTIFY_CHAT_ID` | اختیاری؛ چت خصوصی شما برای پیام‌های «منتشر شد» و «خطا» |
| `VAULT_GITHUB_TOKEN` | اختیاری؛ بخش ۷ |
| `VAULT_LOCAL_ROOT` | اختیاری؛ فقط اگر یادداشت‌ها در پوشه‌ای روی همین سرور باشند |

> **مهم:** سایت تنظیمات `.env` را کش می‌کند. هر بار که `.env` را عوض کردید، Cron Job نصب (مرحله‌ی ۴) را یک بار دیگر اجرا کنید تا تغییر اعمال شود.

## ۴. نصب با Cron Job (به‌جای SSH)

در سی‌پنل **Cron Jobs** را باز کنید و یک کار با زمان **Once Per Minute** (`* * * * *`) بسازید. به‌جای `PHP` مسیری را بگذارید که در «پیدا کردن مسیر دقیق PHP» پیدا کردید:

```bash
cd /home/USER/blog && PHP artisan blog:install >> storage/logs/install.log 2>&1
```

مثلاً:

```bash
cd /home/USER/blog && /opt/cpanel/ea-php84/root/usr/bin/php artisan blog:install >> storage/logs/install.log 2>&1
```

دستور `blog:install` به ترتیب این کارها را می‌کند و هر بار اجرای دوباره‌اش بی‌خطر است:

1. کش تنظیمات قبلی را پاک می‌کند تا `.env` تازه خوانده شود.
2. نسخه‌ی PHP و اکستنشن‌ها را بررسی می‌کند (`OK` یا `MISSING`).
3. اگر `APP_KEY` خالی باشد، آن را می‌سازد.
4. اتصال به دیتابیس را امتحان می‌کند.
5. سایت را در **حالت تعمیر** (maintenance) می‌برد، جدول‌های دیتابیس را به‌روز می‌کند و دوباره سایت را باز می‌کند.
6. در نصب اول: حساب مدیر کل را از `ADMIN_*` می‌سازد، و همه‌ی نوشته‌های موجود را «قبلاً به تلگرام فرستاده‌شده» علامت می‌زند تا کانال پر از نوشته‌های قدیمی نشود.
7. کش‌ها را از نو می‌سازد.

یک دقیقه صبر کنید. بعد در File Manager فایل `blog/storage/logs/install.log` را باز کنید:

- اگر آخرش `Done.` آمده: **این Cron Job را حذف کنید** و به مرحله‌ی ۵ بروید.
- اگر خط `MISSING` یا خطای دیگری هست: بخش «رفع اشکال» در انتهای همین راهنما را ببینید. Cron Job هر دقیقه دوباره اجرا می‌شود؛ بعد از رفع مشکل، دقیقه‌ی بعد دوباره امتحان می‌کند.

## ۵. Cron Job دائمی (`schedule:run`)

یک Cron Job دیگر با زمان **Once Per Minute** بسازید و **هرگز حذفش نکنید**:

```bash
PHP /home/USER/blog/artisan schedule:run >> /dev/null 2>&1
```

مثلاً:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /home/USER/blog/artisan schedule:run >> /dev/null 2>&1
```

این کار:

- هر دقیقه نوشته‌های زمان‌بندی‌شده‌ای را که وقتشان رسیده منتشر می‌کند و نوشته‌های تازه را به تلگرام می‌فرستد؛
- هر ۱۰ دقیقه یادداشت‌های Obsidian را همگام می‌کند؛
- هر روز گزارش‌های همگام‌سازی قدیمی‌تر از ۹۰ روز را پاک می‌کند.

خطاهای این کارها در `blog/storage/logs/laravel-YYYY-MM-DD.log` ثبت می‌شوند.

## ۶. ورود به پنل

- آدرس: `https://دامنه/admin`
- ایمیل و رمز: همان `ADMIN_EMAIL` و `ADMIN_PASSWORD` در `.env`

بعد از ورود:

1. از منوی حساب، رمز را عوض کنید. (بعد از آن می‌توانید `ADMIN_PASSWORD` را از `.env` پاک کنید.)
2. در **تنظیمات سایت**، آدرس سایت، عنوان‌ها، نویسنده، منو و لینک‌ها را تنظیم کنید.
3. اگر نویسنده‌ی دیگری دارید، در **کاربران** برایش حساب «نویسنده» بسازید. نویسنده فقط نوشته‌هایی را که خودش در پنل نوشته ویرایش می‌کند؛ نوشته‌های Obsidian در پنل فقط‌خواندنی‌اند.

## ۷. اتصال Obsidian

یادداشت‌ها در یک مخزن GitHub (ترجیحاً **خصوصی**) نگه‌داری می‌شوند و سایت هر ۱۰ دقیقه آن را می‌خواند. سایت هیچ‌وقت در مخزن چیزی نمی‌نویسد.

1. **مخزن:** در GitHub یک مخزن خصوصی بسازید، مثلاً `ali-naderi/vault`.
2. **Obsidian:** در **Settings → Community plugins** افزونه‌ی **Git** (obsidian-git) را نصب کنید، vault را به مخزن وصل کنید و در تنظیمات افزونه **Auto commit-and-sync interval** را مثلاً روی ۱۰ دقیقه بگذارید. یا هر وقت خواستید دستور «Git: Commit and sync» را بزنید.
3. **توکن فقط‌خواندنی:** در GitHub به **Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token** بروید:
   - **Repository access:** Only select repositories → فقط مخزن vault
   - **Permissions → Repository permissions → Contents:** Read-only (چیز دیگری لازم نیست)
   - برای توکن تاریخ انقضا بگذارید و قبل از انقضا آن را عوض کنید.
4. توکن را در `.env` بگذارید: `VAULT_GITHUB_TOKEN=github_pat_...` و Cron Job نصب (مرحله‌ی ۴) را یک بار دیگر اجرا کنید.
5. در پنل، **تنظیمات سایت → Obsidian**: منبع را «مخزن GitHub» بگذارید و نام مخزن، شاخه و پوشه‌ی نوشته‌ها (پیش‌فرض `posts`) را وارد کنید.

نتیجه‌ی هر همگام‌سازی در صفحه‌ی **همگام‌سازی‌ها** در پنل دیده می‌شود. اگر یک یادداشت مشکل داشته باشد (مثلاً `slug` یا `date` نادرست)، همان یادداشت با توضیح خطا گزارش و رد می‌شود و بقیه‌ی یادداشت‌ها منتشر می‌شوند. قواعد مشخصات یادداشت‌ها در [مدل داده](content-model.md#obsidian-note-properties) آمده است.

## ۸. تلگرام

1. در تلگرام با [@BotFather](https://t.me/BotFather) یک ربات بسازید و توکنش را در `TELEGRAM_BOT_TOKEN` بگذارید.
2. ربات را به کانال اضافه کنید و **ادمین** کنید (با دسترسی «Post messages»).
3. `TELEGRAM_CHAT_ID`: نام کانال با `@` (مثلاً `@alinaderi`) یا شناسه‌ی عددی کانال (با `-100` شروع می‌شود).
4. اختیاری، `TELEGRAM_NOTIFY_CHAT_ID`: شناسه‌ی عددی حساب خودتان (از ربات‌هایی مثل @userinfobot). قبلش یک بار به ربات خودتان پیام `/start` بدهید.
5. Cron Job نصب را یک بار دیگر اجرا کنید.

قواعد ارسال:

- هر نوشته **فقط یک بار** به کانال فرستاده می‌شود، حتی اگر بعداً ویرایش شود.
- فقط نوشته‌هایی که در **۴۸ ساعت گذشته** منتشر شده‌اند خودکار فرستاده می‌شوند، تا وارد کردن نوشته‌های قدیمی کانال را پر نکند. نوشته‌های قدیمی‌تر را در پنل با دکمه‌ی «ارسال به تلگرام» می‌توانید دستی بفرستید.
- اگر تلگرام پیامی را نپذیرد، چند بار دوباره امتحان می‌شود و بعد در پنل «ناموفق» نشان داده می‌شود. وضعیت «نامشخص» یعنی جواب تلگرام نرسید؛ قبل از ارسال دوباره، کانال را نگاه کنید.
- هر نوشته در پنل گزینه‌ی «ارسال به کانال تلگرام» دارد؛ در Obsidian مشخصه‌ی `telegram: false` همین کار را می‌کند.

## به‌روزرسانی

1. روی کامپیوتر خودتان، بعد از commit: `scripts/package.sh`
2. **اول** Cron Job نصب (مرحله‌ی ۴) را دوباره بسازید (Once Per Minute).
3. بعد فایل zip را در پوشه‌ی خانه آپلود و **Extract** کنید و اجازه دهید فایل‌های پوشه‌ی `blog` بازنویسی شوند. فایل `.env` و پوشه‌ی `public/uploads` در بسته نیستند و دست نمی‌خورند.
4. حداکثر یک دقیقه بعد از پایان Extract، دستور نصب دیتابیس و کش‌ها را به‌روز می‌کند. اگر وسط Extract هم اجرا شده باشد، اجرای دقیقه‌ی بعد کار را کامل می‌کند.
5. در `blog/storage/logs/install.log` دنبال یک `Done.` با ساعتی **بعد از** پایان Extract بگردید، بعد Cron Job نصب را حذف کنید.
6. اگر از «راه جایگزین» مرحله‌ی ۲ استفاده می‌کنید: محتویات `blog/public` را دوباره در `public_html` کپی کنید، به‌جز `index.php` و `uploads`.

## پشتیبان‌گیری

از این‌ها پشتیبان بگیرید:

| چه چیزی | چطور |
| --- | --- |
| دیتابیس | سی‌پنل → **Backup** → Download a MySQL Database Backup، یا **phpMyAdmin → Export** |
| فایل‌های آپلودشده | پوشه‌ی `blog/public/uploads` (در «راه جایگزین»: `public_html/uploads`)؛ در File Manager روی پوشه **Compress** و بعد Download |
| فایل `.env` | یک نسخه در جای امن (رمزها و `APP_KEY` در آن است) |

یادداشت‌های Obsidian در مخزن GitHub خودشان هستند و کد سایت در مخزن پروژه. برای بازگرداندن: بسته را نصب کنید، `.env` را برگردانید، دیتابیس را در phpMyAdmin **Import** کنید، پوشه‌ی `uploads` را برگردانید و Cron Job نصب را اجرا کنید.

## رفع اشکال

**در `install.log` خط `MISSING` هست.**
- `MISSING PHP ...`: Cron با نسخه‌ی قدیمی PHP اجرا شده. مسیر دقیق PHP ۸٫۴ را در دستور Cron بنویسید (بخش «پیدا کردن مسیر دقیق PHP»).
- `MISSING php extension ...`: همان اکستنشن را در **Select PHP Version → Extensions** روشن کنید. در هاست‌های EasyApache از مدیر هاست بخواهید آن را نصب کند.

**`Cannot reach the database`:** `DB_DATABASE`، `DB_USERNAME` و `DB_PASSWORD` را با **MySQL Databases** مقایسه کنید (با پیشوند `USER_`)، `DB_HOST=localhost` بگذارید و مطمئن شوید کاربر به دیتابیس اضافه شده است.

**خطای ۵۰۰ (Server Error):**
1. آخرین فایل `blog/storage/logs/laravel-YYYY-MM-DD.log` را باز کنید؛ علت خطا آن‌جاست.
2. اگر فایل لاگ ساخته نمی‌شود: دسترسی پوشه‌های `blog/storage` و `blog/bootstrap/cache` باید `755` و فایل‌ها `644` باشد.
3. اگر تازه `.env` را عوض کرده‌اید: Cron Job نصب را دوباره اجرا کنید.
4. `APP_DEBUG=true` را فقط برای چند دقیقه و فقط اگر لاگ کمکی نکرد امتحان کنید، و بعد حتماً به `false` برگردانید.

**صفحه‌ی ورود پنل مدام برمی‌گردد یا خطای ۴۱۹ می‌دهد:** سایت روی `https` باز نشده ولی `SESSION_SECURE_COOKIE=true` است. SSL را در **SSL/TLS Status** فعال کنید، یا موقتاً `false` بگذارید و Cron Job نصب را دوباره اجرا کنید.

**پیام‌های خطای فرم‌ها انگلیسی‌اند یا به شکل `validation.required` دیده می‌شوند:** `APP_LOCALE=fa` باید در `.env` باشد. بعد از عوض کردن آن Cron Job نصب را دوباره اجرا کنید. اگر درست نشد، مطمئن شوید پوشه‌ی `blog/lang/fa` کامل آپلود شده است.

**آپلود فایل بزرگ (پادکست) خطا می‌دهد:** مقدارهای `upload_max_filesize` و `post_max_size` (حداقل `128M`) و `memory_limit` (`256M`) را در بخش «تنظیمات php.ini» بررسی کنید. سقف هر آپلود ۱۰۰ مگابایت است؛ فایل‌های بزرگ‌تر را در Castbox، یوتیوب یا آپارات بگذارید و آدرسشان را وارد کنید.

**پنل بدون ظاهر (بدون CSS) نمایش داده می‌شود:** در «راه جایگزین»، پوشه‌های `css`، `js` و `fonts` از `blog/public` به `public_html` کپی نشده‌اند.

**ریدایرکت بی‌پایان بعد از فعال کردن Cloudflare:** در Cloudflare حالت SSL را روی **Full** بگذارید، نه Flexible.

**نوشته‌ی زمان‌بندی‌شده منتشر نمی‌شود یا Obsidian همگام نمی‌شود:** Cron Job دائمی (مرحله‌ی ۵) را با مسیر دقیق PHP بررسی کنید و صفحه‌ی **همگام‌سازی‌ها** در پنل را ببینید.
