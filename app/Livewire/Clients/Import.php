<?php

namespace App\Livewire\Clients;

use App\Enums\CondicionIva;
use App\Models\Client;
use App\Support\ClientImport\ClientImportFields;
use App\Support\Import\CondicionIvaResolver;
use App\Support\Import\ImportsExcelWithMapping;
use App\Support\Import\TaxIdResolver;
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
        return ClientImportFields::class;
    }

    protected function contextoImport(): string
    {
        return 'clients';
    }

    /** @return array{creados: int, actualizados: int, omitidos: array<int, string>} */
    protected function procesarFilas(array $filas): array
    {
        $creados = 0;
        $actualizados = 0;
        $omitidos = [];

        // Igual que Products\Import: sin esto, cada alta/edición dispara un
        // POST a Tiendanube por fila (afterResponse), cientos de llamadas
        // HTTP colgando el request con un Excel grande.
        TiendanubeSyncGuard::mute(function () use ($filas, &$creados, &$actualizados, &$omitidos) {
            DB::transaction(function () use ($filas, &$creados, &$actualizados, &$omitidos) {
                foreach ($filas as $numero => $fila) {
                    $nombre = trim((string) $this->valor($fila, 'name'));

                    if ($nombre === '') {
                        $omitidos[] = 'Fila '.($numero + 2).': falta el nombre.';

                        continue;
                    }

                    $datos = ['name' => $nombre];

                    $taxId = trim((string) ($this->valor($fila, 'tax_id') ?? ''));
                    if ($taxId !== '') {
                        $datos['tax_id'] = $taxId;
                        $datos['tipo_documento'] = TaxIdResolver::tipoDocumentoPara($taxId)->value;
                    }

                    if (($email = $this->valor($fila, 'email')) !== null && trim((string) $email) !== '') {
                        $datos['email'] = trim((string) $email);
                    }

                    if (($phone = $this->valor($fila, 'phone')) !== null && trim((string) $phone) !== '') {
                        $datos['phone'] = trim((string) $phone);
                    }

                    if (($address = $this->valor($fila, 'address')) !== null && trim((string) $address) !== '') {
                        $datos['address'] = trim((string) $address);
                    }

                    if (($condicion = $this->valor($fila, 'condicion_iva')) !== null && trim((string) $condicion) !== '') {
                        $datos['condicion_iva'] = CondicionIvaResolver::desdeTexto((string) $condicion)->value;
                    }

                    if (($limite = $this->valor($fila, 'credit_limit')) !== null && trim((string) $limite) !== '') {
                        $datos['credit_limit'] = $this->normalizarNumero($limite);
                    }

                    $existente = null;
                    if (! empty($datos['tax_id'])) {
                        $existente = Client::where('tax_id', $datos['tax_id'])->first();
                    }
                    if (! $existente) {
                        $existente = Client::where('name', $nombre)->first();
                    }

                    if ($existente) {
                        $existente->update($datos);
                        $actualizados++;
                    } else {
                        Client::create([
                            'email' => '',
                            'condicion_iva' => CondicionIva::ConsumidorFinal->value,
                            'tipo_documento' => TaxIdResolver::tipoDocumentoPara($taxId)->value,
                            ...$datos,
                        ]);
                        $creados++;
                    }
                }
            });
        });

        return ['creados' => $creados, 'actualizados' => $actualizados, 'omitidos' => $omitidos];
    }

    public function render()
    {
        return view('livewire.clients.import', [
            'campos' => ClientImportFields::fields(),
            'previewMapeado' => $this->previewMapeado(),
        ]);
    }
}
