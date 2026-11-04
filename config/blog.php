<?php

// Blog behaviour that is fixed per installation. Things the site owner changes live in the
// settings table (Settings page in the panel); secrets live in .env.

return [

    // First admin account, created by `php artisan blog:install` (database/seeders/DatabaseSeeder.php).
    'admin' => [
        'name' => env('ADMIN_NAME', 'مدیر سایت'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // Posts per list page on the public site.
    'per_page' => 10,

    // Posts shown on the home page before the "all posts" link.
    'home_posts' => 10,

    // Items in each RSS feed.
    'feed_items' => 30,

    'telegram' => [
        // Only posts published within this many hours are sent automatically, so importing an
        // old archive never floods the channel. Older posts can be sent from the panel.
        'max_age_hours' => 48,
        // A post Telegram refuses is retried this many times, then left for the panel.
        'max_attempts' => 3,
    ],

    // Folder of the Obsidian vault when Settings → Obsidian uses "a folder on this server".
    // The folder chosen in the panel must be inside it.
    'vault_root' => env('VAULT_LOCAL_ROOT', base_path()),

];
