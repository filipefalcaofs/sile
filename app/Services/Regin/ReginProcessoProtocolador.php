<?php

namespace App\Services\Regin;

use App\Enums\AuthType;
use App\Enums\CompanySource;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestOrigin;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ReginRecebimento;
use App\Models\RuleVersion;
use App\Models\TratamentoPergunta;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Rules\ValidCpf;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\PoligonoEnderecamentoService;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Tratamento\CategoriaEmpresa;
use App\Services\Tratamento\RespostaPlanilha;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Protocola o processo a partir do RUC do envelope REGIN — sem o catálogo do
 * simulador. O que o RUC não trouxer (CNAE principal, área, endereço) NÃO
 * protocola: retorna null e a pendência fica visível, nunca processo inventado.
 */
class ReginProcessoProtocolador
{
    public const CONTINGENCIA_RECEBE = 'regin_recebe';

    /** TIPO da instituição Prefeitura na tabela do Guia v2.12 (3.27); as demais perguntas do envelope não são da SEDUR. */
    private const INSTITUICAO_PREFEITURA = '2';

    /** Parte mínima das palavras do enunciado da JUCEB que a planilha precisa conter para casar por semelhança. */
    private const COBERTURA_MINIMA_SEMELHANCA = 0.8;

    /** Enunciado curto demais ("Trata-se de escritório virtual?") não casa por semelhança. */
    private const PALAVRAS_MINIMAS_SEMELHANCA = 4;

    public function __construct(
        private ProtocolarSolicitacaoService $protocolar,
        private FluxoExpressoService $expresso,
        private ReginTipoImovelApplier $tipoImovel,
        private PoligonoEnderecamentoService $poligonoLote,
        private TratamentoRamoResolver $tratamento,
        private PropertyRegistryLookup $cadastro,
        private AuditService $audit,
    ) {}

