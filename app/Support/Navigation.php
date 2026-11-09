<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar by module; an item shows only when the user holds one of its permissions
 * and its route exists (S0 step 5, AGENTS.md section 9). Groups with no visible item
 * are hidden, so later stages only add rows here.
 */
final class Navigation
{
    /** @return list<array{key: string, items: list<array{label: string, route: string, params?: array}>}> */
    public static function forUser(User $user): array
    {
        $out = [];
        foreach (self::definition() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }
                $allowed = false;
                foreach ($item['any'] as $permission) {
                    if ($user->hasPermission($permission)) {
                        $allowed = true;
                        break;
                    }
                }
                if ($allowed) {
                    $items[] = ['label' => $item['label'], 'route' => $item['route']];
                }
            }
            if ($items !== []) {
                $out[] = ['key' => $group['key'], 'items' => $items];
            }
        }

        return $out;
    }

    /** @return list<array{key: string, items: list<array{label: string, route: string, any: list<string>}>}> */
    public static function definition(): array
    {
        return [
            ['key' => 'catalogue', 'items' => []],
            ['key' => 'inventory', 'items' => []],
            ['key' => 'sales', 'items' => []],
            ['key' => 'prescriptions', 'items' => []],
            ['key' => 'customers', 'items' => []],
            ['key' => 'finance', 'items' => []],
            ['key' => 'reports', 'items' => []],
            ['key' => 'staff', 'items' => [
                ['label' => 'nav.staff', 'route' => 'staff.index', 'any' => ['staff.manage', 'staff.view.own']],
            ]],
            ['key' => 'administration', 'items' => [
                ['label' => 'nav.approvals', 'route' => 'approvals.index', 'any' => ['approvals.request']],
                ['label' => 'nav.users', 'route' => 'users.index', 'any' => ['users.view']],
                ['label' => 'nav.audit', 'route' => 'audit.index', 'any' => ['audit.view', 'audit.view.technical']],
                ['label' => 'nav.settings', 'route' => 'settings.index', 'any' => ['settings.view']],
            ]],
        ];
    }
}
