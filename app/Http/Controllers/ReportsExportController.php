<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Support\SalesReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsExportController extends Controller
{
    private function rango(Request $request): array
    {
        return $request->validate([
            'fromDate' => ['required', 'date'],
            'toDate' => ['required', 'date', 'after_or_equal:fromDate'],
        ]);
    }

    public function pdf(Request $request)
    {
        $rango = $this->rango($request);
        $data = SalesReport::build($rango['fromDate'], $rango['toDate']);
        $data['company'] = CompanySettings::current();

        $nombre = 'informe-ventas-'.$rango['fromDate'].'-a-'.$rango['toDate'].'.pdf';

        return Pdf::loadView('pdf.reports', $data)->download($nombre);
    }

    public function csv(Request $request): StreamedResponse
    {
        $rango = $this->rango($request);
        $data = SalesReport::build($rango['fromDate'], $rango['toDate']);

        $nombre = 'informe-ventas-'.$rango['fromDate'].'-a-'.$rango['toDate'].'.csv';

        return response()->streamDownload(function () use ($data, $rango) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 para que Excel muestre bien los acentos.
            fwrite($out, "\xEF\xBB\xBF");

            $money = fn ($v) => number_format((float) $v, 2, ',', '');
            $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
            // Separador ; (convención es-AR para que Excel abra en columnas).
            $row = fn (array $cols) => fputcsv($out, $cols, ';');

            $row(['Informe de ventas']);
            $row(['Periodo', $rango['fromDate'].' a '.$rango['toDate']]);
            $row([]);

            $row(['Resumen']);
            $row(['Total vendido', $money($data['summary']['total'])]);
            $row(['Cantidad de facturas', $data['summary']['count']]);
            $row(['Costo de mercaderia', $money($data['profitability']['cost'])]);
            $row(['Ganancia bruta', $money($data['profitability']['profit'])]);
            $row(['Margen %', $money($data['profitability']['marginPct'])]);
            $row([]);

            $row(['Ventas por dia']);
            $row(['Dia', 'Facturas', 'Total']);
            foreach ($data['byDay'] as $r) {
                $row([$r['label'], $r['count'], $money($r['total'])]);
            }
            $row([]);

            $row(['Ventas por articulo']);
            $row(['Articulo', 'Cantidad', 'Total']);
            foreach ($data['byArticle'] as $r) {
                $row([$r['label'], $num($r['quantity']), $money($r['total'])]);
            }
            $row([]);

            $row(['Ventas por categoria']);
            $row(['Categoria', 'Cantidad', 'Total']);
            foreach ($data['byCategory'] as $r) {
                $row([$r['label'], $num($r['quantity']), $money($r['total'])]);
            }
            $row([]);

            $row(['Ventas por medio de pago']);
            $row(['Medio', 'Total']);
            foreach ($data['byMethod'] as $r) {
                $row([$r['label'], $money($r['total'])]);
            }
            $row([]);

            $row(['Top clientes']);
            $row(['Cliente', 'Facturas', 'Total']);
            foreach ($data['byClient'] as $r) {
                $row([$r['label'], $r['count'], $money($r['total'])]);
            }
            $row([]);

            $row(['Ventas por hora']);
            $row(['Hora', 'Ventas', 'Total']);
            foreach ($data['byHour'] as $r) {
                $row([str_pad($r['hour'], 2, '0', STR_PAD_LEFT).':00', $r['count'], $money($r['total'])]);
            }

            fclose($out);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
