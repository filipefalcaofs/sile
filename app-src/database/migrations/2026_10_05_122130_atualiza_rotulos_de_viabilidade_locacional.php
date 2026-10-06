<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renomearTipo('renovacao-tvl', 'Renovação de TVL', 'Renovação de viabilidade locacional');
        $this->renomearTipo('tvl', 'Termo de Viabilidade de Localização', 'Viabilidade locacional');
        $this->atualizarDeclaracao(
            'Declaro que os condicionantes do TVL provisório estão atendidos no momento da renovação.',
            'Declaro que os condicionantes da viabilidade provisória estão atendidos no momento da renovação.',
        );
        $this->atualizarDescricoes($this->descricoesNovas());
    }

    public function down(): void
    {
        $this->renomearTipo('renovacao-tvl', 'Renovação de viabilidade locacional', 'Renovação de TVL');
        $this->renomearTipo('tvl', 'Viabilidade locacional', 'Termo de Viabilidade de Localização');
        $this->atualizarDeclaracao(
            'Declaro que os condicionantes da viabilidade provisória estão atendidos no momento da renovação.',
            'Declaro que os condicionantes do TVL provisório estão atendidos no momento da renovação.',
        );
        $this->atualizarDescricoes($this->descricoesAntigas());
    }

    private function renomearTipo(string $code, string $de, string $para): void
    {
        if (! Schema::hasTable('viability_service_types')) {
            return;
        }

        DB::table('viability_service_types')
            ->where('code', $code)
            ->where('name', $de)
            ->update(['name' => $para]);
    }

    private function atualizarDeclaracao(string $de, string $para): void
    {
        if (! Schema::hasTable('service_declarations')) {
            return;
        }

        DB::table('service_declarations')->where('body', $de)->update(['body' => $para]);
    }

    /**
     * @param  array<string, string>  $descricoes
     */
    private function atualizarDescricoes(array $descricoes): void
    {
        if (! Schema::hasTable('parameters')) {
            return;
        }

        foreach ($descricoes as $key => $description) {
            DB::table('parameters')->where('key', $key)->update(['description' => $description]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function descricoesNovas(): array
    {
        return [
            'risco.dimensao_tvl' => 'Dimensão de risco que prevalece no encaminhamento da viabilidade locacional (default municipal; confirmar com a SEDUR)',
            'renovacao.intervalo_meses' => 'Intervalo mínimo (meses) entre renovações da mesma viabilidade locacional. Dentro do intervalo o sistema recusa um novo protocolo',
            'features.simulacao_protocolo' => 'SOMENTE HOMOLOGAÇÃO: processos do simulador REGIN com veredito pendente registram a viabilidade locacional como permitida. Desligado, seguem o fluxo normal (pendente → análise). NUNCA ligar em produção',
            'features.notificacao_resultado_expresso' => 'Habilita a notificação por e-mail do resultado do fluxo expresso ao requerente (sem anexo da viabilidade locacional; canais plenos no EP11)',
            'expresso.tvl.prefixo' => 'Prefixo do número da viabilidade locacional ({prefixo}-AAAA-NNNNNN)',
            'analise.tvl.assinatura.modo' => 'Modo de assinatura do PDF da viabilidade locacional: imagem do diretor ou nenhuma; assinatura digital gov.br/ICP é gancho da SEDUR',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function descricoesAntigas(): array
    {
        return [
            'risco.dimensao_tvl' => 'Dimensão de risco que prevalece no encaminhamento/TVL (default municipal; confirmar com a SEDUR)',
            'renovacao.intervalo_meses' => 'Intervalo mínimo (meses) entre renovações do mesmo TVL. Dentro do intervalo o sistema recusa um novo protocolo',
            'features.simulacao_protocolo' => 'SOMENTE HOMOLOGAÇÃO: processos do simulador REGIN com veredito pendente emitem TVL como permitidos. Desligado, seguem o fluxo normal (pendente → análise). NUNCA ligar em produção',
            'features.notificacao_resultado_expresso' => 'Habilita a notificação por e-mail do resultado do fluxo expresso ao requerente (sem anexo de TVL; canais plenos no EP11)',
            'expresso.tvl.prefixo' => 'Prefixo do número de produto TVL do deferimento ({prefixo}-AAAA-NNNNNN)',
            'analise.tvl.assinatura.modo' => 'Modo de assinatura do TVL PDF: imagem do diretor (legado) ou nenhuma; assinatura digital gov.br/ICP é gancho → SEDUR',
        ];
    }
};
