<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| Every day at midnight, scan all bills that are 'unpaid' with amount_paid = 0
| and whose due_date is in the past, then mark them as 'late'.
*/
Schedule::command('bills:mark-late')->dailyAt('00:00');
