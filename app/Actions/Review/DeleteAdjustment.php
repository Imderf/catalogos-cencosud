<?php

namespace App\Actions\Review;

use App\Domain\Review\PageStatus;
use App\Events\Review\ReviewChanged;
use App\Models\Adjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** The author withdraws an adjustment nobody has validated yet; if it was the last open one, the chain resumes. */
class DeleteAdjustment
{
    public function __construct(private readonly PageStatus $status) {}

    public function handle(User $by, Adjustment $adjustment): void
    {
        Gate::forUser($by)->authorize('delete', $adjustment);

        DB::transaction(function () use ($adjustment) {
            $page = $adjustment->page;
            foreach ($adjustment->attachments as $file) {
                if ($file->isFile() && $file->path) {
                    Storage::disk('local')->delete($file->path);
                }
            }
            $adjustment->delete();
            $page->unsetRelation('adjustments');
            if (! $this->status->hasOpenAdjustments($page)) {
                $page->forceFill(['waiting_since' => now(), 'approval_alert_level' => null])->save();
            }
            ReviewChanged::dispatch('ajuste', $page->catalog->slug, $page->number);
        });
    }
}
