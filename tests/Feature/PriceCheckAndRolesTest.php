<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySettings;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
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

    public function test_escanear_el_ean13_de_la_etiqueta_como_upc_a_muestra_el_precio(): void
    {
        // Mismo bug que en Pos\Index::addByBarcode(): el sku ya es el EAN13
        // completo ("0000000000536"), y el lector lo transmite como su
        // equivalente UPC-A de 12 dígitos ("000000000536", sin el primer
        // '0'). Antes de Product::findByBarcode() esta pantalla no lo
        // reconocía aunque el mismo código sí funcionara en el POS.
        Product::create(['name' => 'Individuales x6 u', 'price' => 3200, 'stock' => 12, 'sku' => '0000000000536']);

        Livewire::test('price-check.kiosk')
            ->set('code', '000000000536')
            ->call('search')
            ->assertSet('notFound', false)
            ->assertSet('product.name', 'Individuales x6 u');
    }

    public function test_codigo_inexistente_marca_no_encontrado(): void
    {
        Livewire::test('price-check.kiosk')
            ->set('code', 'NO-EXISTE')
            ->call('search')
            ->assertSet('notFound', true)
            ->assertSet('product', null)
            ->assertDispatched('result-shown'); // dispara el auto-reset de 10s
    }

    public function test_reset_vuelve_al_estado_inicial(): void
    {
        Product::create(['name' => 'Coca 1.5L', 'price' => 1800, 'stock' => 10, 'sku' => '7790001']);

        Livewire::test('price-check.kiosk')
            ->set('code', '7790001')
            ->call('search')
            ->assertSet('product.name', 'Coca 1.5L')
            ->call('resetView')
            ->assertSet('product', null)
            ->assertSet('notFound', false)
            ->assertSet('code', '');
    }

    /**
     * MEJORA: el kiosco mostraba products.stock (el agregado de TODAS las
     * sucursales), no el stock real de la sucursal donde está esa pantalla
     * física — /precios/{sucursal?} (route param opcional) permite pinnear
     * cada pantalla a su local.
     */
    public function test_kiosco_pinneado_a_una_sucursal_muestra_su_propio_stock(): void
    {
        $centro = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa', 'punto_venta' => 92]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 93]);

        $product = Product::create(['name' => 'Coca 1.5L', 'price' => 1800, 'stock' => 15, 'sku' => '7790001']);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $centro->id, 'stock' => 10]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 5]);

        Livewire::test('price-check.kiosk', ['sucursal' => $centro])
            ->set('code', '7790001')
            ->call('search')
            ->assertSet('product.stock', 10);

        Livewire::test('price-check.kiosk', ['sucursal' => $norte])
            ->set('code', '7790001')
            ->call('search')
            ->assertSet('product.stock', 5);
    }

    public function test_muestra_el_logo_de_la_empresa_si_hay_uno(): void
    {
        CompanySettings::current()->update(['logo_path' => 'company-logos/mi-logo.png']);

        $url = Livewire::test('price-check.kiosk')->viewData('logoUrl');

        $this->assertNotNull($url);
        $this->assertStringContainsString('company-logos/mi-logo.png', $url);
    }

    // --- Permisos por rol ---

    public function test_permisos_del_vendedor(): void
    {
        foreach (['dashboard', 'quotes', 'invoices', 'clients', 'products', 'products-manage', 'categories', 'reports', 'price-check'] as $m) {
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
        foreach (['providers', 'purchases', 'quotes', 'reports', 'users', 'company-settings', 'products-manage'] as $m) {
            $this->assertFalse(Permissions::canAccess(Role::Cajero, $m), "cajero NO debería ver {$m}");
        }
    }

    public function test_cajero_ahora_puede_facturar_y_no_ve_proveedores(): void
    {
        $cajero = $this->user(Role::Cajero);

        $this->actingAs($cajero)->get(route('invoices.index'))->assertOk();
        $this->actingAs($cajero)->get(route('providers.index'))->assertForbidden();
    }

    public function test_cajero_solo_consulta_productos_no_los_gestiona(): void
    {
        $cajero = $this->user(Role::Cajero);
        $product = Product::create(['name' => 'Coca 1.5L', 'price' => 1800, 'stock' => 10, 'sku' => '7790001']);

        $this->actingAs($cajero)->get(route('products.index'))->assertOk();
        $this->actingAs($cajero)->get(route('products.create'))->assertForbidden();
        $this->actingAs($cajero)->get(route('products.edit', $product))->assertForbidden();
        $this->actingAs($cajero)->get(route('products.historial', $product))->assertForbidden();
        $this->actingAs($cajero)->get(route('products.labels'))->assertForbidden();
        $this->actingAs($cajero)->get(route('products.export'))->assertForbidden();

        Livewire::actingAs($cajero)
            ->test('products.index')
            ->call('delete', $product->id)
            ->assertForbidden();

        $this->assertNotNull($product->fresh());
    }

    public function test_vendedor_no_entra_a_caja(): void
    {
        $vendedor = $this->user(Role::Vendedor);

        $this->actingAs($vendedor)->get(route('cash-register.index'))->assertForbidden();
    }
}
