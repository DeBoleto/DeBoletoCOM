@extends('boxoffice.pdf.layout')
@use('App\Modules\BoxOffice\Support\Money')

@section('content')
    <table class="breakdown">
        <caption>Totales por localidad</caption>
        <thead>
            <tr><th>Localidad</th><th class="right">Boletos</th><th class="right">Venta</th><th class="right">Comisión</th></tr>
        </thead>
        <tbody>
            @forelse ($localities as $row)
                <tr>
                    <td>{{ $row['locality'] }}</td>
                    <td class="right tabular">{{ $row['tickets'] }}</td>
                    <td class="right tabular">${{ Money::format($row['amount']) }}</td>
                    <td class="right tabular">${{ Money::format($row['commission']) }}</td>
                </tr>
            @empty
                <tr class="empty"><td colspan="4">No hubo ventas este día.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="right tabular">{{ $totals['tickets'] }}</td>
                <td class="right tabular">${{ Money::format($totals['amount']) }}</td>
                <td class="right tabular">${{ Money::format($totals['commission']) }}</td>
            </tr>
        </tfoot>
    </table>

    @foreach ($sections as $section)
        <table class="breakdown">
            <caption>{{ $section['title'] }}</caption>
            <thead>
                <tr><th>Folio</th><th>Hora</th><th>Localidad</th><th>Asiento</th><th class="right">Boletos</th><th class="right">Venta</th><th class="right">Comisión</th></tr>
            </thead>
            <tbody>
                @foreach ($section['rows'] as $row)
                    <tr>
                        <td class="tabular">{{ $row['purchase'] }}</td>
                        <td class="tabular">{{ $row['time'] }}</td>
                        <td>{{ $row['locality'] }}</td>
                        <td>{{ $row['seat'] ?? 'General' }}</td>
                        <td class="right tabular">{{ $row['tickets'] }}</td>
                        <td class="right tabular">${{ Money::format($row['amount']) }}</td>
                        <td class="right tabular">${{ Money::format($row['commission']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">Total {{ $section['title'] }}</td>
                    <td class="right tabular">{{ $section['totals']['tickets'] }}</td>
                    <td class="right tabular">${{ Money::format($section['totals']['amount']) }}</td>
                    <td class="right tabular">${{ Money::format($section['totals']['commission']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection
