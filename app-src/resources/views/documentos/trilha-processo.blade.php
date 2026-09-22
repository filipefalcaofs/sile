<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Trilha de auditoria — {{ $trilha['processo']['protocolo'] }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 12px; color: #1a1a1a; margin: 0; line-height: 1.4; }
        .cabecalho { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; margin-bottom: 10px; }
        .cabecalho .orgao { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #444; }
        .cabecalho h1 { font-size: 16px; margin: 6px 0 4px; }
        .secao { margin-bottom: 12px; }
        .secao h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #999; padding-bottom: 2px; margin: 0 0 6px; }
        .campo { margin: 1px 0; }
        .campo strong { display: inline-block; min-width: 130px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; border: 1px solid #ccc; font-size: 11px; vertical-align: top; }
        th { background: #f0f0f0; }
        .rodape { margin-top: 18px; font-size: 9.5px; color: #555; border-top: 1px solid #ccc; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <div class="orgao">Viabiliza &mdash; Sistema de Licenciamento Eletrônico</div>
        <div class="orgao">Prefeitura Municipal de Salvador &mdash; SEDUR</div>
        <h1>Trilha de auditoria do processo</h1>
    </div>

    <div class="secao">
        <h2>Processo</h2>
        <div class="campo"><strong>Protocolo:</strong> {{ $trilha['processo']['protocolo'] }}</div>
        <div class="campo"><strong>Situação atual:</strong> {{ $trilha['processo']['status_label'] }}</div>
        @if (! empty($trilha['processo']['empresa']))
            <div class="campo"><strong>Empresa:</strong> {{ $trilha['processo']['empresa'] }}</div>
        @endif
        <div class="campo"><strong>Protocolado em:</strong> {{ $trilha['processo']['protocolado_em'] ? \Illuminate\Support\Carbon::parse($trilha['processo']['protocolado_em'])->format('d/m/Y H:i') : '—' }}</div>
    </div>

    <div class="secao">
        <h2>Eventos ({{ count($trilha['eventos']) }})</h2>
        @if (count($trilha['eventos']) > 0)
            <table>
                <thead>
                    <tr>
                        <th style="width: 110px;">Data</th>
                        <th style="width: 120px;">Eixo</th>
                        <th>Evento</th>
                        <th style="width: 140px;">Responsável</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($trilha['eventos'] as $evento)
                        <tr>
                            <td>{{ $evento['data'] ? \Illuminate\Support\Carbon::parse($evento['data'])->format('d/m/Y H:i') : '—' }}</td>
                            <td>{{ $evento['eixo_label'] }}</td>
                            <td>{{ $evento['descricao'] }}</td>
                            <td>{{ $evento['usuario'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>Nenhum evento registrado.</p>
        @endif
    </div>

    <div class="rodape">
        Emitido em {{ $emitido_em->format('d/m/Y H:i') }} por {{ $emitido_por ?? '—' }}.
        Documento gerado eletronicamente a partir das fontes de histórico registradas no processo.
    </div>
</body>
</html>
