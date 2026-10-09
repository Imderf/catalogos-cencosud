<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdjustmentIntake extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DISMISSED = 'dismissed';

    protected $fillable = ['subtask_id', 'adjustment_id', 'status', 'decided_by', 'decided_at', 'reason'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function deliverable(): HasOne
    {
        return $this->hasOne(Deliverable::class, 'intake_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
