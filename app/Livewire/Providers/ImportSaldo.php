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

        DB::transaction(function () use ($filas, &$actualizados, &$omitidos) {
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
                    $proveedor = Provider::where('tax_id', $taxId)->first();
                }
                if (! $proveedor && $nombre !== '') {
                    $proveedor = Provider::where('name', $nombre)->first();
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
