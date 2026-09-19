<?php

namespace App\Services\Regin;

use App\Enums\CompanySource;
use App\Enums\ViabilityRequestOrigin;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;

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
    ) {}

    public function protocolar(ReginRecebimento $recebimento): ?ViabilityRequest
    {
        $ruc = $recebimento->corpo ?? [];
        $rowset = is_array($ruc['rowset'] ?? null) ? $ruc['rowset'] : [];

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
                'name' => 'Termo de Viabilidade de Localização',
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
