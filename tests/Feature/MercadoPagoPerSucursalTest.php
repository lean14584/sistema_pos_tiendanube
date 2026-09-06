<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Sucursal;
use App\Models\SucursalMercadoPagoConfig;
use App\Models\User;
use App\Services\MercadoPago\MercadoPagoQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MercadoPagoPerSucursalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_sin_config_propia_una_sucursal_usa_el_token_global(): void
    {
        config(['mercadopago.access_token' => 'GLOBAL-TOKEN']);
        $sucursal = Sucursal::sole();

        $service = app(MercadoPagoQrService::class);

        $this->assertTrue($service->isConfigured($sucursal->id));
    }

    public function test_una_sucursal_sin_token_propio_ni_global_no_esta_configurada(): void
    {
        config(['mercadopago.access_token' => null]);
        $sucursal = Sucursal::sole();

        $this->assertFalse(app(MercadoPagoQrService::class)->isConfigured($sucursal->id));
        $this->assertFalse(app(MercadoPagoQrService::class)->isConfigured());
    }

    public function test_una_sucursal_con_token_propio_lo_usa_en_vez_del_global(): void
    {
        config(['mercadopago.access_token' => 'GLOBAL-TOKEN']);
        $sucursal = Sucursal::sole();
        SucursalMercadoPagoConfig::create(['sucursal_id' => $sucursal->id, 'access_token' => 'SUCURSAL-TOKEN']);

        Http::fake([
            '*/users/me' => Http::sequence()
                ->push(['id' => 999], 200), // debería pedirse con el token de la sucursal
        ]);

        app(MercadoPagoQrService::class)->collectorId($sucursal->id);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer SUCURSAL-TOKEN'));
    }

    public function test_collector_id_se_persiste_en_la_config_de_la_sucursal(): void
    {
        $sucursal = Sucursal::sole();
        SucursalMercadoPagoConfig::create(['sucursal_id' => $sucursal->id, 'access_token' => 'SUCURSAL-TOKEN']);

        Http::fake(['*/users/me' => Http::response(['id' => 4242], 200)]);

        $id = app(MercadoPagoQrService::class)->collectorId($sucursal->id);

        $this->assertSame(4242, $id);
        $this->assertSame(4242, $sucursal->mercadoPagoConfig->fresh()->collector_id);
    }

    public function test_resuelve_la_sucursal_a_partir_del_collector_id_del_webhook(): void
    {
        $principal = Sucursal::sole();
        $norte = Sucursal::create(['name' => 'Norte', 'razon_social' => 'Mi Empresa', 'punto_venta' => 2]);
        SucursalMercadoPagoConfig::create(['sucursal_id' => $norte->id, 'access_token' => 'TOKEN-NORTE', 'collector_id' => 555]);

        $resuelto = app(MercadoPagoQrService::class)->resolveSucursalByCollectorId(555);

        $this->assertSame($norte->id, $resuelto);
        $this->assertNull(app(MercadoPagoQrService::class)->resolveSucursalByCollectorId(999));
    }

    public function test_admin_guarda_credenciales_de_mp_para_una_sucursal(): void
    {
        $sucursal = Sucursal::sole();

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $sucursal])
            // La "Principal" auto-creada por la migración de product_stocks
            // queda con razon_social vacía (ver create_company_settings_table);
            // hace falta completarla para que el form valide.
            ->set('razon_social', 'Mi Empresa')
            ->set('mp_access_token', 'APP_USR-secreto')
            ->set('mp_store_external_id', 'SUC-01')
            ->set('mp_pos_external_id', 'CAJA-01')
            ->call('save')
            ->assertHasNoErrors();

        $config = SucursalMercadoPagoConfig::where('sucursal_id', $sucursal->id)->sole();
        $this->assertSame('APP_USR-secreto', $config->access_token);
        $this->assertSame('SUC-01', $config->store_external_id);
        $this->assertSame('CAJA-01', $config->pos_external_id);
    }

    public function test_dejar_el_token_vacio_al_editar_no_borra_el_ya_guardado(): void
    {
        $sucursal = Sucursal::sole();
        SucursalMercadoPagoConfig::create(['sucursal_id' => $sucursal->id, 'access_token' => 'TOKEN-ORIGINAL', 'store_name' => 'Original']);

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $sucursal])
            ->set('razon_social', 'Mi Empresa')
            ->set('mp_store_name', 'Nombre Actualizado')
            // mp_access_token queda vacío a propósito.
            ->call('save')
            ->assertHasNoErrors();

        $config = SucursalMercadoPagoConfig::where('sucursal_id', $sucursal->id)->sole();
        $this->assertSame('TOKEN-ORIGINAL', $config->access_token);
        $this->assertSame('Nombre Actualizado', $config->store_name);
    }

    public function test_el_token_nunca_se_precarga_en_el_formulario(): void
    {
        $sucursal = Sucursal::sole();
        SucursalMercadoPagoConfig::create(['sucursal_id' => $sucursal->id, 'access_token' => 'TOKEN-SECRETO']);

        Livewire::actingAs($this->admin())
            ->test('sucursales.edit', ['sucursal' => $sucursal])
            ->assertSet('mp_access_token', '')
            ->assertSee('(cargado)')
            ->assertDontSee('TOKEN-SECRETO');
    }

    public function test_encargado_no_puede_ver_la_pantalla_de_sucursales_para_tocar_mp(): void
    {
        $sucursal = Sucursal::sole();
        $encargado = User::factory()->create(['role' => Role::Encargado, 'active' => true, 'sucursal_id' => $sucursal->id]);

        $this->actingAs($encargado)->get(route('sucursales.edit', $sucursal))->assertForbidden();
    }
}
