<?php

namespace App\Services\Tiendanube;

use App\Enums\TipoComprobanteInterno;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sincronización de datos entre Tiendanube y el sistema. Cada método devuelve
 * un resumen de lo hecho para mostrarlo en pantalla.
 */
class TiendanubeSync
{
    public function __construct(private TiendanubeClient $client) {}

    /**
     * Trae los productos de Tiendanube y los crea/actualiza localmente,
     * vinculándolos por su id de Tiendanube (no duplica al re-importar).
     *
     * @return array{creados:int, actualizados:int}
     */
    public function importProducts(): array
    {
        $creados = 0;
        $actualizados = 0;
        $perPage = config('tiendanube.per_page');

        for ($page = 1; $page <= config('tiendanube.max_pages'); $page++) {
            $productos = $this->client->getProducts($page);

            if (empty($productos)) {
                break;
            }

            foreach ($productos as $tn) {
                $variante = $tn['variants'][0] ?? [];

                $existente = Product::where('tiendanube_product_id', $tn['id'])->first();

                $datos = [
                    'name' => $this->texto($tn['name'] ?? ''),
                    'price' => $this->numero($variante['price'] ?? 0),
                    'stock' => (int) ($variante['stock'] ?? 0),
                    'sku' => $variante['sku'] ?? null,
                    'tiendanube_product_id' => $tn['id'],
                    'tiendanube_variant_id' => $variante['id'] ?? null,
                ];

                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    Product::create($datos);
                    $creados++;
                }
            }

            if (count($productos) < $perPage) {
                break;
            }
        }

        return ['creados' => $creados, 'actualizados' => $actualizados];
    }

    /**
     * Trae los pedidos de Tiendanube y crea una factura (Remito interno) por
     * cada uno que todavía no se haya importado.
     *
     * @return array{importados:int, omitidos:int}
     */
    public function importOrders(): array
    {
        $importados = 0;
        $omitidos = 0;
        $perPage = config('tiendanube.per_page');

        for ($page = 1; $page <= config('tiendanube.max_pages'); $page++) {
            $pedidos = $this->client->getOrders($page);

            if (empty($pedidos)) {
                break;
            }

            foreach ($pedidos as $tn) {
                if (Invoice::where('tiendanube_order_id', $tn['id'])->exists()) {
                    $omitidos++;

                    continue;
                }

                $this->crearFacturaDesdePedido($tn);
                $importados++;
            }

            if (count($pedidos) < $perPage) {
                break;
            }
        }

        return ['importados' => $importados, 'omitidos' => $omitidos];
    }

    /**
     * Empuja el stock local a Tiendanube para los productos vinculados.
     * Sincronización manual y en un sentido (sistema → Tiendanube).
     *
     * @return array{enviados:int, errores:int}
     */
    public function pushStock(): array
    {
        $enviados = 0;
        $errores = 0;

        Product::whereNotNull('tiendanube_product_id')
            ->whereNotNull('tiendanube_variant_id')
            ->each(function (Product $p) use (&$enviados, &$errores) {
                try {
                    $this->client->updateVariantStock(
                        (int) $p->tiendanube_product_id,
                        (int) $p->tiendanube_variant_id,
                        (int) $p->stock,
                    );
                    $enviados++;
                } catch (\Throwable $e) {
                    $errores++;
                }
            });

        return ['enviados' => $enviados, 'errores' => $errores];
    }

    /**
     * @param  array<string,mixed>  $tn
     */
    private function crearFacturaDesdePedido(array $tn): void
    {
        DB::transaction(function () use ($tn) {
            $cliente = $this->clienteDelPedido($tn);
            $fecha = isset($tn['created_at']) ? Carbon::parse($tn['created_at']) : now();

            $invoice = Invoice::create([
                'number' => $this->siguienteNumero(),
                'client_id' => $cliente->id,
                'tipo_comprobante_interno' => TipoComprobanteInterno::RemitoX,
                'issue_date' => $fecha->toDateString(),
                'due_date' => $fecha->toDateString(),
                'tax_rate' => 0,
                'status' => 'pending',
                // No toca stock: Tiendanube ya descontó del suyo al vender.
                'afecta_stock' => false,
                'notes' => 'Pedido Tiendanube #'.($tn['number'] ?? $tn['id']),
                'tiendanube_order_id' => $tn['id'],
            ]);

            foreach ($tn['products'] ?? [] as $item) {
                $invoice->items()->create([
                    'product_id' => null,
                    'description' => $this->texto($item['name'] ?? 'Producto'),
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => $this->numero($item['price'] ?? 0),
                ]);
            }
        });
    }

    /**
     * @param  array<string,mixed>  $tn
     */
    private function clienteDelPedido(array $tn): Client
    {
        $email = $tn['contact_email'] ?? ($tn['customer']['email'] ?? null);
        $nombre = $tn['contact_name'] ?? ($tn['customer']['name'] ?? 'Cliente Tiendanube');

        if (! $email) {
            return Client::consumidorFinal();
        }

        return Client::firstOrCreate(
            ['email' => $email],
            ['name' => $nombre],
        );
    }

    private function siguienteNumero(): string
    {
        $count = Invoice::where('number', 'like', 'TN-%')->count() + 1;

        return 'TN-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Los textos de Tiendanube vienen como objeto por idioma
     * (p. ej. {"es": "Remera"}). Devuelve el primer valor disponible.
     */
    private function texto(mixed $valor): string
    {
        if (is_array($valor)) {
            return (string) (reset($valor) ?: '');
        }

        return (string) $valor;
    }

    private function numero(mixed $valor): float
    {
        return round((float) $valor, 2);
    }
}
