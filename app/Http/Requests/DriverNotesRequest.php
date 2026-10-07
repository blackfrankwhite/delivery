<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['driver_notes' => ['nullable', 'string', 'max:10000']];
    }
}
