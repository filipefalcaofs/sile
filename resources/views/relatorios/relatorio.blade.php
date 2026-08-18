<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 11px; color: #1a1a1a; margin: 0; line-height: 1.4; }
        .cabecalho { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; margin-bottom: 10px; }
        .cabecalho .orgao { font-size: 9.5px; text-transform: uppercase; letter-spacing: 1px; color: #444; }
        .cabecalho h1 { font-size: 15px; margin: 6px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { text-align: left; padding: 4px 6px; border: 1px solid #ccc; font-size: 10px; vertical-align: top; }
        th { background: #f0f0f0; }
        .rodape { margin-top: 14px; font-size: 10px; color: #333; border-top: 1px solid #ccc; padding-top: 6px; }
        .rodape .total { font-weight: bold; }
        .rodape ul { margin: 4px 0; padding-left: 18px; }
        .rodape li { margin: 1px 0; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <div class="orgao">Prefeitura Municipal de Salvador &mdash; SEDUR</div>
        <h1>{{ $titulo }}</h1>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($colunas as $coluna)
                    <th>{{ $coluna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $linha)
                <tr>
                    @foreach ($linha as $celula)
                        <td>{{ $celula }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($colunas) }}">Nenhum registro para os filtros informados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="rodape">
        <div class="total">Total de registros: {{ $total }}</div>
        <div>Gerado em: {{ $geradoEm->format('d/m/Y H:i') }}</div>
        @if (! empty($filtros))
            <div>
                Filtros aplicados:
                <ul>
                    @foreach ($filtros as $chave => $valor)
                        <li>{{ $chave }}: {{ $valor }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <div>Filtros aplicados: nenhum (todos os registros).</div>
        @endif
    </div>
</body>
</html>
