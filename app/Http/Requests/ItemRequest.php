<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'sku' => ['nullable', 'string', 'max:255', Rule::unique('items')->ignore($this->route('item'))], 'description' => ['nullable', 'string', 'max:10000'], 'active' => ['required', 'boolean']];
    }
}
