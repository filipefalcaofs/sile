# HU-112 — Realizar OCR documental

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Extrair texto de um anexo por provedor de visão e gravar o resultado como sugestão, sem transformar a leitura em fato do processo.

## História de Usuário
**Como** analista, **quero** uma transcrição do documento anexado, **para** apoiar a leitura. A transcrição não decide viabilidade nem substitui o arquivo.

## Atores e permissões
- `LeituraDocumentoService` só despacha se `features.ia_ocr` não foi desligado e existe `AiConfiguration` ativa de capacidade `vision`.
- Não há controller, listener nem tela que chame `processar`. O anexo, ao ser enviado, não dispara esta função.

## Fluxo evidenciado
1. `processar` devolve falso e não chama o provedor quando o portão está fechado. Não grava texto simulado.
2. Com o portão aberto, despacha `LeituraDocumentoJob`, que relê o portão no processamento.
3. O agente `LeituraDocumentoAgent` transcreve imagem ou PDF. A saída vai para `ai_suggestions` do tipo `ocr`.
4. O job declara que a leitura nunca vira verdade. Ilegibilidade no mesmo job força revisão humana (HU de documentos ilegíveis).
5. A ficha de análise, se houver sugestão já gravada, apenas lista o ledger. Não cria a leitura.

## Regras de negócio evidenciadas
- Sugestão revisável. Não altera `AnalysisRecord` nem `ViabilityDecision`.
- Sem provedor de visão ou com a função desligada, o retorno é indisponibilidade, não um OCR vazio apresentado como lido.
- O job marca dado pessoal.

## Validações
- O documento precisa existir para o job montar o anexo a partir do disco e do caminho gravados.
- Capacidade exigida é visão, não texto.

## Critérios de aceite
- Com o portão fechado, `processar` não despacha job e não cria sugestão.
- Com o portão aberto, a sugestão gravada é do tipo leitura e não muda o status do processo.
- Enviar um anexo pelo portal não chama este serviço.

## Divergências da redação anterior
O texto tratava o OCR como etapa do fluxo documental. O agente e o job existem; o protocolo e a ficha não os acionam.

## Evidências
- `app/Services/Ai/LeituraDocumentoService.php`
- `app/Jobs/Ai/LeituraDocumentoJob.php`
- `app/Ai/Agents/LeituraDocumentoAgent.php`
- `app/Services/Ai/AiFeatureGate.php`

## Não evidenciado
- Disparo automático no upload ou botão da ficha que chame `LeituraDocumentoService`.
- Uso do texto extraído como validação que aprove ou recuse o documento.
