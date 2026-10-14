@props(['tipo' => 'info'])
<table class="destaque destaque-{{ $tipo }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="destaque-conteudo">
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
