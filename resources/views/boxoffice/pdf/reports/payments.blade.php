@extends('boxoffice.pdf.layout')
@use('App\Modules\BoxOffice\Support\Money')

@section('content')
    <table class="breakdown">
        <thead>
            <tr><th>Fecha</th><th>Concepto</th><th>Tipo</th><th>Comentarios</th><th class="right">Monto</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="tabular">{{ $row['date'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['reason'] }}</td>
                    <td>{{ $row['comments'] }}</td>
                    <td class="right tabular">${{ Money::format($row['amount']) }}</td>
                </tr>
            @empty
                <tr class="empty"><td colspan="5">No hay pagos registrados.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td colspan="4">Total pagado</td><td class="right tabular">${{ Money::format($total) }}</td></tr>
        </tfoot>
    </table>
@endsection
