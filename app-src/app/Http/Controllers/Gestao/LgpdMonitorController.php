<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Lgpd\LgpdMonitorService;
use App\Support\Audit\AuditService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel de monitoramento de conformidade LGPD (HU-102) na retaguarda: agrega,
 * via LgpdMonitorService, consentimentos da versão vigente, retenção (dias +
 * último pruning; decisões fora do pruning) e acessos a dado pessoal
 * (personal_data). MINIMIZADO — só métricas, nunca PII crua. Gated por
 * monitorar-lgpd; a própria consulta é auditada e marcada personal_data
 * (listar acessos a PII é, em si, acesso a dado de auditoria sensível). O 403
 * sem permissão é auditado no ponto único (bootstrap/app.php). Os direitos do
 * titular (eliminação/anonimização — LGPD art. 18) são pendência DPO/SEDUR,
 * registrada honestamente e sem rito automatizado inventado. Tela em 12-10.
 */
class LgpdMonitorController extends Controller
{
    public function __construct(
        private LgpdMonitorService $lgpd,
        private AuditService $audit,
    ) {}

    public function index(): Response
    {
        $consentimentos = $this->lgpd->consentimentos();
        $retencao = $this->lgpd->retencao();
        $acessosDadoPessoal = $this->lgpd->acessosDadoPessoal();

        // A própria consulta lista acessos a dado pessoal — é, em si, acesso a
        // dado de auditoria sensível: auditada (RN-002) e marcada personal_data.
        $this->audit->log(
            logName: 'lgpd',
            event: 'consulta-painel',
            description: 'Consulta do painel de monitoramento LGPD',
            personalData: true,
        );

        return Inertia::render('gestao/lgpd/index', [
            'consentimentos' => $consentimentos,
            'retencao' => $retencao,
            'acessosDadoPessoal' => $acessosDadoPessoal,
            // Pendência DPO honesta: a eliminação/anonimização do titular
            // (LGPD art. 18) depende de rito da SEDUR/DPO e convive com a
            // retenção legal da trilha — registrada, nunca simulada.
            'direitosTitular' => [
                'status' => 'pendente-dpo',
                'descricao' => 'A eliminação e a anonimização de dados do titular (LGPD art. 18) dependem de definição de rito pela SEDUR/DPO e convivem com a retenção legal da trilha de auditoria. Não há rito automatizado neste painel.',
            ],
        ]);
    }
}
