<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AddressSearchService
{
    public function search(string $query, ?string $city): array
    {
        $query = trim($query);
        if ($city && mb_stripos($query, trim($city)) === false) {
            $query .= ', '.trim($city);
        }
        $parameters = [
            'q' => $query,
            'limit' => 6,
            'lang' => 'en',
            'lat' => config('geocoding.latitude'),
            'lon' => config('geocoding.longitude'),
        ];
        $url = config('geocoding.url');
        $key = 'address-search:'.hash('sha256', $url.json_encode($parameters));

        return Cache::remember($key, now()->addMinutes(15), function () use ($url, $parameters) {
            try {
                $response = Http::acceptJson()
                    ->withUserAgent(config('app.name').'/1.0 (+'.config('app.url').')')
                    ->connectTimeout(3)->timeout(8)
                    ->get($url, $parameters)->throw();
            } catch (ConnectionException|RequestException $exception) {
                throw new HttpException(503, __('Address suggestions are temporarily unavailable. Enter the address and place the pin manually.'), $exception);
            }
            $features = $response->json('features');
            if (! is_array($features)) {
                throw new HttpException(503, __('Address suggestions are temporarily unavailable. Enter the address and place the pin manually.'));
            }

            return collect($features)
                ->map(fn ($feature) => is_array($feature) ? $this->suggestion($feature) : null)
                ->filter()->unique('id')->take(6)->values()->all();
        });
    }

    private function suggestion(array $feature): ?array
    {
        $point = $feature['geometry']['coordinates'] ?? [];
        if (($feature['geometry']['type'] ?? '') !== 'Point' || ! is_array($point) || count($point) < 2
            || ! is_numeric($point[0]) || ! is_numeric($point[1])
            || abs((float) $point[0]) > 180 || abs((float) $point[1]) > 90) {
            return null;
        }
        $properties = $feature['properties'] ?? [];
        if (! is_array($properties)) {
            return null;
        }
        $name = $properties['name'] ?? '';
        $street = $properties['street'] ?? '';
        $streetAddress = trim(($properties['housenumber'] ?? '').' '.$street);
        $address = implode(', ', array_unique(array_filter([
            $name !== $street ? $name : '', $streetAddress,
        ])));
        $city = $properties['city'] ?? (($properties['type'] ?? '') === 'city' ? $name : null);
        $address = $address ?: ($city ?: $name);
        if (! $address) {
            return null;
        }
        $label = implode(', ', array_unique(array_filter([
            $address, $properties['district'] ?? null, $city, $properties['country'] ?? null,
        ])));

        return [
            'id' => hash('sha256', $label.json_encode($point)),
            'label' => $label,
            'address' => $address,
            'city' => $city,
            'latitude' => round((float) $point[1], 7),
            'longitude' => round((float) $point[0], 7),
        ];
    }
}
