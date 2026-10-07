<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddressSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:3', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
