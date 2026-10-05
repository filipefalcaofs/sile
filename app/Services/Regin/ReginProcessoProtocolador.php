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
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Protocola o processo a partir do RUC do envelope REGIN — sem o catálogo do
 * simulador. O que o RUC não trouxer (CNAE principal, área, endereço) NÃO
 * protocola: retorna null e a pendência fica visível, nunca processo inventado.
 */
class ReginProcessoProtocolador
{
    public function __construct(
        private ProtocolarSolicitacaoService $protocolar,
        private FluxoExpressoService $expresso,
        private ReginTipoImovelApplier $tipoImovel,
        private PoligonoEnderecamentoService $poligonoLote,
    ) {}

    public function protocolar(ReginRecebimento $recebimento): ?ViabilityRequest
    {
        $corpo = $recebimento->corpo ?? [];

        if (($corpo['formato'] ?? null) === 'processo' && is_array($corpo['dados'] ?? null)) {
            return $this->protocolarDeclaracao($recebimento, RecebimentoProcesso::fromStored($corpo['dados']));
        }

        $rowset = $this->rowset($corpo);

        $cnaes = $this->cnaes($rowset);
        $area = $this->area($rowset);
        $endereco = $this->endereco($rowset);

        if ($cnaes === [] || $area === null || $endereco === null) {
            return null;
        }

        $ator = $this->ator();

        $solicitacao = ViabilityRequest::query()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'service_type_id' => $this->tipoServico()->id,
            'company_id' => $this->empresa($rowset)->id,
            'requester_user_id' => $ator->id,
            'created_by_user_id' => $ator->id,
            'used_area_m2' => $area,
            'address_street' => $endereco['street'],
            'address_number' => $endereco['number'],
            'address_complement' => $endereco['complement'],
            'address_neighborhood' => $endereco['neighborhood'],
            'address_zip' => $endereco['zip'],
            'property_registration' => $this->inscricao($rowset),
            'contingency_reason' => ReginProtocoloSimulacaoService::CONTINGENCIA_RECEBE,
            'external_reference' => $recebimento->protocolo,
        ]);

        foreach (array_values($cnaes) as $indice => $cnae) {
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        $this->protocolar->protocol($solicitacao->fresh() ?? $solicitacao, $ator);
        $this->expresso->decide($solicitacao->fresh() ?? $solicitacao, $ator);

        return $solicitacao->fresh() ?? $solicitacao;
    }

    /**
     * A OpenAPI da integração define `rowset`, mas o envio real da JUCEB chega
     * como `ROWSET` (conversão do XML). Aceita as duas grafias.
     *
     * @param  array<string, mixed>  $corpo
     * @return array<string, mixed>
     */
    private function rowset(array $corpo): array
    {
        foreach (['rowset', 'ROWSET'] as $chave) {
            if (is_array($corpo[$chave] ?? null)) {
                return $corpo[$chave];
            }
        }

        return [];
    }

    private function protocolarDeclaracao(ReginRecebimento $recebimento, RecebimentoProcesso $dados): ?ViabilityRequest
    {
        if (! $dados->podeProtocolar()) {
            return null;
        }

        $ator = $this->atorDaDeclaracao($dados);
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
            'requester_user_id' => $ator->id,
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
            'contingency_reason' => ReginProtocoloSimulacaoService::CONTINGENCIA_RECEBE,
            'external_reference' => $recebimento->protocolo,
            'origem_declarada' => $dados->toArray(),
        ]);

        $solicitacao->cnaes()->attach($cnaePrincipal->id, ['is_primary' => true]);

        foreach ($secundarios as $cnae) {
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => false]);
        }

        $this->tipoImovel->apply($solicitacao, $dados->tipoImovel);

        $this->protocolar->protocol($solicitacao->fresh() ?? $solicitacao, $ator);

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
        $this->poligonoLote->aplicar($solicitacao->fresh() ?? $solicitacao);

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
        ]);
    }

    private function atorDaDeclaracao(RecebimentoProcesso $dados): User
    {
        $solicitante = $this->solicitante($dados);

        if ($solicitante !== null) {
            return $solicitante;
        }

        $existente = User::query()->orderBy('id')->first();

        if ($existente !== null) {
            return $existente;
        }

        throw new RuntimeException('Solicitante ausente e não há usuário para protocolar.');
    }

    private function solicitante(RecebimentoProcesso $dados): ?User
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
     * @return array<string, array<int, bool>>
     */
    private function respostasPorCnae(RecebimentoProcesso $dados): array
    {
        $mapa = [];

        foreach ($dados->perguntas as $item) {
            $numero = $this->numeroDaPergunta($item['pergunta']);
            $resposta = RecebimentoProcesso::booleano($item['resposta']);

            if ($numero === null || $resposta === null) {
                continue;
            }

            $mapa[$item['cnae']][$numero] = $resposta;
        }

        return $mapa;
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

    /**
     * @param  array<string, mixed>  $rowset
     * @return array{street: string, number: string, complement: ?string, neighborhood: string, zip: string}|null
     */
    private function endereco(array $rowset): ?array
    {
        $estab = is_array($rowset['RUC_ESTAB'] ?? null) ? $rowset['RUC_ESTAB'] : [];
        $street = trim((string) ($estab['RES_DIRECCION'] ?? ''));
        $number = trim((string) ($estab['RES_NUME'] ?? ''));
        $neighborhood = trim((string) ($estab['RES_URBANIZACION'] ?? ''));
        $zip = preg_replace('/\D/', '', (string) ($estab['RES_ZONA_POSTAL'] ?? ''));

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
     * @param  array<string, mixed>  $rowset
     */
    private function empresa(array $rowset): Company
    {
        $cnpj = preg_replace('/\D/', '', (string) ($rowset['RUC_GENERAL']['RGE_CGC_CPF'] ?? ''));
        $nome = trim((string) ($rowset['RUC_GENERAL']['RGE_NOMB'] ?? 'Empresa REGIN'));

        if ($cnpj === '') {
            $cnpj = '00000000000000';
        }

        $existente = Company::query()->where('cnpj', $cnpj)->first();

        if ($existente !== null) {
            return $existente;
        }

        return Company::query()->create([
            'cnpj' => $cnpj,
            'legal_name' => $nome !== '' ? $nome : 'Empresa REGIN',
            'source' => CompanySource::Redesim,
        ]);
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

    private function ator(): User
    {
        $autenticado = auth('gestao')->user();

        if ($autenticado instanceof User) {
            return $autenticado;
        }

        $existente = User::query()->orderBy('id')->first();

        if ($existente !== null) {
            return $existente;
        }

        return User::factory()->create();
    }
}
