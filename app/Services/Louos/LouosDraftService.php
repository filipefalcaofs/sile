<?php

namespace App\Services\Louos;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Models\Zona;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * CRUD de linhas dos Quadros da LOUOS sobre um rascunho versionado (HU-046).
 * A versão vigente nunca é tocada; toda edição ocorre no rascunho coexistente;
 * a publicação exige quatro olhos via RuleVersionService e audita o diff.
 */
final class LouosDraftService
{
    /** @var array<string, RuleDomain> */
    public const QUADRO_DOMAINS = [
        'quadro10' => RuleDomain::LouosQuadro10,
        'quadro11a' => RuleDomain::LouosQuadro11a,
    ];

    public function __construct(
        private RuleVersionService $ruleVersionService,
        private LouosQuadroCopier $copier,
        private AuditService $audit,
        private LouosQuadro10ImportService $quadro10Import,
        private LouosQuadro11ImportService $quadro11Import,
    ) {}

    /**
     * Retorna o rascunho aberto mais recente para o domínio, ou null.
     */
    public function rascunhoAberto(RuleDomain $domain): ?RuleVersion
    {
        return RuleVersion::query()
            ->where('domain', $domain->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->latest()
            ->first();
    }

    /**
     * Retorna o rascunho aberto se já existir (idempotente) ou abre um novo
     * copiando as linhas da vigente. `$version` é obrigatória na abertura.
     *
     * @throws InvalidArgumentException quando $version é nula e não há rascunho
     */
    public function abrirOuRetomar(RuleDomain $domain, ?string $version, int $userId): RuleVersion
    {
        return DB::transaction(function () use ($domain, $version, $userId) {
            $existing = RuleVersion::query()
                ->where('domain', $domain->value)
                ->where('status', RuleVersionStatus::Rascunho->value)
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($version === null) {
                throw new InvalidArgumentException('Versão obrigatória para abertura de novo rascunho.');
            }

            $vigente = RuleVersion::vigente($domain)->first();

            $draft = $this->ruleVersionService->openDraft($domain, $version, 'rascunho-editavel', $userId);

            $this->copier->copy($domain, $vigente, $draft);

            $this->audit->log(
                logName: 'louos',
                event: 'rascunho-aberto',
                description: "Rascunho aberto para o domínio {$domain->label()} — versão {$version}",
                properties: ['dominio' => $domain->value, 'versao' => $version],
                subject: $draft,
            );

            return $draft;
        });
    }

    /**
     * Insere uma linha na tabela tipada do rascunho. Rejeita colisão de chave
     * natural com ValidationException.
     *
     * @param  array<string, mixed>  $dados
     *
     * @throws ValidationException quando a chave natural já existe no rascunho
     */
    public function inserirLinha(RuleVersion $draft, array $dados): Model
    {
        $this->assertDraft($draft);

        $dados = $this->normalize($draft->domain, $dados);

        if ($this->existsByKey($draft->domain, $draft->id, $dados)) {
            throw ValidationException::withMessages(['linha' => 'Já existe uma linha com esta chave no rascunho.']);
        }

        $modelClass = $this->modelClass($draft->domain);
        /** @var Model $linha */
        $linha = $modelClass::query()->create(array_merge(['rule_version_id' => $draft->id], $dados));

        $this->audit->log(
            logName: 'louos',
            event: 'rascunho-linha-inserida',
            description: "Linha inserida no rascunho do domínio {$draft->domain->label()}",
            properties: ['dominio' => $draft->domain->value, 'chave' => $this->naturalKey($draft->domain, $dados)],
            subject: $linha,
        );

        return $linha;
    }

    /**
     * Altera uma linha do rascunho pelo id. Rejeita colisão de chave natural
     * com outra linha existente (exceto a própria).
     *
     * @param  array<string, mixed>  $dados
     *
     * @throws ModelNotFoundException quando a linha não pertence a este rascunho
     * @throws ValidationException quando a nova chave colide com outra linha
     */
    public function alterarLinha(RuleVersion $draft, int $linhaId, array $dados): Model
    {
        $this->assertDraft($draft);

        $dados = $this->normalize($draft->domain, $dados);
        $modelClass = $this->modelClass($draft->domain);

        /** @var Model $linha */
        $linha = $modelClass::query()
            ->where('rule_version_id', $draft->id)
            ->findOrFail($linhaId);

        if ($this->existsByKey($draft->domain, $draft->id, $dados, $linhaId)) {
            throw ValidationException::withMessages(['linha' => 'Já existe uma linha com esta chave no rascunho.']);
        }

        $linha->update(array_diff_key($dados, ['rule_version_id' => true]));

        $this->audit->log(
            logName: 'louos',
            event: 'rascunho-linha-alterada',
            description: "Linha alterada no rascunho do domínio {$draft->domain->label()}",
            properties: ['dominio' => $draft->domain->value, 'id' => $linhaId, 'chave' => $this->naturalKey($draft->domain, $dados)],
            subject: $linha,
        );

        return $linha;
    }

    /**
     * Exclui uma linha do rascunho pelo id.
     *
     * @throws ModelNotFoundException quando a linha não pertence a este rascunho
     */
    public function excluirLinha(RuleVersion $draft, int $linhaId): void
    {
        $this->assertDraft($draft);

        $modelClass = $this->modelClass($draft->domain);

        /** @var Model $linha */
        $linha = $modelClass::query()
            ->where('rule_version_id', $draft->id)
            ->findOrFail($linhaId);

        $linha->delete();

        $this->audit->log(
            logName: 'louos',
            event: 'rascunho-linha-excluida',
            description: "Linha excluída do rascunho do domínio {$draft->domain->label()}",
            properties: ['dominio' => $draft->domain->value, 'id' => $linhaId],
            subject: $draft,
        );
    }

    /**
     * Descarta o rascunho: remove as linhas tipadas e o cabeçalho RuleVersion.
     * A vigente é preservada intacta.
     */
    public function descartar(RuleVersion $draft): void
    {
        $this->assertDraft($draft);

        $domain = $draft->domain;
        $version = $draft->version;
        $modelClass = $this->modelClass($domain);

        $modelClass::query()->where('rule_version_id', $draft->id)->delete();
        $draft->delete();

        $this->audit->log(
            logName: 'louos',
            event: 'rascunho-descartado',
            description: "Rascunho descartado do domínio {$domain->label()} — versão {$version}",
            properties: ['dominio' => $domain->value, 'versao' => $version],
        );
    }

    /**
     * Compara rascunho × vigente por chave natural e retorna contagens.
     *
     * @return array{novas: int, alteradas: int, excluidas: int}
     */
    public function diff(RuleVersion $draft): array
    {
        $this->assertDraft($draft);

        $domain = $draft->domain;
        $modelClass = $this->modelClass($domain);
        $vigente = RuleVersion::vigente($domain)->first();

        $rascunhoLinhas = $modelClass::query()->where('rule_version_id', $draft->id)->get();
        $rascunhoIndex = [];

        foreach ($rascunhoLinhas as $linha) {
            $rascunhoIndex[$this->naturalKey($domain, $linha->toArray())] = $linha;
        }

        if ($vigente === null) {
            return ['novas' => count($rascunhoIndex), 'alteradas' => 0, 'excluidas' => 0];
        }

        $vigenteLinhas = $modelClass::query()->where('rule_version_id', $vigente->id)->get();
        $vigenteIndex = [];

        foreach ($vigenteLinhas as $linha) {
            $vigenteIndex[$this->naturalKey($domain, $linha->toArray())] = $linha;
        }

        $novas = 0;
        $alteradas = 0;
        $excluidas = 0;

        foreach ($rascunhoIndex as $chave => $linha) {
            if (! isset($vigenteIndex[$chave])) {
                $novas++;
            } elseif ($this->payloadDiferente($linha, $vigenteIndex[$chave])) {
                $alteradas++;
            }
        }

        foreach (array_keys($vigenteIndex) as $chave) {
            if (! isset($rascunhoIndex[$chave])) {
                $excluidas++;
            }
        }

        return compact('novas', 'alteradas', 'excluidas');
    }

    /**
     * Delega a importação do CSV ao import service do domínio e audita.
     *
     * @return array<string, mixed> relatório do import service
     */
    public function importarCsv(RuleVersion $draft, string $csvPath, ?string $nomeOriginal = null, bool $substituir = false): array
    {
        $this->assertDraft($draft);

        $domain = $draft->domain;

        if ($substituir) {
            $this->modelClass($domain)::query()->where('rule_version_id', $draft->id)->delete();
        }

        $service = match ($domain) {
            RuleDomain::LouosQuadro10 => $this->quadro10Import,
            RuleDomain::LouosQuadro11a => $this->quadro11Import,
            default => throw new DomainException("Import não suportado para o domínio {$domain->value}."),
        };

        $relatorio = $service->import($draft, $csvPath);

        $this->audit->log(
            logName: 'louos',
            event: 'rascunho-importacao',
            description: "Importação CSV no rascunho do domínio {$domain->label()}",
            properties: array_merge($relatorio, [
                'arquivo' => $nomeOriginal ?? basename($csvPath),
                'substituir' => $substituir,
            ]),
            subject: $draft,
        );

        return $relatorio;
    }

    /**
     * Publica o rascunho via RuleVersionService (quatro olhos enforced) e
     * audita o diff na trilha `louos`.
     *
     * @throws FourEyesViolationException quando publicador = autor
     * @throws DomainException quando o rascunho do Quadro 10 referencia zona
     *                         ausente do cadastro de zonas ativas
     */
    public function publicar(RuleVersion $draft, int $publisherId): RuleVersion
    {
        $this->assertDraft($draft);
        $this->assertZonasCadastradas($draft);

        $diffResult = $this->diff($draft);

        $published = $this->ruleVersionService->publish($draft, $publisherId);

        $this->audit->log(
            logName: 'louos',
            event: 'rascunho-publicado',
            description: "Rascunho publicado do domínio {$draft->domain->label()} — versão {$draft->version}",
            properties: [
                'dominio' => $draft->domain->value,
                'versao' => $draft->version,
                'diff' => $diffResult,
            ],
            subject: $published,
        );

        return $published;
    }

    /**
     * Borda de publicação do Quadro 10 (parametrização 3.3): a coluna `zona`
     * é string livre e um typo publicado vira `nao_encontrado` no motor,
     * degradando CADA processo daquela zona para pendente — silenciosamente.
     * Toda zona do rascunho precisa constar do cadastro de zonas ATIVAS. A
     * guarda é exclusiva do Quadro 10 e NÃO toca a vigente: zona desativada
     * permanece nos quadros históricos e só bloqueia publicação nova.
     * Seeders publicam por RuleVersionService (fora deste caminho), sem
     * problema de bootstrap.
     *
     * @throws DomainException listando as zonas ausentes do cadastro ativo
     */
    private function assertZonasCadastradas(RuleVersion $draft): void
    {
        if ($draft->domain !== RuleDomain::LouosQuadro10) {
            return;
        }

        $zonasRascunho = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $draft->id)
            ->distinct()
            ->pluck('zona');

        if ($zonasRascunho->isEmpty()) {
            return;
        }

        $cadastradas = Zona::query()
            ->ativas()
            ->whereIn('codigo', $zonasRascunho)
            ->pluck('codigo');

        $ausentes = $zonasRascunho->diff($cadastradas)->sort()->values();

        if ($ausentes->isNotEmpty()) {
            throw new DomainException(
                'Publicação bloqueada: as zonas '.$ausentes->implode(', ')
                .' não constam do cadastro de zonas ativas. Cadastre-as ou reative-as em Gestão > Zonas antes de publicar o Quadro 10.'
            );
        }
    }

