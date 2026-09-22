<?php

namespace App\Console\Commands;

use App\Enums\Fluxo;
use App\Enums\RiscoSanitario;
use App\Models\Cnae;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\RiscoResult;
use Illuminate\Console\Command;

/**
 * Classifica um CNAE pelo motor de risco REAL (RiscoClassificationService)
 * sobre o seed oficial, imprimindo o resultado fundamentado — evidência de
 * ponta a ponta da Fase 6 (HU-047 a HU-053), espelhando o padrão dos comandos
 * auditados (cnae:importar / redesim:importar).
 *
 * Sem fachada: o comando apenas APLICA a decisão do motor sobre o dado
 * versionado. CNAE de formato inválido sai com erro (exit 1); CNAE válido sem
 * regra vigente NÃO é erro — segue para análise (exit 0), nunca inventa nível.
 */
class RiscoClassificarCommand extends Command
{
    protected $signature = 'risco:classificar
        {cnae : CNAE de subclasse em dígitos ou formatado (ex.: 0111-3/01)}
        {--gatilho=* : Código de gatilho ativo no contexto (ex.: zeis_especial)}';

    protected $description = 'Classifica um CNAE pelo motor de risco real sobre o seed oficial (HU-047 a HU-053) — evidência fundamentada de ponta a ponta';

    public function handle(RiscoClassificationService $service): int
    {
        $raw = (string) $this->argument('cnae');
        $cnae = (string) preg_replace('/\D/', '', $raw);

        if (strlen($cnae) !== 7) {
            $this->error("CNAE inválido: '{$raw}'. Informe uma subclasse com 7 dígitos (ex.: 0111-3/01).");

            return self::FAILURE;
        }

        $result = $service->classify(new RiscoInput(
            cnaeCode: $cnae,
            gatilhosContexto: $this->parseGatilhos(),
        ));

        $this->renderRelatorio($cnae, $result);

        return self::SUCCESS;
    }

    /**
     * Códigos de gatilho ativos no contexto (ex.: zeis_especial vindo do
     * território). O motor cruza esses códigos com os gatilhos vigentes.
     *
     * @return list<string>
     */
    private function parseGatilhos(): array
    {
        /** @var list<string> $gatilhos */
        $gatilhos = (array) $this->option('gatilho');

        $normalizados = array_map(static fn (string $g): string => trim($g), $gatilhos);

        return array_values(array_filter($normalizados, static fn (string $g): bool => $g !== ''));
    }

    private function parseBool(string $valor): bool
    {
        return in_array(mb_strtolower(trim($valor)), ['1', 'true', 'sim', 's', 'verdadeiro'], true);
    }

    private function renderRelatorio(string $cnae, RiscoResult $result): void
    {
        $denominacao = Cnae::query()->where('code', $cnae)->value('description')
            ?? '(subclasse não cadastrada)';
        $formatted = (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $cnae);

        $this->newLine();
        $this->line("Classificação de risco — CNAE {$formatted}");
        $this->line("Denominação: {$denominacao}");
        $this->newLine();

        $this->renderMunicipal($result->municipal, $result->versoes);
        $this->newLine();
        $this->renderSanitario($result->sanitario, $result->versoes);
        $this->newLine();
        $this->renderEncaminhamento($result->encaminhamento);
        $this->newLine();
        $this->renderFundamentacao($result->fundamentacao);
    }

    /**
     * @param  array<string, mixed>  $municipal
     * @param  array<string, ?string>  $versoes
     */
    private function renderMunicipal(array $municipal, array $versoes): void
    {
        $this->line('Risco municipal (Decreto 32.636/2020):');

        if (($municipal['status'] ?? null) === RiscoResult::STATUS_CLASSIFICADO) {
            $this->line("  Nível: {$municipal['nivel_label']}");

            $condicionantes = $municipal['condicionantes'] ?? [];
            if ($condicionantes !== []) {
                $this->line('  Condicionantes gerais: '.implode('; ', $condicionantes));
            }
        } else {
            $this->line('  Não classificado na versão vigente (segue para análise).');
        }

        $this->line('  Versão de regras: '.($versoes['municipal'] ?? '—'));
    }

    /**
     * @param  array<string, mixed>  $sanitario
     * @param  array<string, ?string>  $versoes
     */
    private function renderSanitario(array $sanitario, array $versoes): void
    {
        $this->line('Risco sanitário (Vigilância Sanitária):');

        if (($sanitario['status'] ?? null) === RiscoResult::STATUS_CLASSIFICADO) {
            $final = $this->labelSanitario($sanitario['nivel_final'] ?? null);

            $this->line("  Nível: {$final}");
        } else {
            $this->line('  Não classificado na versão vigente.');
        }

        $this->line('  Versão de regras: '.($versoes['sanitario'] ?? '—'));
    }

    /**
     * @param  array<string, mixed>  $encaminhamento
     */
    private function renderEncaminhamento(array $encaminhamento): void
    {
        $fluxo = (string) ($encaminhamento['fluxo'] ?? Fluxo::Analise->value);
        $fluxoLabel = Fluxo::tryFrom($fluxo)?->label() ?? $fluxo;

        $this->line('Encaminhamento:');
        $this->line("  Fluxo: {$fluxoLabel}");
        $this->line('  Dimensão decisiva: '.($encaminhamento['dimensao_decisiva'] ?? '—'));
        $this->line('  Motivo: '.($encaminhamento['motivo'] ?? '—'));

        /** @var list<array{codigo: string, motivo: string}> $gatilhos */
        $gatilhos = $encaminhamento['gatilhos_acionados'] ?? [];

        if ($gatilhos !== []) {
            $this->line('  Gatilhos acionados:');
            foreach ($gatilhos as $gatilho) {
                $this->line("    - {$gatilho['codigo']}: {$gatilho['motivo']}");
            }
        } else {
            $this->line('  Gatilhos acionados: nenhum');
        }

        $conclusao = $fluxo === Fluxo::Expresso->value
            ? '  Conclusão: elegível ao fluxo expresso.'
            : '  Conclusão: segue para análise técnica.';
        $this->line($conclusao);
    }

    /**
     * @param  list<string>  $fundamentacao
     */
    private function renderFundamentacao(array $fundamentacao): void
    {
        $this->line('Fundamentação legal:');

        if ($fundamentacao === []) {
            $this->line('  - Sem fundamentação automática (CNAE não classificado) — a análise técnica define o enquadramento.');

            return;
        }

        foreach ($fundamentacao as $referencia) {
            $this->line("  - {$referencia}");
        }
    }

    private function labelSanitario(?string $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return RiscoSanitario::tryFrom($valor)?->label() ?? $valor;
    }
}
