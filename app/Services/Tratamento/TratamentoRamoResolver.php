<?php

namespace App\Services\Tratamento;

use App\Enums\RuleDomain;
use App\Enums\TipoImovelReconhecimento;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoCnluItem;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use App\Models\TratamentoRegraRamo;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Risco\TipoImovel;
use Illuminate\Support\Collection;

class TratamentoRamoResolver
{
    public function __construct(private DecisionTextCatalog $textos = new DecisionTextCatalog) {}

    /**
     * Perguntas da planilha vigente para o CNAE, no formato 0000-0/00.
     * A consulta pública exibe estas perguntas e devolve as respostas.
     *
     * @return list<array{numero: int, texto: string, tipo: string, opcoes: list<array{rotulo: string, texto: string}>, depende: array{pergunta: int, sim: bool}|null}>
     */
    public function perguntasExibidas(string $cnae): array
    {
        $digitos = (string) preg_replace('/\D/', '', $cnae);

        if (strlen($digitos) !== 7) {
            return [];
        }

        $formatado = substr($digitos, 0, 4).'-'.substr($digitos, 4, 1).'/'.substr($digitos, 5, 2);
        $versao = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        if ($versao === null) {
            return [];
        }

        $numeros = TratamentoCnaeBinding::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('cnae', $formatado)
            ->get()
            ->pluck('perguntas')
            ->flatten()
            ->filter(fn (mixed $numero): bool => $numero !== null && $numero !== '')
            ->map(fn (mixed $numero): int => (int) $numero)
            ->unique()
            ->sort()
            ->values();

        if ($numeros->isEmpty()) {
            return [];
        }

        $textos = TratamentoPergunta::query()
            ->where('rule_version_id', $versao->getKey())
            ->whereIn('numero', $numeros->all())
            ->get()
            ->keyBy(fn (TratamentoPergunta $pergunta): int => (int) $pergunta->numero);

        return $numeros->map(function (int $numero) use ($textos, $formatado, $versao): array {
            $texto = (string) ($textos->get($numero)?->texto ?? "Pergunta {$numero}");
            $enunciado = PerguntaEnunciado::analisar($texto);

            if ($numero === 1 && $enunciado['opcoes'] === []) {
                $opcoes = $this->opcoesDaSubdivisao($formatado, (int) $versao->getKey());

                if ($opcoes !== []) {
                    $enunciado = ['tipo' => SubdivisaoCnae::tipo($formatado), 'opcoes' => $opcoes];
                }
            }

            return [
                'numero' => $numero,
                'texto' => $texto,
                'tipo' => $enunciado['tipo'],
                'opcoes' => $enunciado['opcoes'],
                'depende' => PerguntaEnunciado::dependencia($texto),
            ];
        })->all();
    }

