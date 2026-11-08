@use('App\Modules\BoxOffice\Support\Barcode')
@use('App\Modules\BoxOffice\Support\SpanishDate')
<!DOCTYPE html>
<html>
<head>
    <style>

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
            height                  : 67mm;
            overflow                : hidden;
            size                    : 8mm;
            text-align              : center;
            padding-top             : 25mm;
            /*padding-left            : 20mm;*/
            page-break-before       : always;
            /*margin-top              : -30px;*/
            margin-left             : -30px;
        }

        section.codeBar {
            width                   : 100%;
            height                  : 25mm;
            overflow                : hidden;
            margin-top              : 0px;
            margin-left             : -39px;
        }

        section.codeBar .codeArea {
            /*margin-top              : 6mm;*/
            margin-left             : 8mm;
            width                   : 70mm;
            /*height                  : 9mm;*/
            overflow                : hidden;
            text-align              : center;
            /*font-family             : "Codebar";
            font-size               : 20mm;*/
            /*padding-top             : 4mm;*/
        }

        section.white {
            width                   : 100%;
            height                  : 20mm;
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
            margin-top: 20px;
            padding: 15px;
        }

    </style>
</head>
<body>

    <section id="printable">
        @for($i = 0; $i < sizeof($boletos); $i++)
        <section class="container" style="page-break-before: always;">
            <img width="50%" src="{{ asset('images/logo.svg') }}" alt="Logo" style="display: block; margin-left: auto; margin-right: auto;" /><br />
            <h2 style="text-align: center">{{$evento->titulo_boleto}}</h2>
            <h3 style="text-align: center">{{$evento->subtitulo_boleto}}</h3>
            <p>
                Escenario: <b>{{$evento->escenario}}</b><br /><br />

                <span>Fecha: <strong>{{SpanishDate::long($funcion->fecha)}}</strong></span><br />
                <span>Hora: <strong>{{$funcion->hora_natural}} Hrs.</strong></span><br /><br />

                Zona: <b>{{$boletos[$i]['localidad']}}</b><br />

                @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                    Fila - {{substr($boletos[$i]['asiento'], 0, 1)}} /// Asiento - {{substr($boletos[$i]['asiento'], 1)}}<br /><br />
                @endif

                @if($boletos[$i]['cortesia'] != 1)
                    <b>Precio: ${{$boletos[$i]['precio']}}</b><br />
                    <b>CXS: ${{$boletos[$i]['comision']}}</b><br />
                    <b>Total: ${{$boletos[$i]['total']}}</b><br />
                @else
                    <span class="precios">BOLETO DE CORTESÍA</span><br />
                @endif
                <br /><br />
                <img width="50%" src="{{ Barcode::qrDataUri(strval($boletos[$i]['pk_evento_funcion_boleto'])) }}" alt="barcode" style="display: block; margin-left: auto; margin-right: auto;" /> <br />
            </p>
            <br /><br />
            <p style="text-align: center">
                <b>GRACIAS POR TU COMPRA!</b><br />
                <b>www.deboleto.mx</b><br />
            </p>
            {{--<br /><br /><br />
            <span>-----------------------------------------------</span><br/>
            <br /><br /><br />
            <span>--------------- TAQUILLA -------------------</span>
            <br />
            <h2>{{$evento->titulo_boleto}}</h2>
            <h3>{{$evento->subtitulo_boleto}}</h3>
            <p>
                Escenario: <b>{{$evento->escenario}}</b><br /><br />

                <span>Fecha: <strong>{{SpanishDate::long($funcion->fecha)}}</strong></span><br />
                <span>Hora: <strong>{{$funcion->hora_natural}} Hrs.</strong></span><br /><br />

                Zona: <b>{{$boletos[$i]['localidad']}}</b><br />

                @if(strpos($boletos[$i]['asiento'], 'NA_NA') === false)
                    Fila - {{substr($boletos[$i]['asiento'], 0, 1)}} /// Asiento - {{substr($boletos[$i]['asiento'], 1)}}<br /><br />
                @endif

                @if($boletos[$i]['cortesia'] != 1)
                    <b>Precio: ${{$boletos[$i]['precio']}}</b><br />
                    <b>CXS: ${{$boletos[$i]['comision']}}</b><br />
                    <b>Total: ${{$boletos[$i]['total']}}</b><br />
                @else
                    <span class="precios">BOLETO DE CORTESÍA</span><br />
                @endif
            </p>--}}
        </section>
        <span style="page-break-after: always;"></span>
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
