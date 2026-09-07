<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SucursalesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_solo_admin_puede_ver_sucursales(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($vendedor)->get(route('sucursales.index'))->assertForbidden();
        $this->actingAs($cajero)->get(route('sucursales.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('sucursales.index'))->assertOk();
    }

    public function test_can_create_a_sucursal(): void
    {
        Livewire::actingAs($this->admin())
            ->test('sucursales.create')
            ->set('name', 'Sucursal Centro')
            ->set('razon_social', 'Mi Empresa SRL')
            ->set('punto_venta', '2')
            ->call('save')
            ->assertRedirect(route('sucursales.index'));

        $this->assertDatabaseHas('sucursales', [
            'name' => 'Sucursal Centro',
            'razon_social' => 'Mi Empresa SRL',
            'active' => true,
        ]);
        $this->assertDatabaseHas('puntos_venta', [
            'sucursal_id' => Sucursal::where('name', 'Sucursal Centro')->value('id'),
            'numero' => 2,
            'active' => true,
        ]);
    }

    public function test_name_razon_social_y_punto_venta_son_requeridos(): void
    {
        Livewire::actingAs($this->admin())
            ->test('sucursales.create')
            ->set('name', '')
            ->set('razon_social', '')
            ->set('punto_venta', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'razon_social' => 'required', 'punto_venta' => 'required']);
    }

    public function test_punto_venta_no_se_puede_repetir_entre_sucursales(): void
    {
        $centro = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL']);
        $centro->puntosVenta()->create(['numero' => 3, 'active' => true]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.create')
            ->set('name', 'Norte')
            ->set('razon_social', 'Mi Empresa SRL')
            ->set('punto_venta', '3')
            ->call('save')
            ->assertHasErrors(['punto_venta' => 'unique']);
    }

    public function test_can_edit_a_sucursal(): void
    {
        $sucursal = Sucursal::create(['name' => 'Vieja', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 4, 'active' => true]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $sucursal])
            ->set('name', 'Nueva')
            ->call('save')
            ->assertRedirect(route('sucursales.index'));

        $this->assertDatabaseHas('sucursales', ['id' => $sucursal->id, 'name' => 'Nueva']);
    }

    public function test_se_puede_agregar_un_punto_de_venta_adicional_a_una_sucursal(): void
    {
        $sucursal = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'active' => true]);
        $sucursal->puntosVenta()->create(['numero' => 5, 'active' => true]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $sucursal])
            ->set('nuevoPuntoVentaNumero', '6')
            ->set('nuevoPuntoVentaNombre', 'Online')
            ->call('agregarPuntoVenta')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('puntos_venta', ['sucursal_id' => $sucursal->id, 'numero' => 6, 'nombre' => 'Online']);
    }

    public function test_no_se_puede_agregar_un_punto_de_venta_ya_usado_por_otra_sucursal(): void
    {
        $centro = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'active' => true]);
        $centro->puntosVenta()->create(['numero' => 5, 'active' => true]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa SRL', 'active' => true]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $norte])
            ->set('nuevoPuntoVentaNumero', '5')
            ->call('agregarPuntoVenta')
            ->assertHasErrors(['nuevoPuntoVentaNumero' => 'unique']);
    }

    public function test_no_se_puede_desactivar_el_unico_punto_de_venta_activo(): void
    {
        $sucursal = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'active' => true]);
        $pv = $sucursal->puntosVenta()->create(['numero' => 5, 'active' => true]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $sucursal])
            ->call('togglePuntoVenta', $pv->id)
            ->assertHasErrors('puntosVenta');

        $this->assertDatabaseHas('puntos_venta', ['id' => $pv->id, 'active' => true]);
    }

    public function test_no_se_puede_borrar_un_punto_de_venta_que_ya_facturo(): void
    {
        $sucursal = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'active' => true]);
        $pv = $sucursal->puntosVenta()->create(['numero' => 5, 'active' => true]);
        $sucursal->puntosVenta()->create(['numero' => 6, 'active' => true]);

        \App\Models\Invoice::create([
            'number' => '0005-00000001',
            'client_id' => \App\Models\Client::consumidorFinal()->id,
            'sucursal_id' => $sucursal->id,
            'punto_venta' => 5,
            'issue_date' => now(),
            'due_date' => now(),
            'tax_rate' => 0,
            'status' => 'draft',
        ]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $sucursal])
            ->call('eliminarPuntoVenta', $pv->id)
            ->assertHasErrors('puntosVenta');

        $this->assertDatabaseHas('puntos_venta', ['id' => $pv->id]);
    }

    public function test_can_delete_a_sucursal_if_more_than_one_exists(): void
    {
        // Ya existe "Principal" (la que crea la migración de product_stocks
        // al no encontrar ninguna sucursal): con esta ya son 2+.
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 2]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.index')
            ->call('delete', $norte->id);

        $this->assertDatabaseMissing('sucursales', ['id' => $norte->id]);
    }

    public function test_cannot_delete_a_sucursal_with_stock_cargado(): void
    {
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 2]);
        $product = Product::create(['name' => 'Yerba', 'price' => 3000, 'stock' => 5]);
        ProductStock::create(['product_id' => $product->id, 'sucursal_id' => $norte->id, 'stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.index')
            ->call('delete', $norte->id);

        $this->assertDatabaseHas('sucursales', ['id' => $norte->id]);
    }

    public function test_cannot_delete_the_only_sucursal(): void
    {
        // La única sucursal en un DB recién migrado es "Principal" (la
        // crea automáticamente la migración de product_stocks).
        $unica = Sucursal::sole();

        Livewire::actingAs($this->admin())
            ->test('sucursales.index')
            ->call('delete', $unica->id);

        $this->assertDatabaseHas('sucursales', ['id' => $unica->id]);
    }
}
