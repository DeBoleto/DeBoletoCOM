@extends('boxoffice.pdf.layout')

@section('per-function', '1')

@section('content')
    @forelse ($functions as $item)
        <div class="function">
            @include('boxoffice.pdf.partials.function-info', ['function' => $item['function']])
            @foreach ($item['sections'] as $section)
                @include('boxoffice.pdf.partials.payment-table', ['section' => $section])
            @endforeach
        </div>
    @empty
        <p class="muted">El evento no tiene funciones registradas.</p>
    @endforelse
@endsection
