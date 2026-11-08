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
            height: 110mm; /* 152.4 = 6*/
            width: 46mm; /* 88..9 = 3.5*/
            padding-left: 2mm;
            padding-right: 2mm;
            text-align: center;
            padding-top: 25mm;
            page-break-before  : always;
            /*border: 1px dashed  #34495e ;*/
        }

        section.ticket .headers {
            height: 37mm;
            width: 50mm;
            font-size: 0.55em;
            font-family: 'Arial';
            overflow: hidden;
            display: flex;
            justify-content: center;
            /* border: 1px solid rgb(116, 65, 212); */
        }

        section.ticket .details {
            height: 64mm;
            width: 100%;
            font-size: 0.75em;
            font-family: 'Arial';
            overflow: hidden;
            display: flex;
            justify-content: center;
            /* border: 1px solid rgb(116, 65, 212); */
        }

        section.ticket .resume{
            height: 27.5mm; /* 38.1 = 1.5 */
            width: 100%;
            font-family : 'Arial';
            font-size: .8em;
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
            width: 50mm;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        .title1 {
            font-size: 1.94em;
            font-weight: bold;
        }

        .subtitle1 {
            font-size: 1.1em;
            font-weight: bold;
            text-wrap: wrap;
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
            font-size: 0.5em;
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
                    <img class="boleto" src="{{ config('boxoffice.event_images_url') }}{{ $evento->imagen_boleto }}" />
                    <br>
                    <br>
                    <div>
                        <span class="subtitle1">{{$evento->titulo_boleto}}</span><br/>
                        <span class="subtitle1">{{$evento->escenario}}</span><br />
                        @if(true === in_array((int) $boletos[$i]['pk_evento_localidad'], config('boxoffice.print.highlight_localities')))
                            <span class="subtitle2">
                                <strong>{{SpanishDate::long($funcion->fecha)}} - {{ $funcion->hora_natural }}  Hrs.</strong>
                            </span><br>
                        @else
                            <span class="subtitle1">
                                <strong>{{SpanishDate::long($funcion->fecha)}} {{ $funcion->hora_natural }}  Hrs.</strong>
                            </span>
                        @endif
                    </div>
                </div>
            </section>
            <section class="details">
                <div class="text">
                    <div>
                        @if($boletos[$i]['cortesia'] != 1)
                            <table class="title3" borders width="100%">
                                <tr>
                                    <td style="width: 25%; text-align: center">#{{ $boletos[$i]['pk_evento_funcion_boleto'] }}</td>
                                </tr>
                                <tr>
                                    <td style="width: 50%; text-align: center">{{$boletos[$i]['localidad']}}</td>
                                </tr>
                                <tr>
                                    <td style="width: 25%; text-align: center">{{$boletos[$i]['asiento']}}</td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 10px">Costo: $ {{$boletos[$i]['precio']}}</td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 10px">CXS: $ {{$boletos[$i]['comision']}}</td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 10px">Total: $ {{ number_format(($boletos[$i]['precio'] + $boletos[$i]['comision']), 2) }}</td>
                                </tr>
                            </table>
                        @else
                            <table class="title3" borders width="100%">
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td style="color: white"></td>
                                </tr>
                                <br>
                                <tr>
                                    <td style="text-align: center">{{ $boletos[$i]['pk_evento_funcion_boleto'] }}</td>
                                    <td>{{$boletos[$i]['localidad']}}</td>
                                    <td>{{$boletos[$i]['asiento']}}</td>
                                    <td style="color: white"></td>
                                </tr>
                            </table>
                            <span class="title3">BOLETO DE CORTESÍA</span><br/>
                            @if($evento->pk_evento === 864)
                                <span class="title3">NO APLICAN BENEFICIOS*</span><br/>
                            @else
                                <span class="title3">PROHIBIDA SU VENTA</span><br/>
                            @endif
                        @endif
                        <table bordesr style="width: 46mm">
                            <tr>
                                <td style="padding: 1mm 6mm 1mm 6mm">
                                    <img width="60%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" />
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </section>

            <section class="resume">
                <div style="width: 100%">
                    <table class="subtitle" bordfer style="width: 100%;margin-top: 3px;">
                        <tr>
                            <td style="width: 33.3%; text-align: left"></td>
                            <td style="width: 33.3%; text-align: left"></td>
                            <td style="width: 33.3%; text-align: left"></td>
                        </tr>
                        <tr>
                            <td colspan="2"  style="width: 66.6%; text-align: center">Zona</td>
                            <td style="width: 33.3%; text-align: center">Asiento</td>
                        </tr>
                        @if($boletos[$i]['cortesia'] != 1)
                            <tr>
                                <td colspan="2" style="width: 66.6%; text-align: center">{{$boletos[$i]['localidad']}}</td>
                                <td style="width: 33.3%; text-align: center">{{$boletos[$i]['asiento']}}</td>
                            </tr>
                            <tr>
                                <td style="width: 33.3%; text-align: center">Costo</td>
                                <td style="width: 33.3%; text-align: center">CXS</td>
                                <td style="width: 33.3%; text-align: center">Total</td>
                            </tr>
                            <tr>
                                <td style="width: 33.3%; text-align: center">$ {{$boletos[$i]['precio']}}</td>
                                <td style="width: 33.3%; text-align: center">$ {{$boletos[$i]['comision']}}</td>
                                <td style="width: 33.3%; text-align: center">$ {{$boletos[$i]['precio'] + $boletos[$i]['comision']}}</td>
                            </tr>
                        @else


                            <tr>
                                <td style="width: 33.3%; text-align: center">Mesa</td>
                                <td style="width: 33.3%; text-align: center">Silla</td>
                                <td style="width: 33.3%; text-align: center">BOLETO DE CORTESÍA</td>
                            </tr>
                            <tr>
                                <td  style="width: 33.3%; text-align: center">{{$boletos[$i]['localidad']}}</td>
                                <td style="width: 33.3%; text-align: center">{{$boletos[$i]['asiento']}}</td>
                                <td style="width: 33.3%; text-align: center">PROHIBIDA SU VENTA</td>
                            </tr>

                        @endif
                        <tr>
                            <td colspan="3" style="padding-top: 2mm; text-align: center">
                                <span class="title2" style="font-size: 1em;">{{$evento->titulo_boleto}}</span><br/>
                                <span>{{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.</span><br />
                                <span>{{$evento->escenario}}</span><br>
                                <!--img height="18mm" style="margin-top: 2mm; bordedr: 1px solid #34495e" src="{{ asset('images/logo.svg') }}" /-->
                            </td>
                        </tr>
                    </table>
                </div>

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
