<?php

namespace App\Console\Commands;

use App\Enums\PoligonoFonte;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Geo\EnderecamentoHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use App\Services\Geo\GeoServerEnderecamentoClient;
use App\Services\Geo\SemapLoteClient;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Realty\LocalizacaoInscricaoService;
use Illuminate\Console\Command;

/**
 * Levantamento, sem gravar nada, dos processos cujo CODLOG gravado não é o do
 * endereço de Localização da inscrição (até 22/10/2026 o protocolo gravava o
 * logradouro tributário). Para cada um, mostra o que mudaria em CODLOG, classe
 * da via, polígono do lote e zona pela maior porção.
 */
class AuditarCodLogInscricaoCommand extends Command
{
    protected $signature = 'processos:auditar-codlog-inscricao
        {--processo= : Verifica só este processo}
        {--desde= : Só processos protocolados a partir desta data (AAAA-MM-DD)}
        {--csv= : Grava a lista dos afetados neste arquivo CSV (separador ;)}';

    protected $description = 'Lista, sem alterar nada, os processos com CODLOG diferente do endereço de Localização da inscrição';

    /** @var list<string> */
    private const COLUNAS = [
        'processo', 'protocolo', 'status', 'decisao_expresso', 'inscricao',
        'codlog_atual', 'codlog_correto', 'logradouro_correto', 'codlog_tributario',
        'via_atual', 'via_correta', 'poligono_fonte_atual', 'poligono_correto',
        'zona_atual', 'zona_correta', 'ajuste_manual',
    ];

    /** @var array<string, string> */
    private array $classes = [];

    private LocalizacaoInscricaoService $localizacao;

    private GeoServerClassificacaoViariaClient $classificacaoViaria;

    private GeoServerEnderecamentoClient $enderecamento;

    private SemapLoteClient $semap;

    private ZonaMaiorPorcaoResolver $maiorPorcao;

