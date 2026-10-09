<?php

namespace App\Http\Presenters;

use App\Domain\Review\AdjustmentRouter;
use App\Domain\Review\AgencyQueue;
use App\Domain\Review\Clocks;
use App\Domain\Review\PageStatus;
use App\Domain\Time\BusinessHours;
use App\Domain\Time\BusinessTime;
use App\Enums\Role;
use App\Models\Adjustment;
use App\Models\AdjustmentIntake;
use App\Models\Card;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Shapes domain objects into the props the React pages consume. */
class ReviewPresenter
{
    public function __construct(
        private readonly PageStatus $status,
        private readonly Clocks $clocks,
        private readonly BusinessTime $time,
    ) {}

    public function catalogSummary(Catalog $catalog): array
    {
        $t = $this->totals($catalog);
        $n = $catalog->pages->count();

        return [
            'id' => $catalog->id,
            'slug' => $catalog->slug,
            'name' => $catalog->name,
            'kind' => $catalog->kind?->value ?? 'octavilla',
            'kind_label' => $catalog->kind?->label() ?? 'Octavilla',
            'unsubmitted' => $catalog->pages->filter(fn (Page $p) => ! $p->isSubmitted())->count(),
            'brand' => $catalog->brand->name,
            'client' => $catalog->brand->clientLabel(),
            'pages_count' => $n,
            'published_at' => $catalog->published_at->toIso8601String(),
            'archived_at' => $catalog->archived_at?->toIso8601String(),
            'totals' => $t,
            'status_label' => $t['open'] > 0 ? 'En revisión' : ($t['total'] > 0 ? 'Al día' : 'Recién cargado'),
            'progress' => $n ? round($t['approved'] / $n * 100) : 0,
            'project' => $this->projectOf($catalog),
        ];
    }

    public function totals(Catalog $catalog): array
    {
        $total = $open = $approved = $defects = $overdue = $approvalsOverdue = 0;
        foreach ($catalog->pages as $page) {
            $total += $page->adjustments->count();
            $open += $page->adjustments->filter(fn (Adjustment $a) => $a->isOpen())->count();
            $defects += $page->defects->count();
            $overdue += $page->adjustments->filter(fn (Adjustment $a) => ($this->clocks->adjustmentRemaining($a) ?? 0) < 0)->count();
            if ($this->status->isApproved($page)) {
                $approved++;
            }
            if ($this->clocks->approvalOverdue($page)) {
                $approvalsOverdue++;
            }
        }

        return compact('total', 'open', 'approved', 'defects', 'overdue', 'approvalsOverdue');
    }

    public function pageCard(Page $page, User $user): array
    {
        $location = $this->status->location($page);
        $clock = $this->clocks->pageClock($page);
        $current = $page->currentVersion();

        return [
            'id' => $page->id,
            'number' => $page->number,
            'label' => $page->label(),
            'sectors' => $page->sectors->map(fn (Sector $s) => ['slug' => $s->slug, 'name' => $s->name, 'abbr' => $s->abbr])->values(),
            'sectors_label' => $page->sectors->pluck('name')->join(' · '),
            'version' => $page->current_version,
            'open' => $page->adjustments->filter(fn (Adjustment $a) => $a->isOpen())->count(),
            'total' => $page->adjustments->count(),
            'approved' => $this->status->isApproved($page),
            'submitted' => $page->isSubmitted(),
            'has_access' => $user->canActOn($page),
            'has_defect' => $page->defects->isNotEmpty(),
            'location' => $location->toArray(),
            'is_mine' => $location->role !== null && $this->actingRole($user) === $location->role,
            'clock' => $clock?->toArray(),
            'thumb_url' => $current?->thumbUrl(),
        ];
    }

