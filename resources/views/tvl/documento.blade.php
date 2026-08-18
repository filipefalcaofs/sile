<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Termo de Viabilidade de Localização</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 12px; color: #1a1a1a; margin: 0; line-height: 1.4; }
        .cabecalho { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; margin-bottom: 10px; }
        .cabecalho .orgao { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #444; }
        .cabecalho h1 { font-size: 16px; margin: 6px 0 4px; }
        .numero-produto { font-size: 13px; font-weight: bold; }
        .nota-interna { font-size: 9.5px; color: #555; font-style: italic; margin: 0 0 12px; }
        .secao { margin-bottom: 12px; }
        .secao h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #999; padding-bottom: 2px; margin: 0 0 6px; }
        .campo { margin: 1px 0; }
        .campo strong { display: inline-block; min-width: 130px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; border: 1px solid #ccc; font-size: 11px; vertical-align: top; }
        th { background: #f0f0f0; }
        ul { margin: 4px 0; padding-left: 18px; }
        li { margin: 1px 0; }
        .assinatura { margin-top: 30px; text-align: center; }
        .assinatura img { max-height: 70px; }
        .assinatura .cargo { margin: 6px auto 0; border-top: 1px solid #333; width: 240px; padding-top: 4px; font-size: 11px; }
        .rodape { margin-top: 18px; font-size: 9.5px; color: #555; border-top: 1px solid #ccc; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <div class="orgao">Prefeitura Municipal de Salvador &mdash; SEDUR</div>
        <h1>Termo de Viabilidade de Localização (TVL)</h1>
        <div class="numero-produto">Nº do produto: {{ $tvl_product_number ?? '—' }}</div>
    </div>

    <p class="nota-interna">
        Documento administrativo interno (HU-132): emitido na retaguarda para arquivo,
        atendimento presencial e auditoria. Não substitui o parecer oficial (Regin/Junta)
        nem os dados transmitidos à SEFAZ, e não é enviado automaticamente ao requerente.
    </p>

    <div class="secao">
        <h2>Identificação</h2>
        <div class="campo"><strong>Protocolo:</strong> {{ $protocolo ?? '—' }}</div>
        <div class="campo"><strong>Data da decisão:</strong> {{ optional($decidido_em)->format('d/m/Y H:i') ?? '—' }}</div>
        <div class="campo"><strong>Emitido em:</strong> {{ optional($emitido_em)->format('d/m/Y H:i') ?? '—' }}</div>
    </div>

    <div class="secao">
        <h2>Empresa</h2>
        <div class="campo"><strong>Razão social:</strong> {{ $empresa['razao_social'] ?? '—' }}</div>
        @if (! empty($empresa['nome_fantasia']))
            <div class="campo"><strong>Nome fantasia:</strong> {{ $empresa['nome_fantasia'] }}</div>
        @endif
        <div class="campo"><strong>CNPJ:</strong> {{ $empresa['cnpj'] ?? '—' }}</div>
    </div>

    <div class="secao">
        <h2>Imóvel</h2>
        <div class="campo"><strong>Endereço:</strong> {{ $imovel['endereco'] ?? '—' }}</div>
        @if (! empty($imovel['bairro']))
            <div class="campo"><strong>Bairro:</strong> {{ $imovel['bairro'] }}</div>
        @endif
        @if (! empty($imovel['area_m2']))
            <div class="campo"><strong>Área utilizada:</strong> {{ $imovel['area_m2'] }} m²</div>
        @endif
    </div>

    <div class="secao">
        <h2>Atividades deferidas</h2>
        @if (count($atividades) > 0)
            <table>
                <thead>
                    <tr>
                        <th style="width: 110px;">CNAE</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($atividades as $atividade)
                        <tr>
                            <td>{{ $atividade['codigo_formatado'] ?? $atividade['codigo'] }}</td>
                            <td>
                                {{ $atividade['descricao'] ?? '—' }}
                                @if (! empty($atividade['condicionantes']))
                                    <ul>
                                        @foreach ($atividade['condicionantes'] as $condicionante)
                                            <li>{{ $condicionante }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>—</p>
        @endif
    </div>

    @if (count($condicionantes) > 0)
        <div class="secao">
            <h2>Condicionantes</h2>
            <ul>
                @foreach ($condicionantes as $condicionante)
                    <li>{{ $condicionante }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($parecer))
        <div class="secao">
            <h2>Parecer técnico</h2>
            <p>{{ $parecer }}</p>
        </div>
    @endif

    <div class="secao">
        <h2>Fundamentação legal</h2>
        @if (count($fundamentacao) > 0)
            <ul>
                @foreach ($fundamentacao as $base)
                    <li>{{ $base }}</li>
                @endforeach
            </ul>
        @else
            <p>—</p>
        @endif
    </div>

    @if ($assinatura && ! empty($assinatura['imagem']))
        <div class="assinatura">
            <img src="{{ $assinatura['imagem'] }}" alt="Assinatura da Diretoria">
            <div class="cargo">Diretoria &mdash; SEDUR</div>
        </div>
    @endif

    <div class="rodape">
        Código de verificação: <strong>{{ $verification_code }}</strong>.
        A autenticidade deste documento pode ser conferida na retaguarda da SEDUR.
    </div>
</body>
</html>
