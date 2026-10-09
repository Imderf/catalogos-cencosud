<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardAttachment extends Model
{
    protected $fillable = ['card_id', 'subtask_id', 'card_comment_id', 'custom_field_id', 'user_id', 'kind', 'name', 'path', 'url', 'mime', 'size'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isImage(): bool
    {
        return $this->isFile() && str_starts_with((string) $this->mime, 'image/');
    }

    public function isFile(): bool
    {
        return $this->kind === 'file';
    }
}
