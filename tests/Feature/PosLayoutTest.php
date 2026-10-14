<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reordenamiento del POS (2026-10-13): Comprobante+Cliente pasaron a una
 * card propia arriba del carrito (antes vivían en la columna derecha, que
 * se había hecho muy larga), y la columna derecha quedó Medios de pago →
 * Mis Descuentos → Vale de cambio → Total. Este test solo verifica el
 * ORDEN en el HTML renderizado, no estilos.
 */
class PosLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_orden_de_secciones_en_la_pantalla_de_venta(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        Discount::create(['name' => 'Promo Test', 'type' => 'percent', 'value' => 10, 'active' => true]);

        Livewire::actingAs($admin)->test('pos.index')
            ->assertSeeInOrder([
                'Comprobante',
                'Cliente',
                'Productos',
                'Medios de pago',
                'Mis Descuentos',
                '¿Cliente trae un vale de cambio?',
                'Total a cobrar',
            ]);
    }

    public function test_el_check_de_vale_de_cambio_arranca_destildado_por_defecto(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)->test('pos.index')
            ->assertSeeHtml('mostrarVale: false');
    }

    public function test_lista_de_precios_es_columna_propia_cuando_el_modulo_esta_activo(): void
    {
        config(['features.price_lists' => true]);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)->test('pos.index')
            ->assertSeeHtml('md:grid-cols-3')
            ->assertSeeInOrder(['Comprobante', 'Cliente', 'Lista de precios']);
    }

    public function test_lista_de_precios_no_aparece_si_el_modulo_esta_apagado(): void
    {
        config(['features.price_lists' => false]);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)->test('pos.index')
            ->assertSeeHtml('md:grid-cols-2')
            ->assertDontSee('Lista de precios');
    }

    public function test_el_check_de_vale_de_cambio_arranca_tildado_si_ya_hay_uno_aplicado(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $voucher = Voucher::emitir(500);

        Livewire::actingAs($admin)->test('pos.index')
            ->call('addProduct', Product::create(['name' => 'Test', 'price' => 1000, 'iva_rate' => 0, 'stock' => 10])->id)
            ->set('vale_codigo', $voucher->code)
            ->call('buscarVale')
            ->assertSeeHtml('mostrarVale: true');
    }
}
