<?php

namespace App\Support\Import;

use App\Models\ImportMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Máquina de pasos común a toda pantalla "Importar desde Excel" (subir →
 * mapear columnas → confirmar → resultado), con mapeo de columnas asistido
 * y recordado por "forma" de archivo (ver ImportMapping). Nació en
 * Products\Import y se generalizó para Clientes, Proveedores, Ventas
 * históricas y los saldos de apertura de cuenta corriente.
 *
 * La clase que usa este trait debe implementar camposImport(),
 * contextoImport() y procesarFilas().
 */
trait ImportsExcelWithMapping
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

    /** Clase "campos" de este import (usa App\Support\Import\FieldSetHelpers). */
    abstract protected function camposImport(): string;

    /** Contexto para recordar el mapeo (una "forma" de Excel recordada por contexto). */
    abstract protected function contextoImport(): string;

    /**
     * Procesa las filas ya mapeadas (sin la fila de cabecera) y devuelve el
     * resultado a mostrar. Usar $this->valor($fila, 'campo') para leer cada
     * columna según el mapeo actual.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @return array{creados: int, actualizados: int, omitidos: array<int, string>}
     */
    abstract protected function procesarFilas(array $filas): array;

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

        $fieldsClass = $this->camposImport();
        $recordado = ImportMapping::recordarPara($this->contextoImport(), $this->cabeceras);

        $this->mapeo = $recordado
            ? $this->resolverMapeoRecordado($recordado->mapping, $fieldsClass)
            : $fieldsClass::sugerir($this->cabeceras);

        $this->step = 'mapear';
    }

    /** Traduce un mapeo guardado (campo => nombre de columna) al índice real en ESTE archivo. */
    private function resolverMapeoRecordado(array $mapeoGuardado, string $fieldsClass): array
    {
        $normalizadas = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $this->cabeceras);

        return collect($fieldsClass::fields())->keys()->mapWithKeys(function ($campo) use ($mapeoGuardado, $normalizadas) {
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
        $fieldsClass = $this->camposImport();
        $campos = collect($fieldsClass::fields());
        $requeridos = $campos->filter(fn ($f, $campo) => $fieldsClass::esRequerido($campo));

        $this->validate(
            $requeridos->keys()->mapWithKeys(fn ($campo) => ["mapeo.{$campo}" => ['required']])->all(),
            [],
            $requeridos->mapWithKeys(fn ($f, $campo) => ["mapeo.{$campo}" => $f['label']])->all(),
        );

        $rutaCompleta = Storage::disk('local')->path($this->rutaTemporal);
        $spreadsheet = IOFactory::load($rutaCompleta);
        $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($filas); // cabecera

        $this->resultado = $this->procesarFilas($filas);

        // Recordar el mapeo para la próxima vez que suban un Excel con estas mismas cabeceras.
        $mapeoPorNombre = collect($this->mapeo)
            ->map(fn ($indice) => $indice !== null && $indice !== '' ? $this->cabeceras[$indice] : null)
            ->all();
        ImportMapping::guardarPara($this->contextoImport(), $this->cabeceras, $mapeoPorNombre);

        Storage::disk('local')->delete($this->rutaTemporal);

        $this->step = 'resultado';
    }

    /** Valor de una fila para un campo del sistema, según el mapeo actual (o null si no está mapeado). */
    protected function valor(array $fila, string $campo): mixed
    {
        $indice = $this->mapeo[$campo] ?? null;

        return $indice !== null && $indice !== '' ? ($fila[$indice] ?? null) : null;
    }

    /** Acepta "1.234,56" o "1234.56" y devuelve un string numérico con punto decimal. */
    protected function normalizarNumero(mixed $valor): string
    {
        $texto = trim((string) $valor);

        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
        }

        return str_replace(',', '.', $texto);
    }

    /** Filas de preview ya traducidas de "índice de columna" a "campo del sistema", para la tabla de confirmación. */
    protected function previewMapeado(): Collection
    {
        $campos = collect($this->camposImport()::fields())->keys();

        return collect($this->filasPreview)->map(fn ($fila) => $campos
            ->mapWithKeys(fn ($campo) => [$campo => $this->valor($fila, $campo)])
            ->all());
    }
}
