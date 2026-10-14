@props(['url'])
<tr>
<td class="header">
<table class="header-inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header-marca" align="left">
<a href="{{ $url }}"><img src="cid:viabiliza-pin" class="header-pin" alt="" width="36" height="36">{!! $slot !!}</a>
<div class="header-lema">A viabilidade certa, no lugar certo</div>
</td>
<td class="header-logos" align="right">
<img src="cid:logo-prefeitura" class="logo-prefeitura" alt="Prefeitura de Salvador">
<img src="cid:logo-sedur" class="logo-sedur" alt="SEDUR">
</td>
</tr>
</table>
</td>
</tr>
