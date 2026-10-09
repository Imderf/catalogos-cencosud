<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Presenters\ProjectPresenter;
use App\Models\Card;
use App\Models\Client;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Month view of every card with a due date across the projects the user can see.
 * Clients get a read-only version: only projects tied to a client, no people filter, no links into the boards.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $month = CarbonImmutable::createFromFormat('Y-m', (string) $request->query('mes', now()->format('Y-m'))) ?: CarbonImmutable::now();
        $month = $month->startOfMonth();
        $from = $month->startOfWeek();
        $to = $month->endOfMonth()->endOfWeek();

        $readOnly = ! $user->can('projects.access');
        $clientId = $readOnly ? null : ($request->integer('cliente') ?: null);
        $projects = ($readOnly ? Project::query()->whereNotNull('client_id') : Project::visibleTo($user))
            ->active()->when($clientId, fn ($q) => $q->where('client_id', $clientId))->orderBy('name')->get(['id', 'name', 'color', 'client_id']);
        $clients = $readOnly ? collect() : Client::where('is_active', true)->orderBy('name')->get(['id', 'name', 'color']);
        $projectId = $request->integer('proyecto') ?: null;
        $personId = $readOnly ? null : ($request->integer('persona') ?: null);
        $cards = Card::whereIn('project_id', $projects->pluck('id'))
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($personId, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($personId)))
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->with(['assignees', 'project:id,name,color', 'catalog:id,slug,name'])
            ->orderBy('due_date')->orderBy('position')->get();

        /* Subtasks with a date share the month with the cards. */
        $subtasks = Subtask::whereIn('project_id', $projects->pluck('id'))
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($personId, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($personId)))
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->with(['assignees', 'project:id,name,color', 'card:id,title'])
            ->orderBy('due_date')->orderBy('position')->get();

        return Inertia::render('calendar/index', [
            'month' => $month->format('Y-m'),
            'label' => ucfirst($month->locale('es')->translatedFormat('F Y')),
            'prev' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'today' => now()->toDateString(),
            'projects' => $projects,
            'clients' => $clients,
            'filter' => ['proyecto' => $projectId, 'persona' => $personId, 'cliente' => $clientId],
            'read_only' => $readOnly,
            'people' => $readOnly ? [] : User::whereHas('projectMemberships', fn ($q) => $q->whereIn('project_id', $projects->pluck('id')))->orderBy('name')->get()
                ->map(fn ($u) => app(ProjectPresenter::class)->person($u))->values(),
            'cards' => $cards->map(fn (Card $c) => [
                'id' => $c->id, 'title' => $c->title, 'due_date' => $c->due_date->toDateString(),
                'completed' => $c->completed_at !== null, 'priority' => $c->priority->value,
                'project' => ['id' => $c->project->id, 'name' => $c->project->name, 'color' => $c->project->color],
                'mine' => $c->assignees->contains('id', $user->id), 'catalog' => $c->catalog?->name,
                'assignees' => $c->assignees->map(fn ($u) => app(ProjectPresenter::class)->person($u))->values(),
                /* The client cannot open the board; a catalog card takes it to the catalog itself. */
                'url' => $readOnly ? ($c->catalog ? route('catalogs.show', $c->catalog->slug) : null) : route('projects.show', [$c->project_id, 'tarjeta' => $c->id]),
                'parent' => null,
            ])->concat($subtasks->map(fn (Subtask $s) => [
                'id' => 'sub-'.$s->id, 'title' => $s->title, 'due_date' => $s->due_date->toDateString(),
                'completed' => ! $s->isOpen(), 'priority' => $s->priority->value,
                'project' => ['id' => $s->project->id, 'name' => $s->project->name, 'color' => $s->project->color],
                'mine' => $s->assignees->contains('id', $user->id), 'catalog' => null,
                'assignees' => $s->assignees->map(fn ($u) => app(ProjectPresenter::class)->person($u))->values(),
                'url' => $readOnly ? null : route('projects.show', ['project' => $s->project_id, 'tarjeta' => $s->card_id, 'subtarea' => $s->id]),
                'parent' => $s->card?->title,
            ]))->sortBy('due_date')->values(),
        ]);
    }
}
