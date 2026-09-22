<?php

namespace App\Services\Tratamento;

use App\Enums\RuleDomain;
use App\Enums\TipoImovelReconhecimento;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoEnquadramento;
use App\Services\Risco\TipoImovel;
use Illuminate\Support\Collection;

class TratamentoRamoResolver
{
    public function resolver(TratamentoRamoInput $input): TratamentoRamoResult
    {
        $versao = $this->resolverVersao($input);

        if ($versao === null) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: 'Planilha de tratamento sem versão vigente',
            );
        }

        $bindings = TratamentoCnaeBinding::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('cnae', $input->cnae)
            ->get();

        if ($bindings->isEmpty()) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: 'CNAE sem regra de tratamento vigente',
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
                return new TratamentoRamoResult(
                    status: 'nao_resolvido',
                    motivo: "Pergunta {$numero} sem resposta",
                    versaoRegra: $versao->version,
                );
            }
        }

        $enquadramentos = TratamentoEnquadramento::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('cnae', $input->cnae)
            ->get();

        $noLocal = $this->atividadeNoLocal($input, $perguntas);
        $linha = $this->escolherLinha($enquadramentos, $noLocal, $input->respostas);

        if ($linha === null) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: 'CNAE sem enquadramento no ramo escolhido',
                versaoRegra: $versao->version,
            );
        }

        if ($this->eCnlu($linha->risco)) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                fluxo: 'analise',
                motivo: 'A SER DEFINIDO PELA CNLU',
                codigoLouos: $linha->codigo_louos,
                grupo: $linha->grupo,
                subgrupo: $linha->subcategoria,
                versaoRegra: $versao->version,
            );
        }

        if ($linha->ate_m2 !== null && $input->areaUtilizada === null) {
            return new TratamentoRamoResult(
                status: 'nao_resolvido',
                motivo: 'Área utilizada ausente para o ramo que depende de faixa',
                versaoRegra: $versao->version,
            );
        }

        [$subgrupo, $grupo, $risco] = $this->aplicarFaixa($linha, $input->areaUtilizada);

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

        $fluxo = $this->fluxoDe($linha->codigo_louos, $subgrupo, $risco, $noLocal, $input->tipoImovel);

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
    private function escolherLinhaNoLocal($outros, array $respostas): TratamentoEnquadramento
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

        return $outros->first();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function aplicarFaixa(TratamentoEnquadramento $linha, ?float $area): array
    {
        $sub = $linha->subcategoria;
        $grupo = $linha->grupo;
        $risco = $this->normalizarRisco($linha->risco);

        if ($area !== null && $linha->ate_m2 !== null && $area > (float) $linha->ate_m2 && $linha->enquadramento2) {
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

    private function motivoTipoAusente(?TipoImovel $tipo): ?string
    {
        if ($tipo === null || $tipo->reconhecimento === TipoImovelReconhecimento::Ausente) {
            return 'Tipo de imóvel ausente para o ramo que depende do tipo';
        }

        if ($tipo->reconhecimento === TipoImovelReconhecimento::Desconhecido) {
            return 'Tipo de imóvel desconhecido para o ramo que depende do tipo';
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
