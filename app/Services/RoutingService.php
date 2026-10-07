<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoutingService
{
    public function optimize(array $points): array
    {
        $matrix = $this->request('table', $points, ['annotations' => 'duration'])['durations'] ?? null;
        $count = count($points);
        if (! is_array($matrix) || count($matrix) !== $count) {
            $this->unavailable();
        }
        foreach ($matrix as $row) {
            if (! is_array($row) || count($row) !== $count) {
                $this->unavailable();
            }
            foreach ($row as $duration) {
                if ($duration !== null && (! is_numeric($duration) || ! is_finite((float) $duration) || $duration < 0)) {
                    $this->unavailable();
                }
            }
        }
        // Start is fixed at storage. Build an open route; never add a return stop.
        $order = [0];
        $remaining = range(1, $count - 1);
        while ($remaining) {
            $from = end($order);
            usort($remaining, fn ($a, $b) => ($matrix[$from][$a] ?? INF) <=> ($matrix[$from][$b] ?? INF) ?: $a <=> $b);
            $next = array_shift($remaining);
            if ($matrix[$from][$next] === null) {
                $this->unreachable();
            }
            $order[] = $next;
        }
        // Improve the nearest-neighbour route using the actual directed road matrix.
        $cost = fn ($route) => array_sum(array_map(fn ($i) => $matrix[$route[$i - 1]][$route[$i]] ?? INF, range(1, count($route) - 1)));
        $best = $cost($order);
        for ($pass = 0; $pass < 5; $pass++) {
            $improved = false;
            for ($i = 1; $i < $count - 1; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $candidate = array_merge(array_slice($order, 0, $i), array_reverse(array_slice($order, $i, $j - $i + 1)), array_slice($order, $j + 1));
                    $value = $cost($candidate);
                    if ($value < $best - 0.01) {
                        $order = $candidate;
                        $best = $value;
                        $improved = true;
                    }
                }
            }
            if (! $improved) {
                break;
            }
        }

        return array_slice($order, 1);
    }

    public function directions(array $points): array
    {
        $route = $this->request('route', $points, ['steps' => 'true', 'geometries' => 'geojson', 'overview' => 'full'])['routes'][0] ?? null;
        if (! is_array($route) || ! is_numeric($route['distance'] ?? null) || ! is_numeric($route['duration'] ?? null)
            || ($route['geometry']['type'] ?? '') !== 'LineString' || ! is_array($route['geometry']['coordinates'] ?? null)
            || count($route['geometry']['coordinates']) < 2 || ! is_array($route['legs'] ?? null) || count($route['legs']) !== count($points) - 1) {
            $this->unavailable();
        }

        return [
            'geometry' => $route['geometry'],
            'distance' => round($route['distance']),
            'duration' => round($route['duration']),
            'legs' => array_map(fn ($leg) => [
                'distance' => round($leg['distance']), 'duration' => round($leg['duration']),
                'steps' => array_map(fn ($step) => [
                    'name' => $step['name'] ?? '', 'distance' => round($step['distance'] ?? 0),
                    'type' => $step['maneuver']['type'] ?? 'continue', 'modifier' => $step['maneuver']['modifier'] ?? null,
                    'exit' => $step['maneuver']['exit'] ?? null,
                ], $leg['steps'] ?? []),
            ], $route['legs']),
        ];
    }

    private function request(string $service, array $points, array $parameters): array
    {
        $coordinates = implode(';', array_map(fn ($point) => $point['longitude'].','.$point['latitude'], $points));
        $url = rtrim(config('routing.url'), '/').'/'.$service.'/v1/'.config('routing.profile').'/'.$coordinates;
        $key = 'routing:'.hash('sha256', $url.json_encode($parameters));

        return Cache::remember($key, now()->addHour(), function () use ($url, $parameters, $service, $points) {
            try {
                $response = Http::acceptJson()->withUserAgent(config('app.name').'/1.0 (+'.config('app.url').')')->connectTimeout(3)->timeout(20)->get($url, $parameters);
                if (in_array($response->json('code'), ['NoRoute', 'NoSegment', 'NoTable'])) {
                    $this->unreachable();
                }
                $response->throw();
            } catch (ConnectionException|RequestException $exception) {
                throw new HttpException(503, __('Road directions are temporarily unavailable. Your stops are saved; try recalculating the route.'), $exception);
            }
            $data = $response->json();
            if (! is_array($data) || ($data['code'] ?? '') !== 'Ok') {
                if (in_array($data['code'] ?? '', ['NoRoute', 'NoSegment', 'NoTable'])) {
                    $this->unreachable();
                }
                $this->unavailable();
            }

            $this->validatePayload($service, $data, count($points));

            return $data;
        });
    }

    private function validatePayload(string $service, array $data, int $count): void
    {
        $number = fn ($value) => is_numeric($value) && is_finite((float) $value) && $value >= 0;
        if ($service === 'table') {
            if (! is_array($data['durations'] ?? null) || count($data['durations']) !== $count) {
                $this->unavailable();
            }
            foreach ($data['durations'] as $row) {
                if (! is_array($row) || count($row) !== $count) {
                    $this->unavailable();
                }
                foreach ($row as $value) {
                    if ($value !== null && ! $number($value)) {
                        $this->unavailable();
                    }
                }
            }

            return;
        }
        $route = $data['routes'][0] ?? null;
        if (! is_array($route) || ! $number($route['distance'] ?? null) || ! $number($route['duration'] ?? null)
            || ($route['geometry']['type'] ?? '') !== 'LineString' || ! is_array($route['geometry']['coordinates'] ?? null)
            || count($route['geometry']['coordinates']) < 2 || ! is_array($route['legs'] ?? null) || count($route['legs']) !== $count - 1) {
            $this->unavailable();
        }
        foreach ($route['geometry']['coordinates'] as $point) {
            if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1]) || ! is_finite((float) $point[0]) || ! is_finite((float) $point[1]) || abs($point[0]) > 180 || abs($point[1]) > 90) {
                $this->unavailable();
            }
        }
        foreach ($route['legs'] as $leg) {
            if (! is_array($leg) || ! $number($leg['distance'] ?? null) || ! $number($leg['duration'] ?? null) || ! is_array($leg['steps'] ?? null)) {
                $this->unavailable();
            }
            foreach ($leg['steps'] as $step) {
                if (! is_array($step) || ! $number($step['distance'] ?? null) || ! is_string($step['name'] ?? '') || ! is_array($step['maneuver'] ?? null) || ! is_string($step['maneuver']['type'] ?? null)) {
                    $this->unavailable();
                }
            }
        }
    }

    private function unreachable(): never
    {
        throw ValidationException::withMessages(['route' => __('A driving route could not connect these pins. Check the storage and order locations.')]);
    }

    private function unavailable(): never
    {
        throw new HttpException(503, __('Road directions are temporarily unavailable. Your stops are saved; try recalculating the route.'));
    }
}
