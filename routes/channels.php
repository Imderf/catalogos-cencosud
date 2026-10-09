<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/* One channel for the whole review: every active user may listen; what they may do is decided per request. */
Broadcast::channel('review', fn (User $user) => (bool) $user->is_active);

/* Board channel doubles as presence: members see who else has the board open. */
Broadcast::channel('project.{id}', function (User $user, int $id) {
    $project = Project::find($id);
    if ($project === null || ! $user->can('projects.access') || $project->roleOf($user) === null) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});

/* Personal channel for live notifications. */
Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id) => $user->id === $id);
