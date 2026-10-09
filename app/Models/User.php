<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'organization',
        'password',
        'is_active',
        'must_change_password',
        'notification_mode',
        'notification_sound',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'notification_sound' => 'boolean',
        ];
    }

    public function wantsDailyDigest(): bool
    {
        return $this->notification_mode === 'daily';
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class)->orderBy('position');
    }

    /** The single business role of the user (admin, agencia, marketing, planificacion, comercial). */
    public function role(): ?Role
    {
        $name = $this->getRoleNames()->first();

        return $name ? Role::from($name) : null;
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    public function isAgencia(): bool
    {
        return $this->hasRole(Role::Agencia->value);
    }

    /** Ogilvy people who only look: they see everything Ogilvy sees but cannot upload, approve or comment. */
    public function isReadOnly(): bool
    {
        return $this->hasRole(Role::Lectura->value);
    }

    /** Sits on Ogilvy's side of the table (acting or not): admins, agency and read-only users. */
    public function isOgilvySide(): bool
    {
        return $this->hasAnyRole([Role::Admin->value, Role::Agencia->value, Role::Lectura->value]);
    }

    /** Ogilvy side of the table and able to act: agency users and administrators. */
    public function actsAsAgency(): bool
    {
        return $this->hasAnyRole([Role::Admin->value, Role::Agencia->value]);
    }

    /** Cencosud side: a role that sits in the approval chain. */
    public function isClient(): bool
    {
        return in_array($this->role(), Role::chain(), true);
    }

    /** Whether the user may act on a page: shares at least one sector, or is not sector-bound. */
    public function canActOn(Page $page): bool
    {
        $mine = $this->sectorSlugs();
        if ($mine === null) {
            return true;
        }

        return count(array_intersect($mine, $page->sectorSlugs())) > 0;
    }

    /**
     * Sector slugs the user may act on. Null means "all sectors" (non-commercial roles).
     *
     * @return list<string>|null
     */
    public function sectorSlugs(): ?array
    {
        if (! $this->role()?->usesSectors()) {
            return null;
        }

        return $this->sectors->pluck('slug')->all();
    }
}
