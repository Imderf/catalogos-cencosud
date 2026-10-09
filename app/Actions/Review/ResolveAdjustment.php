<?php

namespace App\Actions\Review;

use App\Domain\Review\PageStatus;
use App\Enums\AdjustmentStatus;
use App\Events\Review\AdjustmentResolved;
use App\Models\Adjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Ogilvy executed the adjustment on the current version, without uploading a new image. */
class ResolveAdjustment
{
    public function __construct(private readonly PageStatus $status) {}

    public function handle(User $by, Adjustment $adjustment): Adjustment
    {
        Gate::forUser($by)->authorize('resolve', $adjustment);

        return DB::transaction(function () use ($by, $adjustment) {
            $page = $adjustment->page;
            $adjustment->forceFill([
                'status' => AdjustmentStatus::Resolved->value,
                'resolved_in_version' => $page->current_version,
                'resolved_at' => now(),
            ])->save();

            $page->unsetRelation('adjustments');
            if (! $this->status->hasOpenAdjustments($page)) {
                /* The chain resumes: the client's approval clock starts now. */
                $page->forceFill(['waiting_since' => now(), 'approval_alert_level' => null])->save();
            }

            AdjustmentResolved::dispatch($adjustment, $by);

            return $adjustment->refresh();
        });
    }
}
