<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Activity;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProjectPresenter;
use App\Http\Presenters\ReviewPresenter;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\Catalog;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ogilvy's home: my cards, what is due, project health, catalogs in review and recent activity. */
class DashboardController extends Controller
{
    public function __construct(private readonly ProjectPresenter $presenter, private readonly ReviewPresenter $review) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        /* Filters: project, person (assignee of a card or a subtask) and a date range on start/due dates. */
        $projectId = $request->integer('proyecto') ?: null;
        $personId = $request->integer('persona') ?: null;
        $from = $this->date($request->string('desde'));
        $to = $this->date($request->string('hasta'));
        $inRange = fn (?CarbonInterface $d) => $d !== null && (! $from || $d->gte($from)) && (! $to || $d->lte($to));

        $allProjects = Project::visibleTo($user)->active()->with(['client', 'owner', 'members', 'cards.assignees', 'lists'])->orderBy('name')->get();
        $projects = $projectId ? $allProjects->where('id', $projectId)->values() : $allProjects;
        $ids = $projects->pluck('id');
        $today = now()->startOfDay();
        $weekEnd = now()->endOfWeek();

        $open = Card::whereIn('project_id', $ids)->whereNull('completed_at')->with(['assignees', 'labels', 'catalog', 'project:id,name,color', 'list:id,name', 'subtasks.assignees'])->get()
            ->filter(fn (Card $c) => ! $personId || $c->assignees->contains('id', $personId) || $c->subtasks->contains(fn ($s) => $s->isOpen() && $s->assignees->contains('id', $personId)))
            ->filter(fn (Card $c) => (! $from && ! $to) || $inRange($c->due_date) || $inRange($c->start_date))
            ->values();
        $mySubtasks = Subtask::whereIn('project_id', $ids)->where('status', '!=', 'completed')->whereHas('assignees', fn ($q) => $q->whereKey($personId ?: $user->id))
            ->with(['assignees', 'project:id,name,color', 'card:id,title,board_list_id', 'card.list:id,name'])->get()
            ->filter(fn ($s) => (! $from && ! $to) || $inRange($s->due_date))->values();
        $focus = $personId ?: $user->id;
        $mine = $open->filter(fn (Card $c) => $c->assignees->contains('id', $focus))->sortBy(fn (Card $c) => $c->due_date?->timestamp ?? PHP_INT_MAX);
        $dueSoon = $open->filter(fn (Card $c) => $c->due_date && $c->due_date->lte($from || $to ? ($to ?? $weekEnd) : $weekEnd))->sortBy('due_date');

        $catalogs = Catalog::active()->with(['brand', 'pages.adjustments', 'pages.approvals', 'pages.defects'])->latest('published_at')->limit(8)->get();

