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
            height: 135mm; /* 152.4 = 6*/
            width: 78mm; /* 88..9 = 3.5*/
            padding-left: 2mm;
            padding-right: 1mm;
            text-align: center;
            padding-top: 15mm;
            break-after: always;
            /*border: 1px dashed  #34495e ;*/
        }

        section.ticket .details {
            height: 105mm;
            width: 100%;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            justify-content: center;
            /*border: 1px solid rgb(116, 65, 212);*/
        }

        section.ticket .resume{
            height: 31mm; /* 38.1 = 1.5 */
            width: 100%;
            font-family : 'Arial';
            font-size: 0.9em;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            /*border: 1px solid rgb(212, 65, 65);*/
        }

        section.ticket .details .pat{
            width: 100%;
            margin-top: 2mm;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        section.ticket .details .boleto{
            width: 77mm;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        .title1 {
            font-size: 1.94em;
            font-weight: bold;
        }

        .subtitle1 {
            font-size: 1.34em;
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
        {{-- {{dd($boletos)}} --}}
        @for($i = 0; $i < sizeof($boletos); $i++)
            <section class="ticket">
                <section class="details">
                    <div class="text">
                        <img class="boleto" src="{{ config('boxoffice.event_images_url') }}{{ $evento->imagen_boleto }}" />
                        <br><br>
                        <div>
                            <span class="title1">{{$evento->titulo_boleto}} {{$evento->subtitulo_boleto}}</span><br/>
                            <span class="subtitle1">{{$evento->escenario}}</span><br />
                            <span class="subtitle1">
                                <strong>{{SpanishDate::long($funcion->fecha)}}</strong><br/>
                                <strong>{{ $funcion->hora_natural }}  Hrs.</strong>
                            </span><br><br>

                            <table class="title3" borders width="100%">
                                <tr>
                                    <td>Folio</td>
                                    <td>Zona</td>
                                    <td>Asiento</td>
                                </tr>
                                <tr>
                                    <td>{{ $boletos[$i]['pk_evento_funcion_boleto'] }}</td>
                                    <td>{{$boletos[$i]['localidad']}}</td>

                                <td>{{strpos($boletos[$i]['asiento'], 'NA_NA') === false ? $boletos[$i]['asiento'] : ''}}</td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 10px">Costo</td>
                                    <td style="padding-top: 10px">CXS</td>
                                    <td style="padding-top: 10px">Total</td>
                                </tr>
                                <tr>
                                    @if($boletos[$i]['cortesia'] != 1)
                                        <td>$ {{$boletos[$i]['precio']}}</td>
                                        <td>$ {{$boletos[$i]['comision']}}</td>
                                        <td>$ {{ number_format(($boletos[$i]['precio'] + $boletos[$i]['comision']), 2) }}</td>
                                    @else
                                        <td>$ 0.00</td>
                                        <td>$ 0.00</td>
                                        <td>$ 0.00</td>
                                    @endif
                                </tr>

                            </table>

                            <table bordesr style="width: 78mm">
                                <tr>
                                    <td colspan="2" style="width: 65%; padding: 0mm 1mm 0mm 4mm">
                                        <img class="pat" src="{{ config('boxoffice.event_images_url') }}patrocinador.png" />
                                    </td>
                                    <td style="padding: 2mm 6mm 0mm 6mm">
                                        <img width="100%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" />
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="resume">
                    <table class="subtitle" bordfer style="width: 78mm">
                        <tr>
                            <td>Zona</td>
                            <td>Asiento</td>
                            <td>Precio</td>
                            <td>CXS</td>
                        </tr>
                        <tr>
                            <td>{{$boletos[$i]['localidad']}}</td>
                            <td>{{strpos($boletos[$i]['asiento'], 'NA_NA') === false ? $boletos[$i]['asiento'] : ''}}</td>
                            @if($boletos[$i]['cortesia'] != 1)
                                <td>$ {{$boletos[$i]['precio']}}</td>
                                <td>$ {{$boletos[$i]['comision']}}</td>
                            @else
                                <td>$ 0.00</td>
                                <td>$ 0.00</td>
                            @endif
                        </tr>
                        <tr>
                            <td style="padding-top: 2mm">
                                <img width="40%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" />
                            </td>
                            <td colspan="3" style="padding-top: 1mm;">
                                <span class="title2">{{$evento->titulo_boleto}} {{$evento->subtitulo_boleto}}</span><br/>
                                <span>{{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.</span><br />
                                <span>{{$evento->escenario}}</span><br>
                                <!--img height="18mm" style="margin-top: 2mm; bordedr: 1px solid #34495e" src="{{ asset('images/logo.svg') }}" /-->
                            </td>
                        </tr>
                    </table>
                </section>
            </section>
        @endfor
    </section>

    <script>
        var printContents = document.getElementById('printable').innerHTML;
        var originalContents = document.body.innerHTML;

        // document.body.innerHTML = printContents;

        // window.print();

        // document.body.innerHTML = originalContents;
    </script>
</body>
</html>
