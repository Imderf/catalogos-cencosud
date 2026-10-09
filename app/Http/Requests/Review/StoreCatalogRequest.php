<?php

namespace App\Http\Requests\Review;

use App\Enums\CatalogKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalogs.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'brand_id' => ['required', Rule::exists('brands', 'id')],
            'kind' => ['required', new Enum(CatalogKind::class)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'card_id' => ['nullable', Rule::exists('cards', 'id')],
            'pages' => ['required', 'array', 'min:1', 'max:64'],
            'pages.*.image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'pages.*.sectors' => ['required', 'array', 'min:1'],
            'pages.*.sectors.*' => ['integer', Rule::exists('sectors', 'id')],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'brand_id' => 'marca', 'kind' => 'tipo', 'pages' => 'páginas', 'pages.*.image' => 'imagen', 'pages.*.sectors' => 'sectores'];
    }

    public function messages(): array
    {
        return [
            'pages.required' => 'Sube al menos una página.',
            'pages.*.sectors.required' => 'Toda página necesita al menos un sector.',
            'pages.*.sectors.min' => 'Toda página necesita al menos un sector.',
        ];
    }
}
