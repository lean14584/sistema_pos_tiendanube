<?php

namespace App\Support;

use App\Models\CompanySettings;
use App\Models\Promotion;
use App\Models\PromotionGroup;
use GdImage;

/**
 * Genera un cartel de ofertas (PNG) con las promociones activas, para
 * imprimir o compartir por WhatsApp/redes. Usa GD puro (sin Imagick ni
 * navegador headless) para que funcione igual en el hosting compartido sin
 * SSH: las únicas fuentes que usa son las DejaVu que ya vienen con dompdf
 * (vendor/dompdf/dompdf/lib/fonts), así que no suma ninguna dependencia
 * nueva al servidor.
 */
class PromotionPoster
{
    private const WIDTH = 1080;

    private const MAX_ITEMS = 8;

    private const COLS = 2;

    private const CARD_H = 168;

    private const CARD_GAP = 28;

    private const MARGIN_X = 50;

    private const GRID_START_Y = 380;

    private const FOOTER_H = 130;

    /** @var array<int, array{r:int,g:int,b:int}> */
    private const PALETTE = [
        ['r' => 236, 'g' => 40, 'b' => 116],  // frutilla
        ['r' => 109, 'g' => 40, 'b' => 217],  // violeta
        ['r' => 5, 'g' => 150, 'b' => 105],   // verde
        ['r' => 234, 'g' => 88, 'b' => 12],   // naranja
    ];

