<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use App\Models\Activity;
use App\Models\AnalysisRecord;
use App\Models\Inspection;
use App\Models\User;
use App\Models\ViabilityRequest;

/**
 * Eventos da tramitação do processo que não são mudança de status: situação da
 * análise, atribuições, pendências e convites, vistoria, malha fina, anexos,
 * TVL, a ficha de análise salva pelo botão ou finalizada, a ficha de vistoria
 * concluída e os envios à JUCEB e à SEFAZ. Vêm da auditoria por uma lista
 * fechada de eventos: consulta de tela, autosave automático da ficha e demais
 * registros técnicos não entram. Os eventos das fichas são gravados com a
 * própria ficha como sujeito. Cada item tem o mesmo formato da linha do tempo
 * de status; o evento da ficha de análise traz o link para abri-la.
 *
 * Uma ação, uma linha: o espelho da situação de análise e o registro de
 * pendência/convite gravados junto com uma mudança de situação do processo
 * ficam de fora, assim como o "Encaminhado para" gravado junto com a
 * atribuição. O passo automático "Analisar" e os avisos de notificação não
 * entram.
 */
class TramitacaoProcessoService
{
    private const ATRIBUICAO = ['distribuir', 'assumir', 'redistribuir'];

    private const FICHA_ANALISE = ['ficha-salvar', 'ficha-finalizar'];

    private const PENDENCIA = ['pendencia-aberta', 'pendencia-respondida', 'convite-cancelado', 'convite-expirado-indeferido'];

    private const EIXO_DO_CONVITE = [AnalysisStatus::EmConvite, AnalysisStatus::ConviteRespondido, AnalysisStatus::ConviteCancelado, AnalysisStatus::ConviteExpirado];

    /** Registros gravados na mesma ação ficam a segundos um do outro. */
    private const MESMO_MOMENTO_SEGUNDOS = 60;

