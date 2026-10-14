@props(['itens' => []])
@foreach ($itens as $item)
{{ $item[0] }}: {{ $item[1] }}
@endforeach
