<?php

namespace App\Http\Presenters;

use App\Domain\Projects\Activity;
use App\Domain\Review\PageStatus;
use App\Enums\CustomFieldType;
use App\Enums\ProjectRole;
use App\Models\AdjustmentIntake;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\CardAttachment;
use App\Models\CardComment;
use App\Models\Catalog;
use App\Models\CustomField;
use App\Models\Deliverable;
use App\Models\Label;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\User;

class ProjectPresenter
{
    public function summary(Project $project, User $user): array
    {
        $cards = $project->cards;
        $open = $cards->whereNull('completed_at');
        $today = now()->startOfDay();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'color' => $project->color,
            'client' => $project->client ? ['id' => $project->client->id, 'name' => $project->client->name, 'color' => $project->client->color, 'logo_url' => $project->client->logoUrl()] : null,
            'archived_at' => $project->archived_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
            'owner' => $project->owner ? ['id' => $project->owner->id, 'name' => $project->owner->name] : null,
            'my_role' => $project->roleOf($user)?->value,
            'members' => $project->members->map(fn (User $u) => $this->person($u, ProjectRole::from($u->pivot->role)->value))->values(),
            'counts' => [
                'open' => $open->count(),
                'overdue' => $open->filter(fn (Card $c) => $c->due_date && $c->due_date->lt($today))->count(),
                'mine' => $open->filter(fn (Card $c) => $c->assignees->contains('id', $user->id))->count(),
            ],
        ];
    }

    public function board(Project $project, User $user): array
    {
        $role = $project->roleOf($user);

        return [
            ...$this->summary($project, $user),
            'can' => [
                'manage' => $role === ProjectRole::Manager && ! $project->isArchived(),
                'work' => $role !== null && ! $project->isArchived(),
                'delete' => $user->isAdmin() || $user->id === $project->owner_id,
            ],
            'labels' => $project->labels->map(fn (Label $l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values(),
            'fields' => $project->fields->map(fn (CustomField $f) => $this->field($f))->values(),
            'lists' => $project->lists->map(fn (BoardList $l) => [
                'id' => $l->id, 'name' => $l->name, 'position' => $l->position, 'kind' => $l->kind,
                'cards' => $l->cards->map(fn (Card $c) => $this->card($c, $user))->values(),
            ])->values(),
        ];
    }

    public function card(Card $c, User $user): array
    {
        $today = now()->startOfDay();

        return [
            'id' => $c->id,
            'list_id' => $c->board_list_id,
            'title' => $c->title,
            'description' => $c->description,
            'position' => $c->position,
            'priority' => $c->priority->value,
            'priority_label' => $c->priority->label(),
            'start_date' => $c->start_date?->format('Y-m-d'),
            'due_date' => $c->due_date?->format('Y-m-d'),
            'overdue' => $c->due_date !== null && $c->completed_at === null && $c->due_date->lt($today),
            'completed_at' => $c->completed_at?->toIso8601String(),
            'assignees' => $c->assignees->map(fn (User $u) => $this->person($u))->values(),
            'labels' => $c->labels->pluck('id')->values(),
            'catalog' => $c->catalog ? $this->catalogOn($c->catalog) : null,
            'mine' => $c->assignees->contains('id', $user->id) || ($c->relationLoaded('subtasks') && $c->subtasks->contains(fn (Subtask $s) => $s->isOpen() && $s->assignees->contains('id', $user->id))),
            'created_by' => $c->created_by,
            'intake_pending' => $c->relationLoaded('subtasks') ? $c->subtasks->sum(fn (Subtask $s) => $s->relationLoaded('intakes') ? $s->intakes->where('status', 'pending')->count() : 0) : 0,
            'subtasks_total' => $c->subtasks_count ?? ($c->relationLoaded('subtasks') ? $c->subtasks->count() : $c->subtasks()->count()),
            'subtasks_open' => $c->relationLoaded('subtasks') ? $c->subtasks->filter(fn (Subtask $s) => $s->isOpen())->count() : $c->subtasks()->where('status', '!=', 'completed')->count(),
            'comments_count' => $c->comments_count ?? $c->comments()->count(),
            'attachments_count' => $c->attachments_count ?? $c->attachments()->count(),
            'has_description' => trim((string) $c->description) !== '',
            'cover' => ($cover = $c->relationLoaded('attachments') ? $c->attachments->first(fn ($a) => $a->isImage()) : $c->attachments()->get()->first(fn ($a) => $a->isImage())) ? route('attachments.show', $cover->id) : null,
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    public function field(CustomField $f): array
    {
        return ['id' => $f->id, 'name' => $f->name, 'type' => $f->type->value, 'type_label' => $f->type->label(), 'options' => $f->options ?? [], 'required' => (bool) $f->required];
    }

    /** Everything the side panel needs beyond the board card. */
    public function cardDetail(Card $c, User $user): array
    {
        $c->loadMissing(['assignees', 'labels', 'catalog', 'comments.author', 'comments.attachments', 'attachments.author', 'fieldValues', 'activities.actor', 'creator', 'subtasks.assignees', 'subtasks.intakes', 'subtasks.deliverables']);
        $values = $c->fieldValues->keyBy('custom_field_id');
        $fields = $c->project->fields;

        return [
            ...$this->card($c, $user),
            'created_by_name' => $c->creator?->name,
            'subtasks' => $c->subtasks->map(fn (Subtask $s) => $this->subtask($s, $user))->values(),
            'created_at' => $c->created_at?->toIso8601String(),
            'values' => $fields->mapWithKeys(function (CustomField $f) use ($values) {
                $v = $values->get($f->id)?->value;
                if ($f->type === CustomFieldType::Image) {
                    $v = $v ? route('attachments.show', (int) $v) : null;
                }

                return [$f->id => $v];
            })->all(),
            'comments' => $c->comments->map(fn (CardComment $m) => [
                'id' => $m->id, 'body' => $m->body, 'author' => $m->author ? $this->person($m->author) : null,
                'mine' => $m->user_id === $user->id, 'can_edit' => $m->isEditableBy($user), 'parent_id' => $m->parent_id, 'at' => $m->created_at->toIso8601String(), 'edited' => $m->updated_at->gt($m->created_at->addSeconds(5)),
                'attachments' => $m->attachments->map(fn (CardAttachment $a) => ['id' => $a->id, 'name' => $a->name, 'size' => $a->size, 'image' => $a->isImage(), 'url' => route('attachments.show', $a->id)])->values(),
                'client' => $this->clientOrigin($m),
            ])->values(),
            'attachments' => $c->attachments->map(fn (CardAttachment $a) => [
                'id' => $a->id, 'kind' => $a->kind, 'name' => $a->name, 'size' => $a->size, 'mime' => $a->mime,
                'url' => $a->isFile() ? route('attachments.show', $a->id) : $a->url,
                'image' => $a->isImage(),
                'author' => $a->author?->name, 'mine' => $a->user_id === $user->id, 'at' => $a->created_at->toIso8601String(),
            ])->values(),
            'activity' => $c->activities->take(60)->map(fn (CardActivity $a) => [
                'id' => $a->id, 'actor' => $a->actor?->name ?? 'Sistema', 'text' => Activity::describe($a), 'at' => $a->created_at->toIso8601String(),
            ])->values(),
        ];
    }

    /** Live progress of a linked catalog, shown on its card. */
    private function catalogOn(Catalog $catalog): array
    {
        $catalog->loadMissing(['pages.approvals', 'pages.adjustments', 'pages.defects']);
        $status = app(PageStatus::class);
        $pages = $catalog->pages;
        $approved = $pages->filter(fn ($p) => $status->isApproved($p))->count();
        $open = $pages->sum(fn ($p) => $p->adjustments->filter(fn ($a) => $a->isOpen())->count());
        $unsubmitted = $pages->filter(fn ($p) => ! $p->isSubmitted())->count();

        return [
            'slug' => $catalog->slug, 'name' => $catalog->name, 'kind_label' => $catalog->kind?->label() ?? 'Octavilla',
            'pages' => $pages->count(), 'approved' => $approved, 'open' => $open,
            'progress' => $pages->count() ? (int) round($approved / $pages->count() * 100) : 0,
            'status_label' => $unsubmitted === $pages->count() ? 'En preparación' : ($approved === $pages->count() ? 'Aprobado a imprenta' : ($open > 0 ? 'Con ajustes' : 'En revisión')),
        ];
    }

    public function subtask(Subtask $s, User $user): array
    {
        $today = now()->startOfDay();

        return [
            'id' => $s->id, 'card_id' => $s->card_id, 'title' => $s->title, 'position' => $s->position,
            'status' => $s->status->value, 'status_label' => $s->status->label(), 'closed' => $s->status->isClosed(),
            'priority' => $s->priority->value, 'priority_label' => $s->priority->label(),
            'due_date' => $s->due_date?->format('Y-m-d'),
            'overdue' => $s->due_date !== null && $s->isOpen() && $s->due_date->lt($today),
            'assignees' => $s->assignees->map(fn (User $u) => $this->person($u))->values(),
            'mine' => $s->assignees->contains('id', $user->id),
            'created_by' => $s->created_by,
            'automatic' => $s->isAutomatic(),
            'intake_pending' => $s->intakes_pending_count ?? ($s->relationLoaded('intakes') ? $s->intakes->where('status', 'pending')->count() : $s->intakes()->where('status', 'pending')->count()),
            'deliverables_open' => $s->relationLoaded('deliverables') ? $s->deliverables->filter(fn ($d) => $d->isOpen())->count() : $s->deliverables()->where('status', '!=', 'done')->count(),
        ];
    }

    /** Everything the subtask dialog needs. */
    public function subtaskDetail(Subtask $s, User $user): array
    {
        $s->loadMissing(['assignees', 'comments.author', 'comments.attachments', 'activities.actor', 'creator', 'page.catalog', 'intakes.adjustment.author', 'intakes.decider', 'deliverables.assignee']);
        $catalog = $s->page?->catalog;

        return [
            ...$this->subtask($s, $user),
            'page' => $s->page ? [
                'number' => $s->page->number, 'label' => $s->page->label(), 'catalog' => $catalog?->name,
                'url' => $catalog ? route('pages.show', [$catalog->slug, $s->page->number]) : null,
                'thumb_url' => $s->page->currentVersion()?->thumbUrl(),
            ] : null,
            'intakes' => $s->intakes->map(fn (AdjustmentIntake $i) => [
                'id' => $i->id, 'status' => $i->status, 'reason' => $i->reason,
                'decided_by' => $i->decider?->name, 'decided_at' => $i->decided_at?->toIso8601String(),
                'adjustment' => $i->adjustment ? [
                    'id' => $i->adjustment->id, 'type_label' => $i->adjustment->type->label(), 'text' => $i->adjustment->text,
                    'author' => $i->adjustment->author?->name ?? '—', 'role_label' => $i->adjustment->origin_role?->short(),
                    'open' => $i->adjustment->isOpen(), 'version' => $i->adjustment->version, 'at' => $i->adjustment->created_at->toIso8601String(),
                    'url' => $catalog ? route('pages.show', [$catalog->slug, $s->page->number, 'ajuste' => $i->adjustment->id]) : null,
                ] : null,
            ])->values(),
            'deliverables' => $s->deliverables->map(fn (Deliverable $d) => [
                'id' => $d->id, 'title' => $d->title, 'status' => $d->status, 'status_label' => Deliverable::STATUSES[$d->status] ?? $d->status,
                'assignee' => $d->assignee ? $this->person($d->assignee) : null, 'due_date' => $d->due_date?->format('Y-m-d'),
                'overdue' => $d->due_date !== null && $d->isOpen() && $d->due_date->lt(now()->startOfDay()),
                'from_client' => $d->intake_id !== null, 'done_at' => $d->done_at?->toIso8601String(),
            ])->values(),
            'deliverable_statuses' => collect(Deliverable::STATUSES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'description' => $s->description,
            'created_by_name' => $s->creator?->name,
            'created_at' => $s->created_at?->toIso8601String(),
            'comments' => $s->comments->map(fn (CardComment $m) => [
                'id' => $m->id, 'body' => $m->body, 'author' => $m->author ? $this->person($m->author) : null,
                'mine' => $m->user_id === $user->id, 'can_edit' => $m->isEditableBy($user), 'parent_id' => $m->parent_id, 'at' => $m->created_at->toIso8601String(), 'edited' => $m->updated_at->gt($m->created_at->addSeconds(5)),
                'attachments' => $m->attachments->map(fn (CardAttachment $a) => ['id' => $a->id, 'name' => $a->name, 'size' => $a->size, 'image' => $a->isImage(), 'url' => route('attachments.show', $a->id)])->values(),
                'client' => $this->clientOrigin($m),
            ])->values(),
            'activity' => $s->activities->take(40)->map(fn (CardActivity $a) => [
                'id' => $a->id, 'actor' => $a->actor?->name ?? 'Sistema', 'text' => Activity::describe($a), 'at' => $a->created_at->toIso8601String(),
            ])->values(),
        ];
    }

    /** Where a client-origin comment came from: the page and the review link. */
    private function clientOrigin(CardComment $m): ?array
    {
        if (! $m->adjustment_id) {
            return null;
        }
        $a = $m->adjustment()->with('page.catalog')->first();
        if (! $a || ! $a->page) {
            return null;
        }

        return ['role_label' => $a->origin_role?->short(), 'page_label' => $a->page->label(), 'open' => $a->isOpen(), 'url' => route('pages.show', [$a->page->catalog->slug, $a->page->number, 'ajuste' => $a->id])];
    }

    public function activityLine(CardActivity $a): array
    {
        return [
            'id' => $a->id, 'actor' => $a->actor ? $this->person($a->actor) : null, 'text' => Activity::describe($a),
            'card' => $a->card ? ['id' => $a->card->id, 'title' => $a->card->title] : null, 'at' => $a->created_at->toIso8601String(),
        ];
    }

    public function person(User $u, ?string $role = null): array
    {
        return array_filter([
            'id' => $u->id, 'name' => $u->name,
            'initials' => collect(explode(' ', trim($u->name)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join(''),
            'role' => $role,
        ], fn ($v) => $v !== null);
    }
}
