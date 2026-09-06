<?php

namespace App\Support;

use App\Enums\Role;

class Permissions
{
    /** @var array<string, string[]> */
    public const ROLE_PERMISSIONS = [
        'admin' => [
            'dashboard', 'pos', 'quotes', 'invoices', 'clients', 'cobranzas', 'products', 'categories', 'price-lists', 'promotions',
            'stock-adjustments', 'stock-transfers', 'product-batches', 'providers', 'purchases', 'cash-register', 'vencimientos', 'reports', 'users', 'messages', 'tasks',
            'company-settings', 'sucursales', 'audit', 'libro-iva', 'price-check', 'backups', 'health',
        ],
        // Encargado: manda en SU sucursal (usuarios de esa sucursal, compras,
        // caja, auditoría filtrada) pero no es global como Admin — no ve
        // 'company-settings', 'sucursales' (ABM de sucursales), 'libro-iva',
        // 'backups' ni 'health'. El scoping por sucursal de 'users' y 'audit'
        // vive en los componentes Livewire, no acá (ver Users\Index/Create/Edit
        // y Audit\Index).
        'encargado' => [
            'dashboard', 'pos', 'quotes', 'invoices', 'clients', 'cobranzas', 'vencimientos', 'products', 'categories', 'price-lists', 'promotions',
            'stock-adjustments', 'stock-transfers', 'product-batches', 'providers', 'purchases', 'cash-register', 'users', 'audit', 'reports', 'messages', 'tasks', 'price-check',
        ],
        'vendedor' => ['dashboard', 'pos', 'quotes', 'invoices', 'clients', 'cobranzas', 'vencimientos', 'products', 'categories', 'price-lists', 'promotions', 'stock-adjustments', 'stock-transfers', 'product-batches', 'reports', 'messages', 'tasks', 'price-check'],
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
