# HU-114 — Identificar inconsistências

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Sinalizar divergência entre o que foi declarado e o que a visão lê no documento, como sugestão, sem punir o processo.

## História de Usuário
**Como** analista, **quero** ver alertas de inconsistência entre declaração e documento, **para** conferir o processo. O alerta não decide nem encaminha punição sozinho.

## Atores e permissões
- `InconsistenciasService` exige `features.ia_inconsistencias` e provedor de visão.
- A ficha de análise lê o ledger `ai_suggestions` no card de alertas. Essa leitura não dispara o serviço de inconsistências.
- Nenhum controller chama `processar`.

## Fluxo evidenciado
1. Portão fechado: não despacha e não inventa divergência.
2. Portão aberto: `InconsistenciasJob` usa a primeira imagem, preferindo a exigência de foto de fachada. Sem imagem, não há material inventado.
3. A saída prevista no card traz campo, valor declarado, valor no documento, severidade e fonte.
4. O serviço não chama malha fina nem muda status. O comentário do código cita a malha fina como destino possível do sinal; o método `processar` só enfileira o job.
5. Checagens determinísticas de área, polígono e Receita não são feitas aqui. Receita Federal não está integrada.

## Regras de negócio evidenciadas
- Sugestão revisável. Não é decisão.
- Ausência de foto não gera inconsistência fictícia.

## Validações
- Capacidade visão. Sem provedor, motivo de indisponibilidade no portão.
- Processo sem imagem não recebe anexo fabricado.

## Critérios de aceite
- Abrir a ficha não dispara detecção de inconsistência; só mostra sugestão já gravada.
- Com o portão fechado, não há alerta simulado.
- Uma sugestão deste tipo, se existir no ledger, não transiciona o processo.

## Divergências da redação anterior
O texto pedia apontar divergências no fluxo do cidadão e do analista. Há job e leitura do ledger; o disparo da detecção não está ligado à ficha nem ao protocolo.

## Evidências
- `app/Services/Ai/InconsistenciasService.php`
- `app/Jobs/Ai/InconsistenciasJob.php`
- `app/Ai/Agents/InconsistenciasAgent.php`
- `app/Http/Controllers/Gestao/AnalysisRecordController.php` (`sugestoesIa`)

## Não evidenciado
- Chamada de `InconsistenciasService::processar` fora dos testes.
- Punição automática ou malha fina aberta por este serviço.
- Confrontação com a Receita Federal.
