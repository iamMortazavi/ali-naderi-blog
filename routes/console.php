<?php

use Illuminate\Support\Facades\Schedule;

// cPanel → Cron Jobs, every minute:  php /home/USER/blog/artisan schedule:run >> /dev/null 2>&1

Schedule::command('blog:publish-due')->everyMinute()->withoutOverlapping();
Schedule::command('blog:sync --quiet-if-idle')->everyTenMinutes()->withoutOverlapping();
