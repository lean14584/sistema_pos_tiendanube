<?php

namespace App\Support;

use App\Enums\Role;

class Permissions
{
    /** @var array<string, string[]> */
    public const ROLE_PERMISSIONS = [
        'admin' => [
            'dashboard', 'quotes', 'invoices', 'clients', 'products', 'categories',
            'providers', 'purchases', 'cash-register', 'reports', 'users', 'messages', 'tasks',
            'company-settings', 'audit', 'libro-iva',
        ],
        'vendedor' => ['dashboard', 'quotes', 'invoices', 'clients', 'products', 'categories', 'reports', 'messages', 'tasks'],
        'cajero' => ['dashboard', 'clients', 'providers', 'cash-register', 'messages', 'tasks'],
    ];

    public static function canAccess(Role $role, string $module): bool
    {
        return in_array($module, self::ROLE_PERMISSIONS[$role->value] ?? [], true);
    }

    /** @return string[] */
    public static function modulesFor(Role $role): array
    {
        return self::ROLE_PERMISSIONS[$role->value] ?? [];
    }
}
