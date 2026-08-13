<?php

namespace App\Support;

use App\Models\Invoice;
use Carbon\Carbon;

/**
 * Arma todos los agregados del informe de ventas para un rango de fechas.
 * Centralizado acá para que lo usen tanto la pantalla (Reports\Index) como
 * las exportaciones a PDF y CSV, sin duplicar la lógica.
 */
class SalesReport
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $fromDate, string $toDate): array
    {
        $invoices = Invoice::whereNot('status', 'draft')
            ->whereDate('issue_date', '>=', $fromDate)
            ->whereDate('issue_date', '<=', $toDate)
            ->with('items.product.category', 'payments', 'client')
            ->get();

        $summary = [
            'total' => $invoices->sum(fn (Invoice $i) => $i->total),
            'count' => $invoices->count(),
        ];

        $byArticle = collect();
        $byCategory = collect();
        $byMethod = collect();
        $byClient = collect();
        $byDay = collect();
        $byHourBuckets = array_fill(0, 24, ['count' => 0, 'total' => 0.0]);
        $totalCost = 0.0;

        foreach ($invoices as $invoice) {
            $total = (float) $invoice->total;

            $dayKey = $invoice->issue_date->toDateString();
            $day = $byDay->get($dayKey, ['label' => $invoice->issue_date->format('d/m'), 'total' => 0.0, 'count' => 0]);
            $day['total'] += $total;
            $day['count']++;
            $byDay->put($dayKey, $day);

            $clientKey = $invoice->client_id ?? 'sin-cliente';
            $clientLabel = $invoice->client?->name ?? 'Sin cliente';
            $client = $byClient->get($clientKey, ['label' => $clientLabel, 'total' => 0.0, 'count' => 0]);
            $client['total'] += $total;
            $client['count']++;
            $byClient->put($clientKey, $client);

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
        $byClient = $byClient->sortByDesc('total')->take(8)->values();
        $byDay = $byDay->sortKeys()->values();
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

        $days = Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)) + 1;
        $prevTo = Carbon::parse($fromDate)->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);
        $prevTotal = Invoice::whereNot('status', 'draft')
            ->whereDate('issue_date', '>=', $prevFrom)
            ->whereDate('issue_date', '<=', $prevTo)
            ->get()
            ->sum(fn (Invoice $i) => $i->total);
        $variationPct = $prevTotal > 0 ? (($summary['total'] - $prevTotal) / $prevTotal) * 100 : null;

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'summary' => $summary,
            'byArticle' => $byArticle,
            'byCategory' => $byCategory,
            'byMethod' => $byMethod,
            'byClient' => $byClient,
            'byDay' => $byDay,
            'byHour' => $byHour,
            'maxArticle' => $byArticle->max('total') ?? 0,
            'maxCategory' => $byCategory->max('total') ?? 0,
            'maxMethod' => $byMethod->max('total') ?? 0,
            'maxClient' => $byClient->max('total') ?? 0,
            'maxDay' => $byDay->max('total') ?? 0,
            'maxHour' => $byHour->max('total') ?? 0,
            'profitability' => $profitability,
            'variationPct' => $variationPct,
        ];
    }
}
