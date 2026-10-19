<?php

namespace App\Services\Vistoria;

use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Inspection;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisStatusStateMachine;
use App\Services\Solicitacao\PropertyGeometryWriter;
use App\Support\Audit\AuditService;

/**
 * Ciclo de vida da ficha de vistoria: abertura (identificação automática +
 * snapshot da localização do processo), rascunho parcial, redesenho/validação
 * do polígono e conclusão (que torna a ficha imutável).
 *
 * Autoria: quem abre a ficha é o vistoriador. Processo COM responsável
 * atribuído (caixa do setor) só pode ser aberto por ele; SEM responsável,
 * quem abre primeiro assume. Só o vistoriador edita — os demais leem.
 *
 * Integração com o eixo operacional: concluir a ficha de um processo em
 * Vistoriar transiciona para Vistoriado pela state machine (auditado); fora
 * de Vistoriar o status não é tocado (a ficha é ortogonal à decisão).
 */
class InspectionService
{
    public function __construct(
        private PropertyGeometryWriter $geometry,
        private AnalysisStatusStateMachine $statusMachine,
        private AuditService $audit,
    ) {}

    /**
     * Abre a ficha do processo ou retoma a existente. Na primeira abertura
     * grava a identificação (tipo, abertura, vistoriador) e o SNAPSHOT da
     * localização do processo — o cadastro pode mudar depois, a ficha preserva
     * o que foi vistoriado. O polígono nasce do property_polygon_geojson.
     */
    public function abrirOuRetomar(ViabilityRequest $processo, User $vistoriador): Inspection
    {
        $existente = Inspection::query()
            ->where('viability_request_id', $processo->id)
            ->latest('id')
            ->first();

        if ($existente !== null) {
            return $existente->status === InspectionStatus::EmPreenchimento
                ? $this->completarLocalizacao($existente, $processo)
                : $existente;
        }

        $this->garantirPodeAssumir($processo, $vistoriador);

        $snapshot = is_array($processo->simulation_snapshot) ? $processo->simulation_snapshot : [];

        $ficha = new Inspection;
        $ficha->viability_request_id = $processo->id;
        $ficha->forceFill([
            'tipo' => InspectionType::Localizacao,
            'status' => InspectionStatus::EmPreenchimento,
            'vistoriador_user_id' => $vistoriador->id,
            'opened_at' => now(),
            'cod_logradouro' => $this->texto($processo->cod_log),
            'logradouro' => $this->logradouroComTipo($processo),
            'numero_metrico' => $processo->address_number,
            'bairro' => $processo->address_neighborhood,
            'cep' => $processo->address_zip,
            'ponto_referencia' => $processo->address_reference,
            'zona' => $processo->zona_codigo ?? data_get($snapshot, 'zona'),
            'via' => $this->viaDoProcesso($processo),
            'polygon_geojson' => $processo->property_polygon_geojson,
        ]);
        $ficha->save();

        return $ficha;
    }

    /**
     * Ficha ainda em preenchimento aberta antes de CodLog, Via e tipo do
     * logradouro entrarem no snapshot: completa só o que está em branco. A
     * ficha concluída não é tocada.
     */
    private function completarLocalizacao(Inspection $ficha, ViabilityRequest $processo): Inspection
    {
        $logradouro = $this->logradouroComTipo($processo);

        $ficha->cod_logradouro ??= $this->texto($processo->cod_log);
        $ficha->via ??= $this->viaDoProcesso($processo);

        if ($logradouro !== null && $ficha->logradouro === $processo->address_street) {
            $ficha->logradouro = $logradouro;
        }

        if ($ficha->isDirty()) {
            $ficha->save();
        }

        return $ficha;
    }

    /**
     * Logradouro com o tipo declarado (rua, avenida, travessa...), sem repeti-lo
     * quando o nome já começa por ele.
     */
    private function logradouroComTipo(ViabilityRequest $processo): ?string
    {
        $rua = $this->texto($processo->address_street);
        $declarado = is_array($processo->origem_declarada) ? $processo->origem_declarada : [];
        $tipo = $this->texto($declarado['tipo_logradouro'] ?? null);

        if ($rua === null || $tipo === null || str_starts_with(mb_strtolower($rua), mb_strtolower($tipo))) {
            return $rua;
        }

        return $tipo.' '.$rua;
    }

