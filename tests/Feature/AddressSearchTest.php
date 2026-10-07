<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressSearchTest extends TestCase
{
    use RefreshDatabase;

    private function dispatcher(): User
    {
        return User::factory()->create(['role' => 'operator', 'active' => true]);
    }

    private function feature(array $properties = [], array $point = [44.8271234, 41.7151234]): array
    {
        return ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => $point], 'properties' => array_replace(['street' => 'Rustaveli Avenue', 'housenumber' => '12', 'city' => 'Tbilisi', 'country' => 'Georgia'], $properties)];
    }

    public function test_search_normalizes_geojson_and_biases_results_to_configured_city(): void
    {
        Http::fake(['*' => Http::response(['features' => [$this->feature()]], 200)]);
        $this->actingAs($this->dispatcher())->getJson('/api/addresses/search?q=Rustaveli&city=Tbilisi')
            ->assertOk()->assertJsonPath('data.0.address', '12 Rustaveli Avenue')
            ->assertJsonPath('data.0.label', '12 Rustaveli Avenue, Tbilisi, Georgia')
            ->assertJsonPath('data.0.city', 'Tbilisi')
            ->assertJsonPath('data.0.latitude', 41.7151234)->assertJsonPath('data.0.longitude', 44.8271234);
        Http::assertSent(fn ($request) => $request['q'] === 'Rustaveli, Tbilisi' && $request['limit'] === 6 && $request['lat'] === config('geocoding.latitude') && $request['lon'] === config('geocoding.longitude'));
    }

    public function test_repeated_queries_are_cached_and_city_is_not_duplicated(): void
    {
        Http::fake(['*' => Http::response(['features' => [$this->feature()]], 200)]);
        $this->actingAs($this->dispatcher());
        $this->getJson('/api/addresses/search?q=Rustaveli%20Tbilisi&city=Tbilisi')->assertOk();
        $this->getJson('/api/addresses/search?q=Rustaveli%20Tbilisi&city=Tbilisi')->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['q'] === 'Rustaveli Tbilisi');
    }

    public function test_search_requires_an_active_dispatcher_and_valid_query(): void
    {
        Http::fake();
        $this->getJson('/api/addresses/search?q=Rustaveli')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'driver', 'active' => true]))->getJson('/api/addresses/search?q=Rustaveli')->assertForbidden();
        $this->actingAs($this->dispatcher())->getJson('/api/addresses/search?q=ab')->assertUnprocessable()->assertJsonValidationErrors('q');
        Http::assertNothingSent();
    }

    public function test_provider_failures_are_reported_and_not_cached(): void
    {
        Http::fakeSequence()->pushStatus(503)->push(['features' => [$this->feature()]], 200);
        $this->actingAs($this->dispatcher())->getJson('/api/addresses/search?q=Rustaveli')->assertStatus(503)->assertJsonPath('message', 'Address suggestions are temporarily unavailable. Enter the address and place the pin manually.');
        $this->getJson('/api/addresses/search?q=Rustaveli')->assertOk()->assertJsonCount(1, 'data');
        Http::assertSentCount(2);
    }

    public function test_connection_failure_has_a_useful_fallback_message(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->actingAs($this->dispatcher())->getJson('/api/addresses/search?q=Rustaveli')->assertStatus(503);
    }

    public function test_malformed_provider_response_is_not_an_empty_success(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => true], 200)]);
        $this->actingAs($this->dispatcher())->getJson('/api/addresses/search?q=Rustaveli')->assertStatus(503);
    }

    public function test_duplicates_and_invalid_coordinates_are_excluded_but_zero_coordinates_are_valid(): void
    {
        Http::fake(['*' => Http::response(['features' => [$this->feature(), $this->feature(), $this->feature([], [200, 41]), $this->feature([], [44, 95]), $this->feature(['street' => 'Equator'], [0, 0])]], 200)]);
        $this->actingAs($this->dispatcher())->getJson('/api/addresses/search?q=Rustaveli')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.latitude', 0)->assertJsonPath('data.1.longitude', 0);
    }

    public function test_search_endpoint_is_rate_limited_even_for_cached_queries(): void
    {
        Http::fake(['*' => Http::response(['features' => []], 200)]);
        $this->actingAs($this->dispatcher());
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/addresses/search?q=Rustaveli')->assertOk();
        }
        $this->getJson('/api/addresses/search?q=Rustaveli')->assertTooManyRequests();
        Http::assertSentCount(1);
    }
}
