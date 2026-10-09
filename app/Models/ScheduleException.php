<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

/** A one-day change to the weekly schedule. See the migration for the three kinds. */
class ScheduleException extends Model
{
    public const CACHE = 'schedule.exceptions';

    protected $fillable = ['date', 'kind', 'from_minutes', 'to_minutes', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::CACHE);
        static::saved($forget);
        static::deleted($forget);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fromLabel(): ?string
    {
        return $this->from_minutes === null ? null : sprintf('%02d:%02d', intdiv($this->from_minutes, 60), $this->from_minutes % 60);
    }

    public function toLabel(): ?string
    {
        return $this->to_minutes === null ? null : sprintf('%02d:%02d', intdiv($this->to_minutes, 60), $this->to_minutes % 60);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'extend' => 'Horario ampliado para todos',
            'closed' => 'Día cerrado',
            default => 'Personas habilitadas',
        };
    }
}
