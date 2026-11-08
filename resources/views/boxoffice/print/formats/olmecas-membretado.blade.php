@use('App\Modules\BoxOffice\Support\Barcode')
@use('App\Modules\BoxOffice\Support\SpanishDate')
<!DOCTYPE html>
<html>
<head>
    <style>
        @font-face {
            font-family: "Codebar";
            src: url('{{ asset('fonts/code128.ttf') }}');
        }

        @media print {
            @page { margin: 0; }
            body { margin: 2cm; }
        }

        body {
            margin          : 0;
            padding         : 0;
        }

        section.ticket {
            position: relative;
            height: 150mm; /* 152.4 = 6*/
            width: 78mm; /* 88..9 = 3.5*/
            padding-left: 2mm;
            padding-right: 1mm;
            text-align: center;
            /*page-break-before  : always;*/
            break-after: page;
            /*border: 1px dashed  #34495e ;*/
        }

        section.ticket .image {
            position: absolute;
            top: 13mm;
            /*top: 18mm;*/
            height: 26mm;
            width: 75mm;
            /*font-size: 0.75em;
            font-family : 'Arial';*/
            overflow: hidden;
            /*display: flex;
            flex-direction: column;
            justify-content: center;
            border: 1px solid rgb(116, 65, 212);*/
        }

        section.ticket .image img {
            height: 100%;
        }

        section.ticket .title {
            position: absolute;
            top: 40mm;
            height: 20mm;
            width: 75mm;
            font-size: 0.68em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            /*border: 1px solid rgb(116, 65, 212);*/
        }

        section.ticket .ticket-seat {
            position: absolute;
            top: 68mm;
            /* top: 75mm; */
            height: 10mm;
            width: 75mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
        }

        section.ticket .ticket-seat .folio {
            width: 20mm;
        }

        section.ticket .optional-text {
            position: absolute;
            top: 60mm;
            font-size: 0.7em;
            font-family : 'Arial';
            display: flex;
            flex-direction: column;
            align-content: center;
            width: 75mm;
            height: 7mm;
        }

        section.ticket .ticket-seat .location {
            width: 24mm;
        }

        section.ticket .ticket-seat .row {
           width: 10mm;
        }

        section.ticket .ticket-seat .seat {
            width: 14mm;
            margin-left: 6mm;
        }

        section.ticket .ticket-price {
            position: absolute;
            top: 79mm;
            height: 10mm;
            width: 75mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
        }

        section.ticket .ticket-price .price {
            width: 25mm;
            display: flex;
            flex-direction: column;
        }

        section.ticket .ticket-price .service {
            width: 25mm;
            display: flex;
            flex-direction: column;
        }

        section.ticket .ticket-price .total {
            width: 25mm;
            display: flex;
            flex-direction: column;
        }

        section.ticket .code-qr {
            position: absolute;
            top: 89mm;
            height: 27mm;
            width: 75mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        section.ticket .cortesy {
            position: absolute;
            top: 107mm;
            height: 10mm;
            width: 30mm;
            font-size: 0.63em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        section.ticket .resume {
            position: absolute;
            top: 126mm;
            /*top: 133mm; */
            height: 10mm;
            width: 50mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
        }

        section.ticket .resume .location {
            width: 18mm;
        }

        section.ticket .resume .row {
            width: 10mm;
        }

        section.ticket .resume .seat {
            width: 17mm;
        }

        section.ticket .resume-qr {
            position: absolute;
            top: 120mm;
            /*top: 126mm; */
            left: 55mm;
            height: 16mm;
            width: 20mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            /*border: 1px solid rgb(116, 65, 212);*/
        }

        section.ticket .resume-price {
            position: absolute;
            top: 134mm;
            /*top: 140mm;*/
            height: 10mm;
            width: 75mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
        }

        section.ticket .resume-price .price {
            width: 23mm;
            display: flex;
            flex-direction: column;
        }

        section.ticket .resume-price .service {
            width: 23mm;
            display: flex;
            flex-direction: column;
        }

        section.ticket .resume-price .total {
            width: 23mm;
            display: flex;
            flex-direction: column;
        }

        section.ticket .resume-optional-text {
            position: absolute;
            top: 150mm;
            /*top: 154mm; */
            font-size: 0.7em;
            font-family : 'Arial';
            justify-content: center;
            overflow: hidden;
            width: 75mm;
            height: 5mm;
        }

        section.ticket .resume-event {
            position: absolute;
            /* top: 145mm;*/
            top: 144mm;
            height: 6mm;
            width: 72mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: row;
            justify-content: center;
        }

        section.ticket .resume-event2 {
            position: absolute;
            top: 148mm;
            /*top: 150mm;*/
            width: 72mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: row;
            justify-content: center;
        }

        section.ticket .resume-cortesy {
            position: absolute;
            top: 131mm;
            left: 61mm;
            height: 7mm;
            width: 28mm;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            -webkit-transform: rotate(-90deg);
            -moz-transform: rotate(-90deg);
        }

        .title1 {
            font-size: 1.84em;
            font-weight: bold;
        }

        .subtitle1 {
            font-size: 1.34em;
            font-weight: bold;
        }

        .subtitle2 {
            font-size: 1em;
            font-weight: bold;
        }

        .title2 {
            font-size: 1.3em;
            font-weight: bold;
        }

        .title3 {
            font-size: 1em;
            font-weight: bold;
        }

        .subtitle {
            font-size: 0.8em;
            font-weight: bold;
        }
        .subtitle22 {
            font-size: 0.65em;
            font-weight: bold;
        }

        section.ticket .mark {
            position: absolute;
            font-size: 0.6em;
            top: 48mm;
            left: 20mm;
        }
    </style>
</head>
<body>

    <section id="printable">
        {{-- {{dd($boletos)}} --}}
        @for($i = 0; $i < sizeof($boletos); $i++)
            <section class="ticket">
                <div class="image">
                    <img class="boleto" src="{{ config('boxoffice.event_images_url') }}{{ $evento->imagen_boleto }}" />
                </div>
                <div class="title">
                    @if(in_array($evento->pk_evento, [641, 652]))
                        <span class="subtitle1">{{$evento->titulo_boleto}} {{$evento->subtitulo_boleto}}</span>
                    @else
                        <span class="title1">{{$evento->titulo_boleto}} {{$evento->subtitulo_boleto}}</span>
                    @endif
                    <span class="subtitle1">{{$evento->escenario}}</span>
                    <span class="subtitle2">
                        <strong>{{SpanishDate::long($funcion->fecha)}} - {{ $funcion->hora_natural }} Hrs.</strong>
                    </span>
                </div>

                <div class="optional-text">
                    @if(in_array($evento->pk_evento, config('boxoffice.print.legend_events')))
                        <span>HORARIO ABIERTO </span>
                        <span>16:00 ó 18:00 hrs</span>
                    @endif

                    @if(in_array($evento->pk_evento, [505]))
                        <span>V&aacute;lido en la compra de 1 six en la entrada</span>
                    @endif
                </div>

                <div class="ticket-seat">
                    <div class="folio">
                        {{ $boletos[$i]['pk_evento_funcion_boleto'] }}
                    </div>

                    <div class="location">
                        {{$boletos[$i]['localidad']}}
                    </div>

                    <div class="row">
                        {{strpos($boletos[$i]['asiento'], 'NA_NA') === false ? substr($boletos[$i]['asiento'], 0, 1) : 'N/A'}}
                    </div>

                    <div class="seat">
                        {{strpos($boletos[$i]['asiento'], 'NA_NA') === false ? substr($boletos[$i]['asiento'], 1, 4) : 'N/A'}}
                    </div>
                </div>

                @if(1 === $boletos[$i]['cortesia'])
                    <div class="ticket-price">
                        <div class="price">
                            <span class="subtitle">COSTO</span>
                            <span class="subtitle">$ 0.00</span>
                        </div>

                        <div class="service">
                            <span class="subtitle">CXS</span>
                            <span class="subtitle">$ 0.00</span>
                        </div>

                        <div class="total">
                            <span class="subtitle">TOTAL</span>
                            <span class="subtitle">$ 0.00</span>
                        </div>
                    </div>
                @else
                    <div class="ticket-price">
                        <div class="price">
                            <span class="subtitle">COSTO</span>
                            <span class="subtitle">$ {{$boletos[$i]['precio']}}</span>
                        </div>

                        <div class="service">
                            <span class="subtitle">CXS</span>
                            <span class="subtitle">$ {{$boletos[$i]['comision']}}</span>
                        </div>

                        <div class="total">
                            <span class="subtitle">TOTAL</span>
                            <span class="subtitle">$ {{ number_format(($boletos[$i]['precio'] + $boletos[$i]['comision']), 2) }}</span>
                        </div>
                    </div>
                @endif

                <div class="code-qr" style="margin-left: 8mm">
                    <table bordesr style="width: 70mm">
                        <tr>
                            <!--td colspan="2" style="width: 65%; padding: 0mm 1mm 0mm 4mm">
                            </td-->
                            <td style="width: 30%; padding: 0mm 1mm 0mm 1mm"></td>
                            <td style="padding: 1mm 4mm 1mm 4mm">
                                <img width="99%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" />
                            </td>

                            <td style="width: 30%; padding: 0mm 1mm 0mm 1mm"></td>
                        </tr>

                    </table>
                    @if(0 === $boletos[$i]['cortesia'])
                        <span>{{$boletos[$i]['codigo']}}</span>
                    @endif
                </div>

                @if(1 === $boletos[$i]['cortesia'])
                    <div class="cortesy">
                        <span class="subtitle">BOLETO DE CORTESIA</span>
                        <span class="subtitle">PROHIBIDA SU VENTA</span>
                    </div>
                @endif

                <div class="resume">
                    <div class="location">
                        {{$boletos[$i]['localidad']}}
                    </div>

                    <div class="row">
                        {{strpos($boletos[$i]['asiento'], 'NA_NA') === false ? substr($boletos[$i]['asiento'], 0, 1) : 'N/A'}}
                    </div>

                    <div class="seat">
                        {{strpos($boletos[$i]['asiento'], 'NA_NA') === false ? substr($boletos[$i]['asiento'], 1, 4) : 'N/A'}}
                    </div>
                </div>

                <div class="resume-qr">
                    <table bordedr>
                        <tr>
                            <td style="padding: 1mm 3mm 1mm 3mm">
                                <img width="99%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" />
                            </td>

                        </tr>
                    </table>
                </div>
                @if(1 === $boletos[$i]['cortesia'])
                    <div class="resume-price">
                        <div class="price">
                            <span class="subtitle">COSTO</span>
                            <span class="subtitle">$ 0.00</span>
                        </div>

                        <div class="service">
                            <span class="subtitle">CXS</span>
                            <span class="subtitle">$ 0.00</span>
                        </div>

                        <div class="total">
                            <span class="subtitle">TOTAL</span>
                            <span class="subtitle">$ 0.00</span>
                        </div>
                    </div>
                @else
                    <div class="resume-price">
                        <div class="price">
                            <span class="subtitle">COSTO</span>
                            <span class="subtitle">$ {{$boletos[$i]['precio']}}</span>
                        </div>

                        <div class="service">
                            <span class="subtitle">CXS</span>
                            <span class="subtitle">$ {{$boletos[$i]['comision']}}</span>
                        </div>

                        <div class="total">
                            <span class="subtitle">TOTAL</span>
                            <span class="subtitle">$ {{ number_format(($boletos[$i]['precio'] + $boletos[$i]['comision']), 2) }}</span>
                        </div>
                    </div>
                @endif

                @if(1 === $boletos[$i]['cortesia'])
                    <div class="resume-cortesy">
                        <span class="subtitle22">BOLETO DE CORTESIA</span>
                        <span class="subtitle22">PROHIBIDA SU VENTA</span>
                    </div>
                @endif

                <div class="resume-event">
                    <span class="subtitle">{{$evento->titulo_boleto}} {{$evento->subtitulo_boleto}}</span><br/>
                </div>
                <div class="resume-event2">
                    <span class="subtitle22">{{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs. // {{$evento->escenario}}</span>
                </div>
            </section>
        @endfor
    </section>

    <script>
        var printContents = document.getElementById('printable').innerHTML;
        var originalContents = document.body.innerHTML;

        document.body.innerHTML = printContents;

        window.print();

        document.body.innerHTML = originalContents;
    </script>
</body>
</html>
