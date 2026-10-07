<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryLocationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace(['recipient_name' => 'Nino', 'recipient_phone' => '123', 'address' => '12 Rustaveli Avenue', 'delivery_date' => today()->toDateString(), 'priority' => 'normal', 'items' => [], 'latitude' => 41.7151234, 'longitude' => 44.8271234], $overrides);
    }

    public function test_selected_pin_is_persisted_visible_to_assigned_driver_and_is_a_snapshot(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create(['role' => 'driver']);
        $customer = Customer::create(['name' => 'Nino', 'phone' => '123']);
        $address = $customer->addresses()->create(['label' => 'Home', 'address' => '12 Rustaveli Avenue', 'latitude' => 41.7151234, 'longitude' => 44.8271234]);
        $payload = $this->payload(['customer_id' => $customer->id, 'customer_address_id' => $address->id, 'driver_id' => $driver->id]);
        $id = $this->actingAs($operator)->postJson('/api/delivery-tasks', $payload)->assertCreated()->assertJsonPath('data.latitude', 41.7151234)->json('data.id');
        $this->putJson('/api/customers/'.$customer->id.'/addresses/'.$address->id, ['label' => 'Home', 'address' => 'New location', 'latitude' => 41.72, 'longitude' => 44.83, 'is_default' => false])->assertOk();
        $this->actingAs($driver)->getJson('/api/driver/tasks/'.$id)->assertOk()->assertJsonPath('data.latitude', 41.7151234)->assertJsonPath('data.longitude', 44.8271234);
        $this->putJson('/api/delivery-tasks/'.$id, $this->payload(['driver_id' => $driver->id, 'latitude' => 0, 'longitude' => 0]))->assertForbidden();
        $this->getJson('/api/driver/tasks/'.$id)->assertJsonPath('data.latitude', 41.7151234);
        $this->actingAs($operator)->putJson('/api/delivery-tasks/'.$id, array_replace($payload, ['latitude' => 41.716, 'longitude' => 44.829]))->assertOk()->assertJsonPath('data.latitude', 41.716);
        $this->assertSame(41.72, $address->refresh()->latitude);
    }

    public function test_coordinate_pairs_and_ranges_are_validated_in_delivery_and_saved_address_forms(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $customer = Customer::create(['name' => 'Nino', 'phone' => '123']);
        $this->actingAs($operator)->postJson('/api/delivery-tasks', $this->payload(['longitude' => null]))->assertUnprocessable()->assertJsonValidationErrors('longitude');
        $this->postJson('/api/delivery-tasks', $this->payload(['latitude' => 91, 'longitude' => 181]))->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->postJson('/api/customers/'.$customer->id.'/addresses', ['label' => 'Home', 'address' => 'Address', 'latitude' => 0, 'is_default' => false])->assertUnprocessable()->assertJsonValidationErrors('longitude');
        $this->postJson('/api/customers/'.$customer->id.'/addresses', ['label' => 'Home', 'address' => 'Address', 'longitude' => 44, 'is_default' => false])->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }

    public function test_zero_coordinates_and_removing_both_coordinates_are_supported(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operator']));
        $id = $this->postJson('/api/delivery-tasks', $this->payload(['latitude' => 0, 'longitude' => 0]))->assertCreated()->assertJsonPath('data.latitude', 0)->assertJsonPath('data.longitude', 0)->json('data.id');
        $this->putJson('/api/delivery-tasks/'.$id, $this->payload(['latitude' => null, 'longitude' => null]))->assertOk()->assertJsonPath('data.latitude', null)->assertJsonPath('data.longitude', null);
    }
}
