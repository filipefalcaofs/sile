<?php

namespace App\Console\Commands;

use App\Enums\Fluxo;
use App\Enums\RiscoSanitario;
use App\Models\Cnae;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\GeocoderException;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Risco\RiscoResult;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Illuminate\Console\Command;

/**
 * Consulta a viabilidade prévia de uma atividade pelo ORQUESTRADOR REAL
 * (ConsultaViabilidadeService) sobre o seed oficial — compõe os motores
 * (Geocoder → TerritoryService → LOUOS → Risco) e imprime o parecer
 * fundamentado de ponta a ponta. É a evidência da Fase 7 (HU-054 a HU-060),
 * espelhando louos:enquadrar / risco:classificar.
 *
 * Três entradas honestas: por CNAE (risco + Quadro 7 por área, sem local), por
 * endereço (geocodifica de verdade → território → motores) e por inscrição
 * imobiliária (resolução pendente SEDUR — degrada com aviso, NUNCA inventa
 * ponto). Sem fachada: o comando só APLICA a decisão dos motores; o veredito é
 * PROPAGADO do motor LOUOS — sem zona oficial fica `pendente` (degradação
 * honesta), e isso NÃO é erro (exit 0). CNAE de formato inválido sai com erro
 * (exit 1); endereço não localizado / serviço de geocodificação indisponível
 * também (exit 1), nunca um resultado falso.
 */
class ConsultaViabilidadeCommand extends Command
{
    protected $signature = 'viabilidade:consultar
        {cnae : Subclasse CNAE em dígitos ou formatada (ex.: 4712-1/00)}
        {--endereco= : Consulta por endereço (geocodifica de verdade)}
        {--inscricao= : Consulta por inscrição imobiliária (resolução pendente SEDUR — degrada)}
        {--area= : Área ocupada em m² (Quadro 7)}';

    protected $description = 'Consulta a viabilidade prévia pelo orquestrador real (HU-054 a HU-060) — parecer fundamentado de ponta a ponta';

    public function handle(ConsultaViabilidadeService $service): int
    {
        $raw = (string) $this->argument('cnae');
        $cnae = (string) preg_replace('/\D/', '', $raw);

        if (strlen($cnae) !== 7) {
            $this->error("CNAE inválido: '{$raw}'. Informe uma subclasse com 7 dígitos (ex.: 4712-1/00).");

            return self::FAILURE;
        }

        $area = $this->parseArea();

        if ($area === false) {
            return self::FAILURE;
        }

        $endereco = $this->stringOption('endereco');
        $inscricao = $this->stringOption('inscricao');

        try {
            $result = match (true) {
                $endereco !== null => $service->consultarPorEndereco($endereco, $cnae, $area),
                $inscricao !== null => $service->consultarPorInscricao($inscricao, $cnae, $area),
                default => $service->consultarPorCnae($cnae, $area),
            };
        } catch (AddressNotFoundException) {
            // Sem fachada: endereço não localizado NUNCA vira resultado falso.
            $this->error("Endereço não localizado: '{$endereco}'. Revise o endereço ou consulte por CNAE.");

            return self::FAILURE;
        } catch (GeocoderException) {
            $this->error('Serviço de geocodificação indisponível no momento. Tente novamente em instantes.');

            return self::FAILURE;
        }

        $this->renderParecer($result);

        return self::SUCCESS;
    }

    /**
     * Lê e valida a área opcional (--area). `null` quando ausente (a consulta por
     * CNAE roda sem área — o Quadro 7 só não enquadra); `false` (com erro
     * impresso) quando presente mas não numérica ou não positiva.
     */
    private function parseArea(): float|false|null
    {
        $raw = $this->option('area');

        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $raw = str_replace(',', '.', trim((string) $raw));

        if (! is_numeric($raw)) {
            $this->error("Área inválida: '{$raw}'. Informe um valor numérico em m² (ex.: --area=200).");

            return false;
        }

        $area = (float) $raw;

        if ($area <= 0) {
            $this->error('A área deve ser maior que zero.');

            return false;
        }

        return $area;
    }

    /**
     * Valor de uma opção de string (--endereco/--inscricao): `null` quando
     * ausente ou vazia; valor trimado caso contrário.
     */
    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function renderParecer(ConsultaViabilidadeResult $result): void
    {
        $entrada = $result->entrada;
        $cnaeFormatado = (string) ($entrada['cnae_formatado'] ?? $entrada['cnae']);
        $denominacao = Cnae::query()->where('code', $entrada['cnae'])->value('description')
            ?? '(subclasse não cadastrada)';

        $this->newLine();
        $this->line('Consulta prévia de viabilidade');
        $this->line('Tipo de entrada: '.$this->labelTipo((string) $entrada['tipo']));
        $this->line("CNAE {$cnaeFormatado} — {$denominacao}");

        if (is_string($entrada['endereco'] ?? null) && $entrada['endereco'] !== '') {
            $this->line('Endereço: '.$entrada['endereco']);
        }

        if (is_string($entrada['inscricao'] ?? null) && $entrada['inscricao'] !== '') {
            $this->line('Inscrição imobiliária: '.$entrada['inscricao']);
        }

        $this->line('Área pretendida: '.($entrada['area'] !== null
            ? $this->formatArea((float) $entrada['area']).' m²'
            : 'não informada'));
        $this->newLine();

        $this->renderRisco($result->risco);
        $this->newLine();
        $this->renderEnquadramento($result->enquadramento);
        $this->newLine();
        $this->renderVeredito($result->vereditoLocacional());
        $this->newLine();
        $this->renderFundamentacao($result->fundamentacao());
        $this->newLine();
        $this->renderAvisos($result->avisos);
        $this->newLine();
        $this->renderVersoes($result->versoes());
    }

