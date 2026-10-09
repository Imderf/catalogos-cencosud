<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deliverable extends Model
{
    public const STATUSES = ['pending' => 'Pendiente', 'in_progress' => 'En proceso', 'done' => 'Listo'];

    protected $fillable = ['subtask_id', 'intake_id', 'title', 'status', 'assignee_id', 'due_date', 'done_at', 'position', 'created_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'done_at' => 'datetime'];
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function intake(): BelongsTo
    {
        return $this->belongsTo(AdjustmentIntake::class, 'intake_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function isOpen(): bool
    {
        return $this->status !== 'done';
    }
}
