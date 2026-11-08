@extends('boxoffice.pdf.layout')

@section('content')
    <table class="breakdown">
        <thead>
            <tr><th>Localidad del boleto</th><th class="right">Pases de estacionamiento</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr><td>{{ $row['locality'] }}</td><td class="right tabular">{{ $row['total'] }}</td></tr>
            @empty
                <tr class="empty"><td colspan="2">Sin pases vendidos.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td>Total vendidos</td><td class="right tabular">{{ $total }}</td></tr>
        </tfoot>
    </table>
@endsection
