<?php

use Illuminate\Support\Facades\Cache;

test('health reports degraded until both background processes have reported', function () {
    Cache::flush();
    $this->get('/salud')->assertStatus(503)->assertJsonPath('status', 'degraded')->assertJsonPath('checks.database.ok', true);

    Cache::forever('heartbeat:scheduler', now()->toIso8601String());
    Cache::forever('heartbeat:queue', now()->subMinutes(20)->toIso8601String());
    $this->get('/salud')->assertStatus(503)->assertJsonPath('checks.scheduler.ok', true)->assertJsonPath('checks.queue.ok', false);

    Cache::forever('heartbeat:queue', now()->toIso8601String());
    $this->get('/salud')->assertOk()->assertJsonPath('status', 'ok');
});

test('error pages are in Spanish', function () {
    $this->get('/no-existe')->assertNotFound()->assertSee('Página no encontrada');
});
