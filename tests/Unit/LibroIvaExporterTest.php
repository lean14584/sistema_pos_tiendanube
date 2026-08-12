<?php

namespace Tests\Unit;

use App\Enums\TipoComprobante;
use App\Support\LibroIva\LibroIvaAlicuota;
use App\Support\LibroIva\LibroIvaExporter;
use App\Support\LibroIva\LibroIvaRow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Verifica que los 4 archivos generados respeten al dígito el diseño de
 * registro oficial de AFIP para el Libro de IVA Digital (RG 4597) — ver
 * Libro-IVA-Digital-Tablas-del-Sistema.pdf y
 * libro-iva-digital-diseno-registros.pdf. Un desfasaje de una sola columna
 * hace que AFIP rechace el archivo completo, así que se verifica campo por
 * campo (por posición), no solo el largo total de la línea.
 */
class LibroIvaExporterTest extends TestCase
{
    /** Campo Nro. => valor, según el ancho de cada diseño de registro. */
    private function fields(string $line, array $widths): array
    {
        $out = [];
        $offset = 0;
        foreach ($widths as $i => $width) {
            $out[$i + 1] = substr($line, $offset, $width);
            $offset += $width;
        }

        return $out;
    }

    private function gravadaRow(): LibroIvaRow
    {
        return new LibroIvaRow(
            fecha: Carbon::parse('2026-07-15'),
            tipoComprobante: TipoComprobante::FacturaB,
            puntoVenta: 1,
            numeroComprobante: 45,
            codigoDocumento: 80,
            numeroDocumento: '20111111112',
            denominacion: 'Cliente Test',
            importeTotal: 1210.00,
            importeExento: 0.0,
            alicuotas: [new LibroIvaAlicuota(21.0, 1000.00, 210.00)],
            codigoOperacion: '',
        );
    }

    private function exentaRow(): LibroIvaRow
    {
        return new LibroIvaRow(
            fecha: Carbon::parse('2026-07-20'),
            tipoComprobante: TipoComprobante::FacturaC,
            puntoVenta: 2,
            numeroComprobante: 7,
            codigoDocumento: 99,
            numeroDocumento: '',
            denominacion: 'CONSUMIDOR FINAL',
            importeTotal: 500.00,
            importeExento: 500.00,
            alicuotas: [],
            codigoOperacion: 'E',
        );
    }

    public function test_ventas_cbte_respeta_el_diseno_de_registro(): void
    {
        // [8,3,5,20,20,2,20,30,15,15,15,15,15,15,15,15,3,10,1,1,15,8] = 266
        $widths = [8, 3, 5, 20, 20, 2, 20, 30, 15, 15, 15, 15, 15, 15, 15, 15, 3, 10, 1, 1, 15, 8];
        $this->assertSame(266, array_sum($widths));

        $line = rtrim(LibroIvaExporter::ventasCbte(new Collection([$this->gravadaRow()])), "\r\n");
        $this->assertSame(266, strlen($line));

        $f = $this->fields($line, $widths);
        $this->assertSame('20260715', $f[1]); // fecha
        $this->assertSame('006', $f[2]); // tipo comprobante (Factura B = 006)
        $this->assertSame('00001', $f[3]); // punto de venta
        $this->assertSame(str_pad('45', 20, '0', STR_PAD_LEFT), $f[4]); // número comprobante
        $this->assertSame($f[4], $f[5]); // número comprobante hasta = mismo (sin rango de hojas)
        $this->assertSame('80', $f[6]); // código documento comprador (CUIT)
        $this->assertSame(str_pad('20111111112', 20, '0', STR_PAD_LEFT), $f[7]); // nro identificación
        $this->assertSame(str_pad('Cliente Test', 30), $f[8]); // denominación
        $this->assertSame(str_pad('121000', 15, '0', STR_PAD_LEFT), $f[9]); // importe total ($1210,00)
        $this->assertSame(str_pad('0', 15, '0', STR_PAD_LEFT), $f[12]); // importe exento (gravada = 0)
        $this->assertSame('PES', $f[17]); // moneda
        $this->assertSame('0001000000', $f[18]); // tipo de cambio (1:1)
        $this->assertSame('1', $f[19]); // cantidad de alícuotas
        $this->assertSame(' ', $f[20]); // código de operación (gravada = blanco)
    }

    public function test_ventas_cbte_marca_codigo_de_operacion_e_para_comprobantes_exentos(): void
    {
        $widths = [8, 3, 5, 20, 20, 2, 20, 30, 15, 15, 15, 15, 15, 15, 15, 15, 3, 10, 1, 1, 15, 8];
        $line = rtrim(LibroIvaExporter::ventasCbte(new Collection([$this->exentaRow()])), "\r\n");
        $f = $this->fields($line, $widths);

        $this->assertSame('E', $f[20]);
        $this->assertSame(str_pad('50000', 15, '0', STR_PAD_LEFT), $f[12]); // importe exento = $500
    }

