<?php

namespace App\Support;

use App\Enums\Role;

class Permissions
{
    /** @var array<string, string[]> */
    public const ROLE_PERMISSIONS = [
        'admin' => [
            'dashboard', 'pos', 'quotes', 'invoices', 'clients', 'cobranzas', 'products', 'categories', 'price-lists', 'promotions',
            'providers', 'purchases', 'cash-register', 'reports', 'users', 'messages', 'tasks',
            'company-settings', 'audit', 'libro-iva', 'price-check', 'backups', 'health',
        ],
        'vendedor' => ['dashboard', 'pos', 'quotes', 'invoices', 'clients', 'cobranzas', 'products', 'categories', 'price-lists', 'promotions', 'reports', 'messages', 'tasks', 'price-check'],
        'cajero' => ['dashboard', 'pos', 'invoices', 'clients', 'cobranzas', 'cash-register', 'products', 'messages', 'tasks', 'price-check'],
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