    public function pageDetail(Page $page, User $user, ?int $viewing = null): array
    {
        $viewing = $viewing && $page->versions->contains('number', $viewing) ? $viewing : $page->current_version;
        $historical = $viewing < $page->current_version;
        $card = $this->pageCard($page, $user);
        $stage = $this->status->approvalStage($page);
        $phase = $this->status->phase($page);
        $suspended = $this->status->approvalSuspended($page);
        $alreadyApproved = $this->status->hasValidated($page, $user);
        $order = $phase === PageStatus::PRINTING ? PageStatus::printOrder() : PageStatus::validationOrder();
        $next = $stage ? ($order[array_search($stage, $order, true) + 1] ?? null) : null;
        $location = $this->status->location($page);
        $steps = function (array $roles, string $ph) use ($page, $stage, $phase, $suspended) {
            return array_map(function (Role $r) use ($page, $stage, $phase, $suspended, $ph) {
                $done = $page->approvals->first(fn ($a) => $a->version === $page->current_version && $a->role === $r && ($a->phase ?? PageStatus::VALIDATION) === $ph);

                return [
                    'role' => $r->value, 'label' => $r->short(),
                    'done_by' => $done?->user?->name ?? ($done ? '—' : null),
                    'active' => $phase === $ph && $stage === $r, 'suspended' => $suspended,
                ];
            }, $roles);
        };

        $can = fn (string $ability) => $this->can($user, $ability, $page, $historical);

        return [
            ...$card,
            'viewing_version' => $viewing,
            'is_historical' => $historical,
            'image_url' => $page->versions->firstWhere('number', $viewing)?->displayUrl(),
            'versions' => $page->versions->map(fn ($v) => [
                'number' => $v->number, 'date' => $v->created_at->toIso8601String(), 'url' => $v->displayUrl(), 'original_url' => $v->imageUrl(),
            ])->values(),
            'adjustments' => $page->adjustments->values()->map(fn (Adjustment $a, int $i) => $this->adjustment($a, $i + 1, $user))->all(),
            'approvals' => $page->approvals->sortBy('created_at')->values()->map(fn ($a) => [
                'role' => $a->role->value, 'role_label' => $a->role->short(), 'role_full' => $a->role->label(), 'phase' => $a->phase ?? 'validation',
                'by' => $a->user?->name ?? '—', 'date' => $a->created_at->toIso8601String(), 'version' => $a->version, 'delay' => $a->delay_minutes,
            ]),
            'defects' => $page->defects->sortBy('created_at')->values()->map(fn ($d) => [
                'by' => $d->user?->name ?? '—', 'role_label' => $d->role->short(), 'date' => $d->created_at->toIso8601String(),
                'version' => $d->version, 'reason' => $d->reason, 'type_label' => $d->type->label(),
            ]),
            'chain' => $steps(PageStatus::validationOrder(), PageStatus::VALIDATION),
            'print_chain' => $steps(PageStatus::printOrder(), PageStatus::PRINTING),
            'agency_step' => [
                'working' => $location->working,
                'label' => $phase === PageStatus::PREPARATION ? 'En preparación' : ($location->working ? $location->label : 'Listo'),
            ],
            'phase' => $phase,
            'stage' => $stage?->value,
            'next_role_label' => $next?->label(),
            'next_role_short' => $next?->short(),
            'already_approved' => $alreadyApproved,
            'already_printed' => $this->status->hasApprovedForPrint($page, $user),
            'suspended' => $suspended,
            'can' => [
                'comment' => $can('comment'), 'approve' => $can('validate'), 'approve_print' => $can('approvePrint'),
                'reopen' => $can('reopen'), 'upload_version' => $can('uploadVersion'), 'submit' => $can('submit'),
            ],
        ];
    }

