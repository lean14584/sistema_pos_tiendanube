<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Support\CurrentSucursal;
use App\Support\SalesReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    /**
     * Igual que Reports\Index: un cajero/vendedor solo puede exportar la suya,
     * sin importar lo que venga en la URL — si no se hiciera este chequeo acá
     * también, alguien podría pegar la URL de export con otro sucursal_id (o
     * ninguno) y esquivar el scope que sí aplica la pantalla.
     */
    private function sucursalId(Request $request): ?int
    {
        if (! Auth::user()?->esAdminGlobal()) {
            return CurrentSucursal::id();
        }

        return $request->filled('sucursal_id') ? (int) $request->input('sucursal_id') : null;
    }

    public function pdf(Request $request)
    {
        $rango = $this->rango($request);
        $data = SalesReport::build($rango['fromDate'], $rango['toDate'], $this->sucursalId($request));
        $data['company'] = CompanySettings::current();

        $nombre = 'informe-ventas-'.$rango['fromDate'].'-a-'.$rango['toDate'].'.pdf';

        return Pdf::loadView('pdf.reports', $data)->download($nombre);
    }

    public function csv(Request $request): StreamedResponse
    {
        $rango = $this->rango($request);
        $data = SalesReport::build($rango['fromDate'], $rango['toDate'], $this->sucursalId($request));

        $nombre = 'informe-ventas-'.$rango['fromDate'].'-a-'.$rango['toDate'].'.csv';

        return response()->streamDownload(function () use ($data, $rango) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 para que Excel muestre bien los acentos.
            fwrite($out, "\xEF\xBB\xBF");

            $money = fn ($v) => number_format((float) $v, 2, ',', '');
            $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
            // Antepone ' a celdas que empiecen con = + - @: evita que Excel/LibreOffice
            // las interprete como fórmula (ej. un nombre de cliente sincronizado desde
            // Tiendanube, que no controlamos, con un payload tipo =HYPERLINK(...)).
            $safe = function ($v) {
                if (is_string($v) && preg_match('/^[=+\-@]/', $v)) {
                    return "'".$v;
                }

                return $v;
            };
            // Separador ; (convención es-AR para que Excel abra en columnas).
            $row = fn (array $cols) => fputcsv($out, array_map($safe, $cols), ';');

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

            if ($data['bySucursal']->count() > 1) {
                $row(['Ventas por sucursal']);
                $row(['Sucursal', 'Ventas', 'Total']);
                foreach ($data['bySucursal'] as $r) {
                    $row([$r['label'], $r['count'], $money($r['total'])]);
                }
                $row([]);
            }

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
