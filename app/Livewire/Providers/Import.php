<?php

namespace App\Livewire\Providers;

use App\Models\Provider;
use App\Support\Import\ImportsExcelWithMapping;
use App\Support\Import\TaxIdResolver;
use App\Support\ProviderImport\ProviderImportFields;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Import extends Component
{
    use ImportsExcelWithMapping;

    protected function camposImport(): string
    {
        return ProviderImportFields::class;
    }

    protected function contextoImport(): string
    {
        return 'providers';
    }

    /** @return array{creados: int, actualizados: int, omitidos: array<int, string>} */
    protected function procesarFilas(array $filas): array
    {
        $creados = 0;
        $actualizados = 0;
        $omitidos = [];

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

                $existente = null;
                if (! empty($datos['tax_id'])) {
                    $existente = Provider::where('tax_id', $datos['tax_id'])->first();
                }
                if (! $existente) {
                    $existente = Provider::where('name', $nombre)->first();
                }

                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    Provider::create([
                        'tipo_documento' => TaxIdResolver::tipoDocumentoPara($taxId)->value,
                        ...$datos,
                    ]);
                    $creados++;
                }
            }
        });

        return ['creados' => $creados, 'actualizados' => $actualizados, 'omitidos' => $omitidos];
    }

    public function render()
    {
        return view('livewire.providers.import', [
            'campos' => ProviderImportFields::fields(),
            'previewMapeado' => $this->previewMapeado(),
        ]);
    }
}
