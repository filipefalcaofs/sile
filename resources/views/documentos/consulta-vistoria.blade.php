@php
    $embutir = static function (string $arquivo): ?string {
        $caminho = public_path('images/'.$arquivo);

        return is_file($caminho) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($caminho)) : null;
    };
    $pin = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><path fill-rule="evenodd" clip-rule="evenodd" fill="#5992ed" d="M24 3C14.6 3 7 10.6 7 20c0 11.3 14.8 23.6 15.4 24.1a2.5 2.5 0 0 0 3.2 0C26.2 43.6 41 31.3 41 20c0-9.4-7.6-17-17-17ZM15.5 30V16.5l6.5-2v15.5h-6.5Zm9 0V10.5l8 2.5v17h-8Z"/></svg>');
    $logoPrefeitura = $embutir('logo_prefeitura.png');
    $logoSedur = $embutir('01JW9M9BYJ76Y0M06Z1XHJDKH8.png');
    $fmt = static fn ($v, string $formato = 'd/m/Y H:i'): string => $v ? \Illuminate\Support\Carbon::parse($v)->setTimezone(config('app.timezone'))->format($formato) : '—';
    $ou = static fn ($v): string => filled($v) ? (string) $v : '—';
    $endereco = $processo['endereco_completo'] ?? $processo['imovel'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} — {{ $processo['protocol_number'] }}</title>
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
        .cartao { width: 100%; border-collapse: separate; border: 1px solid #e4e7ec; border-radius: 10px; background: #f9fafb; margin: 10px 0 0; }
        .cartao td { border: 0; padding: 7px 12px; vertical-align: top; width: 33.33%; }
        .cartao .v { display: block; font-size: 10.5px; color: #1d2939; font-weight: bold; }
        .secao { margin: 14px 0 0; }
        .secao h2 { font-size: 9px; font-weight: bold; letter-spacing: 0.6px; text-transform: uppercase; color: #1351b4; margin: 0 0 4px; padding-bottom: 3px; border-bottom: 1.5px solid #1351b4; }
        .orientacao { border: 1px solid #b2ccff; background: #f5f9ff; border-radius: 8px; padding: 9px 12px; font-size: 10.5px; color: #101828; white-space: pre-line; }
        .orientacao-meta { margin: 0 0 5px; font-size: 8.5px; color: #475467; }
        .aviso-endereco { margin: 4px 0 0; padding: 5px 8px; border: 1px solid #fedf89; background: #fffaeb; color: #b54708; font-size: 9px; }
        .vazio { color: #667085; font-size: 9.5px; }
        table.dados { width: 100%; border-collapse: collapse; margin: 4px 0 0; }
        table.dados th { background: #f2f4f7; color: #344054; font-weight: bold; text-align: left; padding: 5px 6px; border-bottom: 1.5px solid #1351b4; font-size: 9px; }
        table.dados td { padding: 4px 6px; border-bottom: 1px solid #f2f4f7; font-size: 9px; vertical-align: top; }
        table.dados tr { page-break-inside: avoid; }
        table.dados td.mono { font-family: "DejaVu Sans Mono", monospace; font-size: 8.5px; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="pe">
        <table>
            <tr>
                <td class="esq"><b>VIABILIZA</b> · {{ $titulo }} · {{ $processo['protocol_number'] }} · gerado em {{ $geradoEm->format('d/m/Y H:i') }}</td>
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

    <div class="eyebrow">Operação · Vistoria</div>
    <h1>{{ $titulo }}</h1>

    <div class="divisor"></div>
    <table class="meta">
        <tr>
            <td><span class="k">Processo</span>{{ $ou($processo['protocol_number']) }}</td>
            <td><span class="k">Gerado em</span>{{ $geradoEm->format('d/m/Y H:i') }}</td>
            <td><span class="k">Responsável</span>{{ $responsavel }}</td>
            <td><span class="k">Vistoriador</span>{{ $ou($vistoria['vistoriador']) }}</td>
        </tr>
    </table>

    <div class="secao">
        <h2>Identificação do processo</h2>
        <table class="cartao">
            <tr>
                <td><span class="k">Nº do processo</span><span class="v">{{ $ou($processo['protocol_number']) }}</span></td>
                <td><span class="k">Protocolo BAP</span><span class="v">{{ $ou($processo['bap']) }}</span></td>
                <td><span class="k">Nº da viabilidade</span><span class="v">{{ $ou($processo['tvl_product_number']) }}</span></td>
            </tr>
            <tr>
                <td><span class="k">Empresa</span><span class="v">{{ $ou($processo['empresa']) }}</span></td>
                <td><span class="k">CNPJ</span><span class="v">{{ $ou($processo['cnpj']) }}</span></td>
                <td><span class="k">Data de entrada</span><span class="v">{{ $fmt($processo['protocoled_at']) }}</span></td>
            </tr>
            <tr>
                <td><span class="k">Origem</span><span class="v">{{ $ou($processo['origin_label']) }}</span></td>
                <td><span class="k">Status</span><span class="v">{{ $ou($processo['status_label']) }}</span></td>
                <td><span class="k">Situação da análise</span><span class="v">{{ $ou($processo['analysis_status_label']) }}</span></td>
            </tr>
            <tr>
                <td><span class="k">Analista responsável</span><span class="v">{{ $ou($processo['analista']) }}</span></td>
                <td><span class="k">Etapa da análise</span><span class="v">{{ $ou($processo['analysis_stage_label']) }}</span></td>
                <td><span class="k">Prazo (SLA)</span><span class="v">{{ $processo['analysis_due_at'] ? $fmt($processo['analysis_due_at']).(($processo['sla']['status_label'] ?? null) ? ' · '.$processo['sla']['status_label'].' ('.$processo['sla']['restante'].')' : '') : '—' }}</span></td>
            </tr>
            <tr>
                <td colspan="3"><span class="k">Serviço</span><span class="v">{{ $processo['servicos'] !== [] ? implode(' · ', $processo['servicos']) : '—' }}</span></td>
            </tr>
            @if (! empty($processo['categorias']))
                <tr>
                    <td colspan="3"><span class="k">Categorias</span><span class="v">{{ collect($processo['categorias'])->pluck('label')->implode(' · ') }}</span></td>
                </tr>
            @endif
        </table>
    </div>

    <div class="secao">
        <h2>Orientações do analista para a vistoria</h2>
        @if ($despacho)
            <div class="orientacao-meta">
                @if ($despacho['analista'])Analista: {{ $despacho['analista'] }} · @endif
                @if ($despacho['setor_origem'])Setor de origem: {{ $despacho['setor_origem'] }} · @endif
                Encaminhado em {{ $fmt($despacho['encaminhado_em']) }}
            </div>
            <div class="orientacao">{{ $despacho['orientacoes'] }}</div>
        @else
            <div class="vazio">Nenhum encaminhamento à vistoria registrado para este processo.</div>
        @endif
    </div>

    <div class="secao">
        <h2>Imóvel</h2>
        @if ($divergenciaEndereco)
            <div class="aviso-endereco">▲ Endereço diferente do cadastro da inscrição: o logradouro informado no processo é "{{ $divergenciaEndereco['declarado'] }}", mas a inscrição está cadastrada na SEFAZ em "{{ $divergenciaEndereco['sefaz'] }}". A análise de zona e via usa o imóvel da inscrição.</div>
        @endif
        <table class="cartao">
            <tr>
                <td colspan="3"><span class="k">Endereço</span><span class="v">{{ $ou($endereco) }}</span></td>
            </tr>
            <tr>
                <td><span class="k">Inscrição imobiliária</span><span class="v">{{ $ou($processo['inscricao']) }}</span></td>
                <td><span class="k">Tipo de imóvel</span><span class="v">{{ $ou($processo['tipo_imovel']) }}</span></td>
                <td><span class="k">Setor responsável</span><span class="v">{{ $ou($processo['setor']) }}</span></td>
            </tr>
            <tr>
                <td><span class="k">Coordenadas (lat, lng)</span><span class="v">{{ $ou($localizacao['coordenadas']) }}</span></td>
                <td><span class="k">Origem da localização</span><span class="v">{{ $ou($localizacao['origem']) }}</span></td>
                <td><span class="k">Desenho do lote</span><span class="v">{{ $localizacao['desenho_lote'] ? 'Polígono cadastrado' : ($localizacao['poligono_enderecamento'] ? 'Polígono do endereçamento SEDUR' : 'Sem polígono') }}</span></td>
            </tr>
        </table>
    </div>

    @if (! empty($processo['cnaes_detalhe']))
        <div class="secao">
            <h2>Atividades (CNAE)</h2>
            <table class="dados">
                <thead>
                    <tr><th style="width: 90px;">CNAE</th><th>Descrição</th><th style="width: 60px;">Tipo</th></tr>
                </thead>
                <tbody>
                    @foreach ($processo['cnaes_detalhe'] as $cnae)
                        <tr>
                            <td class="mono">{{ $cnae['codigo'] }}</td>
                            <td>{{ $cnae['descricao'] }}</td>
                            <td>{{ $cnae['principal'] ? 'Principal' : 'Secundário' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="secao">
        <h2>Motivo da análise</h2>
        @if (filled($processo['motivo_da_analise']))
            <div style="font-size: 10px;">{{ $processo['motivo_da_analise'] }}</div>
        @elseif (filled($processo['contingency_reason']))
            <div style="font-size: 10px;"><b>Motivo da contingência:</b> {{ $processo['contingency_reason'] }}</div>
        @else
            <div class="vazio">Sem motivo registrado.</div>
        @endif
    </div>

    @if (! empty($processo['escritorio_virtual']))
        <div class="secao">
            <h2>Escritório virtual</h2>
            <table class="cartao">
                <tr>
                    <td><span class="k">Tipo</span><span class="v">{{ $processo['escritorio_virtual']['tipo'] === 'sede' ? 'Sede' : 'Abrigado' }}</span></td>
                    <td><span class="k">{{ $processo['escritorio_virtual']['tipo'] === 'abrigado' ? 'End. Virtual — Viabilidade da sede' : 'Viabilidade da sede' }}</span><span class="v">{{ $ou($processo['escritorio_virtual']['tvl_sede']) }}</span></td>
                    <td><span class="k">Status do produto</span><span class="v">{{ $ou($processo['escritorio_virtual']['status_produto']) }}</span></td>
                </tr>
            </table>
        </div>
    @endif

    @if ($parecer)
        <div class="secao">
            <h2>Parecer da decisão</h2>
            <table class="cartao">
                <tr>
                    <td><span class="k">Resultado</span><span class="v">{{ $parecer['outcome_label'] }}</span></td>
                    <td colspan="2"><span class="k">Decidido em</span><span class="v">{{ $fmt($parecer['decidido_em']) }}</span></td>
                </tr>
                @if ($parecer['motivo'])
                    <tr><td colspan="3"><span class="k">Motivo</span><span class="v" style="font-weight: normal;">{{ $parecer['motivo'] }}</span></td></tr>
                @endif
                @if ($parecer['fundamentacao'] !== [])
                    <tr><td colspan="3"><span class="k">Fundamentação</span>@foreach ($parecer['fundamentacao'] as $linha)<span class="v" style="font-weight: normal;">• {{ $linha }}</span>@endforeach</td></tr>
                @endif
            </table>
        </div>
    @endif

    @if ($camadas !== [])
        <div class="secao">
            <h2>Camadas do imóvel</h2>
            <table class="dados">
                <tbody>
                    @foreach ($camadas as $linha)
                        <tr>
                            <td style="width: 38%; color: #667085;">{{ $linha['camada'] }}</td>
                            <td @style(['font-weight: bold', 'color: #b54708' => $linha['destaque']])>{{ $linha['elemento'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