    /**
     * Guarda comum: valida que o RuleVersion é um rascunho de domínio suportado.
     *
     * @throws DomainException
     */
    private function assertDraft(RuleVersion $draft): void
    {
        if ($draft->status !== RuleVersionStatus::Rascunho) {
            throw new DomainException(
                "A versão '{$draft->version}' não é um rascunho (status: {$draft->status->value})."
            );
        }

        if (! in_array($draft->domain, self::QUADRO_DOMAINS, true)) {
            throw new DomainException(
                "O domínio '{$draft->domain->value}' não é um Quadro da LOUOS suportado pelo LouosDraftService."
            );
        }
    }

    /**
     * Resolve a classe de model para o domínio informado.
     *
     * @return class-string<Model>
     */
    private function modelClass(RuleDomain $domain): string
    {
        return match ($domain) {
            RuleDomain::LouosQuadro10 => LouosQuadro10Permissao::class,
            RuleDomain::LouosQuadro11a => LouosQuadro11CondicaoVia::class,
            default => throw new DomainException("Domínio {$domain->value} não mapeado para model."),
        };
    }

    /**
     * Calcula a chave natural de uma linha para comparação cross-version.
     *
     * @param  array<string, mixed>  $dados
     */
    private function naturalKey(RuleDomain $domain, array $dados): string
    {
        return match ($domain) {
            RuleDomain::LouosQuadro10 => ($dados['zona'] ?? '')
                .'|'.($dados['grupo_uso'] ?? '')
                .'|'.(string) ($dados['subgrupo'] ?? ''),

            RuleDomain::LouosQuadro11a => ($dados['classe_via'] ?? '')
                .'|'.(string) ($dados['grupo_uso'] ?? ''),

            default => throw new DomainException("Chave natural não definida para o domínio {$domain->value}."),
        };
    }

