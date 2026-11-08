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
            height                  : 80mm;
            overflow                : hidden;
            size                    : 8mm;
            text-align              : center;
            padding-top             : 30mm;
            padding-left            : 20mm;
            page-break-after       : always;
            margin-top              : -38px;
            margin-left             : -20px;
        }

        section.codeBar {
            width                   : 100%;
            height                  : 19mm;
            overflow                : hidden;
            margin-top              : 40px;
            margin-left             : 50px;
        }

        section.codeBar .codeArea {
            margin-top              : 6mm;
            margin-left             : 13mm;
            width                   : 70mm;
            height                  : 9mm;
            overflow                : hidden;
            text-align              : center;
            font-family             : "Codebar";
            font-size               : 20mm;
            padding-top             : 4mm;
        }

        section.white {
            width                   : 100%;
            height                  : 25mm;
            overflow                : hidden;
            text-align              : center;
            page-break-before       : always;
            size                    : 6mm;
            text-align              : center;
            padding-top             : 20px;
            padding-bottom          : 40px;
        }

        section.white h6,
        section.white h5 {
            margin-top              : 1mm;
            margin-bottom           : 1mm;
            font-family : 'Arial';
        }

        section.white p {
            margin                  : 3mm;
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
            font-size: 0.9em;
            font-family: "Arial";
            font-weight: bold;
        }

        .zona {
            font-size: 1.2em;
            font-family: "Arial";
            font-weight: bold;
        }

    </style>
</head>
<body>

    <section id="printable">
        @for($i = 0; $i < sizeof($boletos); $i++)

            <section class="white">
                <h5 style="font-size: 1.0em; font-weight: bold">{{$evento->titulo_boleto}}</h5>
                <h6 style="font-size: 0.9em; font-weight: bold">{{$evento->subtitulo_boleto}}</h6>
                <h6 style="font-size: 0.7em; font-weight: bold">{{$evento->escenario}}</h6>
                <p>
                    {{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.<br />
                    @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                        Zona {{$boletos[$i]['localidad']}}, Asiento - {{$boletos[$i]['asiento']}}
                    @else
                        Zona {{$boletos[$i]['localidad']}}
                    @endif
                </p>

                @if($boletos[$i]['cortesia'] != 1)
                    <h6>${{$boletos[$i]['precio']}} CXS ${{$boletos[$i]['comision']}} TOTAL ${{$boletos[$i]['total']}}</h6>
                @else
                    <H6>BOLETO DE CORTESÍA</H6>
                @endif
            </section>

            <section class="codeBar">
                <div class="codeArea">
                    {{-- <img width="80%" src="{{ Barcode::codabarDataUri(str_pad($boletos[$i]['pk_evento_funcion_boleto'], 10, '0', STR_PAD_LEFT)) }}" alt="barcode" /> --}}
                    {{ str_pad($boletos[$i]['pk_evento_funcion_boleto'], 10, '0', STR_PAD_LEFT) }}
                </div>
            </section>

            <section class="main contenido-vertical">
                <span class="title">{{$evento->titulo_boleto}}</span><br />
                <span class="title2">{{$evento->subtitulo_boleto}}</span><br />
                <span class="title3">{{$evento->escenario}}</span><br />
                <span><strong>{{SpanishDate::long($funcion->fecha)}}</strong></span><br />
                <span><strong>{{$funcion->hora_natural}} Hrs.</strong></span><br /><br /><br />
                {{--  <span>{{$funcion->fecha_natural}} - {{$funcion->hora_natural}} Hrs.</span><br />  --}}
                <span class="zona">Zona {{$boletos[$i]['localidad']}}</span><br />
                @if($boletos[$i]['asiento'] != 'NA_NA')
                    <span class="asiento">Fila - {{substr($boletos[$i]['asiento'], 0, 1)}} /// Asiento - {{substr($boletos[$i]['asiento'], 1)}}</span><br />
                @endif

                @if($boletos[$i]['cortesia'] != 1)
                    <span class="precios">${{$boletos[$i]['precio']}} CXS ${{$boletos[$i]['comision']}} TOTAL ${{$boletos[$i]['total']}}</span>
                @else
                    <span class="precios">BOLETO DE CORTESÍA</span>
                @endif
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
