<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
use App\Models\Category;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class FacturaTogglesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin, 'active' => true]);
    }

    public function test_deshabilitar_factura_a_la_saca_de_las_opciones_de_la_factura(): void
    {
        CompanySettings::current()->update(['factura_a_habilitada' => false, 'factura_b_habilitada' => true]);

        $options = Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->viewData('tipoComprobanteInternoOptions');

        $this->assertNotContains(TipoComprobanteInterno::FacturaA, $options);
        $this->assertContains(TipoComprobanteInterno::FacturaB, $options);
    }

    public function test_el_default_cae_en_un_tipo_habilitado(): void
    {
        CompanySettings::current()->update(['factura_a_habilitada' => true, 'factura_b_habilitada' => false]);

        Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->assertSet('tipo_comprobante_interno', 'factura_a');
    }

    public function test_no_se_puede_guardar_una_factura_de_un_tipo_deshabilitado(): void
    {
        CompanySettings::current()->update(['factura_a_habilitada' => false, 'factura_b_habilitada' => true]);

        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);

        Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->set('tipo_comprobante_interno', 'factura_a') // deshabilitada
            ->call('addProductItem', $product->id)
            ->call('save')
            ->assertHasErrors('tipo_comprobante_interno');
    }

    public function test_admin_puede_subir_el_certificado_afip(): void
    {
        $tmp = sys_get_temp_dir().'/afip_test_'.uniqid().'.crt';
        config(['afip.cert_path' => $tmp]);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Test'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 365);
        openssl_x509_export($cert, $certPem);

        try {
            Livewire::actingAs($this->admin())
                ->test('company-settings.edit')
                ->set('cuit', '20111111112')
                ->set('razon_social', 'Mi Empresa S.A.')
                ->set('condicion_iva', 'responsable_inscripto')
                ->set('cert', UploadedFile::fake()->createWithContent('certificado.crt', $certPem))
                ->call('save')
                ->assertHasNoErrors();

            $this->assertFileExists($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_rechaza_un_certificado_con_extension_invalida(): void
    {
        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->set('cuit', '20111111112')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('condicion_iva', 'responsable_inscripto')
            ->set('cert', UploadedFile::fake()->create('malo.txt', 1))
            ->call('save')
            ->assertHasErrors('cert');
    }

    public function test_rechaza_un_certificado_con_extension_correcta_pero_contenido_basura(): void
    {
        // La extensión sola no alcanza: antes esto se guardaba igual y el
        // sistema quedaba sin poder facturar A/B recién cuando se lo notaba.
        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->set('cuit', '20111111112')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('condicion_iva', 'responsable_inscripto')
            ->set('cert', UploadedFile::fake()->createWithContent('certificado.crt', "-----BEGIN CERTIFICATE-----\nabc\n-----END CERTIFICATE-----"))
            ->call('save')
            ->assertHasErrors('cert');
    }

    public function test_habilitar_factura_c_la_suma_a_las_opciones_de_la_factura(): void
    {
        CompanySettings::current()->update([
            'condicion_iva' => 'monotributista',
            'factura_a_habilitada' => false,
            'factura_b_habilitada' => false,
            'factura_c_habilitada' => true,
        ]);

        $options = Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->viewData('tipoComprobanteInternoOptions');

        $this->assertContains(TipoComprobanteInterno::FacturaC, $options);
    }

    public function test_no_se_puede_guardar_empresa_monotributista_con_factura_a_habilitada(): void
    {
        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->set('cuit', '20111111112')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('condicion_iva', 'monotributista')
            ->set('factura_a_habilitada', true)
            ->call('save')
            ->assertHasErrors('condicion_iva');

        $this->assertSame('responsable_inscripto', CompanySettings::current()->condicion_iva->value);
    }

    public function test_no_se_puede_guardar_empresa_responsable_inscripto_con_factura_c_habilitada(): void
    {
        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->set('cuit', '20111111112')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('condicion_iva', 'responsable_inscripto')
            ->set('factura_a_habilitada', false)
            ->set('factura_b_habilitada', false)
            ->set('factura_c_habilitada', true)
            ->call('save')
            ->assertHasErrors('condicion_iva');
    }

    public function test_se_puede_guardar_empresa_monotributista_con_solo_factura_c(): void
    {
        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->set('cuit', '20111111112')
            ->set('razon_social', 'Mi Empresa S.A.')
            ->set('condicion_iva', 'monotributista')
            ->set('factura_a_habilitada', false)
            ->set('factura_b_habilitada', false)
            ->set('factura_c_habilitada', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('monotributista', CompanySettings::current()->condicion_iva->value);
        $this->assertTrue(CompanySettings::current()->factura_c_habilitada);
    }

    public function test_elegir_monotributista_habilita_c_y_deshabilita_a_y_b_automaticamente(): void
    {
        CompanySettings::current()->update(['condicion_iva' => 'responsable_inscripto', 'factura_a_habilitada' => true, 'factura_b_habilitada' => true, 'factura_c_habilitada' => false]);

        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->set('condicion_iva', 'monotributista')
            ->assertSet('factura_a_habilitada', false)
            ->assertSet('factura_b_habilitada', false)
            ->assertSet('factura_c_habilitada', true)
            ->set('razon_social', 'Mi Empresa Monotributo')
            ->call('save')
            ->assertHasNoErrors();

        $company = CompanySettings::current();
        $this->assertFalse($company->factura_a_habilitada);
        $this->assertFalse($company->factura_b_habilitada);
        $this->assertTrue($company->factura_c_habilitada);
    }

    public function test_volver_a_responsable_inscripto_restaura_a_y_b_y_apaga_c_automaticamente(): void
    {
        CompanySettings::current()->update(['condicion_iva' => 'monotributista', 'factura_a_habilitada' => false, 'factura_b_habilitada' => false, 'factura_c_habilitada' => true]);

        Livewire::actingAs($this->admin())
            ->test('company-settings.edit')
            ->set('condicion_iva', 'responsable_inscripto')
            ->assertSet('factura_a_habilitada', true)
            ->assertSet('factura_b_habilitada', true)
            ->assertSet('factura_c_habilitada', false);
    }

    public function test_selector_de_iva_por_item_se_oculta_para_empresa_monotributista(): void
    {
        CompanySettings::current()->update([
            'condicion_iva' => 'monotributista',
            'factura_a_habilitada' => false,
            'factura_b_habilitada' => false,
            'factura_c_habilitada' => true,
        ]);
        $client = Client::create(['name' => 'Cliente 1', 'email' => 'c1@test.com']);
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'stock' => 10]);

        Livewire::actingAs($this->admin())
            ->test('invoices.create')
            ->set('client_id', (string) $client->id)
            ->call('addProductItem', $product->id)
            ->assertSet('items.0.iva_rate', '0')
            ->assertSee('IVA incluido');
    }

    public function test_venta_rapida_no_ofrece_factura_a_ni_b_para_empresa_monotributista(): void
    {
        CompanySettings::current()->update([
            'condicion_iva' => 'monotributista',
            'factura_a_habilitada' => false,
            'factura_b_habilitada' => false,
            'factura_c_habilitada' => true,
        ]);

        $options = Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->viewData('tipoComprobanteInternoOptions');

        $this->assertNotContains(TipoComprobanteInterno::FacturaA, $options);
        $this->assertNotContains(TipoComprobanteInterno::FacturaB, $options);
        $this->assertContains(TipoComprobanteInterno::FacturaC, $options);
    }

    public function test_selector_de_iva_se_oculta_al_crear_producto_para_empresa_monotributista(): void
    {
        CompanySettings::current()->update([
            'condicion_iva' => 'monotributista',
            'factura_a_habilitada' => false,
            'factura_b_habilitada' => false,
            'factura_c_habilitada' => true,
        ]);
        $category = Category::create(['name' => 'Bebidas']);

        Livewire::actingAs($this->admin())
            ->test('products.create')
            ->assertDontSeeHtml('Alícuota de IVA')
            ->set('name', 'Agua Mineral')
            ->set('price', '500')
            ->set('stock', '10')
            ->set('category_id', (string) $category->id)
            ->call('save')
            ->assertRedirect(route('products.index'));

        $this->assertEquals('0.00', Product::firstWhere('name', 'Agua Mineral')->iva_rate);
    }

    public function test_selector_de_iva_se_oculta_al_editar_producto_para_empresa_monotributista(): void
    {
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'iva_rate' => 21, 'stock' => 5]);

        CompanySettings::current()->update([
            'condicion_iva' => 'monotributista',
            'factura_a_habilitada' => false,
            'factura_b_habilitada' => false,
            'factura_c_habilitada' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test('products.edit', ['product' => $product])
            ->assertDontSeeHtml('Alícuota de IVA')
            ->call('save');

        $this->assertEquals('0.00', $product->fresh()->iva_rate);
    }

    public function test_venta_rapida_no_cobra_iva_para_empresa_monotributista_aunque_el_producto_tenga_alicuota_vieja_cargada(): void
    {
        // Producto cargado ANTES de que la empresa pasara a Monotributista
        // (o antes del fix de Products\Create/Edit): todavía tiene un iva_rate
        // real guardado. La venta rápida no debe cobrarlo igual.
        $product = Product::create(['name' => 'Notebook', 'price' => 1000, 'iva_rate' => 21, 'stock' => 5]);

        CompanySettings::current()->update([
            'condicion_iva' => 'monotributista',
            'factura_a_habilitada' => false,
            'factura_b_habilitada' => false,
            'factura_c_habilitada' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test('pos.index')
            ->call('addProduct', $product->id)
            ->assertSet('cart.0.iva_rate', '0');
    }
}
