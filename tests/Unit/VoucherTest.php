<?php

namespace Tests\Unit;

use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_emitir_genera_un_codigo_unico_de_8_caracteres(): void
    {
        $voucher = Voucher::emitir(500);

        $this->assertSame(8, strlen($voucher->code));
        $this->assertSame('500.00', (string) $voucher->amount);
        $this->assertSame('500.00', (string) $voucher->balance);
    }

    public function test_porCodigo_encuentra_sin_importar_mayusculas_ni_espacios(): void
    {
        $voucher = Voucher::emitir(300);

        $this->assertSame($voucher->id, Voucher::porCodigo(' '.strtolower($voucher->code).' ')?->id);
    }

    public function test_porCodigo_devuelve_null_si_no_existe(): void
    {
        $this->assertNull(Voucher::porCodigo('NOEXISTE'));
    }

    public function test_redeem_descuenta_del_saldo(): void
    {
        $voucher = Voucher::emitir(1000);

        $voucher->redeem(400);

        $this->assertSame('600.00', (string) $voucher->fresh()->balance);
    }

    public function test_redeem_permite_canjes_parciales_sucesivos(): void
    {
        $voucher = Voucher::emitir(1000);

        $voucher->redeem(300);
        $voucher->fresh()->redeem(300);

        $this->assertSame('400.00', (string) $voucher->fresh()->balance);
    }

    public function test_redeem_rechaza_un_monto_mayor_al_saldo_disponible(): void
    {
        $voucher = Voucher::emitir(200);

        $this->expectException(RuntimeException::class);

        $voucher->redeem(200.01);
    }

    public function test_redeem_rechaza_montos_no_positivos(): void
    {
        $voucher = Voucher::emitir(200);

        $this->expectException(RuntimeException::class);

        $voucher->redeem(0);
    }

    public function test_esta_disponible_es_falso_cuando_el_saldo_llega_a_cero(): void
    {
        $voucher = Voucher::emitir(150);

        $voucher->redeem(150);

        $this->assertFalse($voucher->fresh()->estaDisponible());
    }
}
