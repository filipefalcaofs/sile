# HU-113 — Detectar documentos ilegíveis

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Marcar, na mesma leitura por visão do OCR, quando o anexo está ilegível, e exigir revisão humana dessa sugestão.

## História de Usuário
**Como** analista, **quero** ser avisado de que o documento não pôde ser lido, **para** não tratar uma transcrição falha como conteúdo do processo.

## Atores e permissões
- Não há serviço separado. A detecção está em `LeituraDocumentoJob` e `LeituraDocumentoAgent`, no mesmo portão `features.ia_ocr` e provedor de visão.
- Não há chamada de produção a esse job.

## Fluxo evidenciado
1. O agente devolve estrutura com motivo de ilegibilidade.
2. O job força escalonamento para revisão humana quando o documento é ilegível (`shouldEscalate`). A sugestão não vira verdade do processo.
3. O tipo de sugestão gravado por esse job é `ocr`. O enum também tem `ilegibilidade`, usado como rótulo de função; este job não grava esse tipo à parte.
4. Documento ilegível não rejeita o protocolo por este código.

## Regras de negócio evidenciadas
- Ilegibilidade escala a sugestão para análise humana. Não indefere e não devolve o anexo sozinha.
- Sem o portão do OCR, não há detecção simulada.

## Validações
- Depende do mesmo anexo e do mesmo portão da leitura documental.

## Critérios de aceite
- Resposta do agente marcada como ilegível não altera a decisão do processo.
- Com OCR desligado, não surge alerta de ilegibilidade.
- Nenhum fluxo de anexo chama esta detecção hoje.

## Divergências da redação anterior
O texto tratava a detecção como função autônoma do fluxo. Ela está acoplada ao job de OCR, e esse job não é disparado pela aplicação.

## Evidências
- `app/Jobs/Ai/LeituraDocumentoJob.php`
- `app/Ai/Agents/LeituraDocumentoAgent.php`
- `app/Enums/AiSuggestionType.php`
- `app/Enums/AiSuggestionStatus.php`

## Não evidenciado
- Pendência automática ao requerente por documento ilegível.
- Tipo de sugestão `ilegibilidade` gravado por um job próprio.
