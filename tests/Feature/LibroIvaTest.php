<?php

namespace Tests\Feature;

use App\Enums\CondicionIva;
use App\Enums\Role;
use App\Enums\TipoComprobante;
use App\Enums\TipoComprobanteInterno;
use App\Enums\TipoDocumento;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Afip\AfipGatewayInterface;
use App\Services\Afip\InvoiceCaeEmitter;
use App\Support\LibroIva\LibroIvaCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeAfipGateway;
use Tests\TestCase;
use ZipArchive;

class LibroIvaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function emitirFacturaFiscal(): Invoice
    {
        $this->app->instance(AfipGatewayInterface::class, new FakeAfipGateway());

        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);

        $client = Client::create([
            'name' => 'Cliente Test', 'email' => 'cliente@test.com', 'tax_id' => '20111111112',
            'condicion_iva' => CondicionIva::ResponsableInscripto->value, 'tipo_documento' => TipoDocumento::Cuit->value,
        ]);

        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaA,
            'issue_date' => now(), 'due_date' => now()->addDays(15),
            'tax_rate' => 21, 'status' => 'draft',
        ]);
        $invoice->items()->create(['description' => 'Producto', 'quantity' => 1, 'unit_price' => 1000]);

        app(InvoiceCaeEmitter::class)->emit($invoice->fresh());

        return $invoice->fresh();
    }

    private function cargarCompra(): Purchase
    {
        $provider = Provider::create(['name' => 'Proveedor Test', 'tax_id' => '30111111113', 'tipo_documento' => 'cuit']);

        $purchase = Purchase::create([
            'number' => 'COM-0001', 'provider_id' => $provider->id,
            'tipo_comprobante' => TipoComprobante::FacturaA, 'punto_venta' => 3, 'numero_comprobante' => 100,
            'issue_date' => now(), 'due_date' => now()->addDays(15),
            'tax_rate' => 21, 'status' => 'paid',
        ]);
        $product = Product::create(['name' => 'Insumo', 'price' => 500, 'stock' => 0]);
        $purchase->items()->create(['product_id' => $product->id, 'description' => 'Insumo', 'quantity' => 1, 'unit_price' => 500]);

        return $purchase;
    }

    public function test_calcula_una_fila_de_ventas_a_partir_de_una_factura_fiscal(): void
    {
        $invoice = $this->emitirFacturaFiscal();

        $rows = LibroIvaCalculator::ventas(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($invoice->punto_venta, $row->puntoVenta);
        $this->assertSame($invoice->numero_comprobante_afip, $row->numeroComprobante);
        $this->assertSame(80, $row->codigoDocumento); // CUIT
        $this->assertEqualsWithDelta(1000.0, $row->importeNetoGravado, 0.01);
        $this->assertEqualsWithDelta(210.0, $row->ivaLiquidado, 0.01);
        $this->assertEqualsWithDelta(1210.0, $row->importeTotal, 0.01);

        $resumen = LibroIvaCalculator::resumenPorAlicuota($rows);
        $this->assertCount(1, $resumen);
        $this->assertEqualsWithDelta(21.0, $resumen->first()['tasa'], 0.01);
    }

    public function test_una_factura_sin_cae_no_entra_al_libro_iva_ventas(): void
    {
        $client = Client::create([
            'name' => 'Cliente Sin CAE', 'email' => 'x@test.com',
            'condicion_iva' => CondicionIva::ConsumidorFinal->value, 'tipo_documento' => TipoDocumento::SinIdentificar->value,
        ]);
        Invoice::create([
            'number' => 'FAC-0002', 'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaB,
            'issue_date' => now(), 'due_date' => now()->addDays(15),
            'tax_rate' => 21, 'status' => 'draft',
        ]);

        $rows = LibroIvaCalculator::ventas(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertCount(0, $rows);
    }

    public function test_calcula_una_fila_de_compras_a_partir_de_una_compra_cargada(): void
    {
        $purchase = $this->cargarCompra();

        $rows = LibroIvaCalculator::compras(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($purchase->punto_venta, $row->puntoVenta);
        $this->assertSame($purchase->numero_comprobante, $row->numeroComprobante);
        $this->assertEqualsWithDelta(500.0, $row->importeNetoGravado, 0.01);
        $this->assertEqualsWithDelta(105.0, $row->ivaLiquidado, 0.01);
    }

    public function test_una_compra_en_borrador_no_entra_al_libro_iva_compras(): void
    {
        $provider = Provider::create(['name' => 'Proveedor Draft']);
        Purchase::create([
            'number' => 'COM-0002', 'provider_id' => $provider->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15),
            'tax_rate' => 21, 'status' => 'draft',
        ]);

        $rows = LibroIvaCalculator::compras(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertCount(0, $rows);
    }

    public function test_admin_puede_ver_la_pantalla_del_libro_iva(): void
    {
        $this->emitirFacturaFiscal();

        Livewire::actingAs($this->admin())
            ->test('libro-iva.index')
            ->assertOk()
            ->assertSee('Cliente Test');
    }

    public function test_vendedor_no_puede_acceder_al_libro_iva(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);

        $this->actingAs($vendedor)->get(route('libro-iva.index'))->assertForbidden();
    }

    public function test_el_export_genera_un_zip_con_los_cuatro_archivos_rg4597(): void
    {
        $this->emitirFacturaFiscal();
        $this->cargarCompra();

        $response = $this->actingAs($this->admin())->get(route('libro-iva.export', [
            'desde' => now()->startOfMonth()->toDateString(),
            'hasta' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertStringContainsString('.zip', $response->headers->get('content-disposition'));

        $path = $response->getFile()->getPathname();
        $zip = new ZipArchive();
        $zip->open($path);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $this->assertCount(4, $names);
        $this->assertTrue(collect($names)->contains(fn ($name) => str_contains($name, 'VENTAS_CBTE')));
        $this->assertTrue(collect($names)->contains(fn ($name) => str_contains($name, 'VENTAS_ALICUOTAS')));
        $this->assertTrue(collect($names)->contains(fn ($name) => str_contains($name, 'COMPRAS_CBTE')));
        $this->assertTrue(collect($names)->contains(fn ($name) => str_contains($name, 'COMPRAS_ALICUOTAS')));

        $zip->close();
    }
}
