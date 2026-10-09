<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Activity;
use App\Domain\Projects\ProjectAlerts;
use App\Enums\SubtaskStatus;
use App\Events\Projects\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\AdjustmentIntake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** A moderator decides whether a client comment enters Ogilvy's work on the page. */
class IntakeController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(private readonly Activity $activity, private readonly ProjectAlerts $alerts) {}

    public function approve(Request $request, AdjustmentIntake $intake): RedirectResponse
    {
        return $this->attempt(function () use ($request, $intake) {
            $subtask = $intake->subtask;
            $card = $subtask->card;
            $this->authorize('manage', $card->project);
            if (! $intake->isPending()) {
                throw new InvalidArgumentException('Este comentario ya fue revisado.');
            }
            $intake->update(['status' => AdjustmentIntake::APPROVED, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'reason' => null]);
            $a = $intake->adjustment;
            /* The client's words enter the subtask's conversation, signed by the client and addressed to the people on it. */
            $mentions = $subtask->assignees->map(fn ($u) => '@['.$u->name.']('.$u->id.')')->join(' ');
            $links = $a->attachments->where('kind', 'link')->pluck('url');
            $body = trim(($mentions ? $mentions.' ' : '').$a->typeLabel().': '.$a->text.($links->isNotEmpty() ? "\n".$links->join("\n") : ''));
            $comment = $subtask->comments()->create(['card_id' => $card->id, 'user_id' => $a->author_id, 'adjustment_id' => $a->id, 'body' => $body]);
            /* The client's files come along as attachments of that comment (a copy: the review keeps its own). */
            foreach ($a->attachments->filter->isFile() as $f) {
                $copy = "proyectos/{$card->project_id}/tarjetas/{$card->id}/".basename($f->path);
                if ($f->path && Storage::disk('local')->exists($f->path) && ! Storage::disk('local')->exists($copy)) {
                    Storage::disk('local')->copy($f->path, $copy);
                }
                $comment->attachments()->create(['card_id' => $card->id, 'subtask_id' => $subtask->id, 'user_id' => $a->author_id, 'kind' => 'file', 'name' => $f->name, 'path' => $copy, 'mime' => $f->mime, 'size' => $f->size]);
            }
            $by = $a->author ?? $request->user();
            $this->alerts->mentioned($card, $by, $body, $subtask);
            if ($subtask->isOpen() && $subtask->status !== SubtaskStatus::Adjustments) {
                $subtask->update(['status' => SubtaskStatus::Adjustments->value]);
            }
            $this->activity->log($card, $request->user(), 'intake_approved', ['who' => $a->author?->name ?? 'cliente'], $subtask);
            BoardChanged::dispatch($card->project_id, 'card');
        }, 'Comentario del cliente publicado en la subtarea; los responsables fueron avisados.');
    }

    public function dismiss(Request $request, AdjustmentIntake $intake): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']], [], ['reason' => 'motivo']);

        return $this->attempt(function () use ($request, $intake, $data) {
            $subtask = $intake->subtask;
            $card = $subtask->card;
            $this->authorize('manage', $card->project);
            if (! $intake->isPending()) {
                throw new InvalidArgumentException('Este comentario ya fue revisado.');
            }
            $intake->update(['status' => AdjustmentIntake::DISMISSED, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'reason' => $data['reason'] ?? null]);
            $this->activity->log($card, $request->user(), 'intake_dismissed', ['who' => $intake->adjustment->author?->name ?? 'cliente'], $subtask);
            BoardChanged::dispatch($card->project_id, 'card');
        }, 'Comentario descartado.');
    }
}
