<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDispatcher();
    }

    protected function prepareForValidation(): void
    {
        $from = $this->input('date_from', $this->input('delivery_date'));
        $this->merge(['date_from' => $from, 'date_to' => $this->input('date_to', $from), 'delivery_date' => $from, 'time_from' => $this->input('time_from') ?: '09:00', 'time_to' => $this->input('time_to') ?: '18:00']);
        foreach (['driver_id', 'customer_id', 'customer_address_id'] as $field) {
            if ($this->filled($field) && filter_var($this->input($field), FILTER_VALIDATE_INT) !== false) {
                $this->merge([$field => (int) $this->input($field)]);
            }
        }
        if (is_int($this->input('customer_id'))) {
            $client = Customer::find($this->input('customer_id'));
            $address = $client?->addresses()->orderByDesc('is_default')->orderBy('id')->first();
            $this->merge(['customer_address_id' => $address?->id]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('active', true)->when($this->route('deliveryTask'), fn ($q) => $q->orWhere('id', $this->route('deliveryTask')->customer_id)))],
            'customer_address_id' => ['required', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $this->input('customer_id'))->whereNull('deleted_at')],
            'driver_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'driver')->where(fn ($q) => $q->where('active', true)->when($this->route('deliveryTask'), fn ($q) => $q->orWhere('id', $this->route('deliveryTask')->driver_id)))],
            'date_from' => ['required', 'date_format:Y-m-d'], 'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'], 'delivery_date' => ['required', 'date_format:Y-m-d'], 'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', Rule::when($this->filled('time_from'), 'after_or_equal:time_from')],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'notes' => ['nullable', 'string', 'max:10000'],
            'items' => ['present', 'array', 'max:100'], 'items.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where(fn ($q) => $q->where('active', true)->when($this->route('deliveryTask'), fn ($q) => $q->orWhereIn('id', $this->route('deliveryTask')->items()->pluck('items.id')->all())))],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
