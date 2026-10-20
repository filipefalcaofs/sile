<?php

namespace App\Services\Vistoria;

use App\Http\Resources\ProcessoResource;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Geo\DivergenciaLogradouroInscricao;
use App\Support\ParecerDecisao;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * PDF da capa de consulta do processo para levar à vistoria: identificação,
 * imóvel, motivo da análise e orientações do analista, no layout do Report
 * Design System (barra, cartões e rodapé do relatório operacional). Só lê o
 * processo; a emissão é auditada no controller.
 */
class ConsultaVistoriaPdfService
{
    public function __construct(private readonly DivergenciaLogradouroInscricao $divergenciaLogradouro) {}

    public function render(ViabilityRequest $processo, ?User $emissor): DomPdf
    {
        return Pdf::loadView('documentos.consulta-vistoria', $this->dados($processo, $emissor))
            ->setPaper('a4', 'portrait');
    }

    public function nomeDoArquivo(ViabilityRequest $processo): string
    {
        $protocolo = preg_replace('/[^A-Za-z0-9-]+/', '-', (string) ($processo->protocol_number ?? $processo->id));

        return "consulta-vistoria-{$protocolo}.pdf";
    }

    /**
     * Dados do Blade. Público para os testes verificarem o conteúdo sem
     * descomprimir o PDF.
     *
     * @return array<string, mixed>
     */
    public function dados(ViabilityRequest $processo, ?User $emissor): array
    {
        $processo->loadMissing([
            'company',
            'sector:id,name',
            'assignedTo:id,name',
            'eventos',
            'cnaes',
            'decision',
            'inspection.vistoriador:id,name',
            'inspectionReferral.analistaOrigem:id,name',
            'inspectionReferral.encaminhadoPor:id,name',
            'inspectionReferral.setorOrigem:id,name',
            'inspectionReferral.setorVistoria:id,name',
        ]);

        $despacho = $processo->inspectionReferral;
        $ficha = $processo->inspection;

        return [
            'titulo' => 'Consulta do processo para vistoria',
            'processo' => (new ProcessoResource($processo))->resolve(),
            'despacho' => $despacho === null ? null : [
                'orientacoes' => $despacho->motivo,
                'analista' => $despacho->analistaOrigem?->name ?? $despacho->encaminhadoPor?->name,
                'setor_origem' => $despacho->setorOrigem?->name,
                'setor_vistoria' => $despacho->setorVistoria?->name,
                'encaminhado_em' => $despacho->created_at,
            ],
            'parecer' => ParecerDecisao::de($processo->decision),
            'divergenciaEndereco' => $this->divergenciaLogradouro->avaliar($processo),
            'localizacao' => $this->localizacao($processo),
            'camadas' => $this->linhasDeCamadas($processo->camadas_incidentes),
            'vistoria' => [
                'vistoriador' => $ficha?->vistoriador?->name ?? $processo->assignedTo?->name ?? 'Não distribuído',
                'situacao' => $ficha?->status?->label(),
            ],
            'geradoEm' => now(),
            'responsavel' => $emissor?->name ?? '—',
        ];
    }

    /**
     * Localização em texto (o PDF não leva o mapa): ponto, origem e se há desenho do lote.
     *
     * @return array{coordenadas: ?string, origem: ?string, desenho_lote: bool, poligono_enderecamento: bool}
     */
    private function localizacao(ViabilityRequest $processo): array
    {
        $ponto = $processo->pontoDoImovel();

        return [
            'coordenadas' => $ponto === null ? null : number_format($ponto['lat'], 6, ',', '').', '.number_format($ponto['lng'], 6, ',', ''),
            'origem' => $processo->property_location_source?->label(),
            'desenho_lote' => filled($processo->property_polygon_geojson),
            'poligono_enderecamento' => filled($processo->address_polygon_geojson),
        ];
    }

    /**
     * Camadas do imóvel como a tela as lista: logradouro, bairro, zona, via e
     * cada camada poligonal com o elemento identificado.
     *
     * @param  array<string, mixed>|null  $camadas
     * @return list<array{camada: string, elemento: string, destaque: bool}>
     */
    private function linhasDeCamadas(?array $camadas): array
    {
        if (blank($camadas)) {
            return [];
        }

        $logradouro = $camadas['logradouro_tributario'] ?? null;
        $linhas = [
            ['Logradouro da inscrição (SEFAZ)', is_array($logradouro) ? trim(($logradouro['cod_log'] ?? '').' — '.($logradouro['nome'] ?? '')) : '—', false],
            ['Bairro', $this->textoDimensao($camadas['bairro'] ?? null), false],
            ['Zona', $this->textoZona($camadas['zona'] ?? null), false],
            ['Via', filled($camadas['via'] ?? null) ? trim((string) $camadas['via']) : '—', false],
        ];

        foreach ($camadas['camadas'] ?? [] as $camada) {
            $status = $camada['status'] ?? '';
            $elemento = match (true) {
                $status === 'indisponivel' => 'Camada indisponível',
                $status === 'fora' => 'Fora da camada',
                default => ($camada['elemento'] ?? 'Dentro da camada').(($camada['incide'] ?? false) ? ' — regra incide' : ''),
            };
            $linhas[] = [$camada['titulo'] ?? $camada['regra'] ?? '—', $elemento, ($camada['incide'] ?? null) === true];
        }

        return array_map(fn (array $l): array => ['camada' => $l[0], 'elemento' => $l[1], 'destaque' => $l[2]], $linhas);
    }

    /**
     * @param  array{status?: string, nome?: ?string}|null  $dimensao
     */
    private function textoDimensao(?array $dimensao): string
    {
        if ($dimensao === null) {
            return '—';
        }

        if (($dimensao['status'] ?? '') === 'identificado' && filled($dimensao['nome'] ?? null)) {
            return (string) $dimensao['nome'];
        }

        return ($dimensao['status'] ?? '') === 'indisponivel' ? 'Camada indisponível' : 'Não identificado';
    }

    /**
     * @param  array<string, mixed>|null  $zona
     */
    private function textoZona(?array $zona): string
    {
        if ($zona === null) {
            return '—';
        }

        if (($zona['status'] ?? '') !== 'identificado' || blank($zona['nome'] ?? null)) {
            return ($zona['status'] ?? '') === 'indisponivel' ? 'Camada indisponível' : 'Não identificado';
        }

        $fonte = ['ponto' => 'ponto da inscrição', 'enderecamento_maior_porcao' => 'maior porção do lote'][$zona['fonte'] ?? ''] ?? null;
        $porcoes = $zona['porcoes'] ?? [];
        $detalhe = count($porcoes) > 1
            ? ' ('.collect($porcoes)->map(fn (array $p): string => $p['zona'].' '.number_format((float) $p['percentual'], 1, ',', '').'%')->implode(' · ').')'
            : '';

        return $fonte ? "{$zona['nome']} — {$fonte}{$detalhe}" : "{$zona['nome']}{$detalhe}";
    }
}
