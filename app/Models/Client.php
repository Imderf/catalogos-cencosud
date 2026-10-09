<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A company Ogilvy works for. Cencosud owns the Jumbo and Metro brands. */
class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color', 'is_active', 'logo_path'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('clients.logo', [$this->id, 'v' => $this->updated_at?->timestamp]) : null;
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
