<?php

namespace App\Livewire\CompanySettings;

use App\Enums\CondicionIva;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\SucursalMercadoPagoConfig;
use App\Support\ThemeCss;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Edit extends Component
{
    use ShowsToasts, WithFileUploads;

    public CompanySettings $company;

    public string $cuit = '';

    public string $razon_social = '';

    public string $nombre_fantasia = '';

    public string $domicilio = '';

    public string $condicion_iva = 'responsable_inscripto';

    public bool $factura_a_habilitada = true;

    public bool $factura_b_habilitada = true;

    public bool $factura_c_habilitada = false;

    public bool $barcode_scale_enabled = false;

    public string $barcode_scale_prefix = '20';

    public string $barcode_scale_code_digits = '5';

    public string $barcode_scale_weight_digits = '5';

    /** % de descuento por pago de contado en efectivo/transferencia (0 = sin descuento). */
    public string $descuento_efectivo_pct = '0';

    public string $descuento_transferencia_pct = '0';

    /** Vacío = paleta de fábrica (indigo/sky), sin ningún CSS extra inyectado. */
    public string $theme_primary_color = '';

    public string $theme_secondary_color = '';

    /** Archivo recién seleccionado, pendiente de guardar (null = no tocar el logo actual). */
    public $logo = null;

    /** Certificado y clave privada de AFIP recién seleccionados (null = no tocar los actuales). */
    public $cert = null;

    public $key = null;

    // --- Sucursal única + Mercado Pago (solo sin multisucursal, ver mount())
    // Este bloque fusiona lo que antes vivía exclusivamente en
    // Sucursales\Edit — ese componente y su ruta siguen existiendo igual
    // (no se tocan), pero con multisucursal apagado el menú ya no linkea ahí:
    // ahora se edita todo desde "Datos de la empresa" directamente. ---
    public ?Sucursal $sucursal = null;

    public string $sucursal_name = '';

    public string $sucursal_razon_social = '';

    public bool $sucursal_active = true;

    public string $nuevoPuntoVentaNumero = '';

    public string $nuevoPuntoVentaNombre = '';

    public string $mp_access_token = '';

    public string $mp_webhook_secret = '';

    public string $mp_store_external_id = '';

    public string $mp_pos_external_id = '';

    public string $mp_store_name = '';

    public string $mp_pos_name = '';

    public function mount(): void
    {
        $this->company = CompanySettings::current();
        $this->cuit = $this->company->cuit;
        $this->razon_social = $this->company->razon_social;
        $this->nombre_fantasia = (string) $this->company->nombre_fantasia;
        $this->domicilio = (string) $this->company->domicilio;
        $this->condicion_iva = $this->company->condicion_iva->value;
        $this->factura_a_habilitada = $this->company->factura_a_habilitada;
        $this->factura_b_habilitada = $this->company->factura_b_habilitada;
        $this->factura_c_habilitada = $this->company->factura_c_habilitada;
        $this->barcode_scale_enabled = $this->company->barcode_scale_enabled;
        $this->barcode_scale_prefix = (string) ($this->company->barcode_scale_prefix ?? '20');
        $this->barcode_scale_code_digits = (string) $this->company->barcode_scale_code_digits;
        $this->barcode_scale_weight_digits = (string) $this->company->barcode_scale_weight_digits;
        $this->descuento_efectivo_pct = (string) $this->company->descuento_efectivo_pct;
        $this->descuento_transferencia_pct = (string) $this->company->descuento_transferencia_pct;
        $this->theme_primary_color = (string) $this->company->theme_primary_color;
        $this->theme_secondary_color = (string) $this->company->theme_secondary_color;

        if (! config('features.multisucursal')) {
            $this->sucursal = Sucursal::query()->orderBy('id')->first();

            if ($this->sucursal) {
                $this->sucursal_name = $this->sucursal->name;
                $this->sucursal_razon_social = $this->sucursal->razon_social;
                $this->sucursal_active = $this->sucursal->active;

                $mp = $this->sucursal->mercadoPagoConfig;
                // El access_token/webhook_secret NO se precargan (mismo
                // criterio que Sucursales\Edit): un input type=password con
                // el valor real lo mandaría en texto plano al navegador.
                $this->mp_store_external_id = (string) ($mp->store_external_id ?? '');
                $this->mp_pos_external_id = (string) ($mp->pos_external_id ?? '');
                $this->mp_store_name = (string) ($mp->store_name ?? '');
                $this->mp_pos_name = (string) ($mp->pos_name ?? '');
            }
        }
    }

    public function mpTokenCargado(): bool
    {
        return filled($this->sucursal?->mercadoPagoConfig?->access_token);
    }

    public function mpWebhookSecretCargado(): bool
    {
        return filled($this->sucursal?->mercadoPagoConfig?->webhook_secret);
    }

    /** Puntos de venta de la única sucursal, más nuevos primero para ver rápido lo recién agregado. */
    public function puntosVenta()
    {
        return $this->sucursal?->puntosVenta()->orderByDesc('id')->get() ?? collect();
    }

    public function agregarPuntoVenta(): void
    {
        if (! $this->sucursal) {
            return;
        }

        $data = $this->validate([
            'nuevoPuntoVentaNumero' => ['required', 'integer', 'min:1', 'max:9999', 'unique:puntos_venta,numero'],
            'nuevoPuntoVentaNombre' => ['nullable', 'string', 'max:100'],
        ], [], ['nuevoPuntoVentaNumero' => 'número de punto de venta']);

        PuntoVenta::create([
            'sucursal_id' => $this->sucursal->id,
            'numero' => $data['nuevoPuntoVentaNumero'],
            'nombre' => $data['nuevoPuntoVentaNombre'] ?: null,
            'active' => true,
        ]);

        $this->reset('nuevoPuntoVentaNumero', 'nuevoPuntoVentaNombre');
        $this->toastSuccess('Punto de venta agregado.');
    }

    /** No deja apagar el único punto de venta activo: rompería la facturación. */
    public function togglePuntoVenta(int $id): void
    {
        if (! $this->sucursal) {
            return;
        }

        $pv = $this->sucursal->puntosVenta()->findOrFail($id);

        if ($pv->active) {
            $quedanActivos = $this->sucursal->puntosVenta()->where('active', true)->where('id', '!=', $id)->exists();

            if (! $quedanActivos) {
                $this->addError('puntosVenta', 'Tiene que quedar al menos un punto de venta activo.');

                return;
            }
        }

        $pv->update(['active' => ! $pv->active]);
    }

    /**
     * Solo se puede borrar un punto de venta que nunca se usó (sin ninguna
     * factura emitida con ese número) — si ya facturó algo, desactivarlo en
     * vez de borrarlo, para no perder trazabilidad de ese historial.
     */
    public function eliminarPuntoVenta(int $id): void
    {
        if (! $this->sucursal) {
            return;
        }

        $pv = $this->sucursal->puntosVenta()->findOrFail($id);

        if ($this->sucursal->puntosVenta()->count() <= 1) {
            $this->addError('puntosVenta', 'Tiene que quedar al menos un punto de venta.');

            return;
        }

        if (Invoice::where('punto_venta', $pv->numero)->exists()) {
            $this->addError('puntosVenta', 'Ese punto de venta ya tiene facturas emitidas: desactivalo en vez de borrarlo.');

            return;
        }

        $pv->delete();
        $this->toastSuccess('Punto de venta eliminado.');
    }

    /**
     * Reacciona al cambio del select "Condición ante IVA": la condición
     * determina de forma excluyente qué tipos de comprobante puede emitir
     * la empresa (ver ComprobanteResolver) — no tiene sentido pedirle al
     * usuario que además marque/desmarque los toggles a mano ni arriesgarse
     * a que quede un estado inconsistente. Responsable Inscripto habilita
     * A y B (elige entre las dos según a quién le vende); Monotributista y
     * Exento habilitan únicamente C.
     */
    public function updatedCondicionIva(string $value): void
    {
        $esMonotributistaOExento = in_array($value, ['monotributista', 'exento'], true);

        $this->factura_a_habilitada = ! $esMonotributistaOExento;
        $this->factura_b_habilitada = ! $esMonotributistaOExento;
        $this->factura_c_habilitada = $esMonotributistaOExento;
    }

    /** ¿Ya hay un certificado AFIP cargado en el servidor? */
    public function certCargado(): bool
    {
        return File::exists(config('afip.cert_path'));
    }

    /** ¿Ya hay una clave privada AFIP cargada en el servidor? */
    public function keyCargada(): bool
    {
        return File::exists(config('afip.key_path'));
    }

    public function save(): void
    {
        $rules = [
            // El CUIT no es obligatorio para guardar el resto de los datos
            // (nombre, logo, etc.) — antes lo era, y bloqueaba TODO el
            // formulario (incluida la razón social) hasta tener el trámite
            // de AFIP resuelto. Sigue validado en formato si se carga.
            'cuit' => ['nullable', 'digits:11'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'condicion_iva' => ['required', Rule::enum(CondicionIva::class)],
            'factura_a_habilitada' => ['boolean'],
            'factura_b_habilitada' => ['boolean'],
            'factura_c_habilitada' => ['boolean'],
            'barcode_scale_enabled' => ['boolean'],
            'barcode_scale_prefix' => ['required_if:barcode_scale_enabled,true', 'nullable', 'digits_between:1,4'],
            'barcode_scale_code_digits' => ['required_if:barcode_scale_enabled,true', 'nullable', 'integer', 'min:1', 'max:9'],
            'barcode_scale_weight_digits' => ['required_if:barcode_scale_enabled,true', 'nullable', 'integer', 'min:1', 'max:9'],
            'descuento_efectivo_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'descuento_transferencia_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'theme_primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // 'image' de Laravel admite SVG, que puede llevar <script>
            // embebido (XSS almacenado si algún día se sirve el archivo
            // fuera de un <img>). Se restringe a los formatos rasterizados
            // que realmente hacen falta para un logo.
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // El certificado y la clave son texto (PEM); se validan por
            // extensión más abajo porque su mime no es confiable.
            'cert' => ['nullable', 'file', 'max:200'],
            'key' => ['nullable', 'file', 'max:200'],
        ];

        // Campos de la única sucursal + Mercado Pago, fusionados en esta
        // misma pantalla cuando multisucursal está apagado (ver mount()).
        if (! config('features.multisucursal') && $this->sucursal) {
            $rules['sucursal_name'] = ['required', 'string', 'max:255'];
            $rules['sucursal_razon_social'] = ['required', 'string', 'max:255'];
            $rules['sucursal_active'] = ['boolean'];
            $rules['mp_access_token'] = ['nullable', 'string', 'max:255'];
            $rules['mp_webhook_secret'] = ['nullable', 'string', 'max:500'];
            $rules['mp_store_external_id'] = ['nullable', 'string', 'max:100'];
            $rules['mp_pos_external_id'] = ['nullable', 'string', 'max:100'];
            $rules['mp_store_name'] = ['nullable', 'string', 'max:255'];
            $rules['mp_pos_name'] = ['nullable', 'string', 'max:255'];
        }

        $data = $this->validate($rules);

        $this->validarExtension('cert', ['crt', 'pem', 'cer']);
        $this->validarExtension('key', ['key', 'pem']);
        $this->validarContenidoAfip();

        if ($this->getErrorBag()->hasAny(['cert', 'key'])) {
            return;
        }

        // Regla dura de AFIP (ver ComprobanteResolver::assertEmisorPuedeForzar):
        // Responsable Inscripto nunca emite C, Monotributista/Exento nunca
        // emite A/B. Se corta acá para no descubrir la combinación inválida
        // recién al intentar emitir una factura.
        $condicionIva = CondicionIva::from($data['condicion_iva']);
        $esMonotributistaOExento = in_array($condicionIva, [CondicionIva::Monotributista, CondicionIva::Exento], true);

        if ($esMonotributistaOExento && ($data['factura_a_habilitada'] || $data['factura_b_habilitada'])) {
            $this->addError('condicion_iva', "Una empresa {$condicionIva->label()} no puede emitir Factura A ni B — destildá esas opciones o cambiá la condición ante IVA.");

            return;
        }

        if (! $esMonotributistaOExento && $data['factura_c_habilitada']) {
            $this->addError('condicion_iva', "Una empresa {$condicionIva->label()} no puede emitir Factura C — destildá esa opción o cambiá la condición ante IVA.");

            return;
        }

        // El código de balanza es EAN-13: prefijo + código + peso + 1 dígito
        // verificador tienen que sumar exactamente 13, si no el checksum de
        // ScaleBarcodeParser nunca va a coincidir y ningún escaneo va a matchear.
        if ($this->barcode_scale_enabled) {
            $total = strlen($data['barcode_scale_prefix']) + (int) $data['barcode_scale_code_digits'] + (int) $data['barcode_scale_weight_digits'] + 1;

            if ($total !== 13) {
                $this->addError('barcode_scale_weight_digits', "Prefijo + código + peso + dígito verificador tienen que sumar 13 en total (ahora suman {$total}).");

                return;
            }
        }

        if ($this->logo) {
            if ($this->company->logo_path) {
                Storage::disk('public')->delete($this->company->logo_path);
            }

            $data['logo_path'] = $this->logo->store('company-logos', 'public');
        }

        // El certificado y la clave se guardan en storage/afip (fuera de la
        // carpeta pública) exactamente en las rutas que espera el gateway.
        if ($this->cert) {
            $this->guardarArchivoAfip($this->cert, config('afip.cert_path'));
        }
        if ($this->key) {
            $this->guardarArchivoAfip($this->key, config('afip.key_path'));
        }

        // Vacío => null (paleta de fábrica), no cadena vacía — más prolijo
        // en la base y ThemeCss::render() los trata igual de cualquier modo.
        $data['theme_primary_color'] = $data['theme_primary_color'] ? strtolower($data['theme_primary_color']) : null;
        $data['theme_secondary_color'] = $data['theme_secondary_color'] ? strtolower($data['theme_secondary_color']) : null;

        if (! config('features.multisucursal') && $this->sucursal) {
            $this->sucursal->update([
                'name' => $data['sucursal_name'],
                'razon_social' => $data['sucursal_razon_social'],
                'active' => $data['sucursal_active'],
            ]);

            $hayDatosDeMp = filled($data['mp_access_token']) || filled($data['mp_webhook_secret']) || filled($data['mp_store_external_id'])
                || filled($data['mp_pos_external_id']) || filled($data['mp_store_name']) || filled($data['mp_pos_name']);

            if ($hayDatosDeMp || $this->sucursal->mercadoPagoConfig) {
                $mpData = [
                    'store_external_id' => $data['mp_store_external_id'] ?: null,
                    'pos_external_id' => $data['mp_pos_external_id'] ?: null,
                    'store_name' => $data['mp_store_name'] ?: null,
                    'pos_name' => $data['mp_pos_name'] ?: null,
                ];
                // Campo vacío = "no cambiar" el token/secret ya guardado
                // (nunca se precarga el real, ver mount()).
                if (filled($data['mp_access_token'])) {
                    $mpData['access_token'] = $data['mp_access_token'];
                }
                if (filled($data['mp_webhook_secret'])) {
                    $mpData['webhook_secret'] = $data['mp_webhook_secret'];
                }

                SucursalMercadoPagoConfig::updateOrCreate(['sucursal_id' => $this->sucursal->id], $mpData);
            }
        }

        unset(
            $data['logo'], $data['cert'], $data['key'],
            $data['sucursal_name'], $data['sucursal_razon_social'], $data['sucursal_active'],
            $data['mp_access_token'], $data['mp_webhook_secret'], $data['mp_store_external_id'],
            $data['mp_pos_external_id'], $data['mp_store_name'], $data['mp_pos_name'],
        );

        $this->company->update($data);
        $this->logo = null;
        $this->cert = null;
        $this->key = null;

        $this->toastSuccess('Datos de la empresa actualizados.');

        // wire:navigate no gestiona <style> sueltos en el <head> (solo
        // <link>/<script> marcados con data-navigate-track), así que una
        // redirección a sí misma no alcanzaría para que el cambio de color
        // se vea sin un F5 manual. Se actualiza el tag en caliente por JS en
        // vez de depender de eso — ver ThemeCss::vars().
        $cssText = ':root{'.ThemeCss::vars().'}';
        $this->js(
            '(function () {'
            .'var el = document.getElementById("theme-vars");'
            .'if (! el) { el = document.createElement("style"); el.id = "theme-vars"; document.head.appendChild(el); }'
            .'el.textContent = '.json_encode($cssText).';'
            .'})();'
        );
    }

    /**
     * @param  array<int, string>  $extensiones
     */
    private function validarExtension(string $campo, array $extensiones): void
    {
        if (! $this->{$campo}) {
            return;
        }

        $ext = strtolower($this->{$campo}->getClientOriginalExtension());

        if (! in_array($ext, $extensiones, true)) {
            $this->addError($campo, 'Extensión inválida. Se esperaba: .'.implode(', .', $extensiones));
        }
    }

    /**
     * La extensión sola no garantiza nada (un .crt puede tener cualquier
     * contenido): parsea el certificado/clave de verdad antes de aceptarlos,
     * para no quedar sin poder facturar A/B por haber subido, sin darse
     * cuenta, un archivo corrupto o que no es lo que dice ser.
     */
    private function validarContenidoAfip(): void
    {
        if ($this->cert && ! $this->getErrorBag()->has('cert')) {
            $contenido = file_get_contents($this->cert->getRealPath());

            if ($contenido === false || openssl_x509_parse($contenido) === false) {
                $this->addError('cert', 'El archivo no es un certificado X.509 válido (PEM/DER). Revisá que sea el archivo correcto.');
            }
        }

        if ($this->key && ! $this->getErrorBag()->has('key')) {
            $contenido = file_get_contents($this->key->getRealPath());

            if ($contenido === false || openssl_pkey_get_private($contenido) === false) {
                $this->addError('key', 'El archivo no es una clave privada válida (PEM). Si tiene contraseña, subila sin contraseña (sin encriptar).');
            }
        }
    }

    private function guardarArchivoAfip($archivo, string $destino): void
    {
        File::ensureDirectoryExists(dirname($destino));
        File::copy($archivo->getRealPath(), $destino);
    }

    public function render()
    {
        return view('livewire.company-settings.edit', [
            'condicionIvaOptions' => CondicionIva::cases(),
            'certCargado' => $this->certCargado(),
            'keyCargada' => $this->keyCargada(),
            'puntosVenta' => $this->puntosVenta(),
            'mpTokenCargado' => $this->mpTokenCargado(),
            'mpWebhookSecretCargado' => $this->mpWebhookSecretCargado(),
        ]);
    }
}
