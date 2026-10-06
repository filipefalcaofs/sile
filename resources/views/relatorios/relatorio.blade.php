@php
    // Coluna numérica (alinhada à direita) = todos os valores preenchidos são números pt-BR/ISO.
    $ehNumero = static fn ($v): bool => is_int($v) || is_float($v)
        || (is_string($v) && preg_match('/^-?(\d{1,3}(\.\d{3})+|\d+)(,\d+)?%?$/', trim($v)) === 1 && ! preg_match('/^0\d/', trim($v)));
    $numericas = [];
    foreach (array_keys($colunas) as $i) {
        $valores = array_filter(array_column($linhas, $i), static fn ($v) => $v !== null && $v !== '');
        $numericas[$i] = $valores !== [] && count(array_filter($valores, $ehNumero)) === count($valores);
    }
    $embutir = static function (string $arquivo): ?string {
        $caminho = public_path('images/'.$arquivo);

        return is_file($caminho) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($caminho)) : null;
    };
    $logoPrefeitura = $embutir('logo_prefeitura.png');
    $logoSedur = $embutir('01JW9M9BYJ76Y0M06Z1XHJDKH8.png');
    $truncado = isset($limite) && $limite !== null && $total >= $limite;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 62px 40px 46px 40px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9.5px; color: #1d2939; margin: 0; line-height: 1.35; }
        .barra { position: fixed; top: -62px; left: -40px; right: -40px; height: 34px; background: #0c111d; color: #fff; }
        .barra table { width: 100%; border: 0; margin: 0; border-collapse: collapse; }
        .barra td { border: 0; padding: 0 40px; height: 34px; vertical-align: middle; background: #0c111d; }
        .pe { position: fixed; bottom: -34px; left: 0; right: 0; border-top: 1px solid #e4e7ec; padding-top: 5px; font-size: 8px; color: #667085; }
        .pe table { width: 100%; border-collapse: collapse; margin: 0; }
        .pe td { border: 0; padding: 0; font-size: 8px; }
        .pe .dir { text-align: right; font-weight: bold; color: #344054; }
        .pe b { color: #344054; }
        .pagina:after { content: "Página " counter(page); }
        .marca { font-size: 12px; font-weight: bold; letter-spacing: 0.5px; color: #fff; }
        .marca small { font-size: 8px; font-weight: normal; color: #b8bec9; letter-spacing: 0; padding-left: 6px; }
        .logo { height: 18px; vertical-align: middle; }
        .logo.sedur { height: 15px; margin-left: 12px; }
        .eyebrow { font-size: 8.5px; font-weight: bold; letter-spacing: 0.6px; text-transform: uppercase; color: #1351b4; margin: 6px 0 2px; }
        h1 { font-size: 18px; margin: 0; color: #101828; }
        .meta { width: 100%; border-collapse: collapse; margin: 4px 0 0; }
        .meta td { border: 0; padding: 0 18px 0 0; font-size: 10px; font-weight: bold; color: #1d2939; }
        .meta .k { display: block; font-size: 8px; font-weight: bold; letter-spacing: 0.4px; text-transform: uppercase; color: #667085; }
        .filtros { margin: 8px 0 0; font-size: 9px; color: #344054; }
        .filtros b { font-size: 8px; letter-spacing: 0.4px; text-transform: uppercase; color: #667085; }
        .filtros span { padding-right: 14px; }
        .kpis { width: 100%; border-collapse: collapse; margin: 8px 0 0; border: 1px solid #e4e7ec; }
        .kpis td { border: 0; border-right: 1px solid #f2f4f7; padding: 5px 10px; }
        .kpis .t { font-size: 8px; font-weight: bold; letter-spacing: 0.4px; text-transform: uppercase; color: #667085; }
        .kpis .v { font-size: 14px; font-weight: bold; color: #1d2939; }
        .aviso { margin: 8px 0 0; padding: 4px 8px; border: 1px solid #b9e6fe; background: #f0f9ff; color: #065986; font-size: 9px; }
        table.dados { width: 100%; border-collapse: collapse; margin: 8px 0 0; table-layout: auto; }
        table.dados th { background: #f2f4f7; color: #344054; font-weight: bold; text-align: left; padding: 4px 5px; border-bottom: 1.5px solid #1351b4; font-size: 9px; }
        table.dados td { padding: 3px 5px; border-bottom: 1px solid #f2f4f7; font-size: 9px; vertical-align: top; word-wrap: break-word; }
        table.dados tr { page-break-inside: avoid; }
        table.dados thead { display: table-header-group; }
        table.dados tbody tr.zebra td { background: #f9fafb; }
        table.dados .num { text-align: right; }
        table.dados tr.total td { border-top: 2px solid #1d2939; border-bottom: 0; padding-top: 6px; font-weight: bold; color: #101828; }
        .vazio { padding: 14px 6px; color: #475467; text-align: center; }
        .rodape-final { margin-top: 10px; font-size: 9px; color: #344054; }
        .rodape-final .total { font-weight: bold; }
    </style>
</head>
<body>
    <div class="pe">
        <table>
            <tr>
                <td class="esq"><b>VIABILIZA</b> · {{ $titulo }} · gerado em {{ $geradoEm->format('d/m/Y H:i') }}</td>
                <td class="dir"><span class="pagina"></span></td>
            </tr>
        </table>
    </div>

    <div class="barra">
        <table>
            <tr>
                <td><span class="marca">VIABILIZA<small>A viabilidade certa, no lugar certo</small></span></td>
                <td style="text-align: right;">@if ($logoPrefeitura)<img class="logo" src="{{ $logoPrefeitura }}" alt="Prefeitura de Salvador">@endif @if ($logoSedur)<img class="logo sedur" src="{{ $logoSedur }}" alt="SEDUR">@endif</td>
            </tr>
        </table>
    </div>

    <div class="eyebrow">Operação · Relatório operacional</div>
    <h1>{{ $titulo }}</h1>

    <table class="meta">
        <tr>
            <td><span class="k">Gerado em</span>{{ $geradoEm->format('d/m/Y H:i') }}</td>
            <td><span class="k">Registros</span>{{ number_format($total, 0, ',', '.') }}</td>
            <td><span class="k">Colunas</span>{{ count($colunas) }}</td>
        </tr>
    </table>

    <div class="filtros">
        <b>Filtros ·</b>
        @forelse ($filtros as $chave => $valor)
            <span>{{ $chave }}: {{ $valor }}</span>
        @empty
            <span>nenhum (todos os registros)</span>
        @endforelse
    </div>

    @if ($truncado)
        <div class="aviso">ⓘ Este PDF traz os {{ number_format($total, 0, ',', '.') }} primeiros registros do recorte (teto técnico de linhas). O conjunto completo está no Excel (aba DADOS).</div>
    @endif

    <table class="dados">
        <thead>
            <tr>
                @foreach ($colunas as $i => $coluna)
                    <th @class(['num' => $numericas[$i]]) scope="col">{{ $coluna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $linha)
                <tr @class(['zebra' => $loop->even])>
                    @foreach ($linha as $i => $celula)
                        <td @class(['num' => $numericas[$i] ?? false])>{{ $celula }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="vazio" colspan="{{ count($colunas) }}">Nenhum registro para os filtros informados.</td>
                </tr>
            @endforelse
            @if ($linhas !== [])
                <tr class="total">
                    <td colspan="{{ count($colunas) }}">Total geral · {{ number_format($total, 0, ',', '.') }} registros</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="rodape-final">
        <div class="total">Total de registros: {{ $total }}</div>
        <div>Gerado em: {{ $geradoEm->format('d/m/Y H:i') }}</div>
        @if (empty($filtros))
            <div>Filtros aplicados: nenhum (todos os registros).</div>
        @endif
    </div>

</body>
</html>
