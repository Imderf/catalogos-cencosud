<?php

namespace App\Domain\Projects;

use App\Domain\Review\PageStatus;
use App\Enums\Role;
use App\Enums\SubtaskStatus;
use App\Models\AdjustmentIntake;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\User;

/**
 * A catalog can live on a board as one card. The card follows the catalog:
 * preparation → review → approved lists (matched by list kind) and completes when every page is closed.
 */
final class CatalogCards
{
    public function __construct(private readonly PageStatus $status, private readonly Activity $activity) {}

    /**
     * Link a catalog to a project (null unlinks). With $card, an existing task of the project adopts the
     * catalog and its own subtasks become the page subtasks, in order; otherwise a card is created.
     */
    public function link(Catalog $catalog, ?Project $project, User $by, ?Card $card = null): ?Card
    {
        $current = Card::where('catalog_id', $catalog->id)->first();
        if ($project === null) {
            $this->unlink($current);

            return null;
        }
        if ($card && ($card->project_id !== $project->id || ($card->catalog_id && $card->catalog_id !== $catalog->id))) {
            throw new \InvalidArgumentException('Esa tarea no es del proyecto elegido o ya tiene otro catálogo.');
        }
        if ($current && ($card ? $current->id === $card->id : $current->project_id === $project->id && $current->catalog_auto)) {
            return $current;
        }
        $this->unlink($current);
        if ($card) {
            $card->update(['catalog_id' => $catalog->id, 'catalog_auto' => false]);
            $this->activity->log($card, $by, 'catalog_linked', ['catalog' => $catalog->name]);
            $this->syncPages($card->fresh(), $catalog);

            return $card;
        }
        $list = $this->listFor($project, $this->stage($catalog));
        if (! $list) {
            return null;
        }
        $card = $project->cards()->create([
            'board_list_id' => $list->id,
            'title' => $catalog->name,
            'description' => $catalog->kind?->label().' · '.$catalog->brand->name.' · '.$catalog->pages()->count().' páginas',
            'position' => Positions::next(Card::where('board_list_id', $list->id)),
            'created_by' => $by->id,
            'catalog_id' => $catalog->id,
            'catalog_auto' => true,
        ]);
        $this->activity->log($card, $by, 'created');
        $this->syncPages($card, $catalog);

        return $card;
    }

    /** A card made for the catalog disappears with it; an adopted task stays and only loses the page subtasks. */
    private function unlink(?Card $card): void
    {
        if (! $card) {
            return;
        }
        if ($card->catalog_auto) {
            $card->delete();

            return;
        }
        /* Automatic "Página NN" subtasks go; hand-written ones stay and just forget their page. */
        $card->subtasks()->whereNotNull('page_id')->where('title', 'like', 'Página %')->delete();
        $card->subtasks()->whereNotNull('page_id')->update(['page_id' => null]);
        $card->update(['catalog_id' => null, 'catalog_auto' => true, 'completed_at' => null]);
    }

    /** Called after every review event: move the card to the list of the catalog's stage. */
    public function sync(Catalog $catalog): void
    {
        $card = Card::where('catalog_id', $catalog->id)->with('project.lists')->first();
        if (! $card) {
            return;
        }
        $stage = $this->stage($catalog);
        $list = $this->listFor($card->project, $stage);
        $changes = ['title' => $catalog->name, 'completed_at' => $stage === 'approved' ? ($card->completed_at ?? now()) : null];
        if ($list && $list->id !== $card->board_list_id) {
            $from = $card->list->name;
            $changes += ['board_list_id' => $list->id, 'position' => Positions::next(Card::where('board_list_id', $list->id))];
            $this->activity->log($card, null, 'moved', ['from' => $from, 'to' => $list->name]);
        }
        $card->update($changes);
        $this->syncPages($card, $catalog);
    }

