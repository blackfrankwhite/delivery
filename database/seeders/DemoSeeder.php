<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Ride;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\RideService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdministratorSeeder::class);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        User::firstOrCreate(['email' => 'operator@example.com'], ['name' => 'Mariam Kapanadze', 'phone' => '+995 555 010 101', 'role' => 'operator', 'password' => 'password123', 'active' => true]);
        $drivers = collect(['Giorgi Beridze', 'Nika Maisuradze', 'Ana Gelashvili'])->map(fn ($n, $i) => User::firstOrCreate(['email' => 'driver'.($i + 1).'@example.com'], ['name' => $n, 'phone' => '+995 555 020 10'.($i + 1), 'role' => 'driver', 'password' => 'password123', 'active' => true]));
        $items = collect(['Package', 'Water bottle', 'Food box', 'Document'])->map(fn ($n) => Item::firstOrCreate(['name' => $n], ['active' => true]));
        $names = ['Nino Abashidze', 'Luka Chikovani', 'Tamar Japaridze', 'David Melikishvili', 'Maya Tsiklauri', 'Saba Kvirikashvili', 'Elene Gvazava', 'Irakli Tsereteli'];
        $addresses = ['12 Rustaveli Avenue', '24 Chavchavadze Avenue', '8 Pekini Avenue', '15 Aghmashenebeli Avenue', '3 Abashidze Street', '42 Kazbegi Avenue', '17 Tsereteli Avenue', '6 Marjanishvili Street'];
        $service = app(DeliveryService::class);
        $rides = app(RideService::class);
        foreach ($names as $i => $name) {
            $customer = Customer::firstOrCreate(['name' => $name], ['phone' => '+995 555 030 10'.$i, 'active' => true]);
            $address = $customer->addresses()->firstOrCreate(['label' => 'Home'], ['address' => $addresses[$i], 'city' => 'Tbilisi', 'is_default' => true, 'instructions' => 'Call on arrival.']);
            if ($customer->deliveries()->whereDate('delivery_date', today())->exists()) {
                continue;
            }
            $driver = $i < 6 ? $drivers[$i % 3] : null;
            $task = $service->create(['customer_id' => $customer->id, 'customer_address_id' => $address->id, 'recipient_name' => $name, 'recipient_phone' => $customer->phone, 'address' => $address->address, 'city' => 'Tbilisi', 'delivery_date' => today()->toDateString(), 'time_from' => sprintf('%02d:00', 10 + $i), 'time_to' => sprintf('%02d:00', 11 + $i), 'priority' => $i === 3 ? 'high' : 'normal', 'notes' => 'Call on arrival.', 'driver_id' => $driver?->id, 'items' => [['item_id' => $items[$i % 4]->id, 'quantity' => $i % 3 + 1]]], $admin);
            if ($driver) {
                $ride = Ride::firstOrCreate(['driver_id' => $driver->id, 'date' => today()->toDateString(), 'status' => 'planned'], ['created_by' => $admin->id]);
                $rides->addTasks($ride, [$task->id], $admin);
                if ($i < 3) {
                    $service->transition($task, DeliveryStatus::Started, $driver);
                    if ($i < 2) {
                        $service->transition($task, DeliveryStatus::Arrived, $driver);
                    }
                    if ($i === 0) {
                        $service->transition($task, DeliveryStatus::Delivered, $driver);
                    }
                }
            }
        }
    }
}
