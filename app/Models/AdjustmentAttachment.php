<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A PDF, image or link the client attached to an adjustment. Files live on the private disk under catalogos/{slug}/ajustes/. */
class AdjustmentAttachment extends Model
{
    protected $fillable = ['adjustment_id', 'user_id', 'kind', 'name', 'path', 'url', 'mime', 'size'];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class);
    }

    public function isFile(): bool
    {
        return $this->kind === 'file';
    }

    public function isImage(): bool
    {
        return $this->isFile() && str_starts_with((string) $this->mime, 'image/');
    }
}
