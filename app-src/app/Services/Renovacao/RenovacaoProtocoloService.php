<?php

namespace App\Services\Renovacao;

use App\Enums\AnalysisCategory;
use App\Enums\CompanySource;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cidadao;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\Sector;
use App\Models\ServiceDeclaration;
use App\Models\ServiceDeclarationAcceptance;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Analise\EnviarParaAnaliseService;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Cnpj\CnpjNotFoundException;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\ProtocolNumberGenerator;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use DomainException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Abre e protocola a renovação de um TVL provisório. Copia endereço e
 * atividades da origem (não são editáveis), completa o território como no
 * protocolo comum (inscrição, CODLOG, zona, via, endereçamento e lote) e
 * manda o processo para análise semi-expressa já com a ficha do motor.
 * Não passa pelo deferimento automático.
 */
class RenovacaoProtocoloService
{
    public function __construct(
        private RenovacaoElegibilidadeService $elegibilidade,
        private ProtocolNumberGenerator $numeros,
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
        private ProtocolarSolicitacaoService $protocoloViabilidade,
        private EnviarParaAnaliseService $enviarAnalise,
        private RenovacaoLegadaElegibilidadeService $elegibilidadeLegada,
        private ResolvedorEnderecoImovel $enderecoImovel,
        private CnpjLookup $cnpjLookup,
    ) {}

    /**
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     * @param  list<int>  $declaracaoIds
     */
    public function protocolar(ViabilityRequest $origem, Cidadao $ator, array $contato, array $declaracaoIds): ViabilityRequest
    {
        $avaliacao = $this->elegibilidade->avaliar($origem);

        if (! $avaliacao->elegivel) {
            throw new DomainException($avaliacao->motivo ?? 'Esta viabilidade não pode ser renovada.');
        }

        $origem->loadMissing(['cnaes', 'company']);

        return $this->protocolarComum(
            atributos: [
                'renews_viability_request_id' => $origem->id,
                'company_id' => $origem->company_id,
                'used_area_m2' => $origem->used_area_m2,
                'property_registration' => $origem->property_registration,
                'address_street' => $origem->address_street,
                'address_number' => $origem->address_number,
                'address_complement' => $origem->address_complement,
                'address_neighborhood' => $origem->address_neighborhood,
                'address_zip' => $origem->address_zip,
                'address_reference' => $origem->address_reference,
                'property_polygon_geojson' => $origem->property_polygon_geojson,
            ],
            cnaes: $origem->cnaes
                ->map(fn ($cnae): array => ['id' => $cnae->id, 'is_primary' => (bool) $cnae->pivot->is_primary])
                ->all(),
            empresa: $origem->company,
            ator: $ator,
            contato: $contato,
            declaracaoIds: $declaracaoIds,
            auditoria: [
                'description' => fn (ViabilityRequest $renovacao): string => "Renovação da viabilidade locacional da solicitação #{$origem->id} protocolada como #{$renovacao->id}",
                'properties' => ['origem_id' => $origem->id],
            ],
        );
    }

