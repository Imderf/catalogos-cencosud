<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardFieldValue extends Model
{
    protected $fillable = ['card_id', 'custom_field_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}
