<?php

// Feature tests for the public-endpoint rate limiters (search / weather-api /
// contact). Verifies the named limiters are registered, the middleware is
// attached to each route, and the limit is actually enforced (429).

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    public function test_named_limiters_are_registered(): void
    {
        $this->assertNotNull(RateLimiter::limiter('search'));
        $this->assertNotNull(RateLimiter::limiter('weather-api'));
        $this->assertNotNull(RateLimiter::limiter('contact'));
    }

    /**
     * @dataProvider throttledRoutes
     */
    public function test_route_has_throttle_middleware(string $routeName, string $expectedMiddleware): void
    {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Route {$routeName} should exist");
        $this->assertContains($expectedMiddleware, $route->gatherMiddleware());
    }

    public static function throttledRoutes(): array
    {
        return [
            'search' => ['api.search', 'throttle:search'],
            'live-weather' => ['api.live-weather', 'throttle:weather-api'],
            'weather-data index' => ['api.weather-data.index', 'throttle:weather-api'],
            'weather-data show' => ['api.weather-data.show', 'throttle:weather-api'],
            'contact' => ['contact.store', 'throttle:contact'],
        ];
    }

    public function test_contact_endpoint_returns_429_past_the_limit(): void
    {
        // Empty payloads fail validation (302 back) but still pass through the
        // throttle middleware, so the 6th request within the minute is blocked.
        for ($requestNumber = 1; $requestNumber <= 5; $requestNumber++) {
            $this->post('/contact', [])->assertStatus(302);
        }

        $this->post('/contact', [])->assertStatus(429);
    }
}
