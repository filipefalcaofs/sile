<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ConfiabilidadeConsulta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ConsultaViabilidadeCnaeRequest;
use App\Http\Requests\Portal\ConsultaViabilidadeEnderecoRequest;
use App\Http\Requests\Portal\ConsultaViabilidadeInscricaoRequest;
use App\Models\PropertyType;
use App\Models\ViabilityQuery;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\ForaDeSalvadorException;
use App\Services\Geo\GeocoderException;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Tratamento\CategoriaEmpresa;
use App\Services\Tratamento\RespostaPlanilha;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta prévia de viabilidade PÚBLICA (HU-052/053/054): a página e os 3
 * endpoints JSON (endereço/CNAE/inscrição) são acessíveis ao cidadão ANÔNIMO,
 * com throttle parametrizado (07-01) e auditoria RN-002. O controller só
 * orquestra via ConsultaViabilidadeService (07-05) — a auditoria de sucesso é
 * interna ao serviço. Traduz as exceções honestas do geocoder (endereço não
 * localizado / serviço indisponível NUNCA viram resultado falso — sem fachada).
 */
class ConsultaViabilidadeController extends Controller
{
    public function __construct(
        private ConsultaViabilidadeService $service,
        private TratamentoRamoResolver $ramos,
        private DecisionTextCatalog $textos,
    ) {}

    /**
     * Página pública da consulta (a UI completa é construída no 07-08; aqui só o
     * backend da rota). `consultaEnabled` comunica o toggle à interface.
     */
    public function index(): Response
    {
        return Inertia::render('portal/viabilidade/consulta', [
            'consultaEnabled' => true,
            'tiposImovel' => PropertyType::query()->active()->orderBy('label')->get(['label'])
                ->map(fn (PropertyType $tipo): array => ['value' => $tipo->label, 'label' => $tipo->label])
                ->values()
                ->all(),
            'categoriasEmpresa' => [
                ['value' => CategoriaEmpresa::ORGAO_PUBLICO, 'label' => 'Órgão público'],
                ['value' => CategoriaEmpresa::OUTRA, 'label' => 'Outra'],
            ],
        ]);
    }

