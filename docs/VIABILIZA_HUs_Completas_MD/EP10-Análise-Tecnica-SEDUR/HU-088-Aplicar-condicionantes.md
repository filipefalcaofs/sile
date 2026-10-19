# HU-088 — Aplicar condicionantes

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Registrar condicionantes na ficha e refleti-las no consolidado do deferimento, inclusive a condicionante de sede de escritório virtual.

## História de Usuário
**Como** analista com `analisar-processos`, **quero** lançar condicionantes na ficha, **para** que o deferimento saia como permitido com condições quando houver condicionante gravada.

## Atores e permissões
- Gravar condicionantes no rascunho: `analisar-processos` (autosave da ficha).
- Publicá-las na decisão, no deferimento: também `concluir-analise`.
- Renovação informa `condicionantes_atendidos` no endpoint de decidir, sob as mesmas duas permissões.

## Fluxo evidenciado
1. O autosave aceita `conditions` (lista) e condicionantes dentro de cada item de `per_cnae`, só em ficha não finalizada.
2. No deferimento, se a ficha tem `conditions` ou algum item com `condicionantes`, o consolidado da decisão é `permitido_com_condicoes`. Sem isso, `permitido`.
3. Deferimento de sede de escritório virtual (ficha marcada, CNAE gatilho presente) força `permitido_com_condicoes` e acrescenta o texto configurado em `viabiliza.analise.escritorio_virtual.condicionante_sede`, trava a inscrição e marca `is_virtual_office`.
4. Na renovação, `condicionantes_atendidos` falso troca o resultado para indeferido antes de gravar, e o indeferimento continua exigindo justificativa.

## Regras de negócio evidenciadas
- Condicionante não é uma decisão à parte. Ela altera o consolidado do deferimento e o texto de fundamentação da sede.
- Indeferimento não usa o consolidado com condições: o consolidado indeferido é `nao_permitido`.
- Desativado, cassado, revogado e indeferimento de sede ativa desvinculam a inscrição pelo serviço de desvinculação. Deferimento não desvincula.

## Validações
- Autosave de ficha finalizada é recusado.
- `condicionantes_atendidos` é booleano opcional no decidir.

## Critérios de aceite
1. Ficha com condicionante, deferida por quem pode concluir, grava consolidado `permitido_com_condicoes`.
2. Ficha sem condicionante, deferida, grava `permitido`, salvo o caso de sede de escritório virtual com CNAE gatilho.
3. Renovação com condicionantes não atendidos não defere.
4. Usuário sem `analisar-processos` não grava a ficha.

## Divergências da redação anterior
- A redação anterior tratava aplicar condicionantes como funcionalidade genérica, com atores e integrações indefinidos. No código, a condicionante é campo da ficha e efeito do deferimento, não uma transição própria de status.

## Evidências
- `app/Services/Analise/AnalysisRecordService.php` (`autosave`)
- `app/Services/Analise/AnaliseTecnicaDecisionService.php` (`temCondicionantes`, `consolidatedResult`, sede)
- `app/Http/Controllers/Gestao/ProcessoDecisaoController.php`
- `app/Services/Analise/ConclusaoAnaliseService.php` (`desvincularSedeSeAtiva`)

## Não evidenciado
- Cadastro avulso de condicionante depois da decisão, sem nova revisão da ficha.
- Checklist de cumprimento de condicionante fora da renovação (`condicionantes_atendidos`).
