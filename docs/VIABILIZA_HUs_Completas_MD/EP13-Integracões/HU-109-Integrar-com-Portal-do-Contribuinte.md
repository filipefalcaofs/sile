# HU-109 — Integrar com Portal do Contribuinte

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Não há integração com Portal do Contribuinte.

## História de Usuário
**Como** sistema, **quero** compartilhar o cadastro com o Portal do Contribuinte quando o contrato existir. Não há cliente nem rota para isso.

## Atores e permissões
- Não há permissão nem parâmetro dessa integração.

## Fluxo evidenciado
1. Busca no código de aplicação não encontra portal do contribuinte, cliente ou evento de envio.
2. O envio de viabilidade à SEFAZ municipal está bloqueado noutro binding e não é este portal.
3. A consulta de CNPJ usa a base de contato da SEDUR, não o Portal do Contribuinte.

## Regras de negócio evidenciadas
- Não há sincronização de cadastro com esse portal.

## Validações
- Não há payload nem retorno desse destino.

## Critérios de aceite
- Nenhuma rota chama Portal do Contribuinte.
- Desligar ou ligar outras integrações não cria esse envio.

## Divergências da redação anterior
O texto pedia evitar retrabalho cadastral com o portal. Não há implementação correspondente.

## Evidências
- Ausência de controller, serviço e rota de Portal do Contribuinte em `app/` e `routes/`.

## Não evidenciado
- URL, credencial, fila ou confirmação de envio ao Portal do Contribuinte.
