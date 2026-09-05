<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SucursalCajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dos_sucursales_pueden_tener_caja_abierta_al_mismo_tiempo(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);

        $cajeroPrincipal = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $principal->id]);
        $cajeroNorte = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $norte->id]);

        Livewire::actingAs($cajeroPrincipal)->test('cash-register.index')
            ->set('openingAmount', '1000')->call('openSession')
            ->assertHasNoErrors();

        Livewire::actingAs($cajeroNorte)->test('cash-register.index')
            ->set('openingAmount', '500')->call('openSession')
            ->assertHasNoErrors();

        $this->assertSame(2, CashSession::where('status', 'open')->count());
        $this->assertNotNull(CashSession::where('sucursal_id', $principal->id)->where('status', 'open')->first());
        $this->assertNotNull(CashSession::where('sucursal_id', $norte->id)->where('status', 'open')->first());
    }

    public function test_cajero_no_puede_abrir_una_segunda_caja_en_su_propia_sucursal(): void
    {
        $principal = Sucursal::sole();
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $principal->id]);

        Livewire::actingAs($cajero)->test('cash-register.index')
            ->set('openingAmount', '1000')->call('openSession');

        Livewire::actingAs($cajero)->test('cash-register.index')
            ->set('openingAmount', '2000')->call('openSession')
            ->assertHasErrors(['openingAmount']);

        $this->assertSame(1, CashSession::where('sucursal_id', $principal->id)->where('status', 'open')->count());
    }

    public function test_un_cobro_en_una_sucursal_no_aparece_en_la_caja_de_otra(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);

        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        // Caja abierta en las dos sucursales.
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => $principal->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $sesionNorte = CashSession::create(['user_id' => $admin->id, 'sucursal_id' => $norte->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        // El admin está parado en "Principal" (la primera, por defecto) y cobra ahí.
        Livewire::actingAs($admin)
            ->test('clients.account', ['client' => $client])
            ->set('amount', '500')
            ->call('addPayment');

        $this->assertDatabaseHas('cash_movements', ['session_id' => CashSession::where('sucursal_id', $principal->id)->first()->id, 'amount' => 500]);
        $this->assertSame(0, $sesionNorte->movements()->count());
    }

    public function test_dos_cajeros_del_mismo_local_pueden_tener_caja_abierta_al_mismo_tiempo(): void
    {
        $principal = Sucursal::sole();
        $cajero1 = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $principal->id]);
        $cajero2 = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $principal->id]);

        Livewire::actingAs($cajero1)->test('cash-register.index')
            ->set('openingAmount', '1000')->call('openSession')
            ->assertHasNoErrors();

        Livewire::actingAs($cajero2)->test('cash-register.index')
            ->set('openingAmount', '500')->call('openSession')
            ->assertHasNoErrors();

        $this->assertSame(2, CashSession::where('sucursal_id', $principal->id)->where('status', 'open')->count());

        // Cada uno ve la SUYA como "openSession", no la del compañero.
        $component1 = Livewire::actingAs($cajero1)->test('cash-register.index');
        $this->assertSame($cajero1->id, $component1->viewData('openSession')->user_id);
        $this->assertCount(1, $component1->viewData('otherOpenSessions'));
        $this->assertSame($cajero2->id, $component1->viewData('otherOpenSessions')->first()->user_id);

        $component2 = Livewire::actingAs($cajero2)->test('cash-register.index');
        $this->assertSame($cajero2->id, $component2->viewData('openSession')->user_id);
    }

    public function test_una_venta_se_anota_en_la_caja_de_quien_la_hizo_no_en_la_de_otro_cajero(): void
    {
        $principal = Sucursal::sole();
        $cajero1 = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $principal->id]);
        $cajero2 = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $principal->id]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);

        $sesion1 = CashSession::create(['user_id' => $cajero1->id, 'sucursal_id' => $principal->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $sesion2 = CashSession::create(['user_id' => $cajero2->id, 'sucursal_id' => $principal->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        Livewire::actingAs($cajero2)
            ->test('clients.account', ['client' => $client])
            ->set('amount', '700')
            ->call('addPayment');

        $this->assertSame(0, $sesion1->movements()->count());
        $this->assertSame(1, $sesion2->movements()->count());
    }

    public function test_un_cajero_no_puede_abrir_una_segunda_caja_propia_aunque_otro_ya_tenga_una_abierta(): void
    {
        $principal = Sucursal::sole();
        $cajero1 = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $principal->id]);
        $cajero2 = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $principal->id]);

        CashSession::create(['user_id' => $cajero1->id, 'sucursal_id' => $principal->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);

        Livewire::actingAs($cajero2)->test('cash-register.index')
            ->set('openingAmount', '1000')->call('openSession')
            ->assertHasNoErrors();

        Livewire::actingAs($cajero2)->test('cash-register.index')
            ->set('openingAmount', '2000')->call('openSession')
            ->assertHasErrors(['openingAmount']);

        $this->assertSame(2, CashSession::where('sucursal_id', $principal->id)->where('status', 'open')->count());
    }
}
