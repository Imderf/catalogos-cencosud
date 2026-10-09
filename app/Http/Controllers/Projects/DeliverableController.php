<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Activity;
use App\Domain\Projects\Positions;
use App\Domain\Projects\ProjectAlerts;
use App\Enums\SubtaskStatus;
use App\Events\Projects\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\Deliverable;
use App\Models\Subtask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** What Ogilvy commits to on a page: its own rows with a person, a date and a status. */
class DeliverableController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(private readonly Activity $activity, private readonly ProjectAlerts $alerts) {}

    public function store(Request $request, Subtask $subtask): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:300']], [], ['title' => 'título']);

        return $this->attempt(function () use ($request, $subtask, $data) {
            $card = $subtask->card;
            $this->authorize('work', $card->project);
            $subtask->deliverables()->create(['title' => $data['title'], 'position' => Positions::next(Deliverable::where('subtask_id', $subtask->id)), 'created_by' => $request->user()->id]);
            if ($subtask->isOpen() && $subtask->status !== SubtaskStatus::Adjustments) {
                $subtask->update(['status' => SubtaskStatus::Adjustments->value]);
            }
            $this->activity->log($card, $request->user(), 'deliverable_added', ['title' => $data['title']], $subtask);
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    public function update(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $subtask = $deliverable->subtask;
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:300'],
            'status' => ['sometimes', Rule::in(array_keys(Deliverable::STATUSES))],
            'assignee_id' => ['sometimes', 'nullable', Rule::exists('project_members', 'user_id')->where('project_id', $subtask->project_id)],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ], ['assignee_id.exists' => 'Solo miembros del proyecto pueden ser responsables.'], ['title' => 'título', 'due_date' => 'fecha']);

        return $this->attempt(function () use ($request, $deliverable, $subtask, $data) {
            $card = $subtask->card;
            $this->authorize('work', $card->project);
            $before = $deliverable->replicate();
            $deliverable->fill($data);
            if (isset($data['status'])) {
                $deliverable->done_at = $data['status'] === 'done' ? ($deliverable->done_at ?? now()) : null;
            }
            $deliverable->save();
            if ($before->status !== $deliverable->status) {
                $this->activity->log($card, $request->user(), 'deliverable_status', ['title' => $deliverable->title, 'to' => Deliverable::STATUSES[$deliverable->status]], $subtask);
            }
            if (array_key_exists('assignee_id', $data) && $before->assignee_id !== $deliverable->assignee_id && $deliverable->assignee_id) {
                $this->alerts->deliverableAssigned($deliverable->fresh(['assignee', 'subtask.card.project']), $request->user());
            }
            /* No open deliverables left on a page still under review: back to "En proceso". */
            if (! $subtask->deliverables()->where('status', '!=', 'done')->exists() && $subtask->status === SubtaskStatus::Adjustments && $subtask->isAutomatic()) {
                $subtask->update(['status' => SubtaskStatus::InProgress->value]);
            }
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    public function destroy(Request $request, Deliverable $deliverable): RedirectResponse
    {
        return $this->attempt(function () use ($request, $deliverable) {
            $subtask = $deliverable->subtask;
            $this->authorize('manage', $subtask->card->project);
            $this->activity->log($subtask->card, $request->user(), 'deliverable_removed', ['title' => $deliverable->title], $subtask);
            $deliverable->delete();
            BoardChanged::dispatch($subtask->project_id, 'card');
        }, '');
    }
}
