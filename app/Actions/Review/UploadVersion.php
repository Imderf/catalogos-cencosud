<?php

namespace App\Actions\Review;

use App\Enums\AdjustmentStatus;
use App\Events\Review\VersionUploaded;
use App\Jobs\ProcessPageVersionImage;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Ogilvy uploads the corrected page as a new version. The approval chain and its clock
 * start from zero; the adjustments marked as resolved close in this version.
 */
class UploadVersion
{
    /** @param  list<int>  $resolvedAdjustmentIds */
    public function handle(User $by, Page $page, UploadedFile|string $image, array $resolvedAdjustmentIds = []): PageVersion
    {
        Gate::forUser($by)->authorize('uploadVersion', $page);

        return DB::transaction(function () use ($by, $page, $image, $resolvedAdjustmentIds) {
            $n = $page->current_version + 1;
            $version = $page->versions()->create([
                'number' => $n, 'uploaded_by' => $by->id,
                'image_path' => PublishCatalog::storeImage($page->catalog, $page->number, $n, $image),
            ]);

            if ($resolvedAdjustmentIds !== []) {
                $page->adjustments()->open()->whereIn('id', $resolvedAdjustmentIds)->update([
                    'status' => AdjustmentStatus::Resolved->value, 'resolved_in_version' => $n, 'resolved_at' => now(),
                ]);
            }

            $page->forceFill(['current_version' => $n, 'waiting_since' => now(), 'approval_alert_level' => null])->save();
            $page->unsetRelation('adjustments')->unsetRelation('versions')->unsetRelation('approvals');

            ProcessPageVersionImage::dispatch($version);
            VersionUploaded::dispatch($version, $by);

            return $version;
        });
    }
}