        /* Completed per day: the chosen range (capped to 120 days) or the last 30 days. */
        $chartFrom = ($from ?? ($to ? $to->copy()->subDays(29) : now()->subDays(29)))->startOfDay();
        $chartTo = ($to ?? now())->endOfDay();
        if ($chartFrom->diffInDays($chartTo) > 120) {
            $chartFrom = $chartTo->copy()->subDays(120)->startOfDay();
        }
        $completedPerDay = collect();
        /* Reassign on each step: CarbonImmutable::addDay() returns a new instance and would loop forever otherwise. */
        for ($d = $chartFrom; $d->lte($chartTo); $d = $d->addDay()) {
            $completedPerDay[$d->toDateString()] = ['date' => $d->toDateString(), 'count' => 0];
        }
        $completedQuery = Card::whereIn('project_id', $ids)->whereNotNull('completed_at')
            ->when($personId, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($personId)));
        (clone $completedQuery)->whereBetween('completed_at', [$chartFrom, $chartTo])->get(['completed_at'])
            ->each(function (Card $c) use ($completedPerDay) {
                $k = $c->completed_at->toDateString();
                if ($completedPerDay->has($k)) {
                    $completedPerDay[$k] = ['date' => $k, 'count' => $completedPerDay[$k]['count'] + 1];
                }
            });
        $completedCount = ($from || $to) ? (clone $completedQuery)->whereBetween('completed_at', [$chartFrom, $chartTo])->count() : (clone $completedQuery)->count();
        $byPriority = $open->countBy(fn (Card $c) => $c->priority->value)->all();
        $byAssignee = $open->flatMap(fn (Card $c) => $c->assignees)->groupBy('id')->map(fn ($g) => ['user' => $this->presenter->person($g->first()), 'count' => $g->count()])->sortByDesc('count')->values();

        $activity = CardActivity::whereIn('project_id', $ids)
            ->when($personId, fn ($q) => $q->where('user_id', $personId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->with(['actor', 'card:id,title,project_id', 'card.project:id,name'])->latest('created_at')->latest('id')->limit(20)->get();

        $people = User::where(fn ($q) => $q->whereHas('projectMemberships', fn ($m) => $m->whereIn('project_id', $allProjects->pluck('id')))->orWhereIn('id', $allProjects->pluck('owner_id')))->orderBy('name')->get()
            ->map(fn (User $u) => $this->presenter->person($u))->values();

        return Inertia::render('dashboard/index', [
            'filters' => ['proyecto' => $projectId, 'persona' => $personId, 'desde' => $from?->toDateString(), 'hasta' => $to?->toDateString()],
            'options' => [
                'projects' => $allProjects->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name])->values(),
                'people' => $people,
            ],
            'stats' => [
                'projects' => $projects->count(),
                'open' => $open->count(),
                'mine' => $mine->count() + $mySubtasks->count(),
                'overdue' => $open->filter(fn (Card $c) => $c->due_date && $c->due_date->lt($today))->count(),
                'due_week' => $dueSoon->count(),
                'completed' => $completedCount,
            ],
            'mine' => $mine->map(fn (Card $c) => $this->row($c, $user))
                ->concat($mySubtasks->map(fn ($s) => [
                    ...$this->presenter->subtask($s, $user),
                    'project' => ['id' => $s->project->id, 'name' => $s->project->name, 'color' => $s->project->color],
                    'list' => $s->card?->list?->name, 'parent' => $s->card?->title,
                    'comments_count' => 0, 'attachments_count' => 0, 'completed_at' => null, 'labels' => [],
                    'url' => route('projects.show', ['project' => $s->project_id, 'tarjeta' => $s->card_id, 'subtarea' => $s->id]),
                ]))
                ->sortBy(fn ($r) => $r['due_date'] ?? '9999')->take(12)->values(),
            'due_soon' => $dueSoon->take(12)->map(fn (Card $c) => $this->row($c, $user))->values(),
            'projects' => $projects->map(fn (Project $p) => [
                ...$this->presenter->summary($p, $user), 'total' => $p->cards->count(), 'done' => $p->cards->whereNotNull('completed_at')->count(),
                'lists' => $p->lists->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'count' => $p->cards->where('board_list_id', $l->id)->count()])->values(),
            ])->values(),
            'completed_per_day' => $completedPerDay->values(),
            'by_priority' => $byPriority,
            'by_assignee' => $byAssignee,
            'catalogs' => $catalogs->map(fn (Catalog $c) => $this->review->catalogSummary($c))->values(),
            'activity' => $activity->map(fn (CardActivity $a) => [
                'id' => $a->id, 'actor' => $a->actor?->name ?? 'Sistema', 'text' => Activity::describe($a),
                'card' => $a->card?->title, 'project' => $a->card?->project?->name, 'at' => $a->created_at->toIso8601String(),
                'url' => $a->card ? route('projects.show', [$a->project_id, 'tarjeta' => $a->card_id]) : route('projects.show', $a->project_id),
            ])->values(),
        ]);
    }

    private function date(mixed $raw): ?CarbonImmutable
    {
        $raw = (string) $raw;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay() : null;
    }

    private function row(Card $c, $user): array
    {
        return [
            ...$this->presenter->card($c, $user),
            'project' => ['id' => $c->project->id, 'name' => $c->project->name, 'color' => $c->project->color],
            'list' => $c->list?->name,
            'url' => route('projects.show', [$c->project_id, 'tarjeta' => $c->id]),
        ];
    }
}
