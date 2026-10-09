<?php

namespace App\Domain\Projects;

use App\Models\AdjustmentIntake;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\Deliverable;
use App\Models\Subtask;
use App\Models\User;
use App\Notifications\Projects\ProjectAlert;
use App\Notifications\Projects\ProjectAlertMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Who hears about a card: mentioned people, new assignees, and the card's watchers when someone comments. */
final class ProjectAlerts
{
    public function mentioned(Card $card, User $by, string $body, ?Subtask $subtask = null): void
    {
        $ids = Mentions::ids($body);
        if ($ids === []) {
            return;
        }
        $users = $card->project->members()->whereIn('users.id', $ids)->get();
        $this->send($users, $card, $by, 'mencion', $by->name.' te mencionó en "'.($subtask?->title ?? $card->title).'"', Mentions::plain($body), $subtask);
    }

    public function commented(Card $card, User $by, string $body): void
    {
        $skip = Mentions::ids($body);
        $users = $card->watchers()->reject(fn (User $u) => in_array($u->id, $skip, true));
        $this->send($users, $card, $by, 'comentario', $by->name.' comentó en "'.$card->title.'"', Mentions::plain($body));
    }

    /** @param  list<int>  $userIds */
    public function assigned(Card $card, User $by, array $userIds, ?Subtask $subtask = null): void
    {
        if ($userIds === []) {
            return;
        }
        $what = $subtask ? 'la subtarea "'.$subtask->title.'" de "'.$card->title.'"' : '"'.$card->title.'"';
        $this->send(User::whereIn('id', $userIds)->get(), $card, $by, 'asignacion', $by->name.' te asignó '.$what, null, $subtask);
    }

    /** Moderators of the board decide what enters the work: tell them a client comment is waiting. */
    public function intakeArrived(AdjustmentIntake $intake, User $by): void
    {
        $subtask = $intake->subtask;
        $project = $subtask->card->project;
        $managers = $project->members()->wherePivot('role', 'manager')->get()->push($project->owner)->filter()->unique('id');
        $this->send($managers, $subtask->card, $by, 'ingreso', $by->name.' comentó "'.$subtask->title.'" · por revisar en el tablero', Mentions::plain((string) $intake->adjustment->text), $subtask);
    }

    /** Someone got a deliverable on a page. */
    public function deliverableAssigned(Deliverable $d, User $by): void
    {
        if (! $d->assignee) {
            return;
        }
        $this->send(collect([$d->assignee]), $d->subtask->card, $by, 'asignacion', $by->name.' te asignó el entregable "'.$d->title.'"', null, $d->subtask);
    }

    /** The people on a subtask hear when its status moves. */
    public function subtaskStatus(Subtask $subtask, User $by): void
    {
        $this->send($subtask->assignees, $subtask->card, $by, 'estado', $by->name.' pasó "'.$subtask->title.'" a '.$subtask->status->label(), null, $subtask);
    }

    public function subtaskCommented(Subtask $subtask, User $by, string $body): void
    {
        $skip = Mentions::ids($body);
        $users = $subtask->assignees->reject(fn (User $u) => in_array($u->id, $skip, true));
        $this->send($users, $subtask->card, $by, 'comentario', $by->name.' comentó en "'.$subtask->title.'"', Mentions::plain($body), $subtask);
    }

    /** The person whose comment was answered, unless they wrote the reply or were mentioned in it. */
    public function replied(CardComment $parent, User $by, string $body): void
    {
        $author = $parent->author;
        if (! $author || $author->is($by) || in_array($author->id, Mentions::ids($body), true)) {
            return;
        }
        $subtask = $parent->subtask_id ? Subtask::find($parent->subtask_id) : null;
        $this->send(collect([$author]), $parent->card, $by, 'respuesta', $by->name.' respondió a tu comentario en "'.($subtask?->title ?? $parent->card->title).'"', Mentions::plain($body), $subtask);
    }

    /** @param  Collection<int, User>  $users */
    private function send(Collection $users, Card $card, User $by, string $kind, string $text, ?string $excerpt = null, ?Subtask $subtask = null): void
    {
        $payload = [
            'kind' => $kind, 'text' => $text, 'actor' => $by->name,
            'project_name' => $card->project->name, 'card_title' => $card->title,
            'excerpt' => $excerpt ? Str::limit($excerpt, 160) : null,
            'url' => route('projects.show', array_filter(['project' => $card->project_id, 'tarjeta' => $card->id, 'subtarea' => $subtask?->id])),
        ];
        $users->reject(fn (User $u) => $u->is($by) || ! $u->is_active)->unique('id')->each(function (User $u) use ($payload) {
            $u->notify(new ProjectAlert($payload));
            if (! $u->wantsDailyDigest()) {
                $u->notify(new ProjectAlertMail($payload));
            }
        });
    }
}
