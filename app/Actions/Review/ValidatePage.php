<?php

namespace App\Actions\Review;

use App\Domain\Review\Clocks;
use App\Domain\Review\PageStatus;
use App\Events\Review\PageApproved;
use App\Models\Approval;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** "Validar": the role whose turn it is gives its validation; the page moves up to the next link. */
class ValidatePage
{
    public function __construct(private readonly PageStatus $status, private readonly Clocks $clocks) {}

    public function handle(User $by, Page $page): Approval
    {
        Gate::forUser($by)->authorize('validate', $page);

        return DB::transaction(function () use ($by, $page) {
            $stage = $this->status->validationStage($page);
            $remaining = $this->clocks->approvalRemaining($page);

            $approval = $page->approvals()->create([
                'version' => $page->current_version,
                'user_id' => $by->id,
                'role' => $stage->value,
                'phase' => Approval::VALIDATION,
                'delay_minutes' => (int) max(0, -($remaining ?? 0)),
            ]);
            $page->forceFill(['waiting_since' => now(), 'approval_alert_level' => null])->save();
            $page->unsetRelation('approvals');

            PageApproved::dispatch($approval, $by);

            return $approval;
        });
    }
}
