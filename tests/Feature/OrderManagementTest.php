<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\DeliveryTask;
use App\Models\Item;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_replace(['recipient_name' => 'Order recipient', 'recipient_phone' => '123', 'address' => 'Rustaveli Avenue', 'date_from' => '2026-10-10', 'date_to' => '2026-10-12', 'priority' => 'normal', 'items' => []], $extra);
    }

    public function test_order_range_defaults_and_validation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operator']));
        $id = $this->postJson('/api/orders', $this->payload())->assertCreated()->assertJsonPath('data.date_from', '2026-10-10')->assertJsonPath('data.date_to', '2026-10-12')->json('data.id');
        $this->getJson('/api/orders/'.$id)->assertOk()->assertJsonPath('data.time_from', '09:00')->assertJsonPath('data.time_to', '18:00');
        foreach (['2026-10-10', '2026-10-11', '2026-10-12'] as $date) {
            $this->getJson('/api/orders?date='.$date)->assertJsonPath('meta.total', 1);
        }
        $this->getJson('/api/orders?date=2026-10-13')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/orders?date_from=2026-10-11&date_to=2026-10-11')->assertJsonPath('meta.total', 1);
        $this->putJson('/api/orders/'.$id, $this->payload(['time_from' => '10:30', 'time_to' => '16:45']))->assertOk()->assertJsonPath('data.time_from', '10:30');
        $this->postJson('/api/orders', $this->payload(['date_to' => '2026-10-09']))->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->postJson('/api/orders', $this->payload(['time_from' => '19:00', 'time_to' => '18:00']))->assertUnprocessable()->assertJsonValidationErrors('time_to');
    }

    public function test_ride_eligibility_uses_inclusive_range_and_updates_cannot_exclude_ride_date(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create();
        $this->actingAs($operator);
        $id = $this->postJson('/api/orders', $this->payload())->json('data.id');
        $ride = Ride::create(['driver_id' => $driver->id, 'created_by' => $operator->id, 'date' => '2026-10-11']);
        $this->postJson('/api/rides/'.$ride->id.'/tasks', ['task_ids' => [$id]])->assertOk()->assertJsonPath('data.tasks.0.id', $id);
        $this->putJson('/api/orders/'.$id, $this->payload(['driver_id' => $driver->id, 'date_from' => '2026-10-11', 'date_to' => '2026-10-15']))->assertOk();
        $this->putJson('/api/orders/'.$id, $this->payload(['driver_id' => $driver->id, 'date_from' => '2026-10-12']))->assertUnprocessable();
        $outside = Ride::create(['driver_id' => $driver->id, 'created_by' => $operator->id, 'date' => '2026-10-16']);
        $other = $this->postJson('/api/orders', $this->payload())->json('data.id');
        $this->postJson('/api/rides/'.$outside->id.'/tasks', ['task_ids' => [$other]])->assertUnprocessable();
    }

    public function test_only_admin_can_soft_delete_and_records_history_and_ride_survive(): void
    {
        $admin = User::factory()->create(['role' => 'superadministrator']);
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create();
        $this->actingAs($admin);
        $id = $this->postJson('/api/orders', $this->payload(['driver_id' => $driver->id, 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]))->json('data.id');
        $ride = Ride::create(['driver_id' => $driver->id, 'created_by' => $admin->id, 'date' => today()->toDateString()]);
        $this->postJson('/api/rides/'.$ride->id.'/tasks', ['task_ids' => [$id]])->assertOk();
        $this->postJson('/api/rides/'.$ride->id.'/status', ['status' => 'started'])->assertOk();
        $this->actingAs($operator)->deleteJson('/api/orders/'.$id)->assertForbidden();
        $this->actingAs($driver)->deleteJson('/api/orders/'.$id)->assertForbidden();
        $this->actingAs($admin)->deleteJson('/api/orders/'.$id)->assertNoContent();
        $this->assertSoftDeleted('delivery_tasks', ['id' => $id]);
        $deleted = DeliveryTask::withTrashed()->findOrFail($id);
        $this->assertSame($ride->id, $deleted->ride_id);
        $this->assertSame(DeliveryStatus::Assigned, $deleted->status);
        $this->assertDatabaseHas('delivery_task_status_history', ['delivery_task_id' => $id, 'user_id' => $admin->id, 'notes' => 'Order deleted']);
        $this->getJson('/api/orders/'.$id)->assertNotFound();
        $this->getJson('/api/orders')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/dashboard')->assertJsonPath('data.total', 0);
        $this->getJson('/api/rides/'.$ride->id)->assertJsonCount(0, 'data.tasks');
        $this->postJson('/api/rides/'.$ride->id.'/status', ['status' => 'completed'])->assertOk();
        $this->actingAs($driver)->getJson('/api/driver/today')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/driver/orders/'.$id)->assertNotFound();
    }

    public function test_deleted_terminal_order_retains_items_and_driver_role_protection(): void
    {
        $admin = User::factory()->create(['role' => 'superadministrator']);
        $driver = User::factory()->create();
        $item = Item::create(['name' => 'Parcel', 'active' => true]);
        $this->actingAs($admin);
        $id = $this->postJson('/api/orders', $this->payload(['driver_id' => $driver->id, 'items' => [['item_id' => $item->id, 'quantity' => 2]]]))->json('data.id');
        DeliveryTask::findOrFail($id)->update(['status' => DeliveryStatus::Delivered]);
        $this->deleteJson('/api/orders/'.$id)->assertNoContent();
        $this->assertDatabaseHas('delivery_task_items', ['delivery_task_id' => $id, 'item_id' => $item->id, 'quantity' => 2]);
        $this->putJson('/api/users/'.$driver->id, ['name' => $driver->name, 'email' => $driver->email, 'phone' => $driver->phone, 'role' => 'operator', 'active' => true])->assertUnprocessable()->assertJsonValidationErrors('role');
    }
}
