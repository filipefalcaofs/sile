<?php

namespace App\Services\Tratamento;

use App\Enums\RuleDomain;
use App\Enums\TipoImovelReconhecimento;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoCnluItem;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
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
     * @return list<array{numero: int, texto: string}>
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

        return $numeros->map(fn (int $numero): array => [
            'numero' => $numero,
            'texto' => (string) ($textos->get($numero)?->texto ?? "Pergunta {$numero}"),
        ])->all();
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

        foreach ($perguntas as $numero) {
            if (! array_key_exists((int) $numero, $input->respostas)) {
                // A P3 só é exibida quando a P2 é SIM (texto das regras da
                // família 52: "Se SIM na pergunta 2, exibir a pergunta 3").
                // Com P2=NÃO ela não se aplica e não pode travar a resolução
                // (pendência espúria do relatório SEDUR 21/09, item 25).
                if ((int) $numero === 3 && ($input->respostas[2] ?? null) === false) {
                    continue;
                }

                return new TratamentoRamoResult(
                    status: 'nao_resolvido',
                    motivo: $this->motivoPerguntaSemResposta((int) $numero, (int) $versao->getKey()),
                    versaoRegra: $versao->version,
                );
            }
        }

        $enquadramentos = TratamentoEnquadramento::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('cnae', $input->cnae)
            ->get();

        $noLocal = $this->atividadeNoLocal($input, $perguntas);
        $tipoDirige = $this->tipoDirige($input->tipoImovel);

        // O fluxo do ramo é dado curado dos textos oficiais das regras
        // (treatment_regra_ramos) — quando o ramo traz código LOUOS, ele manda
        // na linha (relatório SEDUR 21/09, item 18); quando traz só o fluxo,
        // a linha segue a seleção atual e o fluxo vence a heurística.
        $ramoCurado = $this->ramoCurado($bindings, $input->respostas, $input->areaUtilizada, $tipoDirige, (int) $versao->getKey());

        $linha = null;

        if ($ramoCurado !== null && $ramoCurado->codigo_louos !== null && $ramoCurado->codigo_louos !== '') {
            $linha = $enquadramentos->firstWhere('codigo_louos', $ramoCurado->codigo_louos);
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
                motivo: $this->textos->get('tratamento.motivo.ramo_bloqueado'),
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

        $fluxo = $ramoCurado?->fluxo ?? $this->fluxoDaClassificacao($linha->classificacao) ?? $this->fluxoDe($linha->codigo_louos, $subgrupo, $risco, $noLocal, $input->tipoImovel);

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
        );
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
                return (bool) $input->respostas[$numero];
            }
        }

        foreach ($perguntas as $numero) {
            if (array_key_exists((int) $numero, $input->respostas)) {
                return (bool) $input->respostas[(int) $numero];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TratamentoEnquadramento>  $linhas
     * @param  array<int, bool>  $respostas
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
     * @param  array<int, bool>  $respostas
     */
    private function escolherLinhaNoLocal($outros, array $respostas): ?TratamentoEnquadramento
    {
        if ($outros->count() === 1) {
            return $outros->first();
        }

        $artesanal = array_key_exists(3, $respostas) ? (bool) $respostas[3] : null;

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
     * @param  array<int, bool>  $respostas
     */
    private function ramoCurado(Collection $bindings, array $respostas, ?float $area, bool $tipoDirige, int $versaoId): ?TratamentoRegraRamo
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
            ->filter(function (TratamentoRegraRamo $ramo) use ($respostas, $faixa, $tipoDirige, $perguntaVinculada): bool {
                // Ramo sem número de pergunta no texto (ex.: regra 50) casa com
                // a pergunta vinculada ao CNAE.
                $pergunta = $ramo->pergunta ?? $perguntaVinculada;

                if ($pergunta === null || ! array_key_exists($pergunta, $respostas)) {
                    return false;
                }

                if ($ramo->resposta !== null && $ramo->resposta !== (bool) $respostas[$pergunta]) {
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
            ->sortByDesc(fn (TratamentoRegraRamo $ramo): int => ($ramo->resposta !== null ? 1 : 0)
                + ($ramo->faixa !== null ? 1 : 0)
                + ($ramo->tipo_dirige !== null ? 2 : 0)
                + ($ramo->pergunta !== null ? 2 : 0))
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
