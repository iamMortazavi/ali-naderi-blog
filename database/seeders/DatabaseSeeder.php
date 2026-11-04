<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * First-run data: the admin account (config/blog.php admin, from ADMIN_* in .env), starting settings and the about pages.
 * Safe to run again: nothing that already exists is overwritten.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // From config/blog.php (ADMIN_* in .env); env() would return null once the config is cached.
        $email = config('blog.admin.email');
        $password = config('blog.admin.password');
        if (! $email || ! $password) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.');
        }
        User::query()->firstOrCreate(['email' => $email], [
            'name' => config('blog.admin.name') ?: 'مدیر سایت',
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $starting = [
            'site' => ['url' => null, 'title_fa' => 'علی نادری', 'title_en' => 'Ali Naderi'],
            'author' => [
                'name_fa' => 'علی نادری', 'name_en' => 'Ali Naderi',
                'bio_fa' => 'اینجا درباره‌ی فناوری، یادگیری و زندگی می‌نویسم.',
                'bio_en' => 'I write about technology, learning and life.',
            ],
            'telegram' => ['channel_url' => '', 'languages' => ['fa'], 'hashtags' => true, 'footer_fa' => '', 'footer_en' => ''],
        ];
        foreach ($starting as $group => $value) {
            if (! SiteSettings::group($group) || SiteSettings::group($group) === SiteSettings::DEFAULTS[$group]) {
                SiteSettings::put($group, $value);
            }
        }

        Page::query()->firstOrCreate(['locale' => 'fa', 'slug' => 'about'], [
            'title' => 'درباره‌ی من',
            'description' => 'کمی درباره‌ی نویسنده‌ی این وبلاگ.',
            'body' => "سلام! این متن را می‌توانید از پنل مدیریت، بخش «صفحه‌ها»، ویرایش کنید.\n",
        ]);
        Page::query()->firstOrCreate(['locale' => 'en', 'slug' => 'about'], [
            'title' => 'About',
            'description' => 'A little about the author of this blog.',
            'body' => "Hi! You can edit this text in the admin panel, under Pages.\n",
        ]);
    }
}
