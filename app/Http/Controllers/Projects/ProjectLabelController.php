<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectLabelController extends Controller
{
    use HandlesDomainErrors;

    private const RULES = ['name' => ['required', 'string', 'max:40'], 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']];

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate(self::RULES, [], ['name' => 'nombre']);

        return $this->attempt(function () use ($project, $data) {
            $this->authorize('manage', $project);
            $project->labels()->create($data);
        }, 'Etiqueta creada.');
    }

    public function update(Request $request, Label $label): RedirectResponse
    {
        $data = $request->validate(self::RULES, [], ['name' => 'nombre']);

        return $this->attempt(function () use ($label, $data) {
            $this->authorize('manage', $label->project);
            $label->update($data);
        }, 'Etiqueta actualizada.');
    }

    public function destroy(Label $label): RedirectResponse
    {
        return $this->attempt(function () use ($label) {
            $this->authorize('manage', $label->project);
            $label->delete();
        }, 'Etiqueta eliminada.');
    }
}
