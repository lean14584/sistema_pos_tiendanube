<?php

namespace Tests\Feature;

use App\Enums\Role;
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
            'punto_venta' => 2,
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
        Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 3]);

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

    public function test_editar_puede_reusar_su_propio_punto_de_venta(): void
    {
        // La regla unique con ->ignore() no debe rechazar la sucursal contra
        // sí misma si no cambió el punto de venta.
        $sucursal = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 5, 'active' => true]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $sucursal])
            ->set('name', 'Centro renombrado')
            ->set('punto_venta', '5')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_can_delete_a_sucursal_if_more_than_one_exists(): void
    {
        Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 1]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 2]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.index')
            ->call('delete', $norte->id);

        $this->assertDatabaseMissing('sucursales', ['id' => $norte->id]);
    }

    public function test_cannot_delete_the_only_sucursal(): void
    {
        $unica = Sucursal::create(['name' => 'Centro', 'razon_social' => 'Mi Empresa SRL', 'punto_venta' => 1]);

        Livewire::actingAs($this->admin())
            ->test('sucursales.index')
            ->call('delete', $unica->id);

        $this->assertDatabaseHas('sucursales', ['id' => $unica->id]);
    }
}
