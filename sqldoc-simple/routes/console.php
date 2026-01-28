<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\SyncPendingChangesJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new SyncPendingChangesJob())
    ->everyFiveMinutes()
    ->name('sync-to-web')
    ->onOneServer()
    ->withoutOverlapping();
    //->runInBackground();
