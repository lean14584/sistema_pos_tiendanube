<?php

namespace App\Livewire\Products;

use App\Enums\AlicuotaIva;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportMapping;
use App\Support\ProductImport\ProductImportFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

#[Layout('layouts.app')]
class Import extends Component
{
    use WithFileUploads;

    public string $step = 'subir';

    public $archivo = null;

    /** Ruta temporal del archivo ya subido (para no tener que resubirlo entre pasos). */
    public string $rutaTemporal = '';

    /** @var array<int, string> cabeceras del Excel, índice = número de columna */
    public array $cabeceras = [];

    /** @var array<int, array<int, mixed>> primeras filas, para la vista previa */
    public array $filasPreview = [];

    public int $totalFilas = 0;

    /** @var array<string, int|null> campo del sistema => índice de columna del Excel */
    public array $mapeo = [];

    /** @var array{creados: int, actualizados: int, omitidos: array<int, string>}|null */
    public ?array $resultado = null;

    public function updatedArchivo(): void
    {
        $this->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $this->rutaTemporal = $this->archivo->store('imports', 'local');
        $rutaCompleta = Storage::disk('local')->path($this->rutaTemporal);

        $spreadsheet = IOFactory::load($rutaCompleta);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, false);

        if (empty($filas)) {
            $this->addError('archivo', 'El archivo está vacío.');

            return;
        }

        $this->cabeceras = array_map(fn ($h) => trim((string) $h), array_shift($filas));
        $this->totalFilas = count($filas);
        $this->filasPreview = array_slice($filas, 0, 5);

        $recordado = ProductImportMapping::recordarPara($this->cabeceras);

        $this->mapeo = $recordado
            ? $this->resolverMapeoRecordado($recordado->mapping)
            : ProductImportFields::sugerir($this->cabeceras);

        $this->step = 'mapear';
    }

    /** Traduce un mapeo guardado (campo => nombre de columna) al índice real en ESTE archivo. */
    private function resolverMapeoRecordado(array $mapeoGuardado): array
    {
        $normalizadas = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $this->cabeceras);

        return collect(ProductImportFields::FIELDS)->keys()->mapWithKeys(function ($campo) use ($mapeoGuardado, $normalizadas) {
            $nombreGuardado = $mapeoGuardado[$campo] ?? null;
            $indice = $nombreGuardado !== null
                ? array_search(mb_strtolower(trim($nombreGuardado)), $normalizadas, true)
                : false;

            return [$campo => $indice !== false ? $indice : null];
        })->all();
    }

    public function volverAMapeo(): void
    {
        $this->step = 'mapear';
        $this->resultado = null;
    }

    public function cancelar(): void
    {
        if ($this->rutaTemporal) {
            Storage::disk('local')->delete($this->rutaTemporal);
        }

        $this->reset(['step', 'archivo', 'rutaTemporal', 'cabeceras', 'filasPreview', 'totalFilas', 'mapeo', 'resultado']);
        $this->step = 'subir';
    }

    public function confirmarImportacion(): void
    {
        $this->validate([
            'mapeo.name' => ['required'],
            'mapeo.price' => ['required'],
        ], [], ['mapeo.name' => 'Nombre', 'mapeo.price' => 'Precio de venta']);

        $rutaCompleta = Storage::disk('local')->path($this->rutaTemporal);
        $spreadsheet = IOFactory::load($rutaCompleta);
        $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($filas); // cabecera

        $creados = 0;
        $actualizados = 0;
        $omitidos = [];
        $categoriasCache = [];

        DB::transaction(function () use ($filas, &$creados, &$actualizados, &$omitidos, &$categoriasCache) {
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

                if (($stock = $this->valor($fila, 'stock')) !== null && trim((string) $stock) !== '') {
                    $datos['stock'] = (int) $this->normalizarNumero($stock);
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

                $existente = null;
                if (! empty($datos['sku'])) {
                    $existente = Product::whereRaw('LOWER(sku) = ?', [mb_strtolower($datos['sku'])])->first();
                }
                if (! $existente) {
                    $existente = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($nombre)])->first();
                }

                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    Product::create($datos);
                    $creados++;
                }
            }
        });

        // Recordar el mapeo para la próxima vez que suban un Excel con estas mismas cabeceras.
        $mapeoPorNombre = collect($this->mapeo)
            ->map(fn ($indice) => $indice !== null && $indice !== '' ? $this->cabeceras[$indice] : null)
            ->all();
        ProductImportMapping::guardarPara($this->cabeceras, $mapeoPorNombre);

        Storage::disk('local')->delete($this->rutaTemporal);

        $this->resultado = ['creados' => $creados, 'actualizados' => $actualizados, 'omitidos' => $omitidos];
        $this->step = 'resultado';
    }

    /** Valor de una fila para un campo del sistema, según el mapeo actual (o null si no está mapeado). */
    private function valor(array $fila, string $campo): mixed
    {
        $indice = $this->mapeo[$campo] ?? null;

        return $indice !== null && $indice !== '' ? ($fila[$indice] ?? null) : null;
    }

    /** Acepta "1.234,56" o "1234.56" y devuelve un string numérico con punto decimal. */
    private function normalizarNumero(mixed $valor): string
    {
        $texto = trim((string) $valor);

        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
        }

        return str_replace(',', '.', $texto);
    }

    public function render()
    {
        $previewMapeado = collect($this->filasPreview)->map(fn ($fila) => collect(ProductImportFields::FIELDS)
            ->keys()
            ->mapWithKeys(fn ($campo) => [$campo => $this->valor($fila, $campo)])
            ->all());

        return view('livewire.products.import', [
            'campos' => ProductImportFields::FIELDS,
            'previewMapeado' => $previewMapeado,
        ]);
    }
}
