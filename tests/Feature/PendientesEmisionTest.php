<?php

namespace Tests\Feature;

use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendientesEmisionTest extends TestCase
{
    use RefreshDatabase;

    private function factura(TipoComprobanteInterno $tipo, string $status, ?string $cae = null): Invoice
    {
        static $n = 0;
        $n++;

        $client = Client::create(['name' => "Cliente {$n}", 'email' => "c{$n}@test.com"]);

        $invoice = Invoice::create([
            'number' => 'FAC-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'client_id' => $client->id,
            'tipo_comprobante_interno' => $tipo,
            'issue_date' => now(), 'due_date' => now()->addDays(15),
            'tax_rate' => 21, 'status' => $status,
        ]);

        if ($cae !== null) {
            $invoice->forceFill(['cae' => $cae])->save();
        }

        return $invoice;
    }

    public function test_cuenta_solo_facturas_fiscales_sin_cae_y_fuera_de_borrador(): void
    {
        // Cuenta: fiscal, finalizada, sin CAE.
        $this->factura(TipoComprobanteInterno::FacturaB, 'pending');
        $this->factura(TipoComprobanteInterno::FacturaA, 'pending');

        // No cuenta: Remito X no es fiscal.
        $this->factura(TipoComprobanteInterno::RemitoX, 'pending');
        // No cuenta: en borrador.
        $this->factura(TipoComprobanteInterno::FacturaB, 'draft');
        // No cuenta: ya tiene CAE (emitida).
        $this->factura(TipoComprobanteInterno::FacturaA, 'pending', cae: '75123456789012');

        $this->assertSame(2, Invoice::pendientesDeEmision()->count());
        $this->assertSame(2, Invoice::pendientesDeEmisionCountCached());
    }
}
