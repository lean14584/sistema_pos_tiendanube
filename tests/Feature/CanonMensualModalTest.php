<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\CanonMensualModal;
use App\Models\CanonPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CanonMensualModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Config::set('services.mercadopago_jjsoftware.access_token', 'TEST-TOKEN');
        Config::set('services.mercadopago_jjsoftware.monto_canon', 9000);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    private function vendedor(): User
    {
        return User::factory()->create(['role' => Role::Vendedor, 'active' => true]);
    }

    public function test_no_muestra_nada_para_usuario_no_administrador(): void
    {
        $this->travelTo(now()->setDay(10));

        Livewire::actingAs($this->vendedor())
            ->test(CanonMensualModal::class)
            ->assertSet('mostrarModal', false);
    }

    public function test_no_muestra_el_modal_antes_del_dia_5(): void
    {
        $this->travelTo(now()->setDay(3));

        Livewire::actingAs($this->admin())
            ->test(CanonMensualModal::class)
            ->assertSet('mostrarModal', false);
    }

    public function test_muestra_el_modal_al_administrador_desde_el_dia_5_sin_pago_registrado(): void
    {
        $this->travelTo(now()->setDay(6));

        Http::fake([
            'api.mercadopago.com/checkout/preferences*' => Http::response(['init_point' => 'https://mp.example/checkout/pref-123'], 200),
        ]);

        Livewire::actingAs($this->admin())
            ->test(CanonMensualModal::class)
            ->assertSet('mostrarModal', true)
            ->assertSet('initPoint', 'https://mp.example/checkout/pref-123');
    }

    public function test_no_muestra_el_modal_si_ya_hay_pago_registrado_este_mes(): void
    {
        $this->travelTo(now()->setDay(20));

        $admin = $this->admin();

        CanonPago::create([
            'user_id' => $admin->id,
            'fecha_pago' => now()->toDateString(),
            'mp_payment_id' => 'pago-ya-existente',
            'monto' => 9000,
            'mes' => now()->format('m'),
            'anio' => now()->format('Y'),
        ]);

        Livewire::actingAs($admin)
            ->test(CanonMensualModal::class)
            ->assertSet('mostrarModal', false);
    }

    public function test_registra_el_pago_al_volver_de_mercado_pago_con_estado_aprobado(): void
    {
        $this->travelTo(now()->setDay(10));

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 555444333,
                'status' => 'approved',
                'transaction_amount' => 9000,
                'external_reference' => 'localhost-canon-'.now()->format('Y-m'),
            ], 200),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard', [
            'collection_id' => '555444333',
            'collection_status' => 'approved',
        ]));

        $this->assertDatabaseHas('canon_pagos', [
            'mp_payment_id' => '555444333',
            'user_id' => $admin->id,
            'mes' => now()->format('m'),
            'anio' => now()->format('Y'),
        ]);
    }

    public function test_no_registra_un_pago_aprobado_de_otra_instalacion_o_mes(): void
    {
        // El mismo MP_JJSOFTWARE_ACCESS_TOKEN se usa para todos los clientes
        // de jjsoftware: un collection_id real pero de OTRO sitio/mes no
        // tiene que poder marcar el canon de este sitio como pagado.
        $this->travelTo(now()->setDay(10));

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 111222333,
                'status' => 'approved',
                'transaction_amount' => 9000,
                'external_reference' => 'otro-cliente.jjsoftware.click-canon-2026-01',
            ], 200),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard', [
            'collection_id' => '111222333',
            'collection_status' => 'approved',
        ]));

        $this->assertDatabaseMissing('canon_pagos', [
            'mp_payment_id' => '111222333',
        ]);
    }

    public function test_no_registra_un_pago_aprobado_por_menos_del_monto_del_canon(): void
    {
        $this->travelTo(now()->setDay(10));

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 777888999,
                'status' => 'approved',
                'transaction_amount' => 100,
                'external_reference' => 'localhost-canon-'.now()->format('Y-m'),
            ], 200),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard', [
            'collection_id' => '777888999',
            'collection_status' => 'approved',
        ]));

        $this->assertDatabaseMissing('canon_pagos', [
            'mp_payment_id' => '777888999',
        ]);
    }

    public function test_no_revienta_si_mercado_pago_esta_caido_al_crear_la_preferencia(): void
    {
        $this->travelTo(now()->setDay(10));

        Http::fake([
            'api.mercadopago.com/checkout/preferences*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
        ]);

        Livewire::actingAs($this->admin())
            ->test(CanonMensualModal::class)
            ->assertSet('mostrarModal', false);
    }

    public function test_no_registra_el_pago_si_mercado_pago_no_lo_aprobo(): void
    {
        $this->travelTo(now()->setDay(10));

        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response([
                'id' => 999888777,
                'status' => 'rejected',
            ], 200),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard', [
            'collection_id' => '999888777',
            'collection_status' => 'rejected',
        ]));

        $this->assertDatabaseMissing('canon_pagos', [
            'mp_payment_id' => '999888777',
        ]);
    }

    public function test_no_revienta_si_faltan_credenciales_de_mercado_pago(): void
    {
        $this->travelTo(now()->setDay(10));

        Config::set('services.mercadopago_jjsoftware.access_token', null);

        Livewire::actingAs($this->admin())
            ->test(CanonMensualModal::class)
            ->assertSet('mostrarModal', false);
    }
}
