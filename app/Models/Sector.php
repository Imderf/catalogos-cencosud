<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sector extends Model
{
    use HasFactory;

    public const GROUPS = ['food' => 'Food', 'non_food' => 'Non Food'];

    protected $fillable = ['slug', 'name', 'abbr', 'group', 'position'];

    public function groupLabel(): string
    {
        return self::GROUPS[$this->group] ?? $this->group;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
