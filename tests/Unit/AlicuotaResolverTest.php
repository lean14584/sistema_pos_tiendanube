<?php

namespace Tests\Unit;

use App\Support\Afip\AlicuotaResolver;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AlicuotaResolverTest extends TestCase
{
    /**
     * Tabla "Alícuotas del IVA" del Libro de IVA Digital (RG 4597), tal
     * como la publica AFIP. No son valores inventados: confirmar cualquier
     * cambio contra Libro-IVA-Digital-Tablas-del-Sistema.pdf.
     */
    #[DataProvider('tablaAlicuotas')]
    public function test_resuelve_el_codigo_afip_de_cada_alicuota(float $tasa, string $esperado): void
    {
        $this->assertSame($esperado, AlicuotaResolver::codigo($tasa));
    }

    public static function tablaAlicuotas(): array
    {
        return [
            '0%' => [0.0, '0003'],
            '2.5%' => [2.5, '0009'],
            '5%' => [5.0, '0008'],
            '10.5%' => [10.5, '0004'],
            '21%' => [21.0, '0005'],
            '27%' => [27.0, '0006'],
        ];
    }

    public function test_una_alicuota_desconocida_explota_en_vez_de_generar_un_archivo_invalido(): void
    {
        $this->expectException(DomainException::class);

        AlicuotaResolver::codigo(15.0);
    }
}
