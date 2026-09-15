<?php

namespace App\Support;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Arma todos los agregados del informe de ventas para un rango de fechas.
 * Centralizado acá para que lo usen tanto la pantalla (Reports\Index) como
 * las exportaciones a PDF y CSV, sin duplicar la lógica.
 */
class SalesReport
{
    /**
     * $sucursalId: si se pasa, el informe queda acotado a esa sucursal (lo
     * usa un cajero/vendedor, que solo puede ver la suya). null = todas las
     * sucursales consolidadas (solo lo elige un admin global).
     *
     * @return array<string, mixed>
     */
    public static function build(string $fromDate, string $toDate, ?int $sucursalId = null): array
    {
        // MEJORA: sin caché, esta pantalla (y sus exports PDF/CSV) recorría
        // TODA la historia de facturas con items/payments/client/sucursal
        // eager-cargados en cada render — hasta 3 veces en el mismo request
        // si además se pide comparación con el período anterior. TTL corto
        // (mismo criterio que Dashboard) porque el informe tiene que
        // reflejar ventas recién hechas, solo evita repetir el escaneo
        // completo en renders/exports consecutivos del mismo rango.
        $cacheKey = "sales-report:{$fromDate}:{$toDate}:".($sucursalId ?? 'todas');

        return Cache::remember($cacheKey, now()->addSeconds(60), fn () => self::buildUncached($fromDate, $toDate, $sucursalId));
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildUncached(string $fromDate, string $toDate, ?int $sucursalId): array
    {
        // MEJORA: whereDate() envuelve la columna en DATE(...), lo que
        // impide usar el índice de issue_date. Ojo: aunque la columna es
        // `date`, el cast 'date' de Eloquent solo trunca la HORA al leer
        // — al guardar usa el formato genérico del modelo (con hora), así
        // que en SQLite (tests) queda con parte de hora real; MySQL sí la
        // trunca solo porque el tipo de columna lo fuerza. Por eso el corte
        // superior usa "< día siguiente" (no ">= igual a $toDate") — así
        // funciona para los dos casos sin dejar de ser un rango sargable.
        $invoices = Invoice::whereNot('status', 'draft')
            ->where('issue_date', '>=', $fromDate)
            ->where('issue_date', '<', Carbon::parse($toDate)->addDay()->toDateString())
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->with('items.product.category', 'payments', 'client', 'sucursal')
            ->get();

        $summary = [
            'total' => $invoices->sum(fn (Invoice $i) => $i->total),
            'count' => $invoices->count(),
        ];

        $byArticle = collect();
        $byCategory = collect();
        $byMethod = collect();
        $byClient = collect();
        $bySucursal = collect();
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

            $sucursalKey = $invoice->sucursal_id ?? 'sin-sucursal';
            $sucursalLabel = $invoice->sucursal?->name ?? 'Sin sucursal';
            $suc = $bySucursal->get($sucursalKey, ['label' => $sucursalLabel, 'total' => 0.0, 'count' => 0]);
            $suc['total'] += $total;
            $suc['count']++;
            $bySucursal->put($sucursalKey, $suc);

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
        $bySucursal = $bySucursal->sortByDesc('total')->values();
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
            ->where('issue_date', '>=', $prevFrom->toDateString())
            ->where('issue_date', '<', $prevTo->copy()->addDay()->toDateString())
            ->when($sucursalId !== null, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->with('items')
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
            'bySucursal' => $bySucursal,
            'byDay' => $byDay,
            'byHour' => $byHour,
            'maxArticle' => $byArticle->max('total') ?? 0,
            'maxCategory' => $byCategory->max('total') ?? 0,
            'maxMethod' => $byMethod->max('total') ?? 0,
            'maxClient' => $byClient->max('total') ?? 0,
            'maxSucursal' => $bySucursal->max('total') ?? 0,
            'maxDay' => $byDay->max('total') ?? 0,
            'maxHour' => $byHour->max('total') ?? 0,
            'profitability' => $profitability,
            'variationPct' => $variationPct,
        ];
    }
}
