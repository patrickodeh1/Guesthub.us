<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class RouteControllersResolveTest extends TestCase
{
    public function test_every_controller_route_resolves_to_an_existing_callable_method(): void
    {
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (! is_string($action) || ! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action, 2);

            $this->assertTrue(
                class_exists($class),
                sprintf('Route [%s] references missing controller class [%s].', $route->uri(), $class),
            );

            $controller = app($class);

            $this->assertTrue(
                is_callable([$controller, $method]),
                sprintf('Route [%s] references non-callable action [%s@%s].', $route->uri(), $class, $method),
            );

            $reflection = new ReflectionMethod($controller, $method);
            $this->assertTrue(
                $reflection->isPublic(),
                sprintf('Route [%s] references non-public action [%s@%s].', $route->uri(), $class, $method),
            );
        }
    }
}
