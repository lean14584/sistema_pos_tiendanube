<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Services\Afip\QrPayloadBuilder;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use GdImage;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

/**
 * Renderiza el ticket entero como una imagen (GD + fuente TTF) y lo manda a
 * la impresora como bitmap, en vez de depender de los code pages ESC/POS de
 * la impresora. Se probaron dos perfiles de code page (POS-5890 y CP437) y
 * en ambos la impresora clon rendía mal los acentos/ñ — como bitmap el
 * resultado no depende del firmware de la impresora.
 */
class TicketPrinterService
{
    private const PRINTER_NAME = 'POS-58';

    // 58mm de papel a 203dpi (resolución típica de estas impresoras).
    private const CANVAS_WIDTH = 384;

    private const MARGIN = 12;

    private const FONT_SIZE_NORMAL = 17;

    private const FONT_SIZE_BRAND = 21;

    private const LINE_HEIGHT_NORMAL = 26;

    private const LINE_HEIGHT_BRAND = 32;

    /** @var array<int, array{type: string, text?: string, left?: string, right?: string, bold?: bool, size?: int}> */
    private array $blocks = [];

    public function imprimir(Invoice $invoice): void
    {
        $invoice->loadMissing('client', 'items', 'payments');
        $company = CompanySettings::current();

        $this->blocks = [];

        $this->armarEncabezado($company);
        $this->armarDatosComprobante($invoice);
        $this->addSpacer();
        $this->armarCliente($invoice);
        $this->addSpacer();
        $this->armarItems($invoice);
        $this->armarTotales($invoice);
        $this->addSpacer();
        $this->armarPagos($invoice);

        if ($invoice->isFiscal) {
            $this->armarDatosFiscales($invoice);
        }

        $this->addRule();
        $this->addCenter('¡Gracias por su compra!');

        $tempFile = tempnam(sys_get_temp_dir(), 'ticket_').'.png';

        try {
            $this->renderizar($tempFile);

            $printer = new Printer(new WindowsPrintConnector(self::PRINTER_NAME));

            try {
                $printer->bitImage(EscposImage::load($tempFile));
                $printer->feed(3);
                $printer->cut();
            } finally {
                $printer->close();
            }
        } finally {
            @unlink($tempFile);
        }
    }

    private function armarEncabezado(CompanySettings $company): void
    {
        $this->addCenter($company->display_name, bold: true, size: self::FONT_SIZE_BRAND);

        if ($company->domicilio) {
            $this->addCenter($company->domicilio);
        }
        if ($company->cuit) {
            $this->addCenter("CUIT: {$company->cuit}");
        }
        if ($company->condicion_iva) {
            $this->addCenter($company->condicion_iva->label());
        }

        $this->addRule();
    }

    private function armarDatosComprobante(Invoice $invoice): void
    {
        $this->addCenter(
            $invoice->isFiscal ? $invoice->tipo_comprobante->label() : $invoice->tipo_comprobante_interno->label(),
            bold: true,
        );

        if ($invoice->isFiscal) {
            $this->addCenter(sprintf(
                'Pto. Vta. %04d N° %08d',
                $invoice->punto_venta,
                $invoice->numero_comprobante_afip,
            ));
        } else {
            $this->addCenter($invoice->number);
        }
        $this->addCenter($invoice->issue_date->format('d/m/Y H:i'));
    }

    private function armarCliente(Invoice $invoice): void
    {
        $this->addRule();
        $this->addLeft('Cliente: '.$invoice->client->name);
        if ($invoice->client->tax_id) {
            $this->addLeft('ID fiscal: '.$invoice->client->tax_id);
        }
        $this->addRule();
    }

    private function armarItems(Invoice $invoice): void
    {
        foreach ($invoice->items as $item) {
            $this->addLeft($item->description);

            $qty = rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.');
            $left = "{$qty} x \${$this->money($item->unit_price)}";
            $right = '$'.$this->money($item->line_total);
            $this->addColumns($left, $right);
        }
    }

    private function armarTotales(Invoice $invoice): void
    {
        $this->addRule();

        // Neto gravado/exento ya vienen netos del descuento (ver HasLineTotal).
        // Para que el Descuento se pueda mostrar sin que el ticket parezca no
        // cerrar, primero se muestra el Subtotal bruto (sin descuento) y
        // recién ahí se resta: Subtotal − Descuento = Neto gravado + Exento.
        $bruto = $invoice->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price);
        $descuento = $bruto - ((float) $invoice->neto_gravado + (float) $invoice->neto_exento);

        if ($descuento > 0.004) {
            $this->addColumns('Subtotal', '$'.$this->money($bruto));
            $this->addColumns('Descuento', '-$'.$this->money($descuento));
        }

        $this->addColumns('Neto gravado', '$'.$this->money($invoice->neto_gravado));

        if ($invoice->neto_exento > 0) {
            $this->addColumns('Exento', '$'.$this->money($invoice->neto_exento));
        }

