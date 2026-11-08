@use('App\Modules\BoxOffice\Support\Barcode')
@use('App\Modules\BoxOffice\Support\SeatLabel')
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Boletos — {{ $evento->evento }}</title>
    <style>
        @page { margin: 36px; }
        body { font-family: "DejaVu Sans", sans-serif; color: #16202b; font-size: 11px; margin: 0; }
        .ticket { border: 1.5px solid #16202b; border-radius: 14px; page-break-inside: avoid; margin-bottom: 28px; }
        .ticket table { width: 100%; border-collapse: collapse; }
        .head { background: #16202b; color: #fff; border-radius: 12px 12px 0 0; }
        .head td { padding: 14px 20px; }
        .brand { font-size: 13px; font-weight: bold; color: #4ecba0; }
        .kind { text-align: right; font-size: 10px; color: #c9d1d6; }
        .body td { vertical-align: top; padding: 18px 20px; }
        h1 { font-size: 19px; margin: 0 0 4px; line-height: 1.2; }
        .muted { color: #5a6672; }
        .facts td { padding: 8px 0 0; width: 33%; }
        .label { display: block; font-size: 8.5px; color: #5a6672; }
        .value { font-size: 13px; font-weight: bold; }
        .stub { border-left: 2px dashed #dce2df; width: 190px; text-align: center; }
        .stub img { width: 150px; height: 150px; }
        .price td { padding: 10px 20px 16px; border-top: 1px solid #dce2df; }
        .courtesy { color: #176f53; font-weight: bold; }
        .code { font-size: 8.5px; color: #5a6672; word-break: break-all; }
        .notice { font-size: 9px; color: #5a6672; margin-top: 6px; }
    </style>
</head>
<body>
    @foreach ($boletos as $boleto)
        <div class="ticket">
            <table class="head">
                <tr>
                    <td class="brand">DeBoleto</td>
                    <td class="kind">Boleto digital</td>
                </tr>
            </table>
            <table class="body">
                <tr>
                    <td>
                        <h1>{{ $evento->evento }}</h1>
                        <p class="muted" style="margin: 0">{{ $evento->escenario }}, {{ $evento->ciudad }}</p>

                        <table class="facts">
                            <tr>
                                <td><span class="label">Fecha</span><span class="value">{{ $funcion->fecha_natural }}</span></td>
                                <td><span class="label">Hora</span><span class="value">{{ $funcion->hora_natural }} h</span></td>
                                <td><span class="label">Zona</span><span class="value">{{ $boleto['localidad'] }}</span></td>
                            </tr>
                            @php($seat = SeatLabel::parts($boleto['asiento']))
                            <tr>
                                @forelse ($seat as $part)
                                    <td><span class="label">{{ $part['label'] }}</span><span class="value">{{ $part['value'] }}</span></td>
                                @empty
                                    <td colspan="2"><span class="label">Acceso</span><span class="value">Admisión general</span></td>
                                @endforelse
                                <td><span class="label">Folio de compra</span><span class="value">{{ $boleto['pk_evento_compra'] }}</span></td>
                            </tr>
                            @if ($boleto['localidad_text'])
                                <tr>
                                    <td colspan="3"><span class="label">Tipo de boleto</span><span class="value">{{ $boleto['localidad_text'] }}</span></td>
                                </tr>
                            @endif
                        </table>
                    </td>
                    <td class="stub">
                        <img src="{{ Barcode::qrDataUri((string) $boleto['pk_evento_funcion_boleto'], 300) }}" alt="Código QR del boleto">
                        <p class="code">{{ $boleto['codigo'] }}</p>
                    </td>
                </tr>
            </table>
            <table class="price">
                <tr>
                    <td>
                        @if ($boleto['cortesia'])
                            <span class="courtesy">Boleto de cortesía</span>
                        @else
                            Precio ${{ $boleto['precio'] }} + cargo por servicio ${{ $boleto['comision'] }} = <strong>${{ $boleto['total'] }}</strong>
                        @endif
                        <p class="notice">Presenta este código en el acceso. Cada código es válido para una sola entrada.</p>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
