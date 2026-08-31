<?php

namespace App\Services\EscritorioVirtual;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;

/**
 * Atividades que uma SEDE de escritorio virtual pode exercer (RN-EV-05c):
 * {CNAE gatilho} uniao Anexo A. O CNAE gatilho (default 8211-3/00,
 * parametrizavel) caracteriza a sede e por isso nao figura no Anexo A —
 * precisa ser excluido da conferencia, senao toda sede seria indeferida
 * pelo proprio CNAE que a define (SEDUR 2026-08-31). O codigo em si vem de
 * SedeEscritorioVirtualGatilho::cnaeGatilho() — fonte unica de verdade,
 * evitando reler o parametro aqui.
 */
class SedeAtividadesResolver
{
    public function __construct(private readonly SedeEscritorioVirtualGatilho $gatilho) {}

    /**
     * Existe versao VIGENTE do dominio AtividadesEscritorioVirtual? O Anexo A
     * nao tem endpoint conhecido (`[OPEN-EV-2-bis]`) e depende de importacao
     * administrativa versionada — numa janela sem vigente (seeder ainda nao
     * rodou, ou publicacao de versao nova em andamento), `permitida()`
     * reprovaria TODOS os codigos (menos o gatilho), o que pareceria "nenhuma
     * atividade permitida" quando na verdade e "dado ausente". Quem chama
     * `naoPermitidos()` para decidir bloqueio deve checar isto ANTES: lista
     * indisponivel nao e "toda atividade fora do Anexo A", e um indeferimento
     * automatico sobre essa base seria uma decisao tomada sem dado (o mesmo
     * principio anti-fachada que o FluxoExpressoService honra em toda parte).
     */
    public function listaDisponivel(): bool
    {
        return RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->exists();
    }

    /**
     * Um codigo e permitido a sede se for o CNAE gatilho ou constar do
     * Anexo A na versao vigente do dominio.
     */
    public function permitida(string $cnaeCode): bool
    {
        if ($this->normalizar($cnaeCode) === $this->gatilho->cnaeGatilho()) {
            return true;
        }

        return VirtualOfficeActivityCnae::permitidoNoAnexo($cnaeCode, VirtualOfficeActivityCnae::ANEXO_A);
    }

    /**
     * Codigos que NAO podem ser exercidos pela sede, na ordem e na grafia
     * de entrada. Lista vazia = todas permitidas.
     *
     * @param  iterable<string>  $cnaeCodes
     * @return array<int, string>
     */
    public function naoPermitidos(iterable $cnaeCodes): array
    {
        $naoPermitidos = [];

        foreach ($cnaeCodes as $cnaeCode) {
            if (! $this->permitida($cnaeCode)) {
                $naoPermitidos[] = $cnaeCode;
            }
        }

        return $naoPermitidos;
    }

    /** Codigo de entrada normalizado a digitos, para comparar com o gatilho ja normalizado. */
    private function normalizar(string $code): string
    {
        return preg_replace('/\D/', '', $code) ?? '';
    }
}
