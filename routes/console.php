<?php

use Illuminate\Support\Facades\Schedule;

// cPanel → Cron Jobs, every minute:  php /home/USER/blog/artisan schedule:run >> /dev/null 2>&1
// withoutOverlapping(minutes): the lock expires after that many minutes even if a run was killed.

Schedule::command('blog:publish-due')->everyMinute()->withoutOverlapping(10);
Schedule::command('blog:sync --quiet-if-idle')->everyTenMinutes()->withoutOverlapping(30);

// Old sync history (App\Models\SyncRun keeps 90 days) and any other Prunable model.
Schedule::command('model:prune')->daily();
