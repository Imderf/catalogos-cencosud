<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardComment extends Model
{
    protected $fillable = ['card_id', 'subtask_id', 'parent_id', 'user_id', 'adjustment_id', 'body'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CardAttachment::class)->orderBy('id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class);
    }

    /** The comment this one answers, when it is a reply. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Written by the client through the review; only its author could edit it, and the client has no board. */
    public function isEditableBy(User $user): bool
    {
        return $this->user_id === $user->id && $this->adjustment_id === null;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
