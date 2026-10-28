<?php

namespace App\Livewire\Tienda;

use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\Sucursal;
use App\Models\TiendaPedido;
use App\Services\MercadoPago\MercadoPagoCheckoutProService;
use App\Support\InvoiceNumberGenerator;
use App\Support\StockAdjuster;
use App\Support\TiendaCart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Confirma el pedido reservando stock YA (crea un Remito, no una Factura
 * fiscal — ver app/Enums/TipoComprobanteInterno.php: RemitoX no se emite a
 * AFIP; eso pasa recién después, ver InvoiceObserver). Si Mercado Pago está
 * configurado para la sucursal de la tienda, el cliente elige entre pagar
 * ahí (Checkout Pro) o al retirar en el local ($payment_choice); si MP no
 * está configurado no hay nada que elegir, el pedido queda igual reservado
 * y se coordina el pago al retirar — así el flujo sigue siendo probable de
 * punta a punta en una instalación que todavía no cargó credenciales de
 * Mercado Pago.
 */
#[Layout('layouts.tienda')]
class Checkout extends Component
{
    public string $customer_name = '';

    public string $customer_email = '';

    public string $customer_phone = '';

    /**
     * 'mercadopago' | 'retiro'. Solo tiene sentido elegir cuando Mercado Pago
     * está configurado para la sucursal (ver render()) — si no lo está, el
     * pedido siempre queda pendiente para coordinar el pago al retirar,
     * igual que antes de que existiera esta elección.
     */
    public string $payment_choice = 'mercadopago';

    /** Si hay un cliente logueado, precarga sus datos (los puede seguir editando). */
    public function mount(): void
    {
        if ($customer = Auth::guard('customer')->user()) {
            $this->customer_name = $customer->name;
            $this->customer_email = $customer->email;
            $this->customer_phone = (string) $customer->phone;
        }
    }