    /**
     * Protocola a renovação de uma TVL do sistema legado, que não tem linha
     * local para travar: a exclusão é um lock atômico pelo número legado e a
     * elegibilidade é reavaliada dentro dele e da transação, para o segundo
     * pedido enxergar a renovação aberta pelo primeiro. A empresa é a do
     * Viabiliza com o CNPJ (a de menor id) ou é criada com os dados da TVL,
     * dentro da mesma transação e de um lock pelo CNPJ, para não duplicar.
     *
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     * @param  list<int>  $declaracaoIds
     */
    public function protocolarLegada(TvlLegadaDetalhe $tvl, ?string $cnpj, Cidadao $ator, array $contato, array $declaracaoIds): ViabilityRequest
    {
        $avaliacao = $cnpj === null
            ? $this->elegibilidadeLegada->avaliarAposTitularidade($tvl)
            : $this->elegibilidadeLegada->avaliar($tvl, new Company(['cnpj' => $cnpj]));

        if (! $avaliacao->elegivel) {
            throw new DomainException($avaliacao->motivo ?? 'Esta viabilidade não pode ser renovada.');
        }

        $endereco = $this->enderecoDoProcessoLegado($tvl);

        $lock = Cache::lock('renovacao-legada:'.$tvl->numero, 60);
        $lockEmpresa = Cache::lock('renovacao-legada-empresa:'.($cnpj ?? 'tvl-'.$tvl->numero), 60);

        try {
            $lock->block(3);
        } catch (LockTimeoutException) {
            throw new DomainException('Já existe um protocolo em andamento para esta viabilidade. Aguarde alguns instantes.');
        }

        try {
            $lockEmpresa->block(3);
        } catch (LockTimeoutException) {
            $lock->release();

            throw new DomainException('Já existe um protocolo em andamento para esta empresa. Aguarde alguns instantes.');
        }

        try {
            return DB::transaction(fn (): ViabilityRequest => $this->protocolarComum(
                atributos: [
                    'renews_legacy_tvl_number' => $tvl->numero,
                    'legacy_tvl_snapshot' => $this->snapshotLegado($tvl),
                    'company_id' => ($empresa = $this->empresaDaRenovacaoLegada($tvl, $cnpj, $endereco))->id,
                    'property_registration' => $tvl->inscricaoImobiliaria,
                    'address_street' => $endereco['logradouro'],
                    'address_number' => $endereco['numero'],
                    'address_complement' => $tvl->complemento,
                    'address_neighborhood' => $endereco['bairro'],
                    'address_zip' => $endereco['cep'],
                ],
                cnaes: $this->cnaesDoCatalogo($tvl),
                empresa: $empresa,
                ator: $ator,
                contato: $contato,
                declaracaoIds: $declaracaoIds,
                auditoria: [
                    'description' => fn (ViabilityRequest $renovacao): string => "Renovação da viabilidade locacional legada {$tvl->numero} protocolada como #{$renovacao->id}",
                    'properties' => ['tvl_legada_numero' => $tvl->numero],
                ],
                verificar: function () use ($tvl): void {
                    $reavaliacao = $this->elegibilidadeLegada->avaliarAposTitularidade($tvl);

                    if (! $reavaliacao->elegivel) {
                        throw new DomainException($reavaliacao->motivo ?? 'Esta viabilidade não pode ser renovada.');
                    }
                },
            ));
        } finally {
            $lockEmpresa->release();
            $lock->release();
        }
    }

    /**
     * Empresa da renovação de TVL do sistema anterior: a do Viabiliza com o
     * CNPJ (a mais antiga, se houver repetidas) ou uma nova, com a razão
     * social da TVL (ou do cadastro do CNPJ) e o endereço do imóvel. Sem CNPJ,
     * a de uma renovação anterior da mesma TVL ou uma nova sem CNPJ.
     *
     * @param  array{logradouro: ?string, numero: ?string, bairro: ?string, cep: ?string}  $endereco
     *
     * @throws DomainException
     */
    private function empresaDaRenovacaoLegada(TvlLegadaDetalhe $tvl, ?string $cnpj, array $endereco): Company
    {
        $existente = $cnpj === null
            ? Company::query()->whereKey(ViabilityRequest::query()->where('renews_legacy_tvl_number', $tvl->numero)->orderBy('id')->value('company_id'))->first()
            : Company::query()->where('cnpj', $cnpj)->orderBy('id')->first();

        if ($existente !== null) {
            return $existente;
        }

        $razao = trim((string) $tvl->razaoSocial);

        if ($razao === '' && $cnpj === null) {
            throw new DomainException('A viabilidade anterior não tem razão social registrada. Procure a SEDUR.');
        }

        if ($razao === '') {
            try {
                $razao = $this->cnpjLookup->lookup($cnpj)->legalName;
            } catch (CnpjNotFoundException|CnpjLookupException) {
                throw new DomainException('Não foi possível confirmar a razão social da empresa agora. Tente novamente em instantes.');
            }
        }

        return Company::query()->create([
            'cnpj' => $cnpj,
            'legal_name' => $razao,
            'street' => $endereco['logradouro'],
            'number' => $endereco['numero'],
            'complement' => $tvl->complemento,
            'neighborhood' => $endereco['bairro'],
            'city' => 'Salvador',
            'state' => 'BA',
            'zip_code' => $endereco['cep'],
            'source' => CompanySource::Legado,
        ]);
    }

