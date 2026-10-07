<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RidePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('ride'));
    }

    public function rules(): array
    {
        return [
            'storage_location_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')->whereNull('deleted_at')->where('active', true)],
            'optimize' => ['required', 'boolean'],
        ];
    }
}
