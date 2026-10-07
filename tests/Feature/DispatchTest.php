<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryTask;
use App\Models\Item;
use App\Models\Ride;
use App\Models\User;
use Database\Seeders\AdministratorSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'operator'): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['recipient_name' => 'Nino', 'recipient_phone' => '+995555123456', 'address' => '12 Rustaveli Avenue', 'city' => 'Tbilisi', 'delivery_date' => today()->toDateString(), 'priority' => 'normal', 'items' => []], $overrides);
    }

    public function test_session_login_current_user_logout_and_invalid_credentials(): void
    {
        $user = User::factory()->create(['role' => 'superadministrator', 'password' => 'password123']);
        $this->withHeaders(['Origin' => 'http://localhost:5173'])->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->assertOk()->assertJsonPath('data.id', $user->id);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.role', 'superadministrator');
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/delivery-tasks')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_admin_can_create_accounts_and_operator_cannot_manage_users(): void
    {
        $this->actingAs($this->account('superadministrator'))->postJson('/api/users', ['name' => 'Driver', 'email' => 'driver@example.com', 'password' => 'password123', 'role' => 'driver', 'active' => true])->assertCreated();
        $operator = $this->account();
        $this->actingAs($operator)->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', ['name' => 'Admin', 'email' => 'other@example.com', 'password' => 'password123', 'role' => 'superadministrator', 'active' => true])->assertForbidden();
        $this->getJson('/api/drivers')->assertOk();
    }

    public function test_create_update_attach_items_and_assign_delivery(): void
    {
        $item = Item::create(['name' => 'Package', 'active' => true]);
        $driver = $this->account('driver');
        $this->actingAs($this->account());
        $response = $this->postJson('/api/delivery-tasks', $this->payload(['items' => [['item_id' => $item->id, 'quantity' => 2, 'notes' => 'Fragile']]]))->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.items.0.quantity', 2);
        $id = $response->json('data.id');
        $this->assertMatchesRegularExpression('/^DEL-\d{8}-\d{4,}$/', $response->json('data.reference_number'));
        $this->putJson("/api/delivery-tasks/$id", $this->payload(['address' => '24 Pekini Avenue']))->assertOk()->assertJsonPath('data.address', '24 Pekini Avenue');
        $this->postJson("/api/delivery-tasks/$id/assign-driver", ['driver_id' => $driver->id])->assertOk()->assertJsonPath('data.status', 'assigned');
        $this->assertDatabaseHas('delivery_task_status_history', ['delivery_task_id' => $id, 'from_status' => 'pending', 'to_status' => 'assigned']);
        $this->postJson('/api/delivery-tasks', $this->payload())->assertCreated()->assertJsonPath('data.reference_number', 'DEL-'.now()->format('Ymd').'-0002');
    }

    public function test_validation_customer_address_ownership_quantity_driver_and_time_window(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'phone' => '123', 'active' => true]);
        $address = $customer->addresses()->create(['label' => 'Home', 'address' => 'Address']);
        $item = Item::create(['name' => 'Box']);
        $operator = $this->account();
        $this->actingAs($operator)->postJson('/api/delivery-tasks', $this->payload(['customer_address_id' => $address->id, 'driver_id' => $operator->id, 'time_from' => '12:00', 'time_to' => '11:00', 'items' => [['item_id' => $item->id, 'quantity' => 0]]]))->assertUnprocessable()->assertJsonValidationErrors(['customer_address_id', 'driver_id', 'time_to', 'items.0.quantity']);
    }

    public function test_filters_search_date_range_status_driver_customer_priority_and_pagination(): void
    {
        $driver = $this->account('driver');
        $customer = Customer::create(['name' => 'Customer', 'phone' => '123']);
        $this->actingAs($this->account());
        $this->postJson('/api/delivery-tasks', $this->payload(['driver_id' => $driver->id, 'customer_id' => $customer->id, 'priority' => 'urgent']))->assertCreated();
        $this->postJson('/api/delivery-tasks', $this->payload(['recipient_name' => 'Other']))->assertCreated();
        $query = http_build_query(['search' => 'Rustaveli', 'date' => today()->toDateString(), 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString(), 'status' => 'assigned', 'driver_id' => $driver->id, 'customer_id' => $customer->id, 'priority' => 'urgent', 'per_page' => 1]);
        $this->getJson('/api/delivery-tasks?'.$query)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.recipient_name', 'Nino');
        $this->getJson('/api/delivery-tasks?per_page=1000')->assertUnprocessable();
    }

    public function test_driver_is_scoped_and_cannot_access_administration_or_change_ownership(): void
    {
        $driver = $this->account('driver');
        $other = $this->account('driver');
        $mine = DeliveryTask::factory()->create(['driver_id' => $driver->id, 'status' => 'assigned']);
        $theirs = DeliveryTask::factory()->create(['driver_id' => $other->id, 'status' => 'assigned']);
        $this->actingAs($driver)->getJson('/api/driver/today')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/delivery-tasks')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/driver/tasks/'.$theirs->id)->assertForbidden();
        $this->postJson('/api/delivery-tasks/'.$theirs->id.'/status', ['status' => 'started'])->assertForbidden();
        foreach (['users', 'customers', 'items', 'drivers', 'operators', 'dashboard'] as $endpoint) {
            $this->getJson('/api/'.$endpoint)->assertForbidden();
        }
        $this->postJson('/api/delivery-tasks', $this->payload())->assertForbidden();
        $this->postJson('/api/delivery-tasks/'.$mine->id.'/assign-driver', ['driver_id' => $other->id])->assertForbidden();
    }

    public function test_driver_status_workflow_timestamps_history_notes_and_finality(): void
    {
        $driver = $this->account('driver');
        $task = DeliveryTask::factory()->create(['driver_id' => $driver->id, 'status' => 'assigned']);
        $url = '/api/delivery-tasks/'.$task->id;
        $this->actingAs($driver)->postJson($url.'/status', ['status' => 'delivered'])->assertUnprocessable();
        foreach (['started', 'arrived', 'delivered'] as $status) {
            $this->postJson($url.'/status', ['status' => $status])->assertOk()->assertJsonPath('data.status', $status);
        }
        $task->refresh();
        $this->assertNotNull($task->started_at);
        $this->assertNotNull($task->arrived_at);
        $this->assertNotNull($task->completed_at);
        $this->assertSame(3, $task->history()->count());
        $this->assertTrue($task->completed_at->equalTo($task->history()->latest('id')->first()->created_at));
        $this->patchJson($url.'/notes', ['driver_notes' => 'Left at reception'])->assertOk()->assertJsonPath('data.driver_notes', 'Left at reception');
        $this->postJson($url.'/status', ['status' => 'started'])->assertUnprocessable();
        $this->postJson($url.'/status', ['status' => 'cancelled'])->assertUnprocessable();
    }

    public function test_failure_requires_reason_and_dispatcher_can_cancel_pending(): void
    {
        $driver = $this->account('driver');
        $task = DeliveryTask::factory()->create(['driver_id' => $driver->id, 'status' => 'assigned']);
        $this->actingAs($driver)->postJson('/api/delivery-tasks/'.$task->id.'/status', ['status' => 'failed'])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->postJson('/api/delivery-tasks/'.$task->id.'/status', ['status' => 'failed', 'notes' => 'Recipient unavailable'])->assertOk();
        $pending = DeliveryTask::factory()->create();
        $this->actingAs($this->account())->postJson('/api/delivery-tasks/'.$pending->id.'/status', ['status' => 'cancelled'])->assertOk();
        $this->assertNotNull($pending->refresh()->cancelled_at);
    }

    public function test_rides_create_add_reorder_remove_and_prevent_invalid_membership(): void
    {
        $driver = $this->account('driver');
        $operator = $this->account();
        $tasks = DeliveryTask::factory()->count(2)->create(['created_by' => $operator->id]);
        $this->actingAs($operator);
        $rideId = $this->postJson('/api/rides', ['driver_id' => $driver->id, 'date' => today()->toDateString()])->assertCreated()->json('data.id');
        $this->postJson("/api/rides/$rideId/tasks", ['task_ids' => $tasks->modelKeys()])->assertOk()->assertJsonCount(2, 'data.tasks');
        $this->assertSame($driver->id, $tasks[0]->refresh()->driver_id);
        $this->putJson("/api/rides/$rideId/reorder", ['task_ids' => [$tasks[1]->id, $tasks[0]->id]])->assertOk()->assertJsonPath('data.tasks.0.id', $tasks[1]->id);
        $this->putJson("/api/rides/$rideId/reorder", ['task_ids' => [$tasks[0]->id]])->assertUnprocessable();
        $this->postJson("/api/rides/$rideId/tasks", ['task_ids' => [$tasks[0]->id]])->assertUnprocessable();
        $other = $this->account('driver');
        $this->postJson('/api/delivery-tasks/'.$tasks[0]->id.'/assign-driver', ['driver_id' => $other->id])->assertUnprocessable();
        $this->deleteJson("/api/rides/$rideId/tasks/".$tasks[0]->id)->assertOk()->assertJsonCount(1, 'data.tasks');
        $this->assertNull($tasks[0]->refresh()->ride_id);
    }

    public function test_ride_driver_authorization_and_start_complete_rules(): void
    {
        $operator = $this->account();
        $driver = $this->account('driver');
        $other = $this->account('driver');
        $ride = Ride::create(['driver_id' => $driver->id, 'date' => today()->toDateString(), 'created_by' => $operator->id]);
        $this->actingAs($other)->getJson('/api/rides/'.$ride->id)->assertForbidden();
        $this->postJson('/api/rides/'.$ride->id.'/status', ['status' => 'started'])->assertForbidden();
        $this->putJson('/api/rides/'.$ride->id.'/reorder', ['task_ids' => []])->assertForbidden();
        $this->actingAs($driver)->postJson('/api/rides/'.$ride->id.'/status', ['status' => 'started'])->assertUnprocessable();
        $task = DeliveryTask::factory()->create(['driver_id' => $driver->id, 'ride_id' => $ride->id, 'sequence' => 1, 'status' => 'assigned']);
        $this->postJson('/api/rides/'.$ride->id.'/status', ['status' => 'started'])->assertOk();
        $this->postJson('/api/rides/'.$ride->id.'/status', ['status' => 'completed'])->assertUnprocessable();
        foreach (['started', 'arrived', 'delivered'] as $status) {
            $this->postJson('/api/delivery-tasks/'.$task->id.'/status', ['status' => $status])->assertOk();
        }
        $this->postJson('/api/rides/'.$ride->id.'/status', ['status' => 'completed'])->assertOk();
        $this->assertNotNull($ride->refresh()->started_at);
        $this->assertNotNull($ride->completed_at);
    }

    public function test_saved_address_snapshot_default_and_deactivation_preserve_history(): void
    {
        $this->actingAs($this->account());
        $customerId = $this->postJson('/api/customers', ['name' => 'Nino', 'phone' => '123', 'active' => true])->assertCreated()->json('data.id');
        $first = $this->postJson("/api/customers/$customerId/addresses", ['label' => 'Home', 'address' => 'Original', 'is_default' => true])->assertCreated()->json('data.id');
        $this->postJson("/api/customers/$customerId/addresses", ['label' => 'Office', 'address' => 'Office address', 'is_default' => true])->assertCreated();
        $this->assertDatabaseHas('customer_addresses', ['id' => $first, 'is_default' => false]);
        $taskId = $this->postJson('/api/delivery-tasks', $this->payload(['customer_id' => $customerId, 'customer_address_id' => $first, 'address' => 'Delivery override']))->assertCreated()->json('data.id');
        $this->putJson("/api/customers/$customerId/addresses/$first", ['label' => 'Home', 'address' => 'New address', 'is_default' => false])->assertOk();
        $this->deleteJson("/api/customers/$customerId/addresses/$first")->assertNoContent();
        $this->deleteJson("/api/customers/$customerId")->assertNoContent();
        $this->getJson('/api/delivery-tasks/'.$taskId)->assertOk()->assertJsonPath('data.address', 'Delivery override');
    }

    public function test_inactive_accounts_are_rejected_and_admin_cannot_demote_self(): void
    {
        $inactive = $this->account();
        $inactive->update(['active' => false]);
        $this->actingAs($inactive)->getJson('/api/delivery-tasks')->assertForbidden();
        $admin = $this->account('superadministrator');
        $this->actingAs($admin)->putJson('/api/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'operator', 'active' => true])->assertUnprocessable();
    }

    public function test_directory_blank_active_filter_includes_active_and_inactive_records(): void
    {
        Customer::create(['name' => 'Active', 'phone' => '123', 'active' => true]);
        Customer::create(['name' => 'Inactive', 'phone' => '456', 'active' => false]);
        $this->actingAs($this->account())->getJson('/api/customers?active=')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/customers?active=0')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Inactive');
    }

    public function test_ineligible_stop_rolls_back_all_ride_membership_and_assignment(): void
    {
        $operator = $this->account();
        $driver = $this->account('driver');
        $ride = Ride::create(['driver_id' => $driver->id, 'date' => today()->toDateString(), 'created_by' => $operator->id]);
        $valid = DeliveryTask::factory()->create();
        $invalid = DeliveryTask::factory()->create(['delivery_date' => today()->addDay()->toDateString()]);
        $this->actingAs($operator)->postJson('/api/rides/'.$ride->id.'/tasks', ['task_ids' => [$valid->id, $invalid->id]])->assertUnprocessable();
        $this->assertNull($valid->refresh()->driver_id);
        $this->assertNull($valid->ride_id);
        $this->assertSame('pending', $valid->status->value);
        $this->assertSame(0, $valid->history()->count());
    }

    public function test_inactive_historical_items_can_be_retained_during_edit(): void
    {
        $item = Item::create(['name' => 'Historical box', 'active' => true]);
        $this->actingAs($this->account());
        $payload = $this->payload(['items' => [['item_id' => $item->id, 'quantity' => 2]]]);
        $id = $this->postJson('/api/delivery-tasks', $payload)->assertCreated()->json('data.id');
        $this->deleteJson('/api/items/'.$item->id)->assertNoContent();
        $this->putJson('/api/delivery-tasks/'.$id, $payload)->assertOk()->assertJsonPath('data.items.0.quantity', 2);
        $this->postJson('/api/delivery-tasks', $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.item_id');
    }

    public function test_operator_cannot_view_admin_account_endpoint_and_driver_detail_contains_all_today_tasks(): void
    {
        $admin = $this->account('superadministrator');
        $driver = $this->account('driver');
        DeliveryTask::factory()->count(31)->create(['driver_id' => $driver->id, 'status' => 'assigned']);
        $this->actingAs($this->account())->getJson('/api/users/'.$admin->id)->assertForbidden();
        $this->getJson('/api/drivers/'.$driver->id)->assertOk()->assertJsonCount(31, 'data.today_tasks')->assertJsonCount(30, 'data.deliveries');
    }

    public function test_dashboard_summarizes_todays_statuses_and_excludes_other_dates(): void
    {
        DeliveryTask::factory()->create(['status' => 'pending']);
        DeliveryTask::factory()->create(['status' => 'delivered']);
        DeliveryTask::factory()->create(['delivery_date' => today()->subDay()->toDateString()]);
        $this->actingAs($this->account())->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.pending', 1)->assertJsonPath('data.delivered', 1);
    }

    public function test_admin_seeder_is_idempotent_and_hashes_required_password(): void
    {
        $this->seed(AdministratorSeeder::class);
        $this->seed(AdministratorSeeder::class);
        $admin = User::where('email', 'admin@example.com')->sole();
        $this->assertSame('superadministrator', $admin->role->value);
        $this->assertTrue(Hash::check('password123', $admin->password));
    }

    public function test_mysql_rejects_zero_item_quantity_even_outside_request_validation(): void
    {
        $item = Item::create(['name' => 'Box']);
        $task = DeliveryTask::factory()->create();
        $this->expectException(QueryException::class);
        $task->items()->attach($item->id, ['quantity' => 0]);
    }
}
