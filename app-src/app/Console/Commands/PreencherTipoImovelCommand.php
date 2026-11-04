<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Regin\ReginTipoImovelApplier;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Preenche o tipo do imóvel dos processos que ficaram sem ele (o REGIN não
 * declarou), em qualquer situação, com o uso do imóvel no Cadastro Imobiliário
 * (Utilizacao da inscrição). Tipo já gravado não muda. Sem `--aplicar`, só
 * mostra o levantamento. O agendador roda com `--dias` para tentar de novo os
 * processos novos que chegaram com o cadastro fora do ar.
 */
class PreencherTipoImovelCommand extends Command
{
    protected $signature = 'processos:preencher-tipo-imovel
        {--processo= : Preenche só este processo (id)}
        {--dias= : Só processos criados nos últimos N dias}
        {--pausa-ms=100 : Pausa entre as consultas ao cadastro, em milissegundos}
        {--aplicar : Grava o tipo (sem esta opção, só lista)}';

    protected $description = 'Preenche o tipo do imóvel dos processos sem tipo pela inscrição imobiliária';

    private const LINHAS_NA_TABELA = 200;

    public function handle(PropertyRegistryLookup $cadastro, ReginTipoImovelApplier $tipoImovel, AuditService $audit): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $pausaUs = max(0, (int) $this->option('pausa-ms')) * 1000;

        $verificados = 0;
        $encontrados = [];
        $semTipo = 0;
        $erros = 0;

        $this->consulta()->orderBy('id')->chunkById(100, function ($processos) use ($cadastro, $tipoImovel, $audit, $aplicar, $pausaUs, &$verificados, &$encontrados, &$semTipo, &$erros): void {
            foreach ($processos as $processo) {
                $verificados++;

                if ($verificados > 1 && $pausaUs > 0) {
                    usleep($pausaUs);
                }

                try {
                    $utilizacao = $this->utilizacao($cadastro->resolve((string) $processo->property_registration));
                } catch (PropertyNotFoundException|PropertyRegistryUnavailableException) {
                    $erros++;

                    continue;
                } catch (Throwable $erro) {
                    report($erro);
                    $erros++;

                    continue;
                }

                if ($utilizacao === null) {
                    $semTipo++;

                    continue;
                }

                $normalizado = null;

                if ($aplicar) {
                    $normalizado = $tipoImovel->apply($processo, $utilizacao)->normalized;

                    $audit->log(
                        logName: 'geo',
                        event: 'tipo-imovel-da-inscricao',
                        description: "Tipo do imóvel {$utilizacao} da inscrição gravado na solicitação #{$processo->id} (comando)",
                        properties: [
                            'viability_request_id' => $processo->id,
                            'inscricao' => $processo->property_registration,
                            'tipo_imovel' => $utilizacao,
                            'tipo_imovel_normalized' => $normalizado,
                        ],
                        subject: $processo,
                        result: 'gravado',
                    );
                }

                $encontrados[] = [
                    $processo->id,
                    $processo->protocol_number ?? '—',
                    $processo->status->value,
                    $processo->property_registration,
                    $utilizacao,
                    $aplicar ? ($normalizado ?? 'não reconhecido') : '—',
                ];
            }
        });

        $this->info("Verificados: {$verificados}");

        if ($encontrados !== [] && count($encontrados) <= self::LINHAS_NA_TABELA) {
            $this->table(['Processo', 'Protocolo', 'Situação', 'Inscrição', 'Tipo da inscrição', 'Código'], $encontrados);
        }

        $this->info(($aplicar ? 'Preenchidos: ' : 'Com tipo na inscrição: ').count($encontrados));
        $this->info("Inscrição sem tipo: {$semTipo}");
        $this->info("Inscrição não consultada (não encontrada ou base indisponível): {$erros}");
        $this->info('Sem inscrição imobiliária (sem fonte para o tipo): '.$this->semInscricao()->count());

        if (! $aplicar) {
            $this->info('Simulação: nada foi gravado. Rode com --aplicar para gravar.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Builder<ViabilityRequest>
     */
    private function consulta(): Builder
    {
        return $this->semTipo()
            ->whereNotNull('property_registration')
            ->where('property_registration', '!=', '');
    }

    /**
     * @return Builder<ViabilityRequest>
     */
    private function semInscricao(): Builder
    {
        return $this->semTipo()
            ->where(fn (Builder $sem) => $sem->whereNull('property_registration')->orWhere('property_registration', ''));
    }

    /**
     * Processos sem tipo, fora os rascunhos (ainda não protocolados).
     *
     * @return Builder<ViabilityRequest>
     */
    private function semTipo(): Builder
    {
        return ViabilityRequest::query()
            ->where(fn (Builder $semTipo) => $semTipo->whereNull('tipo_imovel')->orWhere('tipo_imovel', ''))
            ->when($this->option('processo') !== null, fn (Builder $q) => $q->whereKey((int) $this->option('processo')))
            ->when($this->option('processo') === null, fn (Builder $q) => $q->where('status', '!=', ViabilityRequestStatus::Rascunho->value))
            ->when($this->option('dias') !== null, fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(max(1, (int) $this->option('dias')))));
    }

    private function utilizacao(PropertyRegistryResult $resultado): ?string
    {
        $uso = $resultado->cadastro?->tipo_imovel ?? $resultado->raw['Utilizacao'] ?? null;
        $uso = is_string($uso) ? trim($uso) : '';

        return $uso !== '' ? $uso : null;
    }
}
