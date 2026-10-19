<?php

namespace Tests\Feature;

use App\Models\CompanySettings;
use App\Support\ThemeCss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeCssTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_colores_cargados_no_genera_nada(): void
    {
        $this->assertSame('', ThemeCss::render());
    }

    public function test_con_un_color_principal_genera_las_variables_de_indigo(): void
    {
        CompanySettings::current()->update(['theme_primary_color' => '#ff5733']);

        $css = ThemeCss::render();

        $this->assertStringContainsString('<style id="theme-vars">', $css);
        $this->assertStringContainsString('--color-indigo-600:', $css);
        $this->assertStringContainsString('--color-indigo-950:', $css);
        // Sin color secundario cargado, no se toca sky.
        $this->assertStringNotContainsString('--color-sky-', $css);
    }

    public function test_con_los_dos_colores_genera_indigo_y_sky(): void
    {
        CompanySettings::current()->update([
            'theme_primary_color' => '#ff5733',
            'theme_secondary_color' => '#00aabb',
        ]);

        $css = ThemeCss::render();

        $this->assertStringContainsString('--color-indigo-600:', $css);
        $this->assertStringContainsString('--color-sky-600:', $css);
    }
}
