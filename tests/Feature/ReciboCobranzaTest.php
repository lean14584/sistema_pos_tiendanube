<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReciboCobranzaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_el_recibo_de_un_cobro_devuelve_un_pdf(): void
    {
        $client = Client::create(['name' => 'Juan Perez', 'email' => 'j@test.com', 'phone' => '3511234567']);
        $payment = ClientPayment::create([
            'client_id' => $client->id, 'date' => now(), 'amount' => 15000, 'method' => 'efectivo', 'notes' => 'Cobranza',
        ]);

        $response = $this->actingAs($this->admin())->get(route('clients.recibo', $payment));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_la_cuenta_corriente_del_cliente_muestra_el_boton_de_recibo(): void
    {
        $client = Client::create(['name' => 'Juan Perez', 'email' => 'j@test.com', 'phone' => '3511234567']);
        $payment = ClientPayment::create([
            'client_id' => $client->id, 'date' => now(), 'amount' => 5000, 'method' => 'efectivo',
        ]);

        // La pantalla de cuenta corriente enlaza al recibo de ese cobro.
        $this->actingAs($this->admin())
            ->get(route('clients.account', $client))
            ->assertOk()
            ->assertSee(route('clients.recibo', $payment), false);
    }
}