    /** @var array<string, array{tipo: string, titulo: string}> */
    private const EVENTOS = [
        'distribuir' => ['tipo' => 'atribuicao', 'titulo' => 'Distribuído para análise'],
        'assumir' => ['tipo' => 'atribuicao', 'titulo' => 'Processo assumido'],
        'redistribuir' => ['tipo' => 'atribuicao', 'titulo' => 'Redistribuído'],
        'pendencia-aberta' => ['tipo' => 'pendencia', 'titulo' => 'Pendência aberta'],
        'pendencia-respondida' => ['tipo' => 'pendencia', 'titulo' => 'Pendência respondida'],
        'convite-cancelado' => ['tipo' => 'pendencia', 'titulo' => 'Convite cancelado'],
        'convite-expirado-indeferido' => ['tipo' => 'pendencia', 'titulo' => 'Convite expirado'],
        'vistoria-encaminhar' => ['tipo' => 'vistoria', 'titulo' => 'Encaminhado à vistoria'],
        'vistoria-assumir' => ['tipo' => 'vistoria', 'titulo' => 'Vistoria assumida'],
        'vistoria-devolver' => ['tipo' => 'vistoria', 'titulo' => 'Vistoria devolvida'],
        'malha-fina-encaminhar' => ['tipo' => 'malha_fina', 'titulo' => 'Encaminhado à malha fina'],
        'malha-fina-resolver' => ['tipo' => 'malha_fina', 'titulo' => 'Malha fina resolvida'],
        'documento-anexado' => ['tipo' => 'documento', 'titulo' => 'Documento anexado'],
        'tvl-emitido' => ['tipo' => 'documento', 'titulo' => 'TVL emitido'],
        'ficha-conclusao' => ['tipo' => 'vistoria', 'titulo' => 'Ficha de vistoria concluída'],
        'zona-via-alterada' => ['tipo' => 'analise', 'titulo' => 'Zona e via alteradas'],
        'ficha-salvar' => ['tipo' => 'analise', 'titulo' => 'Ficha de análise salva'],
        'ficha-finalizar' => ['tipo' => 'analise', 'titulo' => 'Ficha de análise finalizada'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function eventos(ViabilityRequest $processo): array
    {
        $atividades = Activity::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('subject_type', $processo->getMorphClass())
                    ->where('subject_id', $processo->getKey())
                    ->whereIn('event', [...array_keys(self::EVENTOS), 'regin-parecer', 'sefaz-viabilidade']))
                ->orWhere(fn ($q) => $q
                    ->where('subject_type', (new AnalysisRecord)->getMorphClass())
                    ->whereIn('subject_id', $processo->analysisRecords()->select('id'))
                    ->whereIn('event', self::FICHA_ANALISE))
                ->orWhere(fn ($q) => $q
                    ->where('subject_type', (new Inspection)->getMorphClass())
                    ->whereIn('subject_id', Inspection::query()->where('viability_request_id', $processo->getKey())->select('id'))
                    ->where('event', 'ficha-conclusao')))
            ->where(fn ($q) => $q->whereNull('log_name')->orWhere('log_name', '!=', 'notificacoes'))
            ->oldest('id')
            ->get();

        $usuarios = $this->usuarios($atividades, $processo);
        $mudancasDeSituacao = $processo->transitions()->pluck('created_at')->filter()->map->getTimestamp()->all();
        $atribuicoes = $atividades
            ->filter(fn (Activity $a): bool => in_array($a->event, [...self::ATRIBUICAO, 'vistoria-encaminhar'], true))
            ->map(fn (Activity $a): ?int => $a->created_at?->getTimestamp())
            ->filter()
            ->all();
        $itens = [];

        foreach ($processo->analysisStatusTransitions()->oldest('id')->get() as $t) {
            $momento = $t->created_at?->getTimestamp();

            if ($t->to_status === AnalysisStatus::Analisar
                || (in_array($t->to_status, self::EIXO_DO_CONVITE, true) && $this->noMesmoMomento($momento, $mudancasDeSituacao))
                || ($t->to_status === AnalysisStatus::Encaminhado && $this->noMesmoMomento($momento, $atribuicoes))) {
                continue;
            }

            $itens[] = $this->item(
                tipo: 'analise',
                titulo: 'Análise: '.($t->to_status?->label() ?? '—'),
                detalhe: $t->reason,
                em: $t->created_at?->toIso8601String(),
                ator: $this->nomeDoAtor($usuarios, $t->actor_user_id),
                de: $t->from_status?->label(),
            );
        }

        foreach ($atividades as $a) {
            if (in_array($a->event, self::PENDENCIA, true) && $this->noMesmoMomento($a->created_at?->getTimestamp(), $mudancasDeSituacao)) {
                continue;
            }

            $item = $this->deAtividade($a, $usuarios, $processo);

            if ($item !== null) {
                $itens[] = $item;
            }
        }

        return $itens;
    }

