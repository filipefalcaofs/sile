# HU-111 — Migrar dados do sistema legado

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Não há carga do sistema legado para o Viabiliza nem estratégia de convivência implementada.

## História de Usuário
**Como** gestor, **quero** trazer processos e viabilidades do sistema anterior. Não há comando, serviço nem rota que faça essa migração.

## Atores e permissões
- Não há permissão de migração.

## Fluxo evidenciado
1. Não existe importador de processos, decisões ou cadastros do legado.
2. O comando de renumeração de TVL só reescreve números já gravados em `viability_decisions` no formato antigo. Não importa base externa.
3. Menções a “legado” no código são decisão sem `decision_trace`, vocabulário de formulário ou paridade de tela. Não são migração.

## Regras de negócio evidenciadas
- Decisão antiga sem trace é explicada como não registrada. Isso não copia o sistema anterior.

## Validações
- Não há arquivo, conexão nem de-para de migração.

## Critérios de aceite
- Nenhum comando em `app/Console` importa a base do sistema legado.
- Renumerar TVL não cria processo histórico externo.

## Divergências da redação anterior
O texto pedia migrar processos, viabilidades e cadastros e definir convivência. Isso não está implementado.

## Evidências
- Ausência de serviço de migração em `app/`.
- `app/Console/Commands/TvlRenumerarCommand.php` (renumeração interna, não importação)

## Não evidenciado
- Origem, mapeamento, carga e convivência com o sistema legado.
