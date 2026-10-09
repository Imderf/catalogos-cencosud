<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * /salud — for an uptime monitor. 200 while the database answers and both background
 * processes (scheduler cron and queue worker) have reported recently; 503 otherwise.
 * Without them no deadline alert fires and no email leaves.
 */
class HealthController extends Controller
{
    public const SCHEDULER_MAX_MINUTES = 10;

    public const QUEUE_MAX_MINUTES = 15;

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->database(),
            'scheduler' => $this->fresh('heartbeat:scheduler', self::SCHEDULER_MAX_MINUTES),
            'queue' => $this->fresh('heartbeat:queue', self::QUEUE_MAX_MINUTES),
        ];
        $ok = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks], $ok ? 200 : 503);
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'sin conexión a la base de datos'];
        }
    }

    private function fresh(string $key, int $maxMinutes): array
    {
        $at = Cache::get($key);
        $age = $at ? (int) CarbonImmutable::parse($at)->diffInMinutes(now(), true) : null;

        return ['ok' => $age !== null && $age <= $maxMinutes, 'last_at' => $at, 'max_minutes' => $maxMinutes];
    }
}
