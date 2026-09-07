<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySettings;
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
            ->set('punto_venta', '3')
            ->set('condicion_iva', 'monotributista')
            ->call('save');

        $company = CompanySettings::current();
        $this->assertSame('20111111112', $company->cuit);
        $this->assertSame('Mi Empresa S.A.', $company->razon_social);
        $this->assertSame(3, $company->punto_venta);
        $this->assertSame('monotributista', $company->condicion_iva->value);
    }

    public function test_se_puede_guardar_razon_social_y_otros_datos_sin_cargar_el_cuit_todavia(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'active' => true]);

        Livewire::actingAs($admin)
            ->test('company-settings.edit')
            ->set('cuit', '')
            ->set('razon_social', 'Almacén Don José')
            ->set('punto_venta', '1')
            ->set('condicion_iva', 'monotributista')
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
            ->set('punto_venta', '1')
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
            ->set('punto_venta', '1')
            ->set('condicion_iva', 'monotributista')
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
            ->set('punto_venta', '1')
            ->set('condicion_iva', 'monotributista')
            ->set('barcode_scale_enabled', true)
            ->set('barcode_scale_prefix', '20')
            ->set('barcode_scale_code_digits', '5')
            ->set('barcode_scale_weight_digits', '4') // 2+5+4+1 = 12, no 13
            ->call('save')
            ->assertHasErrors('barcode_scale_weight_digits');

        $this->assertFalse(CompanySettings::current()->barcode_scale_enabled);
    }

    public function test_vendedor_cannot_access_company_settings(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);

        $this->actingAs($vendedor)->get(route('company-settings.edit'))->assertForbidden();
    }
}