    protected function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'payment_choice' => ['required', 'in:mercadopago,retiro'],
        ];
    }

    public function confirmar()
    {
        $this->validate();

        $lines = TiendaCart::lines();

        if ($lines->isEmpty()) {
            $this->addError('cart', 'Tu carrito está vacío.');

            return;
        }

        $sucursalId = CompanySettings::current()->tiendaOnlineSucursalId();

        if ($sucursalId === null) {
            $this->addError('cart', 'La tienda todavía no tiene una sucursal configurada. Contactanos para completar tu pedido.');

            return;
        }

        $cartItems = $lines->map(fn (array $line) => [
            'product_id' => $line['product']->id,
            'quantity' => $line['quantity'],
        ])->all();

        $tipo = TipoComprobanteInterno::RemitoX;
        $puntoVentaNumero = Sucursal::find($sucursalId)?->puntoVentaPorDefecto()?->numero ?? 1;
        $reservaMinutos = CompanySettings::current()->tienda_online_reserva_minutos;
        $token = (string) Str::uuid();

        try {
            // Lock por sesión: evita que un doble clic o dos pestañas del
            // mismo navegador generen dos pedidos/remitos del mismo carrito
            // (no hay usuario logueado acá, así que no hay (sucursal,user)
            // como en Pos\Index::cobrar() — la sesión de invitado es lo más
            // parecido a una identidad estable que tenemos).
            $pedido = Cache::lock('tienda:checkout:'.session()->getId(), 10)->block(5, function () use (
                $lines, $cartItems, $tipo, $sucursalId, $puntoVentaNumero, $reservaMinutos, $token,
            ) {
                return InvoiceNumberGenerator::withLock($tipo->value, fn () => DB::transaction(function () use (
                    $lines, $cartItems, $tipo, $sucursalId, $puntoVentaNumero, $reservaMinutos, $token,
                ) {
                    $invoice = Invoice::create([
                        'number' => InvoiceNumberGenerator::next($tipo->value, $sucursalId, $puntoVentaNumero),
                        // Sin login de cliente (ver plan de la tienda): el
                        // contacto real del pedido queda en TiendaPedido;
                        // acá se usa el mismo "Consumidor Final" que el POS
                        // para ventas de mostrador, no se crea un Client por
                        // cada pedido web.
                        'client_id' => Client::consumidorFinal()->id,
                        'sucursal_id' => $sucursalId,
                        'punto_venta' => $puntoVentaNumero,
                        'tipo_comprobante_interno' => $tipo,
                        'issue_date' => now()->toDateString(),
                        'due_date' => now()->toDateString(),
                        'tax_rate' => 0,
                        'status' => 'pending',
                        'notes' => 'Pedido de la tienda online.',
                        // Mismo campo que ya usa el QR presencial (ver
                        // MercadoPagoWebhookController) — así el webhook
                        // público existente encuentra y paga esta factura
                        // sin necesitar un endpoint nuevo.
                        'mp_external_reference' => $token,
                    ]);

                    foreach ($lines as $line) {
                        $invoice->items()->create([
                            'product_id' => $line['product']->id,
                            'description' => $line['product']->name,
                            'quantity' => $line['quantity'],
                            'unit_price' => $line['product']->price,
                            'discount_percent' => 0,
                            'iva_rate' => $line['product']->iva_rate,
                        ]);
                    }

                    // apply() valida stock suficiente internamente (lanza
                    // InsufficientStockException) y toma el lock de las filas
                    // de products involucradas hasta el commit — mismo
                    // mecanismo que usa Pos\Index::cobrarInterno().
                    StockAdjuster::apply($cartItems, $tipo->stockSign(), $sucursalId);

                    $pedido = TiendaPedido::create([
                        'token' => $token,
                        'customer_id' => Auth::guard('customer')->id(),
                        'invoice_id' => $invoice->id,
                        'sucursal_id' => $sucursalId,
                        'status' => 'pending_payment',
                        'customer_name' => $this->customer_name,
                        'customer_email' => $this->customer_email,
                        'customer_phone' => $this->customer_phone ?: null,
                        'cart_snapshot' => $cartItems,
                        'expires_at' => now()->addMinutes($reservaMinutos),
                    ]);

                    // No se podía setear al crear el Invoice: el pedido
                    // todavía no existía (depende del invoice_id). Ver
                    // InvoiceObserver, que necesita este campo para saber
                    // que un pago confirmado corresponde a un pedido web.
                    $invoice->update(['tienda_pedido_id' => $pedido->id]);

                    return $pedido;
                }), $sucursalId, $puntoVentaNumero);
            });
        } catch (\App\Exceptions\InsufficientStockException $e) {
            $this->addError('cart', $e->getMessage());

            return;
        }

        TiendaCart::clear();

        // El stock ya está reservado en este punto pase lo que pase con
        // Mercado Pago: si no está configurado, o la API falla, el pedido
        // sigue existiendo y se coordina el pago al retirar (la reserva se
        // libera sola si nadie hace nada, ver tienda:release-expired-pedidos).
        $mp = app(MercadoPagoCheckoutProService::class);

        if ($mp->isConfigured($sucursalId) && $this->payment_choice === 'mercadopago') {
            try {
                $preferencia = $mp->createPreference($pedido->invoice->load('items'), $token, $this->customer_email);
                $pedido->update(['mp_preference_id' => $preferencia['id']]);

                $this->redirect($preferencia['init_point'], navigate: false);

                return;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->redirect(route('tienda.order-status', $pedido->token), navigate: true);
    }

    public function render()
    {
        $sucursalId = CompanySettings::current()->tiendaOnlineSucursalId();

        return view('livewire.tienda.checkout', [
            'lines' => TiendaCart::lines(),
            'total' => TiendaCart::total(),
            'mpConfigured' => $sucursalId !== null && app(MercadoPagoCheckoutProService::class)->isConfigured($sucursalId),
        ]);
    }
}
