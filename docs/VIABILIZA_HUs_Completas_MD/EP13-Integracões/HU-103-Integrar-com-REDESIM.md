# HU-103 — Integrar com REDESIM

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Receber o envelope da JUCEB/REGIN, persistir o protocolo e abrir o processo municipal a partir dessa declaração.

## História de Usuário
**Como** sistema, **quero** acusar o recebimento do REGIN só depois de gravar o envelope, **para** a Junta não tratar como entregue um protocolo que não ficou armazenado.

## Atores e permissões
- A URL `/api_integracao/recebe` é pública de propósito: a JUCEB chama sem sessão Laravel.
- Throttle `regin-recebe`. Middleware `LogInboundIntegration` registra a entrada.
- Documentação em `/api_integracao/docs` fica fora desse throttle.

## Fluxo evidenciado
1. GET devolve o texto `3` (ping do guia).
2. POST sem protocolo, ou declaração reconhecida sem os dados mínimos para protocolar, responde `0` com HTTP 400.
3. Protocolo novo grava `ReginRecebimento` (inclusive CNPJ, função, NIRE, serviço e corpo) e responde `3`. Duplicidade, inclusive corrida no índice único, responde `5`.
4. A auditoria do recebimento não pode desfazer o ack: se a trilha falhar, a JUCEB ainda recebe 3 ou 5.
5. A abertura do processo vai para `ProtocolarRecebimentoReginJob`. Se a fila falhar, o recebimento permanece e a pendência é registrada; a JUCEB não recebe HTTP 500 por isso.
6. O protocolador lê as perguntas que vieram no envelope e casa respostas com a planilha de tratamento. Não há formulário complementar hospedado à parte.
7. A devolução do parecer é outra chamada HTTP (HU de Junta). O vínculo de protocolo BAP não é feito: o registro BAP ligado na aplicação devolve nulo.

## Regras de negócio evidenciadas
- Sem persistência não há código 3.
- O ack é do envelope, não da consulta ao Cadastro Imobiliário nem ao GeoServer, que ocorrem na abertura.
- Log do recebimento guarda protocolo, função, serviço, código e duração. Não grava o JSON nem o CNPJ nesse log de aplicação.

## Validações
- Protocolo em branco é rejeitado.
- Declaração reconhecida com campos faltantes para protocolar é rejeitada com a lista do que falta.

## Critérios de aceite
- Envelope novo e completo responde `3` e fica gravado antes do ack.
- Reenvio do mesmo protocolo responde `5` e não abre outro recebimento.
- Envelope inválido responde `0` em 400.
- Falha ao enfileirar a abertura não apaga o recebimento nem finge que o processo foi aberto.

## Divergências da redação anterior
O texto pedia formulário complementar hospedado pela SEDUR e vínculo de BAP no mesmo fluxo. O código recebe o envelope, extrai as perguntas que já vieram e não vincula BAP.

## Evidências
- `app/Http/Controllers/Regin/ReginRecebeController.php`
- `app/Services/Regin/ReginRecebeService.php`
- `app/Services/Regin/ReginProcessoProtocolador.php`
- `routes/web.php` (`regin.recebe.ping`, `regin.recebe.store`)

## Não evidenciado
- Vínculo do protocolo BAP (o binding atual não consulta base da Junta).
- Tela da SEDUR que hospede o questionário complementar fora do envelope.
