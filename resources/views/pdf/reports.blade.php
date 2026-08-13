<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 40px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1f2937;
            font-size: 11px;
            margin: 0;
        }

        h1 { font-size: 20px; color: #111827; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .small { font-size: 10px; }

        .brand-name { font-size: 13px; font-weight: bold; color: #111827; margin-bottom: 10px; }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0 8px;
        }
        .summary td {
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            padding: 8px 10px;
            width: 20%;
            text-align: center;
        }
        .summary .k { font-size: 9px; text-transform: uppercase; color: #6b7280; }
        .summary .v { font-size: 14px; font-weight: bold; color: #111827; }

        h2 {
            font-size: 12px;
            color: #111827;
            margin: 18px 0 6px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 3px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #d1d5db;
            padding: 5px 6px;
        }
        table.data td {
            padding: 5px 6px;
            border-bottom: 1px solid #f3f4f6;
        }
        table.data td.r, table.data th.r { text-align: right; }
        .two-col { width: 100%; border-collapse: collapse; }
        .two-col td { vertical-align: top; width: 50%; padding: 0 8px; }
    </style>
</head>
<body>
    <div class="brand-name">{{ $company->display_name }}</div>
    <h1>Informe de ventas</h1>
    <p class="muted small">
        Período {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d/m/Y') }}
        al {{ \Illuminate\Support\Carbon::parse($toDate)->format('d/m/Y') }}
        · Generado el {{ now()->format('d/m/Y H:i') }}
    </p>

    <table class="summary">
        <tr>
            <td><div class="k">Total vendido</div><div class="v">${{ number_format($summary['total'], 2) }}</div></td>
            <td><div class="k">Facturas</div><div class="v">{{ $summary['count'] }}</div></td>
            <td><div class="k">Costo mercadería</div><div class="v">${{ number_format($profitability['cost'], 2) }}</div></td>
            <td><div class="k">Ganancia bruta</div><div class="v">${{ number_format($profitability['profit'], 2) }}</div></td>
            <td><div class="k">Margen</div><div class="v">{{ number_format($profitability['marginPct'], 1) }}%</div></td>
        </tr>
    </table>

    @if ($summary['count'] === 0)
        <p class="muted">No hay ventas en el período seleccionado.</p>
    @else
        <h2>Ventas por día</h2>
        <table class="data">
            <thead><tr><th>Día</th><th class="r">Facturas</th><th class="r">Total</th></tr></thead>
            <tbody>
                @foreach ($byDay as $r)
                    <tr><td>{{ $r['label'] }}</td><td class="r">{{ $r['count'] }}</td><td class="r">${{ number_format($r['total'], 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <table class="two-col">
            <tr>
                <td>
                    <h2>Ventas por artículo</h2>
                    <table class="data">
                        <thead><tr><th>Artículo</th><th class="r">Cant.</th><th class="r">Total</th></tr></thead>
                        <tbody>
                            @foreach ($byArticle as $r)
                                <tr><td>{{ $r['label'] }}</td><td class="r">{{ rtrim(rtrim(number_format($r['quantity'], 2), '0'), '.') }}</td><td class="r">${{ number_format($r['total'], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
                <td>
                    <h2>Ventas por categoría</h2>
                    <table class="data">
                        <thead><tr><th>Categoría</th><th class="r">Cant.</th><th class="r">Total</th></tr></thead>
                        <tbody>
                            @foreach ($byCategory as $r)
                                <tr><td>{{ $r['label'] }}</td><td class="r">{{ rtrim(rtrim(number_format($r['quantity'], 2), '0'), '.') }}</td><td class="r">${{ number_format($r['total'], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <table class="two-col">
            <tr>
                <td>
                    <h2>Medios de pago</h2>
                    <table class="data">
                        <thead><tr><th>Medio</th><th class="r">Total</th></tr></thead>
                        <tbody>
                            @forelse ($byMethod as $r)
                                <tr><td>{{ $r['label'] }}</td><td class="r">${{ number_format($r['total'], 2) }}</td></tr>
                            @empty
                                <tr><td class="muted" colspan="2">Sin pagos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
                <td>
                    <h2>Top clientes</h2>
                    <table class="data">
                        <thead><tr><th>Cliente</th><th class="r">Fact.</th><th class="r">Total</th></tr></thead>
                        <tbody>
                            @foreach ($byClient as $r)
                                <tr><td>{{ $r['label'] }}</td><td class="r">{{ $r['count'] }}</td><td class="r">${{ number_format($r['total'], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <h2>Ventas por hora del día</h2>
        <table class="data">
            <thead><tr><th>Hora</th><th class="r">Ventas</th><th class="r">Total</th></tr></thead>
            <tbody>
                @foreach ($byHour as $r)
                    <tr><td>{{ str_pad($r['hour'], 2, '0', STR_PAD_LEFT) }}:00 – {{ str_pad($r['hour'], 2, '0', STR_PAD_LEFT) }}:59</td><td class="r">{{ $r['count'] }}</td><td class="r">${{ number_format($r['total'], 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
