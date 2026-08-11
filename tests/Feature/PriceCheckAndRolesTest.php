<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriceCheckAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true]);
    }

    // --- Kiosco de precios ---

    public function test_el_kiosco_es_publico(): void
    {
        $this->get('/precios')->assertOk()->assertSee('Consultá tu precio');
    }

    public function test_escanear_un_sku_muestra_el_precio(): void
    {
        Product::create(['name' => 'Coca 1.5L', 'price' => 1800, 'stock' => 10, 'sku' => '7790001']);

        Livewire::test('price-check.kiosk')
            ->set('code', '7790001')
            ->call('search')
            ->assertSet('notFound', false)
            ->assertSet('product.name', 'Coca 1.5L')
            ->assertSet('product.price', 1800.0)
            ->assertSet('code', ''); // se limpia para el próximo escaneo
    }

    public function test_codigo_inexistente_marca_no_encontrado(): void
    {
        Livewire::test('price-check.kiosk')
            ->set('code', 'NO-EXISTE')
            ->call('search')
            ->assertSet('notFound', true)
            ->assertSet('product', null);
    }

    // --- Permisos por rol ---

    public function test_permisos_del_vendedor(): void
    {
        foreach (['dashboard', 'quotes', 'invoices', 'clients', 'products', 'categories', 'reports', 'price-check'] as $m) {
            $this->assertTrue(Permissions::canAccess(Role::Vendedor, $m), "vendedor debería ver {$m}");
        }
        foreach (['cash-register', 'providers', 'purchases', 'users', 'company-settings'] as $m) {
            $this->assertFalse(Permissions::canAccess(Role::Vendedor, $m), "vendedor NO debería ver {$m}");
        }
    }

    public function test_permisos_del_cajero(): void
    {
        foreach (['dashboard', 'invoices', 'clients', 'cash-register', 'products', 'price-check'] as $m) {
            $this->assertTrue(Permissions::canAccess(Role::Cajero, $m), "cajero debería ver {$m}");
        }
        foreach (['providers', 'purchases', 'quotes', 'reports', 'users', 'company-settings'] as $m) {
            $this->assertFalse(Permissions::canAccess(Role::Cajero, $m), "cajero NO debería ver {$m}");
        }
    }

    public function test_cajero_ahora_puede_facturar_y_no_ve_proveedores(): void
    {
        $cajero = $this->user(Role::Cajero);

        $this->actingAs($cajero)->get(route('invoices.index'))->assertOk();
        $this->actingAs($cajero)->get(route('providers.index'))->assertForbidden();
    }

    public function test_vendedor_no_entra_a_caja(): void
    {
        $vendedor = $this->user(Role::Vendedor);

        $this->actingAs($vendedor)->get(route('cash-register.index'))->assertForbidden();
    }
}
