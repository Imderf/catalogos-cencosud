<?php

namespace App\Domain\Review;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Collection;

/** Who gets told when the ball moves: the active users of a role, filtered by sector for the client. */
class Recipients
{
    /** @return Collection<int, User> */
    public function forRole(Role $role, ?Page $page = null): Collection
    {
        $users = User::query()->where('is_active', true)->role($role->value)->with('sectors')->get();
        if ($page === null || ! $role->usesSectors()) {
            return $users;
        }
        $page->loadMissing('sectors');

        return $users->filter(fn (User $u) => $u->canActOn($page))->values();
    }

    /** @param  list<Role>  $roles */
    public function forRoles(array $roles, ?Page $page = null): Collection
    {
        return collect($roles)->flatMap(fn (Role $r) => $this->forRole($r, $page))->unique('id')->values();
    }
}
