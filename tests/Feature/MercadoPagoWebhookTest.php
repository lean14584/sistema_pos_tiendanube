<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\MercadoPago\MercadoPagoQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgo de la auditoría del 15/9: /mp/webhook era una ruta pública sin
 * ninguna verificación — cualquier POST con type=payment&data.id=lo-que-sea
 * disparaba una llamada bloqueante de hasta 15s a la API de MP. Se agregó
 * la validación de firma que documenta Mercado Pago (header x-signature,
 * HMAC-SHA256 sobre un manifest con el data.id del query string), fail-closed
 * como ya hace TiendanubeWebhookController.
 */
class MercadoPagoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $invoice = Invoice::create([
            'number' => 'FAC-0001', 'client_id' => $client->id,
            'issue_date' => now(), 'due_date' => now()->addDays(15), 'status' => 'pending',
            'mp_external_reference' => 'REF-1',
        ]);
        $invoice->items()->create(['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 1000]);

        return $invoice;
    }

    public function test_rechaza_el_webhook_si_no_hay_secret_configurado(): void
    {
        config(['mercadopago.webhook_secret' => null]);

        $response = $this->postJson('/mp/webhook?type=payment&data.id=12345', ['type' => 'payment', 'data' => ['id' => '12345']]);

        $response->assertStatus(401);
    }

    public function test_rechaza_el_webhook_con_firma_invalida(): void
    {
        config(['mercadopago.webhook_secret' => 'shhh']);

        $response = $this->withHeaders([
            'x-signature' => 'ts=1700000000,v1=firmainventada',
            'x-request-id' => 'req-1',
        ])->postJson('/mp/webhook?type=payment&data.id=12345', ['type' => 'payment', 'data' => ['id' => '12345']]);

        $response->assertStatus(401);
    }

    public function test_rechaza_el_webhook_sin_header_de_firma(): void
    {
        config(['mercadopago.webhook_secret' => 'shhh']);

        $response = $this->postJson('/mp/webhook?type=payment&data.id=12345', ['type' => 'payment', 'data' => ['id' => '12345']]);

        $response->assertStatus(401);
    }

    public function test_acepta_una_firma_valida_y_aplica_el_pago(): void
    {
        config(['mercadopago.webhook_secret' => 'shhh']);
        $invoice = $this->invoice();

        $ts = (string) time();
        $requestId = 'req-123';
        $dataId = '999999';
        // Mismo algoritmo que MercadoPagoWebhookController::firmaValida():
        // manifest "id:<data.id>;request-id:<x-request-id>;ts:<ts>;", HMAC-SHA256 hex.
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $hash = hash_hmac('sha256', $manifest, 'shhh');

        $fake = $this->createMock(MercadoPagoQrService::class);
        $fake->method('webhookSecretFor')->willReturn('shhh');
        $fake->method('paymentPaidReference')->with($dataId, null)->willReturn('REF-1');
        $this->app->instance(MercadoPagoQrService::class, $fake);

        $response = $this->withHeaders([
            'x-signature' => "ts={$ts},v1={$hash}",
            'x-request-id' => $requestId,
        ])->postJson("/mp/webhook?type=payment&data.id={$dataId}", ['type' => 'payment', 'data' => ['id' => $dataId]]);

        $response->assertOk();
        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertSame(1, $invoice->payments()->where('method', PaymentMethod::MercadoPago->value)->count());
    }

    public function test_no_llama_a_la_api_de_mp_si_la_firma_es_invalida(): void
    {
        config(['mercadopago.webhook_secret' => 'shhh']);

        $fake = $this->createMock(MercadoPagoQrService::class);
        $fake->method('webhookSecretFor')->willReturn('shhh');
        $fake->expects($this->never())->method('paymentPaidReference');
        $this->app->instance(MercadoPagoQrService::class, $fake);

        $response = $this->withHeaders([
            'x-signature' => 'ts=1700000000,v1=firmainventada',
            'x-request-id' => 'req-1',
        ])->postJson('/mp/webhook?type=payment&data.id=12345', ['type' => 'payment', 'data' => ['id' => '12345']]);

        $response->assertStatus(401);
    }
}
