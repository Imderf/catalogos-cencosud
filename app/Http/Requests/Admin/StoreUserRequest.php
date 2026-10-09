<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'organization' => ['nullable', 'string', 'max:120'],
            'role' => ['required', new Enum(Role::class)],
            'sectors' => [Rule::requiredIf(fn () => $this->input('role') === Role::Comercial->value), 'array'],
            'sectors.*' => ['integer', Rule::exists('sectors', 'id')],
            'password' => ['required', Password::defaults()],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'organization' => 'organización',
            'role' => 'rol',
            'sectors' => 'sectores',
            'password' => 'contraseña',
        ];
    }

    public function messages(): array
    {
        return [
            'sectors.required' => 'Un usuario comercial necesita al menos un sector.',
        ];
    }
}