    public function adjustment(Adjustment $a, int $index, User $user): array
    {
        $stage = $a->currentStage();
        $remaining = $this->clocks->adjustmentRemaining($a);
        $sla = $this->clocks->adjustmentSla($a);

        return [
            'id' => $a->id,
            'index' => $index,
            'type' => $a->type->value,
            'types' => array_map(fn ($t) => $t->value, $a->typeList()),
            'type_label' => $a->typeLabel(),
            'text' => $a->text,
            'box' => $a->box,
            'status' => $a->status->value,
            'is_defect' => $a->is_defect,
            'author' => $a->author?->name ?? '—',
            'origin_role_label' => $a->origin_role->short(),
            'date' => $a->created_at->toIso8601String(),
            'version' => $a->version,
            'resolved_in' => $a->resolved_in_version,
            'stage' => $stage?->value,
            'stage_label' => $stage ? ($stage === Role::Agencia ? 'Ogilvy' : $stage->short()) : null,
            'where' => $this->clocks->adjustmentWhere($a),
            'queued' => $this->clocks->isQueued($a),
            'task_priority' => $this->clocks->isQueued($a) ? app(AgencyQueue::class)->priorityLabel($a) : null,
            'sla' => $sla,
            'remaining' => $remaining !== null ? round($remaining, 2) : null,
            'consumed_pct' => $sla > 0 ? round(min(1, $this->clocks->adjustmentConsumed($a) / $sla), 4) : 0,
            'state' => $this->clocks->adjustmentState($a),
            'trace' => array_map(fn (string $r) => Role::from($r)->short(), AdjustmentRouter::trace($a->origin_role)),
            'validations' => $a->validations->map(fn ($v) => [
                'by' => $v->user?->name ?? '—', 'role_label' => $v->role->short(), 'note' => $v->note, 'date' => $v->created_at->toIso8601String(),
            ])->values(),
            'attachments' => $a->attachments->map(fn ($f) => [
                'id' => $f->id, 'kind' => $f->kind, 'name' => $f->name, 'size' => $f->size, 'image' => $f->isImage(),
                'url' => $f->isFile() ? route('adjustments.attachments.show', [$a->id, $f->id]) : $f->url,
            ])->values(),
            'replies' => $a->replies->map(fn ($r) => [
                'by' => $r->user?->name ?? '—', 'text' => $r->text, 'date' => $r->created_at->toIso8601String(),
            ])->values(),
            'board' => $this->boardLink($a, $user),
            'can' => [
                'validate' => Gate::forUser($user)->inspect('validate', $a)->allowed(),
                'resolve' => Gate::forUser($user)->inspect('resolve', $a)->allowed(),
                'reply' => Gate::forUser($user)->inspect('reply', $a)->allowed(),
                'update' => Gate::forUser($user)->inspect('update', $a)->allowed(),
                'delete' => Gate::forUser($user)->inspect('delete', $a)->allowed(),
            ],
        ];
    }

    public function workday(): array
    {
        $open = $this->time->isOpen();
        $now = $this->time->now();
        $label = $now->locale('es')->isoFormat('ddd D MMM · HH:mm');
        $w = $this->time->windowFor($now);
        $today = $w ? 'hoy '.BusinessHours::formatHour($w['from']).'–'.BusinessHours::formatHour($w['to']) : 'hoy cerrado';

        return [
            'open' => $open,
            'hours' => $this->time->hours()->label(),
            'label' => $open ? $label.' · dentro de horario ('.$today.')'
                : $label.' · fuera de horario ('.$today.'). Los cronómetros están en pausa y se reanudan el '
                  .$this->time->nextOpening()->locale('es')->isoFormat('ddd D MMM [a las] HH:mm').'.',
        ];
    }

    private function actingRole(User $user): ?Role
    {
        return $user->isOgilvySide() ? Role::Agencia : $user->role();
    }

    /** Ability result plus the reason, so the UI can explain a disabled button. */
    private function can(User $user, string $ability, Page $page, bool $historical): array
    {
        if ($historical) {
            return ['allowed' => false, 'reason' => 'Versión histórica: reemplazada por la v'.$page->current_version.'.'];
        }
        $r = Gate::forUser($user)->inspect($ability, $page);

        return ['allowed' => $r->allowed(), 'reason' => $r->allowed() ? null : $r->message()];
    }

    /** The board card of this catalog, only for people who can see boards. */
    private function projectOf(Catalog $catalog): ?array
    {
        $user = auth()->user();
        if (! $user || ! $user->can('projects.access')) {
            return null;
        }
        $card = Card::where('catalog_id', $catalog->id)->with('project:id,name,color')->first();

        return $card && $card->project ? ['id' => $card->project->id, 'name' => $card->project->name, 'color' => $card->project->color, 'card_id' => $card->id] : null;
    }

    /** Where this comment sits on Ogilvy's board (intake pending, deliverable, or dismissed). Hidden from the client. */
    private function boardLink(Adjustment $a, User $user): ?array
    {
        if (! $user->can('projects.access')) {
            return null;
        }
        $intake = AdjustmentIntake::where('adjustment_id', $a->id)->with('subtask:id,card_id,project_id')->first();
        if (! $intake || ! $intake->subtask) {
            return null;
        }

        return [
            'status' => $intake->status,
            'url' => route('projects.show', ['project' => $intake->subtask->project_id, 'tarjeta' => $intake->subtask->card_id, 'subtarea' => $intake->subtask_id]),
        ];
    }
}
