# HU-052 — Registrar fundamentação legal

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Montar a lista de fundamentos do parecer só com o que o motor aplicou, mais o motivo quando o resultado fica pendente.

## História de Usuário
**Como** sistema,  
**quero** citar a base legal das dimensões identificadas,  
**para** o parecer não mencionar quadro que não chegou a ser aplicado.

## Atores e permissões
- A lista é produzida no enquadramento e copiada para a auditoria do evento `louos` / `enquadramento`.
- Os textos-base vêm do catálogo de textos decisórios (`base_legal.louos` e os motivos `louos.motivo.*`). A manutenção desses textos não é desta HU.

## Fluxo evidenciado
1. `buildFundamentacao` começa vazio.
2. Enquadramento `identificado`: inclui o texto `base_legal.louos` seguido do subgrupo, ou de “enquadramento de uso” se o subgrupo vier vazio.
3. Quadro 10 `identificado`: inclui `base_legal` da linha, quando preenchida, e o texto da LOUOS com “Quadro 10”.
4. Quadro 11A `identificado`: inclui `base_legal` da linha, quando preenchida, e o texto da LOUOS com “Quadro 11A”.
5. Se o consolidado está pendente, o motivo da pendência entra na lista. O quadro que ficou indisponível não é citado como regra aplicada.
6. Entradas repetidas são removidas.
7. A auditoria grava essa lista junto com CNAE, área, status do enquadramento, resultado e versões. A versão de regra registrada no evento é a de `risco_tratamento`.
8. Nas regras territoriais que indeferem, o fundamento do texto é o campo `base_legal` da regra RT, não esta lista do consolidado.

## Regras de negócio evidenciadas
- Dimensão que não está `identificado` não contribui com quadro.
- Base legal vazia da linha não gera item extra. O rótulo do quadro ainda entra se a dimensão foi identificada.
- Pendência registra o motivo e não finge que a regra faltante foi aplicada.

## Validações
- Não há conferência externa de vigência da lei neste método. O texto é o cadastrado no catálogo e a base legal gravada na linha da versão usada.

## Critérios de aceite
**Dado** que enquadramento, Quadro 10 e Quadro 11A estão identificados,  
**Quando** a fundamentação é montada,  
**Então** a lista cita o enquadramento, o Quadro 10, o Quadro 11A e as bases legais não vazias das linhas.

**Dado** que o consolidado fica pendente porque a zona não foi identificada,  
**Quando** a fundamentação é montada,  
**Então** o motivo da pendência entra na lista e o Quadro 10 não é citado como aplicado.

## Divergências da redação anterior
A redação anterior pedia fundamentação genérica em toda ação. O código só inclui a dimensão que chegou a `identificado` e, na pendência, o motivo em vez do quadro não aplicado.

## Evidências
- `LouosEnquadramentoService::buildFundamentacao` e `baseLegal`
- Auditoria do evento `enquadramento`
- Textos `base_legal.louos` e `louos.motivo.*`

## Não evidenciado
- Citação do Quadro 7 ou do Quadro 11 na fundamentação.
- Fundamentação da VISA neste parecer. O classificador de risco municipal tem lista própria e não inclui a dimensão sanitária.
