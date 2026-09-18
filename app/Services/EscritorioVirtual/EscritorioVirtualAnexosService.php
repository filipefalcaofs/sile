<?php

namespace App\Services\EscritorioVirtual;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Publicação versionada dos Anexos A/B do Decreto 35.062/2021 (escritório
 * virtual) pela retaguarda: o upload dos dois CSVs cai num rascunho nomeado
 * (a vigente fica intacta até a publicação — a guarda de "lista disponível"
 * do SedeAtividadesResolver nunca é violada no meio do caminho) e a
 * publicação exige quatro olhos via RuleVersionService (domínio sensível,
 * auditoria G7). Espelha LouosDraftService.
 */
class EscritorioVirtualAnexosService
{
    public function __construct(
        private RuleVersionService $ruleVersions,
        private EscritorioVirtualCnaeImportService $import,
        private AuditService $audit,
    ) {}

    /**
     * Abre (ou reusa) o rascunho nomeado e importa os dois CSVs PARA O
     * RASCUNHO — a vigente só muda na publicação. Linhas do rascunho ausentes
     * dos CSVs são removidas (o rascunho é descartável; a vigente, jamais).
     *
     * @return array{versao: RuleVersion, anexo_a: int, anexo_b: int}
     *
     * @throws DomainException quando o nome de versão colide com uma versão já publicada
     */
    public function importarParaRascunho(string $versao, string $fonte, string $csvAnexoA, string $csvAnexoB, int $autorId): array
    {
        return DB::transaction(function () use ($versao, $fonte, $csvAnexoA, $csvAnexoB, $autorId): array {
            $rascunho = $this->ruleVersions->openDraft(
                RuleDomain::AtividadesEscritorioVirtual,
                $versao,
                $fonte,
                $autorId,
            );

            // openDraft é idempotente por (domínio, versão): se o nome colidir
            // com uma versão JÁ PUBLICADA, recusa — histórico nunca é reescrito.
            if ($rascunho->status !== RuleVersionStatus::Rascunho) {
                throw new DomainException(
                    "A versão '{$versao}' já foi publicada e não pode ser reescrita — informe outro nome de versão.",
                );
            }

            // A importação SUBSTITUI o conteúdo do rascunho: linhas ausentes
            // dos CSVs novos são removidas (o rascunho é descartável).
            VirtualOfficeActivityCnae::query()->where('rule_version_id', $rascunho->id)->delete();

            $anexoA = $this->import->import($rascunho, $csvAnexoA, VirtualOfficeActivityCnae::ANEXO_A);
            $anexoB = $this->import->import($rascunho, $csvAnexoB, VirtualOfficeActivityCnae::ANEXO_B);

            $this->audit->log(
                logName: 'escritorio-virtual',
                event: 'importacao-lista-ev',
                description: "Importação dos Anexos A/B no rascunho {$rascunho->version} (escritório virtual)",
                properties: [
                    'versao' => $rascunho->version,
                    'fonte' => $fonte,
                    'anexo_a' => $anexoA['importados'],
                    'anexo_b' => $anexoB['importados'],
                ],
                subject: $rascunho,
                rulesVersion: $rascunho->version,
            );

            return [
                'versao' => $rascunho,
                'anexo_a' => $anexoA['importados'],
                'anexo_b' => $anexoB['importados'],
            ];
        });
    }

    /**
     * Diff rascunho × vigente por anexo (cnae_code). Sem vigente, tudo o que
     * está no rascunho é adição.
     *
     * @return array{A: array{adicionados: list<string>, removidos: list<string>}, B: array{adicionados: list<string>, removidos: list<string>}}
     */
    public function diffRascunho(RuleVersion $rascunho): array
    {
        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->first();

        $diff = [];

        foreach ([VirtualOfficeActivityCnae::ANEXO_A, VirtualOfficeActivityCnae::ANEXO_B] as $anexo) {
            $doRascunho = VirtualOfficeActivityCnae::query()
                ->where('rule_version_id', $rascunho->id)
                ->where('anexo', $anexo)
                ->orderBy('cnae_code')
                ->pluck('cnae_code');

            $daVigente = $vigente === null
                ? collect()
                : VirtualOfficeActivityCnae::query()
                    ->where('rule_version_id', $vigente->id)
                    ->where('anexo', $anexo)
                    ->pluck('cnae_code');

            $diff[$anexo] = [
                'adicionados' => $doRascunho->diff($daVigente)->values()->all(),
                'removidos' => $daVigente->diff($doRascunho)->sort()->values()->all(),
            ];
        }

        return $diff;
    }

    /**
     * Publica via RuleVersionService (quatro olhos) e audita com os contadores
     * do diff por anexo.
     *
     * @throws DomainException quando a versão não é rascunho do domínio
     * @throws FourEyesViolationException quando publicador = autor
     */
    public function publicar(RuleVersion $rascunho, int $publicadorId): RuleVersion
    {
        if ($rascunho->domain !== RuleDomain::AtividadesEscritorioVirtual
            || $rascunho->status !== RuleVersionStatus::Rascunho) {
            throw new DomainException(
                "A versão '{$rascunho->version}' não é um rascunho dos Anexos de escritório virtual.",
            );
        }

        $diff = $this->diffRascunho($rascunho);

        $publicada = $this->ruleVersions->publish($rascunho, $publicadorId);

        $this->audit->log(
            logName: 'escritorio-virtual',
            event: 'publicacao-lista-ev',
            description: "Rascunho {$publicada->version} dos Anexos A/B publicado (escritório virtual)",
            properties: [
                'versao' => $publicada->version,
                'diff' => [
                    'A' => [
                        'adicionados' => count($diff[VirtualOfficeActivityCnae::ANEXO_A]['adicionados']),
                        'removidos' => count($diff[VirtualOfficeActivityCnae::ANEXO_A]['removidos']),
                    ],
                    'B' => [
                        'adicionados' => count($diff[VirtualOfficeActivityCnae::ANEXO_B]['adicionados']),
                        'removidos' => count($diff[VirtualOfficeActivityCnae::ANEXO_B]['removidos']),
                    ],
                ],
            ],
            subject: $publicada,
            rulesVersion: $publicada->version,
        );

        return $publicada;
    }
}
