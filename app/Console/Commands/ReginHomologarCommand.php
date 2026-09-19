<?php

namespace App\Console\Commands;

use App\Services\Regin\ReginHttpClient;
use App\Services\Regin\ReginUnavailableException;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * Homologa o parecer contra os endpoints /teste/* da Juceb — evidência de
 * chamada real, sem gravar produção. Token e senha nunca entram no output.
 */
class ReginHomologarCommand extends Command
{
    protected $signature = 'regin:homologar {--protocolo=43747 : Protocolo usado no envelope de homologação}';

    protected $description = 'Valida o parecer da SEDUR contra a API REGIN de homologação';

    public function handle(ReginHttpClient $client, AuditService $audit): int
    {
        $protocolo = (string) $this->option('protocolo');
        $envelope = $this->envelope($protocolo);

        try {
            $client->homologar($envelope);
        } catch (ReginUnavailableException $e) {
            $audit->log(
                'integracoes',
                'regin-homologacao',
                'Homologação REGIN falhou',
                ['protocolo' => $protocolo],
                result: 'bloqueado',
            );
            $this->error('Homologação REGIN falhou. Confira as credenciais e a URL.');

            return self::FAILURE;
        }

        $audit->log(
            'integracoes',
            'regin-homologacao',
            'Parecer validado em /teste/validaResposta',
            ['protocolo' => $protocolo, 'etapa' => 'validaResposta'],
        );
        $audit->log(
            'integracoes',
            'regin-homologacao',
            'Parecer aceito em /teste/testeRecebimento',
            ['protocolo' => $protocolo, 'etapa' => 'testeRecebimento'],
        );

        $this->info("Homologação REGIN concluída para o protocolo {$protocolo}.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(string $protocolo): array
    {
        $cnpj = (string) Settings::get(
            'integrations.regin.cnpj_prefeitura',
            config('sile.integrations.regin.cnpj_prefeitura'),
        );

        return [
            'protocolo' => $protocolo,
            'servico' => 'WsProSol098',
            'cnpjDestino' => $cnpj,
            'cnpjOrigem' => $cnpj,
            'cnpjEmpresa' => $cnpj,
            'codFuncao' => 110,
            'dataGeracao' => now()->toIso8601String(),
            'dadosProcesso' => [
                'PROTOCOLO' => $protocolo,
                'CNPJ_INSTITUICAO' => $cnpj,
                'DATA_GERACAO' => now()->format('Ymd'),
                'FINALIZA_PROCESSO' => 1,
                'PROCESSO_INTERESSE_INSTITUICAO' => 1,
                'GERA_DOCUMENTO_PROCESSO' => 0,
                'GERA_DOCUMENTOS_AREAS' => 0,
                'ANALISES' => [
                    'AREA' => [
                        [
                            'STATUS_ANALISE' => 2,
                            'JUSTIFICATIVA_ANALISE' => 'Homologação do parecer SEDUR',
                            'DATA_ANALISE' => now()->format('Ymd'),
                        ],
                    ],
                ],
            ],
        ];
    }
}
