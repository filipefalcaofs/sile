# HU-086 — Deferir solicitação

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Concluir como deferido o processo em análise, a partir da ficha finalizada, gerando número de TVL quando não for renovação.

## História de Usuário
**Como** usuário com `analisar-processos` e `concluir-analise`, **quero** deferir o processo, **para** encerrar a análise como deferida e registrar a decisão humana.

## Atores e permissões
- `POST processos/{processo}/decidir`, `POST processos/{processo}/conclusao-analise` e `POST processos/{processo}/ficha/concluir-processo` exigem as duas permissões.
- No seeder, quem tem `concluir-analise`: Coordenador, Subcoordenador, Chefe de setor e Analista Servidor. Analista Terceirizado tem `analisar-processos` e não defere. Apoio não analisa. Administrador, no seeder, não tem `concluir-analise` nem `analisar-processos`.
- A decisão grava `decided_by_user_id` com o usuário autenticado e `flow` `analise_tecnica`.

## Fluxo evidenciado
1. A ficha precisa estar finalizada. O endpoint da ficha finaliza o rascunho antes de decidir. Rascunho enviado ao decidir direto é recusado.
2. O processo precisa estar `em_analise`. A ficha precisa ter ao menos um CNAE.
3. Deferir com alguma atividade marcada como indeferida na ficha é recusado, e a mensagem lista essas atividades. Atividade sem marcação não impede o deferimento: o desfecho derivado é deferido quando nenhuma atividade está marcada como indeferida.
4. Renovação deferida exige tipo de TVL (provisória ou definitiva) e nova data de validade. Se `condicionantes_atendidos` vier falso, o resultado vira indeferido.
5. Fora de renovação, o deferimento gera `tvl_product_number`. O status canônico vai para `deferida`. A auditoria `decisao` ocorre na mesma transação.
6. Depois do commit dispara `ResultadoEmitido`. Uma segunda decisão no mesmo processo esbarra na unicidade e devolve a decisão já gravada, sem disparar o evento de novo.
7. Sede de escritório virtual confirmada na ficha, com o CNAE gatilho, grava condicionante, trava a inscrição e marca o processo como escritório virtual.

## Regras de negócio evidenciadas
- Deferido e indeferido são os únicos resultados lidos da ficha neste serviço. Desativado, cassado e revogado passam por `ConclusaoAnaliseService` (HU-089).
- O humano pode deferir caso que o motor deixou pendente: a decisão nasce da ficha, não de um veredito consolidado.
- Com condicionantes na ficha, o consolidado do deferimento é `permitido_com_condicoes`; sem elas, `permitido`.
- Processo já deferido não é indeferido por esta conclusão: a mensagem manda usar desativado, cassado ou revogado.

## Validações
- `decisao`, quando informada, só aceita deferida ou indeferida.
- Renovação sem tipo ou sem validade no deferimento lança erro de domínio, traduzido para a tela.
- Sem ficha vigente, a decisão responde que a ficha ainda não foi criada.

## Critérios de aceite
1. Usuário sem `concluir-analise` recebe 403 em decidir e em concluir pela ficha.
2. Ficha finalizada, processo em análise, nenhuma atividade indeferida: status `deferida`, decisão com fluxo `analise_tecnica`, autor preenchido e número de TVL quando não é renovação.
3. Atividade indeferida na ficha impede o deferimento e não grava decisão.
4. Segunda conclusão não cria outra decisão.

## Divergências da redação anterior
- A redação anterior dizia que o analista defere e que REGIN e SEFAZ são disparados e concluídos com o deferimento. Quem defere é quem tem `concluir-analise`. O evento `ResultadoEmitido` dispara, mas o binding do REGIN e o da SEFAZ indisponíveis auditam bloqueio e não registram transmissão. O indeferimento não é enviado à SEFAZ.
- A redação dizia que o deferimento exige todas as atividades deferidas. O código defere quando nenhuma atividade está marcada como indeferida; atividade sem marcação não bloqueia.

## Evidências
- `app/Http/Controllers/Gestao/ProcessoDecisaoController.php`
- `app/Http/Controllers/Gestao/ConclusaoAnaliseController.php`
- `app/Http/Controllers/Gestao/AnalysisRecordController.php` (`concluirProcesso`)
- `app/Services/Analise/AnaliseTecnicaDecisionService.php`
- `app/Services/Analise/ConclusaoAnaliseService.php`
- `routes/gestao.php`
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Transmissão concluída ao REGIN ou à SEFAZ a partir deste deferimento.
- Entrega do PDF ao requerente. A emissão no backoffice é a HU-132.
