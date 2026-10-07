<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'], 'date' => ['nullable', 'date_format:Y-m-d'], 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'max:50'], 'driver_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])], 'role' => ['nullable', Rule::enum(Role::class)],
            'active' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
