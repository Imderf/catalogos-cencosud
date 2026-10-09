<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = ['catalog_id', 'number', 'current_version', 'submitted_at', 'waiting_since', 'approval_alert_level'];

    protected function casts(): array
    {
        return ['waiting_since' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class)->orderBy('position');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderBy('number');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class)->orderBy('created_at');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }

    /** False while Ogilvy still prepares the page; nothing can be validated until it is sent. */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function currentVersion(): ?PageVersion
    {
        return $this->versions->firstWhere('number', $this->current_version);
    }

    /** @return list<string> */
    public function sectorSlugs(): array
    {
        return $this->sectors->pluck('slug')->all();
    }

    public function label(): string
    {
        return 'Página '.str_pad((string) $this->number, 2, '0', STR_PAD_LEFT);
    }
}
