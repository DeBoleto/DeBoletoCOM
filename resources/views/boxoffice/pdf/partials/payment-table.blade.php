@use('App\Modules\BoxOffice\Support\Money')
<table class="breakdown">
    <caption>{{ $section['title'] }}</caption>
    <thead>
        <tr>
            <th style="width: 30%">Localidad</th>
            <th class="right">Boletos</th>
            <th class="right">Precio</th>
            <th class="right">Venta</th>
            <th class="right">Comisión</th>
            @if ($section['show_card_commission'])
                <th class="right">Com. tarjeta</th>
            @endif
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($section['rows'] as $row)
            <tr>
                <td>{{ $row['locality'] }}</td>
                <td class="right tabular">{{ $row['quantity'] }}</td>
                <td class="right tabular">${{ Money::format($row['unit_price']) }}</td>
                <td class="right tabular">${{ Money::format($row['tickets']) }}</td>
                <td class="right tabular">${{ Money::format($row['commission']) }}</td>
                @if ($section['show_card_commission'])
                    <td class="right tabular">${{ Money::format($row['card_commission']) }}</td>
                @endif
                <td class="right tabular">${{ Money::format($row['total']) }}</td>
            </tr>
        @empty
            <tr class="empty">
                <td colspan="{{ $section['show_card_commission'] ? 7 : 6 }}">Sin ventas con esta forma de pago.</td>
            </tr>
        @endforelse
    </tbody>
    @if (count($section['rows']))
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="right tabular">{{ $section['totals']['quantity'] }}</td>
                <td></td>
                <td class="right tabular">${{ Money::format($section['totals']['tickets']) }}</td>
                <td class="right tabular">${{ Money::format($section['totals']['commission']) }}</td>
                @if ($section['show_card_commission'])
                    <td class="right tabular">${{ Money::format($section['totals']['card_commission']) }}</td>
                @endif
                <td class="right tabular">${{ Money::format($section['totals']['total']) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
