<?php

namespace App\Console\Commands;

use App\Models\ViabilityRequest;
use App\Services\Realty\LocalizacaoInscricaoService;
use Illuminate\Console\Command;

/**
 * Preenche a Utilizacao do Cadastro Imobiliário nos processos que ainda não a têm
 * (os de antes de ela ser gravada no protocolo). Uma consulta por processo, com
 * pausa entre elas para não sobrecarregar a API da SEFAZ via SEDUR. Sem `--aplicar`,
 * consulta e conta, mas não grava. O `tipo_imovel` do REGIN nunca é alterado.
 */
class PreencherTipoImovelCadastroCommand extends Command
{
    protected $signature = 'processos:preencher-tipo-imovel
        {--processo= : Só este processo (id)}
        {--desde= : Só processos criados a partir desta data (AAAA-MM-DD)}
        {--limite= : Máximo de processos nesta passada}
        {--pausa-ms=250 : Pausa entre as consultas, em milissegundos}
        {--aplicar : Grava o valor (sem esta opção, só simula)}';

    protected $description = 'Preenche o tipo do imóvel (Utilizacao do cadastro) nos processos que ainda não o têm';

    public function handle(LocalizacaoInscricaoService $localizacao): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $pausaUs = max(0, (int) $this->option('pausa-ms')) * 1000;

        $consulta = ViabilityRequest::query()
            ->whereNotNull('property_registration')
            ->where('property_registration', '!=', '')
            ->whereNull('tipo_imovel_cadastro')
            ->when($this->option('processo'), fn ($q, $id) => $q->whereKey((int) $id))
            ->when($this->option('desde'), fn ($q, $data) => $q->whereDate('created_at', '>=', $data))
            ->orderBy('id');

        if ($this->option('limite')) {
            $consulta->limit(max(1, (int) $this->option('limite')));
        }

        $consultados = $preenchidos = $semUtilizacao = 0;

        foreach ($consulta->get() as $processo) {
            $consultados++;
            $utilizacao = $localizacao->localizar($processo->property_registration)->utilizacao;

            if ($utilizacao === null) {
                $semUtilizacao++;
            } else {
                $preenchidos++;

                if ($aplicar) {
                    $processo->forceFill(['tipo_imovel_cadastro' => mb_substr($utilizacao, 0, 120)])->save();
                }
            }

            if ($pausaUs > 0) {
                usleep($pausaUs);
            }
        }

        $this->info(($aplicar ? 'Gravado: ' : 'Simulação, nada foi gravado: ')."{$consultados} processos consultados, {$preenchidos} com tipo do imóvel, {$semUtilizacao} sem Utilizacao (cadastro fora do ar, inscrição não encontrada ou campo vazio).");

        if (! $aplicar && $preenchidos > 0) {
            $this->info('Rode com --aplicar para gravar.');
        }

        return self::SUCCESS;
    }
}
