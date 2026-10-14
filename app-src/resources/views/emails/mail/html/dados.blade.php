@props(['itens' => []])
<table class="dados" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach (array_chunk($itens, 2) as $linha)
<tr>
@foreach ($linha as $item)
<td class="dados-celula" width="50%">
<span class="dados-rotulo">{{ $item[0] }}</span><br>
<span class="dados-valor dados-{{ $item[2] ?? 'neutro' }}">{{ $item[1] }}</span>
</td>
@endforeach
@if (count($linha) === 1)
<td class="dados-celula" width="50%"></td>
@endif
</tr>
@endforeach
</table>
