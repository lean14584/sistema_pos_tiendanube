<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_company_settings(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('cuit', '20111111112')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('condicion_iva', 'monotributista')
            ->set('factura_a_habilitada', false)
            ->set('factura_b_habilitada', false)
            ->call('save')
            ->assertHasNoErrors();

        $company = CompanySettings::current();
        $this->assertSame('20111111112', $company->cuit);
        $this->assertSame('Mi Empresa S.A.', $company->razon_social);
        $this->assertSame('monotributista', $company->condicion_iva->value);
    }

    public function test_se_puede_guardar_razon_social_y_otros_datos_sin_cargar_el_cuit_todavia(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('cuit', '')
            ->set('razon_social', 'Almacén Don José')
            ->set('condicion_iva', 'monotributista')
            ->set('factura_a_habilitada', false)
            ->set('factura_b_habilitada', false)
            ->call('save')
            ->assertHasNoErrors();

        $company = CompanySettings::current();
        $this->assertSame('', $company->cuit);
        $this->assertSame('Almacén Don José', $company->razon_social);
    }

    public function test_un_cuit_invalido_igual_se_rechaza_aunque_ya_no_sea_obligatorio(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('cuit', '123')
            ->set('razon_social', 'Almacén Don José')
            ->set('condicion_iva', 'monotributista')
            ->call('save')
            ->assertHasErrors(['cuit']);
    }

    public function test_admin_puede_configurar_la_balanza_con_formato_2_5_5(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('cuit', '20111111112')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('condicion_iva', 'monotributista')
            ->set('factura_a_habilitada', false)
            ->set('factura_b_habilitada', false)
            ->set('barcode_scale_enabled', true)
            ->set('barcode_scale_prefix', '20')
            ->set('barcode_scale_code_digits', '5')
            ->set('barcode_scale_weight_digits', '5')
            ->call('save')
            ->assertHasNoErrors();

        $company = CompanySettings::current();
        $this->assertTrue($company->barcode_scale_enabled);
        $this->assertSame('20', $company->barcode_scale_prefix);
        $this->assertSame(5, $company->barcode_scale_code_digits);
        $this->assertSame(5, $company->barcode_scale_weight_digits);
    }

    public function test_configuracion_de_balanza_que_no_suma_13_digitos_es_rechazada(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('cuit', '20111111112')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('condicion_iva', 'monotributista')
            ->set('factura_a_habilitada', false)
            ->set('factura_b_habilitada', false)
            ->set('barcode_scale_enabled', true)
            ->set('barcode_scale_prefix', '20')
            ->set('barcode_scale_code_digits', '5')
            ->set('barcode_scale_weight_digits', '4') // 2+5+4+1 = 12, no 13
            ->call('save')
            ->assertHasErrors('barcode_scale_weight_digits');

        $this->assertFalse(CompanySettings::current()->barcode_scale_enabled);
    }

    public function test_admin_puede_configurar_descuentos_por_medio_de_pago(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('descuento_efectivo_pct', '15')
            ->set('descuento_transferencia_pct', '10')
            ->call('save')
            ->assertHasNoErrors();

        $company = CompanySettings::current();
        $this->assertEquals(15, $company->descuento_efectivo_pct);
        $this->assertEquals(10, $company->descuento_transferencia_pct);
    }

    public function test_descuento_por_medio_de_pago_fuera_de_rango_es_rechazado(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('descuento_efectivo_pct', '150')
            ->call('save')
            ->assertHasErrors('descuento_efectivo_pct');
    }

    public function test_vendedor_cannot_access_company_settings(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);

        $this->actingAs($vendedor)->get(route('company-settings.edit'))->assertForbidden();
    }

    public function test_puede_guardar_un_color_de_marca_valido(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('theme_primary_color', '#FF5733')
            ->set('theme_secondary_color', '#00AABB')
            ->call('save')
            ->assertHasNoErrors();

        $company = CompanySettings::current();
        // Se normaliza a minúsculas al guardar.
        $this->assertSame('#ff5733', $company->theme_primary_color);
        $this->assertSame('#00aabb', $company->theme_secondary_color);
    }

    public function test_un_color_de_marca_con_formato_invalido_es_rechazado(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('theme_primary_color', 'no-es-un-hex')
            ->call('save')
            ->assertHasErrors('theme_primary_color');

        $this->assertNull(CompanySettings::current()->theme_primary_color);
    }

    public function test_dejar_el_color_de_marca_en_blanco_vuelve_a_la_paleta_de_fabrica(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        CompanySettings::current()->update(['theme_primary_color' => '#ff5733']);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('theme_primary_color', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(CompanySettings::current()->theme_primary_color);
    }

    /**
     * Sin multisucursal, los datos de la única sucursal (nombre, razón
     * social, puntos de venta, Mercado Pago) se fusionan en esta misma
     * pantalla — ver CompanySettings\Edit::mount()/save(). sucursales.edit
     * sigue existiendo igual (ver SucursalesTest), solo deja de ser el único
     * lugar donde cargar esto.
     */
    public function test_sin_multisucursal_se_puede_editar_la_sucursal_desde_datos_de_la_empresa(): void
    {
        config(['features.multisucursal' => false]);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $unica = tap(Sucursal::sole())->update(['razon_social' => 'Mi Empresa SRL']);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('sucursal_name', 'Casa Central')
            ->set('mp_access_token', 'APP_USR-secreto')
            ->call('save')
            ->assertHasNoErrors();

        $unica->refresh();
        $this->assertSame('Casa Central', $unica->name);
        $this->assertSame('APP_USR-secreto', $unica->mercadoPagoConfig->access_token);
    }

    public function test_con_multisucursal_prendido_no_se_valida_ni_se_pide_el_nombre_de_sucursal(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_sin_multisucursal_no_se_puede_dejar_el_nombre_de_sucursal_vacio(): void
    {
        config(['features.multisucursal' => false]);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('sucursal_name', '')
            ->call('save')
            ->assertHasErrors(['sucursal_name']);
    }

    public function test_sin_multisucursal_se_puede_agregar_un_punto_de_venta_desde_datos_de_la_empresa(): void
    {
        config(['features.multisucursal' => false]);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $unica = Sucursal::sole();

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('nuevoPuntoVentaNumero', '6')
            ->set('nuevoPuntoVentaNombre', 'Online')
            ->call('agregarPuntoVenta')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('puntos_venta', ['sucursal_id' => $unica->id, 'numero' => 6, 'nombre' => 'Online']);
    }

    public function test_sin_multisucursal_no_se_puede_borrar_un_punto_de_venta_que_ya_facturo(): void
    {
        config(['features.multisucursal' => false]);
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);
        $unica = Sucursal::sole();
        $pv = $unica->puntosVenta()->create(['numero' => 5, 'active' => true]);
        $unica->puntosVenta()->create(['numero' => 6, 'active' => true]);

        Invoice::create([
            'number' => '0005-00000001',
            'client_id' => Client::consumidorFinal()->id,
            'sucursal_id' => $unica->id,
            'punto_venta' => 5,
            'issue_date' => now(),
            'due_date' => now(),
            'tax_rate' => 0,
            'status' => 'draft',
        ]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->call('eliminarPuntoVenta', $pv->id)
            ->assertHasErrors('puntosVenta');

        $this->assertDatabaseHas('puntos_venta', ['id' => $pv->id]);
    }
}
