<x-mail::message>
@isset($selo)
<x-mail::selo :tipo="$selo[1]">{{ $selo[0] }}</x-mail::selo>
@endisset

# {{ $titulo ?? (! empty($greeting) ? $greeting : 'Olá!') }}

@foreach ($introLines as $line)
{{ $line }}

@endforeach
@if (! empty($dados))
<x-mail::dados :itens="$dados" />
@endif
@isset($destaque)
<x-mail::destaque :tipo="$destaque[1]">
{{ $destaque[0] }}
</x-mail::destaque>
@endisset
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
@foreach ($outroLines as $line)
{{ $line }}

@endforeach
@isset($actionText)
<x-slot:subcopy>
Se o botão “{{ $actionText }}” não funcionar, copie e cole este endereço no navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
