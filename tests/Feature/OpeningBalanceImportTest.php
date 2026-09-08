<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GeneratesExcelFixtures;
use Tests\TestCase;

class OpeningBalanceImportTest extends TestCase
{
    use GeneratesExcelFixtures;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_carga_el_saldo_inicial_de_un_cliente_existente_y_aparece_en_su_cuenta_corriente(): void
    {
        $cliente = Client::create(['name' => 'Kiosco El Sol', 'email' => 'sol@example.com', 'tax_id' => '20111111112']);

        $archivo = $this->excel([
            ['CUIT', 'Nombre', 'Saldo', 'Fecha'],
            ['20111111112', 'Kiosco El Sol', '15000,50', '01/06/2026'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('clients.import-saldo')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion')
            ->assertSet('step', 'resultado');

        $cliente->refresh();
        $this->assertEqualsWithDelta(15000.50, (float) $cliente->opening_balance, 0.01);
        $this->assertSame('2026-06-01', $cliente->opening_balance_date->toDateString());
        $this->assertEqualsWithDelta(15000.50, $cliente->saldoCuentaCorriente(), 0.01);
    }

    public function test_no_crea_un_cliente_nuevo_si_no_matchea_ninguno_existente(): void
    {
        $archivo = $this->excel([
            ['CUIT', 'Nombre', 'Saldo'],
            ['99999999999', 'Cliente Inexistente', '1000'],
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test('clients.import-saldo')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $this->assertSame(0, Client::count());
        $this->assertCount(1, $component->get('resultado')['omitidos']);
    }

    public function test_carga_el_saldo_inicial_de_un_proveedor_existente(): void
    {
        $proveedor = Provider::create(['name' => 'Distribuidora Norte', 'tax_id' => '30712345678']);

        $archivo = $this->excel([
            ['CUIT', 'Saldo'],
            ['30712345678', '5000'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('providers.import-saldo')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $proveedor->refresh();
        $this->assertEqualsWithDelta(5000.0, (float) $proveedor->opening_balance, 0.01);
        $this->assertEqualsWithDelta(5000.0, $proveedor->saldoCuentaCorriente(), 0.01);
    }

    public function test_cajero_no_puede_entrar_a_cargar_saldo_inicial(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('clients.import-saldo'))->assertForbidden();
        $this->actingAs($cajero)->get(route('providers.import-saldo'))->assertForbidden();
    }
}
