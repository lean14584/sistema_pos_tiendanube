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

    public function test_vendedor_cannot_access_company_settings(): void
    {
        $vendedor = User::factory()->create(['role' => Role::Vendedor, 'active' => true]);

        $this->actingAs($vendedor)->get(route('company-settings.edit'))->assertForbidden();
    }
}
