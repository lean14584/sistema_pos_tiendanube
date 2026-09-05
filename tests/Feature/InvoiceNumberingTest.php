<?php

namespace Tests\Feature;

use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Sucursal;
use App\Support\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function crear(string $tipo, string $number): Invoice
    {
        $client = Client::consumidorFinal();

        return Invoice::create([
            'number' => $number,
            'client_id' => $client->id,
            'tipo_comprobante_interno' => $tipo,
            'issue_date' => now(), 'due_date' => now(), 'tax_rate' => 0, 'status' => 'pending',
        ]);
    }

    public function test_usa_el_punto_de_venta_de_la_sucursal_a_cuatro_digitos(): void
    {
        // Sin usuario logueado, CurrentSucursal cae a "la primera sucursal"
        // (la única que existe en un DB recién migrado: "Principal").
        Sucursal::sole()->update(['punto_venta' => 3]);

        $this->assertSame('0003-00000001', InvoiceNumberGenerator::next(TipoComprobanteInterno::FacturaB->value));
    }

    public function test_sin_ninguna_sucursal_resoluble_usa_el_punto_de_venta_de_la_empresa(): void
    {
        // Único caso donde CurrentSucursal no tiene nada que resolver:
        // borramos la sucursal auto-creada para simular ese escenario.
        Sucursal::query()->delete();
        CompanySettings::current()->update(['punto_venta' => 3]);

        $this->assertSame('0003-00000001', InvoiceNumberGenerator::next(TipoComprobanteInterno::FacturaB->value));
    }

    public function test_serie_independiente_por_tipo_y_correlativa(): void
    {
        CompanySettings::current()->update(['punto_venta' => 1]);

        $n1 = InvoiceNumberGenerator::next(TipoComprobanteInterno::FacturaB->value);
        $this->crear(TipoComprobanteInterno::FacturaB->value, $n1);

        $n2 = InvoiceNumberGenerator::next(TipoComprobanteInterno::FacturaB->value);
        $this->crear(TipoComprobanteInterno::FacturaB->value, $n2);

        // Otro tipo arranca su propia serie desde 1.
        $r1 = InvoiceNumberGenerator::next(TipoComprobanteInterno::RemitoX->value);

        $this->assertSame('0001-00000001', $n1);
        $this->assertSame('0001-00000002', $n2);
        $this->assertSame('0001-00000001', $r1); // remito: serie propia
    }

    public function test_dos_tipos_distintos_pueden_compartir_numero(): void
    {
        // El mismo número en distinto tipo NO viola la unicidad (única por
        // tipo + número, como en AFIP).
        $fb = $this->crear(TipoComprobanteInterno::FacturaB->value, '0001-00000001');
        $rx = $this->crear(TipoComprobanteInterno::RemitoX->value, '0001-00000001');

        $this->assertSame('0001-00000001', $fb->number);
        $this->assertSame('0001-00000001', $rx->number);
        $this->assertDatabaseCount('invoices', 2);
    }
}
