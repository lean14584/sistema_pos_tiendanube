<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\HistoricalSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\GeneratesExcelFixtures;
use Tests\TestCase;

class HistoricalSaleImportTest extends TestCase
{
    use GeneratesExcelFixtures;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_importa_ventas_y_matchea_el_cliente_existente_por_cuit(): void
    {
        $cliente = Client::create(['name' => 'Kiosco El Sol', 'email' => 'sol@example.com', 'tax_id' => '20111111112']);

        $archivo = $this->excel([
            ['Fecha', 'Cliente', 'CUIT', 'Tipo', 'Numero', 'Total'],
            ['15/03/2025', 'Kiosco El Sol', '20111111112', 'Factura B', '0001-00001234', '5000'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('historical-sales.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion')
            ->assertSet('step', 'resultado');

        $venta = HistoricalSale::first();
        $this->assertNotNull($venta);
        $this->assertSame($cliente->id, $venta->client_id);
        $this->assertSame('2025-03-15', $venta->sale_date->toDateString());
        $this->assertEqualsWithDelta(5000.0, (float) $venta->total, 0.01);
        $this->assertSame('Factura B', $venta->comprobante_type);

        // No afecta el saldo de cuenta corriente (eso se carga aparte).
        $this->assertSame(0.0, $cliente->fresh()->saldoCuentaCorriente());
    }

    public function test_conserva_el_nombre_del_cliente_aunque_no_matchee_ninguno(): void
    {
        $archivo = $this->excel([
            ['Fecha', 'Cliente', 'Total'],
            ['01/01/2025', 'Cliente Ya No Existe', '1000'],
        ]);

        Livewire::actingAs($this->admin())
            ->test('historical-sales.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $venta = HistoricalSale::first();
        $this->assertNull($venta->client_id);
        $this->assertSame('Cliente Ya No Existe', $venta->client_name_raw);
    }

    public function test_fila_sin_fecha_valida_se_omite(): void
    {
        $archivo = $this->excel([
            ['Fecha', 'Cliente', 'Total'],
            ['fecha-invalida', 'Cliente X', '1000'],
            ['01/01/2025', 'Cliente Válido', '2000'],
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test('historical-sales.import')
            ->set('archivo', $archivo)
            ->call('confirmarImportacion');

        $this->assertSame(1, HistoricalSale::count());
        $this->assertCount(1, $component->get('resultado')['omitidos']);
    }

    public function test_cajero_no_puede_entrar_a_importar_ni_ver_ventas_historicas(): void
    {
        $cajero = User::factory()->create(['role' => Role::Cajero, 'active' => true]);

        $this->actingAs($cajero)->get(route('historical-sales.import'))->assertForbidden();
        $this->actingAs($cajero)->get(route('historical-sales.index'))->assertForbidden();
    }
}