    /**
     * Normaliza os dados de entrada antes de persistir.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normalize(RuleDomain $domain, array $dados): array
    {
        return match ($domain) {
            RuleDomain::LouosQuadro10 => array_merge($dados, [
                'grupo_uso' => (string) ($dados['grupo_uso'] ?? ''),
                'subgrupo' => (string) ($dados['subgrupo'] ?? ''),
            ]),
            RuleDomain::LouosQuadro11a => array_merge($dados, [
                'grupo_uso' => (string) ($dados['grupo_uso'] ?? ''),
                'condicoes' => $dados['condicoes'] ?? null,
            ]),
            default => $dados,
        };
    }

    /**
     * Verifica se já existe uma linha com a mesma chave natural no rascunho.
     * Se `$excludeId` for fornecido, ignora essa linha (para alterações).
     *
     * @param  array<string, mixed>  $dados  (já normalizados)
     */
    private function existsByKey(RuleDomain $domain, int $versionId, array $dados, ?int $excludeId = null): bool
    {
        $modelClass = $this->modelClass($domain);
        $query = $modelClass::query()->where('rule_version_id', $versionId);

        match ($domain) {
            RuleDomain::LouosQuadro10 => $query
                ->where('zona', $dados['zona'])
                ->where('grupo_uso', $dados['grupo_uso'])
                ->where('subgrupo', $dados['subgrupo']),

            RuleDomain::LouosQuadro11a => $query
                ->where('classe_via', $dados['classe_via'])
                ->where('grupo_uso', $dados['grupo_uso']),

            default => throw new DomainException("Domínio {$domain->value} não suportado."),
        };

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Compara o payload de duas linhas da mesma chave natural em versões
     * diferentes. Exclui id, rule_version_id e timestamps da comparação.
     */
    private function payloadDiferente(Model $rascunho, Model $vigente): bool
    {
        $exclude = array_flip(['id', 'rule_version_id', 'created_at', 'updated_at']);

        $a = array_diff_key($rascunho->toArray(), $exclude);
        $b = array_diff_key($vigente->toArray(), $exclude);

        return $a !== $b;
    }
}
