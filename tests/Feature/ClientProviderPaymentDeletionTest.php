<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Antes de este fix, Clients\Account::deletePayment() y
 * Providers\Account::deletePayment() no chequeaban ningún rol/permiso: un
 * Cajero o Vendedor (que sí tienen acceso al módulo de clientes/proveedores)
 * podían borrar el cobro/pago histórico de otro usuario, borrando también el
 * rastro contable en caja (CashLinker::unlinkX()).
 */
class ClientProviderPaymentDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cajero_no_puede_eliminar_un_cobro_de_cliente(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $payment = ClientPayment::create([
            'client_id' => $client->id, 'date' => now(), 'amount' => 1000, 'method' => 'efectivo',
        ]);

        Livewire::actingAs($cajero)
            ->test('clients.account', ['client' => $client])
            ->call('deletePayment', $payment->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('client_payments', ['id' => $payment->id]);
    }

    public function test_admin_si_puede_eliminar_un_cobro_de_cliente(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $payment = ClientPayment::create([
            'client_id' => $client->id, 'date' => now(), 'amount' => 1000, 'method' => 'efectivo',
        ]);

        Livewire::actingAs($admin)
            ->test('clients.account', ['client' => $client])
            ->call('deletePayment', $payment->id);

        $this->assertDatabaseMissing('client_payments', ['id' => $payment->id]);
    }

    public function test_vendedor_no_puede_eliminar_un_pago_a_proveedor(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $payment = ProviderPayment::create([
            'provider_id' => $provider->id, 'date' => now(), 'amount' => 500, 'method' => 'efectivo',
        ]);

        Livewire::actingAs($vendedor)
            ->test('providers.account', ['provider' => $provider])
            ->call('deletePayment', $payment->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('provider_payments', ['id' => $payment->id]);
    }

    public function test_encargado_si_puede_eliminar_un_pago_a_proveedor(): void
    {
        $encargado = User::factory()->create(['role' => Role::Encargado, 'active' => true]);
        $provider = Provider::create(['name' => 'Proveedor 1']);
        $payment = ProviderPayment::create([
            'provider_id' => $provider->id, 'date' => now(), 'amount' => 500, 'method' => 'efectivo',
        ]);

        Livewire::actingAs($encargado)
            ->test('providers.account', ['provider' => $provider])
            ->call('deletePayment', $payment->id);

        $this->assertDatabaseMissing('provider_payments', ['id' => $payment->id]);
    }
}
