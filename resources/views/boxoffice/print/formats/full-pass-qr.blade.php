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

        .codebar {
            font-family: "Codebar";
        }


        .contenido-vertical {
            transform: rotate(-270deg);
            -webkit-transform: rotate(-270deg);
            -moz-transform: rotate(-270deg);
            -o-transform: rotate(-270deg);
        }


        @media print {
            @page { margin: 0; }
            body { margin: 2cm; }
        }

        body {
            margin          : 0;
            padding         : 0;
        }

        section.clearArea1 {
            width                   : 100%;
            height                  : 10mm;
            page-break-before       : always;
        }

        section.main {
            width                   : 100%;
            height                  : 85mm;
            overflow                : hidden;
            size                    : 8mm;
            text-align              : center;
            padding-top             : 5mm;
            /*padding-left            : 20mm;*/
            page-break-before       : always;
            /*margin-top              : -30px;*/
            margin-left             : -30px;
        }

        section.codeBar {
            width                   : 100%;
            height                  : 20mm;
            /*overflow                : hidden;
            margin-top              : 0px;
            margin-left             : -39px;*/
            display: flex;
            align-items: center;
            justify-content: center;
        }

        section.codeBar .codeArea {
                        /* border: 1px solid rgb(82, 212, 65); */
            /*margin-top              : 6mm;
            margin-left             : 8mm;*/
            width                   : 70mm;
            /*height                  : 9mm;*/
            overflow                : hidden;
            text-align              : center;
            margin-right: 70px;
            /*font-family             : "Codebar";
            font-size               : 20mm;*/
            /*padding-top             : 4mm;*/
        }

        section.white {
            width                   : 100%;
            height                  : 29mm;
            overflow                : hidden;
            text-align              : center;
            page-break-after       : always;
            size                    : 6mm;
            text-align              : center;
            margin-top              : 50px;
            margin-left:            -30px;
        }

        section.white h6,
        section.white h5 {
            margin-top              : 1mm;
            margin-bottom           : 1mm;
            font-family : 'Arial';
        }

        section.white p {
            margin                  : 2mm;
            font-family : 'Arial';
        }

        .title {
            font-size   : 2.3em;
            font-family : 'Arial';
            font-weight: bold;
        }

        .title2 {
            font-family : 'Arial';
            font-size: 1.7em;
            font-weight: bold;
        }

        .title3 {
            font-family : 'Arial';
            font-size: 0.9em;
            font-weight: bold;
        }

        .asiento {
            font-size: 1.3em;
            font-family: "Arial";
            font-weight: bold;
        }

        .precios {
            font-size: 0.8em;
            font-family: "Arial";
            font-weight: bold;
        }

        .zona {
            font-size: 1.2em;
            font-family: "Arial";
            font-weight: bold;
        }

        .container {
            /*width: 609px;
            height: 311px;*/
        }

    </style>
</head>
<body>

    <section id="printable">
        @for($i = 0; $i < sizeof($boletos); $i++)
        <section class="container">

            <section class="main">
                {{--<span class="title">{{$evento->titulo_boleto}}</span><br />
                <span class="title2">{{$evento->subtitulo_boleto}}</span><br />--}}
                <img width="300px;" src="{{ config('boxoffice.event_images_url') }}{{$evento->imagen_boleto}}" /><br />
                <span class="title3">{{$evento->escenario}}</span><br />
                <span><strong>{{SpanishDate::long($funcion->fecha)}}</strong> <strong>{{$funcion->hora_natural}} Hrs.</strong></span><br />
                {{--<span><strong>{{$funcion->hora_natural}} Hrs.</strong></span><br />--}}
                {{--  <span>{{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.</span><br />  --}}
                <span class="zona">Zona {{$boletos[$i]['localidad']}}</span><br />
                <span class="asiento">
                @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                    @if (false !== isset($boletos[$i]['mesas']) && true === $boletos[$i]['mesas'])
                        Mesa - {{ substr($boletos[$i]['asiento'], 0, stripos($boletos[$i]['asiento'], '-')) }} /// Asiento - {{ substr($boletos[$i]['asiento'], stripos($boletos[$i]['asiento'], '-') + 1) }}
                    @else
                        @if(false !== strpos($boletos[$i]['asiento'], '-'))
                            Mesa - {{ substr($boletos[$i]['asiento'], 0, stripos($boletos[$i]['asiento'], '-')) }} /// Asiento - {{ substr($boletos[$i]['asiento'], stripos($boletos[$i]['asiento'], '-') + 1) }}
                        @else
                            Fila - {{substr($boletos[$i]['asiento'], 0, 1)}} /// Asiento - {{substr($boletos[$i]['asiento'], 1)}}
                        @endif
                    @endif
                @endif
                </span><br />
                @if($boletos[$i]['cortesia'] != 1)
                    <span class="precios">${{$boletos[$i]['precio']}} CXS ${{$boletos[$i]['comision']}} TOTAL ${{$boletos[$i]['total']}}</span>
                @else
                    <span class="precios">BOLETO DE CORTESÍA</span><br>
                    <span class="precios">PROHIBIDA SU VENTA</span>
                @endif
            </section>


            <section class="codeBar">
                <div class="codeArea" style="text-align: center;">
                    <img width="31%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" />
                </div>
            </section>

            <section class="white">

                <!--<img width="120px;" src="{{ asset('images/logo.svg') }}" style="margin-top: 1px; " />-->
                <p>
                    <strong>{{$evento->titulo_boleto}} {{$evento->subtitulo_boleto}}</strong><br />
                    {{$evento->escenario}}<br />
                    {{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.<br />
                    @if($boletos[$i]['cortesia'] != 1)
                        @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                            <span style="font-size: 12px">Zona {{$boletos[$i]['localidad']}}, Asiento - {{$boletos[$i]['asiento']}} // TOTAL ${{$boletos[$i]['total']}}</span>
                        @else
                            <span style="font-size: 12px">Zona {{$boletos[$i]['localidad']}} // TOTAL ${{$boletos[$i]['total']}}</span>
                        @endif
                    @else
                        @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                            <span style="font-size: 12px">Zona {{$boletos[$i]['localidad']}}, Asiento - {{$boletos[$i]['asiento']}} // BOLETO DE CORTESÍA</span><br>
                            <span style="font-size: 11px">PROHIBIDA SU VENTA</span>
                        @else
                            <span style="font-size: 12px">Zona {{$boletos[$i]['localidad']}} // BOLETO DE CORTESÍA</span><br>
                            <span style="font-size: 11px">PROHIBIDA SU VENTA</span>
                        @endif
                    @endif
                </p>
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
