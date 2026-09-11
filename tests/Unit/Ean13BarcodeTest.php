<?php

namespace Tests\Unit;

use App\Support\Ean13Barcode;
use Tests\TestCase;

class Ean13BarcodeTest extends TestCase
{
    public function test_genera_un_png_valido(): void
    {
        $png = Ean13Barcode::render('0000000098762');

        $info = getimagesizefromstring($png);

        $this->assertNotFalse($info);
        $this->assertSame('image/png', $info['mime']);
        $this->assertGreaterThan(0, $info[0]);
        $this->assertGreaterThan(0, $info[1]);
    }

    public function test_data_uri_tiene_el_prefijo_correcto(): void
    {
        $uri = Ean13Barcode::dataUri('0000000098762');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }
}