    private function viaDoProcesso(ViabilityRequest $processo): ?string
    {
        $snapshot = is_array($processo->simulation_snapshot) ? $processo->simulation_snapshot : [];

        return $this->texto(data_get($snapshot, 'via')) ?? $this->texto(data_get($processo->camadas_incidentes, 'via'));
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor) && ! is_numeric($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * Salva o rascunho: atualiza só os campos enviados (validados pelo Form
     * Request). Numa ficha concluída recusa — imutável. Só o vistoriador edita.
     *
     * @param  array<string, mixed>  $dados
     */
    public function salvarRascunho(Inspection $ficha, array $dados, User $ator): Inspection
    {
        $this->garantirEditavel($ficha);
        $this->garantirAutoria($ficha, $ator);

        $ficha->fill($dados);
        $ficha->save();

        return $ficha;
    }

    /**
     * Redesenha e valida o polígono da vistoria: grava o novo GeoJSON, a área
     * calculada (ST_Area no pgsql; aproximação planar no SQLite) e a marca de
     * validação. O novo desenho fica associado à ficha. Só o vistoriador.
     *
     * @param  array<string, mixed>  $geojson
     */
    public function validarPoligono(Inspection $ficha, array $geojson, User $ator): Inspection
    {
        $this->garantirEditavel($ficha);
        $this->garantirAutoria($ficha, $ator);

        $ficha->forceFill([
            'polygon_geojson' => $geojson,
            'polygon_area_m2' => $this->geometry->polygonAreaSquareMeters($geojson),
            'polygon_validated_at' => now(),
        ]);
        $ficha->save();

        return $ficha;
    }

    /**
     * Conclui a ficha: grava os dados finais (o parecer obrigatório é exigido
     * pelo Form Request) e sela status/concluded_at. Imutável a partir daí.
     * Processo em Vistoriar transiciona para Vistoriado e fica com o vistoriador
     * até ele encaminhá-lo à Caixa do Setor (EncaminharVistoriaService).
     *
     * @param  array<string, mixed>  $dados
     */
    public function concluir(Inspection $ficha, array $dados, User $ator): Inspection
    {
        $this->garantirEditavel($ficha);
        $this->garantirAutoria($ficha, $ator);

        $ficha->fill($dados);
        $ficha->forceFill([
            'status' => InspectionStatus::Concluida,
            'concluded_at' => now(),
        ]);
        $ficha->save();

        $processo = $ficha->viabilityRequest;

        if ($processo?->analysis_status === AnalysisStatus::Vistoriar) {
            $this->statusMachine->transition(
                $processo,
                AnalysisStatus::Vistoriado,
                $ator,
                'Ficha de vistoria concluída.',
            );
        }

        return $ficha;
    }

    /**
     * Só o vistoriador que abriu a ficha pode alterá-la — usado também pelo
     * controller para os anexos.
     */
    public function garantirAutoria(Inspection $ficha, User $ator): void
    {
        if ($ficha->vistoriador_user_id !== $ator->id) {
            throw new InspectionAutoriaException(
                'Só o vistoriador que abriu a ficha pode alterá-la.'
            );
        }
    }

    /**
     * Processo com responsável atribuído (caixa do setor) só abre ficha por
     * ele; sem responsável, quem abre primeiro assume a vistoria.
     */
    private function garantirPodeAssumir(ViabilityRequest $processo, User $vistoriador): void
    {
        $responsavel = $processo->assigned_user_id;

        if ($responsavel === null) {
            throw new InspectionAtribuidaException(
                'O processo ainda não foi distribuído a um vistoriador. A atribuição é feita pela distribuição, na Caixa de vistoria.'
            );
        }

        if ($responsavel !== $vistoriador->id) {
            throw new InspectionAtribuidaException(
                'O processo está atribuído a outro responsável. A ficha de vistoria só pode ser aberta por ele.'
            );
        }
    }

    private function garantirEditavel(Inspection $ficha): void
    {
        if ($ficha->isConcluida()) {
            throw new InspectionConcluidaException(
                'A ficha de vistoria está concluída e não pode mais ser alterada.'
            );
        }
    }
}
