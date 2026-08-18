<?php

namespace App\Console\Commands;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Models\Cnae;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use Illuminate\Console\Command;

/**
 * Enquadra um CNAE + área pelo motor de regras REAL da LOUOS
 * (LouosEnquadramentoService) sobre o seed oficial dos Quadros 7/10/11/11A,
 * imprimindo o parecer fundamentado — evidência de ponta a ponta da Fase 5
 * (HU-038 a HU-046), espelhando o padrão dos comandos auditados
 * (risco:classificar / cnae:importar / redesim:importar).
 *
 * Sem fachada: o comando apenas APLICA a decisão do motor sobre o dado
 * versionado. A zona é ENTRADA EXPLÍCITA do operador (hipótese de simulação) —
 * a base oficial de zona segue pendente SEDUR; sem --zona o parecer é
 * honestamente `pendente` (o Quadro 10 degrada, o motor jamais inventa
 * permissão). CNAE de formato inválido ou área ausente/inválida saem com erro
 * (exit 1); CNAE válido sem regra no Quadro 7 NÃO é erro — segue pendente
 * (exit 0), nunca inventa enquadramento.
 */
class LouosEnquadrarCommand extends Command
{
    protected $signature = 'louos:enquadrar
        {cnae : Subclasse CNAE em dígitos ou formatada (ex.: 4712-1/00)}
        {--area= : Área ocupada em m² (obrigatória)}
        {--zona= : Zona urbanística hipotética — ENTRADA EXPLÍCITA do operador (a base oficial pende SEDUR)}
        {--restricao=* : Restrição territorial incidente como entrada explícita (ex.: ZEIS)}';

    protected $description = 'Enquadra um CNAE + área pelo motor real da LOUOS (HU-038 a HU-046) — parecer fundamentado de ponta a ponta';

    public function handle(LouosEnquadramentoService $service): int
    {
        $raw = (string) $this->argument('cnae');
        $cnae = (string) preg_replace('/\D/', '', $raw);

        if (strlen($cnae) !== 7) {
            $this->error("CNAE inválido: '{$raw}'. Informe uma subclasse com 7 dígitos (ex.: 4712-1/00).");

            return self::FAILURE;
        }

        $area = $this->parseArea();

        if ($area === null) {
            return self::FAILURE;
        }

        $result = $service->enquadrar(new EnquadramentoInput(
            area: $area,
            cnaePrincipal: $cnae,
            territory: $this->montaTerritorio(),
        ));

        $this->renderParecer($cnae, $area, $result);

        return self::SUCCESS;
    }

    /**
     * Lê e valida a área obrigatória (--area). Null (com erro impresso) quando
     * ausente, não numérica ou não positiva — sem área o Quadro 7 não se aplica.
     */
    private function parseArea(): ?float
    {
        $raw = $this->option('area');

        if ($raw === null || trim((string) $raw) === '') {
            $this->error('Informe a área ocupada em m² pela opção --area (ex.: --area=200).');

            return null;
        }

        $raw = str_replace(',', '.', trim((string) $raw));

        if (! is_numeric($raw)) {
            $this->error("Área inválida: '{$raw}'. Informe um valor numérico em m² (ex.: --area=200).");

            return null;
        }

        $area = (float) $raw;

        if ($area <= 0) {
            $this->error('A área deve ser maior que zero.');

            return null;
        }

        return $area;
    }

