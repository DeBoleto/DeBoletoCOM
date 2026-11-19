@use('App\Modules\BoxOffice\Support\SpanishDate')
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $event->evento }}</title>
    <style>
        @page { margin: 28px 34px 64px; }
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; margin: 0; }
        hr { background-color: #2ba17c; height: 2px; border: 0; margin: 4px 0 10px; }
        .tabular { font-variant-numeric: tabular-nums; }
        .muted { color: #5a6672; }
        .right { text-align: right; }
        .center { text-align: center; }
        .masthead { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .masthead td { vertical-align: middle; padding: 0; }
        .masthead h1 { font-size: 20pt; font-weight: bold; color: #b6bcc0; text-transform: uppercase; margin: 0; }
        .generated { font-size: 11pt; font-weight: bold; color: #000; text-align: right; margin: 0 0 6px; }
        .event-title { font-size: 15pt; font-weight: bold; color: #333; text-transform: uppercase; margin: 18px 0 0; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .info td { padding: 3px 4px; border: 1pt solid #333; vertical-align: top; }
        .info .label { width: 25%; background: #333; color: #fff; font-weight: bold; text-align: right; }
        .info .value { color: #333; text-align: left; }
        .info .spacer { border: 0; }
        .function { page-break-inside: avoid; }
        .section-title { font-size: 9pt; font-weight: bold; color: #b6bcc0; margin: 14px 0 0; padding-bottom: 4px; border-bottom: 2px solid #2ba17c; }
        .breakdown { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        .breakdown th { background: #333; color: #fff; font-weight: bold; text-transform: uppercase; padding: 6px 4px; border: 1pt solid #333; }
        .breakdown td { color: #333; padding: 3px 4px; border: 1pt solid #333; }
        .breakdown th, .breakdown td, .breakdown .right { text-align: center; }
        .breakdown tfoot td { font-weight: bold; }
        .breakdown tfoot td:first-child { background: #333; color: #fff; text-transform: uppercase; }
        .breakdown .empty td { color: #8a949d; font-style: italic; }
        .page-break { page-break-after: always; }
        footer { position: fixed; bottom: -52px; left: 0; right: 0; font-size: 8pt; color: #333; border-top: 2px solid #2ba17c; padding-top: 5px; }
        footer table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    <footer>
        <table>
            <tr>
                <td colspan="2" class="center">Este es un reporte autogenerado por el sistema de DeBoleto, cualquier duda o aclaración respecto a los datos presentados, favor de comunicarse con nosotros.</td>
            </tr>
            <tr>
                <td>contacto@deboleto.com</td>
                <td class="right">Tels. (993) 396 3602 · {{ $generatedAt->format('d/m/Y H:i') }} h</td>
            </tr>
        </table>
    </footer>

    <table class="masthead">
        <tr>
            <td style="width: 70%"><h1>{{ $title }}</h1></td>
            <td class="right" style="width: 30%">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo_full.png'))) }}" alt="DeBoleto" style="height: 34px">
            </td>
        </tr>
    </table>
    <p class="generated">Reporte generado: {{ SpanishDate::long($generatedAt) }}</p>

    @hasSection('per-function')
        @isset($seller)
            <table class="info">
                <tr><td class="label">Vendedor</td><td class="value">{{ $seller->nombreCompleto() ?: $seller->correo }} ({{ $seller->correo }})</td></tr>
                @if (! empty($period))<tr><td class="label">Periodo</td><td class="value">{{ $period }}</td></tr>@endif
            </table>
        @endisset
    @else
        <p class="event-title">{{ $event->evento }}</p>
        <hr>
        <table class="info">
            @isset($seller)
                <tr><td class="label">Vendedor</td><td class="value">{{ $seller->nombreCompleto() ?: $seller->correo }} ({{ $seller->correo }})</td></tr>
            @endisset
            <tr><td class="label">Estado y Ciudad</td><td class="value">{{ $event->ciudad }}, {{ $event->estado }}</td></tr>
            <tr><td class="label">Lugar</td><td class="value">{{ $event->escenario }}</td></tr>
            <tr><td class="label">Dirección</td><td class="value">{{ $event->direccion }}</td></tr>
            @if (! empty($period))<tr><td class="label">Periodo</td><td class="value">{{ $period }}</td></tr>@endif
        </table>
    @endif

    @yield('content')
</body>
</html>