    public function resolver(TratamentoRamoInput $input): TratamentoRamoResult
    {
        $versao = $this->resolverVersao($input);

        if ($versao === null) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: $this->textos->get('tratamento.motivo.sem_versao'),
            );
        }

        $bindings = TratamentoCnaeBinding::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('cnae', $input->cnae)
            ->get();

        if ($bindings->isEmpty()) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: $this->textos->get('tratamento.motivo.cnae_sem_regra'),
                versaoRegra: $versao->version,
            );
        }

        $perguntas = $bindings
            ->pluck('perguntas')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $textosDasPerguntas = TratamentoPergunta::query()
            ->where('rule_version_id', $versao->getKey())
            ->pluck('texto', 'numero');

        $naoSeAplicam = [];

        foreach ($perguntas->sort()->values() as $numero) {
            if (! array_key_exists((int) $numero, $input->respostas)) {
                // Pergunta exibida só após outra resposta ("Exibir apenas SE
                // RESPONDIDO SIM na pergunta N"): com a resposta contrária, ou
                // se a pergunta de que depende também não se aplica, ela não
                // se aplica e não trava a resolução.
                $dependencia = PerguntaEnunciado::dependencia((string) ($textosDasPerguntas[(int) $numero] ?? ''));

                if ($dependencia !== null && (
                    in_array($dependencia['pergunta'], $naoSeAplicam, true)
                    || (array_key_exists($dependencia['pergunta'], $input->respostas)
                        && RespostaPlanilha::de($input->respostas[$dependencia['pergunta']])->comoBooleano() !== $dependencia['sim'])
                )) {
                    $naoSeAplicam[] = (int) $numero;

                    continue;
                }

                // A P3 só é exibida quando a P2 é SIM (texto das regras da
                // família 52: "Se SIM na pergunta 2, exibir a pergunta 3").
                // Com P2=NÃO ela não se aplica e não pode travar a resolução
                // (pendência espúria do relatório SEDUR 21/09, item 25).
                if ((int) $numero === 3 && ($input->respostas[2] ?? null) === false) {
                    continue;
                }

                return new TratamentoRamoResult(
                    status: 'nao_resolvido',
                    motivo: $this->subdivisaoNaoCarregada((int) $numero, (string) ($textosDasPerguntas[(int) $numero] ?? ''), $input->cnae, (int) $versao->getKey())
                        ?? $this->motivoPerguntaSemResposta((int) $numero, (int) $versao->getKey()),
                    versaoRegra: $versao->version,
                );
            }
        }

        $enquadramentos = TratamentoEnquadramento::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('cnae', $input->cnae)
            ->get();

        if ($input->categoriaEmpresa === null && $this->regraDependeDaCategoria($bindings, (int) $versao->getKey())) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: $this->textos->get('tratamento.motivo.categoria_ausente'),
                versaoRegra: $versao->version,
            );
        }

        $noLocal = $this->atividadeNoLocal($input, $perguntas);
        $tipoDirige = $this->tipoDirige($input->tipoImovel);

        // O fluxo do ramo é dado curado dos textos oficiais das regras
        // (treatment_regra_ramos) — quando o ramo traz código LOUOS, ele manda
        // na linha (relatório SEDUR 21/09, item 18); quando traz só o fluxo,
        // a linha segue a seleção atual e o fluxo vence a heurística.
        $ramoCurado = $this->ramoCurado(
            $bindings,
            $input->respostas,
            $input->areaUtilizada,
            $tipoDirige,
            (int) $versao->getKey(),
            new ContextoRamo(
                respostas: $input->respostas,
                categoriaEmpresa: $input->categoriaEmpresa,
                tipoImovel: $input->tipoImovel,
                faixa: $input->areaUtilizada === null ? null : ($input->areaUtilizada <= 1250 ? 'ate_1250' : 'acima_1250'),
            ),
        );

        $linha = null;

        if ($ramoCurado !== null && $ramoCurado->codigo_louos !== null && $ramoCurado->codigo_louos !== '') {
            $linha = $enquadramentos->firstWhere('codigo_louos', $ramoCurado->codigo_louos);
        }

        if ($ramoCurado?->bloqueia && $ramoCurado->condicoes !== null) {
            return new TratamentoRamoResult(
                status: 'resolvido',
                grupo: $linha?->grupo,
                subgrupo: $linha?->subcategoria,
                codigoLouos: $linha?->codigo_louos,
                risco: $linha === null ? null : $this->normalizarRisco($linha->risco),
                fluxo: 'analise',
                bloqueia: true,
                motivo: $this->motivoDoBloqueio($ramoCurado, $input->cnae),
                versaoRegra: $versao->version,
            );
        }

        if ($linha === null && $ramoCurado?->condicoes !== null && filled($ramoCurado->codigo_louos)) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: $this->textos->get('tratamento.motivo.sem_linha'),
                versaoRegra: $versao->version,
            );
        }

        $linha ??= $this->escolherLinha($enquadramentos, $noLocal, $input->respostas);

        if ($linha === null) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: $this->textos->get('tratamento.motivo.sem_linha'),
                versaoRegra: $versao->version,
            );
        }

        if ($ramoCurado?->bloqueia) {
            return new TratamentoRamoResult(
                status: 'resolvido',
                grupo: $linha->grupo,
                subgrupo: $linha->subcategoria,
                codigoLouos: $linha->codigo_louos,
                risco: $this->normalizarRisco($linha->risco),
                fluxo: 'analise',
                bloqueia: true,
                motivo: $this->motivoDoBloqueio($ramoCurado, $input->cnae),
                versaoRegra: $versao->version,
            );
        }

        if ($this->eCnlu($linha->risco) || $this->naListaCnlu((string) $linha->subcategoria, (int) $versao->getKey())) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                fluxo: 'analise',
                motivo: $this->textos->get('tratamento.motivo.cnlu'),
                codigoLouos: $linha->codigo_louos,
                grupo: $linha->grupo,
                subgrupo: $linha->subcategoria,
                versaoRegra: $versao->version,
                cnlu: true,
            );
        }

        if ($linha->ate_m2 !== null && $input->areaUtilizada === null) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: $this->textos->get('tratamento.motivo.area_ausente'),
                versaoRegra: $versao->version,
            );
        }

        [$subgrupo, $grupo, $risco] = $this->aplicarFaixa(
            $this->linhaComFaixaDaRegra($linha, $ramoCurado),
            $input->areaUtilizada,
        );

        $subgrupoForcado = $ramoCurado?->acao['subgrupo'] ?? null;

        if (is_string($subgrupoForcado) && $subgrupoForcado !== '') {
            $subgrupo = $subgrupoForcado;
            $grupo = $this->grupoDe($subgrupoForcado);
        }

        if ($this->eFamiliaId($subgrupo)) {
            $dependenciaTipo = $this->motivoTipoAusente($input->tipoImovel);

            if ($dependenciaTipo !== null) {
                return new TratamentoRamoResult(
                    status: 'nao_resolvido',
                    motivo: $dependenciaTipo,
                    codigoLouos: $linha->codigo_louos,
                    grupo: $grupo,
                    subgrupo: $subgrupo,
                    versaoRegra: $versao->version,
                );
            }
        }

        if ($this->eFamiliaId($subgrupo) && $this->tipoDirige($input->tipoImovel)) {
            $risco = 'alto';
        } elseif ($this->tipoDirige($input->tipoImovel) && $risco === 'baixo') {
            // Planilha (regras 1, 5, 24, 51…): galpão/container/edificação
            // residencial fora da família ID eleva o ramo a MÉDIO e remete à
            // crítica do analista (o fluxoDe já marca semiexpresso). Relatório
            // SEDUR 21/09, item 01.
            $risco = 'medio';
        }

        $risco = $this->riscoDaRegra($risco, $subgrupo, $ramoCurado, $input);

        $fluxoDoRamo = $ramoCurado?->fluxo;
        $fluxoDaClassificacao = $fluxoDoRamo === null ? $this->fluxoDaClassificacao($linha->classificacao) : null;
        $fluxo = $fluxoDoRamo ?? $fluxoDaClassificacao ?? $this->fluxoDe($linha->codigo_louos, $subgrupo, $risco, $noLocal, $input->tipoImovel);

        $binding = $bindings->firstWhere('codigo_louos', $linha->codigo_louos);

        return new TratamentoRamoResult(
            status: 'resolvido',
            grupo: $grupo,
            subgrupo: $subgrupo,
            codigoLouos: $linha->codigo_louos,
            risco: $risco,
            fluxo: $fluxo,
            tll: $linha->codigo_tll,
            condicionantes: array_map('intval', $binding?->condicionantes ?? []),
            versaoRegra: $versao->version,
            planilha: $this->referenciaDaPlanilha(
                $versao,
                $linha,
                $ramoCurado,
                $subgrupo,
                $risco,
                $input,
                $noLocal,
                match (true) {
                    $fluxoDoRamo !== null => 'ramo',
                    $fluxoDaClassificacao !== null => 'classificacao',
                    default => 'heuristica',
                },
                $fluxo,
            ),
        );
    }

    /**
     * Onde, na planilha vigente, está o que decidiu este CNAE: a linha da aba
     * CNAES (regra, LOUOS, subgrupo, risco, classificação), a regra com a data
     * de atualização e o que definiu o fluxo (ramo curado da regra, a
     * classificação da linha ou a heurística do motor). É o que a ficha mostra
     * ao analista para ele conferir na planilha.
     *
     * @return array<string, mixed>
     */
    private function referenciaDaPlanilha(
        RuleVersion $versao,
        TratamentoEnquadramento $linha,
        ?TratamentoRegraRamo $ramo,
        string $subgrupo,
        string $risco,
        TratamentoRamoInput $input,
        ?bool $noLocal,
        string $origemFluxo,
        string $fluxo,
    ): array {
        $regra = $linha->regra === null ? null : TratamentoRegra::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('numero', (int) $linha->regra)
            ->first();

        $area = $input->areaUtilizada;
        $faixa = $area === null ? null : ($area <= 1250 ? 'ate_1250' : 'acima_1250');

        $decisao = match ($origemFluxo) {
            'ramo' => $this->descreverRamo($ramo),
            'classificacao' => 'classificação da linha na aba CNAES: '.trim((string) $linha->classificacao),
            default => $this->descreverHeuristica($linha->codigo_louos, $subgrupo, $risco, $noLocal, $input->tipoImovel),
        };

        return [
            'versao' => $versao->version,
            'cnae' => $this->formatarCnae($input->cnae),
            'regra' => $linha->regra === null ? null : (int) $linha->regra,
            'regra_referencia' => $regra?->referencia,
            'codigo_louos' => $linha->codigo_louos,
            'denominacao_louos' => $linha->denominacao_louos,
            'subgrupo' => $subgrupo,
            'faixa' => $faixa,
            'area_m2' => $area,
            'risco_planilha' => $linha->risco,
            'risco' => $risco,
            'classificacao' => $linha->classificacao,
            'fluxo' => $fluxo,
            'origem_fluxo' => $origemFluxo,
            'decisao_fluxo' => $decisao,
        ];
    }

    private function descreverRamo(?TratamentoRegraRamo $ramo): string
    {
        if ($ramo === null) {
            return 'ramo da regra';
        }

        $partes = [];

        if ($ramo->condicoes !== null) {
            foreach ((array) $ramo->condicoes as $chave => $valor) {
                $partes[] = $chave.' = '.(is_bool($valor) ? ($valor ? 'Sim' : 'Não') : (is_array($valor) ? implode(', ', array_map('strval', $valor)) : (string) $valor));
            }
        } else {
            if ($ramo->pergunta !== null) {
                $partes[] = 'pergunta '.$ramo->pergunta.($ramo->resposta === null ? '' : ' = '.($ramo->resposta ? 'Sim' : 'Não'));
            }

            if ($ramo->faixa !== null) {
                $partes[] = $ramo->faixa === 'ate_1250' ? 'área até 1.250 m²' : 'área acima de 1.250 m²';
            }

            if ($ramo->tipo_dirige !== null) {
                $partes[] = $ramo->tipo_dirige ? 'imóvel galpão, container ou edificação residencial' : 'imóvel que não dirige regra';
            }
        }

        $texto = 'ramo da Regra '.$ramo->regra.($partes === [] ? '' : ' ('.implode('; ', $partes).')');

        return $texto.' → fluxo '.$ramo->fluxo;
    }

    private function descreverHeuristica(string $codigoLouos, string $subgrupo, string $risco, ?bool $noLocal, ?TipoImovel $tipo): string
    {
        if ($this->eFamiliaId($subgrupo)) {
            return "subgrupo {$subgrupo} (família ID) remete à análise";
        }

        if ($tipo?->dirigeRegra() ?? false) {
            return 'tipo de imóvel '.($tipo->raw ?? 'galpão/container/residencial').' remete à crítica do analista';
        }

        if ($codigoLouos === '07.12.13' && $noLocal === false) {
            return 'escritório sem atividade no local segue o expresso';
        }

        if ($risco === 'alto') {
            return 'risco alto não é decidido automaticamente';
        }

        return "nível {$risco} da planilha";
    }

    private function resolverVersao(TratamentoRamoInput $input): ?RuleVersion
    {
        $override = $input->versoesOverride[RuleDomain::RiscoTratamento->value] ?? null;

        if ($override !== null) {
            return RuleVersion::versao(RuleDomain::RiscoTratamento, $override)->first();
        }

        if ($input->data !== null) {
            return RuleVersion::naData(RuleDomain::RiscoTratamento, $input->data)->first();
        }

        return RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();
    }

    /**
     * @param  Collection<int, mixed>  $perguntas
     */
    private function atividadeNoLocal(TratamentoRamoInput $input, $perguntas): ?bool
    {
        $preferidas = [2, 8, 11, 13];

        foreach ($preferidas as $numero) {
            if ($perguntas->contains($numero) && array_key_exists($numero, $input->respostas)) {
                return RespostaPlanilha::de($input->respostas[$numero])->comoBooleano();
            }
        }

        foreach ($perguntas as $numero) {
            if (array_key_exists((int) $numero, $input->respostas)) {
                return RespostaPlanilha::de($input->respostas[(int) $numero])->comoBooleano();
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TratamentoEnquadramento>  $linhas
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    private function escolherLinha($linhas, ?bool $noLocal, array $respostas = []): ?TratamentoEnquadramento
    {
        if ($linhas->count() === 1) {
            return $linhas->first();
        }

        $escritorio = $linhas->firstWhere('codigo_louos', '07.12.13');
        $outros = $linhas->reject(fn (TratamentoEnquadramento $l): bool => $l->codigo_louos === '07.12.13');

        if ($noLocal === false && $escritorio !== null) {
            return $escritorio;
        }

        if ($noLocal === true && $outros->isNotEmpty()) {
            return $this->escolherLinhaNoLocal($outros, $respostas);
        }

        return $escritorio ?? $linhas->first();
    }

    /**
     * @param  Collection<int, TratamentoEnquadramento>  $outros
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    private function escolherLinhaNoLocal($outros, array $respostas): ?TratamentoEnquadramento
    {
        if ($outros->count() === 1) {
            return $outros->first();
        }

        $artesanal = array_key_exists(3, $respostas) ? RespostaPlanilha::de($respostas[3])->comoBooleano() : null;

        // P3 = "modo artesanal?" (planilha de tratamento, regras 3/7/19/25/52/57/59):
        // SIM = artesanal → linha de serviço (07.09.xx, fora da família ID);
        // NÃO = produção em série/industrial → linha ID/CNLU/ALTO (09.xx).
        // Os predicados já estiveram invertidos (relatório SEDUR 21/09 — deferimento
        // indevido do 990006/2026): não trocar sem reler a planilha.
        if ($artesanal === true) {
            return $outros->first(
                fn (TratamentoEnquadramento $linha): bool => ! $this->eFamiliaId((string) $linha->subcategoria)
                    && ! $this->eCnlu((string) $linha->risco),
            ) ?? $outros->first();
        }

        if ($artesanal === false) {
            return $outros->first(
                fn (TratamentoEnquadramento $linha): bool => $this->eFamiliaId((string) $linha->subcategoria)
                    || $this->eCnlu((string) $linha->risco)
                    || str_contains(mb_strtoupper((string) $linha->risco), 'ALTO'),
            ) ?? $outros->first();
        }

        // Sem resposta que decida entre várias linhas "no local", o sistema NÃO
        // enquadra (relatório SEDUR 21/09, item 21 + decisão 22/09): null →
        // nao_resolvido → análise. Nunca a primeira linha do arquivo (o default
        // anterior assumia a linha ID/ALTO e chegou a indeferir o 990011).
        return null;
    }

    /**
     * Ramo curado da planilha (treatment_regra_ramos) para as regras do CNAE:
     * casa pergunta decisiva × resposta × faixa (1.250 m²) × tipo que dirige
     * regra; o mais específico vence. Null quando nada casa — o resolver cai
     * na heurística (degradação honesta, nunca inferência silenciosa).
     *
     * @param  Collection<int, TratamentoCnaeBinding>  $bindings
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    private function ramoCurado(Collection $bindings, array $respostas, ?float $area, bool $tipoDirige, int $versaoId, ContextoRamo $contexto): ?TratamentoRegraRamo
    {
        $regras = $bindings->pluck('regra')->filter()->unique()->values();

        if ($regras->isEmpty()) {
            return null;
        }

        $faixa = $area === null ? null : ($area <= 1250 ? 'ate_1250' : 'acima_1250');
        $perguntaVinculada = $bindings->pluck('perguntas')->flatten()->filter()->map(fn (mixed $n): int => (int) $n)->unique()->first();

        $ramos = TratamentoRegraRamo::query()
            ->where('rule_version_id', $versaoId)
            ->whereIn('regra', $regras)
            ->get();

        return $ramos
            ->filter(function (TratamentoRegraRamo $ramo) use ($respostas, $faixa, $tipoDirige, $perguntaVinculada, $contexto): bool {
                if ($ramo->condicoes !== null) {
                    return CondicaoRamo::atende($ramo->condicoes, $contexto);
                }

                // Ramo sem número de pergunta no texto (ex.: regra 50) casa com
                // a pergunta vinculada ao CNAE.
                $pergunta = $ramo->pergunta ?? $perguntaVinculada;

                if ($pergunta === null || ! array_key_exists($pergunta, $respostas)) {
                    return false;
                }

                if ($ramo->resposta !== null && $ramo->resposta !== RespostaPlanilha::de($respostas[$pergunta])->comoBooleano()) {
                    return false;
                }

                if ($ramo->faixa !== null && $ramo->faixa !== $faixa) {
                    return false;
                }

                if ($ramo->tipo_dirige !== null && $ramo->tipo_dirige !== $tipoDirige) {
                    return false;
                }

                return true;
            })
            // Mais específico vence: tipo que dirige regra pesa mais, depois a
            // pergunta explícita (ramo curado à mão), depois faixa e resposta.
            ->sortByDesc(fn (TratamentoRegraRamo $ramo): int => ($ramo->bloqueia ? 100 : 0)
                + ($ramo->condicoes !== null
                    ? 10 + CondicaoRamo::peso($ramo->condicoes)
                    : ($ramo->resposta !== null ? 1 : 0)
                        + ($ramo->faixa !== null ? 1 : 0)
                        + ($ramo->tipo_dirige !== null ? 2 : 0)
                        + ($ramo->pergunta !== null ? 2 : 0)))
            ->first();
    }

    /**
     * Regras 3, 7, 19 e 57: o corte de área do texto é 1.250 m². A linha
     * 07.09.17 traz 350 m²; abaixo disso o subgrupo não pode antecipar o nR2.
     */
    private function linhaComFaixaDaRegra(TratamentoEnquadramento $linha, ?TratamentoRegraRamo $ramo): TratamentoEnquadramento
    {
        if (! $this->regraUsaCorte1250($ramo) || $this->eFamiliaId((string) $linha->subcategoria)) {
            return $linha;
        }

        if ($linha->ate_m2 === null || (float) $linha->ate_m2 >= 1250 || ! $linha->enquadramento2) {
            return $linha;
        }

        $ajustada = clone $linha;
        $ajustada->ate_m2 = 1250;

        return $ajustada;
    }

    /**
     * Risco que o texto da regra manda, por cima da coluna da linha.
     * Regras 24/25: fora do ID é médio; ID é alto. Regras 3, 7, 19 e 57,
     * no artesanal e fora de galpão/container/residencial: baixo até 1.250 m²
     * e médio acima, com o fluxo expresso que o ramo curado já traz.
     */
    private function riscoDaRegra(string $risco, string $subgrupo, ?TratamentoRegraRamo $ramo, TratamentoRamoInput $input): string
    {
        if ($ramo === null) {
            return $risco;
        }

        $regra = (int) $ramo->regra;

        if (in_array($regra, [24, 25], true)) {
            return $this->eFamiliaId($subgrupo) ? 'alto' : 'medio';
        }

        if (! $this->regraUsaCorte1250($ramo) || $this->eFamiliaId($subgrupo) || $this->tipoDirige($input->tipoImovel)) {
            return $risco;
        }

        if ($input->areaUtilizada === null) {
            return $risco;
        }

        return $input->areaUtilizada > 1250 ? 'medio' : 'baixo';
    }

    private function regraUsaCorte1250(?TratamentoRegraRamo $ramo): bool
    {
        return $ramo !== null && in_array((int) $ramo->regra, [3, 7, 19, 57], true);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function aplicarFaixa(TratamentoEnquadramento $linha, ?float $area): array
    {
        $sub = $linha->subcategoria;
        $grupo = $linha->grupo;
        $risco = $this->normalizarRisco($linha->risco);

        if ($area !== null && $linha->enquadramento3 && $linha->acima_m2 !== null && $area >= (float) $linha->acima_m2) {
            $sub = $linha->enquadramento3;
            $grupo = $this->grupoDe($sub);
            $risco = 'alto';
        } elseif ($area !== null && $linha->ate_m2 !== null && $area > (float) $linha->ate_m2 && $linha->enquadramento2) {
            $sub = $linha->enquadramento2;
            $grupo = $this->grupoDe($sub);

            if ($risco === 'baixo') {
                $risco = 'medio';
            }
        }

        return [$sub, $grupo, $risco];
    }

    private function fluxoDe(string $codigoLouos, string $subgrupo, string $risco, ?bool $noLocal, ?TipoImovel $tipo): string
    {
        // O tipo que dirige regra (galpão/container/edificação residencial) vem
        // ANTES do atalho do escritório: pela planilha (regras 1, 5, 24, 51…),
        // "não no local" + esses tipos remete à crítica do analista
        // (semiexpresso), não ao expresso. Relatório SEDUR 21/09, item 01.
        if ($this->eFamiliaId($subgrupo) || ($tipo?->dirigeRegra() ?? false)) {
            return 'semiexpresso';
        }

        if ($codigoLouos === '07.12.13' && $noLocal === false) {
            return 'expresso';
        }

        if ($risco === 'alto') {
            return 'semiexpresso';
        }

        return 'expresso';
    }

    private function fluxoDaClassificacao(?string $classificacao): ?string
    {
        $texto = mb_strtoupper((string) $classificacao);

        if (str_contains($texto, 'SEMI')) {
            return 'semiexpresso';
        }

        if (str_contains($texto, 'EXPRESSO')) {
            return 'expresso';
        }

        return null;
    }

    private function naListaCnlu(string $subgrupo, int $versaoId): bool
    {
        $codigos = TratamentoCnluItem::query()
            ->where('rule_version_id', $versaoId)
            ->pluck('codigo');

        return CnluLista::contem($subgrupo, $codigos);
    }

    private function eCnlu(string $risco): bool
    {
        return str_contains(mb_strtoupper($risco), 'CNLU');
    }

    private function eFamiliaId(string $valor): bool
    {
        return (bool) preg_match('/^ID\d/i', $valor);
    }

    private function tipoDirige(?TipoImovel $tipo): bool
    {
        return $tipo !== null && $tipo->dirigeRegra();
    }

    /**
     * @return list<array{rotulo: string, texto: string}>
     */
    private function opcoesDaSubdivisao(string $cnaeFormatado, int $versaoId): array
    {
        $subdivisao = TratamentoEnquadramento::query()
            ->where('rule_version_id', $versaoId)
            ->where('cnae', $cnaeFormatado)
            ->get()
            ->map(fn (TratamentoEnquadramento $linha): ?string => $linha->metadados['subdivisao'] ?? null)
            ->first(fn (?string $valor): bool => filled($valor));

        return SubdivisaoCnae::opcoes($subdivisao);
    }

    /**
     * Pergunta 1 que manda escolher entre as opções do CNAE, mas a planilha
     * carregada não as traz (complemento da planilha não aplicado): a falha é
     * da carga, não do requerente, e o motivo diz isso.
     */
    private function subdivisaoNaoCarregada(int $numero, string $enunciado, string $cnae, int $versaoId): ?string
    {
        if ($numero !== 1 || preg_match('/identifique entre as op[cç][õo]es/iu', $enunciado) !== 1) {
            return null;
        }

        $formatado = $this->formatarCnae($cnae);

        if (PerguntaEnunciado::analisar($enunciado)['opcoes'] !== [] || $this->opcoesDaSubdivisao($formatado, $versaoId) !== []) {
            return null;
        }

        return $this->textos->render('tratamento.motivo.subdivisao_ausente', [':cnae' => $formatado]);
    }

    /**
     * Enunciado da pergunta da planilha, só a primeira linha: as opções de
     * resposta ficam de fora do motivo.
     */
    private function motivoPerguntaSemResposta(int $numero, int $versaoId): string
    {
        $texto = TratamentoPergunta::query()
            ->where('rule_version_id', $versaoId)
            ->where('numero', $numero)
            ->value('texto');

        $primeiraLinha = trim((string) strtok((string) $texto, "\n"));

        return $this->textos->render('tratamento.motivo.pergunta_sem_resposta', [
            ':numero' => (string) $numero,
            ':texto' => $primeiraLinha !== '' ? $primeiraLinha : "Pergunta {$numero}",
        ]);
    }

    /**
     * @param  Collection<int, TratamentoCnaeBinding>  $bindings
     */
    private function regraDependeDaCategoria(Collection $bindings, int $versaoId): bool
    {
        $regras = $bindings->pluck('regra')->filter()->unique()->values();

        if ($regras->isEmpty()) {
            return false;
        }

        return TratamentoRegraRamo::query()
            ->where('rule_version_id', $versaoId)
            ->whereIn('regra', $regras)
            ->whereNotNull('condicoes')
            ->get()
            ->contains(fn (TratamentoRegraRamo $ramo): bool => array_key_exists('categoria', (array) $ramo->condicoes));
    }

    private function motivoDoBloqueio(TratamentoRegraRamo $ramo, string $cnae): string
    {
        $chave = $ramo->acao['mensagem'] ?? null;

        if (! is_string($chave) || $chave === '') {
            return $this->textos->get('tratamento.motivo.ramo_bloqueado');
        }

        return $this->textos->render($chave, [':cnae' => $this->formatarCnae($cnae)]);
    }

    private function formatarCnae(string $cnae): string
    {
        $digitos = (string) preg_replace('/\D/', '', $cnae);

        return strlen($digitos) === 7
            ? substr($digitos, 0, 4).'-'.substr($digitos, 4, 1).'/'.substr($digitos, 5, 2)
            : $cnae;
    }

    private function motivoTipoAusente(?TipoImovel $tipo): ?string
    {
        if ($tipo === null || $tipo->reconhecimento === TipoImovelReconhecimento::Ausente) {
            return $this->textos->get('tratamento.motivo.tipo_ausente');
        }

        if ($tipo->reconhecimento === TipoImovelReconhecimento::Desconhecido) {
            return $this->textos->get('tratamento.motivo.tipo_desconhecido');
        }

        return null;
    }

    private function normalizarRisco(string $risco): string
    {
        $t = mb_strtolower($risco);

        if (str_contains($t, 'alto')) {
            return 'alto';
        }

        if (str_contains($t, 'médio') || str_contains($t, 'medio')) {
            return 'medio';
        }

        return 'baixo';
    }

    private function grupoDe(string $subcategoria): string
    {
        $pos = strrpos($subcategoria, '-');

        return $pos === false ? $subcategoria : substr($subcategoria, 0, $pos);
    }
}
