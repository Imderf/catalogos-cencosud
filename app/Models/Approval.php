<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    public const VALIDATION = 'validation';

    public const PRINTING = 'print';

    protected $fillable = ['page_id', 'version', 'user_id', 'role', 'phase', 'delay_minutes'];

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
