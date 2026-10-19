# HU-046 — Versionar regras da LOUOS

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Guardar Quadros 10 e 11A, planilha de tratamento e regras territoriais em versões de `rule_versions`, publicar por quatro olhos e simular um rascunho sem alterar a versão vigente.

## História de Usuário
**Como** administrador,  
**quero** editar um rascunho e publicar uma versão nova, com outra pessoa publicando,  
**para** a decisão usar a versão vigente e ainda ser reproduzível numa data ou num sandbox.

## Atores e permissões
- Consulta do hub Motor de regras: `consultar-louos` ou `consultar-cnaes` (administrador, Analista Terceirizado e Coordenador consultam; cidadão não).
- Publicar, ativar, importar, editar rascunho e simular: `manter-louos`, somente administrador.
- Quadro 10, Quadro 11A, planilha (`risco_tratamento`) e domínio `territorial` são sensíveis: a publicação exige autor do rascunho diferente de quem publica.

## Fluxo evidenciado
1. Cada domínio tem cabeçalho em `rule_versions`. O motor resolve a versão em três modos: override do sandbox, vigente na data informada, ou vigente agora.
2. O rascunho dos quadros permite abrir, incluir, alterar e excluir linha, importar CSV e descartar. Publicar gera versão nova. A anterior permanece para reprodução. Ativar uma versão antiga troca a vigente sem apagar o histórico.
3. A publicação recusa autor igual ao publicador, com a mensagem de quatro olhos.
4. Regras territoriais seguem o mesmo ciclo: rascunho, publicar, ativar e descartar.
5. `LouosSandboxSimulationService` reexecuta o motor real com `versoesOverride` na versão rascunho, compara o consolidado com o da vigente e devolve quantos cenários mudariam. A simulação corre em transação revertida. Não publica, não notifica e não grava decisão. Depois, fora da transação, audita o evento `simulacao` sem versão de publicação.
6. A amostra padrão é `viabiliza.louos.sandbox.amostra_padrao` (50), montada com CNAEs ativos e uma zona do Quadro 10 vigente. Não há arquivo próprio de cenários de processo.
7. Zona citada no rascunho do Quadro 10 e classe citada no rascunho do 11A precisam existir e estar ativas nos cadastros de zonas e de vias.

## Regras de negócio evidenciadas
- Decisão e consulta usam a versão resolvida, não a última linha editada fora de versão.
- Simulação não altera a vigente.
- Reimportar o seed territorial substitui as regras da versão vigente. Não duplica a versão.

## Validações
- Publicador precisa ser outro usuário que não o autor do rascunho.
- CSV e linhas do rascunho passam pelos importadores do quadro. Zona ou classe desconhecida bloqueia a publicação do rascunho em vez de virar `nao_encontrado` silencioso.

## Critérios de aceite
**Dado** que existe rascunho de um domínio sensível e quem publica é outro usuário,  
**Quando** a publicação é confirmada,  
**Então** nasce uma versão nova e a anterior deixa de ser a vigente, sem ser apagada.

**Dado** que o autor tenta publicar o próprio rascunho,  
**Quando** a publicação é submetida,  
**Então** ela é recusada.

**Dado** que um rascunho é simulado,  
**Quando** a simulação termina,  
**Então** o relatório conta divergências de consolidado e a versão vigente permanece a mesma.

**Dado** que o enquadramento recebe data ou override de versão,  
**Quando** o motor resolve o quadro,  
**Então** usa a versão daquela data ou a versão indicada, não a vigente atual.

## Divergências da redação anterior
A redação anterior falava em versionar regras sem quatro olhos, sem sandbox e sem os domínios que o código realmente versiona.

## Evidências
- `RuleDomain` (`louos_quadro10`, `louos_quadro11a`, `risco_tratamento`, `territorial`)
- `LouosEnquadramentoService::resolveVersion`
- `LouosController::publish`
- `LouosDraftController`, `TerritorialRuleController`
- `LouosSandboxSimulationService`
- Rotas `gestao.louos.publicar`, `rascunho.*`, `territoriais.*`, `sandbox.simular`

## Não evidenciado
- Versionamento do Quadro 7 ou de um Quadro 11 separado.
- Simulação sobre processos protocolados. A amostra sai do catálogo de CNAE e de uma zona do Quadro 10.
