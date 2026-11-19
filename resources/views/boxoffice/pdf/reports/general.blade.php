@extends('boxoffice.pdf.layout')
@use('App\Modules\BoxOffice\Support\Money')

@section('per-function', '1')

@section('content')
    @forelse ($functions as $item)
        <div class="function">
            @include('boxoffice.pdf.partials.function-info', ['function' => $item['function'], 'stats' => [
                'Total Boletos Vendidos' => $item['sold'],
                'Total Cortesías' => $item['courtesies'],
                'Total Reservados' => $item['reserved'],
                'Personas en el Evento' => $item['people'],
            ]])
            <p class="section-title">Desglose de Ventas</p>
            <table class="breakdown">
                <thead>
                    <tr>
                        <th>Zona</th>
                        <th class="right">Precio</th>
                        @if ($item['hasCapacity'])<th class="right">Aforo</th>@endif
                        <th class="right">Vendidos</th>
                        <th class="right">Cortesías</th>
                        <th class="right">Reservados</th>
                        <th class="right">Por vender</th>
                        @if ($pendingColumns)<th class="right">Por cobrar</th>@endif
                        @if ($item['hasCapacity'])<th class="right">Disponibles</th><th class="right">% venta</th>@endif
                        <th class="right">Venta</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($item['lines'] as $line)
                        <tr>
                            <td>{{ $line['locality'] }}</td>
                            <td class="right tabular">${{ Money::format($line['price']) }}</td>
                            @if ($item['hasCapacity'])<td class="right tabular">{{ $line['capacity'] ?: '' }}</td>@endif
                            <td class="right tabular">{{ $line['sold'] }}</td>
                            <td class="right tabular">{{ $line['courtesies'] }}</td>
                            <td class="right tabular">{{ $line['blocked'] }}</td>
                            <td class="right tabular">{{ $line['toSell'] }}</td>
                            @if ($pendingColumns)<td class="right tabular">${{ Money::format($line['toCollect']) }}</td>@endif
                            @if ($item['hasCapacity'])
                                <td class="right tabular">{{ $line['available'] }}</td>
                                <td class="right tabular">{{ $line['percentage'] !== null ? $line['percentage'].' %' : '' }}</td>
                            @endif
                            <td class="right tabular">${{ Money::format($line['saleAmount']) }}</td>
                        </tr>
                    @empty
                        <tr class="empty"><td colspan="10">Sin localidades.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Totales</td>
                        @if ($item['hasCapacity'])<td class="right tabular">{{ $item['totals']['capacity'] }}</td>@endif
                        <td class="right tabular">{{ $item['totals']['sold'] }}</td>
                        <td class="right tabular">{{ $item['totals']['courtesies'] }}</td>
                        <td class="right tabular">{{ $item['totals']['blocked'] }}</td>
                        <td class="right tabular">{{ $item['totals']['toSell'] }}</td>
                        @if ($pendingColumns)<td class="right tabular">${{ Money::format($item['totals']['toCollect']) }}</td>@endif
                        @if ($item['hasCapacity'])<td class="right tabular">{{ $item['totals']['available'] }}</td><td></td>@endif
                        <td class="right tabular">${{ Money::format($item['totals']['saleAmount']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @empty
        <p class="muted">El evento no tiene funciones abiertas.</p>
    @endforelse
@endsection
