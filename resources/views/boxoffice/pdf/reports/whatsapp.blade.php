@extends('boxoffice.pdf.layout')
@use('App\Modules\BoxOffice\Support\Money')

@section('content')
    @foreach ($functions as $item)
        <div class="function">
            <h2>Función {{ $item['function']->fecha_natural }}, {{ $item['function']->hora_natural }} h</h2>
            <table class="breakdown">
                <thead>
                    <tr><th>Localidad</th><th class="right">Compras</th><th class="right">Boletos</th><th class="right">Costo de envío</th></tr>
                </thead>
                <tbody>
                    @forelse ($item['rows'] as $row)
                        <tr>
                            <td>{{ $row['locality'] }}</td>
                            <td class="right tabular">{{ $row['purchases'] }}</td>
                            <td class="right tabular">{{ $row['tickets'] }}</td>
                            <td class="right tabular">${{ Money::format($row['cost']) }}</td>
                        </tr>
                    @empty
                        <tr class="empty"><td colspan="4">Sin envíos.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td>Totales</td>
                        <td class="right tabular">{{ $item['totals']['purchases'] }}</td>
                        <td class="right tabular">{{ $item['totals']['tickets'] }}</td>
                        <td class="right tabular">${{ Money::format($item['totals']['cost']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach
@endsection
