<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Post Production Defect: a page reopened after the whole chain had approved it. */
class Defect extends Model
{
    protected $fillable = ['page_id', 'version', 'user_id', 'role', 'type', 'reason', 'adjustment_id'];

    protected function casts(): array
    {
        return ['role' => Role::class, 'type' => AdjustmentType::class];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class);
    }
}
