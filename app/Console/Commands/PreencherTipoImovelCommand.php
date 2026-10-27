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
 * Preenche o tipo do imóvel dos processos em aberto que ficaram sem ele (o
 * REGIN não declarou) com o uso do imóvel no Cadastro Imobiliário
 * (Utilizacao da inscrição). Tipo já gravado não muda. Sem `--aplicar`, só
 * mostra o levantamento.
 */
class PreencherTipoImovelCommand extends Command
{
    protected $signature = 'processos:preencher-tipo-imovel
        {--processo= : Preenche só este processo (id), em qualquer situação}
        {--aplicar : Grava o tipo (sem esta opção, só lista)}';

    protected $description = 'Preenche o tipo do imóvel dos processos em aberto pela inscrição imobiliária';

    public function handle(PropertyRegistryLookup $cadastro, ReginTipoImovelApplier $tipoImovel, AuditService $audit): int
    {
        $aplicar = (bool) $this->option('aplicar');

        $verificados = 0;
        $encontrados = [];
        $semTipo = 0;
        $erros = 0;

        $this->consulta()->orderBy('id')->chunkById(100, function ($processos) use ($cadastro, $tipoImovel, $audit, $aplicar, &$verificados, &$encontrados, &$semTipo, &$erros): void {
            foreach ($processos as $processo) {
                $verificados++;

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

        if ($encontrados !== []) {
            $this->table(['Processo', 'Protocolo', 'Situação', 'Inscrição', 'Tipo da inscrição', 'Código'], $encontrados);
        }

        $this->info(($aplicar ? 'Preenchidos: ' : 'Com tipo na inscrição: ').count($encontrados));
        $this->info("Inscrição sem tipo: {$semTipo}");
        $this->info("Inscrição não consultada (não encontrada ou base indisponível): {$erros}");

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
        $query = ViabilityRequest::query()
            ->whereNotNull('property_registration')
            ->where('property_registration', '!=', '')
            ->where(fn (Builder $semTipo) => $semTipo->whereNull('tipo_imovel')->orWhere('tipo_imovel', ''));

        if ($this->option('processo') !== null) {
            return $query->whereKey((int) $this->option('processo'));
        }

        return $query->whereIn('status', [
            ViabilityRequestStatus::EmAnalise->value,
            ViabilityRequestStatus::EmPendencia->value,
        ]);
    }

    private function utilizacao(PropertyRegistryResult $resultado): ?string
    {
        $uso = $resultado->cadastro?->tipo_imovel ?? $resultado->raw['Utilizacao'] ?? null;
        $uso = is_string($uso) ? trim($uso) : '';

        return $uso !== '' ? $uso : null;
    }
}
