<?php

namespace Tests\Feature;

use App\Enums\CondicionIva;
use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
use App\Enums\TipoDocumento;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Afip\AfipGatewayInterface;
use App\Services\Afip\InvoiceCaeEmitter;
use App\Support\CurrentSucursal;
use App\Support\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fakes\FakeAfipGateway;
use Tests\TestCase;

class SucursalAfipTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): FakeAfipGateway
    {
        $fake = new FakeAfipGateway();
        $this->app->instance(AfipGatewayInterface::class, $fake);

        return $fake;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_cada_sucursal_numera_sus_comprobantes_con_su_propio_punto_de_venta(): void
    {
        $principal = Sucursal::sole();
        $principal->update(['punto_venta' => 1]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);

        $admin = $this->admin();
        $this->actingAs($admin);

        // Parado en Principal.
        $numeroPrincipal = InvoiceNumberGenerator::next(TipoComprobanteInterno::FacturaB->value);

        // Un admin cambia a Norte.
        CurrentSucursal::set($norte->id);
        $numeroNorte = InvoiceNumberGenerator::next(TipoComprobanteInterno::FacturaB->value);

        $this->assertSame('0001-00000001', $numeroPrincipal);
        $this->assertSame('0002-00000001', $numeroNorte);
    }

    public function test_una_venta_hecha_por_un_cajero_queda_con_la_sucursal_de_su_cajero(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        $cajeroNorte = User::factory()->create(['role' => Role::Cajero, 'active' => true, 'sucursal_id' => $norte->id]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Producto', 'price' => 1000, 'stock' => 10]);

        Livewire::actingAs($cajeroNorte)
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->call('addProductItem', $product->id)
            ->call('save')
            ->assertHasNoErrors();

        $created = Invoice::latest('id')->first();

        $this->assertSame($norte->id, $created->sucursal_id);
        $this->assertNotSame($principal->id, $created->sucursal_id);
    }

    public function test_emitir_a_afip_usa_el_punto_de_venta_de_la_sucursal_de_la_factura_no_la_sesion_actual(): void
    {
        $this->fake();
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);

        $principal = Sucursal::sole();
        $principal->update(['punto_venta' => 1]);
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 9]);

        $client = Client::create([
            'name' => 'Cliente Test', 'email' => 'cliente@test.com',
            'tax_id' => '20111111112', 'condicion_iva' => CondicionIva::ConsumidorFinal->value,
            'tipo_documento' => TipoDocumento::SinIdentificar->value,
        ]);

        // La factura se creó en "Norte" (quedó guardado en la factura)...
        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id, 'sucursal_id' => $norte->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaB,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'tax_rate' => 21, 'status' => 'draft',
        ]);
        $invoice->items()->create(['description' => 'Producto', 'quantity' => 1, 'unit_price' => 1000]);

        // ...pero quien la emite ahora (sin sesión activa en este test, cae
        // a "la primera sucursal" = Principal, punto de venta 1) NO debe
        // afectar con qué punto de venta se emite: tiene que ser el de Norte.
        $emitted = app(InvoiceCaeEmitter::class)->emit($invoice->fresh());

        $this->assertSame(9, $emitted->punto_venta);
    }
}
