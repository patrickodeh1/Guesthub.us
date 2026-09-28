<?php

namespace App\Support;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    public function forUser(User $user): array
    {
        return collect(config('navigation.sections', []))
            ->map(function (array $section) use ($user) {
                $items = collect($section['items'] ?? [])
                    ->map(fn (array $item) => $this->resolveItem($item, $user))
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'label' => $section['label'],
                    'items' => $items,
                ];
            })
            ->all();
    }

    private function resolveItem(array $item, User $user): ?array
    {
        $itemRoles = $item['roles'] ?? [];
        $excludedRoles = $item['exclude_roles'] ?? [];

        if ($itemRoles !== [] && ! $user->hasAnyRole($itemRoles)) {
            return null;
        }

        if ($excludedRoles !== [] && $user->hasAnyRole($excludedRoles)) {
            return null;
        }

        $route = $this->firstAvailableRoute($item['routes'] ?? [], $user);

        if (! $route && empty($item['collapsible'])) {
            return null;
        }

        $children = collect($item['children'] ?? [])
            ->map(fn (array $child) => $this->resolveItem($child, $user))
            ->filter()
            ->values()
            ->all();

        if (isset($item['property_children'])) {
            $propertyConfig = $item['property_children'];
            $propertyRoles = $propertyConfig['roles'] ?? [];
            $propertyRoute = $this->firstAvailableRoute([
                ['name' => $propertyConfig['route'], 'roles' => $propertyRoles],
            ], $user);

            if ($propertyRoute) {
                $children = array_merge($children, Property::query()
                    ->visibleTo($user)
                    ->orderBy('name')
                    ->get()
                    ->map(function (Property $property) use ($propertyConfig) {
                        $actions = collect($propertyConfig['actions'] ?? [])
                            ->filter(fn (array $action) => Route::has($action['route']))
                            ->map(fn (array $action) => [
                                'label' => $action['label'],
                                'href' => route($action['route'], $property),
                                'active' => $this->isPropertyActive($action['active'] ?? [], $property),
                                'children' => [],
                                'collapsible' => false,
                                'icon' => null,
                                'tour' => null,
                            ])
                            ->values()
                            ->all();

                        return [
                            'label' => $property->name,
                            'href' => null,
                            'active' => collect($actions)->contains(fn (array $action) => $action['active']),
                            'children' => $actions,
                            'collapsible' => true,
                            'icon' => null,
                            'tour' => null,
                            'submenu_id' => 'nav-property-'.$property->id,
                        ];
                    })
                    ->all());
            }
        }

        if (! $route && $children === []) {
            return null;
        }

        $active = $this->isActive($item['active'] ?? [])
            || collect($children)->contains(fn (array $child) => $child['active']);

        return [
            'label' => $item['label'],
            'icon' => $item['icon'] ?? 'info',
            'tour' => $item['tour'] ?? null,
            'href' => $route['href'] ?? null,
            'active' => $active,
            'children' => $children,
            'collapsible' => ! empty($item['collapsible']),
            'submenu_id' => $item['submenu_id'] ?? 'nav-'.\Illuminate\Support\Str::slug($item['label']).'-submenu',
        ];
    }

    private function firstAvailableRoute(array $routes, User $user): ?array
    {
        foreach ($routes as $candidate) {
            $roles = $candidate['roles'] ?? [];

            if ($roles !== [] && ! $user->hasAnyRole($roles)) {
                continue;
            }

            if (! Route::has($candidate['name'])) {
                continue;
            }

            $route = Route::getRoutes()->getByName($candidate['name']);
            $hasParameters = $route && $route->parameterNames() !== [];

            return [
                'name' => $candidate['name'],
                'href' => $hasParameters ? null : route($candidate['name']),
            ];
        }

        return null;
    }

    private function isActive(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isPropertyActive(array $patterns, Property $property): bool
    {
        $currentProperty = request()->route('property');
        $currentPropertyId = $currentProperty instanceof Property ? $currentProperty->id : $currentProperty;

        return $this->isActive($patterns)
            && (int) $currentPropertyId === $property->id;
    }
}
