<?php

namespace App\Livewire\Reports;

use App\Models\Invoice;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    #[Url]
    public string $fromDate;

    #[Url]
    public string $toDate;

    public function mount(): void
    {
        $this->fromDate ??= now()->subDays(30)->toDateString();
        $this->toDate ??= now()->toDateString();
    }

    public function render()
    {
        $invoices = Invoice::whereNot('status', 'draft')
            ->whereDate('issue_date', '>=', $this->fromDate)
            ->whereDate('issue_date', '<=', $this->toDate)
            ->with('items.product.category', 'payments')
            ->get();

        $summary = [
            'total' => $invoices->sum(fn (Invoice $i) => $i->total),
            'count' => $invoices->count(),
        ];

        $byArticle = collect();
        $byCategory = collect();
        $byMethod = collect();
        $byHourBuckets = array_fill(0, 24, ['count' => 0, 'total' => 0.0]);
        $totalCost = 0.0;

        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                $lineTotal = (float) $item->line_total;
                $totalCost += (float) $item->quantity * (float) ($item->product?->cost_price ?? 0);

                $articleKey = $item->product_id ?? 'sin-producto';
                $articleLabel = $item->product?->name ?? 'Sin producto vinculado';
                $article = $byArticle->get($articleKey, ['label' => $articleLabel, 'quantity' => 0, 'total' => 0.0]);
                $article['quantity'] += (float) $item->quantity;
                $article['total'] += $lineTotal;
                $byArticle->put($articleKey, $article);

                $categoryId = $item->product?->category_id;
                $categoryKey = $categoryId ?? 'sin-categoria';
                $categoryLabel = $item->product?->category?->name ?? 'Sin categoría';
                $category = $byCategory->get($categoryKey, ['label' => $categoryLabel, 'quantity' => 0, 'total' => 0.0]);
                $category['quantity'] += (float) $item->quantity;
                $category['total'] += $lineTotal;
                $byCategory->put($categoryKey, $category);
            }

            foreach ($invoice->payments as $payment) {
                $methodKey = $payment->method->value;
                $method = $byMethod->get($methodKey, ['label' => $payment->method->label(), 'total' => 0.0]);
                $method['total'] += (float) $payment->amount;
                $byMethod->put($methodKey, $method);
            }

            $hour = (int) $invoice->created_at->format('G');
            $byHourBuckets[$hour]['count']++;
            $byHourBuckets[$hour]['total'] += (float) $invoice->total;
        }

        $byArticle = $byArticle->sortByDesc('total')->values();
        $byCategory = $byCategory->sortByDesc('total')->values();
        $byMethod = $byMethod->sortByDesc('total')->values();
        $byHour = collect($byHourBuckets)
            ->map(fn ($bucket, $hour) => array_merge($bucket, ['hour' => $hour]))
            ->filter(fn ($bucket) => $bucket['count'] > 0)
            ->values();

        $grossProfit = $summary['total'] - $totalCost;
        $profitability = [
            'cost' => $totalCost,
            'profit' => $grossProfit,
            'marginPct' => $summary['total'] > 0 ? ($grossProfit / $summary['total']) * 100 : 0,
        ];

        $days = Carbon::parse($this->fromDate)->diffInDays(Carbon::parse($this->toDate)) + 1;
        $prevTo = Carbon::parse($this->fromDate)->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);
        $prevTotal = Invoice::whereNot('status', 'draft')
            ->whereDate('issue_date', '>=', $prevFrom)
            ->whereDate('issue_date', '<=', $prevTo)
            ->get()
            ->sum(fn (Invoice $i) => $i->total);
        $variationPct = $prevTotal > 0 ? (($summary['total'] - $prevTotal) / $prevTotal) * 100 : null;

        return view('livewire.reports.index', [
            'summary' => $summary,
            'byArticle' => $byArticle,
            'byCategory' => $byCategory,
            'byMethod' => $byMethod,
            'byHour' => $byHour,
            'maxArticle' => $byArticle->max('total') ?? 0,
            'maxCategory' => $byCategory->max('total') ?? 0,
            'maxMethod' => $byMethod->max('total') ?? 0,
            'maxHour' => $byHour->max('total') ?? 0,
            'profitability' => $profitability,
            'variationPct' => $variationPct,
        ]);
    }
}
