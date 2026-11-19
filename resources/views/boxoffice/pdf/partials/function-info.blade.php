@php($stats = $stats ?? [])
<p class="event-title">{{ $event->evento }} {{ $function->hora_natural }} Hrs. ({{ $event->ciudad }})</p>
<hr>
<table class="info">
    <tr>
        <td class="label">Evento</td>
        <td class="value" @if (! $stats) colspan="3" @endif>{{ $event->evento }} {{ $function->hora_natural }} Hrs. ({{ $event->ciudad }})</td>
        @if ($stats)<td class="spacer"></td><td class="spacer"></td>@endif
    </tr>
    @foreach ([
        'Estado y Ciudad' => "{$event->ciudad}, {$event->estado}",
        'Lugar' => $event->escenario,
        'Dirección' => $event->direccion,
        'Fecha' => $function->fecha_natural,
        'Hora' => "{$function->hora_natural} Hrs",
    ] as $label => $value)
        @php($stat = array_slice($stats, $loop->index, 1, true))
        <tr>
            <td class="label">{{ $label }}</td>
            <td class="value" @if (! $stats) colspan="3" @endif>{{ $value }}</td>
            @if ($stats)
                @if ($stat)
                    <td class="label">{{ array_key_first($stat) }}</td>
                    <td class="value center">{{ reset($stat) }}</td>
                @else
                    <td class="spacer"></td><td class="spacer"></td>
                @endif
            @endif
        </tr>
    @endforeach
</table>
