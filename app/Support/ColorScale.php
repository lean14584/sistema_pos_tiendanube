<?php

namespace App\Support;

/**
 * Genera una escala tipo Tailwind (50..950) a partir de un solo hex, en el
 * espacio OKLCH — aclarar/oscurecer en RGB plano da tintes lavados o sucios
 * según el matiz; en OKLCH se mantiene el mismo matiz percibido en todos los
 * escalones, que es como Tailwind construye su propia paleta default.
 */
class ColorScale
{
    /** Luminosidad (OKLCH) de cada escalón — igual para cualquier color de entrada. */
    private const STOPS = [
        50 => 0.971,
        100 => 0.936,
        200 => 0.882,
        300 => 0.808,
        400 => 0.704,
        500 => 0.609,
        600 => 0.511,
        700 => 0.433,
        800 => 0.368,
        900 => 0.313,
        950 => 0.208,
    ];

    /** Escalón donde el chroma de entrada se usa tal cual, sin atenuar. */
    private const ANCHOR_L = 0.609;

    /** @return array<int, string> escalón (50..950) => "#rrggbb" */
    public static function fromHex(string $hex): array
    {
        [$l, $c, $h] = self::hexToOklch($hex);

        $scale = [];

        foreach (self::STOPS as $stop => $targetL) {
            // El chroma se atenúa cuanto más lejos del ancla (500) está el
            // escalón: sin esto, los extremos (50/950) salen con el mismo
            // chroma que el 500 y quedan demasiado intensos para ser un
            // fondo claro o un texto casi negro.
            $factor = max(0.25, 1 - 0.9 * abs($targetL - self::ANCHOR_L));
            $scale[$stop] = self::oklchToHex($targetL, $c * $factor, $h);
        }

        return $scale;
    }

    /** @return array{0: float, 1: float, 2: float} [L, C, H(radianes)] */
    private static function hexToOklch(string $hex): array
    {
        [$r, $g, $b] = self::hexToLinearSrgb($hex);
        [$l, $a, $b2] = self::linearSrgbToOklab($r, $g, $b);

        return [$l, sqrt($a ** 2 + $b2 ** 2), atan2($b2, $a)];
    }

    private static function oklchToHex(float $l, float $c, float $h): string
    {
        [$r, $g, $b] = self::oklabToLinearSrgb($l, $c * cos($h), $c * sin($h));

        return self::linearSrgbToHex($r, $g, $b);
    }

    /** @return array{0: float, 1: float, 2: float} RGB lineal, 0..1 */
    private static function hexToLinearSrgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            self::srgbToLinear(hexdec(substr($hex, 0, 2)) / 255),
            self::srgbToLinear(hexdec(substr($hex, 2, 2)) / 255),
            self::srgbToLinear(hexdec(substr($hex, 4, 2)) / 255),
        ];
    }

    private static function srgbToLinear(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function linearToSrgb(float $c): float
    {
        $c = max(0.0, min(1.0, $c));

        return $c <= 0.0031308 ? $c * 12.92 : 1.055 * ($c ** (1 / 2.4)) - 0.055;
    }

    /**
     * Matrices de Björn Ottosson (https://bottosson.github.io/posts/oklab/).
     *
     * @return array{0: float, 1: float, 2: float} [L, a, b]
     */
    private static function linearSrgbToOklab(float $r, float $g, float $b): array
    {
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;

        $l_ = self::signedCbrt($l);
        $m_ = self::signedCbrt($m);
        $s_ = self::signedCbrt($s);

        return [
            0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_,
            1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_,
            0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_,
        ];
    }

    /** @return array{0: float, 1: float, 2: float} RGB lineal, 0..1 (sin clamp todavía) */
    private static function oklabToLinearSrgb(float $bigL, float $a, float $b): array
    {
        $l_ = $bigL + 0.3963377774 * $a + 0.2158037573 * $b;
        $m_ = $bigL - 0.1055613458 * $a - 0.0638541728 * $b;
        $s_ = $bigL - 0.0894841775 * $a - 1.2914855480 * $b;

        $l = $l_ ** 3;
        $m = $m_ ** 3;
        $s = $s_ ** 3;

        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    private static function signedCbrt(float $x): float
    {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }

    private static function linearSrgbToHex(float $r, float $g, float $b): string
    {
        $toByte = fn (float $c) => (int) round(self::linearToSrgb($c) * 255);

        return sprintf('#%02x%02x%02x', $toByte($r), $toByte($g), $toByte($b));
    }
}
