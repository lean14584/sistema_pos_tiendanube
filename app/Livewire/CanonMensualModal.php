<?php

namespace App\Livewire;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\CanonPago;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

/**
 * Modal de cobro mensual del servicio (canon) a traves de la cuenta de
 * Mercado Pago de jjsoftware (el desarrollador), no del negocio. Vive en el
 * layout (layouts/app.blade.php) para evaluarse en cada carga de pagina de
 * un Administrador logueado - mismo mecanismo ya usado en otros clientes
 * (posOfflineDos y lapanalera).
 */
class CanonMensualModal extends Component
{
    use ShowsToasts;

    public bool $mostrarModal = false;

    public string $initPoint = '';

    public float $monto = 0;

    public function mount()
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->esAdminGlobal()) {
            return;
        }

        $accessToken = config('services.mercadopago_jjsoftware.access_token');
        $this->monto = (float) config('services.mercadopago_jjsoftware.monto_canon');

        if (! $accessToken) {
            // Sin credenciales cargadas todavia (instalacion nueva, u otro
            // cliente que no paga canon) - no reventar la pagina, simplemente
            // no mostrar el cobro.
            return;
        }

        $this->procesarRetornoDePago($accessToken, $user);

        $mes = (int) now()->format('m');
        $anio = (int) now()->format('Y');
        $dia = (int) now()->format('d');

        $yaHayPagoEsteMes = CanonPago::where('mes', $mes)->where('anio', $anio)->exists();

        if ($yaHayPagoEsteMes || $dia < 5) {
            return;
        }

        $this->mostrarModal = true;
        $this->initPoint = $this->crearPreferencia($accessToken);
    }

    // Se usa el link de checkout directo (init_point) en vez del widget JS
    // de Mercado Pago (mp.checkout()): ese widget arma el boton de forma
    // asincronica despues de cargar, y un click hecho antes de que termine
    // de resolverse simplemente no hace nada ("MercadoPago.js - You are
    // using open() before checkout instantiation has resolved", confirmado
    // en la version anterior de este mismo componente en posOfflineDos). Un
    // link comun al mismo destino no tiene esa carrera.
    private function crearPreferencia(string $accessToken): string
    {
        $response = Http::asJson()->post(
            'https://api.mercadopago.com/checkout/preferences?access_token='.$accessToken,
            [
                'items' => [[
                    'title' => 'Servicio Mensual',
                    'quantity' => 1,
                    'unit_price' => $this->monto,
                ]],
                'back_urls' => [
                    'success' => route('dashboard'),
                ],
                'auto_return' => 'approved',
            ]
        );

        return (string) ($response->json('init_point') ?? '');
    }

    private function procesarRetornoDePago(string $accessToken, User $user): void
    {
        $collectionId = request()->query('collection_id');
        $collectionStatus = request()->query('collection_status');

        if (! $collectionId || ! $collectionStatus || ! is_numeric($collectionId)) {
            return;
        }

        // No confiar en collection_status de la URL (se puede manipular a
        // mano) - confirmar el estado real contra la API de Mercado Pago.
        $payment = Http::get("https://api.mercadopago.com/v1/payments/{$collectionId}", [
            'access_token' => $accessToken,
        ])->json();

        if (($payment['status'] ?? null) !== 'approved') {
            $this->toastError('El pago no fue aprobado por Mercado Pago (estado: '.($payment['status'] ?? 'desconocido').'). No se registró.');

            return;
        }

        try {
            CanonPago::create([
                'user_id' => $user->id,
                'fecha_pago' => now()->toDateString(),
                'mp_payment_id' => $payment['id'],
                'monto' => $payment['transaction_amount'] ?? $this->monto,
                'mes' => now()->format('m'),
                'anio' => now()->format('Y'),
            ]);

            $this->toastSuccess('Pago registrado correctamente');
        } catch (QueryException $e) {
            // unique(mes, anio) o mp_payment_id ya insertado - el usuario
            // recargo la URL de retorno de MP (F5), no es un error real.
        }

        // Sin redirect: cortaria la respuesta HTTP (302) antes de que el
        // navegador llegue a ejecutar el $this->js() del toast recien
        // encolado, y el aviso de "pago registrado" nunca se veria. La URL
        // se queda con los parametros de retorno de MP hasta que el usuario
        // navegue, sin efecto real.
    }

    public function render()
    {
        return view('livewire.canon-mensual-modal');
    }
}
