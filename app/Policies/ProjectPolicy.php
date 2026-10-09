<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Boards are Ogilvy's own tool: the client never sees them. */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.access');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.access');
    }

    public function view(User $user, Project $project): Response
    {
        if (! $user->can('projects.access')) {
            return Response::deny('Los proyectos son una herramienta interna de Ogilvy.');
        }

        return $project->roleOf($user) ? Response::allow() : Response::deny('No eres miembro de este proyecto.');
    }

    /** Members work the cards. */
    public function work(User $user, Project $project): Response
    {
        if ($project->isArchived()) {
            return Response::deny('Este proyecto está archivado.');
        }

        return $this->view($user, $project);
    }

    /** Managers, the owner and admins shape the project. */
    public function manage(User $user, Project $project): Response
    {
        $r = $this->work($user, $project);
        if ($r->denied()) {
            return $r;
        }

        return $project->roleOf($user) === ProjectRole::Manager ? Response::allow() : Response::deny('Solo los moderadores del proyecto pueden hacer esto.');
    }

    public function delete(User $user, Project $project): Response
    {
        return $user->isAdmin() || $user->id === $project->owner_id ? Response::allow() : Response::deny('Solo el dueño del proyecto puede eliminarlo.');
    }
}
