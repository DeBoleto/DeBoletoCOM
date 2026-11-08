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
            body {
                margin: 2cm;
                transform: scale(0.88); /* Escala el contenido al 88% */
            }
        }

        body {
            margin          : 0;
            padding         : 0;
            transform: scale(0.88); /* Escala el contenido al 88% */
        }

        section.ticket {
            position: relative;
            height: 140mm;
            width: 48mm;
            padding-left: 2mm;
            padding-right: 1mm;
            text-align: center;
            break-after: page;
            /*border: 1px dashed #000;*/
        }

        section.ticket .image-header {
            position: absolute;
            top: 0mm;
            height: 15mm;
            width: 50mm;
            overflow: hidden;
            visibility: hidden;
            /*border: 1px solid rgb(116, 65, 212); */
        }

        section.ticket .image {
            position: absolute;
            top: 18mm;
            height: 19mm;
            width: 47mm;
            overflow: hidden;
            /*border: 1px solid rgb(116, 65, 212);*/
        }

        section.ticket .image img {
            width: 100%;
        }

        section.ticket .image-header img {
            width: 100%;
        }

        section.ticket .description {
            position: absolute;
            top: 39mm;
            height: 95mm;
            width: 100%;
            font-size: 0.75em;
            font-family : 'Arial';
            /* border: 1px solid rgb(116, 65, 212); */
        }

        section.ticket .code-qr {
            position: absolute;
            top: 94mm;
            overflow: hidden;
        }

        section.ticket .code{
            height: 15mm;
            width: 100%;
            font-family: "Codebar";
            display: flex;
            align-items: center;
            justify-content: center;
            /* border: 1px solid rgb(82, 212, 65); */
        }

        section.ticket .resume {
            position: absolute;
            top: 132mm;
            height: 10mm;
            font-size: 0.6em;
            font-family : 'Arial';
            /*border: 1px solid rgb(116, 65, 112);*/
        }

        section.ticket .final{
            height: 45mm;
            width: 100%;
            font-family : 'Arial';
            font-size: 0.9em;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        section.ticket .description img{
            width: 165px;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        section.ticket .description .text {
            height: 50mm;
            display: flex;
            align-items: center;
            justify-content: center;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        section.ticket .description .text div{
            display: inline;
        }

        section.ticket .final div{
            display: inline
        }

        .title1 {
            font-size: 1.65em;
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
    </style>
</head>
<body>

    <section id="printable">
        @for($i = 0; $i < sizeof($boletos); $i++)
            <section class="ticket">
                <section class="image-header">
                    <img class="boleto" src="{{ asset('images/header_boleto_07.png') }}" />
                </section>

                <section class="image">
                    <img class="boleto" src="{{ config('boxoffice.event_images_url') }}{{ $evento->imagen_boleto}}" />
                </section>

                <section class="description">
                    <div class="text">
                        <div>
                            <span class="title1">{{$evento->titulo_boleto}}</span><br/>
                            <span class="title2">{{$evento->subtitulo_boleto}}</span><br/><br/>
                            <span class="title3">{{$evento->escenario}}</span><br />
                            <span class="title3">
                                <strong>{{SpanishDate::long($funcion->fecha)}}</strong><br/>
                                <strong>{{$funcion->hora_natural}} Hrs.</strong>
                            </span><br><br>
                            @if(in_array($boletos[$i]['pk_evento_localidad'], [6040]))
                                <span class="title1">2 x 1</span>
                                <br>
                            @endif
{{--                            <span class="title2">Zona {{$boletos[$i]['localidad']}}</span><br />--}}

{{--                            <span class="subtitle">Folio: {{$boletos[$i]['pk_evento_compra']}}</span><br/>--}}
                            <span class="subtitle"># {{$boletos[$i]['pk_evento_funcion_boleto']}}</span><br/>
                            <span class="title2">Zona {{$boletos[$i]['localidad']}}@if(isset($boletos[$i]['localidad_text'])) <br> {{$boletos[$i]['localidad_text']}}@endif</span><br />
                            <span class="title2">
                                @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                                    Fila - {{substr($boletos[$i]['asiento'], 0, 1)}} /// Asiento - {{substr($boletos[$i]['asiento'], 1)}}
                                @endif
                            </span><br>
                            @if($boletos[$i]['cortesia'] != 1)
                                <span class="subtitle">${{$boletos[$i]['precio']}} CXS ${{$boletos[$i]['comision']}} TOTAL ${{$boletos[$i]['total']}}</span>
                            @else
                                <span class="subtitle">BOLETO DE CORTESÍA</span>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="code-qr">
                    <table bordesr style="width: 45mm">
                        <tr>
                            <td style="width: 15%; padding: 0mm 1mm 0mm 1mm"></td>
                            <td style="padding: 1mm 4mm 1mm 4mm">
                                <img width="99%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" />
                            </td>
                            <td style="width: 15%; padding: 0mm 1mm 0mm 1mm"></td>
                        </tr>

                    </table>
                </section>

                <section class="resume">
                    <span class="title3">{{$evento->titulo_boleto}} <br> {{$evento->subtitulo_boleto}}</span>
                    <p style="margin-top: 4px;">
                        {{$evento->escenario}}<br />
                        {{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.
                    </p>
                    @if($boletos[$i]['cortesia'] != 1)
                        @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                            <span>Zona - <strong>{{$boletos[$i]['localidad']}}</strong></span><br>
                            <span>Asiento - <strong>{{$boletos[$i]['asiento']}}</strong></span><br>
                            <span>TOTAL: ${{$boletos[$i]['total']}}</span>
                        @else
                            <span>Zona: {{$boletos[$i]['localidad']}} // TOTAL ${{$boletos[$i]['total']}}</span>
                        @endif
                    @else
                        @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                            <p style="margin-top: -10px;">
                                Zona: {{$boletos[$i]['localidad']}}<br>, Asiento: {{$boletos[$i]['asiento']}} // BOLETO DE CORTESÍA
                            </p>
                        @else
                            <p style="margin-top: -10px;">Zona: {{$boletos[$i]['localidad']}} // BOLETO DE CORTESÍA</p>
                        @endif
                    @endif
                </section>
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
