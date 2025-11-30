<x-mail::message>
{{-- Saludo --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# {{ __('¡Vaya!') }}
@else
# {{ __('¡Hola!') }}
@endif
@endif

{{-- Líneas de introducción --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Botón de acción --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Líneas de cierre --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Despedida --}}
@if (! empty($salutation))
{{ $salutation }}
@else
{{ __('Saludos,') }}<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
{{ __('Si tienes problemas haciendo clic en el botón ":actionText", copia y pega la siguiente URL en tu navegador:',['actionText' => $actionText]) }}
<br>
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
