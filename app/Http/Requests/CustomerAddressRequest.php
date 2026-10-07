<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        return ['label' => ['required', 'string', 'max:255'], 'address' => ['required', 'string', 'max:5000'], 'city' => ['nullable', 'string', 'max:255'], 'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'], 'entrance' => ['nullable', 'string', 'max:255'], 'floor' => ['nullable', 'string', 'max:255'], 'apartment' => ['nullable', 'string', 'max:255'], 'instructions' => ['nullable', 'string', 'max:10000'], 'is_default' => ['required', 'boolean']];
    }
}
