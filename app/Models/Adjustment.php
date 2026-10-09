<?php

namespace App\Models;

use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id', 'version', 'author_id', 'origin_role', 'type', 'types', 'text', 'box', 'status',
        'resolved_in_version', 'resolved_at', 'route', 'is_defect', 'stage_since', 'received_by_agency_at', 'agency_started_at', 'alert_level',
    ];

    protected function casts(): array
    {
        return [
            'box' => 'array',
            'types' => 'array',
            'route' => 'array',
            'type' => AdjustmentType::class,
            'status' => AdjustmentStatus::class,
            'origin_role' => Role::class,
            'is_defect' => 'boolean',
            'resolved_at' => 'datetime',
            'stage_since' => 'datetime',
            'received_by_agency_at' => 'datetime',
            'agency_started_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(AdjustmentValidation::class)->orderBy('created_at');
    }

    /** Every kind this adjustment is about; `type` is just the first of them. @return list<AdjustmentType> */
    public function typeList(): array
    {
        $list = array_values(array_unique(array_filter($this->types ?? [$this->type->value])));

        return array_map(fn (string $v) => AdjustmentType::from($v), $list ?: [$this->type->value]);
    }

    /** @param  AdjustmentType|list<AdjustmentType>  $type  @return non-empty-list<AdjustmentType> */
    public static function normalizeTypes(AdjustmentType|array $type): array
    {
        $types = array_values(array_unique(is_array($type) ? $type : [$type], SORT_REGULAR));
        if ($types === []) {
            throw new \InvalidArgumentException('Elige al menos un tipo de ajuste.');
        }

        return $types;
    }

    public function typeLabel(): string
    {
        return implode(' + ', array_map(fn (AdjustmentType $t) => $t->label(), $this->typeList()));
    }

    public function typeShort(): string
    {
        return implode(' + ', array_map(fn (AdjustmentType $t) => $t->short(), $this->typeList()));
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AdjustmentAttachment::class)->orderBy('id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(AdjustmentReply::class)->orderBy('created_at');
    }

    public function isOpen(): bool
    {
        return $this->status === AdjustmentStatus::Open;
    }

    /** Role holding the adjustment right now; null once resolved. */
    public function currentStage(): ?Role
    {
        if (! $this->isOpen() || empty($this->route)) {
            return null;
        }

        return Role::from($this->route[0]);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', AdjustmentStatus::Open->value);
    }
}
