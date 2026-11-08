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
            padding-top: 11mm;
            page-break-before  : always;
            /*border: 1px dashed  #34495e ;*/
        }

        section.ticket .headers {
            height: 45mm;
            width: 46mm;
            font-size: 0.55em;
            font-family: 'Arial';
            overflow: hidden;
            display: flex;
            justify-content: center;
            /* border: 1px solid rgb(116, 65, 212); */
        }

        section.ticket .details {
            height: 60mm;
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
            width: 50mm;
            /* border: 1px solid rgb(212, 65, 65); */
        }

        .title1 {
            font-size: 3.4em;
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
            font-size: 1.9em;
            font-weight: bold;
        }

        .title3 {
            font-size: 1.5em;
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
                    <img style="width: 96%;margin-top: 45px;" class="boleto" src="{{ config('boxoffice.event_images_url') }}{{ $evento->imagen_boleto }}" />
                    <br>
                    <br>

                </div>
            </section>
            <section class="details">
                <div class="text">
                    <div>
                        <span class="title2">
                           Cupón valido por
                        </span><br/><br/>
                        <span class="title1">
                                $ {{$boletos[$i]['precio']}}
                        </span><br/><br/>
                        <span class="title3">
                           pesos de consumo en barra
                        </span>
                    </div>
                </div>
            </section>

            <section class="resume">
                <div style="width: 100%">
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
