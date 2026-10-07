<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_number' => 'DEL-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'created_by' => User::factory()->state(['role' => 'operator']), 'recipient_name' => fake()->name(), 'recipient_phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(), 'city' => 'Tbilisi', 'delivery_date' => today()->toDateString(), 'status' => 'pending', 'priority' => 'normal',
        ];
    }
}
