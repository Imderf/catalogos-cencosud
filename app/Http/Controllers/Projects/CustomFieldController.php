<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Positions;
use App\Enums\CustomFieldType;
use App\Events\Projects\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\CustomField;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/** Custom fields of a project: managers define them, every card shows them. */
class CustomFieldController extends Controller
{
    use HandlesDomainErrors;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validated($request);

        return $this->attempt(function () use ($project, $data) {
            $this->authorize('manage', $project);
            $project->fields()->create([...$data, 'position' => Positions::next(CustomField::where('project_id', $project->id))]);
            BoardChanged::dispatch($project->id, 'fields');
        }, 'Campo creado.');
    }

    public function update(Request $request, CustomField $field): RedirectResponse
    {
        $data = $this->validated($request);

        return $this->attempt(function () use ($field, $data) {
            $this->authorize('manage', $field->project);
            $field->update($data);
            BoardChanged::dispatch($field->project_id, 'fields');
        }, 'Campo actualizado.');
    }

    /** Reorder with the full list of ids. */
    public function reorder(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);

        return $this->attempt(function () use ($project, $data) {
            $this->authorize('manage', $project);
            foreach (array_values($data['order']) as $i => $id) {
                $project->fields()->whereKey($id)->update(['position' => ($i + 1) * Positions::GAP]);
            }
            BoardChanged::dispatch($project->id, 'fields');
        }, '');
    }

    public function destroy(CustomField $field): RedirectResponse
    {
        return $this->attempt(function () use ($field) {
            $this->authorize('manage', $field->project);
            $field->delete();
            BoardChanged::dispatch($field->project_id, 'fields');
        }, 'Campo eliminado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', new Enum(CustomFieldType::class)],
            'options' => ['nullable', 'array', 'max:50'],
            'options.*' => ['nullable', 'string', 'max:60'],
            'required' => ['sometimes', 'boolean'],
        ], [], ['name' => 'nombre', 'type' => 'tipo', 'options' => 'opciones']);
        $data['required'] = (bool) ($data['required'] ?? false);
        $type = CustomFieldType::from($data['type']);
        $data['options'] = $type->hasOptions() ? array_values(array_filter(array_map(fn ($o) => trim((string) $o), $data['options'] ?? []), fn ($o) => $o !== '')) : null;

        return $data;
    }
}