    public function test_ventas_alicuotas_respeta_el_diseno_y_omite_comprobantes_exentos(): void
    {
        // [3,5,20,15,4,15] = 62
        $widths = [3, 5, 20, 15, 4, 15];
        $this->assertSame(62, array_sum($widths));

        $rows = new Collection([$this->gravadaRow(), $this->exentaRow()]);
        $lines = explode("\r\n", rtrim(LibroIvaExporter::ventasAlicuotas($rows), "\r\n"));

        $this->assertCount(1, $lines); // la exenta no genera fila de alícuota

        $f = $this->fields($lines[0], $widths);
        $this->assertSame(62, strlen($lines[0]));
        $this->assertSame('006', $f[1]); // tipo comprobante
        $this->assertSame('00001', $f[2]); // punto venta
        $this->assertSame(str_pad('45', 20, '0', STR_PAD_LEFT), $f[3]); // número comprobante
        $this->assertSame(str_pad('100000', 15, '0', STR_PAD_LEFT), $f[4]); // neto gravado $1000
        $this->assertSame('0005', $f[5]); // código de alícuota 21%
        $this->assertSame(str_pad('21000', 15, '0', STR_PAD_LEFT), $f[6]); // IVA $210
    }

    public function test_compras_cbte_respeta_el_diseno_de_registro(): void
    {
        // [8,3,5,20,16,2,20,30,15,15,15,15,15,15,15,15,3,10,1,1,15,15,11,30,15] = 325
        $widths = [8, 3, 5, 20, 16, 2, 20, 30, 15, 15, 15, 15, 15, 15, 15, 15, 3, 10, 1, 1, 15, 15, 11, 30, 15];
        $this->assertSame(325, array_sum($widths));

        $row = new LibroIvaRow(
            fecha: Carbon::parse('2026-07-10'),
            tipoComprobante: TipoComprobante::FacturaA,
            puntoVenta: 3,
            numeroComprobante: 100,
            codigoDocumento: 80,
            numeroDocumento: '30111111113',
            denominacion: 'Proveedor Test',
            importeTotal: 1000.0,
            importeExento: 0.0,
            alicuotas: [new LibroIvaAlicuota(21.0, 826.45, 173.55)],
            codigoOperacion: '',
        );

        $line = rtrim(LibroIvaExporter::comprasCbte(new Collection([$row])), "\r\n");
        $this->assertSame(325, strlen($line));

        $f = $this->fields($line, $widths);
        $this->assertSame('20260710', $f[1]); // fecha
        $this->assertSame('001', $f[2]); // Factura A = 001
        $this->assertSame('00003', $f[3]); // punto de venta
        $this->assertSame(str_pad('100', 20, '0', STR_PAD_LEFT), $f[4]);
        $this->assertSame(str_pad('', 16), $f[5]); // despacho de importación: no aplica
        $this->assertSame('80', $f[6]); // código documento vendedor
        $this->assertSame(str_pad('30111111113', 20, '0', STR_PAD_LEFT), $f[7]);
        $this->assertSame(str_pad('Proveedor Test', 30), $f[8]);
        $this->assertSame(str_pad('100000', 15, '0', STR_PAD_LEFT), $f[9]); // importe total $1000
        $this->assertSame(str_pad('0', 15, '0', STR_PAD_LEFT), $f[11]); // importe exento
        $this->assertSame('PES', $f[17]);
        $this->assertSame('0001000000', $f[18]);
        $this->assertSame(str_pad('17355', 15, '0', STR_PAD_LEFT), $f[21]); // crédito fiscal computable = IVA $173,55
        $this->assertSame(str_pad('', 11, '0', STR_PAD_LEFT), $f[23]); // CUIT emisor/corredor: no aplica
    }

    public function test_compras_alicuotas_respeta_el_diseno_de_registro(): void
    {
        // [3,5,20,2,20,15,4,15] = 84
        $widths = [3, 5, 20, 2, 20, 15, 4, 15];
        $this->assertSame(84, array_sum($widths));

        $row = new LibroIvaRow(
            fecha: Carbon::parse('2026-07-10'),
            tipoComprobante: TipoComprobante::FacturaA,
            puntoVenta: 3,
            numeroComprobante: 100,
            codigoDocumento: 80,
            numeroDocumento: '30111111113',
            denominacion: 'Proveedor Test',
            importeTotal: 1000.0,
            importeExento: 0.0,
            alicuotas: [new LibroIvaAlicuota(21.0, 826.45, 173.55)],
            codigoOperacion: '',
        );

        $line = rtrim(LibroIvaExporter::comprasAlicuotas(new Collection([$row])), "\r\n");
        $this->assertSame(84, strlen($line));

        $f = $this->fields($line, $widths);
        $this->assertSame('001', $f[1]);
        $this->assertSame('00003', $f[2]);
        $this->assertSame(str_pad('100', 20, '0', STR_PAD_LEFT), $f[3]);
        $this->assertSame('80', $f[4]);
        $this->assertSame(str_pad('30111111113', 20, '0', STR_PAD_LEFT), $f[5]);
        $this->assertSame(str_pad('82645', 15, '0', STR_PAD_LEFT), $f[6]);
        $this->assertSame('0005', $f[7]);
        $this->assertSame(str_pad('17355', 15, '0', STR_PAD_LEFT), $f[8]);
    }
}
