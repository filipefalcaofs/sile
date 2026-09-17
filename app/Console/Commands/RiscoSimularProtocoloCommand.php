<?php

namespace App\Console\Commands;

use App\Services\Regin\ReginProtocoloSimulacaoService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Reexecuta o motor de risco sobre um protocolo SEDUR de validação, com tipo
 * de imóvel e área como se tivessem chegado do REGIN. Evidência de homologação
 * — não substitui a integração.
 */
class RiscoSimularProtocoloCommand extends Command
{
    protected $signature = 'risco:simular-protocolo
        {codigo : Código do protocolo no catálogo (ex.: 43747, sede-virtual)}';

    protected $description = 'Simula o motor de risco com um protocolo SEDUR como se o dado tivesse chegado do REGIN';

    public function handle(ReginProtocoloSimulacaoService $simulacao): int
    {
        try {
            $relatorio = $simulacao->simular((string) $this->argument('codigo'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Simulação de protocolo (dado como se viesse do REGIN)');
        $this->line($relatorio['aviso']);
        $this->newLine();
        $this->line("Protocolo: {$relatorio['rotulo']} ({$relatorio['processo']})");
        if (! empty($relatorio['protocol_number'])) {
            $this->line("Processo criado: {$relatorio['protocol_number']} · {$relatorio['status']}");
            if (! empty($relatorio['tvl'])) {
                $this->line("TVL: {$relatorio['tvl']}");
            }
        }
        $this->line('Tipo de imóvel: '.($relatorio['tipo_imovel'] ?? 'ausente'));
        $this->line('Código normalizado: '.($relatorio['tipo_imovel_normalized'] ?? 'sem código'));
        $this->line('Reconhecimento: '.$relatorio['tipo_imovel_reconhecimento']);
        $this->line('Área utilizada: '.($relatorio['area_utilizada'] ?? '—'));
        $this->line('Zona: '.($relatorio['zona'] ?? '—').' · Via: '.($relatorio['via'] ?? '—'));
        $this->newLine();

        $conjunto = $relatorio['consolidado'];
        $this->info('Viabilidade do conjunto (CNAE mais gravoso): '.($conjunto['nivel_label'] ?? '—').' → '.$conjunto['fluxo']);
        $this->line($conjunto['motivo']);
        $this->newLine();

        foreach ($relatorio['por_cnae'] as $item) {
            $risco = $item['risco'];
            $fluxo = $risco['encaminhamento']['fluxo'] ?? '—';
            $nivel = $risco['municipal']['nivel_label'] ?? $risco['municipal']['status'] ?? '—';
            $this->line("CNAE {$item['cnae']}: {$nivel} → {$fluxo}");
        }

        return self::SUCCESS;
    }
}
