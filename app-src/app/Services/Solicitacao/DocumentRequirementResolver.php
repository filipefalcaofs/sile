<?php

namespace App\Services\Solicitacao;

use App\Models\DocumentRequirement;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolução dos documentos obrigatórios da solicitação (HU-067).
 *
 * Os obrigatórios são a UNIÃO de duas fontes:
 *  1. requisitos por-CNAE: document_requirements (required + ativos) vinculados,
 *     via cnae_document_requirement, a algum CNAE da solicitação (08-04);
 *  2. condicionais BASE conhecidos: foto da fachada SEMPRE; termo de concessão
 *     de uso SE a área é pública (is_public_area, gravado no 08-06).
 *
 * A tabela por-CNAE nasce VAZIA (planilha oficial pendente SEDUR) — o resolver
 * degrada honesto: sem vínculo por-CNAE, ainda valida os obrigatórios-base. Os
 * condicionais base são DocumentRequirements seedados com códigos estáveis
 * (08-16); referenciados aqui por code, são ignorados quando ausentes/inativos
 * no ambiente (nunca inventa um requisito que não está cadastrado).
 *
 * missing() é a base do bloqueio do protocolo (08-10) e do aviso ao requerente.
 */
class DocumentRequirementResolver
{
    /**
     * Código estável do requisito condicional "foto da fachada" (sempre
     * obrigatório). Seedado no 08-16.
     */
    public const CODE_FACHADA = 'foto-fachada';

    /**
     * Código estável do requisito condicional "termo de concessão de uso"
     * (obrigatório quando o imóvel está em área pública). Seedado no 08-16.
     */
    public const CODE_CONCESSAO = 'termo-concessao';

    /**
     * Requisitos obrigatórios da solicitação: união dos requisitos por-CNAE
     * (required + ativos) com os condicionais base, sem duplicar.
     *
     * @return Collection<int, DocumentRequirement>
     */
    public function required(ViabilityRequest $request): Collection
    {
        $cnaeIds = $request->cnaes()->pluck('cnaes.id')->all();

        $porCnae = DocumentRequirement::query()
            ->where('active', true)
            ->where('required', true)
            ->whereHas('cnaes', fn ($query) => $query->whereIn('cnaes.id', $cnaeIds))
            ->get();

        $base = $this->baseConditionals($request);

        // União por id (o requisito compartilhado por vários CNAEs entra uma vez;
        // um condicional base já trazido por vínculo de CNAE não duplica).
        return $porCnae->concat($base)->unique('id')->values();
    }

    /**
     * Obrigatórios ainda não atendidos: required() menos os requisitos já
     * cobertos por um anexo (por requirement_id). Base do bloqueio do protocolo.
     *
     * @return Collection<int, DocumentRequirement>
     */
    public function missing(ViabilityRequest $request): Collection
    {
        $coveredIds = $request->documents()
            ->whereNotNull('requirement_id')
            ->pluck('requirement_id')
            ->all();

        return $this->required($request)
            ->reject(fn (DocumentRequirement $requirement) => in_array($requirement->id, $coveredIds, true))
            ->values();
    }

    /**
     * Condicionais base conhecidos por código: fachada SEMPRE; concessão SE área
     * pública. Degrada honesto — só retorna os que existem e estão ativos.
     *
     * @return Collection<int, DocumentRequirement>
     */
    private function baseConditionals(ViabilityRequest $request): Collection
    {
        $codes = [self::CODE_FACHADA];

        if ($request->is_public_area) {
            $codes[] = self::CODE_CONCESSAO;
        }

        return DocumentRequirement::query()
            ->where('active', true)
            ->whereIn('code', $codes)
            ->get();
    }
}
