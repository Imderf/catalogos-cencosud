<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Activity;
use App\Events\Projects\BoardChanged;
use App\Http\Controllers\Concerns\ServesPrivateFiles;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\Card;
use App\Models\CardAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Files live on the private disk under proyectos/; links are just stored. */
class CardAttachmentController extends Controller
{
    use HandlesDomainErrors, ServesPrivateFiles;

    public function __construct(private readonly Activity $activity) {}

    public function store(Request $request, Card $card): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required_without:url', 'array', 'max:10'],
            'files.*' => ['file', 'max:25600'],
            'url' => ['required_without:files', 'nullable', 'url', 'max:2048'],
            'name' => ['nullable', 'string', 'max:120'],
        ], ['files.*.max' => 'Cada archivo puede pesar hasta 25 MB.'], ['url' => 'enlace', 'files' => 'archivos']);

        return $this->attempt(function () use ($request, $card, $data) {
            $this->authorize('work', $card->project);
            $by = $request->user();
            if (! empty($data['url'])) {
                $a = $card->attachments()->create(['user_id' => $by->id, 'kind' => 'link', 'name' => $data['name'] ?: $data['url'], 'url' => $data['url']]);
                $this->activity->log($card, $by, 'attached', ['name' => $a->name]);
            }
            foreach ($data['files'] ?? [] as $file) {
                /** @var UploadedFile $file */
                $name = $file->getClientOriginalName();
                $path = $file->storeAs("proyectos/{$card->project_id}/tarjetas/{$card->id}", Str::random(12).'-'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension(), 'local');
                $card->attachments()->create(['user_id' => $by->id, 'kind' => 'file', 'name' => $name, 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                $this->activity->log($card, $by, 'attached', ['name' => $name]);
            }
            BoardChanged::dispatch($card->project_id, 'card');
        }, 'Adjunto agregado.');
    }

    public function show(Request $request, CardAttachment $attachment): Response
    {
        $this->authorize('view', $attachment->card->project);
        abort_unless($attachment->isFile() && $attachment->path && Storage::disk('local')->exists($attachment->path), 404);

        return $this->privateFile($attachment->path, $attachment->name, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function destroy(Request $request, CardAttachment $attachment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $attachment) {
            $card = $attachment->card;
            $this->authorize('work', $card->project);
            if ($attachment->user_id !== $request->user()->id && $card->project->roleOf($request->user())?->value !== 'manager') {
                throw new InvalidArgumentException('Solo quien lo subió o un moderador puede quitar un adjunto.');
            }
            if ($attachment->isFile() && $attachment->path) {
                Storage::disk('local')->delete($attachment->path);
            }
            $attachment->delete();
            $this->activity->log($card, $request->user(), 'detached', ['name' => $attachment->name]);
            BoardChanged::dispatch($card->project_id, 'card');
        }, 'Adjunto eliminado.');
    }
}
