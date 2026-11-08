@extends('boxoffice.pdf.layout')
@use('App\Modules\BoxOffice\Support\Money')

@section('content')
    @forelse ($functions as $item)
        <div class="function">
            <h2>Función {{ $item['function']->fecha_natural }}, {{ $item['function']->hora_natural }} h</h2>

            <table class="breakdown">
                <caption>Resumen por localidad</caption>
                <thead>
                    <tr>
                        <th>Localidad</th>
                        <th class="right">Vendidos</th>
                        <th class="right">Cortesías</th>
                        <th class="right">No disp.</th>
                        <th class="right">Precio</th>
                        <th class="right">Venta</th>
                        <th class="right">Com. DeBoleto</th>
                        <th class="right">Com. en línea</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($item['general']['rows'] as $row)
                        <tr>
                            <td>{{ $row['locality'] }}</td>
                            <td class="right tabular">{{ $row['quantity'] }}</td>
                            <td class="right tabular">{{ $row['courtesies'] }}</td>
                            <td class="right tabular">{{ $row['blocked'] }}</td>
                            <td class="right tabular">${{ Money::format($row['unit_price']) }}</td>
                            <td class="right tabular">${{ Money::format($row['amount']) }}</td>
                            <td class="right tabular">${{ Money::format($row['service']) }}</td>
                            <td class="right tabular">${{ Money::format($row['online']) }}</td>
                            <td class="right tabular">${{ Money::format($row['total']) }}</td>
                        </tr>
                    @empty
                        <tr class="empty"><td colspan="9">Sin ventas.</td></tr>
                    @endforelse
                </tbody>
                @if (count($item['general']['rows']))
                    <tfoot>
                        <tr>
                            <td>Totales</td>
                            <td class="right tabular">{{ $item['general']['totals']['quantity'] }}</td>
                            <td class="right tabular">{{ $item['general']['totals']['courtesies'] }}</td>
                            <td></td><td></td>
                            <td class="right tabular">${{ Money::format($item['general']['totals']['amount']) }}</td>
                            <td class="right tabular">${{ Money::format($item['general']['totals']['service']) }}</td>
                            <td class="right tabular">${{ Money::format($item['general']['totals']['online']) }}</td>
                            <td class="right tabular">${{ Money::format($item['general']['totals']['total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @foreach ($item['payments'] as $section)
                <table class="breakdown">
                    <caption>{{ $section['title'] }}</caption>
                    <thead>
                        <tr>
                            <th>Localidad</th>
                            <th class="right">Boletos</th>
                            <th class="right">Precio</th>
                            <th class="right">Venta</th>
                            <th class="right">Com. DeBoleto</th>
                            @if ($section['show_card'])<th class="right">Com. {{ $section['card_label'] }}</th>@endif
                            <th class="right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($section['rows'] as $row)
                            <tr>
                                <td>{{ $row['locality'] }}</td>
                                <td class="right tabular">{{ $row['quantity'] }}</td>
                                <td class="right tabular">${{ Money::format($row['unit_price']) }}</td>
                                <td class="right tabular">${{ Money::format($row['amount']) }}</td>
                                <td class="right tabular">${{ Money::format($row['service']) }}</td>
                                @if ($section['show_card'])<td class="right tabular">${{ Money::format($row['card']) }}</td>@endif
                                <td class="right tabular">${{ Money::format($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr class="empty"><td colspan="7">Sin ventas con esta forma de pago.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($section['rows']))
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td class="right tabular">{{ $section['totals']['quantity'] }}</td>
                                <td></td>
                                <td class="right tabular">${{ Money::format($section['totals']['amount']) }}</td>
                                <td class="right tabular">${{ Money::format($section['totals']['service']) }}</td>
                                @if ($section['show_card'])<td class="right tabular">${{ Money::format($section['totals']['card']) }}</td>@endif
                                <td class="right tabular">${{ Money::format($section['totals']['total']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endforeach
        </div>
    @empty
        <p class="muted">El evento no tiene funciones abiertas.</p>
    @endforelse
@endsection
