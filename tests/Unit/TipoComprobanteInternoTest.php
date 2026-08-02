<?php

namespace Tests\Unit;

use App\Enums\TipoComprobante;
use App\Enums\TipoComprobanteInterno;
use PHPUnit\Framework\TestCase;

class TipoComprobanteInternoTest extends TestCase
{
    public function test_solo_devolucion_repone_stock(): void
    {
        $this->assertSame(-1, TipoComprobanteInterno::RemitoX->stockSign());
        $this->assertSame(-1, TipoComprobanteInterno::FacturaB->stockSign());
        $this->assertSame(-1, TipoComprobanteInterno::FacturaA->stockSign());
        $this->assertSame(1, TipoComprobanteInterno::Devolucion->stockSign());
    }

    public function test_solo_factura_a_y_b_son_fiscales(): void
    {
        $this->assertFalse(TipoComprobanteInterno::RemitoX->esFiscal());
        $this->assertTrue(TipoComprobanteInterno::FacturaB->esFiscal());
        $this->assertTrue(TipoComprobanteInterno::FacturaA->esFiscal());
        $this->assertFalse(TipoComprobanteInterno::Devolucion->esFiscal());
    }

    public function test_mapeo_a_tipo_comprobante_afip(): void
    {
        $this->assertSame(TipoComprobante::FacturaA, TipoComprobanteInterno::FacturaA->aTipoComprobante());
        $this->assertSame(TipoComprobante::FacturaB, TipoComprobanteInterno::FacturaB->aTipoComprobante());
        $this->assertNull(TipoComprobanteInterno::RemitoX->aTipoComprobante());
        $this->assertNull(TipoComprobanteInterno::Devolucion->aTipoComprobante());
    }
}
