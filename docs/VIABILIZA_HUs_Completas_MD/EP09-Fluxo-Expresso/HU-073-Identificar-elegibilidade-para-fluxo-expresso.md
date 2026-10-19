# HU-073 — Identificar elegibilidade para fluxo expresso

## Épica
**EP09 — Fluxo Expresso**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Decidir, sobre a solicitação já protocolada, se o motor conclui no fluxo expresso ou se encaminha o processo à análise técnica, reexecutando o enquadramento em vez de confiar na simulação pré-protocolo.

## História de Usuário
**Como** sistema,  
**quero** classificar o processo protocolado como elegível ou não ao expresso,  
**para** deferir ou indeferir só quando a regra autoriza decisão automática e, nos demais casos, mandar à análise.

## Atores e permissões
- Não há tela de elegibilidade. O ator é o processamento: `DecidirFluxoExpressoJob` depois de `SolicitacaoProtocolada`, o comando de reavaliação e, na contingência, a chamada direta a `FluxoExpressoService::decide`.
- O ator humano, quando existe, é gravado em `decided_by_user_id` só na decisão. No encaminhamento à análise, o analista permanece nulo.
- Consultar o resultado depois é a HU-076, com permissão `consultar-solicitacoes`.

## Fluxo evidenciado
1. O serviço só decide solicitação `protocolada`, ou `em_analise` sem analista, sem decisão e com análise nula ou `para_distribuir`. Fora isso, devolve o estado atual sem nova transição.
2. Com `features.fluxo_expresso` desligado, encaminha à análise com o motivo de expresso desativado. Não cria decisão.
3. Exclusão exclusiva de atividades, sem o CNAE gatilho da sede, não passa pelo enquadramento: o caminho é deferimento (HU-074).
4. Exclusão do CNAE gatilho em inscrição com sede ativa só defere se o requerente for o titular e tiver confirmado a perda da condição. Sem confirmação, ou se não for o titular, vai à análise.
5. Nos demais casos, resolve de novo zona, camadas, regras territoriais e viabilidade por CNAE.
6. `ResolvedViability::elegivelExpresso` é verdadeiro só quando há CNAE e todos têm fluxo `expresso`. Qualquer fluxo `analise`, ou lista vazia, torna o conjunto inelegível e o processo vai à análise, com o motivo e o CNAE que o tirou do expresso.
7. Alto risco sem ramo expresso curado na planilha vai à análise antes do teste geral de elegibilidade, salvo se o consolidado já for `nao_permitido`: nesse caso a emissão indefere.
8. Veredito consolidado `pendente` vai à análise, sem decisão.
9. CNAE gatilho com intenção de sede também vai à análise.
10. O encaminhamento transiciona para `em_analise`, deposita no setor `analise.setor_triagem_id` se o setor existir e estiver ativo, inicia a etapa de distribuição com SLA e grava a queda. Não dispara `ResultadoEmitido`.

## Regras de negócio evidenciadas
- A simulação da HU-141 não define a elegibilidade. A decisão resolve de novo.
- Um único CNAE fora do expresso torna o processo inelegível.
- Sem zona que permita veredito, o consolidado pendente não vira deferimento nem indeferimento.
- Toggle do expresso desligado encaminha tudo o que ainda pode ser decidido à análise, com auditoria.
- Camada territorial indisponível, dentro da janela e com o processo ainda protocolado, devolve espera (`DecisionResult::aguardando`) sem encaminhar naquela passada.
- Empate de zona e divergência entre desenho e endereçamento ou inscrição encaminham à análise.
- Regra territorial que pede análise encaminha com o motivo da regra. A que pede indeferimento segue na HU-075.

## Validações
- Releitura do status sob lock. Segunda passagem não redispara evento se o processo já saiu de protocolada e não pode ser reavaliado.
- Idempotência também pela unicidade da decisão por solicitação, quando a emissão chega a criar `ViabilityDecision`.

## Critérios de aceite
- Dado processo protocolado em que todos os CNAEs resolvem para o fluxo expresso, o consolidado não é pendente e não há veto, alto risco fora do expresso, gatilho de sede nem regra territorial de análise, quando o motor decide, então segue para emissão de decisão.
- Dado qualquer CNAE com fluxo de análise, quando decide, então o status vai a `em_analise`, sem `ViabilityDecision` e sem `ResultadoEmitido`.
- Dado `features.fluxo_expresso` falso, quando decide um processo protocolado, então encaminha à análise com o motivo de desativado.
- Dado consolidado pendente, quando decide, então encaminha à análise e não inventa permitido nem não permitido.
- Dado processo já decidido ou em análise com analista, quando o job roda de novo, então não há nova transição.

## Divergências da redação anterior
A elegibilidade não é uma etiqueta gravada antes do protocolo. É a passagem do motor depois de protocolar, com várias saídas para a análise além do risco: toggle, território, pergunta, zona, sede e veredito pendente. Alto risco não permitido no local é indeferido, não encaminhado.

## Evidências
- `App\Services\Expresso\FluxoExpressoService::decide` e `decidirSobLock`
- `App\Services\Solicitacao\ResolvedViability::elegivelExpresso` e `temAltoRiscoForaDoExpresso`
- `App\Jobs\DecidirFluxoExpressoJob`
- `App\Listeners\AvaliarFluxoExpresso`
- Parâmetro `features.fluxo_expresso`

## Não evidenciado
- Tela em que o cidadão ou o analista marque o processo como elegível.
- Uso do `simulation_snapshot` como fonte da decisão.
- Elegibilidade que ignore o veto locacional `nao_permitido` em favor do encaminhamento por alto risco.
