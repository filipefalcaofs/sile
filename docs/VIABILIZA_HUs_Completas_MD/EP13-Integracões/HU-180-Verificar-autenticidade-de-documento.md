# HU-180 — Verificar autenticidade de documento

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir que qualquer pessoa confira, pelo código impresso, se uma TVL, um indeferimento ou um comprovante de protocolo foi emitido pelo sistema.

## História de Usuário
**Como** portador do documento, **quero** informar o código de verificação, **para** saber se ele confere com um documento emitido, sem ver dados da empresa.

## Atores e permissões
- Rota pública `GET /verificar-documento/{codigo}`, sem login.
- Throttle `consulta-protocolo`.
- Cada consulta é auditada em `documentos` / `verificacao-documento-publica`, com causer nulo, código, validade e tipo.

## Fluxo evidenciado
1. `VerificacaoDocumentoService::verificar` procura o código.
2. Código desconhecido ou adulterado devolve `valido` falso, sem tipo, protocolo nem data.
3. Código de TVL devolve tipo “Viabilidade locacional”, protocolo e data de referência.
4. Código de indeferimento devolve tipo “Documento de Indeferimento”.
5. Código de comprovante devolve tipo “Comprovante de Protocolo”.
6. A página `verificacao-documento` recebe só código e esse resultado. Não vão empresa, CNPJ nem endereço.

## Regras de negócio evidenciadas
- Documento não encontrado não é apresentado como válido.
- O payload público é mínimo, no mesmo espírito da consulta pública de protocolo.

## Validações
- O código vai na URL. Não há formulário autenticado nesta rota.
- O limite de taxa é o da consulta pública de protocolo.

## Critérios de aceite
- Código existente de TVL, indeferimento ou comprovante mostra tipo, protocolo e data, e `valido` verdadeiro.
- Código inexistente mostra inválido e não revela se o número “quase” existe por meio de dado cadastral.
- A consulta entra na trilha mesmo sem usuário logado.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/VerificacaoDocumentoController.php`
- `app/Services/Documentos/VerificacaoDocumentoService.php`
- `routes/web.php` (`verificar-documento`)

## Não evidenciado
- Verificação que exija login ou que devolva o PDF completo.
- Confirmação de documento que não seja TVL, indeferimento ou comprovante de protocolo.
