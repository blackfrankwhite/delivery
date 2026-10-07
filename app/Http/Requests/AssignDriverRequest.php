<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        return ['driver_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'driver')->where('active', true)]];
    }
}