    /**
     * Endereço do processo: o do imóvel no cadastro, pela inscrição da TVL.
     * Sem inscrição, mantém o endereço registrado no legado.
     *
     * @return array{logradouro: ?string, numero: ?string, bairro: ?string, cep: ?string}
     *
     * @throws DomainException
     */
    private function enderecoDoProcessoLegado(TvlLegadaDetalhe $tvl): array
    {
        if (! $this->enderecoImovel->temInscricao($tvl->inscricaoImobiliaria)) {
            return $tvl->endereco;
        }

        try {
            $imovel = $this->enderecoImovel->resolver($tvl->inscricaoImobiliaria);
        } catch (EnderecoImovelNaoEncontradoException $e) {
            throw new DomainException($e->getMessage());
        } catch (EnderecoImovelIndisponivelException) {
            throw new DomainException(ResolvedorEnderecoImovel::M_INDISPONIVEL);
        }

        return [
            'logradouro' => $imovel->logradouro,
            'numero' => $imovel->numero,
            'bairro' => $imovel->bairro,
            'cep' => $imovel->cep,
        ];
    }

    /**
     * Só o que veio do detalhe da API, com datas em texto.
     *
     * @return array<string, mixed>
     */
    private function snapshotLegado(TvlLegadaDetalhe $tvl): array
    {
        $dados = get_object_vars($tvl);

        foreach (['emissao', 'validade', 'ultimaRenovacaoEm'] as $campo) {
            $dados[$campo] = $dados[$campo]?->toDateString();
        }

        return $dados;
    }

    /**
     * @return list<array{id: int, is_primary: bool}>
     */
    private function cnaesDoCatalogo(TvlLegadaDetalhe $tvl): array
    {
        $cnaes = [];

        foreach ($tvl->atividades as $indice => $atividade) {
            $codigo = preg_replace('/\D/', '', $atividade['codigo']) ?? '';
            $cnae = Cnae::query()->where('code', $codigo)->where('active', true)->first();

            if ($cnae === null) {
                throw new DomainException("A atividade {$atividade['codigo']} não consta mais do catálogo. Procure a SEDUR.");
            }

            $cnaes[$cnae->id] = ['id' => $cnae->id, 'is_primary' => $indice === 0];
        }

        return array_values($cnaes);
    }

