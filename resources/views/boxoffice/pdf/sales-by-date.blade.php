@extends('boxoffice.pdf.layout')
@use('App\Modules\BoxOffice\Support\Money')

@section('content')
    @forelse ($functions as $item)
        <div class="function">
            <h2>Función {{ $item['function']->fecha_natural }}, {{ $item['function']->hora_natural }} h</h2>

            @php($general = $item['sections']['general'])
            <table class="breakdown">
                <caption>Resumen general</caption>
                <thead>
                    <tr>
                        <th style="width: 34%">Localidad</th>
                        <th class="right">Vendidos</th>
                        <th class="right">Cortesías</th>
                        <th class="right">Precio</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($general['rows'] as $row)
                        <tr>
                            <td>{{ $row['locality'] }}</td>
                            <td class="right tabular">{{ $row['quantity'] }}</td>
                            <td class="right tabular">{{ $row['courtesies'] }}</td>
                            <td class="right tabular">${{ Money::format($row['unit_price']) }}</td>
                            <td class="right tabular">${{ Money::format($row['total']) }}</td>
                        </tr>
                    @empty
                        <tr class="empty"><td colspan="5">Sin boletos en el periodo.</td></tr>
                    @endforelse
                </tbody>
                @if (count($general['rows']))
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="right tabular">{{ $general['totals']['quantity'] }}</td>
                            <td class="right tabular">{{ $general['totals']['courtesies'] }}</td>
                            <td></td>
                            <td class="right tabular">${{ Money::format($general['totals']['total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @foreach ($item['sections'] as $key => $section)
                @continue($key === 'general')
                @include('boxoffice.pdf.partials.payment-table', ['section' => $section])
            @endforeach
        </div>
    @empty
        <p class="muted">El evento no tiene funciones registradas.</p>
    @endforelse
@endsection
