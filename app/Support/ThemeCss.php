<?php

namespace App\Support;

use App\Models\CompanySettings;
use Illuminate\Support\Facades\Cache;

/**
 * Bloque <style> que redefine las variables --color-indigo-N y --color-sky-N
 * (los dos colores de marca usados en todo el sistema) a partir de los hex
 * que el admin cargue en Configuración. Si no hay nada cargado no devuelve
 * nada — cero cambio visual para las instalaciones que nunca tocan esto
 * (pos-tiendanube, DECO-HOGAR).
 */
class ThemeCss
{
    public static function render(): string
    {
        $company = CompanySettings::current();
        $primary = $company->theme_primary_color;
        $secondary = $company->theme_secondary_color;

        if (! $primary && ! $secondary) {
            return '';
        }

        // Función pura del par de hex: no hace falta invalidar nada, un
        // hex distinto es automáticamente otra entrada de caché.
        $vars = Cache::rememberForever(
            "theme-css:{$primary}:{$secondary}",
            fn () => self::buildVars($primary, $secondary)
        );

        return "<style>:root{{$vars}}</style>";
    }

    private static function buildVars(?string $primary, ?string $secondary): string
    {
        $css = '';

        if ($primary) {
            foreach (ColorScale::fromHex($primary) as $stop => $hex) {
                $css .= "--color-indigo-{$stop}:{$hex};";
            }
        }

        if ($secondary) {
            foreach (ColorScale::fromHex($secondary) as $stop => $hex) {
                $css .= "--color-sky-{$stop}:{$hex};";
            }
        }

        return $css;
    }
}
