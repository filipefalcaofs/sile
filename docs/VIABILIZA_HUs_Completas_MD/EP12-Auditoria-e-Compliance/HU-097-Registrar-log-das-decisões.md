# HU-097 — Registrar log das decisões

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Gravar a decisão de viabilidade — automática ou de análise técnica — com desfecho, versão de regras e responsável, na trilha de auditoria.

## História de Usuário
**Como** sistema, **quero** registrar cada decisão deferida ou indeferida, **para** que a fundamentação e o responsável fiquem rastreáveis depois.

## Atores e permissões
- O fluxo expresso registra a decisão sem usuário humano (`FluxoExpressoService`, evento `decisao`).
- O analista autenticado no console registra a decisão técnica (`AnaliseTecnicaDecisionService`). A transição de status usa a máquina de estados do processo.
- A consulta posterior dessa trilha exige `consultar-auditoria`. O registro em si ocorre no ato da decisão, não numa tela própria desta HU.

## Fluxo evidenciado
1. A decisão cria um `ViabilityDecision` com desfecho, resultado consolidado, `per_cnae`, `rules_versions`, fundamentação, `decision_trace`, motivo e data.
2. No fluxo técnico, o processo passa a deferida ou indeferida e o log `analise` / `decisao` guarda protocolo, revisão, desfecho, número TVL quando houver, analista e resumo por CNAE. A versão de regras representativa vai no registro.
3. No fluxo expresso, o mesmo evento `decisao` é gravado nos caminhos de deferimento e indeferimento automático. Falha de decisão gera `decisao-falha`.
4. O indeferimento por ausência de BAP e o indeferimento por prazo de convite também criam `ViabilityDecision` e registram `decisao`.

## Regras de negócio evidenciadas
- A decisão técnica só grava sede de escritório virtual quando o desfecho é deferida, a ficha marcou sede e o processo tem o CNAE gatilho.
- Renovação deferida grava tipo e validade da TVL; o número de produto TVL novo não é gerado na renovação.
- A versão de regras gravada é a do snapshot da ficha (`engine_rules_versions`), não uma releitura posterior do motor.

## Validações
- O log da decisão técnica exige analista autenticado e ficha de análise já existente; o serviço não abre uma tela de “registrar log”.
- Desfecho fora de deferida/indeferida não é o caminho evidenciado neste registro.

## Critérios de aceite
- Dada uma decisão técnica concluída, o `activity_log` contém evento `decisao` com desfecho, protocolo e versão de regras.
- Dado um deferimento ou indeferimento do fluxo expresso, o mesmo evento é gravado sem simular analista.
- Dada falha ao decidir no expresso, o evento registrado é `decisao-falha`, não um desfecho inventado.

## Divergências da redação anterior
A redação genérica falava em consulta a GIS e REDESIM dentro do registro do log. O código só persiste a decisão e a trilha; integrações são outros fluxos.

## Evidências
- `app/Services/Analise/AnaliseTecnicaDecisionService.php` (`registrarDecisao`, evento `decisao`)
- `app/Services/Expresso/FluxoExpressoService.php` (evento `decisao` e `decisao-falha`)
- `app/Services/Expresso/IndeferirSemBapService.php`
- `app/Services/Analise/IndeferirPorPrazoConviteService.php`

## Não evidenciado
- Tela exclusiva para “registrar log”.
- Registro de decisão que não passe por esses serviços.
