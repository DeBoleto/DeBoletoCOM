@use('App\Modules\BoxOffice\Support\Barcode')
@use('App\Modules\BoxOffice\Support\SeatLabel')
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
            height: 136mm; /* 152.4 = 6*/
            width: 50mm; /* 88..9 = 3.5*/
            padding-left: 2mm;
            padding-right: 1mm;
            text-align: center;
            padding-top: 14mm;
            page-break-before  : always;
            /*border: 1px dashed  #34495e ;*/
        }

        section.ticket .headers {
            height: 42mm;
            width: 100%;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            justify-content: center;
            /*border: 1px solid rgb(116, 65, 212);*/
        }

        section.ticket .details {
            height: 68mm;
            width: 100%;
            font-size: 0.75em;
            font-family : 'Arial';
            overflow: hidden;
            display: flex;
            justify-content: center;
            /*border: 1px solid rgb(116, 65, 212);*/
        }

        section.ticket .resume{
            margin-top: 10px;
            height: 28mm; /* 38.1 = 1.5 */
            width: 100%;
            font-family : 'Arial';
            font-size: 0.7em;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            /*border: 1px solid rgb(212, 65, 65);*/
        }

        section.ticket .headers .pat{
            width: 100%;
            margin-top: 2mm;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        section.ticket .details .pat{
            width: 100%;
            margin-top: 2mm;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        section.ticket .headers .boleto{
            width: 45mm;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        .title1 {
            font-size: 1.94em;
            font-weight: bold;
        }

        .subtitle1 {
            font-size: 9.47px;
            font-weight: bold;
        }

        .subtitle2 {
            font-size: 7.87px;
            font-weight: bold;
        }

        .folio{
            font-size: 8.6px;
        }

        .cortesia{
            font-size: 7.91px;
        }

        .cortesia-text{
            font-size: 8.17px;
            padding-top: 14px;
        }

        .costos{
            font-size: 8.6px;
        }


        .asiento{
            font-size: 8.6px;
        }

        .resumen-text{
            font-size: 5.38px;
        }

        .title2 {
            font-size: 6.5px;
            font-weight: bold;
        }

        .title3 {
            margin-top: 24px;
            font-size: 14px;
            font-weight: bold;
        }

        .subtitle {
            font-size: 0.65em;
            font-weight: bold;
            text-align: left;
        }
        .subtitle22 {
            font-size: 0.65em;
            font-weight: bold;
        }
    </style>
</head>
<body>

<section id="printable">
    {{-- {{dd($boletos)}} --}}
    @for($i = 0; $i < sizeof($boletos); $i++)
        <section class="ticket">
            <section class="headers">
                <div class="text">
                    <img class="boleto" src="{{ config('boxoffice.event_images_url') }}{{ $evento->imagen_boleto }}" style="margin-top: 6mm;"/>
                    <div>
                        <span class="subtitle1">{{$evento->titulo_boleto}}</span><br/>
                        <span class="subtitle1">{{$evento->escenario}}</span><br />
                        <span class="subtitle2">
                            <strong>{{SpanishDate::long($funcion->fecha)}} {{ $funcion->hora_natural }}  Hrs.</strong>
                        </span>
                    </div>
                </div>
            </section>
            <section class="details">
                <div class="text">
                    <div>
                        <table borders width="100%" style="padding-top: 25px">
                            <tr>
                                <td class="costos" style="width: 25%"><strong>SECCIÓN</strong></td>
                                <td class="costos" style="width: 25%"><strong>FILA</strong></td>
                                <td class="costos" style="width: 25%"><strong>ASIENTO</strong></td>
                            </tr>
                            <tr>

                                <td class="folio" style="width: 25%; text-align: center"><strong>{{$boletos[$i]['localidad']}}</strong></td>
                                @if($evento->pk_evento === 963)
                                    <td class="asiento" style="width: 25%; text-align: center"></td>
                                    <td class="asiento" style="width: 25%; text-align: center"></td>
                                @else
                                    <td class="asiento" style="width: 25%; text-align: center"><strong>@if(str_contains($boletos[$i]['asiento'], "-")) {{ explode("-", $boletos[$i]['asiento'])[0] }} @else {{ SeatLabel::row($boletos[$i]['asiento']) }} @endif</strong></td>
                                    <td class="asiento" style="width: 25%; text-align: center"><strong>@if(str_contains($boletos[$i]['asiento'], "-")) {{ explode("-", $boletos[$i]['asiento'])[1] }} @else {{ SeatLabel::number($boletos[$i]['asiento']) }} @endif</strong></td>
                                @endif
                            </tr>
                            @if(isset($boletos[$i]['localidad_text']))
                            <tr>
                                <td colspan="3" class="subtitle1" style="text-align: center">
                                    <strong>{{$boletos[$i]['localidad_text']}}</strong>
                                </td>
                            </tr>
                            @endif
                        </table>
                        @if($boletos[$i]['cortesia'] != 1)
                            <div style="width: 100%">
                                <table borders width="100%">
                                    @if($evento->pk_evento === 963)
                                        <tr>
                                            <td class="costos" style="padding-top: 10px; width: 30%"></td>
                                            <td class="costos" style="padding-top: 10px; width: 30%"></td>
                                            <td class="costos" style="padding-top: 10px; width: 40%"></td>
                                        </tr>
                                        <tr style="padding-top: 15px">
                                            <td class="costos"></td>
                                            <td class="costos"></td>
                                            <td class="costos"></td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td class="costos" style="padding-top: 10px; width: 30%">Costo</td>
                                            <td class="costos" style="padding-top: 10px; width: 30%">CXS</td>
                                            <td class="costos" style="padding-top: 10px; width: 40%">Total</td>
                                        </tr>
                                        <tr style="padding-top: 15px">
                                            <td class="costos">$ {{$boletos[$i]['precio']}}</td>
                                            <td class="costos">$ {{$boletos[$i]['comision']}}</td>
                                            <td class="costos">$ {{ number_format(($boletos[$i]['precio'] + $boletos[$i]['comision']), 2) }}</td>
                                        </tr>
                                    @endif
                                    <tr style="padding-top: 15px">
                                        @if($evento->pk_evento === 864)
                                            <span class="title3" style="font-size: 12px;">SIN BENEFICIOS*</span><br/>
                                        @endif
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="folio" style="text-align: center"><strong>FOLIO</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="folio" style="text-align: center"><strong>{{ $boletos[$i]['pk_evento_funcion_boleto'] }}</strong></td>
                                    </tr>
                                </table>
                            </div>

                        @else

                            <div style="width: 100%">
                                <table borders width="100%">
                                    <tr style="padding-top: 15px">
                                        <td colspan="3" class="cortesia-text">
                                            <span class="title3" style="font-size: 12px">BOLETO DE CORTESIA</span><br/>
                                            @if($evento->pk_evento === 864)
                                                <span class="title3">NO APLICAN BENEFICIOS*</span><br/>
                                            @else
                                                <span class="title3" style="font-size: 12px">PROHIBIDA SU VENTA</span><br/>
                                            @endif
                                        </td>
                                    </tr>

                                    @if($boletos[$i]['precio'] > 0)
                                        <tr style="padding-top: 15px">
                                            <td class="costos">$ {{$boletos[$i]['precio']}}</td>
                                            <td class="costos">$ 0.00</td>
                                            <td class="costos">$ {{ number_format(($boletos[$i]['precio'] + $boletos[$i]['comision']), 2) }}</td>
                                        </tr>
                                    @endif

                                    <tr>
                                        <td colspan="3" class="folio" style="text-align: center"><strong>FOLIO</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="folio" style="text-align: center"><strong>{{ $boletos[$i]['pk_evento_funcion_boleto'] }}</strong></td>
                                    </tr>
                                </table>
                            </div>

                        @endif

                        <div style="width: 100%; text-align: center">
                            <img width="39%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" style="padding-top: 15px"/>
                        </div>
                        <span class="cortesia-text" style="text-align:center">
                            Este Boleto es de uso único no está sujeto a cambio, devolución, cancelación o reposición por robo y extravió
                        </span>
                    </div>
                </div>
            </section>

            <section class="resume">
                <div style="width: 100%">
                    <div style="width: 100%;margin-top: -25px;">
                        <p class="title2" style="white-space: pre-line;">
                            {{$evento->titulo_boleto}}
                            {{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.
                            {{$evento->escenario}}
                        </p>
                    </div>
                    <table class="subtitle" bordfer style="width: 100%;margin-top: 7px;">
                        <tr>
                            <td style="width: 40%; text-align: center">{{$boletos[$i]['localidad']}}</td>
                            @if($evento->pk_evento === 963)
                                <td class="asiento" style="width: 30%; text-align: center"></td>
                                <td class="asiento" style="width: 30%; text-align: center"></td>
                            @else
                                <td style="width: 30%; text-align: center">@if(str_contains($boletos[$i]['asiento'], "-")) {{ explode("-", $boletos[$i]['asiento'])[0] }} @else {{ SeatLabel::row($boletos[$i]['asiento']) }} @endif</td>
                                <td style="width: 30%; text-align: center">@if(str_contains($boletos[$i]['asiento'], "-")) {{ explode("-", $boletos[$i]['asiento'])[1] }} @else {{ SeatLabel::number($boletos[$i]['asiento']) }} @endif</td>
                            @endif

                        </tr>
                        @if($boletos[$i]['cortesia'] != 1)

                            @if($evento->pk_evento === 963)
                                <tr>
                                    <td style="width: 20%; text-align: center">

                                    </td>
                                    <td style="width: 20%; text-align: center">
                                    </td>
                                    <td style="width: 20%; text-align: center">
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td style="width: 20%; text-align: center">
                                        Costo <br>
                                        $ {{$boletos[$i]['precio']}}
                                    </td>
                                    <td style="width: 20%; text-align: center">
                                        CXS <br>
                                        $ {{$boletos[$i]['comision']}}
                                    </td>
                                    <td style="width: 20%; text-align: center">
                                        Total <br>
                                        $ {{$boletos[$i]['precio'] + $boletos[$i]['comision']}}
                                    </td>
                                </tr>
                            @endif

                        @else
                            <tr>
                                <td colspan="3">
                                    <span class="title3">BOLETO DE CORTESIA</span><br/>
                                    @if($evento->pk_evento === 864)
                                        <span class="title3">NO APLICAN BENEFICIOS*</span>
                                    @else
                                        <span class="title3">PROHIBIDA SU VENTA</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </table>
                    <br>

                </div>
            </section>
        </section>
    @endfor
</section>

<script>
    var printContents = document.getElementById('printable').innerHTML;
    var originalContents = document.body.innerHTML;

    // document.body.innerHTML = printContents;

    window.print();

    // document.body.innerHTML = originalContents;
</script>
</body>
</html>
