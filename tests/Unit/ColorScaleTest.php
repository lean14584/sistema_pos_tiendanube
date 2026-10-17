<?php

namespace Tests\Unit;

use App\Support\ColorScale;
use PHPUnit\Framework\TestCase;

class ColorScaleTest extends TestCase
{
    public function test_genera_los_11_escalones_de_tailwind(): void
    {
        $scale = ColorScale::fromHex('#4f46e5');

        $this->assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950], array_keys($scale));
    }

    public function test_cada_escalon_es_un_hex_valido(): void
    {
        $scale = ColorScale::fromHex('#4f46e5');

        foreach ($scale as $hex) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);
        }
    }

    public function test_los_escalones_bajos_son_mas_claros_que_los_altos(): void
    {
        // No compara contra el hex de entrada (no hay garantía de que el
        // admin cargue justo el tono "500") — solo que la escala generada
        // es monótona: cada escalón más alto es más oscuro (menos luminoso)
        // que el anterior.
        $scale = ColorScale::fromHex('#4f46e5');
        $luminosidad = fn (string $hex) => array_sum(sscanf($hex, '#%02x%02x%02x'));

        $previa = null;
        foreach ($scale as $hex) {
            $actual = $luminosidad($hex);
            if ($previa !== null) {
                $this->assertLessThan($previa, $actual);
            }
            $previa = $actual;
        }
    }

    public function test_acepta_el_hex_sin_el_simbolo_numeral(): void
    {
        $this->assertSame(ColorScale::fromHex('#4f46e5'), ColorScale::fromHex('4f46e5'));
    }

    public function test_un_color_distinto_da_una_escala_distinta(): void
    {
        $this->assertNotSame(ColorScale::fromHex('#4f46e5'), ColorScale::fromHex('#e54f46'));
    }
}
