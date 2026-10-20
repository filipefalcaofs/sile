# HU-112 — Classificar documentos

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Sugerir o tipo do anexo e alertar se a categoria não confere com a exigência, sem rejeitar o protocolo.

## História de Usuário
**Como** analista, **quero** um alerta se o anexo não parece corresponder à exigência, **para** revisar o documento. O alerta não impede o protocolo.

## Atores e permissões
- `ClassificacaoDocumentoService` exige `features.ia_classificacao` e provedor de visão ativo.
- Nenhum controller chama `processar`.

## Fluxo evidenciado
1. Portão fechado: não despacha, não chama o provedor, não simula categoria.
2. Portão aberto: `ClassificacaoDocumentoJob` classifica e grava `ai_suggestions` do tipo `classificacao`.
3. O confronto entre categoria e exigência do documento fica registrado como alerta na sugestão.
4. A ficha só exibe sugestões que já estejam no ledger.

## Regras de negócio evidenciadas
- A classificação não rejeita o protocolo nem remove o anexo.
- Não decide o processo.

## Validações
- Sem provedor de visão a função permanece indisponível.
- O job recebe o documento e a solicitação pelos identificadores, não pelo arquivo na requisição.

## Critérios de aceite
- Alerta de categoria divergente não muda o status da solicitação.
- Com a função desligada, não há sugestão fabricada.
- O upload de documento não dispara esta classificação.

## Divergências da redação anterior
O texto pedia validar compatibilidade com a exigência como passo do fluxo. O código só prepara um alerta, e esse passo não está ligado ao envio do anexo.

## Evidências
- `app/Services/Ai/ClassificacaoDocumentoService.php`
- `app/Jobs/Ai/ClassificacaoDocumentoJob.php`
- `app/Ai/Agents/ClassificacaoDocumentoAgent.php`

## Não evidenciado
- Recusa automática de anexo ou de protocolo por classificação.
- Botão ou listener que chame o serviço no fluxo real.
