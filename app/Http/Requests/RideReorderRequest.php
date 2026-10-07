<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RideReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('ride'));
    }

    public function rules(): array
    {
        return ['task_ids' => ['present', 'array'], 'task_ids.*' => ['required', 'integer', 'distinct', 'exists:delivery_tasks,id']];
    }
}
