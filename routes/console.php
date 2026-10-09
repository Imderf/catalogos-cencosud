<?php

use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('review:check-deadlines')->everyMinute()->withoutOverlapping();
Schedule::command('review:send-digest')->weekdays()->at('08:35');

/* Heartbeats read by /salud: the cron proves itself every minute, the worker every five. */
Schedule::call(fn () => Cache::forever('heartbeat:scheduler', now()->toIso8601String()))->everyMinute()->name('heartbeat:scheduler');
Schedule::job(new QueueHeartbeat)->everyFiveMinutes()->name('heartbeat:queue');
