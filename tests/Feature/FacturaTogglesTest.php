<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoComprobanteInterno;
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
                ->set('punto_venta', '1')
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
            ->set('punto_venta', '1')
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
            ->set('punto_venta', '1')
            ->set('condicion_iva', 'responsable_inscripto')
            ->set('cert', UploadedFile::fake()->createWithContent('certificado.crt', "-----BEGIN CERTIFICATE-----\nabc\n-----END CERTIFICATE-----"))
            ->call('save')
            ->assertHasErrors('cert');
    }
}
