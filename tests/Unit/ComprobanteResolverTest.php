<?php

namespace Tests\Unit;

use App\Enums\CondicionIva;
use App\Enums\TipoComprobante;
use App\Exceptions\Afip\AfipValidationException;
use App\Support\Afip\ComprobanteResolver;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComprobanteResolverTest extends TestCase
{
    /**
     * Matriz completa emisor x receptor. Este es el guardrail contra
     * repetir el bug de ArcaService.php: CbteTipo/CondicionIVAReceptorId
     * hardcodeados en vez de derivados de ambas condiciones de IVA.
     */
    #[DataProvider('matrizComprobantes')]
    public function test_resuelve_tipo_de_comprobante_segun_emisor_y_receptor(
        CondicionIva $emisor,
        CondicionIva $receptor,
        TipoComprobante $esperado,
    ): void {
        $this->assertSame($esperado, ComprobanteResolver::tipoComprobante($emisor, $receptor));
    }

    public static function matrizComprobantes(): array
    {
        return [
            'RI emisor + RI receptor = Factura A' => [
                CondicionIva::ResponsableInscripto, CondicionIva::ResponsableInscripto, TipoComprobante::FacturaA,
            ],
            'RI emisor + Monotributista receptor = Factura B' => [
                CondicionIva::ResponsableInscripto, CondicionIva::Monotributista, TipoComprobante::FacturaB,
            ],
            'RI emisor + Exento receptor = Factura B' => [
                CondicionIva::ResponsableInscripto, CondicionIva::Exento, TipoComprobante::FacturaB,
            ],
            'RI emisor + Consumidor Final receptor = Factura B' => [
                CondicionIva::ResponsableInscripto, CondicionIva::ConsumidorFinal, TipoComprobante::FacturaB,
            ],
            'Monotributista emisor + RI receptor = Factura C' => [
                CondicionIva::Monotributista, CondicionIva::ResponsableInscripto, TipoComprobante::FacturaC,
            ],
            'Monotributista emisor + Monotributista receptor = Factura C' => [
                CondicionIva::Monotributista, CondicionIva::Monotributista, TipoComprobante::FacturaC,
            ],
            'Monotributista emisor + Exento receptor = Factura C' => [
                CondicionIva::Monotributista, CondicionIva::Exento, TipoComprobante::FacturaC,
            ],
            'Monotributista emisor + Consumidor Final receptor = Factura C' => [
                CondicionIva::Monotributista, CondicionIva::ConsumidorFinal, TipoComprobante::FacturaC,
            ],
            'Exento emisor + RI receptor = Factura C' => [
                CondicionIva::Exento, CondicionIva::ResponsableInscripto, TipoComprobante::FacturaC,
            ],
            'Exento emisor + Monotributista receptor = Factura C' => [
                CondicionIva::Exento, CondicionIva::Monotributista, TipoComprobante::FacturaC,
            ],
            'Exento emisor + Exento receptor = Factura C' => [
                CondicionIva::Exento, CondicionIva::Exento, TipoComprobante::FacturaC,
            ],
            'Exento emisor + Consumidor Final receptor = Factura C' => [
                CondicionIva::Exento, CondicionIva::ConsumidorFinal, TipoComprobante::FacturaC,
            ],
        ];
    }

    public function test_la_empresa_no_puede_emitir_como_consumidor_final(): void
    {
        $this->expectException(DomainException::class);

        ComprobanteResolver::tipoComprobante(CondicionIva::ConsumidorFinal, CondicionIva::ResponsableInscripto);
    }

    #[DataProvider('catalogoCondicionIvaReceptor')]
    public function test_resuelve_condicion_iva_receptor_id(CondicionIva $receptor, int $esperado): void
    {
        $this->assertSame($esperado, ComprobanteResolver::condicionIvaReceptorId($receptor));
    }

    public static function catalogoCondicionIvaReceptor(): array
    {
        return [
            [CondicionIva::ResponsableInscripto, 1],
            [CondicionIva::Exento, 4],
            [CondicionIva::ConsumidorFinal, 5],
            [CondicionIva::Monotributista, 6],
        ];
    }

    /**
     * Guardrail para el switch de Invoices/Create: forzar Factura A o B
     * solo es legal si la empresa emisora es Responsable Inscripto.
     */
    public function test_responsable_inscripto_puede_forzar_factura_a_y_b(): void
    {
        ComprobanteResolver::assertEmisorPuedeForzar(CondicionIva::ResponsableInscripto, TipoComprobante::FacturaA);
        ComprobanteResolver::assertEmisorPuedeForzar(CondicionIva::ResponsableInscripto, TipoComprobante::FacturaB);

        $this->addToAssertionCount(2);
    }

    #[DataProvider('emisoresQueNoPuedenForzarAoB')]
    public function test_emisores_no_responsable_inscripto_no_pueden_forzar_factura_a_ni_b(
        CondicionIva $emisor,
        TipoComprobante $tipo,
    ): void {
        $this->expectException(AfipValidationException::class);

        ComprobanteResolver::assertEmisorPuedeForzar($emisor, $tipo);
    }

    public static function emisoresQueNoPuedenForzarAoB(): array
    {
        return [
            'Monotributista no puede Factura A' => [CondicionIva::Monotributista, TipoComprobante::FacturaA],
            'Monotributista no puede Factura B' => [CondicionIva::Monotributista, TipoComprobante::FacturaB],
            'Exento no puede Factura A' => [CondicionIva::Exento, TipoComprobante::FacturaA],
            'Exento no puede Factura B' => [CondicionIva::Exento, TipoComprobante::FacturaB],
            'Consumidor Final no puede Factura A' => [CondicionIva::ConsumidorFinal, TipoComprobante::FacturaA],
            'Consumidor Final no puede Factura B' => [CondicionIva::ConsumidorFinal, TipoComprobante::FacturaB],
        ];
    }
}
