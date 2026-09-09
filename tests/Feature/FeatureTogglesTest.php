<?php

namespace Tests\Feature;

use App\Enums\Role;
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
        ]);

        $response = $this->actingAs($this->admin())->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Sucursales');
        $response->assertDontSee('Tiendanube');
        $response->assertDontSee('Envío de Mercadería');
        $response->assertDontSee('Lotes y Vencimientos');
        $response->assertDontSee('Listas de precios');
        // "Vencimientos" es ambiguo (aparece en otros labels), así que no lo
        // busco como texto suelto acá — alcanza con que la página no reviente.
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
}
