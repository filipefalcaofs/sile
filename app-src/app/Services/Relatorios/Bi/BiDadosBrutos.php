<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\TipoGatilho;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas brutas para o BI montar os próprios indicadores: uma linha por
 * registro, paginadas por id crescente e com carga incremental por data de
 * atualização. Os dados pessoais e de identificação (CPF, nomes, contatos,
 * CNPJ, endereço, coordenada exata) e os textos livres (motivos, convites,
 * pareceres) saem com `viabiliza.integrations.bi.identificados`, ligado por
 * padrão; desligado, ficam fora e a coordenada vem arredondada a 3 casas
 * (≈110 m). Senha, token e 2FA nunca saem.
 */
class BiDadosBrutos
{
    public const LIMITE_PADRAO = 1000;

    public const LIMITE_MAXIMO = 5000;

    /**
     * @return array<string, array{descricao: string, campos: array<string, string>, filtros: list<string>}>
     */
    public function catalogo(): array
    {
        return array_map(fn (array $tabela): array => [
            'descricao' => $tabela['descricao'],
            'campos' => $tabela['campos'],
            'filtros' => $tabela['filtros'],
        ], $this->tabelas());
    }

    public function existe(string $tabela): bool
    {
        return array_key_exists($tabela, $this->tabelas());
    }

    /**
     * Uma página da tabela, a partir do id seguinte a `apos_id`.
     *
     * @param  array{apos_id?: int|null, limite?: int|null, atualizado_desde?: string|null, processo_id?: int|null, data_de?: string|null, data_ate?: string|null}  $parametros
     * @return array{dados: list<array<string, mixed>>, ultimo_id: int|null, tem_mais: bool}
     */
    public function pagina(string $tabela, array $parametros): array
    {
        $definicao = $this->tabelas()[$tabela];
        $limite = min(self::LIMITE_MAXIMO, max(1, (int) ($parametros['limite'] ?? self::LIMITE_PADRAO)));
        $chave = $definicao['chave'];

        $query = $definicao['consulta']()
            ->when($parametros['apos_id'] ?? null, fn (Builder $q, $id): Builder => $q->where($chave, '>', (int) $id))
            ->when($parametros['atualizado_desde'] ?? null, fn (Builder $q, $desde): Builder => $q->where($definicao['atualizado'], '>=', Carbon::parse($desde)))
            ->when(
                in_array('processo_id', $definicao['filtros'], true) ? ($parametros['processo_id'] ?? null) : null,
                fn (Builder $q, $id): Builder => $q->where($definicao['processo'], (int) $id),
            )
            ->when(
                in_array('data_de', $definicao['filtros'], true) ? ($parametros['data_de'] ?? null) : null,
                fn (Builder $q, $de): Builder => $q->where('vr.protocoled_at', '>=', Carbon::parse($de)->startOfDay()),
            )
            ->when(
                in_array('data_ate', $definicao['filtros'], true) ? ($parametros['data_ate'] ?? null) : null,
                fn (Builder $q, $ate): Builder => $q->where('vr.protocoled_at', '<=', Carbon::parse($ate)->endOfDay()),
            )
            ->orderBy($chave)
            ->limit($limite + 1);

        $registros = $query->get();
        $temMais = $registros->count() > $limite;
        $registros = $registros->take($limite);

        $dados = [];

        foreach ($registros as $registro) {
            foreach ($definicao['linhas']($registro) as $linha) {
                $dados[] = $linha;
            }
        }

        $ultimo = $registros->last();

        return [
            'dados' => $dados,
            'ultimo_id' => $ultimo !== null ? (int) $ultimo->id : null,
            'tem_mais' => $temMais,
        ];
    }

