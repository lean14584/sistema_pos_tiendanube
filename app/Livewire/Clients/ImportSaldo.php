<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Support\ClientImport\ClientBalanceImportFields;
use App\Support\Import\ExcelDateParser;
use App\Support\Import\ImportsExcelWithMapping;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ImportSaldo extends Component
{
    use ImportsExcelWithMapping;

    protected function camposImport(): string
    {
        return ClientBalanceImportFields::class;
    }

    protected function contextoImport(): string
    {
        return 'clients-balance';
    }

    /**
     * No crea clientes nuevos: solo carga el saldo de apertura de clientes
     * ya existentes (matcheados por CUIT/DNI o, si no, por nombre exacto).
     *
     * @return array{creados: int, actualizados: int, omitidos: array<int, string>}
     */
    protected function procesarFilas(array $filas): array
    {
        $actualizados = 0;
        $omitidos = [];

        // MEJORA: cada fila disparaba 1-2 queries (Client::where('tax_id',...)
        // y, si no matcheaba, Client::where('name',...)) — precargado una
        // sola vez en memoria, mismo patrón que Clients\Import.
        $todosLosClientes = Client::all();
        $clientesPorTaxId = $todosLosClientes->filter(fn (Client $c) => filled($c->tax_id))->keyBy('tax_id');
        $clientesPorNombre = $todosLosClientes->keyBy('name');

        DB::transaction(function () use ($filas, &$actualizados, &$omitidos, $clientesPorTaxId, $clientesPorNombre) {
            foreach ($filas as $numero => $fila) {
                $nombre = trim((string) $this->valor($fila, 'name'));
                $taxId = trim((string) ($this->valor($fila, 'tax_id') ?? ''));
                $saldo = $this->valor($fila, 'opening_balance');

                if ($taxId === '' && $nombre === '') {
                    $omitidos[] = 'Fila '.($numero + 2).': falta CUIT/DNI o nombre para identificar al cliente.';

                    continue;
                }

                if ($saldo === null || trim((string) $saldo) === '' || ! is_numeric($this->normalizarNumero($saldo))) {
                    $omitidos[] = 'Fila '.($numero + 2).': falta un saldo válido.';

                    continue;
                }

                $cliente = null;
                if ($taxId !== '') {
                    $cliente = $clientesPorTaxId->get($taxId);
                }
                if (! $cliente && $nombre !== '') {
                    $cliente = $clientesPorNombre->get($nombre);
                }

                if (! $cliente) {
                    $omitidos[] = 'Fila '.($numero + 2).': no se encontró un cliente con ese CUIT/DNI o nombre ('.($nombre !== '' ? $nombre : $taxId).').';

                    continue;
                }

                $fecha = ExcelDateParser::parse($this->valor($fila, 'opening_balance_date'));

                $cliente->update([
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
        return view('livewire.clients.import-saldo', [
            'campos' => ClientBalanceImportFields::fields(),
            'previewMapeado' => $this->previewMapeado(),
        ]);
    }
}
