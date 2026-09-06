<?php

namespace App\Livewire;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sucursal;
use App\Support\CurrentSucursal;
use App\Support\Permissions;
use App\Support\SalesReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    #[Url]
    public int $year;

    /** null (string vacío) = todas las sucursales consolidadas. Solo un admin global puede elegir esto. */
    #[Url]
    public string $sucursal_id = '';

    public function mount(): void
    {
        $this->year ??= (int) now()->year;
    }

    public function puedeVerTodasLasSucursales(): bool
    {
        return (bool) Auth::user()?->esAdminGlobal();
    }

    /**
     * Un cajero/vendedor solo puede ver la de su propia sucursal, sin
     * importar lo que traiga la URL — mismo criterio que Informes.
     */
    private function sucursalIdActiva(): ?int
    {
        return $this->puedeVerTodasLasSucursales()
            ? ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null)
            : CurrentSucursal::id();
    }

    public function render()
    {
        $sucursalId = $this->sucursalIdActiva();

        // El Dashboard es una pantalla de resumen: sus agregados recorren
        // TODA la historia de facturas, así que se cachean por año (TTL corto)
        // para no recalcular toda la base en cada visita. Las consultas
        // baratas y que conviene ver al instante (facturas recientes, stock
        // bajo) quedan fuera del caché, siempre frescas. La clave de caché
        // incluye la sucursal: sin esto, dos usuarios de sucursales
        // distintas (o un admin filtrando y otro viendo "todas") compartirían
        // el mismo resultado cacheado y verían los números de otro local.
        $agg = Cache::remember(
            "dashboard:agg:{$this->year}:".($sucursalId ?? 'todas'),
            now()->addSeconds(120),
            fn () => $this->aggregates($sucursalId)
        );

        $recentInvoices = Invoice::with('client', 'items')
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->latest()->take(5)->get();
        $lowStockProducts = Product::lowStock()->with('category')->orderBy('stock')->take(5)->get();

        return view('livewire.dashboard', [
            'stats' => $agg['stats'],
            'recentInvoices' => $recentInvoices,
            'lowStockCount' => Product::lowStockCountCached(),
            'lowStockProducts' => $lowStockProducts,
            'pendientesEmisionCount' => $agg['pendientesEmisionCount'],
            'canSeeHealth' => Auth::user() && Permissions::canAccess(Auth::user()->role, 'health'),
            'systemWarnings' => Auth::user() && Permissions::canAccess(Auth::user()->role, 'health')
                ? app(\App\Support\SystemHealth::class)->avisos()
                : 0,
            'canManageInvoices' => Auth::user() && Permissions::canAccess(Auth::user()->role, 'invoices'),
            'canManageProducts' => Auth::user() && Permissions::canAccess(Auth::user()->role, 'products'),
            'topProducts' => collect($agg['topProducts']),
            'maxTopProduct' => collect($agg['topProducts'])->max('total') ?? 0,
            'monthlySales' => $agg['monthlySales'],
            'maxMonthlySales' => max($agg['monthlySales']) ?: 0,
            'availableYears' => collect($agg['availableYears']),
            'byCategory' => collect($agg['byCategory']),
            'maxCategory' => collect($agg['byCategory'])->max('total') ?? 0,
            'byMethod' => collect($agg['byMethod']),
            'maxMethod' => collect($agg['byMethod'])->max('total') ?? 0,
            'bySucursal' => collect($agg['bySucursal']),
            'maxSucursal' => collect($agg['bySucursal'])->max('total') ?? 0,
            'puedeVerTodasLasSucursales' => $this->puedeVerTodasLasSucursales(),
            'sucursales' => $this->puedeVerTodasLasSucursales() ? Sucursal::orderBy('name')->get() : collect(),
        ]);
    }

    /**
     * Agregados pesados del dashboard (recorren toda la historia). Devuelve
     * arrays serializables para poder cachearlos. Se calcula igual que antes,
     * así que los números no cambian; solo se recalcula al vencer el TTL.
     *
     * @return array<string, mixed>
     */
    private function aggregates(?int $sucursalId): array
    {
        $invoices = Invoice::with('items.product')
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get();

        $paid = $invoices->where('status', InvoiceStatus::Paid);
        $pending = $invoices->filter(fn (Invoice $i) => $i->status === InvoiceStatus::Pending && ! $i->is_overdue);
        $overdue = $invoices->filter(fn (Invoice $i) => $i->is_overdue);
        $nonDraft = $invoices->reject(fn (Invoice $i) => $i->status === InvoiceStatus::Draft);

        $stats = [
            'totalRevenue' => $paid->sum(fn (Invoice $i) => $i->total),
            'pendingAmount' => $pending->sum(fn (Invoice $i) => $i->total),
            'overdueCount' => $overdue->count(),
            'totalInvoices' => $invoices->count(),
        ];

        // Facturas fiscales finalizadas pero todavía sin CAE: faltan emitir a
        // AFIP para que entren al Libro IVA.
        $pendientesEmision = $nonDraft->filter(
            fn (Invoice $i) => $i->tipo_comprobante_interno->esFiscal() && $i->cae === null
        );

        $topProducts = collect();
        foreach ($nonDraft as $invoice) {
            foreach ($invoice->items as $item) {
                $key = $item->product_id ?? "sin-producto-{$item->description}";
                $current = $topProducts->get($key, ['label' => $item->product?->name ?? $item->description, 'total' => 0.0]);
                $current['total'] += (float) $item->line_total;
                $topProducts->put($key, $current);
            }
        }
        $topProducts = $topProducts->sortByDesc('total')->take(5)->values();

        $years = $invoices->map(fn (Invoice $i) => $i->issue_date->year)->all();
        $years[] = (int) now()->year;
        $availableYears = collect($years)->unique()->sortDesc()->values();

        $monthlySales = array_fill(1, 12, 0.0);
        foreach ($nonDraft as $invoice) {
            if ($invoice->issue_date->year === $this->year) {
                $monthlySales[$invoice->issue_date->month] += (float) $invoice->total;
            }
        }

        // Consolidado del año elegido, por categoría y por medio de pago:
        // reusa SalesReport (mismo agregado que Informes) en vez de duplicar
        // la lógica de agrupamiento acá.
        $anio = SalesReport::build("{$this->year}-01-01", "{$this->year}-12-31", $sucursalId);

        return [
            'stats' => $stats,
            'pendientesEmisionCount' => $pendientesEmision->count(),
            'topProducts' => $topProducts->all(),
            'monthlySales' => $monthlySales,
            'availableYears' => $availableYears->all(),
            'byCategory' => $anio['byCategory']->take(5)->all(),
            'byMethod' => $anio['byMethod']->all(),
            'bySucursal' => $anio['bySucursal']->all(),
        ];
    }
}
