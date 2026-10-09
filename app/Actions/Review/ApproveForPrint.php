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

/** "Aprobar a imprenta": descends Comercial → Planificación → Marketing; Marketing's approval closes the page. */
class ApproveForPrint
{
    public function __construct(private readonly PageStatus $status, private readonly Clocks $clocks) {}

    public function handle(User $by, Page $page): Approval
    {
        Gate::forUser($by)->authorize('approvePrint', $page);

        return DB::transaction(function () use ($by, $page) {
            $stage = $this->status->printStage($page);
            $remaining = $this->clocks->approvalRemaining($page);

            $approval = $page->approvals()->create([
                'version' => $page->current_version,
                'user_id' => $by->id,
                'role' => $stage->value,
                'phase' => Approval::PRINTING,
                'delay_minutes' => (int) max(0, -($remaining ?? 0)),
            ]);
            $page->forceFill(['waiting_since' => now(), 'approval_alert_level' => null])->save();
            $page->unsetRelation('approvals');

            PageApproved::dispatch($approval, $by);

            return $approval;
        });
    }
}
