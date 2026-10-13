<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync pending Razorpay orders every 10 minutes safely
Schedule::command('orders:sync-razorpay')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Auto-recover & reconcile pending payment gateway transactions (HyperPay, Tabby, Tamara) every 5 minutes
Schedule::command('payments:reconcile-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Daily recurring subscription delivery processing (06:00 AM Saudi Time - 1 day before delivery)
Schedule::command('subscriptions:process-deliveries --lead-days=1')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Process queued jobs (Email & SMS) continuously in background
Schedule::command('queue:work --stop-when-empty --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();



