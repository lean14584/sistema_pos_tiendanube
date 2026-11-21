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

        // MEJORA: cada fila disparaba 1-2 queries (Provider::where('tax_id',...)
        // y, si no matcheaba, Provider::where('name',...)) — precargado una
        // sola vez en memoria, mismo patrón que Clients\Import.
        // MEJORA: el match por nombre era sensible a mayúsculas/minúsculas
        // (a diferencia de Clients\Import, que ya normaliza con
        // mb_strtolower) — "distribuidora norte" en el Excel no encontraba a
        // "Distribuidora Norte" en la base y creaba un proveedor duplicado
        // en vez de actualizar el existente.
        $todosLosProveedores = Provider::all();
        $proveedoresPorTaxId = $todosLosProveedores->filter(fn (Provider $p) => filled($p->tax_id))->keyBy(fn (Provider $p) => mb_strtolower($p->tax_id));
        $proveedoresPorNombre = $todosLosProveedores->keyBy(fn (Provider $p) => mb_strtolower($p->name));

        DB::transaction(function () use ($filas, &$creados, &$actualizados, &$omitidos, $proveedoresPorTaxId, $proveedoresPorNombre) {
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
                    $existente = $proveedoresPorTaxId->get(mb_strtolower($datos['tax_id']));
                }
                if (! $existente) {
                    $existente = $proveedoresPorNombre->get(mb_strtolower($nombre));
                }

                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    $nuevo = Provider::create([
                        'tipo_documento' => TaxIdResolver::tipoDocumentoPara($taxId)->value,
                        ...$datos,
                    ]);

                    // Una fila duplicada más adelante en el mismo Excel tiene
                    // que encontrar este proveedor recién creado.
                    if (filled($nuevo->tax_id)) {
                        $proveedoresPorTaxId->put(mb_strtolower($nuevo->tax_id), $nuevo);
                    }
                    $proveedoresPorNombre->put(mb_strtolower($nuevo->name), $nuevo);

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
