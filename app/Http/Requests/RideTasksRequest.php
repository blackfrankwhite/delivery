<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RideTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('ride'));
    }

    public function rules(): array
    {
        return ['task_ids' => ['required', 'array', 'min:1'], 'task_ids.*' => ['required', 'integer', 'distinct', 'exists:delivery_tasks,id']];
    }
}
