<?php

namespace App\Models;

use App\Enums\CardPriority;
use App\Enums\SubtaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A piece of a card: its own status, people, date and conversation. */
class Subtask extends Model
{
    use HasFactory;

    protected $fillable = ['card_id', 'page_id', 'project_id', 'title', 'description', 'status', 'priority', 'due_date', 'completed_at', 'position', 'created_by'];

    protected function casts(): array
    {
        return ['status' => SubtaskStatus::class, 'priority' => CardPriority::class, 'due_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subtask_user');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CardComment::class)->orderBy('created_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CardActivity::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function intakes(): HasMany
    {
        return $this->hasMany(AdjustmentIntake::class)->orderByDesc('id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class)->orderBy('position');
    }

    /** Page subtasks follow the review: nobody sets their status by hand. */
    public function isAutomatic(): bool
    {
        return $this->page_id !== null;
    }

    public function isOpen(): bool
    {
        return ! $this->status->isClosed();
    }
}
