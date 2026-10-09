<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Activity;
use App\Domain\Projects\Positions;
use App\Domain\Projects\ProjectAlerts;
use App\Enums\CardPriority;
use App\Enums\ProjectRole;
use App\Enums\SubtaskStatus;
use App\Events\Projects\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\Card;
use App\Models\Subtask;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use InvalidArgumentException;

class SubtaskController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(private readonly Activity $activity, private readonly ProjectAlerts $alerts) {}

    public function store(Request $request, Card $card): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200']], [], ['title' => 'título']);

        return $this->attempt(function () use ($request, $card, $data) {
            $this->authorize('work', $card->project);
            $subtask = $card->subtasks()->create([
                'project_id' => $card->project_id,
                'title' => $data['title'],
                'position' => Positions::next(Subtask::where('card_id', $card->id)),
                'created_by' => $request->user()->id,
            ]);
            $this->activity->log($card, $request->user(), 'subtask_created', [], $subtask);
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    public function update(Request $request, Subtask $subtask): RedirectResponse
    {
        $card = $subtask->card;
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', new Enum(SubtaskStatus::class)],
            'priority' => ['sometimes', new Enum(CardPriority::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assignees' => ['sometimes', 'array'],
            'assignees.*' => ['integer', Rule::exists('project_members', 'user_id')->where('project_id', $card->project_id)],
        ], ['assignees.*.exists' => 'Solo miembros del proyecto pueden ser responsables.'], ['title' => 'título', 'due_date' => 'fecha límite', 'status' => 'estado']);

        return $this->attempt(function () use ($request, $subtask, $card, $data) {
            $this->authorize('work', $card->project);
            $by = $request->user();
            if ($subtask->isAutomatic() && (isset($data['status']) || isset($data['title']))) {
                throw new InvalidArgumentException('Esta subtarea es una página del catálogo: su nombre y estado siguen la revisión.');
            }
            DB::transaction(function () use ($subtask, $card, $data, $by) {
                $before = $subtask->replicate();
                $subtask->fill(collect($data)->only(['title', 'description', 'priority', 'due_date'])->all());
                if (isset($data['status'])) {
                    $status = SubtaskStatus::from($data['status']);
                    $subtask->status = $status;
                    $subtask->completed_at = $status->isClosed() ? ($subtask->completed_at ?? now()) : null;
                }
                $subtask->save();
                if ($before->title !== $subtask->title) {
                    $this->activity->log($card, $by, 'subtask_renamed', ['to' => $subtask->title], $subtask);
                }
                if ($before->status !== $subtask->status) {
                    $this->activity->log($card, $by, 'subtask_status', ['to' => $subtask->status->label()], $subtask);
                    $this->alerts->subtaskStatus($subtask, $by);
                }
                if ($before->due_date?->toDateString() !== $subtask->due_date?->toDateString()) {
                    $this->activity->log($card, $by, 'subtask_due', ['to' => $subtask->due_date?->format('d/m/Y')], $subtask);
                }
                if (array_key_exists('assignees', $data)) {
                    $changes = $subtask->assignees()->sync($data['assignees']);
                    foreach (User::whereIn('id', $changes['attached'])->get() as $u) {
                        $this->activity->log($card, $by, 'subtask_assigned', ['who' => $u->name], $subtask);
                    }
                    foreach (User::whereIn('id', $changes['detached'])->get() as $u) {
                        $this->activity->log($card, $by, 'subtask_unassigned', ['who' => $u->name], $subtask);
                    }
                    $this->alerts->assigned($card, $by, $changes['attached'], $subtask);
                }
            });
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    public function move(Request $request, Subtask $subtask): RedirectResponse
    {
        $data = $request->validate(['position' => ['required', 'numeric']]);

        return $this->attempt(function () use ($subtask, $data) {
            $this->authorize('work', $subtask->card->project);
            $subtask->update(['position' => (float) $data['position']]);
            Positions::rebalanceIfNeeded(Subtask::where('card_id', $subtask->card_id));
        }, '');
    }

    public function comment(Request $request, Subtask $subtask): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required_without:files', 'nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:25600'],
            'parent_id' => ['nullable', 'integer'],
        ], ['body.required_without' => 'Escribe algo o adjunta un archivo.'], ['body' => 'comentario']);
        $data['body'] = trim((string) ($data['body'] ?? '')) ?: '(archivo adjunto)';

        return $this->attempt(function () use ($request, $subtask, $data) {
            $card = $subtask->card;
            $this->authorize('work', $card->project);
            $parent = ! empty($data['parent_id']) ? $subtask->comments()->whereKey($data['parent_id'])->first() : null;
            if (! empty($data['parent_id']) && ! $parent) {
                throw new InvalidArgumentException('El comentario al que respondes ya no existe.');
            }
            $comment = $subtask->comments()->create(['card_id' => $card->id, 'user_id' => $request->user()->id, 'body' => $data['body'], 'parent_id' => $parent?->parent_id ?? $parent?->id]);
            foreach ($data['files'] ?? [] as $file) {
                $path = $file->storeAs("proyectos/{$card->project_id}/tarjetas/{$card->id}/subtareas/{$subtask->id}", Str::random(12).'.'.$file->getClientOriginalExtension(), 'local');
                $comment->attachments()->create(['card_id' => $card->id, 'subtask_id' => $subtask->id, 'user_id' => $request->user()->id, 'kind' => 'file', 'name' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
            }
            $this->activity->log($card, $request->user(), 'subtask_commented', [], $subtask);
            $this->alerts->mentioned($card, $request->user(), $data['body'], $subtask);
            $this->alerts->subtaskCommented($subtask, $request->user(), $data['body']);
            if ($parent) {
                $this->alerts->replied($parent, $request->user(), $data['body']);
            }
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    public function destroy(Request $request, Subtask $subtask): RedirectResponse
    {
        return $this->attempt(function () use ($request, $subtask) {
            $card = $subtask->card;
            $this->authorize('work', $card->project);
            if ($subtask->isAutomatic()) {
                throw new InvalidArgumentException('Las subtareas de página se quitan desvinculando el catálogo del proyecto.');
            }
            $mine = $subtask->created_by === $request->user()->id;
            if (! $mine && $card->project->roleOf($request->user()) !== ProjectRole::Manager) {
                throw new InvalidArgumentException('Solo quien creó la subtarea o un moderador puede eliminarla.');
            }
            foreach ($card->allAttachments()->where('subtask_id', $subtask->id)->get() as $a) {
                if ($a->path) {
                    Storage::disk('local')->delete($a->path);
                }
            }
            $this->activity->log($card, $request->user(), 'subtask_deleted', ['subtask' => $subtask->title]);
            $subtask->delete();
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }
}
