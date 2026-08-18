# HU-140 — Pré-analisar processo pelo motor

> **Status: Aceita (2026-06-12)** — melhoria além do legado. No SAPS, o analista preenche a ficha do zero; no SILE, o motor roda **sempre** e a ficha chega pré-preenchida (human-in-the-loop).

## Épica
**EP10 — Análise Técnica SEDUR**

## Objetivo
Executar o motor de regras (LOUOS + risco + condicionantes + vagas) em todo processo encaminhado à análise humana, entregando a ficha de análise pré-preenchida com o resultado sugerido e o motivo exato da queda.

## História de Usuário
**Como** analista SEDUR,  
**quero** receber a ficha pré-analisada pelo motor,  
**para** revisar e ajustar em minutos, em vez de preencher tudo manualmente.

## Contexto de Negócio
A análise humana no legado consome ~30 minutos por processo porque o analista refaz manualmente o que o motor já sabe calcular (enquadramento, condicionantes, vagas). Com a pré-análise, o trabalho humano vira revisão: confirmar ou divergir do motor — e a divergência registrada alimenta a melhoria da parametrização.

## Pré-condições
- Processo encaminhado à análise técnica (HU-079) com motivo registrado (gatilho, alto risco ou dado pendente).
- Motores das Fases 5 e 6 operacionais.

## Fluxo Principal
1. Ao encaminhar processo à análise, o sistema executa o motor completo com os dados disponíveis.
2. A ficha de análise (HU-135) abre pré-preenchida: enquadramento LOUOS sugerido por CNAE, status sugerido (deferida/indeferida/análise), condicionantes aplicáveis pré-marcadas, vagas calculadas com veredito, gatilhos exibidos com destaque.
3. Campos sem dado confiável (motivo da queda) ficam destacados como pendência de análise humana.
4. O analista confirma ou ajusta cada sugestão; ajustes registram divergência (valor sugerido × valor final + justificativa).
5. O sistema registra a pré-análise (versão das regras, resultados sugeridos) e as divergências em auditoria.

## Fluxos Alternativos
### FA-01 — Motor indisponível ou regra ausente
1. A ficha abre vazia no modo manual (comportamento do legado), com aviso explícito do motivo.
2. O evento é registrado para acompanhamento.

## Regras de Negócio
- RN-001: A pré-análise nunca decide sozinha no fluxo humano — é sugestão; a decisão é do analista.
- RN-002: Toda divergência analista × motor deve registrar campo, valor sugerido, valor final e justificativa.
- RN-003: Divergências recorrentes devem ser consultáveis (relatório) para orientar correção de parametrização (HU-145).
- RN-004: A pré-análise registra a versão das regras utilizada; reabrir a ficha não reexecuta o motor automaticamente (consistência), salvo ação explícita "Recalcular".
- RN-005: Toda execução registrada em auditoria com dados de entrada, regras aplicadas e resultados sugeridos.

## Critérios de Aceite — BDD

### CA-01 — Ficha pré-preenchida
**Dado** um processo encaminhado à análise com dados completos,  
**Quando** o analista abrir a ficha,  
**Então** enquadramento, condicionantes, vagas e status sugerido por CNAE devem estar pré-preenchidos com a fundamentação do motor.

### CA-02 — Registro de divergência
**Dado** uma sugestão do motor,  
**Quando** o analista alterar o valor,  
**Então** a divergência (sugerido × final + justificativa) deve ser registrada e consultável.

### CA-03 — Degradação controlada
**Dado** que o motor está indisponível,  
**Quando** o analista abrir a ficha,  
**Então** o modo manual deve ser oferecido com aviso explícito — nunca falha silenciosa.

## Dependências
- HU-038 a HU-046 (motor LOUOS), HU-047 a HU-051 (risco), HU-042 (vagas), HU-135 (ficha), HU-079.

## Prioridade
Alta

## Observações
Maior alavanca de produtividade do projeto: transforma análise de ~30 min em revisão de ~5 min. As divergências viram insumo do feedback loop de parametrização (HU-145).
