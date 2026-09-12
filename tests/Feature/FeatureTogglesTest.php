<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
use App\Livewire\Dashboard;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Models\CompanySettings;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * config/features.php apaga rutas ENTERAS por instalación (ej. deco-hogar
 * sin multisucursal). Esas rutas se registran una sola vez al bootear la
 * app, así que estos tests no pueden simular "la ruta no existe" cambiando
 * el config en caliente a mitad de la suite. Lo que sí es dinámico —y lo
 * que se prueba acá— es la parte que lee el config en cada request: que el
 * sidebar no reviente si algún día una ruta de estas no está registrada
 * (usa $safeRoute, no route() a secas), y que la vista de Facturas oculte
 * el botón de alta manual cuando el flag está apagado.
 */
class FeatureTogglesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_todas_las_rutas_de_modulos_opcionales_existen_con_la_config_por_defecto(): void
    {
        // Sanity check: con el config default (todo en true, como pos-tiendanube
        // hoy), ninguna de las rutas que deco-hogar va a apagar debería faltar.
        $this->assertTrue(Route::has('sucursales.index'));
        $this->assertTrue(Route::has('tiendanube.index'));
        $this->assertTrue(Route::has('stock-transfers.index'));
        $this->assertTrue(Route::has('product-batches.index'));
        $this->assertTrue(Route::has('price-lists.index'));
        $this->assertTrue(Route::has('vencimientos.index'));
        $this->assertTrue(Route::has('invoices.create'));
        $this->assertTrue(Route::has('historical-sales.index'));
    }

    public function test_sidebar_no_revienta_si_una_ruta_de_modulo_opcional_no_existe(): void
    {
        // Simula el estado real de una instalación con el módulo apagado: acá
        // la ruta ya está registrada (ver comentario de la clase), pero
        // config() sí es dinámico, así que esto ejercita la rama del
        // $featureGate que oculta el ítem — la pieza que si faltara
        // ($safeRoute) haría explotar el layout entero con
        // RouteNotFoundException apenas alguien abre cualquier pantalla.
        config([
            'features.multisucursal' => false,
            'features.tiendanube' => false,
            'features.stock_transfers' => false,
            'features.product_batches' => false,
            'features.price_lists' => false,
            'features.vencimientos_finanzas' => false,
            'features.historical_sales' => false,
        ]);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Sucursales');
        $response->assertDontSee('Tiendanube');
        $response->assertDontSee('Envío de Mercadería');
        $response->assertDontSee('Lotes y Vencimientos');
        $response->assertDontSee('Listas de precios');
        $response->assertDontSee('Ventas históricas');
        // "Vencimientos" es ambiguo (aparece en otros labels), así que no lo
        // busco como texto suelto acá — alcanza con que la página no reviente.
    }

    public function test_selector_de_todas_las_sucursales_se_oculta_para_admin_si_multisucursal_esta_apagado(): void
    {
        // Antes de este fix, puedeVerTodasLasSucursales() solo chequeaba el
        // rol: un admin en una instalación con multisucursal apagado (ej.
        // deco-hogar) seguía viendo el selector "Todas las sucursales" y los
        // desgloses por sucursal en Dashboard/Informes/Auditoría/Facturas.
        config(['features.multisucursal' => false]);

        $admin = $this->admin();

        $this->assertFalse(
            Livewire::actingAs($admin)->test(Dashboard::class)->instance()->puedeVerTodasLasSucursales()
        );
        $this->assertFalse(
            Livewire::actingAs($admin)->test(ReportsIndex::class)->instance()->puedeVerTodasLasSucursales()
        );
    }

    public function test_selector_de_todas_las_sucursales_se_ve_para_admin_si_multisucursal_esta_prendido(): void
    {
        config(['features.multisucursal' => true]);

        $this->assertTrue(
            Livewire::actingAs($this->admin())->test(Dashboard::class)->instance()->puedeVerTodasLasSucursales()
        );
    }

    public function test_ventas_historicas_se_oculta_del_sidebar_cuando_esta_apagado(): void
    {
        config(['features.historical_sales' => false]);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Ventas históricas');
    }

    public function test_boton_de_nueva_factura_se_oculta_cuando_la_creacion_manual_esta_apagada(): void
    {
        config(['features.invoices_manual_create' => false]);

        Livewire::actingAs($this->admin())
            ->test('invoices.index')
            ->assertDontSeeHtml('Nueva factura');
    }

    public function test_boton_de_nueva_factura_se_ve_cuando_esta_prendido(): void
    {
        config(['features.invoices_manual_create' => true]);

        Livewire::actingAs($this->admin())
            ->test('invoices.index')
            ->assertSeeHtml('Nueva factura');
    }

    public function test_venta_rapida_arranca_en_remito_x_cuando_la_facturacion_manual_esta_apagada(): void
    {
        config(['features.invoices_manual_create' => false]);
        CompanySettings::current()->update(['factura_b_habilitada' => true]);

        Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->assertSet('tipo_comprobante_interno', TipoComprobanteInterno::RemitoX->value);
    }

    public function test_venta_rapida_arranca_en_factura_b_cuando_la_facturacion_manual_esta_prendida(): void
    {
        config(['features.invoices_manual_create' => true]);
        CompanySettings::current()->update(['factura_b_habilitada' => true]);

        Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->assertSet('tipo_comprobante_interno', TipoComprobanteInterno::FacturaB->value);
    }

    public function test_checkbox_de_venta_por_peso_se_oculta_en_productos_y_config_empresa_cuando_esta_apagado(): void
    {
        config(['features.sell_by_weight' => false]);

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->assertDontSeeHtml('Se vende por peso (kg)');

        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->assertDontSeeHtml('Código de barras de balanza');
    }

    public function test_checkbox_de_venta_por_peso_se_ve_cuando_esta_prendido(): void
    {
        config(['features.sell_by_weight' => true]);

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->assertSeeHtml('Se vende por peso (kg)');

        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->assertSeeHtml('Código de barras de balanza');
    }

    public function test_selector_de_sucursal_se_oculta_al_crear_y_editar_usuario_si_multisucursal_esta_apagado(): void
    {
        // Antes de este fix, Users\Create y Users\Edit pedían elegir sucursal
        // (campo requerido) sin importar el flag — una instalación de una
        // sola sucursal (ej. deco-hogar) igual tenía que elegir "la única
        // sucursal que hay" para poder dar de alta un cajero/vendedor.
        config(['features.multisucursal' => false]);

        // No crear una sucursal nueva acá: las migraciones (ver
        // create_product_stocks_table) ya dan de alta una "Principal" por
        // defecto en cuanto no existe ninguna — igual que en una instalación
        // real de un solo local.
        $sucursal = Sucursal::sole();
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'sucursal_id' => $sucursal->id]);

        Livewire::actingAs($this->admin())
            ->test('users.create')
            ->assertDontSeeHtml('Elegir sucursal...')
            ->set('name', 'Juana Pérez')
            ->set('username', 'jperez')
            ->set('password', 'password123')
            ->set('role', Role::Vendedor->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($sucursal->id, User::where('username', 'jperez')->value('sucursal_id'));

        Livewire::actingAs($this->admin())
            ->test('users.edit', ['user' => $vendedor])
            ->assertDontSeeHtml('Elegir sucursal...')
            ->set('name', 'Nuevo Nombre')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($sucursal->id, $vendedor->fresh()->sucursal_id);
    }

    public function test_selector_de_sucursal_se_ve_al_crear_usuario_si_multisucursal_esta_prendido(): void
    {
        config(['features.multisucursal' => true]);

        Livewire::actingAs($this->admin())
            ->test('users.create')
            ->assertSeeHtml('Elegir sucursal...');
    }
}
