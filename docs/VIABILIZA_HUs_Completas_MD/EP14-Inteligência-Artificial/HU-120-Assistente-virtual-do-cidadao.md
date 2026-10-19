# HU-120 — Assistente virtual do cidadão

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Não há conversa do cidadão com um assistente. Existe interruptor e menção de infraestrutura, sem fluxo.

## História de Usuário
**Como** cidadão, **quero** tirar dúvidas no portal com um assistente. Não há tela, rota nem serviço de conversa para isso.

## Atores e permissões
- O parâmetro `features.ia_assistente` existe no seed e nasce desligado. A descrição fala em assistentes do cidadão e do analista.
- Não há controller, página ou modelo de conversa usado pela aplicação.
- A migration `agent_conversations` não tem model nem consulta em `app/`.

## Fluxo evidenciado
1. Nenhuma rota do portal chama um agente conversacional.
2. `AiFeatureGate::embeddingsAvailable` documenta a busca vetorial como infraestrutura dos assistentes e não é um chat.
3. O resumo da solicitação e a explicação da decisão são cards de sugestão, não um assistente de perguntas.

## Regras de negócio evidenciadas
- Interruptor sem função ligada não produz resposta.
- Não há degradação comunicada numa tela de chat porque a tela não existe.

## Validações
- Não há validação de mensagem de cidadão para assistente.

## Critérios de aceite
- Nenhuma página do portal oferece conversa com assistente.
- Ligar o parâmetro `features.ia_assistente`, por si, não cria endpoint de diálogo.

## Divergências da redação anterior
O texto pedia orientar o cidadão para reduzir erro de preenchimento. Isso não está implementado como assistente.

## Evidências
- `database/seeders/ParameterSeeder.php` (`features.ia_assistente`)
- `database/migrations/2026_06_18_190510_create_agent_conversations_table.php`
- Ausência de controller ou página de assistente em `app/` e `resources/js/`

## Não evidenciado
- Envio de pergunta, histórico de conversa e resposta ao cidadão.