    private function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            'endereco' => 'Endereço',
            'inscricao' => 'Inscrição imobiliária',
            default => 'CNAE',
        };
    }

    private function renderRisco(RiscoResult $risco): void
    {
        $this->line('RISCO');

        $municipal = $risco->municipal;
        if (($municipal['status'] ?? null) === RiscoResult::STATUS_CLASSIFICADO) {
            $this->line('  Risco municipal (Decreto 32.636/2020): '.($municipal['nivel_label'] ?? '—'));
        } else {
            $this->line('  Risco municipal (Decreto 32.636/2020): não classificado (segue para análise)');
        }

        $sanitario = $risco->sanitario;
        if (($sanitario['status'] ?? null) === RiscoResult::STATUS_CLASSIFICADO) {
            $this->line('  Risco sanitário (VISA): '.$this->labelSanitario($sanitario['nivel_final'] ?? null));
        } else {
            $this->line('  Risco sanitário (VISA): não classificado');
        }

        $fluxo = (string) ($risco->encaminhamento['fluxo'] ?? Fluxo::Analise->value);
        $this->line('  Encaminhamento: '.(Fluxo::tryFrom($fluxo)?->label() ?? $fluxo)
            .' — '.($risco->encaminhamento['motivo'] ?? '—'));
    }

    private function renderEnquadramento(EnquadramentoResult $enquadramento): void
    {
        $this->line('ENQUADRAMENTO POR ÁREA (Quadro 7)');

        $quadro7 = $enquadramento->quadro7;

        if (($quadro7['status'] ?? null) === EnquadramentoResult::STATUS_IDENTIFICADO) {
            $grupo = (string) ($quadro7['grupo'] ?? '—');
            $subgrupo = $quadro7['subgrupo'] ?? null;
            $this->line('  Grupo de uso: '.$grupo.(is_string($subgrupo) && $subgrupo !== '' ? " / Subgrupo: {$subgrupo}" : ''));
            $this->line('  Faixa de área: '.$this->formatFaixa($quadro7['faixa'] ?? null));
        } else {
            $this->line('  Sem enquadramento parametrizado no Quadro 7 vigente para o CNAE — segue para análise técnica.');
        }
    }

    /**
     * @param  array<string, ?string>  $veredito
     */
    private function renderVeredito(array $veredito): void
    {
        $this->line('VEREDITO LOCACIONAL');
        $this->line('  Resultado: '.($veredito['label'] ?? '—'));
        $this->line('  Motivo: '.($veredito['motivo'] ?? '—'));
    }

    /**
     * @param  list<string>  $fundamentacao
     */
    private function renderFundamentacao(array $fundamentacao): void
    {
        $this->line('FUNDAMENTAÇÃO LEGAL');

        if ($fundamentacao === []) {
            $this->line('  - Sem fundamentação automática — a análise técnica define o enquadramento.');

            return;
        }

        foreach ($fundamentacao as $referencia) {
            $this->line("  - {$referencia}");
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private function renderAvisos(array $avisos): void
    {
        $this->line('AVISOS');

        if ($avisos === []) {
            $this->line('  Nenhum.');

            return;
        }

        foreach ($avisos as $aviso) {
            $this->line("  - {$aviso}");
        }
    }

    /**
     * @param  array<string, array<string, ?string>>  $versoes
     */
    private function renderVersoes(array $versoes): void
    {
        $this->line('Versões de regras aplicadas:');

        foreach ($versoes as $dominio => $mapa) {
            if ($mapa === []) {
                $this->line("  {$dominio}: — (não consultado)");

                continue;
            }

            $partes = [];
            foreach ($mapa as $chave => $valor) {
                $partes[] = "{$chave}=".($valor ?? '—');
            }

            $this->line("  {$dominio}: ".implode(' | ', $partes));
        }
    }

    private function labelSanitario(?string $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return RiscoSanitario::tryFrom($valor)?->label() ?? $valor;
    }

    /**
     * @param  array<string, mixed>|null  $faixa
     */
    private function formatFaixa(?array $faixa): string
    {
        if ($faixa === null) {
            return '—';
        }

        $min = $this->formatArea((float) ($faixa['area_min'] ?? 0));
        $max = $faixa['area_max'] ?? null;

        return $max === null
            ? "a partir de {$min} m²"
            : "{$min} a ".$this->formatArea((float) $max).' m²';
    }

    private function formatArea(float $area): string
    {
        return rtrim(rtrim(number_format($area, 2, '.', ''), '0'), '.');
    }
}
