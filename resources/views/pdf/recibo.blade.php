<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 40px 44px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        .muted { color: #6b7280; }
        .small { font-size: 10px; }

        .brand-band { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .brand-band td { padding: 0; vertical-align: top; }
        .brand-mark { display: inline-block; width: 14px; height: 14px; background-color: #4f46e5; border-radius: 3px; }
        .brand-name { font-size: 13px; font-weight: bold; color: #111827; padding-left: 6px; }
        .doc-kind { font-size: 13px; font-weight: bold; letter-spacing: 1px; color: #4f46e5; }
        .doc-title { font-size: 20px; font-weight: bold; color: #111827; margin: 0; }

        .box {
            border: 1px solid #e5e7eb; background-color: #f9fafb; border-radius: 6px;
            padding: 16px 18px; margin: 18px 0;
        }
        .row { width: 100%; border-collapse: collapse; }
        .row td { padding: 4px 0; vertical-align: top; }
        .label { font-size: 9px; text-transform: uppercase; color: #6b7280; }
        .value { font-weight: bold; color: #111827; }

        .amount-box {
            border: 2px solid #4f46e5; border-radius: 8px; padding: 12px 16px; margin: 18px 0;
            text-align: center;
        }
        .amount-box .k { font-size: 9px; text-transform: uppercase; color: #6b7280; }
        .amount-box .v { font-size: 26px; font-weight: bold; color: #111827; }

        .saldo { text-align: right; font-size: 12px; margin-top: 8px; }
        .saldo .num { font-weight: bold; }

        .sign { margin-top: 60px; width: 100%; border-collapse: collapse; }
        .sign td { padding-top: 26px; border-top: 1px solid #9ca3af; text-align: center; color: #6b7280; font-size: 10px; width: 60%; }
        .sign .gap { border: 0; width: 40%; }

        .footer-note { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 9px; }
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
                <div class="doc-kind">RECIBO DE COBRANZA</div>
                <p class="doc-title">N° {{ $numero }}</p>
                <div class="muted small">Fecha: {{ $payment->date->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="box">
        <table class="row">
            <tr>
                <td style="width: 60%;">
                    <div class="label">Recibimos de</div>
                    <div class="value">{{ $client->name }}</div>
                    @if ($client->tax_id)<div class="muted small">ID fiscal: {{ $client->tax_id }}</div>@endif
                </td>
                <td style="width: 40%;">
                    <div class="label">Medio de pago</div>
                    <div class="value">{{ $payment->method->label() }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top: 10px;">
                    <div class="label">En concepto de</div>
                    <div class="value">{{ $payment->notes ?: 'Pago a cuenta corriente' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="amount-box">
        <div class="k">La suma de</div>
        <div class="v">${{ money($payment->amount) }}</div>
    </div>

    <div class="saldo muted">
        Saldo restante en cuenta corriente:
        <span class="num" style="color: {{ $saldo > 0.009 ? '#dc2626' : '#059669' }};">${{ money(max(0, $saldo)) }}</span>
        {{ $saldo > 0.009 ? '' : '(sin saldo pendiente)' }}
    </div>

    <table class="sign">
        <tr>
            <td class="gap"></td>
            <td>Firma y aclaración</td>
        </tr>
    </table>

    <p class="footer-note">Recibo emitido por {{ $company->display_name }}. Documento no válido como factura.</p>
</body>
</html>
