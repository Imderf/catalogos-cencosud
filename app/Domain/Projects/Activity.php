<?php

namespace App\Domain\Projects;

use App\Events\Projects\ActivityLogged;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\Subtask;
use App\Models\User;

/** Writes one history line per meaningful change on a card. */
final class Activity
{
    public function log(Card $card, ?User $by, string $action, array $meta = [], ?Subtask $subtask = null): CardActivity
    {
        if ($subtask) {
            $meta['subtask'] = $subtask->title;
        }
        $activity = CardActivity::create([
            'card_id' => $card->id, 'subtask_id' => $subtask?->id, 'project_id' => $card->project_id, 'user_id' => $by?->id,
            'action' => $action, 'meta' => $meta ?: null, 'created_at' => now(),
        ]);
        ActivityLogged::dispatch($card->project_id, $by, $card, self::describe($activity));

        return $activity;
    }

    /** Human sentence for a stored line. */
    public static function describe(CardActivity $a): string
    {
        $m = $a->meta ?? [];

        $in = isset($m['subtask']) ? ' en la subtarea "'.$m['subtask'].'"' : '';

        return match ($a->action) {
            'catalog_linked' => 'ligó el catálogo "'.($m['catalog'] ?? '').'" a esta tarea',
            'subtask_created' => 'creó la subtarea "'.($m['subtask'] ?? '').'"',
            'subtask_status' => 'pasó "'.($m['subtask'] ?? '').'" a '.($m['to'] ?? ''),
            'subtask_deleted' => 'eliminó la subtarea "'.($m['subtask'] ?? '').'"',
            'subtask_renamed' => 'renombró la subtarea a "'.($m['to'] ?? '').'"',
            'subtask_due' => isset($m['to']) ? 'fijó la fecha de "'.($m['subtask'] ?? '').'" en '.$m['to'] : 'quitó la fecha de "'.($m['subtask'] ?? '').'"',
            'subtask_assigned' => 'asignó a '.($m['who'] ?? '').' a "'.($m['subtask'] ?? '').'"',
            'subtask_unassigned' => 'retiró a '.($m['who'] ?? '').' de "'.($m['subtask'] ?? '').'"',
            'subtask_commented' => 'comentó'.$in,
            'intake_approved' => 'aprobó el ingreso de un comentario de '.($m['who'] ?? 'cliente').$in,
            'intake_dismissed' => 'descartó un comentario de '.($m['who'] ?? 'cliente').$in,
            'deliverable_added' => 'agregó el entregable "'.($m['title'] ?? '').'"'.$in,
            'deliverable_status' => 'puso "'.($m['title'] ?? '').'" en '.($m['to'] ?? '').$in,
            'deliverable_removed' => 'quitó el entregable "'.($m['title'] ?? '').'"'.$in,
            'created' => 'creó la tarjeta',
            'duplicated' => 'duplicó la tarjeta desde "'.($m['from'] ?? '').'"',
            'moved' => 'la movió de "'.($m['from'] ?? '').'" a "'.($m['to'] ?? '').'"',
            'renamed' => 'cambió el título a "'.($m['to'] ?? '').'"',
            'description' => 'editó la descripción',
            'priority' => 'puso prioridad '.($m['to'] ?? ''),
            'start_date' => isset($m['to']) ? 'fijó la fecha de inicio en '.$m['to'] : 'quitó la fecha de inicio',
            'due_date' => isset($m['to']) ? 'fijó la fecha límite en '.$m['to'] : 'quitó la fecha límite',
            'assigned' => 'asignó a '.($m['who'] ?? ''),
            'unassigned' => 'retiró a '.($m['who'] ?? ''),
            'labels' => 'cambió las etiquetas',
            'completed' => 'completó la tarjeta',
            'reopened' => 'reabrió la tarjeta',
            'commented' => 'comentó',
            'attached' => 'adjuntó "'.($m['name'] ?? '').'"',
            'detached' => 'quitó el adjunto "'.($m['name'] ?? '').'"',
            'field' => 'cambió el campo "'.($m['field'] ?? '').'"',
            default => $a->action,
        };
    }
}