    /**
     * Consulta por ENDEREÇO (HU-052): geocodifica → território → motores. Traduz
     * as exceções do geocoder em mensagens honestas — nunca resultado falso.
     */
    public function endereco(ConsultaViabilidadeEnderecoRequest $request): JsonResponse
    {
        try {
            $result = $this->service->consultarPorEndereco(
                $request->validated('endereco'),
                $request->validated('cnae'),
                $request->validated('area') !== null ? (float) $request->validated('area') : null,
                $this->respostasInformadas($request->validated('respostas')),
                $this->tipoImovelInformado($request->validated('tipo_imovel')),
                $request->validated('categoria_empresa'),
                $this->vagasInformadas($request->validated('vagas')),
            );
        } catch (ForaDeSalvadorException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['endereco' => [$exception->getMessage()]],
            ], 422);
        } catch (AddressNotFoundException) {
            return response()->json([
                'message' => 'Endereço não localizado. Revise o endereço ou posicione a consulta por CNAE.',
            ], 404);
        } catch (GeocoderException) {
            return response()->json([
                'message' => 'Serviço de geocodificação indisponível no momento. Tente novamente em instantes.',
            ], 503);
        }

        $this->registrarHistorico($request, $result);

        return $this->jsonComPerguntas($result, $request->validated('cnae'));
    }

    /**
     * Consulta por CNAE (HU-054): risco real + enquadramento da planilha por área, SEM território.
     * Não há geocodificação — sem AddressNotFoundException/GeocoderException a
     * tratar; o veredito locacional fica pendente e o serviço já avisa que a
     * consulta não avalia o local.
     */
    public function cnae(ConsultaViabilidadeCnaeRequest $request): JsonResponse
    {
        $result = $this->service->consultarPorCnae(
            $request->validated('cnae'),
            $request->validated('area') !== null ? (float) $request->validated('area') : null,
            $this->tipoImovelInformado($request->validated('tipo_imovel')),
            $this->respostasInformadas($request->validated('respostas')),
            $request->validated('categoria_empresa'),
            $this->vagasInformadas($request->validated('vagas')),
        );

        $this->registrarHistorico($request, $result);

        return $this->jsonComPerguntas($result, $request->validated('cnae'));
    }

    /**
     * Consulta por INSCRIÇÃO imobiliária (HU-053): o serviço resolve o ponto pelo
     * contrato (pipeline completa) OU degrada para a via CNAE com aviso quando a
     * base de lotes está indisponível (pendente SEDUR). A degradação é comunicada
     * via `avisos` no resultado (200) — não é erro HTTP: a consulta funcionou, só
     * não resolveu o ponto. O serviço captura a indisponibilidade; o controller
     * não trata exceção aqui (nunca inventa coordenada).
     */
    public function inscricao(ConsultaViabilidadeInscricaoRequest $request): JsonResponse
    {
        $result = $this->service->consultarPorInscricao(
            $request->validated('inscricao'),
            $request->validated('cnae'),
            $request->validated('area') !== null ? (float) $request->validated('area') : null,
            $this->respostasInformadas($request->validated('respostas')),
            $this->tipoImovelInformado($request->validated('tipo_imovel')),
            $request->validated('categoria_empresa'),
            $this->vagasInformadas($request->validated('vagas')),
        );

        $this->registrarHistorico($request, $result);

        return $this->jsonComPerguntas($result, $request->validated('cnae'));
    }

    /**
     * Histórico pessoal (HU-058): grava o SNAPSHOT imutável da consulta SOMENTE
     * quando o usuário está autenticado (guard web). A consulta anônima segue
     * auditada (RN-002, no serviço), mas NÃO cria histórico pessoal. Chamado
     * apenas no caminho de SUCESSO e nunca para consulta incompleta (faltou dado,
     * não houve resultado a comprovar) — erros honestos (endereço não localizado,
     * serviço indisponível, toggle off) nunca viram histórico (sem fachada). O
     * snapshot guarda entrada + resultado + versões da época (reprodução).
     */
    private function registrarHistorico(Request $request, ConsultaViabilidadeResult $result): void
    {
        $user = $request->user();

        if ($user === null || $result->confiabilidade()->estado === ConfiabilidadeConsulta::Incompleta) {
            return;
        }

        $payload = $result->toArray();

        ViabilityQuery::create([
            'user_id' => $user->id,
            'entry_type' => $payload['entrada']['tipo'],
            'input' => $payload['entrada'],
            'result' => $payload,
            'rules_versions' => $payload['versoes'],
            'resultado' => $payload['veredito_locacional']['resultado'],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<int|string, mixed>|null  $informadas
     * @return array<int, bool|int|list<int>>
     */
    private function respostasInformadas(?array $informadas): array
    {
        $respostas = [];

        foreach ($informadas ?? [] as $numero => $valor) {
            $respostas[(int) $numero] = $valor === 'true' || $valor === 'false'
                ? $valor === 'true'
                : RespostaPlanilha::de($valor)->paraArmazenar();
        }

        return $respostas;
    }

    private function jsonComPerguntas(ConsultaViabilidadeResult $result, string $cnae): JsonResponse
    {
        $payload = $result->toArray();
        $payload['perguntas'] = $this->ramos->perguntasExibidas($cnae);
        $payload['aviso_simulacao'] = $this->textos->get('consulta.aviso.simulacao');

        if (! $result->confiabilidade()->conclusiva()) {
            $payload['veredito_locacional'] = null;
        }

        return response()->json($payload);
    }

    private function tipoImovelInformado(?string $label): ?TipoImovel
    {
        return $label === null || trim($label) === ''
            ? null
            : TipoImovel::fromRegin($label, TipoImovelCatalog::vigente());
    }

    /**
     * @param  array<string, mixed>|null  $informadas
     * @return array<string, float>
     */
    private function vagasInformadas(?array $informadas): array
    {
        return array_map(floatval(...), $informadas ?? []);
    }
}
