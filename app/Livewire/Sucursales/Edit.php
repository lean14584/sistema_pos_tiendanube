<?php

namespace App\Livewire\Sucursales;

use App\Models\Invoice;
use App\Models\PuntoVenta;
use App\Models\Sucursal;
use App\Models\SucursalMercadoPagoConfig;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Edit extends Component
{
    use WithFileUploads;

    public Sucursal $sucursal;

    public string $name = '';

    public string $razon_social = '';

    public bool $active = true;

    public string $nuevoPuntoVentaNumero = '';

    public string $nuevoPuntoVentaNombre = '';

    /** Archivo recién seleccionado, pendiente de guardar (null = no tocar el logo actual). */
    public $logo = null;

    // --- Mercado Pago (ver SucursalMercadoPagoConfig) ---
    public string $mp_access_token = '';

    public string $mp_store_external_id = '';

    public string $mp_pos_external_id = '';

    public string $mp_store_name = '';

    public string $mp_pos_name = '';

    public function mount(Sucursal $sucursal): void
    {
        $this->sucursal = $sucursal;
        $this->name = $sucursal->name;
        $this->razon_social = $sucursal->razon_social;
        $this->active = $sucursal->active;

        $mp = $sucursal->mercadoPagoConfig;
        // El access_token NO se precarga (mismo criterio que Tiendanube\Index):
        // un input type=password con el valor real lo mandaría en texto plano
        // al navegador. Se muestra solo "cargado/no cargado" (ver
        // mpTokenCargado()); vacío al guardar significa "no cambiar".
        $this->mp_store_external_id = (string) ($mp->store_external_id ?? '');
        $this->mp_pos_external_id = (string) ($mp->pos_external_id ?? '');
        $this->mp_store_name = (string) ($mp->store_name ?? '');
        $this->mp_pos_name = (string) ($mp->pos_name ?? '');
    }

    public function mpTokenCargado(): bool
    {
        return filled($this->sucursal->mercadoPagoConfig?->access_token);
    }

    /** Puntos de venta de esta sucursal, más nuevos primero para ver rápido lo recién agregado. */
    public function puntosVenta()
    {
        return $this->sucursal->puntosVenta()->orderByDesc('id')->get();
    }

    public function agregarPuntoVenta(): void
    {
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
        session()->flash('status', 'Punto de venta agregado.');
    }

    /** No deja apagar el único punto de venta activo: rompería la facturación de esta sucursal. */
    public function togglePuntoVenta(int $id): void
    {
        $pv = $this->sucursal->puntosVenta()->findOrFail($id);

        if ($pv->active) {
            $quedanActivos = $this->sucursal->puntosVenta()->where('active', true)->where('id', '!=', $id)->exists();

            if (! $quedanActivos) {
                $this->addError('puntosVenta', 'Tiene que quedar al menos un punto de venta activo en esta sucursal.');

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
        $pv = $this->sucursal->puntosVenta()->findOrFail($id);

        if ($this->sucursal->puntosVenta()->count() <= 1) {
            $this->addError('puntosVenta', 'Tiene que quedar al menos un punto de venta en esta sucursal.');

            return;
        }

        if (Invoice::where('punto_venta', $pv->numero)->exists()) {
            $this->addError('puntosVenta', 'Ese punto de venta ya tiene facturas emitidas: desactivalo en vez de borrarlo.');

            return;
        }

        $pv->delete();
        session()->flash('status', 'Punto de venta eliminado.');
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'razon_social' => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'mp_access_token' => ['nullable', 'string', 'max:255'],
            'mp_store_external_id' => ['nullable', 'string', 'max:100'],
            'mp_pos_external_id' => ['nullable', 'string', 'max:100'],
            'mp_store_name' => ['nullable', 'string', 'max:255'],
            'mp_pos_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->logo) {
            if ($this->sucursal->logo_path) {
                Storage::disk('public')->delete($this->sucursal->logo_path);
            }

            $data['logo_path'] = $this->logo->store('sucursal-logos', 'public');
        }
        unset($data['logo']);

        $hayDatosDeMp = filled($data['mp_access_token']) || filled($data['mp_store_external_id'])
            || filled($data['mp_pos_external_id']) || filled($data['mp_store_name']) || filled($data['mp_pos_name']);

        if ($hayDatosDeMp || $this->sucursal->mercadoPagoConfig) {
            $mpData = [
                'store_external_id' => $data['mp_store_external_id'] ?: null,
                'pos_external_id' => $data['mp_pos_external_id'] ?: null,
                'store_name' => $data['mp_store_name'] ?: null,
                'pos_name' => $data['mp_pos_name'] ?: null,
            ];
            // Campo vacío = "no cambiar" el token ya guardado (nunca se
            // precarga el real, así que no hay forma de distinguir "lo vacié
            // a propósito" de "no lo toqué").
            if (filled($data['mp_access_token'])) {
                $mpData['access_token'] = $data['mp_access_token'];
            }

            SucursalMercadoPagoConfig::updateOrCreate(['sucursal_id' => $this->sucursal->id], $mpData);
        }

        unset($data['mp_access_token'], $data['mp_store_external_id'], $data['mp_pos_external_id'], $data['mp_store_name'], $data['mp_pos_name']);

        $this->sucursal->update($data);

        session()->flash('status', 'Sucursal actualizada.');
        $this->redirect(route('sucursales.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.sucursales.edit');
    }
}
