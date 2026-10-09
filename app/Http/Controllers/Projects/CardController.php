<?php

namespace App\Http\Controllers\Projects;

use App\Domain\Projects\Activity;
use App\Domain\Projects\Positions;
use App\Domain\Projects\ProjectAlerts;
use App\Domain\Review\AgencyQueue;
use App\Enums\CardPriority;
use App\Enums\CustomFieldType;
use App\Enums\ProjectRole;
use App\Events\Projects\BoardChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\Card;
use App\Models\CardAttachment;
use App\Models\CustomField;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use InvalidArgumentException;

class CardController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(private readonly Activity $activity, private readonly ProjectAlerts $alerts) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'board_list_id' => ['required', Rule::exists('board_lists', 'id')->where('project_id', $project->id)],
            'title' => ['required', 'string', 'max:200'],
        ], [], ['title' => 'título', 'board_list_id' => 'lista']);

        return $this->attempt(function () use ($request, $project, $data) {
            $this->authorize('work', $project);
            $card = $project->cards()->create([
                'board_list_id' => $data['board_list_id'],
                'title' => $data['title'],
                'position' => Positions::next(Card::where('board_list_id', $data['board_list_id'])),
                'created_by' => $request->user()->id,
            ]);
            $this->activity->log($card, $request->user(), 'created');
            BoardChanged::dispatch($project->id, 'card');
        }, 'Tarjeta creada.');
    }

    public function update(Request $request, Card $card): RedirectResponse
    {
        $project = $card->project;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', new Enum(CardPriority::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assignees' => ['sometimes', 'array'],
            'assignees.*' => ['integer', Rule::exists('project_members', 'user_id')->where('project_id', $project->id)],
            'labels' => ['sometimes', 'array'],
            'labels.*' => ['integer', Rule::exists('labels', 'id')->where('project_id', $project->id)],
        ], ['assignees.*.exists' => 'Solo miembros del proyecto pueden ser responsables.', 'due_date.after_or_equal' => 'La fecha límite no puede ser anterior a la fecha de inicio.'], ['title' => 'título', 'due_date' => 'fecha límite', 'start_date' => 'fecha de inicio']);

        return $this->attempt(function () use ($request, $card, $project, $data) {
            $this->authorize('work', $project);
            $by = $request->user();
            DB::transaction(function () use ($card, $data, $by) {
                $before = $card->replicate();
                $card->update(collect($data)->only(['title', 'description', 'priority', 'start_date', 'due_date'])->all());
                $card->refresh();
                if ($before->title !== $card->title) {
                    $this->activity->log($card, $by, 'renamed', ['to' => $card->title]);
                }
                if (($before->description ?? '') !== ($card->description ?? '')) {
                    $this->activity->log($card, $by, 'description');
                }
                if ($before->priority !== $card->priority) {
                    $this->activity->log($card, $by, 'priority', ['to' => $card->priority->label()]);
                    if ($card->catalog_id) {
                        app(AgencyQueue::class)->refresh();
                    }
                }
                if ($before->start_date?->toDateString() !== $card->start_date?->toDateString()) {
                    $this->activity->log($card, $by, 'start_date', ['to' => $card->start_date?->format('d/m/Y')]);
                }
                if ($before->due_date?->toDateString() !== $card->due_date?->toDateString()) {
                    $this->activity->log($card, $by, 'due_date', ['to' => $card->due_date?->format('d/m/Y')]);
                }
                if (array_key_exists('assignees', $data)) {
                    $changes = $card->assignees()->sync($data['assignees']);
                    foreach (User::whereIn('id', $changes['attached'])->get() as $u) {
                        $this->activity->log($card, $by, 'assigned', ['who' => $u->name]);
                    }
                    foreach (User::whereIn('id', $changes['detached'])->get() as $u) {
                        $this->activity->log($card, $by, 'unassigned', ['who' => $u->name]);
                    }
                    $this->alerts->assigned($card, $by, $changes['attached']);
                }
                if (array_key_exists('labels', $data)) {
                    $changes = $card->labels()->sync($data['labels']);
                    if ($changes['attached'] || $changes['detached']) {
                        $this->activity->log($card, $by, 'labels');
                    }
                }
            });
            BoardChanged::dispatch($project->id, 'card');
        }, '');
    }

    /** Values of the project's custom fields for this card, keyed by field id. */
    public function fields(Request $request, Card $card): RedirectResponse
    {
        $data = $request->validate(['values' => ['present', 'array']]);

        return $this->attempt(function () use ($request, $card, $data) {
            $this->authorize('work', $card->project);
            $fields = $card->project->fields->keyBy('id');
            foreach ($data['values'] as $fieldId => $value) {
                /** @var CustomField|null $field */
                $field = $fields->get((int) $fieldId);
                if (! $field) {
                    continue;
                }
                if ($field->type === CustomFieldType::Image) {
                    if ($value === null || $value === '') {
                        $this->clearImage($card, $field);
                        $card->fieldValues()->where('custom_field_id', $field->id)->delete();
                    }

                    continue; // images arrive through their own upload endpoint
                }
                $clean = $this->cleanValue($field, $value, $card->project);
                $current = $card->fieldValues()->where('custom_field_id', $field->id)->first();
                if (($current?->value ?? null) === $clean) {
                    continue;
                }
                $card->fieldValues()->updateOrCreate(['custom_field_id' => $field->id], ['value' => $clean]);
                $this->activity->log($card, $request->user(), 'field', ['field' => $field->name]);
            }
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    /** Drag of a card: target list and float position between its new neighbours. */
    public function move(Request $request, Card $card): RedirectResponse
    {
        $data = $request->validate([
            'board_list_id' => ['required', Rule::exists('board_lists', 'id')->where('project_id', $card->project_id)],
            'position' => ['required', 'numeric'],
        ]);

        return $this->attempt(function () use ($request, $card, $data) {
            $this->authorize('work', $card->project);
            $from = $card->list;
            $card->update(['board_list_id' => $data['board_list_id'], 'position' => (float) $data['position']]);
            Positions::rebalanceIfNeeded(Card::where('board_list_id', $data['board_list_id']));
            if ($from->id !== (int) $data['board_list_id']) {
                $this->activity->log($card, $request->user(), 'moved', ['from' => $from->name, 'to' => $card->fresh()->list->name]);
            }
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    public function complete(Request $request, Card $card): RedirectResponse
    {
        return $this->attempt(function () use ($request, $card) {
            $this->authorize('work', $card->project);
            $done = $card->completed_at === null;
            $card->update(['completed_at' => $done ? now() : null]);
            $this->activity->log($card, $request->user(), $done ? 'completed' : 'reopened');
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    /** Image field: the value is the id of an attachment tied to the field. */
    public function fieldImage(Request $request, Card $card, CustomField $field): RedirectResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:10240']], [], ['image' => 'imagen']);

        return $this->attempt(function () use ($request, $card, $field) {
            $this->authorize('work', $card->project);
            if ($field->project_id !== $card->project_id || $field->type !== CustomFieldType::Image) {
                throw new InvalidArgumentException('Ese campo no es de imagen.');
            }
            $file = $request->file('image');
            $path = $file->storeAs("proyectos/{$card->project_id}/tarjetas/{$card->id}/campos", Str::random(12).'.'.$file->getClientOriginalExtension(), 'local');
            $this->clearImage($card, $field);
            $a = $card->allAttachments()->create(['custom_field_id' => $field->id, 'user_id' => $request->user()->id, 'kind' => 'file', 'name' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
            $card->fieldValues()->updateOrCreate(['custom_field_id' => $field->id], ['value' => $a->id]);
            $this->activity->log($card, $request->user(), 'field', ['field' => $field->name]);
            BoardChanged::dispatch($card->project_id, 'card');
        }, '');
    }

    private function clearImage(Card $card, CustomField $field): void
    {
        foreach ($card->allAttachments()->where('custom_field_id', $field->id)->get() as $old) {
            if ($old->path) {
                Storage::disk('local')->delete($old->path);
            }
            $old->delete();
        }
    }

    /**
     * Copy of a card with options, like the original board: title, target project and list,
     * what travels along (assignees, due date, attachments) and how many copies. Across projects,
     * labels and fields are matched by name; what does not exist there is skipped and reported.
     */
    public function duplicate(Request $request, Card $card): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'board_list_id' => ['required', Rule::exists('board_lists', 'id')->where('project_id', $request->integer('project_id'))],
            'copy_assignees' => ['boolean'],
            'copy_due_date' => ['boolean'],
            'copy_attachments' => ['boolean'],
            'count' => ['integer', 'min:1', 'max:10'],
        ], [], ['title' => 'título', 'board_list_id' => 'lista']);

        $target = Project::visibleTo($request->user())->active()->find($data['project_id']);
        if (! $target) {
            return back()->with('error', 'No puedes duplicar hacia ese proyecto.');
        }
        try {
            $this->authorize('work', $card->project);
            $this->authorize('work', $target);
        } catch (AuthorizationException $e) {
            return back()->with('error', $e->getMessage());
        }

        $count = (int) ($data['count'] ?? 1);
        $skipped = ['labels' => 0, 'fields' => 0, 'assignees' => 0];
        $ids = [];
        DB::transaction(function () use ($request, $card, $target, $data, $count, &$skipped, &$ids) {
            $same = $target->id === $card->project_id;
            $labelIds = [];
            foreach ($card->labels as $l) {
                $id = $same ? $l->id : $target->labels()->where('name', $l->name)->value('id');
                $id ? $labelIds[] = $id : $skipped['labels']++;
            }
            $fieldMap = [];
            foreach ($card->fieldValues as $v) {
                $field = $v->field;
                $id = $same ? $field?->id : ($field ? $target->fields()->where('name', $field->name)->where('type', $field->type->value)->value('id') : null);
                $id ? $fieldMap[$id] = $v : $skipped['fields']++;
            }
            $assigneeIds = [];
            if (! empty($data['copy_assignees'])) {
                foreach ($card->assignees as $u) {
                    $target->roleOf($u) ? $assigneeIds[] = $u->id : $skipped['assignees']++;
                }
            }
            for ($i = 1; $i <= $count; $i++) {
                $copy = new Card;
                $copy->fill([
                    'project_id' => $target->id,
                    'board_list_id' => $data['board_list_id'],
                    'title' => $count > 1 ? $data['title'].' ('.$i.')' : $data['title'],
                    'description' => $card->description,
                    'priority' => $card->priority,
                    'start_date' => ! empty($data['copy_due_date']) ? $card->start_date : null,
                    'due_date' => ! empty($data['copy_due_date']) ? $card->due_date : null,
                    'position' => Positions::next(Card::where('board_list_id', $data['board_list_id'])),
                    'created_by' => $request->user()->id,
                ]);
                $copy->save();
                $copy->labels()->sync($labelIds);
                $copy->assignees()->sync($assigneeIds);
                foreach ($fieldMap as $fieldId => $v) {
                    $isImage = $v->field?->type === CustomFieldType::Image;
                    if ($isImage) {
                        if (empty($data['copy_attachments'])) {
                            continue;
                        }
                        $src = $card->allAttachments()->find($v->value);
                        if (! $src || ! $src->path) {
                            continue;
                        }
                        $a = $this->copyAttachment($src, $copy, ['custom_field_id' => $fieldId]);
                        $copy->fieldValues()->create(['custom_field_id' => $fieldId, 'value' => $a->id]);
                    } else {
                        $copy->fieldValues()->create(['custom_field_id' => $fieldId, 'value' => $v->value]);
                    }
                }
                if (! empty($data['copy_attachments'])) {
                    foreach ($card->attachments as $src) {
                        $this->copyAttachment($src, $copy);
                    }
                }
                $this->activity->log($copy, $request->user(), 'duplicated', ['from' => $card->title]);
                $ids[] = $copy->id;
            }
        });
        BoardChanged::dispatch($target->id, 'card');
        if ($target->id !== $card->project_id) {
            BoardChanged::dispatch($card->project_id, 'card');
        }

        $warn = array_filter([$skipped['labels'] ? $skipped['labels'].' etiqueta(s)' : null, $skipped['fields'] ? $skipped['fields'].' campo(s)' : null, $skipped['assignees'] ? $skipped['assignees'].' asignado(s)' : null]);
        $msg = ($count > 1 ? $count.' copias creadas' : 'Tarjeta duplicada').($warn ? '. No se copiaron: '.implode(', ', $warn).' (no existen en el proyecto destino)' : '.');
        $to = $count === 1 ? route('projects.show', [$target->id, 'tarjeta' => $ids[0]]) : route('projects.show', $target->id);

        return redirect($to)->with('success', $msg);
    }

    private function copyAttachment(CardAttachment $src, Card $copy, array $extra = []): CardAttachment
    {
        $attrs = ['user_id' => $src->user_id, 'kind' => $src->kind, 'name' => $src->name, 'url' => $src->url, 'mime' => $src->mime, 'size' => $src->size, 'path' => null, ...$extra];
        if ($src->path && Storage::disk('local')->exists($src->path)) {
            $ext = pathinfo($src->path, PATHINFO_EXTENSION);
            $attrs['path'] = "proyectos/{$copy->project_id}/tarjetas/{$copy->id}/".Str::random(12).($ext ? '.'.$ext : '');
            Storage::disk('local')->copy($src->path, $attrs['path']);
        }

        return $copy->allAttachments()->create($attrs);
    }

    public function destroy(Request $request, Card $card): RedirectResponse
    {
        return $this->attempt(function () use ($request, $card) {
            $project = $card->project;
            $this->authorize('work', $project);
            $mine = $card->created_by === $request->user()->id;
            if (! $mine && $project->roleOf($request->user()) !== ProjectRole::Manager) {
                throw new InvalidArgumentException('Solo quien creó la tarjeta o un moderador puede eliminarla.');
            }
            $card->delete();
            BoardChanged::dispatch($project->id, 'card');
        }, 'Tarjeta eliminada.');
    }

    /** Coerce a raw value to what the field type stores; anything invalid becomes empty. */
    private function cleanValue(CustomField $field, mixed $value, Project $project): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($field->type) {
            CustomFieldType::Text => mb_substr((string) $value, 0, 1000),
            CustomFieldType::Textarea => mb_substr((string) $value, 0, 5000),
            CustomFieldType::Image => null,
            CustomFieldType::Url => filter_var($value, FILTER_VALIDATE_URL) ? (string) $value : null,
            CustomFieldType::Number => is_numeric($value) ? (float) $value : null,
            CustomFieldType::Date => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? (string) $value : null,
            CustomFieldType::Checkbox => (bool) $value,
            CustomFieldType::Select => in_array($value, $field->options ?? [], true) ? (string) $value : null,
            CustomFieldType::MultiSelect => array_values(array_intersect((array) $value, $field->options ?? [])) ?: null,
            CustomFieldType::User => $project->members()->whereKey((int) $value)->exists() ? (int) $value : null,
        };
    }
}
