<?php

namespace App\Models;

use App\Enums\CatalogKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catalog extends Model
{
    use HasFactory;

    protected $fillable = ['brand_id', 'slug', 'name', 'kind', 'created_by', 'published_at', 'archived_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'archived_at' => 'datetime', 'kind' => CatalogKind::class];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderBy('number');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Catalogs still in play: listed, counted in the inbox and open to actions. */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
