<?php

namespace App\Livewire\Products;

use App\Enums\AlicuotaIva;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Support\CurrentSucursal;
use App\Support\Import\ImportsExcelWithMapping;
use App\Support\ProductImport\ProductImportFields;
use App\Support\StockAdjuster;
use App\Support\TiendanubeSyncGuard;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Import extends Component
{
    use ImportsExcelWithMapping;

    protected function camposImport(): string
    {
        return ProductImportFields::class;
    }

    protected function contextoImport(): string
    {
        return 'products';
    }

    /** @return array{creados: int, actualizados: int, omitidos: array<int, string>} */
    protected function procesarFilas(array $filas): array
    {
        $creados = 0;
        $actualizados = 0;
        $omitidos = [];
        $categoriasCache = [];
        // El stock que trae el Excel es el de esta sucursal (la activa de
        // quien importa) — se resuelve una sola vez, no por fila.
        $sucursalId = CurrentSucursal::id();

        // Cada alta/edición dispara ProductObserver → TiendanubeAutoSync::queue(),
        // que despacha un afterResponse() (llamada HTTP real a Tiendanube,
        // sin depender de un worker de colas). Con un Excel de cientos de
        // filas eso son cientos de llamadas HTTP secuenciales colgando este
        // mismo request al final. Silenciamos el auto-sync acá; para
        // reflejar los productos importados en Tiendanube, usar "Enviar
        // productos" en el panel de Tiendanube (acción manual, ya pensada
        // para tardar).
        TiendanubeSyncGuard::mute(function () use ($filas, &$creados, &$actualizados, &$omitidos, &$categoriasCache, $sucursalId) {
            DB::transaction(function () use ($filas, &$creados, &$actualizados, &$omitidos, &$categoriasCache, $sucursalId) {
                foreach ($filas as $numero => $fila) {
                    $nombre = trim((string) $this->valor($fila, 'name'));
                    $precio = $this->valor($fila, 'price');

                    if ($nombre === '' || $precio === null || $precio === '' || ! is_numeric($this->normalizarNumero($precio))) {
                        $omitidos[] = 'Fila '.($numero + 2).': falta nombre o precio válido.';

                        continue;
                    }

                    $datos = [
                        'name' => $nombre,
                        'price' => $this->normalizarNumero($precio),
                    ];

                    if (($sku = $this->valor($fila, 'sku')) !== null && trim((string) $sku) !== '') {
                        $datos['sku'] = trim((string) $sku);
                    }

                    if (($costo = $this->valor($fila, 'cost_price')) !== null && trim((string) $costo) !== '') {
                        $datos['cost_price'] = $this->normalizarNumero($costo);
                    }

                    $stockImportado = null;
                    if (($stock = $this->valor($fila, 'stock')) !== null && trim((string) $stock) !== '') {
                        $stockImportado = (int) $this->normalizarNumero($stock);
                    }

                    if (($minStock = $this->valor($fila, 'min_stock')) !== null && trim((string) $minStock) !== '') {
                        $datos['min_stock'] = (int) $this->normalizarNumero($minStock);
                    }

                    if (($descripcion = $this->valor($fila, 'description')) !== null && trim((string) $descripcion) !== '') {
                        $datos['description'] = trim((string) $descripcion);
                    }

                    if (($iva = $this->valor($fila, 'iva_rate')) !== null && trim((string) $iva) !== '') {
                        $normalizado = AlicuotaIva::normalizar($this->normalizarNumero($iva));
                        $datos['iva_rate'] = in_array($normalizado, AlicuotaIva::valores(), true) ? $normalizado : '21';
                    }

                    if (($categoria = $this->valor($fila, 'category')) !== null && trim((string) $categoria) !== '') {
                        $nombreCategoria = trim((string) $categoria);
                        $clave = mb_strtolower($nombreCategoria);
                        if (! isset($categoriasCache[$clave])) {
                            $categoriasCache[$clave] = Category::firstOrCreate(['name' => $nombreCategoria])->id;
                        }
                        $datos['category_id'] = $categoriasCache[$clave];
                    }

                    // La colación por defecto de la conexión (utf8mb4_unicode_ci,
                    // ver config/database.php) ya compara sin distinguir
                    // mayúsculas/minúsculas: el LOWER() de acá era redundante y,
                    // de paso, evitaba que MySQL pudiera usar el índice de
                    // sku/name (una función sobre la columna invalida el índice).
                    $existente = null;
                    if (! empty($datos['sku'])) {
                        $existente = Product::where('sku', $datos['sku'])->first();
                    }
                    if (! $existente) {
                        $existente = Product::where('name', $nombre)->first();
                    }

                    if ($existente) {
                        $existente->update($datos);

                        // El valor del Excel es el stock final en esta
                        // sucursal, no un delta: se aplica como diferencia
                        // contra lo que ya había acá (igual que Products\Edit).
                        if ($stockImportado !== null && $sucursalId !== null) {
                            $delta = $stockImportado - $existente->stockEnSucursal($sucursalId);
                            if ($delta !== 0) {
                                StockAdjuster::applyManualDelta($existente->id, $delta, $sucursalId);
                            }
                        }

                        $actualizados++;
                    } else {
                        // Producto nuevo: el stock inicial va directo en el
                        // create (agregado correcto desde el vamos, un solo
                        // evento de auditoría de alta) más su fila en la
                        // sucursal activa.
                        $nuevo = Product::create([...$datos, 'stock' => $stockImportado ?? 0]);

                        if ($stockImportado !== null && $stockImportado > 0 && $sucursalId !== null) {
                            ProductStock::create(['product_id' => $nuevo->id, 'sucursal_id' => $sucursalId, 'stock' => $stockImportado]);
                        }

                        $creados++;
                    }
                }
            });
        });

        return ['creados' => $creados, 'actualizados' => $actualizados, 'omitidos' => $omitidos];
    }

    public function render()
    {
        return view('livewire.products.import', [
            'campos' => ProductImportFields::fields(),
            'previewMapeado' => $this->previewMapeado(),
        ]);
    }
}