    private static function fontBold(): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
    }

    private static function fontItalic(): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Oblique.ttf');
    }

    private static function fontMono(): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono-Bold.ttf');
    }

    /**
     * @return array<int, array{title:string, badge:string, detail:string}>
     */
    public static function items(): array
    {
        $individuales = Promotion::activeNow()->with('product')->get()
            ->filter(fn (Promotion $p) => $p->product !== null)
            ->map(fn (Promotion $p) => [
                'title' => $p->product->name,
                'badge' => $p->shortLabel(),
                'detail' => '$'.money($p->product->price),
            ]);

        $grupos = PromotionGroup::activeNow()->with('products')->get()
            ->filter(fn (PromotionGroup $g) => $g->products->isNotEmpty())
            ->map(function (PromotionGroup $g) {
                $nombres = $g->products->pluck('name');

                return [
                    'title' => $g->name,
                    'badge' => $g->shortLabel(),
                    'detail' => $nombres->take(3)->implode(', ').($nombres->count() > 3 ? '...' : ''),
                ];
            });

        return $individuales->concat($grupos)->values()->all();
    }

    /** PNG crudo, listo para servir con Content-Type: image/png. */
    public static function generate(): string
    {
        $items = array_slice(self::items(), 0, self::MAX_ITEMS);
        $totalItems = count(self::items());

        $rows = max(1, (int) ceil(count($items) / self::COLS));
        $height = self::GRID_START_Y + $rows * self::CARD_H + max(0, $rows - 1) * self::CARD_GAP + self::FOOTER_H;
        $height = max($height, 900);

        $im = imagecreatetruecolor(self::WIDTH, $height);
        imagesavealpha($im, true);
        imagealphablending($im, true);

        self::drawBackground($im, $height);
        self::drawDecoration($im, $height);
        self::drawHeader($im, CompanySettings::current());
        self::drawCards($im, $items);

        if ($totalItems > self::MAX_ITEMS) {
            self::drawMoreBadge($im, $height, $totalItems - self::MAX_ITEMS);
        }

        self::drawFooter($im, $height);

        ob_start();
        imagepng($im);
        $data = (string) ob_get_clean();
        imagedestroy($im);

        return $data;
    }

    private static function drawBackground(GdImage $im, int $height): void
    {
        $top = ['r' => 255, 'g' => 45, 'b' => 110];
        $bottom = ['r' => 255, 'g' => 159, 'b' => 28];

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $r = (int) ($top['r'] + ($bottom['r'] - $top['r']) * $t);
            $g = (int) ($top['g'] + ($bottom['g'] - $top['g']) * $t);
            $b = (int) ($top['b'] + ($bottom['b'] - $top['b']) * $t);
            $color = imagecolorallocate($im, $r, $g, $b);
            imageline($im, 0, $y, self::WIDTH, $y, $color);
        }
    }

    private static function drawDecoration(GdImage $im, int $height): void
    {
        $white = imagecolorallocatealpha($im, 255, 255, 255, 105);
        $yellow = imagecolorallocatealpha($im, 255, 235, 59, 108);

        imagefilledellipse($im, 930, 140, 460, 460, $white);
        imagefilledellipse($im, 60, $height - 90, 320, 320, $white);
        imagefilledellipse($im, 120, (int) ($height * 0.65), 260, 260, $yellow);
    }

    private static function drawHeader(GdImage $im, CompanySettings $company): void
    {
        $white = imagecolorallocate($im, 255, 255, 255);
        $dark = imagecolorallocate($im, 45, 20, 45);
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 45);

        $nombre = $company->nombre_fantasia ?: $company->razon_social;

        if ($nombre) {
            self::roundedRect($im, 290, 55, 790, 118, 31, $white);
            self::centeredText($im, self::fontBold(), 24, 540, 87, 0, $dark, mb_strtoupper($nombre));
        }

        self::centeredText($im, self::fontBold(), 100, 546, 264, -4, $shadow, '¡OFERTAS!');
        self::centeredText($im, self::fontBold(), 100, 540, 258, -4, $white, '¡OFERTAS!');

        self::centeredText($im, self::fontItalic(), 26, 540, 330, 0, $white, 'Precios especiales por tiempo limitado');
    }

    /**
     * @param  array<int, array{title:string, badge:string, detail:string}>  $items
     */
    private static function drawCards(GdImage $im, array $items): void
    {
        if ($items === []) {
            $white = imagecolorallocate($im, 255, 255, 255);
            self::centeredText($im, self::fontBold(), 34, self::WIDTH / 2, 700, 0, $white, 'Todavía no hay');
            self::centeredText($im, self::fontBold(), 34, self::WIDTH / 2, 750, 0, $white, 'promociones activas');

            return;
        }

        $cardW = (int) ((self::WIDTH - 2 * self::MARGIN_X - self::CARD_GAP) / self::COLS);

        $white = imagecolorallocate($im, 255, 255, 255);
        $dark = imagecolorallocate($im, 40, 25, 45);
        $shadowColor = imagecolorallocatealpha($im, 0, 0, 0, 70);
        $badgeDiameter = 148;

        foreach ($items as $i => $item) {
            $col = $i % self::COLS;
            $row = intdiv($i, self::COLS);
            $x1 = self::MARGIN_X + $col * ($cardW + self::CARD_GAP);
            $y1 = self::GRID_START_Y + $row * (self::CARD_H + self::CARD_GAP);
            $x2 = $x1 + $cardW;
            $y2 = $y1 + self::CARD_H;

            self::roundedRect($im, $x1 + 5, $y1 + 7, $x2 + 5, $y2 + 7, 24, $shadowColor);
            self::roundedRect($im, $x1, $y1, $x2, $y2, 24, $white);

            $badgeColor = self::paletteColor($im, $i);
            $badgeCx = $x1 + 95;
            $badgeCy = (int) ($y1 + self::CARD_H / 2);
            imagefilledellipse($im, $badgeCx, $badgeCy, $badgeDiameter, $badgeDiameter, $badgeColor);

            $angle = $i % 2 === 0 ? -7 : 7;
            self::drawBadgeLabel($im, mb_strtoupper($item['badge']), $badgeCx, $badgeCy, $angle, $badgeDiameter, $white);

            $textX = $x1 + 185;
            $maxTextW = $cardW - 205;
            $lines = array_slice(self::wrapText(self::fontBold(), 29, $maxTextW, mb_strtoupper($item['title'])), 0, 2);
            $lineY = $y1 + 62;
            foreach ($lines as $line) {
                imagettftext($im, 29, 0, $textX, $lineY, $dark, self::fontBold(), $line);
                $lineY += 36;
            }

            $detailColor = self::paletteColor($im, $i);
            $detail = self::fitTextToWidth(self::fontMono(), 19, $maxTextW, $item['detail']);
            imagettftext($im, 19, 0, $textX, $y2 - 22, $detailColor, self::fontMono(), $detail);
        }
    }

    /**
     * Encaja el texto de la badge dentro del círculo: como el texto rota
     * alrededor del mismo centro que el círculo, alcanza con que la
     * diagonal del recuadro SIN rotar entre en el diámetro — eso garantiza
     * que quepa a cualquier ángulo. Si ni achicando la letra entra en una
     * sola línea, lo parte en dos.
     */
    private static function drawBadgeLabel(GdImage $im, string $text, int $cx, int $cy, float $angle, int $diameter, int $color): void
    {
        $budget = $diameter * 0.8;
        $size = self::fitSingleLine(self::fontBold(), $text, $budget, 30, 15);

        if ($size !== null) {
            self::centeredText($im, self::fontBold(), $size, $cx, $cy, $angle, $color, $text);

            return;
        }

        // No entra en una línea ni al tamaño mínimo: partirlo en dos por el
        // espacio más cercano al medio.
        $words = explode(' ', $text);
        $mid = (int) ceil(count($words) / 2);
        $line1 = implode(' ', array_slice($words, 0, $mid)) ?: $text;
        $line2 = implode(' ', array_slice($words, $mid)) ?: '';

        $size1 = self::fitSingleLine(self::fontBold(), $line1, $budget, 24, 12) ?? 12;
        $size2 = $line2 !== '' ? (self::fitSingleLine(self::fontBold(), $line2, $budget, 24, 12) ?? 12) : $size1;
        $lineSize = min($size1, $size2);
        $lineGap = $lineSize + 4;

        self::centeredText($im, self::fontBold(), $lineSize, $cx, $cy - (int) ($lineGap / 2), $angle, $color, $line1);
        if ($line2 !== '') {
            self::centeredText($im, self::fontBold(), $lineSize, $cx, $cy + (int) ($lineGap / 2), $angle, $color, $line2);
        }
    }

    /** Tamaño de letra más grande (entre $min y $max) cuya diagonal (sin rotar) entra en $budget; null si ni al mínimo entra. */
    private static function fitSingleLine(string $font, string $text, float $budget, int $max, int $min): ?int
    {
        for ($size = $max; $size >= $min; $size--) {
            $box = imagettfbbox($size, 0, $font, $text);
            $w = $box[2] - $box[0];
            $h = $box[1] - $box[7];

            if (sqrt($w * $w + $h * $h) <= $budget) {
                return $size;
            }
        }

        return null;
    }

    /** Trunca con "…" hasta que el ancho renderizado entre en $maxWidth. */
    private static function fitTextToWidth(string $font, float $size, int $maxWidth, string $text): string
    {
        $box = imagettfbbox($size, 0, $font, $text);
        if ($box[2] - $box[0] <= $maxWidth) {
            return $text;
        }

        $chars = mb_str_split($text);
        while ($chars !== []) {
            array_pop($chars);
            $candidate = implode('', $chars).'…';
            $box = imagettfbbox($size, 0, $font, $candidate);

            if ($box[2] - $box[0] <= $maxWidth) {
                return $candidate;
            }
        }

        return '…';
    }

    private static function drawMoreBadge(GdImage $im, int $height, int $count): void
    {
        $white = imagecolorallocate($im, 255, 255, 255);
        $y = $height - self::FOOTER_H + 30;
        self::centeredText($im, self::fontItalic(), 24, self::WIDTH / 2, $y, 0, $white, "+ {$count} oferta".($count === 1 ? '' : 's').' más en el local');
    }

    private static function drawFooter(GdImage $im, int $height): void
    {
        $white = imagecolorallocate($im, 255, 255, 255);
        self::centeredText($im, self::fontItalic(), 20, self::WIDTH / 2, $height - 40, 0, $white, 'Válido mientras dure el stock. Precios sujetos a modificación.');
    }

    private static function paletteColor(GdImage $im, int $index): int
    {
        $c = self::PALETTE[$index % count(self::PALETTE)];

        return imagecolorallocate($im, $c['r'], $c['g'], $c['b']);
    }

    /** Rectángulo con esquinas redondeadas (GD no lo trae de fábrica). */
    private static function roundedRect(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    /** @return string[] */
    private static function wrapText(string $font, float $size, int $maxWidth, string $text): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($size, 0, $font, $test);
            $width = $box[2] - $box[0];

            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $test;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Centra texto (con o sin rotación) alrededor de ($centerX, $centerY).
     * imagettftext posiciona el arranque del renglón de base, no el centro
     * del bloque, así que hay que compensar con el bounding box real.
     */
    private static function centeredText(GdImage $im, string $font, float $size, int $centerX, int $centerY, float $angle, int $color, string $text): void
    {
        $box = imagettfbbox($size, $angle, $font, $text);
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        $minY = min($box[1], $box[3], $box[5], $box[7]);
        $maxY = max($box[1], $box[3], $box[5], $box[7]);

        $x = $centerX - ($maxX - $minX) / 2 - $minX;
        $y = $centerY + ($maxY - $minY) / 2 - $maxY;

        imagettftext($im, $size, $angle, (int) round($x), (int) round($y), $color, $font, $text);
    }
}
