<?php

namespace App\Enums;

/**
 * Efeito da regra territorial quando ela incide sobre a solicitação
 * (RT-01 a RT-05):
 *
 * - Indeferir: indeferimento automático com texto legal (RT-01).
 * - EncaminharAnalise: vai à análise da SEDUR com o motivo fixo da regra
 *   (RT-02 Vela Branca, RT-03, RT-04).
 * - PermitirExpresso: allow-list — estar na lista NÃO defere; apenas devolve
 *   a solicitação ao fluxo normal (Itaigara).
 * - RestringirUso: fora da allow-list de vias, o uso sujeito à observação do
 *   Quadro 10 não é permitido (RT-05 a/b).
 */
enum TerritorialRuleEffect: string
{
    case Indeferir = 'indeferir';
    case EncaminharAnalise = 'encaminhar_analise';
    case PermitirExpresso = 'permitir_expresso';
    case RestringirUso = 'restringir_uso';

    public function label(): string
    {
        return match ($this) {
            self::Indeferir => 'Indeferimento automático',
            self::EncaminharAnalise => 'Encaminhar à análise',
            self::PermitirExpresso => 'Allow-list de expresso',
            self::RestringirUso => 'Restrição de uso por via',
        };
    }
}
