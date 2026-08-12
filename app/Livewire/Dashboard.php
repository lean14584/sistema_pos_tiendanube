<?php

namespace App\Livewire;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    #[Url]
    public int $year;

    public function mount(): void
    {
        $this->year ??= (int) now()->year;
    }

    public function render()
    {
        $invoices = Invoice::with('client', 'items.product')->get();

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

        $recentInvoices = $invoices->sortByDesc('created_at')->take(5);

        // Facturas fiscales finalizadas pero todavía sin CAE: faltan emitir a
        // AFIP para que entren al Libro IVA.
        $pendientesEmision = $nonDraft->filter(
            fn (Invoice $i) => $i->tipo_comprobante_interno->esFiscal() && $i->cae === null
        );

        $lowStockProducts = Product::lowStock()->with('category')->orderBy('stock')->take(5)->get();

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

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recentInvoices' => $recentInvoices,
            'lowStockCount' => Product::lowStockCountCached(),
            'lowStockProducts' => $lowStockProducts,
            'pendientesEmisionCount' => $pendientesEmision->count(),
            'canSeeHealth' => Auth::user() && Permissions::canAccess(Auth::user()->role, 'health'),
            'systemWarnings' => Auth::user() && Permissions::canAccess(Auth::user()->role, 'health')
                ? app(\App\Support\SystemHealth::class)->avisos()
                : 0,
            'canManageInvoices' => Auth::user() && Permissions::canAccess(Auth::user()->role, 'invoices'),
            'canManageProducts' => Auth::user() && Permissions::canAccess(Auth::user()->role, 'products'),
            'topProducts' => $topProducts,
            'maxTopProduct' => $topProducts->max('total') ?? 0,
            'monthlySales' => $monthlySales,
            'maxMonthlySales' => max($monthlySales) ?: 0,
            'availableYears' => $availableYears,
        ]);
    }
}