    public function protocolar(ReginRecebimento $recebimento): ?ViabilityRequest
    {
        $corpo = $recebimento->corpo ?? [];

        if (($corpo['formato'] ?? null) === 'processo' && is_array($corpo['dados'] ?? null)) {
            return $this->protocolarDeclaracao($recebimento, RecebimentoProcesso::fromStored($corpo['dados']));
        }

        $ruc = $corpo;
        $rowset = is_array($ruc['rowset'] ?? null)
            ? $ruc['rowset']
            : (is_array($ruc['ROWSET'] ?? null) ? $ruc['ROWSET'] : []);
        $rowset = $this->completarPerguntasPeloXml($rowset, $recebimento);

        $cnaes = $this->cnaes($rowset);
        $inscricao = $this->inscricao($rowset);
        $area = $this->area($rowset);
        $endereco = $this->endereco($rowset);

        $precisaDoCadastro = $area === null || $endereco === null;
        $cadastro = $precisaDoCadastro ? $this->cadastroDaInscricao($inscricao) : null;
        $complementados = [];

        if ($area === null && $cadastro !== null) {
            [$area, $origemArea] = $this->areaDoCadastro($cadastro) ?? [null, null];

            if ($origemArea !== null) {
                $complementados[] = $origemArea;
            }
        }

        if ($endereco === null && $cadastro !== null) {
            $endereco = $this->endereco($rowset, $cadastro);

            if ($endereco !== null) {
                $complementados[] = 'endereco';
            }
        }

        if ($cnaes === [] || $area === null || $endereco === null) {
            $this->registrarRecusa($recebimento, $this->motivosDaRecusa(
                semCnae: $cnaes === [],
                semArea: $area === null,
                semEndereco: $endereco === null,
                consultouCadastro: $precisaDoCadastro,
                temInscricao: $inscricao !== null,
                cadastroRespondeu: $cadastro !== null,
            ));

            return null;
        }

        $ator = $this->integracao();

        $solicitacao = ViabilityRequest::query()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'service_type_id' => $this->tipoServico()->id,
            'company_id' => $this->empresa($rowset)->id,
            'requester_user_id' => null,
            'solicitante_nome' => $this->solicitanteDoRowset($rowset)['nome'],
            'solicitante_cpf' => $this->solicitanteDoRowset($rowset)['cpf'],
            'created_by_user_id' => $ator->id,
            'used_area_m2' => $area,
            'address_street' => $endereco['street'],
            'address_number' => $endereco['number'],
            'address_complement' => $endereco['complement'],
            'address_neighborhood' => $endereco['neighborhood'],
            'address_zip' => $endereco['zip'],
            'property_registration' => $inscricao,
            'simulation_snapshot' => $this->snapshotDoRowset($rowset, $cnaes),
            'contingency_reason' => self::CONTINGENCIA_RECEBE,
            'external_reference' => $recebimento->protocolo,
        ]);

        foreach (array_values($cnaes) as $indice => $cnae) {
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        ReginEventos::gravar($solicitacao, $recebimento);

        if ($complementados !== []) {
            $this->audit->log(
                'regin',
                'complementar',
                'Dados do estabelecimento complementados pelo Cadastro Imobiliário',
                ['protocolo' => $recebimento->protocolo, 'campos' => $complementados],
                $solicitacao,
            );
        }

        $this->protocolar->protocol($solicitacao->fresh() ?? $solicitacao, $ator);

        return $solicitacao->fresh() ?? $solicitacao;
    }

    /**
     * @return list<string>
     */
    private function motivosDaRecusa(
        bool $semCnae,
        bool $semArea,
        bool $semEndereco,
        bool $consultouCadastro,
        bool $temInscricao,
        bool $cadastroRespondeu,
    ): array {
        $motivos = [];

        if ($semCnae) {
            $motivos[] = 'sem_cnae';
        }

        if ($semArea) {
            $motivos[] = 'sem_area';
        }

        if ($semEndereco) {
            $motivos[] = 'sem_endereco';
        }

        if ($consultouCadastro && ! $temInscricao) {
            $motivos[] = 'sem_inscricao';
        }

        if ($consultouCadastro && $temInscricao && ! $cadastroRespondeu) {
            $motivos[] = 'cadastro_indisponivel';
        }

        return $motivos;
    }

    /**
     * Recebimento que não vira processo é pendência registrada, nunca silêncio.
     *
     * @param  list<string>  $motivos
     */
    private function registrarRecusa(ReginRecebimento $recebimento, array $motivos): void
    {
        $this->audit->log(
            'regin',
            'protocolar',
            'Processo recebido via REGIN não pôde ser aberto',
            ['protocolo' => $recebimento->protocolo, 'motivos' => $motivos],
            $recebimento,
            'falha',
        );
    }

    private function protocolarDeclaracao(ReginRecebimento $recebimento, RecebimentoProcesso $dados): ?ViabilityRequest
    {
        if (! $dados->podeProtocolar()) {
            $this->registrarRecusa($recebimento, $dados->faltamParaProtocolar());

            return null;
        }

        $ator = $this->integracao();
        $requerente = $this->requerenteDaDeclaracao($dados);
        $cnaePrincipal = $this->cnae((string) $dados->cnaePrincipal);
        $secundarios = [];

        foreach ($dados->cnaesSecundarios as $codigo) {
            if ($codigo === $cnaePrincipal->code) {
                continue;
            }

            $secundarios[] = $this->cnae($codigo);
        }

        $solicitacao = ViabilityRequest::query()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'service_type_id' => $this->tipoServicoDeclarado($dados->servico)->id,
            'company_id' => $this->empresaDaDeclaracao($dados, $recebimento->protocolo)->id,
            'requester_user_id' => $requerente?->id,
            'created_by_user_id' => $ator->id,
            'used_area_m2' => $dados->area,
            'address_street' => $dados->logradouro,
            'address_number' => $dados->numero ?? 's/n',
            'address_complement' => $dados->complemento(),
            'address_neighborhood' => $dados->bairro,
            'address_zip' => $dados->cep,
            'address_reference' => $dados->pontoReferencia,
            'property_registration' => $dados->inscricaoImobiliaria,
            'property_polygon_geojson' => $dados->poligonoDeclarado,
            'has_independent_access' => $dados->acessosIndependentes === true,
            'is_virtual_office' => $dados->escritorioVirtual === true,
            'wants_virtual_office_tenant' => $dados->atividadeAbrigadaEmEscritorioVirtual,
            'wants_virtual_office_hq' => $dados->prestaServicoDeEscritorioVirtual === true,
            'is_public_area' => $dados->atividadeEmAreaPublica === true,
            'simulation_snapshot' => [
                'respostas_tratamento_por_cnae' => $this->respostasPorCnae($dados),
                'perguntas_cnae' => $dados->perguntas,
            ],
            'contingency_reason' => self::CONTINGENCIA_RECEBE,
            'external_reference' => $recebimento->protocolo,
            'origem_declarada' => $dados->toArray(),
        ]);

        $solicitacao->cnaes()->attach($cnaePrincipal->id, ['is_primary' => true]);

        foreach ($secundarios as $cnae) {
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => false]);
        }

        $this->tipoImovel->apply($solicitacao, $dados->tipoImovel);

        $this->protocolar->protocol($solicitacao->fresh() ?? $solicitacao, $ator);

        // Vincula já: o resto (geometria pelo ponto, decisão) pode levar mais de
        // um minuto, e o job morto por timeout nesse meio não pode abrir um
        // segundo processo na nova tentativa.
        $recebimento->update(['viability_request_id' => $solicitacao->id]);

        $ajuste = [];

        if ($dados->codlog !== null) {
            $ajuste['cod_log'] = $dados->codlog;
        }

        if ($dados->dataAbertura !== null) {
            $ajuste['protocoled_at'] = $dados->dataAbertura;
        }

        if ($ajuste !== []) {
            $solicitacao->forceFill($ajuste)->save();
        }

        // Geometria oficial do lote ANTES da decisão: é ela que zoneia pela
        // maior porção. Já fora da transação — fonte indisponível não desfaz
        // o processo. O polígono declarado no REGIN fica em
        // property_polygon_geojson e só vale se a cadeia oficial não achar.
        // Sem polígono pela chave nem pelo SEMAP, busca o lote pelo ponto: este
        // protocolo roda no job do REGIN (120 s), onde a espera é aceitável.
        $this->poligonoLote->aplicar($solicitacao->fresh() ?? $solicitacao, consultarPorPonto: true);

        $this->expresso->decide($solicitacao->fresh() ?? $solicitacao, $ator);

        return $solicitacao->fresh() ?? $solicitacao;
    }

    private function empresaDaDeclaracao(RecebimentoProcesso $dados, string $protocolo): Company
    {
        return Company::query()->create([
            'cnpj' => null,
            'legal_name' => (string) $dados->razaoSocial,
            'size' => $dados->porteEmpresa,
            'street' => $dados->logradouro,
            'number' => $dados->numero,
            'complement' => $dados->complemento(),
            'neighborhood' => $dados->bairro,
            'city' => $dados->municipio,
            'state' => $dados->uf,
            'zip_code' => $dados->cep,
            'source' => CompanySource::Redesim,
            'redesim_protocol' => $protocolo,
            'legal_nature_code' => CategoriaEmpresa::separar($dados->complemento->naturezaJuridica)['codigo'],
            'legal_nature' => CategoriaEmpresa::separar($dados->complemento->naturezaJuridica)['descricao'],
        ]);
    }

    /**
     * Quem a JUCEB indicou como solicitante; sem os dados, ninguém (null).
     */
    public function requerenteDaDeclaracao(RecebimentoProcesso $dados, bool $criar = true): ?User
    {
        return $this->solicitante($dados, $criar);
    }

    /**
     * Autora dos processos recebidos da JUCEB (created_by): nunca uma pessoa real
     * (nem o primeiro usuário, nem quem está logado). Sem e-mail entregável.
     */
    public function integracao(): User
    {
        $existente = User::query()->where('email', User::EMAIL_INTEGRACAO_REGIN)->first();

        if ($existente !== null) {
            return $existente;
        }

        $cpf = '00000000000';

        for ($sufixo = 1; User::query()->where('cpf', $cpf)->exists(); $sufixo++) {
            $cpf = str_pad((string) $sufixo, 11, '0', STR_PAD_LEFT);
        }

        return User::query()->create([
            'name' => 'JUCEB (integração REGIN)',
            'email' => User::EMAIL_INTEGRACAO_REGIN,
            'cpf' => $cpf,
            'password' => Hash::make(Str::random(40)),
            'auth_type' => AuthType::Local,
        ]);
    }

    private function solicitante(RecebimentoProcesso $dados, bool $criar = true): ?User
    {
        $cpf = $dados->cpfSolicitante;
        $email = $dados->emailSolicitante;
        $nome = $dados->nomeSolicitante;

        if ($cpf === null || $email === null || $nome === null) {
            return null;
        }

        $cpfValido = Validator::make(
            ['cpf' => $cpf],
            ['cpf' => [new ValidCpf]],
        )->passes();

        if (! $cpfValido) {
            return null;
        }

        $porCpf = User::query()->where('cpf', $cpf)->first();

        if ($porCpf !== null) {
            return $porCpf;
        }

        if (! $criar) {
            return new User;
        }

        $emailEmUso = User::query()->where('email', $email)->exists();

        return User::query()->create([
            'name' => $nome,
            'email' => $emailEmUso ? $cpf.'@solicitante.viabiliza.local' : $email,
            'cpf' => $cpf,
            'password' => Hash::make(Str::random(40)),
            'auth_type' => AuthType::Local,
        ]);
    }

    private function tipoServicoDeclarado(?string $servico): ViabilityServiceType
    {
        if ($servico === null) {
            return $this->tipoServico();
        }

        $porNome = ViabilityServiceType::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($servico)])
            ->first();

        if ($porNome !== null) {
            return $porNome;
        }

        $code = Str::slug($servico);

        if ($code === '') {
            return $this->tipoServico();
        }

        return ViabilityServiceType::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $servico,
                'flow_hint' => 'expresso',
                'active' => true,
            ],
        );
    }

    /**
     * @return array<string, array<int, bool|int|list<int>>>
     */
    private function respostasPorCnae(RecebimentoProcesso $dados): array
    {
        $mapa = [];

        foreach ($dados->perguntas as $item) {
            $numero = $this->numeroDaPergunta($item['pergunta']);
            $resposta = RespostaPlanilha::deTexto($item['resposta']);

            if ($numero === null || $resposta === null) {
                continue;
            }

            $mapa[$item['cnae']][$numero] = $resposta->paraArmazenar();
        }

        return $mapa;
    }

    /**
     * Snapshot das respostas de um recebimento já gravado, com o mesmo caminho
     * do protocolo: rowset do corpo, completado pelo xml do envelope quando o
     * grupo do json veio vazio. Serve ao reprocesso de recebimentos antigos,
     * que reconstroem o simulation_snapshot sem abrir outro processo.
     *
     * @return array<string, mixed>|null
     */
    public function snapshotDoRecebimento(ReginRecebimento $recebimento): ?array
    {
        $corpo = $recebimento->corpo ?? [];

        if (! is_array($corpo)) {
            return null;
        }

        $rowset = is_array($corpo['rowset'] ?? null)
            ? $corpo['rowset']
            : (is_array($corpo['ROWSET'] ?? null) ? $corpo['ROWSET'] : []);

        if ($rowset === []) {
            return null;
        }

        $rowset = $this->completarPerguntasPeloXml($rowset, $recebimento);

        $processo = ViabilityRequest::query()
            ->where('external_reference', $recebimento->protocolo)
            ->first();

        $cnaes = $processo !== null
            ? $processo->cnaes()->orderByPivot('is_primary', 'desc')->orderBy('code')->get()->all()
            : $this->cnaes($rowset);

        return $this->snapshotDoRowset($rowset, $cnaes);
    }

    /**
     * Snapshot do RUC: as respostas do GROUPPERGUNTAS (Anexo v2.12) casadas com
     * a planilha vigente por CNAE, mais o grupo bruto para auditoria. Null
     * quando o envelope não traz o grupo.
     *
     * @param  array<string, mixed>  $rowset
     * @param  list<Cnae>  $cnaes
     * @return array<string, mixed>|null
     */
    private function snapshotDoRowset(array $rowset, array $cnaes): ?array
    {
        $perguntas = $this->perguntasDoRowset($rowset);

        if ($perguntas === []) {
            return null;
        }

        return [
            'respostas_tratamento_por_cnae' => $this->respostasDoRowsetPorCnae($perguntas, $cnaes),
            'perguntas_regin' => $perguntas,
        ];
    }

    /**
     * A JUCEB entrega as respostas no xml do envelope. O json.ROWSET chega sem
     * GROUPPERGUNTAS — o grupo irmão GROUPPER_PERGUNTAS.PER_PERGUNTAS vem vazio.
     * Sem o xml, o motor protocola como se nenhuma pergunta tivesse resposta.
     *
     * @param  array<string, mixed>  $rowset
     * @return array<string, mixed>
     */
    private function completarPerguntasPeloXml(array $rowset, ReginRecebimento $recebimento): array
    {
        if ($this->perguntasDoRowset($rowset) !== []) {
            return $rowset;
        }

        $xml = $recebimento->envelope['xml'] ?? null;

        if (! is_string($xml) || trim($xml) === '') {
            return $rowset;
        }

        $xml = $this->utf8($xml);

        $linhas = $this->linhasDePerguntaDoXml($xml);

        if ($linhas === []) {
            return $rowset;
        }

        $rowset['GROUPPERGUNTAS'] = ['PERGUNTAS' => $linhas];

        return $rowset;
    }

    /**
     * @return list<array{TIPO: string, CNPJ: string, COD_PERGUNTA: string, PER_PERGUNTA: string, PER_RESPOSTA: string}>
     */
    private function linhasDePerguntaDoXml(string $xml): array
    {
        $errosInternos = libxml_use_internal_errors(true);
        $documento = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($errosInternos);

        if ($documento === false) {
            return [];
        }

        $nos = $documento->xpath('//GROUPPERGUNTAS/PERGUNTAS');

        if ($nos === false || $nos === []) {
            return [];
        }

        $linhas = [];

        foreach ($nos as $no) {
            $linhas[] = [
                'TIPO' => trim((string) $no->TIPO),
                'CNPJ' => trim((string) $no->CNPJ),
                'COD_PERGUNTA' => trim((string) $no->COD_PERGUNTA),
                'PER_PERGUNTA' => trim((string) $no->PER_PERGUNTA),
                'PER_RESPOSTA' => trim((string) $no->PER_RESPOSTA),
            ];
        }

        return $linhas;
    }

    /**
     * GROUPPERGUNTAS do RUC. Uma pergunta só chega como objeto (não lista) na
     * conversão XML→JSON — normalizado aqui.
     *
     * @param  array<string, mixed>  $rowset
     * @return list<array{codigo: ?string, tipo: ?string, cnpj: ?string, pergunta: string, resposta: string}>
     */
    private function perguntasDoRowset(array $rowset): array
    {
        $linhas = $rowset['GROUPPERGUNTAS']['PERGUNTAS'] ?? [];

        if (is_array($linhas) && ! array_is_list($linhas)) {
            $linhas = [$linhas];
        }

        if (! is_array($linhas)) {
            return [];
        }

        $perguntas = [];

        foreach ($linhas as $linha) {
            if (! is_array($linha)) {
                continue;
            }

            $pergunta = trim((string) ($linha['PER_PERGUNTA'] ?? ''));
            $resposta = trim((string) ($linha['PER_RESPOSTA'] ?? ''));

            if ($pergunta === '' || $resposta === '') {
                continue;
            }

            $perguntas[] = [
                'codigo' => $this->textoOuNull($linha['COD_PERGUNTA'] ?? null),
                'tipo' => $this->textoOuNull($linha['TIPO'] ?? null),
                'cnpj' => $this->textoOuNull($linha['CNPJ'] ?? null),
                'pergunta' => $pergunta,
                'resposta' => $resposta,
            ];
        }

        return $perguntas;
    }

    /**
     * Respostas por CNAE: o enunciado se repete em cadeias diferentes da
     * planilha, então o casamento é por enunciado DENTRO das perguntas que o
     * CNAE exibe (TratamentoCnaeBinding) — nunca pelo código REGIN, que é do
     * sistema deles.
     *
     * @param  list<array{codigo: ?string, tipo: ?string, cnpj: ?string, pergunta: string, resposta: string}>  $perguntas
     * @param  list<Cnae>  $cnaes
     * @return array<string, array<int, bool|int|list<int>>>
     */
    private function respostasDoRowsetPorCnae(array $perguntas, array $cnaes): array
    {
        $mapa = [];
        $perguntas = array_values(array_filter(
            $perguntas,
            fn (array $item): bool => $item['tipo'] === null || $item['tipo'] === self::INSTITUICAO_PREFEITURA,
        ));

        foreach ($cnaes as $cnae) {
            $exibidas = $this->tratamento->perguntasExibidas($cnae->code);
            $casadas = [];

            foreach ($perguntas as $indice => $item) {
                $alvo = self::normalizarTexto($item['pergunta']);

                foreach ($exibidas as $exibida) {
                    if (! str_contains(self::normalizarTexto((string) $exibida['texto']), $alvo)) {
                        continue;
                    }

                    $casadas[$indice] = true;
                    $numero = (int) $exibida['numero'];

                    if (isset($mapa[$cnae->code][$numero])) {
                        continue;
                    }

                    $resposta = self::normalizarTexto($item['resposta']) === 'seguir'
                        ? $this->subdivisaoMarcada($perguntas, $exibida['opcoes'])
                        : $this->respostaRegin($item['resposta'], (string) $exibida['tipo'], $exibida['opcoes']);

                    if ($resposta !== null) {
                        $mapa[$cnae->code][$numero] = $resposta->paraArmazenar();
                    }
                }
            }

            foreach ($this->respostasPelaOpcao($perguntas, $casadas, $exibidas, $mapa[$cnae->code] ?? []) as $numero => $resposta) {
                $mapa[$cnae->code][$numero] = $resposta;
            }

            foreach ($this->respostasPorSemelhanca($perguntas, $casadas, $exibidas, $mapa[$cnae->code] ?? []) as $numero => $resposta) {
                $mapa[$cnae->code][$numero] = $resposta;
            }
        }

        return $mapa;
    }

    /**
     * A JUCEB reescreve o enunciado de algumas perguntas ("Informar quais dos
     * serviços citados a seguir…" no lugar do texto da planilha) e responde
     * pelo texto da opção. Sem casar o enunciado, a pergunta de opções ainda
     * sem resposta é resolvida pela linha sobrante cuja resposta é o texto de
     * uma das suas opções — nunca por resposta Sim/Não, que não identifica a
     * pergunta.
     *
     * @param  list<array{codigo: ?string, tipo: ?string, cnpj: ?string, pergunta: string, resposta: string}>  $perguntas
     * @param  array<int, true>  $casadas
     * @param  list<array{numero: int, texto: string, tipo: string, opcoes: list<array{rotulo: string, texto: string}>, depende: array{pergunta: int, sim: bool}|null}>  $exibidas
     * @param  array<int, bool|int|list<int>>  $jaRespondidas
     * @return array<int, bool|int|list<int>>
     */
    private function respostasPelaOpcao(array $perguntas, array $casadas, array $exibidas, array $jaRespondidas): array
    {
        $respostas = [];

        foreach ($exibidas as $exibida) {
            $numero = (int) $exibida['numero'];

            if (isset($jaRespondidas[$numero]) || ! in_array($exibida['tipo'], ['multipla', 'opcao_unica'], true) || $exibida['opcoes'] === []) {
                continue;
            }

            $escolhidas = [];

            foreach ($perguntas as $indice => $item) {
                if (isset($casadas[$indice]) || RespostaPlanilha::deTexto($item['resposta']) !== null) {
                    continue;
                }

                $rotulo = $this->rotuloDaOpcaoPeloTexto($item['resposta'], $exibida['opcoes']);

                if ($rotulo !== null) {
                    $escolhidas[$rotulo] = $rotulo;
                }
            }

            if ($escolhidas !== []) {
                sort($escolhidas);
                $respostas[$numero] = RespostaPlanilha::de(count($escolhidas) === 1 ? $escolhidas[array_key_first($escolhidas)] : $escolhidas)->paraArmazenar();
            }
        }

        return $respostas;
    }

    /**
     * A JUCEB também reescreve enunciados Sim/Não ("Irá prestar serviço de
     * escritório virtual/ centro de negócios?" no lugar de "O estabelecimento
     * prestará o serviço de escritório virtual ou centro de negócios (sede…)?").
     * Pergunta Sim/Não ainda sem resposta casa com a única linha sobrante de
     * resposta Sim/Não cujas palavras o enunciado da planilha contém; havendo
     * mais de uma candidata, nenhuma é usada.
     *
     * @param  list<array{codigo: ?string, tipo: ?string, cnpj: ?string, pergunta: string, resposta: string}>  $perguntas
     * @param  array<int, true>  $casadas
     * @param  list<array{numero: int, texto: string, tipo: string, opcoes: list<array{rotulo: string, texto: string}>, depende: array{pergunta: int, sim: bool}|null}>  $exibidas
     * @param  array<int, bool|int|list<int>>  $jaRespondidas
     * @return array<int, bool|int|list<int>>
     */
    private function respostasPorSemelhanca(array $perguntas, array $casadas, array $exibidas, array $jaRespondidas): array
    {
        $respostas = [];

        foreach ($exibidas as $exibida) {
            $numero = (int) $exibida['numero'];

            if (isset($jaRespondidas[$numero]) || $exibida['tipo'] !== 'sim_nao') {
                continue;
            }

            $palavrasDaPlanilha = self::palavrasDoEnunciado((string) $exibida['texto']);
            $candidatas = [];

            foreach ($perguntas as $indice => $item) {
                $resposta = RespostaPlanilha::deTexto($item['resposta']);
                $palavras = self::palavrasDoEnunciado($item['pergunta']);

                if (isset($casadas[$indice]) || $resposta === null || count($palavras) < self::PALAVRAS_MINIMAS_SEMELHANCA) {
                    continue;
                }

                $cobertura = count(array_intersect($palavras, $palavrasDaPlanilha)) / count($palavras);

                if ($cobertura >= self::COBERTURA_MINIMA_SEMELHANCA) {
                    $candidatas[] = $resposta;
                }
            }

            if (count($candidatas) === 1) {
                $respostas[$numero] = $candidatas[0]->paraArmazenar();
            }
        }

        return $respostas;
    }

    /**
     * Palavras de conteúdo do enunciado, sem acento nem flexão (cinco primeiras
     * letras), para comparar enunciados reescritos.
     *
     * @return list<string>
     */
    private static function palavrasDoEnunciado(string $texto): array
    {
        $semConectivos = ['a', 'o', 'as', 'os', 'de', 'da', 'do', 'das', 'dos', 'e', 'ou', 'em', 'no', 'na', 'um', 'uma', 'se', 'que', 'para', 'por', 'com', 'ser', 'sera'];
        $palavras = preg_split('/[^a-z0-9]+/', Str::ascii(mb_strtolower($texto)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map(
            fn (string $palavra): string => substr($palavra, 0, 5),
            array_filter($palavras, fn (string $palavra): bool => ! in_array($palavra, $semConectivos, true)),
        )));
    }

    /**
     * Texto idêntico à opção (sem o ";" ou "." final) tem prioridade sobre o
     * trecho contido: "Serviços de organização de arquivos" é a opção 3, não a
     * 2 ("…de arquivo de documentos…").
     *
     * @param  list<array{rotulo: string, texto: string}>  $opcoes
     */
    private function rotuloDaOpcaoPeloTexto(string $resposta, array $opcoes): ?int
    {
        $alvo = rtrim(self::normalizarTexto($resposta), ';. ');

        if ($alvo === '') {
            return null;
        }

        $contida = null;

        foreach ($opcoes as $opcao) {
            $texto = rtrim(self::normalizarTexto($opcao['texto']), ';. ');
            $rotulo = trim($opcao['rotulo']);
            $numero = ctype_digit($rotulo) ? (int) $rotulo : ord(mb_strtolower($rotulo)) - ord('a') + 1;

            if ($texto === $alvo) {
                return $numero;
            }

            if ($contida === null && str_contains($texto, $alvo)) {
                $contida = $numero;
            }
        }

        return $contida;
    }

    /**
     * "Seguir" não é resposta: o REGIN abre a lista de subdivisões do CNAE e
     * entrega cada opção como uma pergunta própria, marcada Sim ou Não. A
     * escolha do requerente são as opções marcadas Sim.
     *
     * @param  list<array{codigo: ?string, tipo: ?string, cnpj: ?string, pergunta: string, resposta: string}>  $perguntas
     * @param  list<array{rotulo: string, texto: string}>  $opcoes
     */
    private function subdivisaoMarcada(array $perguntas, array $opcoes): ?RespostaPlanilha
    {
        $marcadas = [];

        foreach ($perguntas as $item) {
            if (self::normalizarTexto($item['resposta']) !== 'sim') {
                continue;
            }

            $alvo = self::normalizarTexto($item['pergunta']);

            foreach ($opcoes as $opcao) {
                $texto = self::normalizarTexto($opcao['texto']);

                if ($texto === $alvo || str_contains($texto, $alvo) || str_contains($alvo, $texto)) {
                    $rotulo = trim($opcao['rotulo']);
                    $marcadas[] = ctype_digit($rotulo) ? (int) $rotulo : ord(mb_strtolower($rotulo)) - ord('a') + 1;

                    break;
                }
            }
        }

        if ($marcadas === []) {
            return null;
        }

        return RespostaPlanilha::de(count($marcadas) === 1 ? $marcadas[0] : $marcadas);
    }

    /**
     * Resposta do REGIN: "Sim"/"Não"/"1,2"/"B" direto; senão, o texto da opção
     * escolhida no formulário deles casa com a opção do enunciado da planilha
     * ("Sim, a atividade será desenvolvida no local." → opção 1). Em pergunta
     * Sim/Não a opção 1 é Sim e a 2 é Não — gravado como booleano legado.
     *
     * @param  list<array{rotulo: string, texto: string}>  $opcoes
     */
    private function respostaRegin(string $resposta, string $tipo, array $opcoes): ?RespostaPlanilha
    {
        $direta = RespostaPlanilha::deTexto($resposta);

        if ($direta !== null) {
            return $direta;
        }

        $alvo = self::normalizarTexto($resposta);

        foreach ($opcoes as $opcao) {
            $textoOpcao = self::normalizarTexto($opcao['texto']);

            if ($textoOpcao !== $alvo && ! str_contains($textoOpcao, $alvo) && ! str_contains($alvo, $textoOpcao)) {
                continue;
            }

            $rotulo = trim((string) $opcao['rotulo']);
            $numero = ctype_digit($rotulo) ? (int) $rotulo : (ord(mb_strtolower($rotulo)) - ord('a') + 1);

            if ($tipo === 'sim_nao') {
                return RespostaPlanilha::de($numero === 1);
            }

            return RespostaPlanilha::de($numero);
        }

        return null;
    }

    private static function normalizarTexto(string $texto): string
    {
        $normalizado = mb_strtolower(trim($texto));
        $normalizado = preg_replace('/\s+/u', ' ', $normalizado) ?? $normalizado;

        return rtrim($normalizado, '? ');
    }

    private function textoOuNull(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * O envelope declara ISO-8859-1 e a JUCEB manda bytes Latin-1. Normaliza
     * para UTF-8: sem isso, o json_encode do envelope inteiro falha ao gravar e
     * o parse do xml não confia na declaração.
     */
    private function utf8(string $texto): string
    {
        if (mb_check_encoding($texto, 'UTF-8')) {
            return preg_replace('/encoding\s*=\s*"[^"]*"/i', 'encoding="UTF-8"', $texto, 1) ?? $texto;
        }

        $convertido = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');

        return preg_replace('/encoding\s*=\s*"[^"]*"/i', 'encoding="UTF-8"', $convertido, 1) ?? $convertido;
    }

    private function numeroDaPergunta(string $pergunta): ?int
    {
        $texto = trim($pergunta);

        if (preg_match('/^\d+$/', $texto) === 1) {
            return (int) $texto;
        }

        $versao = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        if ($versao === null) {
            return null;
        }

        $numero = TratamentoPergunta::query()
            ->where('rule_version_id', $versao->getKey())
            ->whereRaw('lower(texto) = ?', [mb_strtolower($texto)])
            ->value('numero');

        return $numero === null ? null : (int) $numero;
    }

    /**
     * @param  array<string, mixed>  $rowset
     * @return list<Cnae>
     */
    private function cnaes(array $rowset): array
    {
        $linhas = $rowset['GROUPRUC_ACTV_ECON']['RUC_ACTV_ECON'] ?? [];

        if (! is_array($linhas)) {
            return [];
        }

        $principal = [];
        $secundarios = [];

        foreach ($linhas as $linha) {
            if (! is_array($linha)) {
                continue;
            }

            $codigo = preg_replace('/\D/', '', (string) ($linha['RAE_TAE_COD_ACTVD'] ?? ''));

            if ($codigo === '') {
                continue;
            }

            $cnae = $this->cnae($codigo);

            if ((string) ($linha['RAE_CALIF_ACTV'] ?? '') === '1') {
                $principal[] = $cnae;
            } else {
                $secundarios[] = $cnae;
            }
        }

        return [...$principal, ...$secundarios];
    }

    private function cnae(string $code): Cnae
    {
        return Cnae::query()->firstOrCreate(
            ['code' => $code],
            [
                'description' => "CNAE {$code} (REGIN)",
                'section_code' => 'S',
                'section_description' => 'REGIN',
                'division_code' => substr($code, 0, 2) ?: '00',
                'division_description' => 'REGIN',
                'group_code' => substr($code, 0, 3) ?: '000',
                'group_description' => 'REGIN',
                'class_code' => substr($code, 0, 5) ?: '00000',
                'class_description' => 'REGIN',
                'active' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $rowset
     */
    private function area(array $rowset): ?float
    {
        $valor = $rowset['RUC_ESTAB']['RES_AREA'] ?? null;

        if ($valor === null || $valor === '') {
            return null;
        }

        $area = (float) $valor;

        return $area > 0 ? $area : null;
    }

    private function cadastroDaInscricao(?string $inscricao): ?PropertyCadastroCampos
    {
        if ($inscricao === null) {
            return null;
        }

        try {
            return $this->cadastro->resolve($inscricao)->cadastro;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A JUCEB carrega o RES_AREA da inscrição imobiliária; quando vem zerado,
     * a área é a do Cadastro: a construída e, sem ela (imóvel sem construção
     * cadastrada), a do terreno. Base indisponível ou sem área: segue sem
     * processo, nunca com área simulada.
     *
     * @return array{0: float, 1: string}|null área e o campo do Cadastro de onde veio
     */
    private function areaDoCadastro(PropertyCadastroCampos $cadastro): ?array
    {
        $candidatas = [
            'area_construida' => $cadastro->area_construida_m2,
            'area_terreno' => $cadastro->area_terreno_m2,
        ];

        foreach ($candidatas as $origem => $valor) {
            $area = $this->paraArea($valor);

            if ($area !== null && $area > 0) {
                return [$area, $origem];
            }
        }

        return null;
    }

    private function paraArea(?string $valor): ?float
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim($valor);

        if (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', str_replace('.', '', $texto));
        }

        return is_numeric($texto) ? (float) $texto : null;
    }

    /**
     * Endereço do estabelecimento: o que a JUCEB enviou e, campo a campo, o que
     * faltou vem do Cadastro Imobiliário quando ele foi consultado.
     *
     * @param  array<string, mixed>  $rowset
     * @return array{street: string, number: string, complement: ?string, neighborhood: string, zip: string}|null
     */
    private function endereco(array $rowset, ?PropertyCadastroCampos $cadastro = null): ?array
    {
        $estab = is_array($rowset['RUC_ESTAB'] ?? null) ? $rowset['RUC_ESTAB'] : [];
        $doCadastro = $cadastro !== null ? $this->enderecoDoCadastro($cadastro) : ['street' => '', 'number' => ''];

        $streetEnvio = trim((string) ($estab['RES_DIRECCION'] ?? ''));
        $street = $streetEnvio !== '' ? $streetEnvio : $doCadastro['street'];
        $number = trim((string) ($estab['RES_NUME'] ?? ''));

        if ($number === '' && $streetEnvio === '') {
            $number = $doCadastro['number'];
        }

        $neighborhood = trim((string) ($estab['RES_URBANIZACION'] ?? ''));

        if ($neighborhood === '') {
            $neighborhood = trim((string) $cadastro?->bairro);
        }

        $zip = preg_replace('/\D/', '', (string) ($estab['RES_ZONA_POSTAL'] ?? ''));

        if ($zip === '') {
            $zip = preg_replace('/\D/', '', (string) $cadastro?->cep);
        }

        if ($street === '' || $neighborhood === '' || $zip === '') {
            return null;
        }

        $complement = trim((string) ($estab['RES_IDENT_COMP'] ?? ''));

        return [
            'street' => $street,
            'number' => $number !== '' ? $number : 's/n',
            'complement' => $complement !== '' ? $complement : null,
            'neighborhood' => $neighborhood,
            'zip' => $zip,
        ];
    }

    /**
     * O Cadastro entrega logradouro e número numa linha só ("Rua X, 21"), às
     * vezes com uma observação depois do travessão ("Av. Y, 6282 — SUPERMERCADO").
     *
     * @return array{street: string, number: string}
     */
    private function enderecoDoCadastro(PropertyCadastroCampos $cadastro): array
    {
        $texto = trim((string) preg_split('/\s+[—–]\s+/u', trim((string) $cadastro->endereco))[0]);

        if (preg_match('/^(.*?),\s*(\d+\S*)$/u', $texto, $partes) === 1) {
            return ['street' => trim($partes[1]), 'number' => $partes[2]];
        }

        $numero = trim((string) ($cadastro->numero_porta ?? $cadastro->numero_metrico));

        return ['street' => $texto, 'number' => $numero === '0' ? '' : $numero];
    }

    /**
     * @param  array<string, mixed>  $rowset
     */
    private function inscricao(array $rowset): ?string
    {
        $linhas = $rowset['GROUPRUC_GEN_PROTOCOLO']['RUC_GEN_PROTOCOLO'] ?? [];

        if (! is_array($linhas)) {
            return null;
        }

        foreach ($linhas as $linha) {
            if (! is_array($linha)) {
                continue;
            }

            if ((string) ($linha['RGP_TGE_COD_TIP_TAB'] ?? '') !== '5') {
                continue;
            }

            $valor = trim((string) ($linha['RGP_VALOR'] ?? ''));

            return $valor !== '' ? $valor : null;
        }

        return null;
    }

    /**
     * Corrige um processo já protocolado: separa o solicitante da empresa a
     * constituir e passa a razão social pretendida para a empresa. Só atua em
     * empresa cujo documento é um CPF (11 dígitos). Devolve false quando o
     * envelope não traz nada a corrigir.
     */
    public function corrigirEmpresaESolicitante(ViabilityRequest $processo, ReginRecebimento $recebimento, bool $gravar = true): bool
    {
        $corpo = $recebimento->corpo ?? [];
        $rowset = is_array($corpo['rowset'] ?? null)
            ? $corpo['rowset']
            : (is_array($corpo['ROWSET'] ?? null) ? $corpo['ROWSET'] : []);

        $solicitante = $this->solicitanteDoRowset($rowset);
        $pretendida = $this->razaoSocialPretendida($rowset);
        $empresa = $processo->company;

        if ($solicitante['cpf'] === null || $empresa === null || strlen((string) $empresa->cnpj) !== 11) {
            return false;
        }

        if ($gravar) {
            $processo->forceFill([
                'solicitante_nome' => $solicitante['nome'],
                'solicitante_cpf' => $solicitante['cpf'],
            ])->save();

            if ($pretendida !== null && $empresa->legal_name !== $pretendida) {
                $empresa->forceFill(['legal_name' => $pretendida])->save();
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rowset
     */
    private function empresa(array $rowset): Company
    {
        $cnpj = $this->documentoDoRowset($rowset);
        $pretendida = $this->razaoSocialPretendida($rowset);
        $nome = $pretendida ?? trim((string) ($rowset['RUC_GENERAL']['RGE_NOMB'] ?? 'Empresa REGIN'));

        if ($cnpj === '') {
            $cnpj = '00000000000000';
        }

        $natureza = CategoriaEmpresa::separar((string) ($rowset['RUC_COMP']['RCO_TNC_COD_NATUR'] ?? ''));
        $existente = Company::query()->where('cnpj', $cnpj)->first();

        if ($existente !== null) {
            if ($natureza['codigo'] !== null && blank($existente->legal_nature_code)) {
                $existente->forceFill(['legal_nature_code' => $natureza['codigo']])->save();
            }

            if ($pretendida !== null && strlen($cnpj) === 11 && $existente->legal_name !== $pretendida) {
                $existente->forceFill(['legal_name' => $pretendida])->save();
            }

            return $existente;
        }

        return Company::query()->create([
            'cnpj' => $cnpj,
            'legal_name' => $nome !== '' ? $nome : 'Empresa REGIN',
            'source' => CompanySource::Redesim,
            'legal_nature_code' => $natureza['codigo'],
            'legal_nature' => $natureza['descricao'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $rowset
     */
    private function documentoDoRowset(array $rowset): string
    {
        return preg_replace('/\D/', '', (string) ($rowset['RUC_GENERAL']['RGE_CGC_CPF'] ?? '')) ?? '';
    }

    /**
     * Na viabilidade de empresa a constituir o REGIN manda em RUC_GENERAL o nome
     * e o CPF de quem pediu; o CNPJ ainda não existe. Com CNPJ, não há solicitante.
     *
     * @param  array<string, mixed>  $rowset
     * @return array{nome: ?string, cpf: ?string}
     */
    private function solicitanteDoRowset(array $rowset): array
    {
        $documento = $this->documentoDoRowset($rowset);

        if (strlen($documento) !== 11) {
            return ['nome' => null, 'cpf' => null];
        }

        return [
            'nome' => $this->textoOuNull($rowset['RUC_GENERAL']['RGE_NOMB'] ?? null),
            'cpf' => $documento,
        ];
    }

    /**
     * Nome empresarial pretendido (GROUPVIA_PROT_RAZAO_SOCIAL): o de menor ordem
     * de preferência. Vem como lista ou, com um só nome, como um objeto.
     *
     * @param  array<string, mixed>  $rowset
     */
    private function razaoSocialPretendida(array $rowset): ?string
    {
        $nomes = $rowset['GROUPVIA_PROT_RAZAO_SOCIAL']['VIA_PROT_RAZAO_SOCIAL'] ?? null;

        if (! is_array($nomes)) {
            return null;
        }

        if (isset($nomes['VRS_RAZAO_SOCIAL'])) {
            $nomes = [$nomes];
        }

        $escolhido = collect($nomes)
            ->filter(fn (mixed $linha): bool => is_array($linha) && $this->textoOuNull($linha['VRS_RAZAO_SOCIAL'] ?? null) !== null)
            ->sortBy(fn (array $linha): int => (int) ($linha['VRS_ORDEM_PREFERENCIA'] ?? 99))
            ->first();

        return is_array($escolhido) ? $this->textoOuNull($escolhido['VRS_RAZAO_SOCIAL']) : null;
    }

    private function tipoServico(): ViabilityServiceType
    {
        return ViabilityServiceType::query()->firstOrCreate(
            ['code' => 'tvl'],
            [
                'name' => 'Viabilidade locacional',
                'flow_hint' => 'expresso',
                'active' => true,
            ],
        );
    }
}