    /**
     * Parte comum do protocolo: declarações, criação do processo, número,
     * transições, auditoria, território, setor de triagem e envio à análise.
     * O verificador, quando informado, roda dentro da transação antes de criar
     * qualquer coisa.
     *
     * @param  array<string, mixed>  $atributos
     * @param  list<array{id: int, is_primary: bool}>  $cnaes
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     * @param  list<int>  $declaracaoIds
     * @param  array{description: callable, properties: array<string, mixed>}  $auditoria
     */
    private function protocolarComum(
        array $atributos,
        array $cnaes,
        ?Company $empresa,
        Cidadao $ator,
        array $contato,
        array $declaracaoIds,
        array $auditoria,
        ?callable $verificar = null,
    ): ViabilityRequest {
        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->first();

        if ($tipo === null) {
            throw new DomainException('O tipo de serviço Renovação de viabilidade locacional não está cadastrado.');
        }

        $obrigatorias = ServiceDeclaration::vigentesPara($tipo->id)
            ->where('required', true);

        $aceitas = collect($declaracaoIds)->map(fn (int|string $id): int => (int) $id);

        if ($obrigatorias->contains(fn (ServiceDeclaration $declaracao): bool => ! $aceitas->contains($declaracao->id))) {
            throw new DomainException('Marque todas as declarações de responsabilidade para protocolar a renovação.');
        }

        $renovacao = DB::transaction(function () use ($atributos, $cnaes, $empresa, $ator, $contato, $auditoria, $verificar, $tipo, $obrigatorias): ViabilityRequest {
            if ($verificar !== null) {
                $verificar();
            }

            $renovacao = ViabilityRequest::query()->create([
                'origin' => ViabilityRequestOrigin::Portal,
                'service_type_id' => $tipo->id,
                'requester_cidadao_id' => $ator->id,
                'created_by_type' => $ator->getMorphClass(),
                'created_by_id' => $ator->id,
                ...$atributos,
            ]);

            $renovacao->refresh();

            foreach ($cnaes as $cnae) {
                $renovacao->cnaes()->attach($cnae['id'], [
                    'is_primary' => $cnae['is_primary'],
                ]);
            }

            foreach ($obrigatorias as $declaracao) {
                ServiceDeclarationAcceptance::query()->create([
                    'service_declaration_id' => $declaracao->id,
                    'viability_request_id' => $renovacao->id,
                    'cidadao_id' => $ator->id,
                    'version' => $declaracao->version,
                    'accepted_at' => now(),
                ]);
            }

            $this->atualizarContato($empresa, $contato);

            $renovacao->forceFill([
                'protocol_number' => $this->numeros->generate(),
                'protocoled_at' => now(),
            ])->save();

            $this->stateMachine->transition(
                $renovacao,
                ViabilityRequestStatus::Protocolada,
                $ator,
                publicLabel: ViabilityRequestStatus::Protocolada->publicLabel(),
            );

            $this->stateMachine->transition(
                $renovacao,
                ViabilityRequestStatus::EmAnalise,
                $ator,
                reason: 'Renovação de viabilidade locacional provisória encaminhada à análise semi-expressa.',
                publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
            );

            $renovacao->forceFill([
                'analysis_category' => AnalysisCategory::SemiExpresso,
            ])->save();

            $this->audit->log(
                logName: 'solicitacoes',
                event: 'renovacao_protocolada',
                description: ($auditoria['description'])($renovacao),
                properties: [
                    ...$auditoria['properties'],
                    'renovacao_id' => $renovacao->id,
                    'protocol_number' => $renovacao->protocol_number,
                    'mobile' => $contato['mobile'] ?? null,
                ],
                subject: $renovacao,
            );

            return $renovacao;
        });

        // Fora da transação, como no protocolo comum: SEFAZ ou GIS fora do ar
        // não desfaz o número. O motor só roda depois, para a ficha enxergar
        // zona, via e polígono atuais.
        $this->protocoloViabilidade->completarTerritorio($renovacao);

        $setorId = $this->setorTriagemId();

        if ($setorId !== null) {
            $renovacao->forceFill(['sector_id' => $setorId])->save();
        }

        $this->enviarAnalise->enviar(
            $renovacao,
            $ator,
            'Renovação de viabilidade locacional encaminhada à análise.',
        );

        return $renovacao->fresh();
    }

    /**
     * Mesma caixa de entrada do encaminhamento que não é expresso. Sem
     * parâmetro, ou com setor inativo, o processo segue sem caixa.
     */
    private function setorTriagemId(): ?int
    {
        $id = Settings::get('analise.setor_triagem_id');

        if ($id === null || $id === '') {
            return null;
        }

        $id = (int) $id;

        return Sector::query()->whereKey($id)->where('active', true)->exists()
            ? $id
            : null;
    }

    /**
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     */
    private function atualizarContato(?Company $empresa, array $contato): void
    {
        if ($empresa === null) {
            return;
        }

        $dados = [];

        // Qualquer cidadão pode renovar: o contato informado só preenche o
        // que a empresa ainda não tem, nunca troca o contato dela.
        if (filled($contato['email'] ?? null) && blank($empresa->email)) {
            $dados['email'] = $contato['email'];
        }

        if (filled($contato['phone'] ?? null) && blank($empresa->phone)) {
            $dados['phone'] = $contato['phone'];
        }

        if ($dados !== []) {
            $empresa->fill($dados)->save();
        }
    }
}
