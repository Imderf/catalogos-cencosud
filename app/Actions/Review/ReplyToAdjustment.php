<?php

namespace App\Actions\Review;

use App\Events\Review\AdjustmentReplied;
use App\Events\Review\ReviewChanged;
use App\Models\Adjustment;
use App\Models\AdjustmentReply;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ReplyToAdjustment
{
    public function handle(User $by, Adjustment $adjustment, string $text): AdjustmentReply
    {
        Gate::forUser($by)->authorize('reply', $adjustment);
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Escribe la respuesta.');
        }

        $reply = $adjustment->replies()->create(['user_id' => $by->id, 'text' => $text]);
        AdjustmentReplied::dispatch($adjustment, $by);
        ReviewChanged::dispatch('respuesta', $adjustment->page->catalog->slug, $adjustment->page->number);

        return $reply;
    }
}
