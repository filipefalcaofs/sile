<?php

namespace App\Services\Renovacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ViabilityRequest;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Cnpj\CnpjNotFoundException;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * Diz se uma TVL do sistema legado pode ser renovada pela empresa informada.
 * A primeira regra que falha devolve o motivo; sem CNPJ gravado no legado, a
 * titularidade é conferida pelo cadastro do CNPJ e pelo endereço do imóvel da
 * inscrição imobiliária da TVL e, se alguma consulta falhar, o pedido é bloqueado.
 */
class RenovacaoLegadaElegibilidadeService
{
    public function __construct(
        private readonly CnpjLookup $cnpjLookup,
        private readonly ConferenciaTitularidade $conferenciaTitularidade,
        private readonly ResolvedorEnderecoImovel $resolvedorEnderecoImovel,
    ) {}

    public function avaliar(TvlLegadaDetalhe $tvl, Company $empresa): RenovacaoElegibilidade
    {
        $titularidade = $this->titularidade($tvl, $empresa);

        if (! $titularidade->titular) {
            return new RenovacaoElegibilidade(false, $titularidade->motivo);
        }

        return $this->avaliarAposTitularidade($tvl);
    }

    /**
     * Regras posteriores à titularidade, para quem já a conferiu.
     */
    public function avaliarAposTitularidade(TvlLegadaDetalhe $tvl): RenovacaoElegibilidade
    {
        $motivo = $this->motivoTipo($tvl)
            ?? $this->motivoAtividades($tvl)
            ?? $this->motivoRenovadaNoLegado($tvl)
            ?? $this->motivoVencida($tvl)
            ?? RenovacaoElegibilidadeService::motivoForaDaJanela($tvl->validade)
            ?? $this->motivoRenovacaoNoViabiliza($tvl);

        return new RenovacaoElegibilidade($motivo === null, $motivo);
    }

    public function titularidade(TvlLegadaDetalhe $tvl, Company $empresa): ResultadoTitularidade
    {
        $cnpjEmpresa = $this->digitos($empresa->cnpj);
        $cnpjGravado = $this->digitos($tvl->titularDocumento);

        if (strlen($cnpjGravado) === 14) {
            return $cnpjGravado === $cnpjEmpresa
                ? ResultadoTitularidade::titular()
                : ResultadoTitularidade::outroTitular('Esta viabilidade pertence a outra empresa.');
        }

        try {
            $dados = $this->cnpjLookup->lookup($cnpjEmpresa);
        } catch (CnpjNotFoundException|CnpjLookupException) {
            return ResultadoTitularidade::consultaIndisponivel('Não foi possível conferir o CNPJ agora. Tente novamente em instantes.');
        }

        try {
            $conferencia = $this->conferenciaTitularidade->conferir(
                $tvl,
                $dados,
                fn (): EnderecoDoImovel => $this->resolvedorEnderecoImovel->resolver($tvl->inscricaoImobiliaria),
            );
        } catch (EnderecoImovelIndisponivelException) {
            return ResultadoTitularidade::consultaIndisponivel(ResolvedorEnderecoImovel::M_INDISPONIVEL);
        }

        return $conferencia->confere
            ? ResultadoTitularidade::titular()
            : ResultadoTitularidade::outroTitular((string) $conferencia->motivo);
    }

    private function motivoTipo(TvlLegadaDetalhe $tvl): ?string
    {
        return $tvl->tipo === 'provisoria' && $tvl->deferida
            ? null
            : 'Somente viabilidade provisória deferida pode ser renovada.';
    }

    private function motivoAtividades(TvlLegadaDetalhe $tvl): ?string
    {
        if ($tvl->atividades === []) {
            return 'A viabilidade anterior não tem atividades registradas.';
        }

        foreach ($tvl->atividades as $atividade) {
            $existe = Cnae::query()
                ->where('code', $this->digitos($atividade['codigo']))
                ->where('active', true)
                ->exists();

            if (! $existe) {
                return "A atividade {$atividade['codigo']} não consta mais do catálogo. Procure a SEDUR.";
            }
        }

        return null;
    }

    private function motivoRenovadaNoLegado(TvlLegadaDetalhe $tvl): ?string
    {
        return $this->renovadaNaJanelaDaVigente($tvl) ? 'Esta viabilidade já foi renovada.' : null;
    }

    /**
     * O legado só informa a validade da linha vigente e as renovações. Como a renovação só
     * se pede a partir da abertura da janela, um pedido anterior a ela gerou a linha vigente
     * (ou uma anterior) e não conta; o posterior já é renovação desta vigência. Sem validade
     * (ou sem data no pedido) vale o registro.
     */
    private function renovadaNaJanelaDaVigente(TvlLegadaDetalhe $tvl): bool
    {
        $abertura = $tvl->validade === null ? null : RenovacaoElegibilidadeService::aberturaDaJanela($tvl->validade);

        foreach ($tvl->renovacoes as $renovacao) {
            $solicitadaEm = $renovacao['solicitada_em'] === null ? null : CarbonImmutable::parse($renovacao['solicitada_em']);

            if ($abertura === null || $solicitadaEm === null || $solicitadaEm->greaterThanOrEqualTo($abertura)) {
                return true;
            }
        }

        return false;
    }

    private function motivoVencida(TvlLegadaDetalhe $tvl): ?string
    {
        if ($tvl->situacao === 'vencida' && ! (bool) Settings::get('renovacao.legado.permite_vencida', true)) {
            return 'Viabilidade vencida não pode ser renovada.';
        }

        return null;
    }

    private function motivoRenovacaoNoViabiliza(TvlLegadaDetalhe $tvl): ?string
    {
        $renovacoes = fn () => ViabilityRequest::query()->where('renews_legacy_tvl_number', $tvl->numero);

        if ($renovacoes()->where('status', ViabilityRequestStatus::Deferida)->exists()) {
            $meses = max(1, (int) Settings::get('renovacao.intervalo_meses', 3));

            $recente = $renovacoes()
                ->where('status', ViabilityRequestStatus::Deferida)
                ->whereHas('decision', fn ($query) => $query->where('decided_at', '>=', now()->subMonths($meses)))
                ->exists();

            return $recente
                ? "Esta viabilidade foi renovada há menos de {$meses} meses."
                : 'Esta viabilidade já foi renovada.';
        }

        $emAberto = $renovacoes()
            ->whereIn('status', [
                ViabilityRequestStatus::Protocolada,
                ViabilityRequestStatus::EmAnalise,
                ViabilityRequestStatus::EmPendencia,
            ])
            ->exists();

        return $emAberto ? RenovacaoElegibilidadeService::MOTIVO_EM_ANALISE : null;
    }

    private function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', $valor ?? '') ?? '';
    }
}