    /**
     * One subtask per page, kept in step with the review: status from the page's phase,
     * an intake row for every client comment, deliverables closed when their comment is resolved.
     */
    public function syncPages(Card $card, Catalog $catalog): void
    {
        $pages = $catalog->pages()->with(['sectors', 'approvals', 'adjustments'])->orderBy('number')->get();
        $existing = $card->subtasks()->whereNotNull('page_id')->get()->keyBy('page_id');
        /* Subtasks the team wrote by hand before the catalog arrived stand for the pages, in order. */
        $spare = $card->subtasks()->whereNull('page_id')->orderBy('position')->get();
        foreach ($pages as $i => $page) {
            $title = $page->label().' · '.$page->sectors->pluck('name')->join(', ');
            $status = $this->pageStatus($page);
            /** @var Subtask|null $sub */
            $sub = $existing->get($page->id);
            if (! $sub && ($adopted = $spare->shift())) {
                $adopted->update(['page_id' => $page->id, 'status' => $status->value, 'completed_at' => $status->isClosed() ? now() : null]);
                $sub = $adopted->fresh();
            }
            $sub ??= $card->subtasks()->create(['project_id' => $card->project_id, 'page_id' => $page->id, 'title' => $title, 'position' => ($i + 1) * Positions::GAP, 'status' => $status->value, 'completed_at' => $status->isClosed() ? now() : null]);
            /* Hand-written names stay; only the automatic "Página NN" titles follow the page. */
            if (! str_starts_with($sub->title, 'Página ')) {
                $title = $sub->title;
            }
            /* Client comments arrive as intake rows; a resolved comment closes its deliverable. */
            foreach ($page->adjustments as $adjustment) {
                if (! $adjustment->origin_role || $adjustment->origin_role === Role::Agencia) {
                    continue;
                }
                $intake = AdjustmentIntake::firstOrCreate(['adjustment_id' => $adjustment->id], ['subtask_id' => $sub->id]);
                if (! $adjustment->isOpen() && $intake->deliverable && $intake->deliverable->isOpen()) {
                    $intake->deliverable->update(['status' => 'done', 'done_at' => now()]);
                }
            }
            /* Open deliverables keep the page in "Ajustes" even when the review has moved on. */
            $open = $sub->deliverables()->where('status', '!=', 'done')->exists();
            $final = $open && $status !== SubtaskStatus::Pending ? SubtaskStatus::Adjustments : $status;
            if ($sub->status !== $final || $sub->title !== $title) {
                $sub->update(['title' => $title, 'status' => $final->value, 'completed_at' => $final->isClosed() ? ($sub->completed_at ?? now()) : null]);
            }
        }
        $orphans = $existing->keys()->diff($pages->pluck('id'));
        if ($orphans->isNotEmpty()) {
            $card->subtasks()->whereIn('page_id', $orphans)->delete();
        }
    }

    /** Pendiente while Ogilvy prepares, En proceso under review, Ajustes with open client comments, Completado when approved for print. */
    private function pageStatus(Page $page): SubtaskStatus
    {
        if (! $page->isSubmitted()) {
            return SubtaskStatus::Pending;
        }
        if ($this->status->isApproved($page)) {
            return SubtaskStatus::Completed;
        }

        return $page->adjustments->contains(fn ($a) => $a->isOpen()) ? SubtaskStatus::Adjustments : SubtaskStatus::InProgress;
    }

    /** preparation | review | approved */
    public function stage(Catalog $catalog): string
    {
        $pages = $catalog->pages()->with(['approvals', 'adjustments'])->get();
        if ($pages->isEmpty() || $pages->every(fn (Page $p) => ! $p->isSubmitted())) {
            return 'preparation';
        }

        return $pages->every(fn (Page $p) => $this->status->isApproved($p)) ? 'approved' : 'review';
    }

    /** The list tagged with the stage; without tags, the first list receives everything. */
    private function listFor(Project $project, string $stage): ?BoardList
    {
        $lists = $project->lists()->orderBy('position')->get();
        if ($stage === 'preparation') {
            /* The Backlog is where a catalog waits while Ogilvy prepares it. */
            return $lists->firstWhere('kind', 'backlog') ?? $lists->firstWhere('kind', 'preparation') ?? $lists->first();
        }

        return $lists->firstWhere('kind', $stage) ?? $lists->first();
    }
}