    public function handle(
        LocalizacaoInscricaoService $localizacao,
        GeoServerClassificacaoViariaClient $classificacaoViaria,
        GeoServerEnderecamentoClient $enderecamento,
        SemapLoteClient $semap,
        ZonaMaiorPorcaoResolver $maiorPorcao,
    ): int {
        $this->localizacao = $localizacao;
        $this->classificacaoViaria = $classificacaoViaria;
        $this->enderecamento = $enderecamento;
        $this->semap = $semap;
        $this->maiorPorcao = $maiorPorcao;

        $query = ViabilityRequest::query()
            ->with('decision')
            ->where('status', '!=', ViabilityRequestStatus::Rascunho)
            ->whereNotNull('property_registration')
            ->where('property_registration', '!=', '');

        if ($this->option('processo') !== null) {
            $query->whereKey((int) $this->option('processo'));
        }

        if ($this->option('desde') !== null) {
            $query->where('protocoled_at', '>=', $this->option('desde'));
        }

        $afetados = [];
        $verificados = 0;
        $indisponiveis = 0;

        $query->chunkById(100, function ($processos) use (&$afetados, &$verificados, &$indisponiveis): void {
            foreach ($processos as $processo) {
                $verificados++;
                $localizacao = $this->localizacao->localizar($processo->property_registration);

                if ($localizacao->status === LocalizacaoInscricao::INDISPONIVEL) {
                    $indisponiveis++;

                    continue;
                }

                $linha = $this->linha($processo, $localizacao);

                if ($linha !== null) {
                    $afetados[] = $linha;
                }
            }
        });

        if ($afetados !== []) {
            $this->table(
                ['Processo', 'Protocolo', 'Decisão', 'CODLOG', 'Via', 'Polígono', 'Zona'],
                array_map(fn (array $linha): array => [
                    $linha['processo'],
                    $linha['protocolo'],
                    $linha['decisao_expresso'] ?: $linha['status'],
                    "{$linha['codlog_atual']} → {$linha['codlog_correto']}",
                    "{$linha['via_atual']} → {$linha['via_correta']}",
                    $linha['poligono_correto'],
                    "{$linha['zona_atual']} → {$linha['zona_correta']}",
                ], $afetados),
            );
        }

        if ($this->option('csv') !== null) {
            $this->gravarCsv((string) $this->option('csv'), $afetados);
        }

        $this->info("Verificados: {$verificados}");
        $this->info('Afetados: '.count($afetados));
        $this->info("Cadastro indisponível: {$indisponiveis}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>|null
     */
    private function linha(ViabilityRequest $processo, LocalizacaoInscricao $localizacao): ?array
    {
        $correto = $localizacao->codLogDoEndereco();
        $atual = ltrim(trim((string) $processo->cod_log), '0');

        if ($correto === null || $correto === $atual) {
            return null;
        }

        $poligonoAtual = is_array($processo->address_polygon_geojson) && $processo->address_polygon_geojson !== []
            ? $processo->address_polygon_geojson
            : null;
        [$poligonoNovo, $poligonoDescricao] = $this->poligonoCorreto($processo, $correto, $poligonoAtual);

        return [
            'processo' => (string) $processo->id,
            'protocolo' => (string) $processo->protocol_number,
            'status' => $processo->status->value,
            'decisao_expresso' => (string) $processo->decision?->outcome?->value,
            'inscricao' => (string) $processo->property_registration,
            'codlog_atual' => $atual,
            'codlog_correto' => $correto,
            'logradouro_correto' => (string) $localizacao->logradouroDoEndereco(),
            'codlog_tributario' => (string) $localizacao->codLogTributario,
            'via_atual' => (string) ($processo->camadas_incidentes['via'] ?? ''),
            'via_correta' => $this->classe($correto),
            'poligono_fonte_atual' => (string) $processo->address_polygon_source?->value,
            'poligono_correto' => $poligonoDescricao,
            'zona_atual' => (string) $processo->zona_codigo,
            'zona_correta' => $this->zonaCorreta($processo, $poligonoAtual, $poligonoNovo),
            'ajuste_manual' => implode(' ', array_filter([
                filled($processo->zona_manual) ? "zona={$processo->zona_manual}" : null,
                filled($processo->via_manual) ? "via={$processo->via_manual}" : null,
            ])),
        ];
    }

    /**
     * Repete a cadeia do protocolo com o CODLOG correto: chave do
     * endereçamento e, quando o polígono atual veio da chave errada, o SEMAP.
     *
     * @param  array<string, mixed>|null  $atual
     * @return array{0: array<string, mixed>|null|false, 1: string} false = não verificado
     */
    private function poligonoCorreto(ViabilityRequest $processo, string $codLog, ?array $atual): array
    {
        $fonte = $processo->address_polygon_source;
        $numero = trim((string) $processo->address_number);
        $hit = $numero === ''
            ? new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO)
            : $this->enderecamento->consultar($codLog, $numero, $processo->address_complement);

        if ($hit->encontrado()) {
            return [$hit->geometria, $hit->geometria == $atual ? 'mantém (chave)' : 'muda (chave)'];
        }

        if ($hit->status === EnderecamentoHit::INDISPONIVEL) {
            return [false, 'não verificado (endereçamento indisponível)'];
        }

        if ($fonte !== PoligonoFonte::Chave) {
            return [$atual, $atual === null ? 'sem polígono' : "mantém ({$fonte?->value})"];
        }

        $semap = $this->semap->consultar($processo->property_registration);

        if ($semap->encontrado()) {
            return [$semap->geometria, $semap->geometria == $atual ? 'mantém (semap)' : 'muda (semap)'];
        }

        if ($semap->status === EnderecamentoHit::INDISPONIVEL) {
            return [false, 'não verificado (SEMAP indisponível)'];
        }

        return [null, 'perde (sem lote na chave nem no SEMAP)'];
    }

    /**
     * @param  array<string, mixed>|null  $atual
     * @param  array<string, mixed>|null|false  $novo
     */
    private function zonaCorreta(ViabilityRequest $processo, ?array $atual, array|null|false $novo): string
    {
        if ($novo === false) {
            return 'não verificada';
        }

        if ($novo == $atual) {
            return (string) $processo->zona_codigo;
        }

        if ($novo === null) {
            return 'ponto (rever)';
        }

        $resultado = $this->maiorPorcao->resolver($novo);

        return match ($resultado->status) {
            ZonaMaiorPorcao::IDENTIFICADO => (string) $resultado->codigo,
            ZonaMaiorPorcao::EMPATE => 'empate '.implode('/', $resultado->empate),
            default => $resultado->status,
        };
    }

    private function classe(string $codLog): string
    {
        if (! isset($this->classes[$codLog])) {
            $hit = $this->classificacaoViaria->consultar($codLog);
            $this->classes[$codLog] = $hit->classe ?? $hit->status;
        }

        return $this->classes[$codLog];
    }

    /**
     * @param  list<array<string, string>>  $linhas
     */
    private function gravarCsv(string $caminho, array $linhas): void
    {
        $arquivo = fopen($caminho, 'w');

        if ($arquivo === false) {
            $this->error("Não foi possível gravar {$caminho}");

            return;
        }

        fputcsv($arquivo, self::COLUNAS, ';', escape: '');

        foreach ($linhas as $linha) {
            fputcsv($arquivo, array_map(fn (string $coluna): string => $linha[$coluna], self::COLUNAS), ';', escape: '');
        }

        fclose($arquivo);

        $this->info("CSV gravado em {$caminho}");
    }
}
