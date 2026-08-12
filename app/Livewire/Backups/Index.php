<?php

namespace App\Livewire\Backups;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function render()
    {
        $dbPath = config('database.connections.sqlite.database');
        $bytes = is_string($dbPath) && file_exists($dbPath) ? filesize($dbPath) : 0;

        return view('livewire.backups.index', [
            'dbSize' => $this->formatoTamano($bytes),
            'conteos' => [
                ['label' => 'Facturas', 'value' => DB::table('invoices')->count(), 'icon' => 'document-text'],
                ['label' => 'Clientes', 'value' => DB::table('clients')->count(), 'icon' => 'users'],
                ['label' => 'Productos', 'value' => DB::table('products')->count(), 'icon' => 'cube'],
                ['label' => 'Compras', 'value' => DB::table('purchases')->count(), 'icon' => 'shopping-cart'],
            ],
            'autoPath' => config('backups.path'),
            'autoHora' => config('backups.daily_at', '23:30'),
            'autoKeep' => (int) config('backups.keep'),
            'copyTo' => config('backups.copy_to'),
            'respaldos' => $this->respaldosGuardados(),
        ]);
    }

    /**
     * @return array<int, array{nombre: string, tamano: string, fecha: string}>
     */
    private function respaldosGuardados(): array
    {
        $dir = config('backups.path');

        if (! is_dir($dir)) {
            return [];
        }

        return collect(glob(rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'respaldo-*.zip') ?: [])
            ->sortByDesc(fn (string $f) => filemtime($f))
            ->take(10)
            ->map(fn (string $f) => [
                'nombre' => basename($f),
                'tamano' => $this->formatoTamano((int) filesize($f)),
                'fecha' => date('d/m/Y H:i', filemtime($f)),
            ])
            ->values()
            ->all();
    }

    private function formatoTamano(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' bytes';
    }
}
