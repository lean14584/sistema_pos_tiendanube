<?php

namespace App\Livewire\Providers;

use App\Models\Provider;
use App\Support\Import\ExcelDateParser;
use App\Support\Import\ImportsExcelWithMapping;
use App\Support\ProviderImport\ProviderBalanceImportFields;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ImportSaldo extends Component
{
    use ImportsExcelWithMapping;

    protected function camposImport(): string
    {
        return ProviderBalanceImportFields::class;
    }

    protected function contextoImport(): string
    {
        return 'providers-balance';
    }

    /**
     * No crea proveedores nuevos: solo carga el saldo de apertura de
     * proveedores ya existentes (matcheados por CUIT/DNI o, si no, por
     * nombre exacto). Espejo de Clients\ImportSaldo.
     *
     * @return array{creados: int, actualizados: int, omitidos: array<int, string>}
     */
    protected function procesarFilas(array $filas): array
    {
        $actualizados = 0;
        $omitidos = [];

        // MEJORA: cada fila disparaba 1-2 queries (Provider::where('tax_id',...)
        // y, si no matcheaba, Provider::where('name',...)) — precargado una
        // sola vez en memoria, mismo patrón que Providers\Import.
        $todosLosProveedores = Provider::all();
        $proveedoresPorTaxId = $todosLosProveedores->filter(fn (Provider $p) => filled($p->tax_id))->keyBy('tax_id');
        $proveedoresPorNombre = $todosLosProveedores->keyBy('name');

        DB::transaction(function () use ($filas, &$actualizados, &$omitidos, $proveedoresPorTaxId, $proveedoresPorNombre) {
            foreach ($filas as $numero => $fila) {
                $nombre = trim((string) $this->valor($fila, 'name'));
                $taxId = trim((string) ($this->valor($fila, 'tax_id') ?? ''));
                $saldo = $this->valor($fila, 'opening_balance');

                if ($taxId === '' && $nombre === '') {
                    $omitidos[] = 'Fila '.($numero + 2).': falta CUIT/DNI o nombre para identificar al proveedor.';

                    continue;
                }

                if ($saldo === null || trim((string) $saldo) === '' || ! is_numeric($this->normalizarNumero($saldo))) {
                    $omitidos[] = 'Fila '.($numero + 2).': falta un saldo válido.';

                    continue;
                }

                $proveedor = null;
                if ($taxId !== '') {
                    $proveedor = $proveedoresPorTaxId->get($taxId);
                }
                if (! $proveedor && $nombre !== '') {
                    $proveedor = $proveedoresPorNombre->get($nombre);
                }

                if (! $proveedor) {
                    $omitidos[] = 'Fila '.($numero + 2).': no se encontró un proveedor con ese CUIT/DNI o nombre ('.($nombre !== '' ? $nombre : $taxId).').';

                    continue;
                }

                $fecha = ExcelDateParser::parse($this->valor($fila, 'opening_balance_date'));

                $proveedor->update([
                    'opening_balance' => $this->normalizarNumero($saldo),
                    'opening_balance_date' => $fecha,
                ]);

                $actualizados++;
            }
        });

        return ['creados' => 0, 'actualizados' => $actualizados, 'omitidos' => $omitidos];
    }

    public function render()
    {
        return view('livewire.providers.import-saldo', [
            'campos' => ProviderBalanceImportFields::fields(),
            'previewMapeado' => $this->previewMapeado(),
        ]);
    }
}
