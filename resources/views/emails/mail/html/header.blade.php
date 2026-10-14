@props(['url'])
<tr>
<td class="header">
<table class="header-inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header-marca" align="left">
<a href="{{ $url }}"><img src="{{ asset('images/viabiliza-pin.png') }}" class="header-pin" alt="" width="36" height="36">{!! $slot !!}</a>
<div class="header-lema">A viabilidade certa, no lugar certo</div>
</td>
<td class="header-logos" align="right">
<img src="{{ asset('images/logo_prefeitura.png') }}" class="logo-prefeitura" alt="Prefeitura de Salvador">
<img src="{{ asset('images/01JW9M9BYJ76Y0M06Z1XHJDKH8.png') }}" class="logo-sedur" alt="SEDUR">
</td>
</tr>
</table>
</td>
</tr>
