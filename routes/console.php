<?php

use Illuminate\Support\Facades\Schedule;

// Daily database backup; clean old backups weekly.
Schedule::command('backup:clean')->weekly()->sundays()->at('01:00');
Schedule::command('backup:run --only-db')->daily()->at('01:30');
