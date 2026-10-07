<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaburtaloSeeder extends Seeder
{
    public function run(): void
    {
        // Photon/OpenStreetMap address coordinates; all results identify Saburtalo district.
        // Names, contact details, apartments and instructions are fictional demo data.
        $clients = [
            ['ნინო აბაშიძე', 'პეკინის გამზირი 8', 41.7202467, 44.775598],
            ['ლუკა ჩიქოვანი', 'პეკინის გამზირი 16', 41.7227338, 44.7727801],
            ['თამარ ჯაფარიძე', 'პეკინის გამზირი 24', 41.724086, 44.7713278],
            ['დავით მელიქიშვილი', 'პეკინის გამზირი 30', 41.7254283, 44.769816],
            ['მაია წიკლაური', 'გივი კარტოზიას ქუჩა 10', 41.7235303, 44.7495308],
            ['საბა კვირიკაშვილი', 'ალექსანდრე ყაზბეგის გამზირი 16', 41.7234779, 44.7572377],
            ['ელენე გვაზავა', 'ალექსანდრე ყაზბეგის გამზირი 24', 41.7240301, 44.748547],
            ['ირაკლი წერეთელი', 'ვაჟა-ფშაველას გამზირი 12', 41.7268875, 44.7620483],
            ['ანა კაპანაძე', 'ვაჟა-ფშაველას გამზირი 21', 41.7273314, 44.7621111],
            ['გიორგი ლომიძე', 'ვაჟა-ფშაველას გამზირი 36', 41.7255231, 44.7492088],
        ];
        DB::transaction(function () use ($clients) {
            foreach ($clients as $index => [$name, $address, $latitude, $longitude]) {
                $number = $index + 1;
                $client = Customer::firstOrCreate(
                    ['email' => sprintf('saburtalo.client%02d@example.com', $number)],
                    ['name' => $name, 'phone' => sprintf('+995 555 090 %03d', 100 + $number), 'active' => true,
                        'notes' => 'სატესტო კლიენტი — საბურთალო. საკონტაქტო და ბინის მონაცემები გამოგონილია.'],
                );
                // Reruns preserve any address changes made through the application.
                if ($client->wasRecentlyCreated || ! $client->addresses()->exists()) {
                    $client->addresses()->create([
                        'label' => 'ძირითადი მისამართი', 'address' => $address, 'city' => 'თბილისი',
                        'latitude' => $latitude, 'longitude' => $longitude,
                        'entrance' => (string) ($index % 3 + 1), 'floor' => (string) ($index % 7 + 1),
                        'apartment' => (string) (10 + $number), 'is_default' => true,
                        'instructions' => 'საბურთალო. მისვლისას დარეკეთ. სატესტო მისამართის დეტალები.',
                    ]);
                }
            }
            foreach (['გიორგი ბერიძე', 'ნიკა მაისურაძე', 'ანა გელაშვილი', 'ლევან ჯაფარიძე', 'მარიამ ქავთარაძე'] as $index => $name) {
                User::firstOrCreate(
                    ['email' => 'saburtalo.driver'.($index + 1).'@example.com'],
                    ['name' => $name, 'phone' => sprintf('+995 555 090 %03d', 201 + $index),
                        'role' => 'driver', 'active' => true, 'password' => 'SaburtaloDemo2026!'],
                );
            }
        });
        $this->command?->info('Saburtalo sample records ready: 10 clients with addresses and 5 drivers.');
    }
}
