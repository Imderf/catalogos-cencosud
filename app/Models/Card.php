<?php

namespace App\Models;

use App\Enums\CardPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Card extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'board_list_id', 'title', 'description', 'position', 'priority', 'start_date', 'due_date', 'completed_at', 'created_by', 'catalog_id', 'catalog_auto'];

    protected function casts(): array
    {
        return ['priority' => CardPriority::class, 'start_date' => 'date', 'due_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'board_list_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_user');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'card_label');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    /** The card's own conversation; subtasks keep theirs apart. */
    public function comments(): HasMany
    {
        return $this->hasMany(CardComment::class)->whereNull('subtask_id')->orderBy('created_at');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }

    /** The card's own attachments: not those of a comment nor the value of an image field. */
    public function attachments(): HasMany
    {
        return $this->hasMany(CardAttachment::class)->whereNull('card_comment_id')->whereNull('custom_field_id')->whereNull('subtask_id')->orderBy('created_at');
    }

    public function allAttachments(): HasMany
    {
        return $this->hasMany(CardAttachment::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(CardFieldValue::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CardActivity::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** People who care about this card: its assignees and whoever created it. */
    public function watchers(): Collection
    {
        $people = $this->assignees->keyBy('id');
        if ($this->creator) {
            $people->put($this->creator->id, $this->creator);
        }

        return $people->values();
    }
}
