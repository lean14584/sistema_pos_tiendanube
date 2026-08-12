<?php

namespace Tests\Feature;

use App\Enums\CondicionIva;
use App\Enums\TipoComprobanteInterno;
use App\Enums\TipoDocumento;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Services\Afip\AfipGatewayInterface;
use App\Services\Afip\InvoiceCaeEmitter;
use App\Support\LibroIva\LibroIvaCalculator;
use App\Support\LibroIva\LibroIvaExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Fakes\FakeAfipGateway;
use Tests\TestCase;

class IvaPorItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Factura con 21% + 10,5% + exento en el mismo comprobante.
     */
    private function facturaMixta(): Invoice
    {
        $client = Client::create([
            'name' => 'Cliente Mixto', 'email' => 'mixto@test.com', 'tax_id' => '20111111112',
            'condicion_iva' => CondicionIva::ResponsableInscripto->value, 'tipo_documento' => TipoDocumento::Cuit->value,
        ]);

        $invoice = Invoice::create([
            'number' => 'FAC-9001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaA,
            'issue_date' => now(), 'due_date' => now(), 'tax_rate' => 0, 'status' => 'pending',
        ]);

        $invoice->items()->create(['description' => 'General', 'quantity' => 1, 'unit_price' => 1000, 'iva_rate' => 21]);
        $invoice->items()->create(['description' => 'Reducida', 'quantity' => 1, 'unit_price' => 500, 'iva_rate' => 10.5]);
        $invoice->items()->create(['description' => 'Exento', 'quantity' => 1, 'unit_price' => 300, 'iva_rate' => 0]);

        return $invoice->fresh();
    }

    public function test_totales_de_una_factura_con_alicuotas_mezcladas(): void
    {
        $invoice = $this->facturaMixta();

        $this->assertEqualsWithDelta(1800.0, (float) $invoice->subtotal, 0.01);      // 1000 + 500 + 300
        $this->assertEqualsWithDelta(1500.0, (float) $invoice->neto_gravado, 0.01);  // 1000 + 500
        $this->assertEqualsWithDelta(300.0, (float) $invoice->neto_exento, 0.01);
        $this->assertEqualsWithDelta(262.5, (float) $invoice->tax_amount, 0.01);     // 210 + 52,50
        $this->assertEqualsWithDelta(2062.5, (float) $invoice->total, 0.01);

        $desglose = $invoice->ivaPorAlicuota();
        $this->assertCount(2, $desglose);
        $this->assertEqualsWithDelta(10.5, $desglose[0]['tasa'], 0.01);
        $this->assertEqualsWithDelta(52.5, $desglose[0]['iva'], 0.01);
        $this->assertEqualsWithDelta(21.0, $desglose[1]['tasa'], 0.01);
        $this->assertEqualsWithDelta(210.0, $desglose[1]['iva'], 0.01);
    }

    public function test_la_emision_a_afip_manda_una_alicuota_por_tasa(): void
    {
        $fake = new FakeAfipGateway();
        $this->app->instance(AfipGatewayInterface::class, $fake);
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);

        app(InvoiceCaeEmitter::class)->emit($this->facturaMixta());

        $request = $fake->requestsRecibidas[0];
        $this->assertEqualsWithDelta(1500.0, $request->impNeto, 0.01);   // neto gravado
        $this->assertEqualsWithDelta(300.0, $request->impOpEx, 0.01);    // exento
        $this->assertEqualsWithDelta(262.5, $request->impIva, 0.01);
        $this->assertEqualsWithDelta(2062.5, $request->impTotal, 0.01);
        $this->assertCount(2, $request->alicuotas);
    }

    public function test_el_libro_iva_desglosa_dos_lineas_de_alicuota(): void
    {
        $fake = new FakeAfipGateway();
        $this->app->instance(AfipGatewayInterface::class, $fake);
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        app(InvoiceCaeEmitter::class)->emit($this->facturaMixta());

        $rows = LibroIvaCalculator::ventas(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        // Un comprobante con dos alícuotas gravadas.
        $this->assertCount(1, $rows);
        $this->assertCount(2, $rows->first()->alicuotas);
        $this->assertEqualsWithDelta(300.0, $rows->first()->importeExento, 0.01);

        // El archivo de alícuotas tiene una línea por tasa.
        $alicuotasFile = rtrim(LibroIvaExporter::ventasAlicuotas($rows), "\r\n");
        $this->assertCount(2, explode("\r\n", $alicuotasFile));

        // El comprobante declara "2" alícuotas (posición 19 del registro CBTE).
        $cbte = rtrim(LibroIvaExporter::ventasCbte($rows), "\r\n");
        $widths = [8, 3, 5, 20, 20, 2, 20, 30, 15, 15, 15, 15, 15, 15, 15, 15, 3, 10, 1, 1, 15, 8];
        $offset = array_sum(array_slice($widths, 0, 18));
        $this->assertSame('2', substr($cbte, $offset, 1));
    }

    public function test_el_resumen_por_alicuota_agrupa_varias_facturas(): void
    {
        $fake = new FakeAfipGateway();
        $this->app->instance(AfipGatewayInterface::class, $fake);
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        app(InvoiceCaeEmitter::class)->emit($this->facturaMixta());

        $rows = LibroIvaCalculator::ventas(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $resumen = LibroIvaCalculator::resumenPorAlicuota($rows);

        $this->assertCount(2, $resumen);
        $this->assertEqualsWithDelta(262.5, $resumen->sum('iva'), 0.01);
        $this->assertEqualsWithDelta(1500.0, $resumen->sum('netoGravado'), 0.01);
    }
}
