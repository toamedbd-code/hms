<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Tests\TestCase;

class SidebarCacheMiddlewareTest extends TestCase
{
    public function test_share_does_not_throw_when_cache_store_does_not_support_tags(): void
    {
        $middleware = new HandleInertiaRequests(app());
        $request = Request::create('/admin', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => null);

        $props = $middleware->share($request);

        $this->assertIsArray($props);
        $this->assertArrayHasKey('auth', $props);
    }

    public function test_inertia_navigation_omits_the_full_ziggy_route_map(): void
    {
        $middleware = new HandleInertiaRequests(app());
        $request = Request::create('/admin', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => null);

        $props = $middleware->share($request);

        $this->assertIsCallable($props['ziggy']);
        $this->assertNull(($props['ziggy'])());
    }
}
