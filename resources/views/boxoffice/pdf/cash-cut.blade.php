@extends('boxoffice.pdf.layout')

@section('content')
    @forelse ($functions as $item)
        <div class="function">
            <h2>Función {{ $item['function']->fecha_natural }}, {{ $item['function']->hora_natural }} h</h2>
            @foreach ($item['sections'] as $section)
                @include('boxoffice.pdf.partials.payment-table', ['section' => $section])
            @endforeach
        </div>
    @empty
        <p class="muted">El evento no tiene funciones registradas.</p>
    @endforelse
@endsection
