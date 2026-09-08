<?php

namespace App\Livewire\HistoricalSales;

use App\Models\Client;
use App\Models\HistoricalSale;
use App\Support\HistoricalSaleImport\HistoricalSaleImportFields;
use App\Support\Import\ExcelDateParser;
use App\Support\Import\ImportsExcelWithMapping;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Import extends Component
{
    use ImportsExcelWithMapping;

    protected function camposImport(): string
    {
        return HistoricalSaleImportFields::class;
    }

    protected function contextoImport(): string
    {
        return 'historical-sales';
    }

    /**
     * Cada fila se agrega como un registro nuevo (no hay forma confiable de
     * "actualizar" una venta histórica de otro sistema): importar el mismo
     * archivo dos veces duplica los registros. `creados` cuenta las filas
     * cargadas; `actualizados` queda siempre en 0.
     *
     * @return array{creados: int, actualizados: int, omitidos: array<int, string>}
     */
    protected function procesarFilas(array $filas): array
    {
        $creados = 0;
        $omitidos = [];

        DB::transaction(function () use ($filas, &$creados, &$omitidos) {
            foreach ($filas as $numero => $fila) {
                $nombre = trim((string) $this->valor($fila, 'client_name'));
                $total = $this->valor($fila, 'total');
                $fecha = ExcelDateParser::parse($this->valor($fila, 'sale_date'));

                if ($nombre === '') {
                    $omitidos[] = 'Fila '.($numero + 2).': falta el cliente.';

                    continue;
                }

                if ($fecha === null) {
                    $omitidos[] = 'Fila '.($numero + 2).': falta una fecha válida.';

                    continue;
                }

                if ($total === null || trim((string) $total) === '' || ! is_numeric($this->normalizarNumero($total))) {
                    $omitidos[] = 'Fila '.($numero + 2).': falta un total válido.';

                    continue;
                }

                $taxId = trim((string) ($this->valor($fila, 'tax_id') ?? ''));
                $cliente = null;
                if ($taxId !== '') {
                    $cliente = Client::where('tax_id', $taxId)->first();
                }
                if (! $cliente) {
                    $cliente = Client::where('name', $nombre)->first();
                }

                HistoricalSale::create([
                    'client_id' => $cliente?->id,
                    'client_name_raw' => $nombre,
                    'sale_date' => $fecha,
                    'comprobante_type' => $this->valorTexto($fila, 'comprobante_type'),
                    'comprobante_number' => $this->valorTexto($fila, 'comprobante_number'),
                    'total' => $this->normalizarNumero($total),
                    'notes' => $this->valorTexto($fila, 'notes'),
                ]);

                $creados++;
            }
        });

        return ['creados' => $creados, 'actualizados' => 0, 'omitidos' => $omitidos];
    }

    private function valorTexto(array $fila, string $campo): ?string
    {
        $valor = $this->valor($fila, $campo);

        return $valor !== null && trim((string) $valor) !== '' ? trim((string) $valor) : null;
    }

    public function render()
    {
        return view('livewire.historical-sales.import', [
            'campos' => HistoricalSaleImportFields::fields(),
            'previewMapeado' => $this->previewMapeado(),
        ]);
    }
}
