<?php

namespace Tests\Unit;

use App\Support\Ean13;
use Tests\TestCase;

class Ean13Test extends TestCase
{
    public function test_completa_un_sku_corto_con_ceros_y_digito_verificador(): void
    {
        $this->assertSame('0000000098762', Ean13::fromSku('9876'));
    }

    public function test_usa_un_sku_que_ya_es_un_ean13_real_tal_cual(): void
    {
        // 4006381333931: EAN13 real (Kellogg's), checksum válido.
        $this->assertSame('4006381333931', Ean13::fromSku('4006381333931'));
    }

    public function test_rechaza_un_sku_de_13_digitos_con_checksum_invalido(): void
    {
        // Mismo código de arriba con el último dígito cambiado a propósito.
        $this->assertNull(Ean13::fromSku('4006381333930'));
    }

    public function test_rechaza_sku_no_numerico(): void
    {
        $this->assertNull(Ean13::fromSku('ABC123'));
    }

    public function test_rechaza_sku_de_mas_de_12_digitos_que_no_sea_13(): void
    {
        $this->assertNull(Ean13::fromSku('1234567890123456'));
    }

    public function test_rechaza_sku_vacio_o_nulo(): void
    {
        $this->assertNull(Ean13::fromSku(null));
        $this->assertNull(Ean13::fromSku(''));
    }

    public function test_recalcula_un_sku_paddeado_a_mano_a_13_digitos_que_no_es_un_ean13_valido(): void
    {
        // Sku cargado a mano como "0000000000001" (12 ceros + un 1): no es
        // un GTIN real (checksum no da), pero tampoco hay que dejarlo sin
        // código de barras — se recorta a los dígitos significativos ("1")
        // y se recalcula el dígito verificador, igual que con un sku corto.
        $this->assertSame('0000000000017', Ean13::fromSku('0000000000001'));
    }

    public function test_strip_padding_recupera_el_sku_original(): void
    {
        $this->assertSame('9876', Ean13::stripPadding('0000000098762'));
    }

    public function test_strip_padding_devuelve_null_si_el_checksum_no_da(): void
    {
        $this->assertNull(Ean13::stripPadding('0000000098763'));
    }

    public function test_is_valid(): void
    {
        $this->assertTrue(Ean13::isValid('0000000098762'));
        $this->assertTrue(Ean13::isValid('4006381333931'));
        $this->assertFalse(Ean13::isValid('4006381333930'));
        $this->assertFalse(Ean13::isValid('123'));
    }
}
