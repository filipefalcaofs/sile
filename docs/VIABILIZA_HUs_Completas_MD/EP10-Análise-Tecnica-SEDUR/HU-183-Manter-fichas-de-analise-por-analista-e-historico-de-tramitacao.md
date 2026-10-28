# HU-183 — Manter fichas de análise por analista e histórico de tramitação

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Proposta, para validação da SEDUR.
- **Criação:** 23/10/2026. O texto completo está em `docs/requisitos/hus/HU.183 - Manter fichas de analise por analista e historico de tramitacao.md`.

## Objetivo
Fazer a ficha de análise pertencer ao analista que a preencheu, manter o histórico de todas as fichas na tramitação e separar a análise da decisão formal do processo.

## História de Usuário
**Como** analista,
**quero** abrir uma ficha só minha, partindo da minha última ficha do processo, e consultar as fichas dos outros analistas na tramitação,
**para que** eu não herde dados que não são meus e a gestão saiba quem fez cada análise e cada decisão.

## Regras de negócio
- Cada analista pode ter várias fichas no mesmo processo.
- A ficha nova parte da última ficha do próprio analista; sem ficha anterior dele, abre em branco.
- Abrir ficha nova não apaga nem altera ficha anterior, nem decisão já registrada.
- A última ficha criada não é o parecer conclusivo; o resultado formal é emitido por quem pode concluir a análise.
- Analista Terceirizado analisa e encaminha, mas não finaliza o processo.
- Processo já decidido só volta à análise por regra explícita de reabertura, sem apagar a decisão.

## Critérios de aceite
### CA-01 — Mesmo analista, nova ficha
**Dado** o analista tem ficha anterior no processo,
**Quando** abre uma nova ficha,
**Então** ela vem com os dados da última ficha dele e a anterior não muda.

### CA-02 — Outro analista
**Dado** outro analista já preencheu ficha no processo,
**Quando** o usuário abre a primeira ficha dele,
**Então** ela abre em branco e as duas ficam na tramitação.

### CA-03 — Terceirizado
**Dado** o usuário é Analista Terceirizado,
**Quando** tenta finalizar o processo,
**Então** a finalização é recusada.
