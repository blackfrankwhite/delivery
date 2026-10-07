<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDispatcher() || $this->user()->role === Role::Driver;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()->isDispatcher()) {
            $this->merge(['driver_id' => $this->user()->id]);
        }
        foreach (['storage_location_id', 'driver_id', 'customer_id', 'customer_address_id'] as $field) {
            if ($this->filled($field) && filter_var($this->input($field), FILTER_VALIDATE_INT) !== false) {
                $this->merge([$field => (int) $this->input($field)]);
            }
        }
    }

    public function rules(): array
    {
        return ['storage_location_id' => ['nullable', 'integer', Rule::exists('storage_locations', 'id')->whereNull('deleted_at')->where('active', true)], 'driver_id' => ['required', Rule::exists('users', 'id')->where('role', 'driver')->where('active', true)], 'date' => ['required', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:10000']];
    }
}
