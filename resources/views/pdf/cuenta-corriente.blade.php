<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 40px 44px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 11px; margin: 0; }
        .muted { color: #6b7280; }
        .small { font-size: 9px; }

        .brand-band { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .brand-band td { padding: 0; vertical-align: top; }
        .brand-mark { display: inline-block; width: 14px; height: 14px; background-color: #4f46e5; border-radius: 3px; }
        .brand-name { font-size: 13px; font-weight: bold; color: #111827; padding-left: 6px; }
        .doc-kind { font-size: 13px; font-weight: bold; letter-spacing: 1px; color: #4f46e5; }
        .doc-title { font-size: 20px; font-weight: bold; color: #111827; margin: 0; }

        .box {
            border: 1px solid #e5e7eb; background-color: #f9fafb; border-radius: 6px;
            padding: 14px 18px; margin: 16px 0;
        }
        .row { width: 100%; border-collapse: collapse; }
        .row td { padding: 4px 0; vertical-align: top; }
        .label { font-size: 9px; text-transform: uppercase; color: #6b7280; }
        .value { font-weight: bold; color: #111827; }

        table.ledger { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.ledger th {
            text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7280;
            padding: 6px 8px; border-bottom: 1px solid #d1d5db; background-color: #f3f4f6;
        }
        table.ledger td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; }
        table.ledger td.num { text-align: right; }

        .saldo-box {
            border: 2px solid #4f46e5; border-radius: 8px; padding: 10px 16px; margin: 16px 0;
            text-align: right;
        }
        .saldo-box .k { font-size: 9px; text-transform: uppercase; color: #6b7280; }
        .saldo-box .v { font-size: 22px; font-weight: bold; }

        .footer-note { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 9px; }
    </style>
</head>
<body>
    <table class="brand-band">
        <tr>
            <td style="width: 55%;">
                @if ($logoPath)
                    <img src="{{ $logoPath }}" style="height: 32px; vertical-align: middle;">
                @else
                    <span class="brand-mark"></span>
                @endif
                <span class="brand-name">{{ $company->display_name }}</span>
                @if ($company->domicilio)<div class="muted small" style="margin-top: 4px;">{{ $company->domicilio }}</div>@endif
                @if ($company->cuit)<div class="muted small">CUIT: {{ $company->cuit }}</div>@endif
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="doc-kind">CUENTA CORRIENTE</div>
                <p class="doc-title">{{ $subjectName }}</p>
                <div class="muted small">Generado el {{ $generatedAt->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="box">
        <table class="row">
            <tr>
                <td>
                    <div class="label">{{ $subjectLabel }}</div>
                    <div class="value">{{ $subjectName }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if ($movements->isEmpty())
        <p class="muted">Sin movimientos registrados.</p>
    @else
        <table class="ledger">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Detalle</th>
                    <th class="num">Debe</th>
                    <th class="num">Haber</th>
                    <th class="num">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $m)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($m['date'])->format('d/m/Y') }}</td>
                        <td>{{ $m['description'] }}</td>
                        <td class="num">{{ $m['debit'] ? '$'.money($m['debit']) : '—' }}</td>
                        <td class="num">{{ $m['credit'] ? '$'.money($m['credit']) : '—' }}</td>
                        <td class="num">${{ money($m['balance']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="saldo-box">
        <div class="k">{{ $saldo > 0.009 ? $balanceOwedLabel : 'Saldo' }}</div>
        <div class="v" style="color: {{ $saldo > 0.009 ? '#dc2626' : '#059669' }};">
            ${{ money(abs($saldo)) }}
            @if ($saldo <= 0.009)<span style="font-size: 11px; font-weight: normal;"> (sin saldo pendiente)</span>@endif
        </div>
    </div>

    <p class="footer-note">Resumen de cuenta corriente emitido por {{ $company->display_name }}. Documento no válido como factura.</p>
</body>
</html>
