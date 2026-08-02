<?php

namespace Tests\Feature;

use App\Enums\CondicionIva;
use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
use App\Enums\TipoDocumento;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\Afip\AfipGatewayInterface;
use App\Services\Afip\InvoiceCaeEmitter;
use App\Support\StockAdjuster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAfipGateway;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_editar_y_borrar_un_producto_genera_los_tres_logs(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin, 'active' => true]));

        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);
        $product->update(['price' => 1200]);
        $product->delete();

        $logs = AuditLog::where('auditable_type', Product::class)->orderBy('id')->get();

        $this->assertCount(3, $logs);
        $this->assertSame('created', $logs[0]->event);
        $this->assertEqualsWithDelta(1000.0, (float) $logs[0]->changes['price']['new'], 0.01);

        $this->assertSame('updated', $logs[1]->event);
        $this->assertEqualsWithDelta(1000.0, (float) $logs[1]->changes['price']['old'], 0.01);
        $this->assertEqualsWithDelta(1200.0, (float) $logs[1]->changes['price']['new'], 0.01);
        $this->assertArrayNotHasKey('stock', $logs[1]->changes); // no cambió, no debe aparecer

        $this->assertSame('deleted', $logs[2]->event);
        $this->assertNull($logs[2]->changes['price']['new']);
    }

    public function test_cambiar_stock_via_stockadjuster_no_genera_log(): void
    {
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 5]);
        AuditLog::query()->delete(); // limpiar el log del create de arriba

        StockAdjuster::apply([['product_id' => $product->id, 'quantity' => 2]], -1);

        $this->assertSame(0, AuditLog::count());
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_password_nunca_aparece_en_los_cambios_de_usuario(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        AuditLog::query()->delete();

        $user = User::factory()->create(['role' => Role::Vendedor]);
        $user->update(['password' => 'nuevo-secreto', 'role' => 'admin']);

        $logs = AuditLog::where('auditable_type', User::class)->get();

        foreach ($logs as $log) {
            $this->assertArrayNotHasKey('password', $log->changes);
            $this->assertArrayNotHasKey('remember_token', $log->changes);
        }

        $updated = $logs->firstWhere('event', 'updated');
        $this->assertSame('vendedor', $updated->changes['role']['old']);
        $this->assertSame('admin', $updated->changes['role']['new']);
    }

    public function test_emitir_a_afip_genera_un_log_de_actualizacion_con_el_cae(): void
    {
        $fake = new FakeAfipGateway();
        $this->app->instance(AfipGatewayInterface::class, $fake);

        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'punto_venta' => 1]);
        $client = Client::create([
            'name' => 'Cliente Test', 'email' => 'cliente@test.com',
            'condicion_iva' => CondicionIva::ConsumidorFinal->value, 'tipo_documento' => TipoDocumento::SinIdentificar->value,
        ]);
        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'tipo_comprobante_interno' => TipoComprobanteInterno::FacturaB,
            'issue_date' => now(), 'due_date' => now()->addDays(15),
            'tax_rate' => 0, 'status' => 'draft',
        ]);
        $invoice->items()->create(['description' => 'Producto', 'quantity' => 1, 'unit_price' => 1000]);
        AuditLog::query()->delete();

        app(InvoiceCaeEmitter::class)->emit($invoice->fresh());

        $log = AuditLog::where('auditable_type', Invoice::class)->where('event', 'updated')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->changes['cae']['old']);
        $this->assertNotNull($log->changes['cae']['new']);
        $this->assertArrayNotHasKey('afip_response', $log->changes);
    }
}
