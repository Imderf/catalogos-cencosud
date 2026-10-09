<?php

namespace App\Actions\Review;

use App\Enums\AdjustmentType;
use App\Events\Review\ReviewChanged;
use App\Models\Adjustment;
use App\Models\User;
use App\Support\Box;
use Illuminate\Support\Facades\Gate;

/** The author fixes the wording, type or region of an adjustment nobody has validated yet. */
class UpdateAdjustment
{
    /** @param  AdjustmentType|list<AdjustmentType>  $type */
    public function handle(User $by, Adjustment $adjustment, AdjustmentType|array $type, string $text, ?Box $box = null): Adjustment
    {
        $types = Adjustment::normalizeTypes($type);
        Gate::forUser($by)->authorize('update', $adjustment);
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Describe el ajuste.');
        }
        $adjustment->forceFill(['type' => $types[0]->value, 'types' => array_map(fn (AdjustmentType $t) => $t->value, $types), 'text' => $text, 'box' => ($box ?? Box::fromArray($adjustment->box))->toArray()])->save();

        ReviewChanged::dispatch('ajuste', $adjustment->page->catalog->slug, $adjustment->page->number);

        return $adjustment->refresh();
    }
}
