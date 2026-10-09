<?php

namespace App\Actions\Review;

use App\Domain\Review\AdjustmentRouter;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Events\Review\AdjustmentCreated;
use App\Models\Adjustment;
use App\Models\Page;
use App\Models\User;
use App\Support\Box;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** A client marks a region and asks for an adjustment; it starts descending towards Ogilvy. */
class CreateAdjustment
{
    /**
     * @param  list<UploadedFile>  $files  PDFs or images the client attaches as reference
     * @param  ?string  $url  a link to resources (drive folder, brand site…)
     */
    /** @param  AdjustmentType|list<AdjustmentType>  $type  one kind, or several at once */
    public function handle(User $by, Page $page, AdjustmentType|array $type, string $text, Box $box, bool $defect = false, array $files = [], ?string $url = null): Adjustment
    {
        $types = Adjustment::normalizeTypes($type);
        if (! $defect) {
            Gate::forUser($by)->authorize('comment', $page);
        }
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Describe el ajuste.');
        }

        $role = $by->role() ?? Role::Marketing;
        $route = $defect ? [Role::Agencia->value] : AdjustmentRouter::routeFrom($role);
        $now = now();

        $adjustment = $page->adjustments()->create([
            'version' => $page->current_version,
            'author_id' => $by->id,
            'origin_role' => $role->value,
            'type' => $types[0]->value,
            'types' => array_map(fn (AdjustmentType $t) => $t->value, $types),
            'text' => $text,
            'box' => $box->toArray(),
            'status' => AdjustmentStatus::Open->value,
            'route' => $route,
            'is_defect' => $defect,
            'stage_since' => $now,
            'received_by_agency_at' => $route[0] === Role::Agencia->value ? $now : null,
        ]);
        $page->unsetRelation('adjustments');
        $this->attach($adjustment, $by, $files, $url);

        AdjustmentCreated::dispatch($adjustment, $by);

        /* The agency queue may have started (or held) its clock while listeners ran. */
        return $adjustment->refresh();
    }

    /** @param  list<UploadedFile>  $files */
    private function attach(Adjustment $adjustment, User $by, array $files, ?string $url): void
    {
        $url = trim((string) $url);
        if ($url !== '') {
            $adjustment->attachments()->create(['user_id' => $by->id, 'kind' => 'link', 'name' => Str::limit($url, 120), 'url' => $url]);
        }
        $dir = 'catalogos/'.$adjustment->page->catalog->slug.'/ajustes/'.$adjustment->id;
        foreach ($files as $file) {
            $name = $file->getClientOriginalName();
            $path = $file->storeAs($dir, Str::random(12).'-'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.strtolower($file->getClientOriginalExtension()), 'local');
            $adjustment->attachments()->create(['user_id' => $by->id, 'kind' => 'file', 'name' => $name, 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
        }
        if ($files || $url !== '') {
            $adjustment->unsetRelation('attachments');
        }
    }
}