        foreach ($invoice->ivaPorAlicuota() as $linea) {
            $tasa = rtrim(rtrim(number_format($linea['tasa'], 2), '0'), '.');
            $this->addColumns("IVA {$tasa}%", '$'.$this->money($linea['iva']));
        }

        $this->addColumns('TOTAL', '$'.$this->money($invoice->total), bold: true);
    }

    private function armarPagos(Invoice $invoice): void
    {
        if ($invoice->payments->isEmpty()) {
            return;
        }

        $this->addRule();
        $this->addLeft('Medios de pago', bold: true);
        foreach ($invoice->payments as $payment) {
            $this->addColumns($payment->method->label(), '$'.$this->money($payment->amount));
        }

        $vuelto = $invoice->payments->sum('amount') - (float) $invoice->total;
        if ($vuelto > 0.004) {
            $this->addColumns('Vuelto', '$'.$this->money($vuelto), bold: true);
        }
    }

    private function armarDatosFiscales(Invoice $invoice): void
    {
        $this->addRule();
        $this->addCenter("CAE: {$invoice->cae}");
        $this->addCenter('Vto. CAE: '.$invoice->cae_vencimiento->format('d/m/Y'));

        $qrPng = (new PngWriter)->write(new QrCode(QrPayloadBuilder::url($invoice)))->getString();
        $tempFile = tempnam(sys_get_temp_dir(), 'ticket_qr_').'.png';
        file_put_contents($tempFile, $qrPng);
        $this->blocks[] = ['type' => 'image', 'path' => $tempFile];
    }

    private function money(string|float $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }

    private function addCenter(string $text, bool $bold = false, int $size = self::FONT_SIZE_NORMAL): void
    {
        $this->blocks[] = ['type' => 'center', 'text' => $text, 'bold' => $bold, 'size' => $size];
    }

    private function addLeft(string $text, bool $bold = false): void
    {
        $this->blocks[] = ['type' => 'left', 'text' => $text, 'bold' => $bold, 'size' => self::FONT_SIZE_NORMAL];
    }

    private function addColumns(string $left, string $right, bool $bold = false): void
    {
        $this->blocks[] = ['type' => 'columns', 'left' => $left, 'right' => $right, 'bold' => $bold, 'size' => self::FONT_SIZE_NORMAL];
    }

    private function addRule(): void
    {
        $this->blocks[] = ['type' => 'rule'];
    }

    private function addSpacer(int $height = 14): void
    {
        $this->blocks[] = ['type' => 'spacer', 'height' => $height];
    }

    private function font(bool $bold): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono'.($bold ? '-Bold' : '').'.ttf');
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function textWidth(string $text, int $size, bool $bold): float
    {
        $box = imagettfbbox($size, 0, $this->font($bold), $text);

        return abs($box[2] - $box[0]);
    }

    /**
     * Envuelve texto por ancho de píxel real en vez de cantidad de
     * caracteres, respetando palabras y partiendo palabras sueltas
     * demasiado largas para una línea.
     *
     * @return array<int, string>
     */
    private function wrap(string $text, int $maxWidth, int $size, bool $bold): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($this->textWidth($candidate, $size, $bold) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            // La palabra sola ya no entra: la partimos letra por letra.
            if ($this->textWidth($word, $size, $bold) > $maxWidth) {
                $chars = mb_str_split($word);
                $chunk = '';
                foreach ($chars as $char) {
                    $candidateChunk = $chunk.$char;
                    if ($this->textWidth($candidateChunk, $size, $bold) > $maxWidth && $chunk !== '') {
                        $lines[] = $chunk;
                        $chunk = $char;
                    } else {
                        $chunk = $candidateChunk;
                    }
                }
                $current = $chunk;
            } else {
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    private function renderizar(string $outputPath): void
    {
        $usableWidth = self::CANVAS_WIDTH - (self::MARGIN * 2);

        // Primera pasada: expandimos cada bloque a líneas concretas con su
        // alto, para poder calcular la altura total del canvas de antemano.
        /** @var array<int, array{draw: string, size: int, bold: bool, text?: string, left?: string, right?: string, path?: string, height?: int}> */
        $lines = [];

        foreach ($this->blocks as $block) {
            match ($block['type']) {
                'center' => $lines[] = ['draw' => 'center', 'text' => $block['text'], 'bold' => $block['bold'], 'size' => $block['size']],
                'left' => array_push($lines, ...array_map(
                    fn (string $l) => ['draw' => 'left', 'text' => $l, 'bold' => $block['bold'], 'size' => $block['size']],
                    $this->wrap($block['text'], $usableWidth, $block['size'], $block['bold']),
                )),
                'columns' => array_push($lines, ...$this->expandColumns($block, $usableWidth)),
                'rule' => $lines[] = ['draw' => 'rule'],
                'spacer' => $lines[] = ['draw' => 'spacer', 'height' => $block['height']],
                'image' => $lines[] = ['draw' => 'image', 'path' => $block['path'], 'height' => $this->imageHeightFor($block['path'], $usableWidth)],
                default => null,
            };
        }

        $y = self::MARGIN;
        $heights = [];
        foreach ($lines as $line) {
            $h = match ($line['draw']) {
                'rule' => 10,
                'spacer' => $line['height'],
                'image' => $line['height'] + 10,
                default => $line['size'] >= self::FONT_SIZE_BRAND ? self::LINE_HEIGHT_BRAND : self::LINE_HEIGHT_NORMAL,
            };
            $heights[] = $h;
            $y += $h;
        }
        $canvasHeight = $y + self::MARGIN;

        $image = imagecreatetruecolor(self::CANVAS_WIDTH, $canvasHeight);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        $y = self::MARGIN;
        foreach ($lines as $i => $line) {
            $h = $heights[$i];

            match ($line['draw']) {
                'center' => $this->drawCentered($image, $black, $line['text'], $y + $h - 6, $line['size'], $line['bold']),
                'left' => $this->drawText($image, $black, self::MARGIN, $line['text'], $y + $h - 6, $line['size'], $line['bold']),
                'columns' => $this->drawColumns($image, $black, $line['left'], $line['right'], $y + $h - 6, $line['size'], $line['bold'], $usableWidth),
                'right' => $this->drawRight($image, $black, $line['text'], $y + $h - 6, $line['size'], $line['bold'], $usableWidth),
                'rule' => $this->drawRule($image, $y + (int) ($h / 2)),
                'image' => $this->drawImage($image, $line['path'], $y, $usableWidth),
                default => null,
            };

            $y += $h;
        }

        imagepng($image, $outputPath);
        imagedestroy($image);
    }

    private function drawText(GdImage $image, int $color, int $x, string $text, int $baselineY, int $size, bool $bold): void
    {
        imagettftext($image, $size, 0, $x, $baselineY, $color, $this->font($bold), $text);
    }

    private function drawCentered(GdImage $image, int $color, string $text, int $baselineY, int $size, bool $bold): void
    {
        $width = $this->textWidth($text, $size, $bold);
        $x = (int) ((self::CANVAS_WIDTH - $width) / 2);
        $this->drawText($image, $color, $x, $text, $baselineY, $size, $bold);
    }

    private function drawColumns(GdImage $image, int $color, string $left, string $right, int $baselineY, int $size, bool $bold, int $usableWidth): void
    {
        $this->drawText($image, $color, self::MARGIN, $left, $baselineY, $size, $bold);

        $rightWidth = $this->textWidth($right, $size, $bold);
        $x = self::MARGIN + $usableWidth - $rightWidth;
        $this->drawText($image, $color, (int) $x, $right, $baselineY, $size, $bold);
    }

    private function drawRight(GdImage $image, int $color, string $text, int $baselineY, int $size, bool $bold, int $usableWidth): void
    {
        $width = $this->textWidth($text, $size, $bold);
        $x = self::MARGIN + $usableWidth - $width;
        $this->drawText($image, $color, (int) $x, $text, $baselineY, $size, $bold);
    }

    /**
     * Si la etiqueta y el importe entran uno al lado del otro, una sola
     * línea de columnas. Si no, nunca se trunca el importe (es plata): la
     * etiqueta se envuelve por ancho real y el importe queda solo, alineado
     * a la derecha, en su propia línea.
     *
     * @return array<int, array<string, mixed>>
     */
    private function expandColumns(array $block, int $usableWidth): array
    {
        ['left' => $left, 'right' => $right, 'bold' => $bold, 'size' => $size] = $block;
        $minGap = 12;

        if ($this->textWidth($left, $size, $bold) + $this->textWidth($right, $size, $bold) + $minGap <= $usableWidth) {
            return [['draw' => 'columns', 'left' => $left, 'right' => $right, 'bold' => $bold, 'size' => $size]];
        }

        $lines = array_map(
            fn (string $l) => ['draw' => 'left', 'text' => $l, 'bold' => $bold, 'size' => $size],
            $this->wrap($left, $usableWidth, $size, $bold),
        );
        $lines[] = ['draw' => 'right', 'text' => $right, 'bold' => $bold, 'size' => $size];

        return $lines;
    }

    private function drawRule(GdImage $image, int $y): void
    {
        $black = imagecolorallocate($image, 0, 0, 0);
        $x = self::MARGIN;
        $end = self::CANVAS_WIDTH - self::MARGIN;
        while ($x < $end) {
            imageline($image, $x, $y, min($x + 4, $end), $y, $black);
            $x += 7;
        }
    }

    private function imageHeightFor(string $path, int $maxWidth): int
    {
        [$w, $h] = getimagesize($path);

        return (int) round($h * min(1, $maxWidth / $w));
    }

    private function drawImage(GdImage $canvas, string $path, int $y, int $maxWidth): void
    {
        $src = imagecreatefrompng($path);
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $scale = min(1, $maxWidth / $srcW);
        $dstW = (int) round($srcW * $scale);
        $dstH = (int) round($srcH * $scale);
        $x = (int) ((self::CANVAS_WIDTH - $dstW) / 2);

        imagecopyresampled($canvas, $src, $x, $y, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        if (str_contains($path, 'ticket_qr_')) {
            @unlink($path);
        }
    }
}
