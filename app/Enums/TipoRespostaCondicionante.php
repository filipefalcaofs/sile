<?php

namespace App\Enums;

/**
 * Tipo de resposta esperada para a pergunta de uma condicionante de risco
 * (mecanismo "DI" — a resposta do requerente reclassifica o risco;
 * HU-019/HU-048, RN-004/005/008). A planilha VISA opera hoje só com perguntas
 * de sim/não; 'selecao' fica reservado para perguntas de múltipla escolha que
 * os mantenedores (06-06) poderão cadastrar sem migração.
 */
enum TipoRespostaCondicionante: string
{
    case BooleanoSimNao = 'booleano_sim_nao';
    case Selecao = 'selecao';

    public function label(): string
    {
        return match ($this) {
            self::BooleanoSimNao => 'Sim ou Não',
            self::Selecao => 'Seleção (múltipla escolha)',
        };
    }
}
