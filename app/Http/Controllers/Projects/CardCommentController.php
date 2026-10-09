<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Activity;
use App\Domain\Projects\ProjectAlerts;
use App\Events\Projects\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\Card;
use App\Models\CardComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CardCommentController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(private readonly Activity $activity, private readonly ProjectAlerts $alerts) {}

    public function store(Request $request, Card $card): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required_without:files', 'nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:25600'],
            'parent_id' => ['nullable', 'integer'],
        ], ['body.required_without' => 'Escribe algo o adjunta un archivo.'], ['body' => 'comentario', 'files' => 'archivos']);
        $data['body'] = trim((string) ($data['body'] ?? '')) ?: '(archivo adjunto)';

        return $this->attempt(function () use ($request, $card, $data) {
            $this->authorize('work', $card->project);
            /* A reply answers a comment of this same card; replies stay one level deep. */
            $parent = ! empty($data['parent_id']) ? $card->comments()->whereKey($data['parent_id'])->first() : null;
            if (! empty($data['parent_id']) && ! $parent) {
                throw new InvalidArgumentException('El comentario al que respondes ya no existe.');
            }
            $comment = $card->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'parent_id' => $parent?->parent_id ?? $parent?->id]);
            foreach ($data['files'] ?? [] as $file) {
                $path = $file->storeAs("proyectos/{$card->project_id}/tarjetas/{$card->id}/comentarios", Str::random(12).'.'.$file->getClientOriginalExtension(), 'local');
                $comment->attachments()->create(['card_id' => $card->id, 'user_id' => $request->user()->id, 'kind' => 'file', 'name' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
            }
            $this->activity->log($card, $request->user(), 'commented');
            $this->alerts->mentioned($card, $request->user(), $data['body']);
            $this->alerts->commented($card, $request->user(), $data['body']);
            if ($parent) {
                $this->alerts->replied($parent, $request->user(), $data['body']);
            }
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    public function update(Request $request, CardComment $comment): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], [], ['body' => 'comentario']);

        return $this->attempt(function () use ($request, $comment, $data) {
            $this->authorize('work', $comment->card->project);
            if (! $comment->isEditableBy($request->user())) {
                throw new InvalidArgumentException('Solo puedes editar tus propios comentarios.');
            }
            $comment->update(['body' => trim($data['body'])]);
            BoardChanged::dispatch($comment->card->project_id, 'card');
        }, '');
    }

    public function destroy(Request $request, CardComment $comment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $comment) {
            $project = $comment->card->project;
            $this->authorize('work', $project);
            if ($comment->user_id !== $request->user()->id && $project->roleOf($request->user())?->value !== 'manager') {
                throw new InvalidArgumentException('Solo el autor o un moderador puede borrar un comentario.');
            }
            foreach ($comment->attachments as $a) {
                if ($a->path) {
                    Storage::disk('local')->delete($a->path);
                }
            }
            $comment->delete();
        }, '');
    }
}
