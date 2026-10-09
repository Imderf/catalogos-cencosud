<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use InvalidArgumentException;

class ProjectMemberController extends Controller
{
    use HandlesDomainErrors;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'role' => ['required', new Enum(ProjectRole::class)],
        ], [], ['user_id' => 'persona', 'role' => 'rol']);

        return $this->attempt(function () use ($project, $data) {
            $this->authorize('manage', $project);
            $user = User::findOrFail($data['user_id']);
            if (! $user->can('projects.access')) {
                throw new InvalidArgumentException('Solo personas de Ogilvy pueden ser miembros de un proyecto.');
            }
            $project->members()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);
        }, 'Miembro agregado.');
    }

    public function update(Request $request, Project $project, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', new Enum(ProjectRole::class)]]);

        return $this->attempt(function () use ($project, $user, $data) {
            $this->authorize('manage', $project);
            $project->members()->updateExistingPivot($user->id, ['role' => $data['role']]);
        }, 'Rol actualizado.');
    }

    public function destroy(Request $request, Project $project, User $user): RedirectResponse
    {
        return $this->attempt(function () use ($project, $user) {
            $this->authorize('manage', $project);
            if ($user->id === $project->owner_id) {
                throw new InvalidArgumentException('El dueño del proyecto no puede salir de él.');
            }
            $project->members()->detach($user->id);
            $project->cards()->each(fn ($card) => $card->assignees()->detach($user->id));
        }, 'Miembro retirado.');
    }
}
