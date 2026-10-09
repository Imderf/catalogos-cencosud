<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Positions;
use App\Events\Projects\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\BoardList;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BoardListController extends Controller
{
    use HandlesDomainErrors;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']], [], ['name' => 'nombre']);

        return $this->attempt(function () use ($project, $data) {
            $this->authorize('manage', $project);
            $project->lists()->create(['name' => $data['name'], 'position' => Positions::next(BoardList::where('project_id', $project->id))]);
            BoardChanged::dispatch($project->id, 'list');
        }, 'Lista creada.');
    }

    public function update(Request $request, BoardList $list): RedirectResponse
    {
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:60'], 'kind' => ['sometimes', 'nullable', Rule::in(['backlog', 'preparation', 'review', 'approved'])]], [], ['name' => 'nombre', 'kind' => 'tipo']);

        return $this->attempt(function () use ($list, $data) {
            $this->authorize('manage', $list->project);
            if (array_key_exists('kind', $data) && $data['kind']) {
                /* One list per kind: the tag moves to this list. */
                $list->project->lists()->where('kind', $data['kind'])->whereKeyNot($list->id)->update(['kind' => null]);
            }
            $list->update($data);
            BoardChanged::dispatch($list->project_id, 'list');
        }, 'Lista renombrada.');
    }

    /** Drag of a column: the client sends the new float position. */
    public function move(Request $request, BoardList $list): RedirectResponse
    {
        $data = $request->validate(['position' => ['required', 'numeric']]);

        return $this->attempt(function () use ($list, $data) {
            $this->authorize('manage', $list->project);
            $list->update(['position' => (float) $data['position']]);
            Positions::rebalanceIfNeeded(BoardList::where('project_id', $list->project_id));
            BoardChanged::dispatch($list->project_id, 'list');
        }, '');
    }

    public function destroy(BoardList $list): RedirectResponse
    {
        return $this->attempt(function () use ($list) {
            $this->authorize('manage', $list->project);
            if ($list->cards()->exists()) {
                throw new InvalidArgumentException('Mueve o elimina las tarjetas de la lista antes de borrarla.');
            }
            $list->delete();
            BoardChanged::dispatch($list->project_id, 'list');
        }, 'Lista eliminada.');
    }
}
