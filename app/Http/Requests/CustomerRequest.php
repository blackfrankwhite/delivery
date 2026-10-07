<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        $rules = ['name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'], 'active' => ['required', 'boolean']];
        $rules['default_address'] = [$this->isMethod('POST') ? 'required' : 'sometimes', 'array'];
        foreach (($this->isMethod('POST') || $this->has('default_address') ? (new CustomerAddressRequest)->rules() : []) as $key => $constraints) {
            $rules['default_address.'.$key] = $constraints;
            if (in_array($key, ['latitude', 'longitude'])) {
                $rules['default_address.'.$key] = array_map(fn ($rule) => str_starts_with($rule, 'required_with:') ? 'required_with:default_address.'.substr($rule, 14) : $rule, $constraints);
            }
        }

        return $rules;
    }
}
