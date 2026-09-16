<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CashSession;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProvidersAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    /**
     * MEJORA: addPayment() no tenía lock — dos submits casi simultáneos
     * (doble clic) creaban dos ProviderPayment del mismo pago. Mismo patrón
     * de test que ClientsTest::test_doble_clic_en_registrar_cobro_de_cuenta_corriente_no_lo_duplica.
     */
    public function test_doble_clic_en_registrar_pago_de_cuenta_corriente_no_lo_duplica(): void
    {
        $admin = $this->admin();
        CashSession::create(['user_id' => $admin->id, 'sucursal_id' => Sucursal::sole()->id, 'status' => 'open', 'opened_at' => now(), 'opening_amount' => 0]);
        $provider = Provider::create(['name' => 'Proveedor 1']);

        $component = Livewire::actingAs($admin)
            ->test('providers.account', ['provider' => $provider])
            ->set('amount', '3000');

        $component->call('addPayment')->assertHasNoErrors();
        $component->call('addPayment')->assertHasErrors('amount');

        $this->assertSame(1, ProviderPayment::count(), 'No debería duplicarse el pago al registrarlo dos veces.');
    }
}
