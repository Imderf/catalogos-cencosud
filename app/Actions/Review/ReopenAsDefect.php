<?php

namespace App\Actions\Review;

use App\Enums\AdjustmentType;
use App\Events\Review\PageReopenedAsDefect;
use App\Models\Defect;
use App\Models\Page;
use App\Models\User;
use App\Support\Box;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Post Production Defect: voids the approvals of this version, sends the page back to
 * Marketing and creates an adjustment flagged as defect that goes straight to Ogilvy.
 * Counted apart in the report: it is the metric of client errors on approved work.
 */
class ReopenAsDefect
{
    public function __construct(private readonly CreateAdjustment $createAdjustment) {}

    public function handle(User $by, Page $page, AdjustmentType $type, string $reason, ?Box $box = null): Defect
    {
        Gate::forUser($by)->authorize('reopen', $page);
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Describe el defecto encontrado.');
        }

        return DB::transaction(function () use ($by, $page, $type, $reason, $box) {
            $page->approvals()->where('version', $page->current_version)->delete();
            $page->forceFill(['waiting_since' => now(), 'approval_alert_level' => null])->save();
            $page->unsetRelation('approvals');

            $adjustment = $this->createAdjustment->handle($by, $page, $type, $reason, $box ?? Box::defectDefault(), defect: true);

            $defect = $page->defects()->create([
                'version' => $page->current_version, 'user_id' => $by->id, 'role' => $by->role()->value,
                'type' => $type->value, 'reason' => $reason, 'adjustment_id' => $adjustment->id,
            ]);

            PageReopenedAsDefect::dispatch($defect, $by);

            return $defect;
        });
    }
}
