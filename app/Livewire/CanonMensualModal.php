<?php

namespace App\Livewire;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\CanonPago;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $this->initPoint = $this->crearPreferencia($accessToken);
        $this->mostrarModal = $this->initPoint !== '';
    }

    // El mismo MP_JJSOFTWARE_ACCESS_TOKEN se usa para TODOS los clientes de
    // jjsoftware (una sola cuenta de MP cobrandole a todos). Sin esta
    // referencia, un Admin que visite una URL con ?collection_id=<ID de
    // CUALQUIER pago aprobado real de esa cuenta, de cualquier cliente o
    // monto> podria marcar el canon de este sitio como pagado sin que exista
    // una transaccion real hecha para este sitio/mes. Ata cada preferencia a
    // instalacion+mes y se vuelve a chequear al confirmar el pago.
    private function referenciaExterna(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost();

        return $host.'-canon-'.now()->format('Y-m');
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
        try {
            $response = Http::asJson()->post(
                'https://api.mercadopago.com/checkout/preferences?access_token='.$accessToken,
                [
                    'items' => [[
                        'title' => 'Servicio Mensual',
                        'quantity' => 1,
                        'unit_price' => $this->monto,
                    ]],
                    'external_reference' => $this->referenciaExterna(),
                    'back_urls' => [
                        'success' => route('dashboard'),
                    ],
                    'auto_return' => 'approved',
                ]
            );

            return (string) ($response->json('init_point') ?? '');
        } catch (ConnectionException $e) {
            // El modal vive en el layout global: si Mercado Pago esta caido
            // o no responde, no puede tirar 500 en TODAS las paginas que
            // carga un Admin. Se degrada a "no mostrar el cobro esta carga".
            Log::warning('Canon mensual: fallo al crear preferencia de MP', ['error' => $e->getMessage()]);

            return '';
        }
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
        try {
            $payment = Http::get("https://api.mercadopago.com/v1/payments/{$collectionId}", [
                'access_token' => $accessToken,
            ])->json();
        } catch (ConnectionException $e) {
            Log::warning('Canon mensual: fallo al verificar pago contra la API de MP', ['error' => $e->getMessage()]);

            return;
        }

        if (($payment['status'] ?? null) !== 'approved') {
            $this->toastError('El pago no fue aprobado por Mercado Pago (estado: '.($payment['status'] ?? 'desconocido').'). No se registró.');

            return;
        }

        // Mismo token de MP para todos los clientes: sin este chequeo,
        // cualquier pago aprobado real de la cuenta (de otro cliente, otro
        // mes, o un monto menor) podria reusarse via ?collection_id=... para
        // marcar el canon de este sitio como pagado. Ver referenciaExterna().
        if (($payment['external_reference'] ?? null) !== $this->referenciaExterna()) {
            $this->toastError('El pago no corresponde a este sitio o período. No se registró.');
            Log::warning('Canon mensual: external_reference no coincide, posible reuso de un pago de otra instalación', [
                'collection_id' => $collectionId,
                'esperada' => $this->referenciaExterna(),
                'recibida' => $payment['external_reference'] ?? null,
            ]);

            return;
        }

        if ((float) ($payment['transaction_amount'] ?? 0) < $this->monto) {
            $this->toastError('El monto del pago no coincide con el canon mensual. No se registró.');
            Log::warning('Canon mensual: monto insuficiente', [
                'collection_id' => $collectionId,
                'esperado' => $this->monto,
                'recibido' => $payment['transaction_amount'] ?? null,
            ]);

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
            // recargo la URL de retorno de MP (F5), no es un error real. Pero
            // si la causa es otra (ej. columna nueva sin migrar), no
            // queremos perderlo en silencio.
            if (! str_contains($e->getMessage(), 'Duplicate entry')) {
                Log::error('Canon mensual: fallo inesperado al registrar el pago', [
                    'collection_id' => $collectionId,
                    'error' => $e->getMessage(),
                ]);
            }
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
