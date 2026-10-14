<?php

namespace App\Services\Ai;

use App\Models\AiConfiguration;
use App\Models\Parameter;
use App\Support\Settings;
use Illuminate\Database\QueryException;

/**
 * Portão de disponibilidade das funções de IA (Fase 14) — degradação honesta,
 * anti-fachada. Uma função só está disponível quando AMBOS são verdadeiros:
 *  1. o toggle features.ia_{função} não foi desligado pelo gestor (HU-014);
 *  2. existe uma AiConfiguration ATIVA da capacidade exigida (text|vision|embeddings).
 *
 * O catálogo nasce sem valor gravado (padrão desligado). Nesse estado a função
 * acompanha o provedor: ativar a IA em Configuração de IA basta. Valor 0
 * gravado continua sendo o interruptor que a operação desliga de verdade.
 *
 * OFF ou sem provedor ⇒ a camada de serviço NÃO instancia o agente, NÃO despacha
 * o job, NÃO chama o provedor e NÃO simula resultado. O job re-checa este portão
 * no handle (a configuração pode mudar entre enfileirar e processar).
 */
class AiFeatureGate
{
    public function available(string $function, string $capability = 'text'): bool
    {
        return $this->unavailableReason($function, $capability) === null;
    }

    /**
     * Motivo honesto da indisponibilidade (para a ficha comunicar, nunca
     * silenciar). Null quando a função pode rodar.
     */
    public function unavailableReason(string $function, string $capability = 'text'): ?string
    {
        if (! $this->featureEnabled($function)) {
            return 'A função de IA está desligada nos parâmetros.';
        }

        if (! $this->hasActiveProvider($capability)) {
            return 'Não há provedor de texto ativo. Cadastre e ative um em Configuração de IA.';
        }

        return null;
    }

    /**
     * A infra de RAG (Onda 3) está disponível quando há um provedor de embeddings
     * ATIVO. Diferente de available(): a indexação/busca por similaridade é
     * INFRAESTRUTURA dos assistentes (HU-120/121), não uma função togglável por si
     * — o toggle de feature é checado na camada do assistente que a consome. Sem
     * provedor de embeddings, indexar/buscar fica indisponível (não chama, não
     * simula).
     */
    public function embeddingsAvailable(): bool
    {
        return $this->hasActiveProvider('embeddings');
    }

    /**
     * Sem linha no catálogo, vale o config (testes e ambiente sem migração).
     * Com linha e sem valor gravado, o padrão do seed não bloqueia: a função
     * fica disponível assim que houver provedor. Valor 0 gravado pelo gestor
     * desliga de verdade, mesmo que o cache de Settings ainda diga o contrário.
     */
    private function featureEnabled(string $function): bool
    {
        $key = "features.ia_{$function}";

        try {
            $parameter = Parameter::query()->where('key', $key)->first();
        } catch (QueryException) {
            return Settings::enabled("ia_{$function}");
        }

        if ($parameter === null) {
            return Settings::enabled("ia_{$function}");
        }

        if ($parameter->value === null) {
            return true;
        }

        return (bool) $parameter->typedValue();
    }

    private function hasActiveProvider(string $capability): bool
    {
        return AiConfiguration::query()
            ->where('capability', $capability)
            ->where('active', true)
            ->exists();
    }
}