    /**
     * @return array<string, array{descricao: string, campos: array<string, string>, filtros: list<string>, chave: string, atualizado: string, processo: string, consulta: Closure(): Builder, linhas: Closure(object): list<array<string, mixed>>}>
     */
    private function tabelas(): array
    {
        $identificados = (bool) config('viabiliza.integrations.bi.identificados');
        $nominal = (bool) config('viabiliza.integrations.bi.nominal');
        $filhos = ['apos_id', 'limite', 'atualizado_desde', 'processo_id'];
        $dimensao = ['apos_id', 'limite', 'atualizado_desde'];

        return [
            'processos' => [
                'descricao' => 'Uma linha por solicitação protocolada (rascunhos ficam fora), com a situação atual, território, empresa (porte e natureza) e a decisão, quando houver.',
                'campos' => [
                    'id' => 'Identificador do processo (chave das demais tabelas).',
                    'protocolo' => 'Número do protocolo no Viabiliza.',
                    'situacao' => 'Situação atual (código).',
                    'situacao_rotulo' => 'Situação atual (texto).',
                    'origem' => 'Canal de entrada: portal, regin ou contingencia.',
                    'origem_rotulo' => 'Canal de entrada (texto).',
                    'tipo_servico_id' => 'Tipo de serviço (chave de tipos-servico).',
                    'categoria' => 'Categoria da análise: expresso ou semi_expresso.',
                    'situacao_analise' => 'Etapa operacional da análise (código).',
                    'situacao_analise_rotulo' => 'Etapa operacional da análise (texto).',
                    'etapa_analise' => 'Fase da análise: distribuicao ou analise.',
                    'malha_fina' => 'Processo caiu na malha fina.',
                    'escritorio_virtual' => 'Solicitação de escritório virtual.',
                    'area_publica' => 'Atividade em área pública.',
                    'acesso_independente' => 'Imóvel com acesso independente.',
                    'area_utilizada_m2' => 'Área pretendida para a atividade, em m².',
                    'tipo_imovel' => 'Tipo do imóvel normalizado.',
                    'zona' => 'Zona urbanística identificada no imóvel.',
                    'bairro' => 'Bairro oficial, ou o informado quando não identificado.',
                    'bairro_oficial' => 'Bairro oficial identificado pela camada da prefeitura.',
                    'latitude' => 'Latitude do imóvel (arredondada a 3 casas sem dados identificados).',
                    'longitude' => 'Longitude do imóvel (idem).',
                    'fonte_localizacao' => 'De onde veio o ponto do imóvel.',
                    'setor_id' => 'Setor responsável (chave de setores).',
                    'analista_id' => 'Analista responsável (chave de analistas).',
                    'empresa_porte' => 'Porte da empresa.',
                    'empresa_natureza_juridica' => 'Natureza jurídica da empresa.',
                    'renova_processo_id' => 'Processo renovado por este, quando é renovação.',
                    'simulacao_resultado' => 'Resultado da simulação feita antes do protocolo.',
                    'cnae_principal' => 'Código do CNAE principal.',
                    'protocolado_em' => 'Data e hora do protocolo.',
                    'distribuido_em' => 'Data e hora da distribuição ao analista.',
                    'inicio_etapa_em' => 'Início da etapa atual da análise.',
                    'prazo_analise_em' => 'Prazo (SLA) da etapa atual.',
                    'prazo_bap_em' => 'Prazo para a atuação na Junta (BAP).',
                    'bap_vinculado_em' => 'Vinculação do BAP.',
                    'cancelado_em' => 'Cancelamento.',
                    'tempo_viabilidade_horas_uteis' => 'Tempo do protocolo à decisão, em horas úteis, sem o tempo com o requerente.',
                    'decisao_desfecho' => 'Desfecho da decisão: deferida, indeferida, desativado, cassado ou revogado.',
                    'decisao_fluxo' => 'Quem decidiu: expresso (automático) ou analise_tecnica.',
                    'decidido_em' => 'Data e hora da decisão.',
                    'criado_em' => 'Criação do registro.',
                    'atualizado_em' => 'Última atualização (use em atualizado_desde).',
                    ...($identificados ? [
                        'cnpj' => 'CNPJ da empresa.',
                        'razao_social' => 'Razão social.',
                        'nome_fantasia' => 'Nome fantasia.',
                        'logradouro' => 'Logradouro do imóvel.',
                        'numero' => 'Número do imóvel.',
                        'complemento' => 'Complemento do endereço.',
                        'bairro_informado' => 'Bairro digitado no pedido.',
                        'cep' => 'CEP do imóvel.',
                        'inscricao_imobiliaria' => 'Inscrição imobiliária.',
                        'referencia_externa' => 'Referência externa do pedido (protocolo da JUCEB, quando houver).',
                        'empresa_email' => 'E-mail da empresa.',
                        'empresa_telefone' => 'Telefone da empresa.',
                        'solicitante_nome' => 'Nome do solicitante informado pela JUCEB.',
                        'solicitante_cpf' => 'CPF do solicitante informado pela JUCEB.',
                        'requerente_nome' => 'Nome do requerente com conta no portal.',
                        'requerente_cpf' => 'CPF do requerente.',
                        'requerente_email' => 'E-mail do requerente.',
                        'requerente_telefone' => 'Telefone do requerente.',
                        'motivo_cancelamento' => 'Motivo do cancelamento.',
                        'justificativa_contingencia' => 'Justificativa do registro por contingência.',
                    ] : []),
                ],
                'filtros' => ['apos_id', 'limite', 'atualizado_desde', 'data_de', 'data_ate'],
                'chave' => 'vr.id',
                'atualizado' => 'vr.updated_at',
                'processo' => 'vr.id',
                'consulta' => fn (): Builder => $this->comRequerente(DB::table('viability_requests as vr'))
                    ->whereNotNull('vr.protocoled_at')
                    ->where('vr.status', '!=', ViabilityRequestStatus::Rascunho->value)
                    ->leftJoin('companies as co', 'co.id', '=', 'vr.company_id')
                    ->leftJoin('viability_decisions as vd', 'vd.viability_request_id', '=', 'vr.id')
                    ->select(
                        'vr.*',
                        'co.size as empresa_porte',
                        'co.legal_nature as empresa_natureza_juridica',
                        'co.cnpj as empresa_cnpj',
                        'co.legal_name as empresa_razao_social',
                        'co.trade_name as empresa_nome_fantasia',
                        'co.email as empresa_email',
                        'co.phone as empresa_telefone',
                        'rq.name as requerente_nome',
                        'rq.cpf as requerente_cpf',
                        'rq.email as requerente_email',
                        'rq.phone as requerente_telefone',
                        'vd.outcome as decisao_desfecho',
                        'vd.flow as decisao_fluxo',
                        'vd.decided_at as decidido_em',
                    )
                    ->selectSub(
                        DB::table('viability_request_cnaes as vrc')
                            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
                            ->whereColumn('vrc.viability_request_id', 'vr.id')
                            ->where('vrc.is_primary', true)
                            ->orderBy('vrc.id')
                            ->limit(1)
                            ->select('c.code'),
                        'cnae_principal',
                    ),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'protocolo' => $r->protocol_number,
                    'situacao' => $r->status,
                    'situacao_rotulo' => $this->rotulo(ViabilityRequestStatus::class, $r->status),
                    'origem' => $r->origin,
                    'origem_rotulo' => $this->rotulo(ViabilityRequestOrigin::class, $r->origin),
                    'tipo_servico_id' => $this->inteiro($r->service_type_id),
                    'categoria' => $r->analysis_category,
                    'situacao_analise' => $r->analysis_status,
                    'situacao_analise_rotulo' => $this->rotulo(AnalysisStatus::class, $r->analysis_status),
                    'etapa_analise' => $r->analysis_stage,
                    'malha_fina' => (bool) $r->in_fine_mesh,
                    'escritorio_virtual' => (bool) $r->is_virtual_office,
                    'area_publica' => (bool) $r->is_public_area,
                    'acesso_independente' => (bool) $r->has_independent_access,
                    'area_utilizada_m2' => $this->decimal($r->used_area_m2),
                    'tipo_imovel' => $r->tipo_imovel_normalized ?? $r->tipo_imovel,
                    'zona' => $r->zona_codigo,
                    'bairro' => $r->bairro_oficial ?? $r->address_neighborhood,
                    'bairro_oficial' => $r->bairro_oficial,
                    'latitude' => $this->coordenada($r->property_point_lat, $identificados),
                    'longitude' => $this->coordenada($r->property_point_lng, $identificados),
                    'fonte_localizacao' => $r->property_location_source,
                    'setor_id' => $this->inteiro($r->sector_id),
                    'analista_id' => $this->inteiro($r->assigned_user_id),
                    'empresa_porte' => $r->empresa_porte,
                    'empresa_natureza_juridica' => $r->empresa_natureza_juridica,
                    'renova_processo_id' => $this->inteiro($r->renews_viability_request_id),
                    'simulacao_resultado' => $r->simulation_resultado,
                    'cnae_principal' => $r->cnae_principal,
                    'protocolado_em' => $this->data($r->protocoled_at),
                    'distribuido_em' => $this->data($r->assigned_at),
                    'inicio_etapa_em' => $this->data($r->analysis_stage_started_at),
                    'prazo_analise_em' => $this->data($r->analysis_due_at),
                    'prazo_bap_em' => $this->data($r->bap_due_at),
                    'bap_vinculado_em' => $this->data($r->bap_linked_at),
                    'cancelado_em' => $this->data($r->cancelled_at),
                    'tempo_viabilidade_horas_uteis' => $this->decimal($r->viabilidade_horas_uteis),
                    'decisao_desfecho' => $r->decisao_desfecho,
                    'decisao_fluxo' => $r->decisao_fluxo,
                    'decidido_em' => $this->data($r->decidido_em),
                    'criado_em' => $this->data($r->created_at),
                    'atualizado_em' => $this->data($r->updated_at),
                    ...($identificados ? [
                        'cnpj' => $r->empresa_cnpj,
                        'razao_social' => $r->empresa_razao_social,
                        'nome_fantasia' => $r->empresa_nome_fantasia,
                        'logradouro' => $r->address_street,
                        'numero' => $r->address_number,
                        'complemento' => $r->address_complement,
                        'bairro_informado' => $r->address_neighborhood,
                        'cep' => $r->address_zip,
                        'inscricao_imobiliaria' => $r->property_registration,
                        'referencia_externa' => $r->external_reference,
                        'empresa_email' => $r->empresa_email,
                        'empresa_telefone' => $r->empresa_telefone,
                        'solicitante_nome' => $r->solicitante_nome ?? null,
                        'solicitante_cpf' => $r->solicitante_cpf ?? null,
                        'requerente_nome' => $r->requerente_nome,
                        'requerente_cpf' => $r->requerente_cpf,
                        'requerente_email' => $r->requerente_email,
                        'requerente_telefone' => $r->requerente_telefone,
                        'motivo_cancelamento' => $r->cancelled_reason,
                        'justificativa_contingencia' => $r->contingency_reason,
                    ] : []),
                ]],
            ],

