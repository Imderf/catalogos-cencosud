<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'name', 'description', 'color', 'owner_id', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    public function lists(): HasMany
    {
        return $this->hasMany(BoardList::class)->orderBy('position');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class)->orderBy('name');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CardActivity::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomField::class)->orderBy('position');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Role of a user inside the project; admins and the owner are always managers. */
    public function roleOf(User $user): ?ProjectRole
    {
        if ($user->isAdmin() || $user->id === $this->owner_id) {
            return ProjectRole::Manager;
        }
        $member = $this->members->firstWhere('id', $user->id) ?? $this->members()->whereKey($user->id)->first();

        return $member ? ProjectRole::from($member->pivot->role) : null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    /** Projects a user may open: all for admins, otherwise the ones they belong to. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereHas('members', fn ($m) => $m->whereKey($user->id)));
    }
}
