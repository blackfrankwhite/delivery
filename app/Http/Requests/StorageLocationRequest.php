<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class StorageLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === Role::Superadministrator;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'active' => ['required', 'boolean'],
        ];
    }
}
