<?php

namespace App\Services\Tiendanube;

use App\Enums\TipoComprobanteInterno;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\InvoiceNumberGenerator;
use App\Support\TiendanubeSyncGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sincronización de datos entre Tiendanube y el sistema. Cada método devuelve
 * un resumen de lo hecho para mostrarlo en pantalla.
 *
 * Todo lo que ESCRIBE localmente datos que vienen DE Tiendanube (importar,
 * traer, webhooks) se corre dentro de TiendanubeSyncGuard::mute() para que no
 * dispare los observers que empujarían de vuelta a Tiendanube (evita el eco).
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
        return TiendanubeSyncGuard::mute(function () {
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
                        'category_id' => $this->resolverCategoria($tn['categories'] ?? []),
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
        });
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
                $this->importOrder($tn) ? $importados++ : $omitidos++;
            }

            if (count($pedidos) < $perPage) {
                break;
            }
        }

        return ['importados' => $importados, 'omitidos' => $omitidos];
    }

    /**
     * Importa un pedido puntual (lo usa también el webhook). Devuelve true si
     * creó la factura, false si el pedido ya estaba importado.
     *
     * @param  array<string,mixed>  $tn
     */
    public function importOrder(array $tn): bool
    {
        if (Invoice::where('tiendanube_order_id', $tn['id'])->exists()) {
            return false;
        }

        TiendanubeSyncGuard::mute(fn () => $this->crearFacturaDesdePedido($tn));

        return true;
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
     * Empuja los productos locales a Tiendanube (crea los no vinculados y
     * actualiza los vinculados).
     *
     * @return array{creados:int, actualizados:int}
     */
    public function pushProducts(): array
    {
        $creados = 0;
        $actualizados = 0;

        Product::query()->with('category')->each(function (Product $p) use (&$creados, &$actualizados) {
            $this->pushProduct($p) === 'created' ? $creados++ : $actualizados++;
        });

        return ['creados' => $creados, 'actualizados' => $actualizados];
    }

    /**
     * Empuja un único producto a Tiendanube: lo crea si no está vinculado
     * (guardando el id que devuelve TN) o lo actualiza si ya lo está.
     * Devuelve 'created' o 'updated'.
     */
    public function pushProduct(Product $p): string
    {
        $p->loadMissing('category');
        $categorias = $this->categoriasParaTiendanube($p);

        if ($p->tiendanube_product_id) {
            $payload = ['name' => ['es' => $p->name]];
            if ($categorias) {
                $payload['categories'] = $categorias;
            }
            $this->client->updateProduct((int) $p->tiendanube_product_id, $payload);

            if ($p->tiendanube_variant_id) {
                $this->client->updateVariant(
                    (int) $p->tiendanube_product_id,
                    (int) $p->tiendanube_variant_id,
                    ['price' => $this->precio($p->price), 'stock' => (int) $p->stock, 'sku' => $p->sku],
                );
            }

            return 'updated';
        }

        $payload = [
            'name' => ['es' => $p->name],
            'variants' => [[
                'price' => $this->precio($p->price),
                'stock' => (int) $p->stock,
                'sku' => $p->sku,
            ]],
        ];
        if ($categorias) {
            $payload['categories'] = $categorias;
        }

        $tn = $this->client->createProduct($payload);

        TiendanubeSyncGuard::mute(fn () => $p->update([
            'tiendanube_product_id' => $tn['id'] ?? null,
            'tiendanube_variant_id' => $tn['variants'][0]['id'] ?? null,
        ]));

        return 'created';
    }

    /**
     * Trae el stock de Tiendanube y lo copia al stock local de los productos
     * vinculados (Tiendanube → sistema).
     *
     * @return array{actualizados:int}
     */
    public function pullStock(): array
    {
        return TiendanubeSyncGuard::mute(function () {
            $actualizados = 0;
            $perPage = config('tiendanube.per_page');

            for ($page = 1; $page <= config('tiendanube.max_pages'); $page++) {
                $productos = $this->client->getProducts($page);

                if (empty($productos)) {
                    break;
                }

                foreach ($productos as $tn) {
                    $local = Product::where('tiendanube_product_id', $tn['id'])->first();

                    if ($local) {
                        $local->update(['stock' => (int) ($tn['variants'][0]['stock'] ?? $local->stock)]);
                        $actualizados++;
                    }
                }

                if (count($productos) < $perPage) {
                    break;
                }
            }

            return ['actualizados' => $actualizados];
        });
    }

    /**
     * Trae los clientes de Tiendanube y los crea/actualiza localmente.
     *
     * @return array{creados:int, actualizados:int}
     */
    public function pullCustomers(): array
    {
        return TiendanubeSyncGuard::mute(function () {
            $creados = 0;
            $actualizados = 0;
            $perPage = config('tiendanube.per_page');

            for ($page = 1; $page <= config('tiendanube.max_pages'); $page++) {
                $clientes = $this->client->getCustomers($page);

                if (empty($clientes)) {
                    break;
                }

                foreach ($clientes as $tn) {
                    $email = $tn['email'] ?? null;
                    $existente = Client::where('tiendanube_customer_id', $tn['id'])
                        ->orWhere(fn ($q) => $email ? $q->where('email', $email) : $q->whereRaw('1=0'))
                        ->first();

                    $datos = [
                        'name' => $this->texto($tn['name'] ?? 'Cliente Tiendanube'),
                        'email' => $email,
                        'phone' => $tn['phone'] ?? null,
                        'tiendanube_customer_id' => $tn['id'],
                    ];

                    if ($existente) {
                        $existente->update($datos);
                        $actualizados++;
                    } else {
                        Client::create($datos);
                        $creados++;
                    }
                }

                if (count($clientes) < $perPage) {
                    break;
                }
            }

            return ['creados' => $creados, 'actualizados' => $actualizados];
        });
    }

    /**
     * Empuja a Tiendanube los clientes locales con email: crea los que no están
     * vinculados y actualiza los que sí.
     *
     * @return array{creados:int, actualizados:int, errores:int}
     */
    public function pushCustomers(): array
    {
        $creados = 0;
        $actualizados = 0;
        $errores = 0;

        Client::whereNotNull('email')->each(function (Client $c) use (&$creados, &$actualizados, &$errores) {
            try {
                match ($this->pushClient($c)) {
                    'created' => $creados++,
                    'updated' => $actualizados++,
                    default => null,
                };
            } catch (\Throwable $e) {
                $errores++;
            }
        });

        return ['creados' => $creados, 'actualizados' => $actualizados, 'errores' => $errores];
    }

    /**
     * Empuja un único cliente a Tiendanube. Devuelve 'created', 'updated' o
     * 'skipped' (Consumidor Final o sin email real no se empujan).
     */
    public function pushClient(Client $c): string
    {
        if (! $c->email || $c->name === 'Consumidor Final') {
            return 'skipped';
        }

        if ($c->tiendanube_customer_id) {
            $this->client->updateCustomer((int) $c->tiendanube_customer_id, [
                'name' => $c->name,
                'phone' => $c->phone,
            ]);

            return 'updated';
        }

        $tn = $this->client->createCustomer([
            'name' => $c->name,
            'email' => $c->email,
        ]);

        TiendanubeSyncGuard::mute(fn () => $c->update(['tiendanube_customer_id' => $tn['id'] ?? null]));

        return 'created';
    }

    /**
     * Actualiza el stock local de un producto puntual desde Tiendanube (lo usa
     * el webhook product/updated).
     */
    public function updateStockFromTiendanube(int $tiendanubeProductId): void
    {
        TiendanubeSyncGuard::mute(function () use ($tiendanubeProductId) {
            $local = Product::where('tiendanube_product_id', $tiendanubeProductId)->first();

            if (! $local) {
                return;
            }

            $tn = $this->client->getProduct($tiendanubeProductId);
            $local->update(['stock' => (int) ($tn['variants'][0]['stock'] ?? $local->stock)]);
        });
    }

    /**
     * Trae/actualiza un cliente puntual desde Tiendanube (webhook customer/*).
     */
    public function updateCustomerFromTiendanube(int $tiendanubeCustomerId): void
    {
        TiendanubeSyncGuard::mute(function () use ($tiendanubeCustomerId) {
            $tn = $this->client->getCustomer($tiendanubeCustomerId);

            $datos = [
                'name' => $this->texto($tn['name'] ?? 'Cliente Tiendanube'),
                'email' => $tn['email'] ?? null,
                'phone' => $tn['phone'] ?? null,
                'tiendanube_customer_id' => $tiendanubeCustomerId,
            ];

            $existente = Client::where('tiendanube_customer_id', $tiendanubeCustomerId)->first();

            $existente ? $existente->update($datos) : Client::create($datos);
        });
    }

    /**
     * Trae/actualiza una categoría puntual desde Tiendanube (webhook category/*).
     */
    public function updateCategoryFromTiendanube(int $tiendanubeCategoryId): void
    {
        TiendanubeSyncGuard::mute(function () use ($tiendanubeCategoryId) {
            $tn = $this->client->getCategory($tiendanubeCategoryId);
            $nombre = $this->texto($tn['name'] ?? 'Sin categoría');

            $existente = Category::where('tiendanube_category_id', $tiendanubeCategoryId)->first();

            $existente
                ? $existente->update(['name' => $nombre])
                : Category::create(['name' => $nombre, 'tiendanube_category_id' => $tiendanubeCategoryId]);
        });
    }

    /**
     * Trae las categorías de Tiendanube y las crea/actualiza localmente.
     *
     * @return array{creados:int, actualizados:int}
     */
    public function pullCategories(): array
    {
        return TiendanubeSyncGuard::mute(function () {
            $creados = 0;
            $actualizados = 0;
            $perPage = config('tiendanube.per_page');

            for ($page = 1; $page <= config('tiendanube.max_pages'); $page++) {
                $categorias = $this->client->getCategories($page);

                if (empty($categorias)) {
                    break;
                }

                foreach ($categorias as $tn) {
                    $existente = Category::where('tiendanube_category_id', $tn['id'])->first();
                    $nombre = $this->texto($tn['name'] ?? 'Sin categoría');

                    if ($existente) {
                        $existente->update(['name' => $nombre]);
                        $actualizados++;
                    } else {
                        Category::create(['name' => $nombre, 'tiendanube_category_id' => $tn['id']]);
                        $creados++;
                    }
                }

                if (count($categorias) < $perPage) {
                    break;
                }
            }

            return ['creados' => $creados, 'actualizados' => $actualizados];
        });
    }

    /**
     * Empuja a Tiendanube las categorías locales: crea las que no están
     * vinculadas y actualiza el nombre de las que sí.
     *
     * @return array{creados:int, actualizados:int, errores:int}
     */
    public function pushCategories(): array
    {
        $creados = 0;
        $actualizados = 0;
        $errores = 0;

        Category::query()->each(function (Category $c) use (&$creados, &$actualizados, &$errores) {
            try {
                $this->pushCategory($c) === 'created' ? $creados++ : $actualizados++;
            } catch (\Throwable $e) {
                $errores++;
            }
        });

        return ['creados' => $creados, 'actualizados' => $actualizados, 'errores' => $errores];
    }

    /**
     * Empuja una única categoría a Tiendanube. Devuelve 'created' o 'updated'.
     */
    public function pushCategory(Category $c): string
    {
        if ($c->tiendanube_category_id) {
            $this->client->updateCategory((int) $c->tiendanube_category_id, ['name' => ['es' => $c->name]]);

            return 'updated';
        }

        $this->ensureCategoryInTiendanube($c);

        return 'created';
    }

    /**
     * Categorías (ids de Tiendanube) a mandar en el payload de un producto.
     * Si la categoría local todavía no existe en Tiendanube, la crea.
     *
     * @return array<int, int>
     */
    private function categoriasParaTiendanube(Product $p): array
    {
        if (! $p->category) {
            return [];
        }

        $id = $this->ensureCategoryInTiendanube($p->category);

        return $id ? [$id] : [];
    }

    /**
     * Garantiza que la categoría local exista en Tiendanube; devuelve su id
     * de Tiendanube (y lo guarda localmente si tuvo que crearla).
     */
    private function ensureCategoryInTiendanube(Category $c): ?int
    {
        if ($c->tiendanube_category_id) {
            return (int) $c->tiendanube_category_id;
        }

        $tn = $this->client->createCategory(['name' => ['es' => $c->name]]);
        $id = $tn['id'] ?? null;

        if ($id) {
            TiendanubeSyncGuard::mute(fn () => $c->update(['tiendanube_category_id' => $id]));
        }

        return $id ? (int) $id : null;
    }

    /**
     * Devuelve el id de la categoría local correspondiente a la primera
     * categoría del producto de Tiendanube, creándola si no existe (sin
     * duplicar, gracias al vínculo tiendanube_category_id).
     *
     * @param  array<int, array<string,mixed>>  $categorias
     */
    private function resolverCategoria(array $categorias): ?int
    {
        $cat = $categorias[0] ?? null;

        if (! $cat || ! isset($cat['id'])) {
            return null;
        }

        $local = Category::firstOrCreate(
            ['tiendanube_category_id' => $cat['id']],
            ['name' => $this->texto($cat['name'] ?? 'Sin categoría')],
        );

        return $local->id;
    }

    private function precio(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    /**
     * @param  array<string,mixed>  $tn
     */
    private function crearFacturaDesdePedido(array $tn): void
    {
        InvoiceNumberGenerator::withLock(TipoComprobanteInterno::RemitoX->value, fn () => DB::transaction(function () use ($tn) {
            $cliente = $this->clienteDelPedido($tn);
            $fecha = isset($tn['created_at']) ? Carbon::parse($tn['created_at']) : now();

            $invoice = Invoice::create([
                'number' => InvoiceNumberGenerator::next(TipoComprobanteInterno::RemitoX->value),
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
        }));
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
