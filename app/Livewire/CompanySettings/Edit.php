<?php

namespace App\Livewire\CompanySettings;

use App\Enums\CondicionIva;
use App\Models\CompanySettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Edit extends Component
{
    use WithFileUploads;

    public CompanySettings $company;

    public string $cuit = '';

    public string $razon_social = '';

    public string $nombre_fantasia = '';

    public string $domicilio = '';

    public string $punto_venta = '1';

    public string $condicion_iva = 'responsable_inscripto';

    public bool $factura_a_habilitada = true;

    public bool $factura_b_habilitada = true;

    /** Archivo recién seleccionado, pendiente de guardar (null = no tocar el logo actual). */
    public $logo = null;

    /** Certificado y clave privada de AFIP recién seleccionados (null = no tocar los actuales). */
    public $cert = null;

    public $key = null;

    public function mount(): void
    {
        $this->company = CompanySettings::current();
        $this->cuit = $this->company->cuit;
        $this->razon_social = $this->company->razon_social;
        $this->nombre_fantasia = (string) $this->company->nombre_fantasia;
        $this->domicilio = (string) $this->company->domicilio;
        $this->punto_venta = (string) $this->company->punto_venta;
        $this->condicion_iva = $this->company->condicion_iva->value;
        $this->factura_a_habilitada = $this->company->factura_a_habilitada;
        $this->factura_b_habilitada = $this->company->factura_b_habilitada;
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
        $data = $this->validate([
            'cuit' => ['required', 'digits:11'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'punto_venta' => ['required', 'integer', 'min:1', 'max:9999'],
            'condicion_iva' => ['required', Rule::enum(CondicionIva::class)],
            'factura_a_habilitada' => ['boolean'],
            'factura_b_habilitada' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
            // El certificado y la clave son texto (PEM); se validan por
            // extensión más abajo porque su mime no es confiable.
            'cert' => ['nullable', 'file', 'max:200'],
            'key' => ['nullable', 'file', 'max:200'],
        ]);

        $this->validarExtension('cert', ['crt', 'pem', 'cer']);
        $this->validarExtension('key', ['key', 'pem']);

        if ($this->getErrorBag()->hasAny(['cert', 'key'])) {
            return;
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

        unset($data['logo'], $data['cert'], $data['key']);

        $this->company->update($data);
        $this->logo = null;
        $this->cert = null;
        $this->key = null;

        session()->flash('status', 'Datos de la empresa actualizados.');
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
        ]);
    }
}
