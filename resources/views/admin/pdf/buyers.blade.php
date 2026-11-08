@extends('boxoffice.pdf.layout', ['title' => 'Compradores', 'period' => $date, 'generatedAt' => now(config('boxoffice.timezone'))])

@section('content')
    <table class="breakdown">
        <thead>
            <tr><th>Nombre</th><th>Correo</th><th>Teléfono</th><th class="right">Boletos</th></tr>
        </thead>
        <tbody>
            @forelse ($buyers as $buyer)
                <tr>
                    <td>{{ $buyer->nombre }}</td>
                    <td>{{ $buyer->adquisicion_correo }}</td>
                    <td class="tabular">{{ $buyer->telefono }}</td>
                    <td class="right tabular">{{ $buyer->boletos }}</td>
                </tr>
            @empty
                <tr class="empty"><td colspan="4">Sin compradores registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
