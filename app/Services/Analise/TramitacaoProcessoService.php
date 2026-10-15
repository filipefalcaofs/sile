<?php

namespace App\Services\Analise;

use App\Models\Activity;
use App\Models\User;
use App\Models\ViabilityRequest;

/**
 * Eventos da tramitação do processo que não são mudança de status: situação da
 * análise, atribuições, pendências e convites, vistoria, malha fina, anexos,
 * TVL e os envios à JUCEB e à SEFAZ. Vêm da auditoria por uma lista fechada de
 * eventos: consulta de tela, autosave da ficha e demais registros técnicos não
 * entram. Cada item tem o mesmo formato da linha do tempo de status.
 */
class TramitacaoProcessoService
{
    private const ATRIBUICAO = ['distribuir', 'assumir', 'redistribuir'];

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
        'ficha-conclusao' => ['tipo' => 'analise', 'titulo' => 'Ficha de análise concluída'],
        'ficha-finalizar' => ['tipo' => 'analise', 'titulo' => 'Ficha de análise finalizada'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function eventos(ViabilityRequest $processo): array
    {
        $atividades = Activity::query()
            ->where('subject_type', $processo->getMorphClass())
            ->where('subject_id', $processo->getKey())
            ->whereIn('event', [...array_keys(self::EVENTOS), 'regin-parecer', 'sefaz-viabilidade'])
            ->oldest('id')
            ->get();

        $usuarios = $this->usuarios($atividades, $processo);
        $itens = [];

        foreach ($processo->analysisStatusTransitions()->oldest('id')->get() as $t) {
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
            $item = $this->deAtividade($a, $usuarios);

            if ($item !== null) {
                $itens[] = $item;
            }
        }

        return $itens;
    }

    /**
     * @param  array<int, User>  $usuarios
     * @return array<string, mixed>|null
     */
    private function deAtividade(Activity $a, array $usuarios): ?array
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

        $detalhe = (string) $a->description;
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
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $tipo, string $titulo, ?string $detalhe, ?string $em, ?string $ator, ?string $de = null, ?string $resultado = null): array
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
