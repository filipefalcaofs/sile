# HU-048 — Aplicar regra de baixo risco

## Épica
**EP06 — Classificação de Risco**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Encaminhar o nível municipal de chave `baixo_a` conforme o mapa de risco. No padrão, `baixo_a` é elegível ao fluxo expresso. O rótulo de tela é “Baixo”.

## História de Usuário
**Como** sistema,  
**quero** tratar o CNAE classificado como Baixo A pelo mapa de encaminhamento,  
**para** só seguir ao expresso quando o parâmetro e os gatilhos permitirem.

## Atores e permissões
- Quem altera o mapa é quem mantém parâmetros (`manter-parametros`). A chave `baixo_a` só aceita `expresso` ou `analise`.
- A classificação em si é do motor, não de um botão de baixo risco.

## Fluxo evidenciado
1. `RiscoMunicipal::BaixoA` vale `baixo_a`, rótulo “Baixo”, severidade 1.
2. O padrão de `risco.mapa_encaminhamento` é `baixo_a` → `expresso`, `baixo_b` → `expresso`, `alto` → `analise`.
3. Com dimensão decisiva municipal e nível `baixo_a`, o motivo diz que o nível é elegível ao fluxo expresso.
4. Se o mapa for alterado para `analise`, o mesmo nível vai à análise. Nível fora do mapa também vai à análise.
5. Qualquer gatilho ativo acionado no contexto substitui o fluxo por `analise`, mesmo em baixo risco. O motivo passa a ser o do gatilho.
6. Ramo da planilha resolvido prevalece sobre o mapa: o fluxo é o do ramo, não o de `baixo_a`.
7. A severidade serve para escolher o CNAE mais restritivo da solicitação. Baixo A é o menos severo entre as três chaves municipais.

## Regras de negócio evidenciadas
- Baixo risco, no código municipal, é a chave `baixo_a`. Não é um cálculo de porte ou área.
- `baixo_b` não é baixo risco nesta HU. O rótulo dele é “Médio Risco” (HU-049).
- Gatilho ou ausência de classificação impedem o expresso mesmo que o mapa diga `expresso` para `baixo_a`. A ausência cai em análise antes do mapa.

## Validações
- O parâmetro rejeita mapa sem a chave `baixo_a` ou com valor diferente de `expresso` e `analise`.
- Importação só grava `baixo_a` quando a célula do decreto é `BAIXO A`.

## Critérios de aceite
**Dado** que o CNAE está `baixo_a` na versão municipal, a dimensão decisiva é municipal, o mapa aponta `expresso` e não há gatilho nem ramo da planilha,  
**Quando** o motor classifica,  
**Então** o fluxo é `expresso` e o motivo cita o nível `baixo_a`.

**Dado** que um gatilho ativo está no contexto,  
**Quando** o CNAE é `baixo_a`,  
**Então** o fluxo é `analise` e o motivo é o do gatilho.

## Divergências da redação anterior
A redação anterior descrevia “regra de baixo risco” sem a chave `baixo_a`, o rótulo “Baixo” e o mapa parametrizado.

## Evidências
- `RiscoMunicipal::BaixoA`
- `RiscoClassificationService::resolveEncaminhamento`
- Parâmetro `risco.mapa_encaminhamento` em `ParameterSeeder` e `config/viabiliza.php`

## Não evidenciado
- Deferimento automático nesta HU. O motor só marca elegibilidade. Quem emite o deferimento é o fluxo expresso.
- Tratamento de `baixo_b` como baixo risco.
