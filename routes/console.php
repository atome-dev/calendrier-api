<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// ============ SCHEDULING ============

use Illuminate\Console\Scheduling\Schedule;

app(Schedule::class)->command('holidays:scheduled-sync')
    /*->weeklyOn(1, '02:00')*/
    ->timezone('Europe/Paris')
    ->onFailure(function () {
        logger()->error('holidays:scheduled-sync failed');
    })
    ->onSuccess(function () {
        logger()->info('holidays:scheduled-sync completed successfully');
    });
