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
            height                  : 65mm;
            overflow                : hidden;
            size                    : 8mm;
            text-align              : center;
            padding-top             : 28mm;
            /*padding-left            : 20mm;*/
            page-break-before       : always;
            /*margin-top              : -30px;*/
            margin-left             : -10px;
        }

        section.codeBar {
            width                   : 100%;
            height                  : 19mm;
            overflow                : hidden;
            margin-top              : 10px;
            margin-left             : 0px;
        }

        section.codeBar .codeArea {
            /*margin-top              : 6mm;*/
            margin-left             : 9mm;
            width                   : 70mm;
            height                  : 9mm;
            overflow                : hidden;
            text-align              : center;
            /*font-family             : "Codebar";
            font-size               : 20mm;*/
            /*padding-top             : 4mm;*/
        }

        section.white {
            width                   : 100%;
            height                  : 25mm;
            overflow                : hidden;
            text-align              : center;
            page-break-after        : always;
            size                    : 6mm;
            text-align              : center;
            margin-top              : 45px;
            margin-left             : -15px;
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
            font-size   : 2.2em;
            font-family : 'Arial';
            font-weight: bold;
        }

        .title2 {
            font-family : 'Arial';
            font-size: 1.6em;
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
            font-size: 0.9em;
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
                <span class="title">{{$evento->titulo_boleto}}</span><br />
                <span class="title2">{{$evento->subtitulo_boleto}}</span><br />
                <span class="title3">{{$evento->escenario}}</span><br />
                <span><strong>{{SpanishDate::long($boletos[$i]['funcion_fecha'])}}</strong></span><br />
                <span><strong>{{$boletos[$i]['funcion_hora']}} Hrs.</strong></span><br /><br /><br />
                {{--  <span>{{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.</span><br />  --}}
                <span class="zona">Zona {{$boletos[$i]['localidad']}}</span><br />
                <span class="asiento">
                @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                    Fila - {{substr($boletos[$i]['asiento'], 0, 1)}} /// Asiento - {{substr($boletos[$i]['asiento'], 1)}}
                @endif
                </span><br />
                @if($boletos[$i]['cortesia'] != 1)
                    <span class="precios">${{$boletos[$i]['precio']}} CXS ${{$boletos[$i]['comision']}} TOTAL ${{$boletos[$i]['total']}}</span>
                @else
                    <span class="precios">BOLETO DE CORTESÍA</span>
                @endif
            </section>


            <section class="codeBar">
                <div class="codeArea">
                    <img width="95%" src="{{ Barcode::codabarDataUri(str_pad($boletos[$i]['pk_evento_funcion_boleto'], 10, '0', STR_PAD_LEFT)) }}" alt="barcode" />
                    {{-- {{ str_pad($boletos[$i]['pk_evento_funcion_boleto'], 10, '0', STR_PAD_LEFT) }}  --}}
                </div>
            </section>

            <section class="white">
                <p>
                    <strong>{{$evento->titulo_boleto}}</strong><br />
                    {{$evento->escenario}}<br />
                    {{$boletos[$i]['funcion_fecha_natural']}} - {{$boletos[$i]['funcion_hora']}} Hrs.<br />
                    @if($boletos[$i]['cortesia'] != 1)
                        @if($boletos[$i]['asiento'] != 'NA_NA')
                            <span style="font-size: 12px">Zona {{$boletos[$i]['localidad']}}, Asiento - {{$boletos[$i]['asiento']}} // TOTAL ${{$boletos[$i]['total']}}</span>
                        @else
                            <span style="font-size: 12px">Zona {{$boletos[$i]['localidad']}} // TOTAL ${{$boletos[$i]['total']}}</span>
                        @endif
                    @else
                        @if($boletos[$i]['asiento'] != 'NA_NA')
                            <span style="font-size: 12px">Zona {{$boletos[$i]['localidad']}}, Asiento - {{$boletos[$i]['asiento']}} // BOLETO DE CORTESÍA</span>
                        @else
                            <span style="font-size: 12px">Zona {{$boletos[$i]['localidad']}} // BOLETO DE CORTESÍA</span>
                        @endif
                    @endif
                </p>
            </section>
        </section>

        @endfor
    </section>

    {{--
    <section id="printable" class="container">
        <div class="row">
            <div class=" col-md-12 col-sm-12">
                @for($i = 0; $i < sizeof($boletos); $i++)
                    <div class="row" style="margin-top:150px"></div>
                    <div class="row text-center">
                        <div class="col-md-12" style="margin-top:10px;">
                            <h2>{{$evento->evento}}</h2>
                            <h3>{{$evento->escenario}}</h3>
                            <p>
                                {{$funcion->fecha_natural}} - {{$funcion->hora_natural}}<br />
                                Zona {{$boletos[$i]['localidad']}}
                            </p>
                            <p>
                                <b>Asiento - {{$boletos[$i]['asiento']}}</b><br />
                                ${{$boletos[$i]['precio']}} CXS ${{$boletos[$i]['comision']}} TOTAL ${{$boletos[$i]['total']}}
                            </p>
                        </div>

                        <div class="row text-center" style="margin-top: 150px; margin-bottom:10px;">
                            <img src="{{ Barcode::codabarDataUri(str_pad($boletos[$i]['pk_evento_funcion_boleto'], 10, '0', STR_PAD_LEFT)) }}" alt="barcode" />
                            <h6>{{$boletos[$i]['codigo']}}</h6>
                        </div>

                        <div class="row" style="margin-bottom:350px;">
                            <h3>{{$evento->evento}}</h3>
                            <h4>{{$evento->escenario}}</h4>
                            <p>
                                {{$funcion->fecha_natural}} - {{$funcion->hora_natural}}<br />
                                Zona {{$boletos[$i]['localidad']}}
                            </p>
                            <h4>Asiento - {{$boletos[$i]['asiento']}}</h4>
                            <h6>${{$boletos[$i]['precio']}} CXS ${{$boletos[$i]['comision']}} TOTAL ${{$boletos[$i]['total']}}</h6>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </section>
    --}}

    {{--
    --}}
    <script>
        var printContents = document.getElementById('printable').innerHTML;
        var originalContents = document.body.innerHTML;

        document.body.innerHTML = printContents;

        window.print();

        document.body.innerHTML = originalContents;
    </script>
</body>
</html>
