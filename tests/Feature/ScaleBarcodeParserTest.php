<?php

namespace Tests\Feature;

use App\Models\CompanySettings;
use App\Support\ScaleBarcodeParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScaleBarcodeParserTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $overrides = []): CompanySettings
    {
        $company = CompanySettings::current();
        $company->update(array_merge([
            'barcode_scale_enabled' => true,
            'barcode_scale_prefix' => '20',
            'barcode_scale_code_digits' => 5,
            'barcode_scale_weight_digits' => 5,
        ], $overrides));

        return $company->fresh();
    }

    public function test_parsea_un_codigo_valido_formato_2_5_5(): void
    {
        // prefijo 20, sku 00023, peso 00350 (350g = 0.35kg), dígito verificador 5.
        $result = ScaleBarcodeParser::parse('2000023003505', $this->settings());

        $this->assertNotNull($result);
        $this->assertSame('00023', $result['sku']);
        $this->assertEqualsWithDelta(0.35, $result['weightKg'], 0.0001);
    }

    public function test_parsea_un_codigo_valido_formato_2_4_6(): void
    {
        // prefijo 21, sku 0099, peso 001250 (1250g = 1.25kg), dígito verificador 7.
        $settings = $this->settings(['barcode_scale_prefix' => '21', 'barcode_scale_code_digits' => 4, 'barcode_scale_weight_digits' => 6]);

        $result = ScaleBarcodeParser::parse('2100990012507', $settings);

        $this->assertNotNull($result);
        $this->assertSame('0099', $result['sku']);
        $this->assertEqualsWithDelta(1.25, $result['weightKg'], 0.0001);
    }

    public function test_rechaza_checksum_invalido(): void
    {
        $this->assertNull(ScaleBarcodeParser::parse('2000023003506', $this->settings()));
    }

    public function test_rechaza_prefijo_que_no_coincide(): void
    {
        $this->assertNull(ScaleBarcodeParser::parse('9900023003505', $this->settings()));
    }

    public function test_rechaza_longitud_incorrecta(): void
    {
        $this->assertNull(ScaleBarcodeParser::parse('20000230035', $this->settings()));
    }

    public function test_rechaza_codigo_con_letras(): void
    {
        $this->assertNull(ScaleBarcodeParser::parse('200002A003505', $this->settings()));
    }

    public function test_devuelve_null_si_la_balanza_esta_desactivada(): void
    {
        $settings = $this->settings(['barcode_scale_enabled' => false]);

        $this->assertNull(ScaleBarcodeParser::parse('2000023003505', $settings));
    }

    public function test_un_sku_normal_de_producto_no_pesado_no_matchea_como_balanza(): void
    {
        // Un SKU cualquiera de un producto normal ("NB-14") no tiene el
        // formato numérico esperado, así que addByBarcode() cae al match
        // exacto de siempre en vez de intentar parsearlo como balanza.
        $this->assertNull(ScaleBarcodeParser::parse('NB-14', $this->settings()));
    }
}
