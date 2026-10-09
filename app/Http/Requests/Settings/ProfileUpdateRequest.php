<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/** Users only manage their display name; email and role belong to the administrator. */
class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'notification_mode' => ['required', 'in:instant,daily'],
            'notification_sound' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'notification_mode' => 'modo de notificación', 'notification_sound' => 'sonido'];
    }
}
