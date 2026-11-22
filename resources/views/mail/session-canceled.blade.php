Olá, {{ $studentName }}!

O {{ $studioName }} cancelou a aula "{{ $classTitle }}" de {{ $startsAtLocal }}.
@if ($reason)

Motivo: {{ $reason }}
@endif

@if ($wasWaitlisted)
Você estava na lista de espera, então nenhuma ação é necessária.
@else
Sua reserva foi cancelada. Escolha outro horário pelo aplicativo.
@endif
