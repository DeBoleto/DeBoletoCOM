<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $event->evento }}</title>
    <style>
        @page { margin: 28px 34px 56px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #16202b; margin: 0; }
        .tabular { font-variant-numeric: tabular-nums; }
        .muted { color: #5a6672; }
        .right { text-align: right; }
        .center { text-align: center; }
        .masthead { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .masthead td { vertical-align: top; padding: 0; }
        .masthead h1 { font-size: 18px; margin: 0 0 2px; }
        .masthead .event { font-size: 12px; font-weight: bold; margin: 0; }
        .brand { font-size: 13px; font-weight: bold; color: #1f8f6b; }
        .facts { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f4f6f5; }
        .facts td { padding: 7px 10px; vertical-align: top; }
        .facts .label { color: #5a6672; font-size: 8.5px; display: block; }
        .function { page-break-inside: avoid; margin-top: 16px; }
        .function h2 { font-size: 12.5px; margin: 0 0 8px; padding-bottom: 5px; border-bottom: 2px solid #16202b; }
        .breakdown { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .breakdown caption { text-align: left; font-weight: bold; font-size: 10.5px; padding: 4px 0; color: #16202b; }
        .breakdown .num { text-align: right; }
        .breakdown th { background: #16202b; color: #fff; font-weight: bold; padding: 5px 7px; font-size: 9px; text-align: left; }
        .breakdown td { padding: 5px 7px; border-bottom: 1px solid #dce2df; }
        .breakdown tfoot td { font-weight: bold; border-top: 1.5px solid #16202b; border-bottom: 0; background: #f4f6f5; }
        .breakdown .empty td { color: #8a949d; font-style: italic; }
        .page-break { page-break-after: always; }
        footer { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 8px; color: #8a949d; border-top: 1px solid #dce2df; padding-top: 6px; }
        footer table { width: 100%; }
    </style>
</head>
<body>
    <footer>
        <table>
            <tr>
                <td>Reporte generado por DeBoleto el {{ $generatedAt->format('d/m/Y H:i') }} h. Dudas o aclaraciones: contacto@deboleto.com, tel. (993) 396 3602</td>
            </tr>
        </table>
    </footer>

    <table class="masthead">
        <tr>
            <td>
                <h1>{{ $title }}</h1>
                <p class="event">{{ $event->evento }}</p>
                <p class="muted" style="margin: 2px 0 0">{{ $period }}</p>
            </td>
            <td class="right" style="width: 30%">
                <span class="brand">DeBoleto</span><br>
                <span class="muted">Taquilla</span>
            </td>
        </tr>
    </table>

    <table class="facts">
        <tr>
            @isset($seller)
                <td style="width: 34%"><span class="label">Vendedor</span>{{ $seller->nombreCompleto() ?: $seller->correo }}<br><span class="muted">{{ $seller->correo }}</span></td>
            @endisset
            <td style="width: 33%"><span class="label">Recinto</span>{{ $event->escenario }}<br><span class="muted">{{ $event->direccion }}</span></td>
            <td style="width: 33%"><span class="label">Ciudad</span>{{ $event->ciudad }}, {{ $event->estado }}</td>
        </tr>
    </table>

    @yield('content')
</body>
</html>
