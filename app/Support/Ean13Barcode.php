<?php

namespace App\Support;

/**
 * Dibuja el símbolo de barras EAN13 con GD puro (mismo enfoque que
 * PromotionPoster: sin librerías de barcode ni Imagick, solo las fuentes
 * DejaVu que ya vienen con dompdf). El alto de las barras es más chico que
 * el estándar de góndola a propósito (pensado para la etiqueta HPRT de
 * 55x44mm, donde ya compite por espacio con nombre/precio) — reducir el
 * alto no afecta la lectura del escáner, reducir el ancho de módulo sí.
 */
class Ean13Barcode
{
    private const L_CODES = [
        '0001101', '0011001', '0010011', '0111101', '0100011',
        '0110001', '0101111', '0111011', '0110111', '0001011',
    ];

    private const G_CODES = [
        '0100111', '0110011', '0011011', '0100001', '0011101',
        '0111001', '0000101', '0010001', '0001001', '0010111',
    ];

    private const R_CODES = [
        '1110010', '1100110', '1101100', '1000010', '1011100',
        '1001110', '1010000', '1000100', '1001000', '1110100',
    ];

    /** L/G de los 6 dígitos que siguen al primero, según el primer dígito. */
    private const PARITY = [
        'LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG',
        'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL',
    ];

    /**
     * @param  string  $code  13 dígitos válidos (ver Ean13::fromSku/isValid).
     * @return string PNG binario (fondo blanco, sin transparencia).
     */
    public static function render(string $code, int $moduleWidth = 4, int $barHeight = 90): string
    {
        $bits = self::pattern($code);
        $textHeight = 30;
        $quiet = $moduleWidth * 8;
        $width = strlen($bits) * $moduleWidth + $quiet * 2;
        $height = $barHeight + $textHeight;

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        $x = $quiet;
        foreach (str_split($bits) as $bit) {
            if ($bit === '1') {
                imagefilledrectangle($img, $x, 0, $x + $moduleWidth - 1, $barHeight - 1, $black);
            }
            $x += $moduleWidth;
        }

        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono.ttf');
        $fontSize = 15;
        $text = self::spacedDigits($code);
        $box = imagettfbbox($fontSize, 0, $font, $text);
        $textWidth = $box[2] - $box[0];
        imagettftext($img, $fontSize, 0, (int) (($width - $textWidth) / 2), $barHeight + $textHeight - 8, $black, $font, $text);

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    public static function dataUri(string $code, int $moduleWidth = 4, int $barHeight = 90): string
    {
        return 'data:image/png;base64,'.base64_encode(self::render($code, $moduleWidth, $barHeight));
    }

    private static function spacedDigits(string $code): string
    {
        return $code[0].' '.substr($code, 1, 6).' '.substr($code, 7);
    }

    private static function pattern(string $code): string
    {
        $digits = str_split($code);
        $parity = self::PARITY[(int) $digits[0]];

        $bits = '101';

        for ($i = 1; $i <= 6; $i++) {
            $d = (int) $digits[$i];
            $bits .= $parity[$i - 1] === 'L' ? self::L_CODES[$d] : self::G_CODES[$d];
        }

        $bits .= '01010';

        for ($i = 7; $i <= 12; $i++) {
            $bits .= self::R_CODES[(int) $digits[$i]];
        }

        return $bits.'101';
    }
}
