<?php

namespace App\Http\Controllers\Projects;

use App\Enums\CardPriority;
use App\Enums\CustomFieldType;
use App\Enums\ProjectRole;
use App\Enums\SubtaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Http\Presenters\ProjectPresenter;
use App\Models\Card;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use HandlesDomainErrors;

    private const DEFAULT_LISTS = [['Backlog', 'backlog'], ['Por hacer', 'preparation'], ['En curso', 'review'], ['Hecho', 'approved']];

    public function __construct(private readonly ProjectPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $archived = $request->boolean('archivados');
        $clientId = $request->integer('cliente') ?: null;
        $projects = Project::visibleTo($user)
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->active())
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->with(['client', 'owner', 'members', 'cards.assignees'])
            ->orderBy('name')->get();

        return Inertia::render('projects/index', [
            'projects' => $projects->map(fn (Project $p) => $this->presenter->summary($p, $user))->values(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get()->map(fn (Client $c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color, 'logo_url' => $c->logoUrl()])->values(),
            'people' => $this->people(),
            'filters' => ['archivados' => $archived, 'cliente' => $clientId],
            'templates' => Project::visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);
        $data = $this->validated($request);
        $request->validate(['template_id' => ['nullable', Rule::exists('projects', 'id')]], [], ['template_id' => 'proyecto de origen']);
        /* Copying a board's setup: lists, labels, fields and people come along; cards never do. */
        $template = $request->filled('template_id') ? Project::visibleTo($request->user())->with(['lists', 'labels', 'fields', 'members'])->find($request->integer('template_id')) : null;

        $project = DB::transaction(function () use ($request, $data, $template) {
            $project = Project::create([...$data, 'client_id' => $data['client_id'] ?? $template?->client_id, 'owner_id' => $request->user()->id]);
            $project->members()->attach($request->user(), ['role' => ProjectRole::Manager->value]);
            if ($template) {
                foreach ($template->lists as $l) {
                    $project->lists()->create(['name' => $l->name, 'kind' => $l->kind, 'position' => $l->position]);
                }
                foreach ($template->labels as $l) {
                    $project->labels()->create(['name' => $l->name, 'color' => $l->color]);
                }
                foreach ($template->fields as $f) {
                    $project->fields()->create(['name' => $f->name, 'type' => $f->type, 'options' => $f->options, 'required' => $f->required, 'position' => $f->position]);
                }
                foreach ($template->members as $m) {
                    if ($m->id !== $request->user()->id) {
                        $project->members()->attach($m->id, ['role' => $m->pivot->role]);
                    }
                }
            } else {
                foreach (self::DEFAULT_LISTS as $i => [$name, $kind]) {
                    $project->lists()->create(['name' => $name, 'kind' => $kind, 'position' => ($i + 1) * 1024]);
                }
            }

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', $template ? 'Proyecto creado con la configuración de "'.$template->name.'".' : 'Proyecto creado.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);
        $project->load(['client', 'owner', 'members', 'labels', 'fields', 'cards.assignees']);
        $project->load(['lists.cards' => fn ($q) => $q->withCount(['comments', 'attachments'])->with(['assignees', 'labels', 'catalog', 'subtasks.assignees', 'subtasks.intakes'])]);
        $openCard = $request->integer('tarjeta') ? Card::where('project_id', $project->id)->find($request->integer('tarjeta')) : null;
        $openSubtask = $openCard && $request->integer('subtarea') ? $openCard->subtasks()->find($request->integer('subtarea')) : null;

        return Inertia::render('projects/show', [
            'project' => $this->presenter->board($project, $request->user()),
            /* The open card travels in the URL (?tarjeta=) so every redirect back keeps the panel open. */
            'card' => $openCard ? $this->presenter->cardDetail($openCard, $request->user()) : null,
            'subtask' => $openSubtask ? $this->presenter->subtaskDetail($openSubtask, $request->user()) : null,
            'subtask_statuses' => SubtaskStatus::options(),
            'priorities' => CardPriority::options(),
            'field_types' => CustomFieldType::options(),
            /* Loaded only when the activity panel opens (partial reload). */
            'activity' => Inertia::optional(fn () => $project->activities()->with(['actor', 'card:id,title'])->latest('created_at')->latest('id')->limit(80)->get()->map(fn ($a) => $this->presenter->activityLine($a))->values()),
            'all_projects' => Project::visibleTo($request->user())->active()->with('lists')->orderBy('name')->get()->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'lists' => $p->lists->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->values()])->values(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get()->map(fn (Client $c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color, 'logo_url' => $c->logoUrl()])->values(),
            'people' => $this->people(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validated($request);

        return $this->attempt(function () use ($project, $data) {
            $this->authorize('manage', $project);
            $project->update($data);
        }, 'Proyecto actualizado.');
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        return $this->attempt(function () use ($project) {
            $this->authorize('manage', $project);
            $project->update(['archived_at' => now()]);
        }, 'Proyecto archivado.');
    }

    public function unarchive(Request $request, Project $project): RedirectResponse
    {
        return $this->attempt(function () use ($project) {
            $this->authorize('view', $project);
            if ($project->roleOf(auth()->user()) !== ProjectRole::Manager) {
                throw new \InvalidArgumentException('Solo los moderadores del proyecto pueden reactivarlo.');
            }
            $project->update(['archived_at' => null]);
        }, 'Proyecto reactivado.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Proyecto eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')],
        ], [], ['name' => 'nombre', 'color' => 'color', 'client_id' => 'cliente']);
    }

    /** Ogilvy people who can be members. */
    private function people(): array
    {
        return User::where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $u) => $u->can('projects.access'))
            ->map(fn (User $u) => $this->presenter->person($u))->values()->all();
    }
}