            'processo-cnaes' => [
                'descricao' => 'CNAEs de cada processo: uma linha por processo × CNAE.',
                'campos' => [
                    'id' => 'Identificador da linha.',
                    'processo_id' => 'Processo (chave de processos).',
                    'cnae' => 'Código do CNAE.',
                    'principal' => 'É o CNAE principal.',
                    'intencao' => 'Intenção na alteração de atividade (incluir, manter, excluir), quando houver.',
                    'atualizado_em' => 'Última atualização.',
                ],
                'filtros' => $filhos,
                'chave' => 'vrc.id',
                'atualizado' => 'vrc.updated_at',
                'processo' => 'vrc.viability_request_id',
                'consulta' => fn (): Builder => DB::table('viability_request_cnaes as vrc')
                    ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
                    ->select('vrc.*', 'c.code as cnae'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'cnae' => $r->cnae,
                    'principal' => (bool) $r->is_primary,
                    'intencao' => $r->intencao,
                    'atualizado_em' => $this->data($r->updated_at),
                ]],
            ],

            'decisoes' => [
                'descricao' => 'Decisões vinculantes: uma por processo decidido, com desfecho, modalidade, Viabilidade emitida e fundamentos legais.',
                'campos' => [
                    'id' => 'Identificador da decisão.',
                    'processo_id' => 'Processo (chave de processos).',
                    'desfecho' => 'deferida, indeferida, desativado, cassado ou revogado.',
                    'fluxo' => 'expresso (automático) ou analise_tecnica.',
                    'resultado_consolidado' => 'Enquadramento consolidado dos CNAEs.',
                    'numero_viabilidade' => 'Número da Viabilidade emitida.',
                    'tipo_viabilidade' => 'provisoria, definitiva ou pre_operacional.',
                    'tipo_viabilidade_rotulo' => 'Tipo da Viabilidade (texto).',
                    'valida_ate' => 'Fim da validade da Viabilidade.',
                    'decidido_em' => 'Data e hora da decisão.',
                    'decidido_por_id' => 'Analista que decidiu (vazio na decisão automática).',
                    'sede_escritorio_virtual' => 'Decisão de sede de escritório virtual.',
                    'abrigado_escritorio_virtual' => 'Decisão de empresa abrigada em escritório virtual.',
                    'fundamentos' => 'Lista dos fundamentos legais citados.',
                    'atualizado_em' => 'Última atualização.',
                    ...($identificados ? ['motivo' => 'Texto do motivo da decisão (justificativa do indeferimento).'] : []),
                ],
                'filtros' => $filhos,
                'chave' => 'vd.id',
                'atualizado' => 'vd.updated_at',
                'processo' => 'vd.viability_request_id',
                'consulta' => fn (): Builder => DB::table('viability_decisions as vd')->select('vd.*'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'desfecho' => $r->outcome,
                    'fluxo' => $r->flow,
                    'resultado_consolidado' => $r->consolidated_result,
                    'numero_viabilidade' => $r->tvl_product_number,
                    'tipo_viabilidade' => $r->tvl_kind,
                    'tipo_viabilidade_rotulo' => $this->rotulo(TvlKind::class, $r->tvl_kind),
                    'valida_ate' => $this->data($r->tvl_valid_until),
                    'decidido_em' => $this->data($r->decided_at),
                    'decidido_por_id' => $this->inteiro($r->decided_by_user_id),
                    'sede_escritorio_virtual' => (bool) $r->is_virtual_office_hq,
                    'abrigado_escritorio_virtual' => (bool) $r->is_virtual_office_tenant,
                    'fundamentos' => $this->lista($r->fundamentacao),
                    'atualizado_em' => $this->data($r->updated_at),
                    ...($identificados ? ['motivo' => $r->reason] : []),
                ]],
            ],

            'decisao-cnaes' => [
                'descricao' => 'Enquadramento de cada CNAE na decisão: uma linha por decisão × CNAE. Mostra qual atividade foi permitida ou barrada.',
                'campos' => [
                    'id' => 'Decisão (chave de decisoes); a paginação é por decisão.',
                    'processo_id' => 'Processo (chave de processos).',
                    'cnae' => 'Código do CNAE.',
                    'principal' => 'É o CNAE principal.',
                    'tendencia' => 'Enquadramento do CNAE (por exemplo permitido, nao_permitido).',
                    'fluxo' => 'Encaminhamento do CNAE (expresso ou análise).',
                    'intencao' => 'Intenção na alteração de atividade, quando houver.',
                    'desfecho_decisao' => 'Desfecho da decisão inteira.',
                    'decidido_em' => 'Data e hora da decisão.',
                ],
                'filtros' => $filhos,
                'chave' => 'vd.id',
                'atualizado' => 'vd.updated_at',
                'processo' => 'vd.viability_request_id',
                'consulta' => fn (): Builder => DB::table('viability_decisions as vd')
                    ->select('vd.id', 'vd.viability_request_id', 'vd.per_cnae', 'vd.outcome', 'vd.decided_at'),
                'linhas' => fn (object $r): array => array_values(array_map(fn (array $item): array => [
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'cnae' => isset($item['cnae']) ? (string) $item['cnae'] : null,
                    'principal' => isset($item['is_primary']) ? (bool) $item['is_primary'] : null,
                    'tendencia' => $item['tendencia'] ?? null,
                    'fluxo' => $item['fluxo'] ?? null,
                    'intencao' => $item['intencao'] ?? null,
                    'desfecho_decisao' => $r->outcome,
                    'decidido_em' => $this->data($r->decided_at),
                ], array_filter($this->lista($r->per_cnae), 'is_array'))),
            ],

            'transicoes' => [
                'descricao' => 'Histórico de situação do processo: uma linha por mudança (de → para), com o instante e quem agiu.',
                'campos' => [
                    'id' => 'Identificador da transição.',
                    'processo_id' => 'Processo (chave de processos).',
                    'de' => 'Situação anterior.',
                    'para' => 'Situação nova.',
                    'em' => 'Data e hora da mudança.',
                    'ator_tipo' => 'Quem agiu: servidor, requerente ou vazio quando foi o sistema.',
                    'ator_id' => 'Servidor que agiu (chave de analistas); vazio para requerente e sistema.',
                    ...($identificados ? [
                        'motivo' => 'Motivo registrado na mudança.',
                        'rotulo_publico' => 'Texto mostrado ao requerente.',
                    ] : []),
                ],
                'filtros' => $filhos,
                'chave' => 't.id',
                'atualizado' => 't.created_at',
                'processo' => 't.viability_request_id',
                'consulta' => fn (): Builder => $this->transicoesComAtor(),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'de' => $r->from_status,
                    'para' => $r->to_status,
                    'em' => $this->data($r->created_at),
                    'ator_tipo' => $r->ator_tipo,
                    'ator_id' => $this->inteiro($r->ator_id),
                    ...($identificados ? ['motivo' => $r->reason, 'rotulo_publico' => $r->public_label] : []),
                ]],
            ],

            'transicoes-analise' => [
                'descricao' => 'Histórico das etapas operacionais da análise (distribuir, analisar, convite, vistoria, conclusão): uma linha por mudança.',
                'campos' => [
                    'id' => 'Identificador da transição.',
                    'processo_id' => 'Processo (chave de processos).',
                    'de' => 'Etapa anterior.',
                    'para' => 'Etapa nova.',
                    'para_rotulo' => 'Etapa nova (texto).',
                    'grupo' => 'Grupo da etapa nova: Distribuição, Análise, Convite ou Vistoria.',
                    'em' => 'Data e hora da mudança.',
                    'ator_id' => 'Usuário que agiu.',
                    ...($identificados ? ['motivo' => 'Motivo registrado na mudança.'] : []),
                ],
                'filtros' => $filhos,
                'chave' => 't.id',
                'atualizado' => 't.created_at',
                'processo' => 't.viability_request_id',
                'consulta' => fn (): Builder => DB::table('analysis_status_transitions as t')->select('t.id', 't.viability_request_id', 't.from_status', 't.to_status', 't.actor_user_id', 't.reason', 't.created_at'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'de' => $r->from_status,
                    'para' => $r->to_status,
                    'para_rotulo' => $this->rotulo(AnalysisStatus::class, $r->to_status),
                    'grupo' => AnalysisStatus::tryFrom((string) $r->to_status)?->grupo(),
                    'em' => $this->data($r->created_at),
                    'ator_id' => $this->inteiro($r->actor_user_id),
                    ...($identificados ? ['motivo' => $r->reason] : []),
                ]],
            ],

            'convites' => [
                'descricao' => 'Convites (pendências) ao requerente: abertura, prazo, resposta e situação. O texto do convite e da resposta fica fora.',
                'campos' => [
                    'id' => 'Identificador do convite.',
                    'processo_id' => 'Processo (chave de processos).',
                    'situacao' => 'aberta, respondida, expirada ou cancelada.',
                    'situacao_rotulo' => 'Situação (texto).',
                    'aberto_em' => 'Abertura do convite.',
                    'prazo_em' => 'Prazo para o requerente responder.',
                    'respondido_em' => 'Resposta do requerente.',
                    'cancelado_em' => 'Cancelamento.',
                    'solicitado_por_id' => 'Usuário que abriu o convite.',
                    'atualizado_em' => 'Última atualização.',
                    ...($identificados ? [
                        'descricao' => 'O que foi pedido ao requerente.',
                        'resposta' => 'Resposta do requerente.',
                        'parecer' => 'Parecer do servidor sobre a resposta.',
                    ] : []),
                ],
                'filtros' => $filhos,
                'chave' => 'p.id',
                'atualizado' => 'p.updated_at',
                'processo' => 'p.viability_request_id',
                'consulta' => fn (): Builder => DB::table('analysis_pendencies as p')->select('p.id', 'p.viability_request_id', 'p.status', 'p.created_at', 'p.due_at', 'p.responded_at', 'p.cancelled_at', 'p.requested_by_user_id', 'p.updated_at', 'p.description', 'p.response', 'p.parecer'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'situacao' => $r->status,
                    'situacao_rotulo' => $this->rotulo(AnalysisPendencyStatus::class, $r->status),
                    'aberto_em' => $this->data($r->created_at),
                    'prazo_em' => $this->data($r->due_at),
                    'respondido_em' => $this->data($r->responded_at),
                    'cancelado_em' => $this->data($r->cancelled_at),
                    'solicitado_por_id' => $this->inteiro($r->requested_by_user_id),
                    'atualizado_em' => $this->data($r->updated_at),
                    ...($identificados ? ['descricao' => $r->description, 'resposta' => $r->response, 'parecer' => $r->parecer] : []),
                ]],
            ],

            'vistorias' => [
                'descricao' => 'Vistorias do imóvel: tipo, situação, datas, vistoriador e os dados medidos que não identificam pessoa.',
                'campos' => [
                    'id' => 'Identificador da vistoria.',
                    'processo_id' => 'Processo (chave de processos).',
                    'tipo' => 'Tipo da vistoria.',
                    'tipo_rotulo' => 'Tipo (texto).',
                    'situacao' => 'Situação da vistoria.',
                    'situacao_rotulo' => 'Situação (texto).',
                    'vistoriador_id' => 'Vistoriador (chave de analistas).',
                    'aberta_em' => 'Abertura.',
                    'concluida_em' => 'Conclusão.',
                    'data_vistoria' => 'Dia da ida ao imóvel.',
                    'zona' => 'Zona conferida na vistoria.',
                    'via' => 'Via conferida na vistoria.',
                    'tipo_imovel' => 'Tipo do imóvel conferido.',
                    'area_total_m2' => 'Área total medida.',
                    'area_ocupada_atividade_m2' => 'Área ocupada pela atividade.',
                    'atividade_em_funcionamento' => 'A atividade já funcionava no dia.',
                    'atualizado_em' => 'Última atualização.',
                    ...($identificados ? [
                        'logradouro' => 'Logradouro conferido.',
                        'numero_metrico' => 'Número métrico.',
                        'bairro' => 'Bairro conferido.',
                        'cep' => 'CEP conferido.',
                        'ponto_referencia' => 'Ponto de referência.',
                        'contato_nome' => 'Pessoa que recebeu a vistoria.',
                        'contato_telefone' => 'Telefone do contato.',
                        'observacoes' => 'Observações do vistoriador.',
                        'parecer' => 'Parecer da vistoria.',
                    ] : []),
                ],
                'filtros' => $filhos,
                'chave' => 'i.id',
                'atualizado' => 'i.updated_at',
                'processo' => 'i.viability_request_id',
                'consulta' => fn (): Builder => DB::table('inspections as i')->select(
                    'i.id', 'i.viability_request_id', 'i.tipo', 'i.status', 'i.vistoriador_user_id', 'i.opened_at', 'i.concluded_at',
                    'i.data_vistoria', 'i.zona', 'i.via', 'i.tipo_imovel', 'i.area_total_m2', 'i.area_ocupada_atividade_m2',
                    'i.atividade_em_funcionamento', 'i.updated_at', 'i.logradouro', 'i.numero_metrico', 'i.bairro', 'i.cep',
                    'i.ponto_referencia', 'i.contato_nome', 'i.contato_telefone', 'i.observacoes', 'i.parecer',
                ),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'tipo' => $r->tipo,
                    'tipo_rotulo' => $this->rotulo(InspectionType::class, $r->tipo),
                    'situacao' => $r->status,
                    'situacao_rotulo' => $this->rotulo(InspectionStatus::class, $r->status),
                    'vistoriador_id' => $this->inteiro($r->vistoriador_user_id),
                    'aberta_em' => $this->data($r->opened_at),
                    'concluida_em' => $this->data($r->concluded_at),
                    'data_vistoria' => $this->data($r->data_vistoria),
                    'zona' => $r->zona,
                    'via' => $r->via,
                    'tipo_imovel' => $r->tipo_imovel,
                    'area_total_m2' => $this->decimal($r->area_total_m2),
                    'area_ocupada_atividade_m2' => $this->decimal($r->area_ocupada_atividade_m2),
                    'atividade_em_funcionamento' => $r->atividade_em_funcionamento,
                    'atualizado_em' => $this->data($r->updated_at),
                    ...($identificados ? [
                        'logradouro' => $r->logradouro,
                        'numero_metrico' => $r->numero_metrico,
                        'bairro' => $r->bairro,
                        'cep' => $r->cep,
                        'ponto_referencia' => $r->ponto_referencia,
                        'contato_nome' => $r->contato_nome,
                        'contato_telefone' => $r->contato_telefone,
                        'observacoes' => $r->observacoes,
                        'parecer' => $r->parecer,
                    ] : []),
                ]],
            ],

            'fichas-analise' => [
                'descricao' => 'Fichas de análise técnica: revisão, situação, analista e finalização. O parecer fica fora.',
                'campos' => [
                    'id' => 'Identificador da ficha.',
                    'processo_id' => 'Processo (chave de processos).',
                    'revisao' => 'Número da revisão da ficha.',
                    'situacao' => 'Situação da ficha.',
                    'analista_id' => 'Analista dono da ficha.',
                    'endereco_confirmado' => 'O analista confirmou o endereço.',
                    'criada_em' => 'Abertura da ficha.',
                    'finalizada_em' => 'Finalização da ficha.',
                    'atualizado_em' => 'Última atualização.',
                    ...($identificados ? ['parecer' => 'Parecer da análise técnica.'] : []),
                ],
                'filtros' => $filhos,
                'chave' => 'a.id',
                'atualizado' => 'a.updated_at',
                'processo' => 'a.viability_request_id',
                'consulta' => fn (): Builder => DB::table('analysis_records as a')->select('a.id', 'a.viability_request_id', 'a.revision', 'a.status', 'a.analyst_user_id', 'a.address_confirmed', 'a.created_at', 'a.finalized_at', 'a.updated_at', 'a.parecer'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'revisao' => $this->inteiro($r->revision),
                    'situacao' => $r->status,
                    'analista_id' => $this->inteiro($r->analyst_user_id),
                    'endereco_confirmado' => $r->address_confirmed === null ? null : (bool) $r->address_confirmed,
                    'criada_em' => $this->data($r->created_at),
                    'finalizada_em' => $this->data($r->finalized_at),
                    'atualizado_em' => $this->data($r->updated_at),
                    ...($identificados ? ['parecer' => $r->parecer] : []),
                ]],
            ],

            'quedas-expresso' => [
                'descricao' => 'Motivos de o processo sair do fluxo expresso para a análise técnica: uma linha por CNAE que caiu (ou uma por processo quando o motivo é geral).',
                'campos' => [
                    'id' => 'Identificador da queda.',
                    'processo_id' => 'Processo (chave de processos).',
                    'cnae' => 'CNAE que levou à análise (vazio quando o motivo é do processo).',
                    'tipo_gatilho' => 'Condição que derrubou o expresso (código).',
                    'tipo_gatilho_rotulo' => 'Condição (texto).',
                    'dimensao' => 'Dimensão decisiva (municipal, estadual, etc.).',
                    'motivo' => 'Motivo registrado pelo sistema.',
                    'em' => 'Data e hora do encaminhamento.',
                ],
                'filtros' => $filhos,
                'chave' => 'q.id',
                'atualizado' => 'q.created_at',
                'processo' => 'q.viability_request_id',
                'consulta' => fn (): Builder => DB::table('expresso_quedas as q')->select('q.*'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'cnae' => $r->cnae,
                    'tipo_gatilho' => $r->tipo_gatilho,
                    'tipo_gatilho_rotulo' => $this->rotulo(TipoGatilho::class, $r->tipo_gatilho),
                    'dimensao' => $r->dimensao,
                    'motivo' => $r->motivo,
                    'em' => $this->data($r->created_at),
                ]],
            ],

            'servicos-juceb' => [
                'descricao' => 'Serviços (eventos) pedidos na JUCEB em cada processo: uma linha por processo × evento.',
                'campos' => [
                    'id' => 'Identificador da linha.',
                    'processo_id' => 'Processo (chave de processos).',
                    'codigo' => 'Código do evento na JUCEB.',
                    'nome' => 'Nome do evento.',
                    'condicao' => 'Condição detectada do evento, quando houver.',
                ],
                'filtros' => $filhos,
                'chave' => 'e.id',
                'atualizado' => 'e.updated_at',
                'processo' => 'e.viability_request_id',
                'consulta' => fn (): Builder => DB::table('viability_request_eventos as e')->select('e.id', 'e.viability_request_id', 'e.codigo', 'e.nome', 'e.condicao', 'e.updated_at'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'processo_id' => (int) $r->viability_request_id,
                    'codigo' => $r->codigo,
                    'nome' => $r->nome,
                    'condicao' => $r->condicao,
                ]],
            ],

            'cnaes' => [
                'descricao' => 'Tabela de CNAEs com a hierarquia (seção, divisão, grupo, classe).',
                'campos' => [
                    'id' => 'Identificador.',
                    'cnae' => 'Código do CNAE (7 dígitos).',
                    'descricao' => 'Descrição.',
                    'secao' => 'Seção.',
                    'secao_descricao' => 'Descrição da seção.',
                    'divisao' => 'Divisão.',
                    'divisao_descricao' => 'Descrição da divisão.',
                    'grupo' => 'Grupo.',
                    'grupo_descricao' => 'Descrição do grupo.',
                    'ativo' => 'CNAE ativo no cadastro.',
                ],
                'filtros' => $dimensao,
                'chave' => 'c.id',
                'atualizado' => 'c.updated_at',
                'processo' => 'c.id',
                'consulta' => fn (): Builder => DB::table('cnaes as c')->select('c.*'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'cnae' => $r->code,
                    'descricao' => $r->description,
                    'secao' => $r->section_code,
                    'secao_descricao' => $r->section_description,
                    'divisao' => $r->division_code,
                    'divisao_descricao' => $r->division_description,
                    'grupo' => $r->group_code,
                    'grupo_descricao' => $r->group_description,
                    'ativo' => (bool) $r->active,
                ]],
            ],

            'setores' => [
                'descricao' => 'Setores da SEDUR.',
                'campos' => ['id' => 'Identificador.', 'codigo' => 'Código.', 'nome' => 'Nome.', 'ativo' => 'Setor ativo.'],
                'filtros' => $dimensao,
                'chave' => 's.id',
                'atualizado' => 's.updated_at',
                'processo' => 's.id',
                'consulta' => fn (): Builder => DB::table('sectors as s')->select('s.*'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'codigo' => $r->code,
                    'nome' => $r->name,
                    'ativo' => (bool) $r->active,
                ]],
            ],

            'tipos-servico' => [
                'descricao' => 'Tipos de serviço de viabilidade.',
                'campos' => ['id' => 'Identificador.', 'codigo' => 'Código.', 'nome' => 'Nome.', 'ativo' => 'Tipo ativo.'],
                'filtros' => $dimensao,
                'chave' => 't.id',
                'atualizado' => 't.updated_at',
                'processo' => 't.id',
                'consulta' => fn (): Builder => DB::table('viability_service_types as t')->select('t.*'),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'codigo' => $r->code,
                    'nome' => $r->name,
                    'ativo' => (bool) $r->active,
                ]],
            ],

            'analistas' => [
                'descricao' => 'Servidores que aparecem como analista, vistoriador ou autor de decisão e transição. Sem a liberação nominal, o nome vira um rótulo pelo código e o e-mail fica fora.',
                'campos' => [
                    'id' => 'Identificador (chave de analista_id, decidido_por_id, ator_id e vistoriador_id).',
                    'nome' => 'Nome, ou "Analista {id}" sem a liberação nominal.',
                    ...($nominal ? ['email' => 'E-mail funcional.'] : []),
                ],
                'filtros' => ['apos_id', 'limite'],
                'chave' => 'u.id',
                'atualizado' => 'u.updated_at',
                'processo' => 'u.id',
                'consulta' => fn (): Builder => DB::table('users as u')
                    ->select('u.id', 'u.name', 'u.email')
                    ->where(fn (Builder $q): Builder => $q
                        ->whereIn('u.id', DB::table('viability_requests')->whereNotNull('assigned_user_id')->select('assigned_user_id'))
                        ->orWhereIn('u.id', DB::table('viability_decisions')->whereNotNull('decided_by_user_id')->select('decided_by_user_id'))
                        ->orWhereIn('u.id', DB::table('analysis_status_transitions')->whereNotNull('actor_user_id')->select('actor_user_id'))
                        ->orWhereIn('u.id', DB::table('inspections')->whereNotNull('vistoriador_user_id')->select('vistoriador_user_id'))),
                'linhas' => fn (object $r): array => [[
                    'id' => (int) $r->id,
                    'nome' => $nominal ? $r->name : 'Analista '.$r->id,
                    ...($nominal ? ['email' => $r->email] : []),
                ]],
            ],
        ];
    }

    /**
     * Junta o requerente com conta no portal (`rq`): cidadão onde o cadastro é
     * separado, usuário onde não é.
     */
    private function comRequerente(Builder $query): Builder
    {
        return Schema::hasColumn('viability_requests', 'requester_cidadao_id')
            ? $query->leftJoin('cidadaos as rq', 'rq.id', '=', 'vr.requester_cidadao_id')
            : $query->leftJoin('users as rq', 'rq.id', '=', 'vr.requester_user_id');
    }

    /**
     * Histórico de situação com o autor normalizado. Onde a coluna é polimórfica
     * (servidor ou requerente), só o servidor sai com id.
     */
    private function transicoesComAtor(): Builder
    {
        $query = DB::table('viability_request_transitions as t')
            ->select('t.id', 't.viability_request_id', 't.from_status', 't.to_status', 't.reason', 't.public_label', 't.created_at');

        if (Schema::hasColumn('viability_request_transitions', 'actor_user_id')) {
            return $query
                ->addSelect('t.actor_user_id as ator_id')
                ->selectRaw("case when t.actor_user_id is null then null else 'servidor' end as ator_tipo");
        }

        $servidor = (new User)->getMorphClass();

        return $query
            ->selectRaw('case when t.actor_type = ? then t.actor_id end as ator_id', [$servidor])
            ->selectRaw("case when t.actor_type is null then null when t.actor_type = ? then 'servidor' else 'requerente' end as ator_tipo", [$servidor]);
    }

    /**
     * @param  class-string  $enum
     */
    private function rotulo(string $enum, mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $caso = $enum::tryFrom((string) $valor);

        return $caso !== null && method_exists($caso, 'label') ? $caso->label() : (string) $valor;
    }

    private function data(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : Carbon::parse((string) $valor)->toIso8601String();
    }

    private function inteiro(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function decimal(mixed $valor): ?float
    {
        return $valor === null || $valor === '' ? null : round((float) $valor, 2);
    }

    private function coordenada(mixed $valor, bool $exata): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return round((float) $valor, $exata ? 7 : 3);
    }

    /**
     * Coluna JSON como lista, venha decodificada ou como texto.
     *
     * @return array<int|string, mixed>
     */
    private function lista(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }

        return is_array($valor) ? $valor : [];
    }
}
