<?php

namespace App\Actions\Review;

use App\Enums\Role;
use App\Events\Review\AdjustmentValidated;
use App\Models\Adjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** An intermediate link validates the adjustment, optionally adds a note, and passes it down. */
class ValidateAdjustment
{
    public function handle(User $by, Adjustment $adjustment, ?string $note = null): Adjustment
    {
        Gate::forUser($by)->authorize('validate', $adjustment);

        return DB::transaction(function () use ($by, $adjustment, $note) {
            $adjustment->refresh(); // the deadline checker or the queue may have written it since it was loaded
            $adjustment->validations()->create([
                'user_id' => $by->id, 'role' => $by->role()->value, 'note' => trim((string) $note) ?: null,
            ]);
            $route = array_slice($adjustment->route, 1);
            $now = now();
            $adjustment->forceFill([
                'route' => $route,
                'stage_since' => $now,
                'alert_level' => null,
                'received_by_agency_at' => $route[0] === Role::Agencia->value ? $now : $adjustment->received_by_agency_at,
            ])->save();

            AdjustmentValidated::dispatch($adjustment, $by);

            return $adjustment->refresh();
        });
    }
}