    /**
     * @param  array<int, int>  $momentos
     */
    private function noMesmoMomento(?int $momento, array $momentos): bool
    {
        if ($momento === null) {
            return false;
        }

        foreach ($momentos as $outro) {
            if (abs($momento - $outro) <= self::MESMO_MOMENTO_SEGUNDOS) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, User>  $usuarios
     * @return array<string, mixed>|null
     */
    private function deAtividade(Activity $a, array $usuarios, ViabilityRequest $processo): ?array
    {
        $resultado = $a->result;

        if ($a->event === 'regin-parecer') {
            return $this->item(
                tipo: 'integracao',
                titulo: $resultado === 'sucesso' ? 'Parecer enviado à JUCEB (REGIN)' : 'Envio do parecer à JUCEB não confirmado',
                detalhe: $a->description,
                em: $a->created_at?->toIso8601String(),
                ator: $this->nomeDoAtor($usuarios, $a->causer_id),
                resultado: $resultado,
            );
        }

        if ($a->event === 'sefaz-viabilidade') {
            if ($resultado === 'ignorado') {
                return null;
            }

            return $this->item(
                tipo: 'integracao',
                titulo: $resultado === 'sucesso' ? 'Viabilidade enviada à SEFAZ' : 'Envio à SEFAZ pendente',
                detalhe: $a->description,
                em: $a->created_at?->toIso8601String(),
                ator: $this->nomeDoAtor($usuarios, $a->causer_id),
                resultado: $resultado,
            );
        }

        $evento = self::EVENTOS[$a->event] ?? null;

        if ($evento === null) {
            return null;
        }

        $daFicha = in_array($a->event, self::FICHA_ANALISE, true) && isset($a->properties['revision']);
        $detalhe = $daFicha
            ? 'Revisão '.$a->properties['revision'].' da ficha.'
            : (string) $a->description;
        if ($a->event === 'zona-via-alterada') {
            $detalhe = $this->detalheDaZonaVia($a->properties->toArray());
        }

        $atribuido = in_array($a->event, self::ATRIBUICAO, true) && isset($a->properties['assigned_user_id'])
            ? ($usuarios[(int) $a->properties['assigned_user_id']]?->name ?? null)
            : null;

        if ($atribuido !== null && ! str_contains($detalhe, $atribuido)) {
            $detalhe = trim($detalhe.' Analista: '.$atribuido.'.');
        }

        return $this->item(
            tipo: $evento['tipo'],
            titulo: $evento['titulo'],
            detalhe: $detalhe !== '' ? $detalhe : null,
            em: $a->created_at?->toIso8601String(),
            ator: $this->nomeDoAtor($usuarios, $a->properties['ator_id'] ?? $a->causer_id),
            link: $daFicha ? route('gestao.processos.ficha.show', $processo, false).'?revisao='.$a->properties['revision'].'&somente_leitura=1' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function detalheDaZonaVia(array $propriedades): string
    {
        $antes = (array) ($propriedades['antes'] ?? []);
        $depois = (array) ($propriedades['depois'] ?? []);
        $traco = fn (mixed $valor): string => filled($valor) ? (string) $valor : '—';

        return sprintf(
            'Zona: %s → %s · Via: %s → %s. Justificativa: %s',
            $traco($antes['zona'] ?? null),
            $traco($depois['zona'] ?? null),
            $traco($antes['via'] ?? null),
            $traco($depois['via'] ?? null),
            (string) ($propriedades['justificativa'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $tipo, string $titulo, ?string $detalhe, ?string $em, ?string $ator, ?string $de = null, ?string $resultado = null, ?string $link = null): array
    {
        return [
            'tipo' => $tipo,
            'from' => null,
            'from_label' => $de,
            'to' => $tipo,
            'to_label' => $titulo,
            'reason' => $detalhe,
            'em' => $em,
            'ator' => $ator,
            'atribuido' => null,
            'distribuidor' => null,
            'resultado' => $resultado,
            'link' => $link,
        ];
    }

    /**
     * @param  array<int, User>  $usuarios
     */
    private function nomeDoAtor(array $usuarios, mixed $id): ?string
    {
        $usuario = $id === null ? null : ($usuarios[(int) $id] ?? null);

        return $usuario === null || $usuario->isAtorOcultoNaTramitacao() ? null : $usuario->name;
    }

    /**
     * @param  iterable<Activity>  $atividades
     * @return array<int, User>
     */
    private function usuarios(iterable $atividades, ViabilityRequest $processo): array
    {
        $ids = [];

        foreach ($atividades as $a) {
            foreach ([$a->causer_id, $a->properties['ator_id'] ?? null, $a->properties['assigned_user_id'] ?? null] as $id) {
                if ($id !== null) {
                    $ids[] = (int) $id;
                }
            }
        }

        foreach ($processo->analysisStatusTransitions as $t) {
            if ($t->actor_user_id !== null) {
                $ids[] = (int) $t->actor_user_id;
            }
        }

        return $ids === [] ? [] : User::query()->whereIn('id', array_unique($ids))->get()->keyBy('id')->all();
    }
}
