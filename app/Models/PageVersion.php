<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageVersion extends Model
{
    use HasFactory;

    protected $fillable = ['page_id', 'number', 'image_path', 'display_path', 'thumb_path', 'uploaded_by'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Original print file, served through the session. */
    public function imageUrl(): string
    {
        return route('images.show', ['path' => $this->image_path]);
    }

    /** Screen copy when the queue has produced it; the original meanwhile. */
    public function displayUrl(): string
    {
        return route('images.show', ['path' => $this->display_path ?? $this->image_path]);
    }

    public function thumbUrl(): string
    {
        return route('images.show', ['path' => $this->thumb_path ?? $this->display_path ?? $this->image_path]);
    }
}