    /**
     * Monta o território a partir das ENTRADAS EXPLÍCITAS do operador. Sem
     * --zona, devolve null: o Quadro 10 degrada para indisponível e o parecer
     * sai pendente (degradação honesta — zona pendente SEDUR). Com --zona, a
     * feição é identificada (hipótese), com as restrições de --restricao.
     */
    private function montaTerritorio(): ?TerritoryResult
    {
        $zona = $this->option('zona');

        if (! is_string($zona) || trim($zona) === '') {
            return null;
        }

        $zona = trim($zona);

        return new TerritoryResult(
            bairro: $this->dimVazia(),
            via: $this->dimVazia() + ['distancia_m' => null],
            zona: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'nome' => $zona,
                'propriedades' => ['NOME' => $zona],
                'motivo' => null,
                'versao_camada' => null,
            ],
            lote: $this->dimVazia(),
            restricoes: $this->montaRestricoes(),
        );
    }

    /**
     * Restrições territoriais a partir de --restricao (entrada explícita do
     * operador). Sem itens, dimensão `nao_encontrado`.
     *
     * @return array<string, mixed>
     */
    private function montaRestricoes(): array
    {
        /** @var list<string> $itens */
        $itens = (array) $this->option('restricao');

        $itens = array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), $itens),
            static fn (string $item): bool => $item !== '',
        ));

        if ($itens === []) {
            return ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null];
        }

        return [
            'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
            'itens' => array_map(
                static fn (string $nome): array => ['nome' => $nome, 'propriedades' => []],
                $itens,
            ),
            'motivo' => null,
            'versao_camada' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dimVazia(): array
    {
        return [
            'status' => 'nao_encontrado',
            'nome' => null,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => null,
        ];
    }

    private function renderParecer(string $cnae, float $area, EnquadramentoResult $result): void
    {
        $denominacao = Cnae::query()->where('code', $cnae)->value('description')
            ?? '(subclasse não cadastrada)';
        $formatted = (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $cnae);

        $this->newLine();
        $this->line("Enquadramento LOUOS — CNAE {$formatted}");
        $this->line("Denominação: {$denominacao}");
        $this->line('Área pretendida: '.$this->formatArea($area).' m²');
        $this->newLine();

        $this->renderEnquadramento($result->quadro7, $result->versoes());
        $this->newLine();
        $this->renderPermissao($result->quadro10, $result->versoes());
        $this->newLine();
        $this->renderCondicoesVia($result);
        $this->newLine();
        $this->renderConsolidado($result->consolidado);
        $this->newLine();
        $this->renderFundamentacao($result->consolidado['fundamentacao'] ?? []);
        $this->newLine();
        $this->renderVersoes($result->versoes());
    }

    /**
     * @param  array<string, mixed>  $quadro7
     * @param  array<string, ?string>  $versoes
     */
    private function renderEnquadramento(array $quadro7, array $versoes): void
    {
        $this->line('Enquadramento por área (Quadro 7):');

        if (($quadro7['status'] ?? null) === EnquadramentoResult::STATUS_IDENTIFICADO) {
            $grupo = (string) ($quadro7['grupo'] ?? '—');
            $subgrupo = $quadro7['subgrupo'] ?? null;

            $this->line('  Grupo de uso: '.$grupo.($subgrupo !== null && $subgrupo !== '' ? " / Subgrupo: {$subgrupo}" : ''));
            $this->line('  Faixa de área: '.$this->formatFaixa($quadro7['faixa'] ?? null));
        } else {
            $this->line('  Sem enquadramento parametrizado no Quadro 7 vigente para o CNAE — segue para análise técnica.');
            $this->lineMotivo($quadro7['motivo'] ?? null);
        }

        $this->line('  Versão de regras: '.($versoes['quadro7'] ?? '—'));
    }

    /**
     * @param  array<string, mixed>  $quadro10
     * @param  array<string, ?string>  $versoes
     */
    private function renderPermissao(array $quadro10, array $versoes): void
    {
        $this->line('Permissão na zona (Quadro 10):');

        $status = $quadro10['status'] ?? null;

        if ($status === EnquadramentoResult::STATUS_IDENTIFICADO) {
            $permissao = $quadro10['permissao'] ?? null;
            $label = is_string($permissao)
                ? (Quadro10Permissao::tryFrom($permissao)?->label() ?? $permissao)
                : '—';
            $this->line("  Permissão: {$label}");

            $ref = $quadro10['condicionante_ref'] ?? null;
            if (is_string($ref) && $ref !== '') {
                $this->line("  Condicionante urbanística: {$ref}");
            }
        } else {
            // Degradação honesta: sem zona real o motor não consulta a tabela
            // nem inventa permissão — comunica a indisponibilidade.
            $this->line('  Indisponível — não decidida.');
            $this->lineMotivo($quadro10['motivo'] ?? null);
        }

        $this->line('  Versão de regras: '.($versoes['quadro10'] ?? '—'));
    }

    private function renderCondicoesVia(EnquadramentoResult $result): void
    {
        $this->line('Condições de instalação pela via (Quadros 11 / 11A):');

        foreach ([['Quadro 11', $result->quadro11], ['Quadro 11A', $result->quadro11a]] as [$rotulo, $via]) {
            if (($via['status'] ?? null) === EnquadramentoResult::STATUS_IDENTIFICADO) {
                $condicoes = $via['condicoes'] ?? [];
                $this->line("  {$rotulo}: ".($condicoes === [] ? 'sem condições adicionais' : json_encode($condicoes, JSON_UNESCAPED_UNICODE)));
            } else {
                $this->line("  {$rotulo}: indisponível".$this->motivoInline($via['motivo'] ?? null));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $consolidado
     */
    private function renderConsolidado(array $consolidado): void
    {
        $resultado = (string) ($consolidado['resultado'] ?? ResultadoViabilidade::Pendente->value);
        $label = ResultadoViabilidade::tryFrom($resultado)?->label() ?? $resultado;

        $this->line('Parecer consolidado:');
        $this->line("  Resultado: {$label}");
        $this->line('  Motivo: '.($consolidado['motivo'] ?? '—'));

        /** @var list<array<string, mixed>> $condicionantes */
        $condicionantes = $consolidado['condicionantes'] ?? [];

        if ($condicionantes === []) {
            $this->line('  Condicionantes: nenhuma');

            return;
        }

        $this->line('  Condicionantes:');
        foreach ($condicionantes as $condicionante) {
            $this->line('    - '.$this->descreveCondicionante($condicionante));
        }
    }

    /**
     * @param  array<string, mixed>  $condicionante
     */
    private function descreveCondicionante(array $condicionante): string
    {
        $tipo = (string) ($condicionante['tipo'] ?? 'condicionante');
        $motivo = (string) ($condicionante['motivo'] ?? '');

        return "[{$tipo}] {$motivo}";
    }

    /**
     * @param  list<string>  $fundamentacao
     */
    private function renderFundamentacao(array $fundamentacao): void
    {
        $this->line('Fundamentação legal:');

        if ($fundamentacao === []) {
            $this->line('  - Sem fundamentação automática — a análise técnica define o enquadramento.');

            return;
        }

        foreach ($fundamentacao as $referencia) {
            $this->line("  - {$referencia}");
        }
    }

    /**
     * @param  array<string, ?string>  $versoes
     */
    private function renderVersoes(array $versoes): void
    {
        $this->line('Versões de regras aplicadas:');
        $this->line('  Quadro 7: '.($versoes['quadro7'] ?? '—')
            .' | Quadro 10: '.($versoes['quadro10'] ?? '—')
            .' | Quadro 11: '.($versoes['quadro11'] ?? '—')
            .' | Quadro 11A: '.($versoes['quadro11a'] ?? '—'));
    }

    private function lineMotivo(?string $motivo): void
    {
        if (is_string($motivo) && $motivo !== '') {
            $this->line("  Motivo: {$motivo}");
        }
    }

    private function motivoInline(?string $motivo): string
    {
        return is_string($motivo) && $motivo !== '' ? " ({$motivo})" : '';
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
