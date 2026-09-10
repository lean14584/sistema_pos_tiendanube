<?php

namespace App\Support;

use App\Models\CompanySettings;
use App\Models\Promotion;
use App\Models\PromotionGroup;
use App\Models\Sucursal;
use GdImage;
use Illuminate\Support\Facades\Storage;

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
        ['r' => 51, 'g' => 51, 'b' => 54],    // grafito
        ['r' => 92, 'g' => 86, 'b' => 78],    // taupe
        ['r' => 68, 'g' => 73, 'b' => 79],    // pizarra
        ['r' => 112, 'g' => 105, 'b' => 92],  // arena oscura
    ];

    /** Dorado de acento: precios, sticker de descuento y detalles destacados. */
    private const ACCENT = ['r' => 201, 'g' => 162, 'b' => 39];

    private static function fontBold(): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
    }

    private static function fontMono(): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono-Bold.ttf');
    }

    private static function fontSerifBold(): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-Bold.ttf');
    }

    private static function fontSerifItalic(): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-Italic.ttf');
    }

    /**
     * @return array<int, array{title:string, badge:string, detail:string, image_path:?string}>
     */
    public static function items(): array
    {
        $individuales = Promotion::activeNow()->with('product')->get()
            ->filter(fn (Promotion $p) => $p->product !== null)
            ->map(fn (Promotion $p) => [
                'title' => $p->product->name,
                'badge' => $p->shortLabel(),
                'detail' => '$'.money($p->product->price),
                'image_path' => self::resolveImagePath($p->product->image_path),
            ]);

        $grupos = PromotionGroup::activeNow()->with('products')->get()
            ->filter(fn (PromotionGroup $g) => $g->products->isNotEmpty())
            ->map(function (PromotionGroup $g) {
                $nombres = $g->products->pluck('name');

                // Usa la foto del primer producto de la familia que tenga una
                // cargada; si ninguno tiene, la tarjeta cae al círculo de color.
                $imagePath = null;
                foreach ($g->products as $producto) {
                    $imagePath = self::resolveImagePath($producto->image_path);
                    if ($imagePath) {
                        break;
                    }
                }

                return [
                    'title' => $g->name,
                    'badge' => $g->shortLabel(),
                    'detail' => $nombres->take(3)->implode(', ').($nombres->count() > 3 ? '...' : ''),
                    'image_path' => $imagePath,
                ];
            });

        return $individuales->concat($grupos)->values()->all();
    }

    private static function resolveImagePath(?string $imagePath): ?string
    {
        if (! $imagePath) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($imagePath) ? $disk->path($imagePath) : null;
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
        $top = ['r' => 30, 'g' => 30, 'b' => 33];
        $bottom = ['r' => 58, 'g' => 55, 'b' => 50];

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
        $white = imagecolorallocatealpha($im, 255, 255, 255, 118);
        $gold = imagecolorallocatealpha($im, self::ACCENT['r'], self::ACCENT['g'], self::ACCENT['b'], 112);

        imagefilledellipse($im, 930, 140, 460, 460, $white);
        imagefilledellipse($im, 60, $height - 90, 320, 320, $white);
        imagefilledellipse($im, 120, (int) ($height * 0.65), 260, 260, $gold);
    }

    /**
     * Nombre a mostrar en el encabezado del cartel: la razón social se
     * carga en "Datos de la empresa" (global) y prácticamente siempre está
     * completa (hace falta para facturar); el nombre de fantasía es
     * opcional. Si todavía no se cargó ninguna de las dos (setup a medio
     * hacer), como último recurso se usa la razón social de alguna
     * sucursal — existe un campo con el mismo nombre ahí, para facturar
     * por sucursal, y es fácil cargarlo ahí por error pensando que es el
     * dato global.
     */
    public static function headerName(CompanySettings $company): ?string
    {
        return $company->razon_social
            ?: $company->nombre_fantasia
            ?: Sucursal::where('razon_social', '!=', '')->orderBy('id')->value('razon_social');
    }

    private static function drawHeader(GdImage $im, CompanySettings $company): void
    {
        $white = imagecolorallocate($im, 255, 255, 255);
        $dark = imagecolorallocate($im, 40, 38, 35);
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 55);
        $gold = imagecolorallocate($im, self::ACCENT['r'], self::ACCENT['g'], self::ACCENT['b']);

        $nombre = self::headerName($company);
        $logoPath = self::resolveLogoPath($company);
        $logoBox = 63;
        $logoGap = 18;

        if ($nombre) {
            $label = mb_strtoupper($nombre);
            $maxWidth = self::WIDTH - 160 - ($logoPath ? $logoBox + $logoGap : 0);
            $fontSize = self::fitSingleLineByWidth(self::fontSerifBold(), $label, $maxWidth, 24, 16);
            $label = self::fitTextToWidth(self::fontSerifBold(), $fontSize, $maxWidth, $label);
            $box = imagettfbbox($fontSize, 0, self::fontSerifBold(), $label);
            $textWidth = $box[2] - $box[0];
            $pillWidth = (int) max(320, min($maxWidth + 90, $textWidth + 90));
            $groupWidth = $pillWidth + ($logoPath ? $logoBox + $logoGap : 0);
            $groupX1 = (int) ((self::WIDTH - $groupWidth) / 2);

            if ($logoPath) {
                self::roundedRect($im, $groupX1, 55, $groupX1 + $logoBox, 118, 18, $white);
                self::drawContainedImage($im, $logoPath, $groupX1, 55, $groupX1 + $logoBox, 118, 8);
            }

            $pillX1 = $groupX1 + ($logoPath ? $logoBox + $logoGap : 0);
            self::roundedRect($im, $pillX1, 55, $pillX1 + $pillWidth, 118, 31, $white);
            self::centeredText($im, self::fontSerifBold(), $fontSize, $pillX1 + $pillWidth / 2, 87, 0, $dark, $label);
        }

        self::centeredText($im, self::fontSerifBold(), 100, 546, 264, -4, $shadow, '¡OFERTAS!');
        self::centeredText($im, self::fontSerifBold(), 100, 540, 258, -4, $gold, '¡OFERTAS!');

        self::centeredText($im, self::fontSerifItalic(), 26, 540, 330, 0, $white, 'Precios especiales por tiempo limitado');
    }

    private static function resolveLogoPath(CompanySettings $company): ?string
    {
        if (! $company->logo_path) {
            return null;
        }

        $path = storage_path('app/public/'.$company->logo_path);

        return file_exists($path) ? $path : null;
    }

    /** Dibuja una imagen "contain" (sin recortar) centrada dentro de un recuadro. */
    private static function drawContainedImage(GdImage $canvas, string $absolutePath, int $boxX1, int $boxY1, int $boxX2, int $boxY2, int $padding): void
    {
        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $maxW = ($boxX2 - $boxX1) - $padding * 2;
        $maxH = ($boxY2 - $boxY1) - $padding * 2;
        $scale = min($maxW / $srcW, $maxH / $srcH, 1);
        $dstW = (int) round($srcW * $scale);
        $dstH = (int) round($srcH * $scale);
        $dstX = $boxX1 + (int) ((($boxX2 - $boxX1) - $dstW) / 2);
        $dstY = $boxY1 + (int) ((($boxY2 - $boxY1) - $dstH) / 2);

        imagecopyresampled($canvas, $src, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);
    }

    /**
     * @param  array<int, array{title:string, badge:string, detail:string}>  $items
     */
    private static function drawCards(GdImage $im, array $items): void
    {
        if ($items === []) {
            $white = imagecolorallocate($im, 255, 255, 255);
            self::centeredText($im, self::fontSerifBold(), 34, self::WIDTH / 2, 700, 0, $white, 'Todavía no hay');
            self::centeredText($im, self::fontSerifBold(), 34, self::WIDTH / 2, 750, 0, $white, 'promociones activas');

            return;
        }

        $cardW = (int) ((self::WIDTH - 2 * self::MARGIN_X - self::CARD_GAP) / self::COLS);

        $white = imagecolorallocate($im, 255, 255, 255);
        $dark = imagecolorallocate($im, 40, 38, 35);
        $shadowColor = imagecolorallocatealpha($im, 0, 0, 0, 70);
        $gold = imagecolorallocate($im, self::ACCENT['r'], self::ACCENT['g'], self::ACCENT['b']);
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

            $badgeCx = $x1 + 95;
            $badgeCy = (int) ($y1 + self::CARD_H / 2);
            $angle = $i % 2 === 0 ? -7 : 7;

            $thumb = ! empty($item['image_path']) ? self::loadSquareThumbnail($item['image_path'], 128) : null;

            if ($thumb !== null) {
                self::drawProductPhoto($im, $thumb, $badgeCx, $badgeCy, $i, mb_strtoupper($item['badge']), $angle);
                imagedestroy($thumb);
            } else {
                $badgeColor = self::paletteColor($im, $i);
                imagefilledellipse($im, $badgeCx, $badgeCy, $badgeDiameter, $badgeDiameter, $badgeColor);
                self::drawBadgeLabel($im, mb_strtoupper($item['badge']), $badgeCx, $badgeCy, $angle, $badgeDiameter, $white);
            }

            $textX = $x1 + 185;
            $maxTextW = $cardW - 205;
            $lines = array_slice(self::wrapText(self::fontSerifBold(), 29, $maxTextW, mb_strtoupper($item['title'])), 0, 2);
            $lineY = $y1 + 62;
            foreach ($lines as $line) {
                imagettftext($im, 29, 0, $textX, $lineY, $dark, self::fontSerifBold(), $line);
                $lineY += 36;
            }

            $detail = self::fitTextToWidth(self::fontMono(), 19, $maxTextW, $item['detail']);
            imagettftext($im, 19, 0, $textX, $y2 - 22, $gold, self::fontMono(), $detail);
        }
    }

    /**
     * Dibuja la foto del producto en un marco cuadrado de color (redondeado)
     * y le pega encima, en la esquina, un "sticker" circular con el badge de
     * descuento — como una oferta pegada sobre la foto en un folleto real.
     */
    private static function drawProductPhoto(GdImage $im, GdImage $thumb, int $cx, int $cy, int $index, string $badgeText, float $angle): void
    {
        $frameSize = 150;
        $photoSize = imagesx($thumb);
        $margin = (int) (($frameSize - $photoSize) / 2);
        $fx1 = $cx - (int) ($frameSize / 2);
        $fy1 = $cy - (int) ($frameSize / 2);

        $frameColor = self::paletteColor($im, $index);
        self::roundedRect($im, $fx1, $fy1, $fx1 + $frameSize, $fy1 + $frameSize, 22, $frameColor);
        imagecopy($im, $thumb, $fx1 + $margin, $fy1 + $margin, 0, 0, $photoSize, $photoSize);

        $white = imagecolorallocate($im, 255, 255, 255);
        $dark = imagecolorallocate($im, 40, 38, 35);
        $stickerColor = imagecolorallocate($im, self::ACCENT['r'], self::ACCENT['g'], self::ACCENT['b']); // dorado, fijo para que se distinga de la foto
        $stickerD = 76;
        $stickerCx = $fx1 + $frameSize - 12;
        $stickerCy = $fy1 + 4;

        imagefilledellipse($im, $stickerCx, $stickerCy, $stickerD + 8, $stickerD + 8, $white);
        imagefilledellipse($im, $stickerCx, $stickerCy, $stickerD, $stickerD, $stickerColor);
        self::drawBadgeLabel($im, $badgeText, $stickerCx, $stickerCy, $angle, $stickerD, $dark);
    }

    /** Recorta al cuadrado (centrado) y reescala; null si el archivo no es una imagen válida. */
    private static function loadSquareThumbnail(string $absolutePath, int $size): ?GdImage
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return null;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $side = min($srcW, $srcH);
        $srcX = (int) (($srcW - $side) / 2);
        $srcY = (int) (($srcH - $side) / 2);

        $thumb = imagecreatetruecolor($size, $size);
        imagecopyresampled($thumb, $src, 0, 0, $srcX, $srcY, $size, $size, $side, $side);
        imagedestroy($src);

        return $thumb;
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

    /** Tamaño de letra más grande (entre $min y $max) cuyo ANCHO entra en $maxWidth; si ni al mínimo entra, devuelve el mínimo igual (el texto se recorta al dibujarlo). */
    private static function fitSingleLineByWidth(string $font, string $text, int $maxWidth, int $max, int $min): int
    {
        for ($size = $max; $size >= $min; $size--) {
            $box = imagettfbbox($size, 0, $font, $text);
            if ($maxWidth >= $box[2] - $box[0]) {
                return $size;
            }
        }

        return $min;
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
        if ($maxWidth >= $box[2] - $box[0]) {
            return $text;
        }

        $chars = mb_str_split($text);
        while ($chars !== []) {
            array_pop($chars);
            $candidate = implode('', $chars).'…';
            $box = imagettfbbox($size, 0, $font, $candidate);

            if ($maxWidth >= $box[2] - $box[0]) {
                return $candidate;
            }
        }

        return '…';
    }

    private static function drawMoreBadge(GdImage $im, int $height, int $count): void
    {
        $white = imagecolorallocate($im, 255, 255, 255);
        $y = $height - self::FOOTER_H + 30;
        self::centeredText($im, self::fontSerifItalic(), 24, self::WIDTH / 2, $y, 0, $white, "+ {$count} oferta".($count === 1 ? '' : 's').' más en el local');
    }

    private static function drawFooter(GdImage $im, int $height): void
    {
        $white = imagecolorallocate($im, 255, 255, 255);
        self::centeredText($im, self::fontSerifItalic(), 20, self::WIDTH / 2, $height - 40, 0, $white, 'Válido mientras dure el stock. Precios sujetos a modificación.');
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
