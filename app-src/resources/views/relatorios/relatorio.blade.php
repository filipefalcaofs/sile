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
    $pin = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path fill-rule="evenodd" clip-rule="evenodd" fill="#5992ed" d="M24 3C14.6 3 7 10.6 7 20c0 11.3 14.8 23.6 15.4 24.1a2.5 2.5 0 0 0 3.2 0C26.2 43.6 41 31.3 41 20c0-9.4-7.6-17-17-17ZM15.5 30V16.5l6.5-2v15.5h-6.5Zm9 0V10.5l8 2.5v17h-8Z"/></svg>');
    $logoPrefeitura = $embutir('logo_prefeitura.png');
    $logoSedur = $embutir('01JW9M9BYJ76Y0M06Z1XHJDKH8.png');
    $fuso = config('app.timezone');
    $colunaMono = static fn (string $c): bool => (bool) preg_match('/^(processo|protocolo|cnpj|cpf|cnae|inscri)/iu', $c);
    $colunaStatus = static fn (string $c): bool => (bool) preg_match('/^(status|situa|resultado)/iu', $c);
    $formatar = static function (mixed $v, string $coluna) use ($fuso): string {
        if ($v === null || $v === '') {
            return '';
        }
        $t = (string) $v;
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $t) === 1) {
            return \Illuminate\Support\Carbon::parse($t)->setTimezone($fuso)->format('d/m/Y H:i');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) === 1) {
            return \Illuminate\Support\Carbon::parse($t)->format('d/m/Y');
        }
        if (preg_match('/^cnpj/iu', $coluna) && preg_match('/^\d{11}$/', $t) === 1) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $t);
        }
        if (preg_match('/^cnpj/iu', $coluna) && preg_match('/^\d{14}$/', $t) === 1) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $t);
        }
        if (preg_match('/^cpf/iu', $coluna) && preg_match('/^\d{11}$/', $t) === 1) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $t);
        }

        return $t;
    };
    $pilula = static function (string $t): array {
        $n = mb_strtolower($t);
        return match (true) {
            (bool) preg_match('/indefer|cr[ií]tic|vencid|negad|reprov|erro|falh/u', $n) => ['◆', '#fef3f2', '#b42318', '#fecdca'],
            (bool) preg_match('/defer|conclu|aprovad|permitid|emitid|ativo|sucesso/u', $n) => ['●', '#ecfdf3', '#027a48', '#abefc6'],
            (bool) preg_match('/aten[cç]|exig|alerta|parcial/u', $n) => ['▲', '#fffaeb', '#b54708', '#fedf89'],
            (bool) preg_match('/pendent|aguard|rascunho|protocol/u', $n) => ['○', '#f0f9ff', '#026aa2', '#b9e6fe'],
            (bool) preg_match('/cancel|arquiv|inativ/u', $n) => ['–', '#f2f4f7', '#475467', '#d0d5dd'],
            default => ['◐', '#f5f9ff', '#1351b4', '#b2ccff'],
        };
    };
    $periodo = collect($filtrosCard ?? [])->firstWhere(0, 'Período')[1] ?? null;
    $truncado = isset($limite) && $limite !== null && $total >= $limite;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 70px 57px 56px 57px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9.5px; color: #1d2939; margin: 0; line-height: 1.35; }
        .barra { position: fixed; top: -70px; left: -57px; right: -57px; height: 42px; background: #0c111d; color: #fff; }
        .barra table { width: 100%; border: 0; margin: 0; border-collapse: collapse; }
        .barra td { border: 0; padding: 0 57px; height: 42px; vertical-align: middle; background: #0c111d; }
        .pe { position: fixed; bottom: -40px; left: 0; right: 0; border-top: 1px solid #e4e7ec; padding-top: 5px; font-size: 8px; color: #667085; }
        .pe table { width: 100%; border-collapse: collapse; margin: 0; }
        .pe td { border: 0; padding: 0; font-size: 8px; }
        .pe .dir { text-align: right; font-weight: bold; color: #344054; }
        .pe b { color: #344054; }
        .pagina:after { content: "Página " counter(page); }
        .marca img { width: 22px; height: 22px; vertical-align: middle; margin-right: 6px; }
        .marca { font-size: 14px; font-weight: bold; letter-spacing: 0.5px; color: #fff; }
        .marca small { font-size: 10px; font-weight: normal; color: #b8bec9; letter-spacing: 0; padding-left: 6px; }
        .logo { height: 20px; vertical-align: middle; }
        .logo.sedur { height: 17px; margin-left: 12px; }
        .eyebrow { font-size: 8.5px; font-weight: bold; letter-spacing: 0.6px; text-transform: uppercase; color: #1351b4; margin: 6px 0 2px; }
        h1 { font-size: 18px; margin: 0; color: #101828; }
        .divisor { border-top: 1px solid #e4e7ec; margin: 10px 0 0; }
        .meta { width: 100%; border-collapse: collapse; margin: 8px 0 0; }
        .meta td { border: 0; padding: 0 18px 0 0; font-size: 10.5px; font-weight: bold; color: #1d2939; width: 25%; vertical-align: top; }
        .k { display: block; font-size: 8px; font-weight: bold; letter-spacing: 0.4px; text-transform: uppercase; color: #667085; }
        .card-filtros { width: 100%; border-collapse: separate; border: 1px solid #e4e7ec; border-radius: 10px; background: #f9fafb; margin: 10px 0 0; }
        .card-filtros td { border: 0; padding: 7px 12px; width: 20%; vertical-align: top; }
        .card-filtros .v { display: block; font-size: 10.5px; color: #1d2939; font-weight: bold; }
        .card-filtros .v.todos { color: #667085; font-weight: normal; }
        .aviso { margin: 8px 0 0; padding: 4px 8px; border: 1px solid #b9e6fe; background: #f0f9ff; color: #065986; font-size: 9px; }
        table.dados { width: 100%; border-collapse: collapse; margin: 8px 0 0; table-layout: auto; }
        table.dados th { background: #f2f4f7; color: #344054; font-weight: bold; text-align: left; padding: 5px 6px; border-bottom: 1.5px solid #1351b4; font-size: 9px; }
        table.dados td { padding: 4px 6px; border-bottom: 1px solid #f2f4f7; font-size: 9px; vertical-align: top; word-wrap: break-word; }
        table.dados tr { page-break-inside: avoid; }
        table.dados thead { display: table-header-group; }
        table.dados tbody tr.zebra td { background: #f9fafb; }
        table.dados .num { text-align: right; }
        table.dados tr.total td { border-top: 2px solid #1d2939; border-bottom: 0; padding-top: 6px; font-weight: bold; color: #101828; }
        table.dados td.mono { font-family: "DejaVu Sans Mono", monospace; font-size: 8.5px; color: #344054; white-space: nowrap; }
        table.dados td.mudo { color: #98a2b3; }
        .pilula { display: inline-block; padding: 0 6px; border: 1px solid; border-radius: 9px; font-size: 8.5px; white-space: nowrap; }
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
                <td><span class="marca"><img src="{{ $pin }}" alt="">VIABILIZA<small>A viabilidade certa, no lugar certo</small></span></td>
                <td style="text-align: right;">@if ($logoPrefeitura)<img class="logo" src="{{ $logoPrefeitura }}" alt="Prefeitura de Salvador">@endif @if ($logoSedur)<img class="logo sedur" src="{{ $logoSedur }}" alt="SEDUR">@endif</td>
            </tr>
        </table>
    </div>

    <div class="eyebrow">Operação · Relatório operacional</div>
    <h1>{{ $titulo }}</h1>

    <div class="divisor"></div>
    <table class="meta">
        <tr>
            <td><span class="k">Período</span>{{ $periodo ?? 'Todos' }}</td>
            <td><span class="k">Gerado em</span>{{ $geradoEm->format('d/m/Y H:i') }}</td>
            <td><span class="k">Responsável</span>{{ $responsavel }}</td>
            <td><span class="k">Registros</span>{{ number_format($total, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table class="card-filtros">
        @foreach (array_chunk($filtrosCard, 5) as $grupo)
            <tr>
                @foreach ($grupo as [$rotulo, $valor])
                    <td><span class="k">{{ $rotulo }}</span><span @class(['v', 'todos' => $valor === null])>{{ $valor ?? 'Todos' }}</span></td>
                @endforeach
                @for ($i = count($grupo); $i < 5; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>

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
                        @php($col = $colunas[$i] ?? '')
                        @php($txt = $formatar($celula, $col))
                        <td @class(['num' => $numericas[$i] ?? false, 'mono' => $colunaMono($col), 'mudo' => $txt === ''])>
                            @if ($txt === '')
                                —
                            @elseif ($colunaStatus($col))
                                @php([$g, $bg, $fg, $bd] = $pilula($txt))
                                <span class="pilula" style="background: {{ $bg }}; color: {{ $fg }}; border-color: {{ $bd }};">{{ $g }} {{ $txt }}</span>
                            @else
                                {{ $txt }}
                            @endif
                        </td>
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
