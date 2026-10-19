# HU-141 — Simular viabilidade antes de protocolar

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Executar, a pedido do requerente e ainda em rascunho, os motores reais de viabilidade sobre os dados já informados, gravar o snapshot orientativo e permitir protocolar mesmo quando a tendência é desfavorável.

## História de Usuário
**Como** requerente dono do rascunho,  
**quero** simular a viabilidade antes de protocolar,  
**para** ver a tendência por CNAE e decidir se ajusto o pedido ou se protocolo mesmo assim.

## Atores e permissões
- Dono efetivo, status rascunho (`ViabilityRequestPolicy::update`).
- Rota `POST portal/solicitacoes/{solicitacao}/simular`, com autenticação do portal e `portal.solicitacao`.

## Fluxo evidenciado
1. `SimulacaoSolicitacaoService` chama `SolicitacaoViabilityResolver` com a solicitação. Sem polígono, o resolver segue pela via de CNAE, sem território.
2. O resultado consolidado, o snapshot por CNAE, as versões de regra e `simulated_at` são gravados na solicitação. A execução é auditada como `simulacao`.
3. A resposta volta ao wizard com a mensagem de que o resultado é orientativo e não impede o protocolo, e com o flash da simulação.
4. O wizard relê o snapshot persistido. Se `simulated_at` ou `simulation_resultado` estiver nulo, não há simulação para exibir.
5. Imóvel, área ou CNAEs alterados chamam `markSimulationStale` e o snapshot deixa de ser exibido até nova simulação.
6. No protocolo, tendência `nao_permitido` não bloqueia. Se o requerente envia `proceed_despite`, a ciência fica em `applicant_proceeded_despite`. A decisão posterior do expresso não reutiliza este snapshot: ela resolve de novo.

## Regras de negócio evidenciadas
- A simulação não muda o status.
- O veredito por CNAE é o do motor. Consolidação pendente permanece pendente; o serviço não transforma pendência em permitido ou não permitido.
- O snapshot guardado é orientativo. A decisão autoritativa é outra passagem do resolver, depois do protocolo.
- A prop `simulacaoEnabled` enviada ao wizard está fixa em verdadeiro. O `store` da simulação não consulta `features.simulacao_solicitacao`.

## Validações
- Autorização de atualização do rascunho. Não há campos de formulário neste POST.
- A ciência no protocolo é booleana opcional e só produz efeito com simulação `nao_permitido`.

## Critérios de aceite
- Dado o dono de um rascunho, quando aciona a simulação, então o processo guarda resultado, snapshot, versões e data, a auditoria registra a execução e a mensagem informa que o resultado não impede o protocolo.
- Dado resultado `nao_permitido`, quando o dono protocola com `proceed_despite`, então o protocolo ocorre e a ciência fica registrada.
- Dado resultado diferente de `nao_permitido`, quando envia `proceed_despite`, então o protocolo não grava a ciência.
- Dado alteração posterior de imóvel ou de CNAEs, quando o wizard reabre, então a simulação anterior não é reexibida até nova execução.
- Dado usuário sem permissão de editar o rascunho, quando chama a rota, então a policy nega.

## Divergências da redação anterior
A simulação não dispara sozinha ao terminar imóvel e atividades. É um POST explícito. O código não devolve sugestão textual do que revisar. Não gasta número de protocolo nem exige BAP.

## Evidências
- `App\Http\Controllers\Portal\SolicitacaoSimulacaoController`
- `App\Services\Solicitacao\SimulacaoSolicitacaoService`
- `App\Http\Controllers\Portal\SolicitacaoController::simulationPayload`
- `App\Services\Solicitacao\ProtocolarSolicitacaoService` (`proceedDespite`, `unfavorableTendency`)
- `routes/portal.php` (`portal.solicitacoes.simular`)

## Não evidenciado
- Bloqueio do protocolo pela tendência simulada.
- Uso deste snapshot como decisão do fluxo expresso.
- Interruptor efetivo `features.simulacao_solicitacao` no controller, apesar de existir na config e no comentário da classe.
