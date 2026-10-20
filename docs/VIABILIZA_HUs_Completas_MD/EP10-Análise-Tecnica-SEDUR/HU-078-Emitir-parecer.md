# HU-078 — Emitir parecer

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Registrar o parecer do analista na ficha e usá-lo como texto da decisão quando o processo é indeferido.

## História de Usuário
**Como** analista com `analisar-processos`, **quero** escrever o parecer na ficha, com texto padrão quando houver, **para** que a conclusão grave esse motivo na decisão.

## Atores e permissões
- Abrir, autosave e finalizar a ficha humana: `analisar-processos`. A ficha do expresso abre com `consultar-solicitacoes` ou `analisar-processos` e não cria revisão humana.
- Concluir o processo a partir da ficha, decidir e gravar a justificativa REGIN: as duas permissões, `analisar-processos` e `concluir-analise`.
- No seeder, `concluir-analise` está em Coordenador, Subcoordenador, Chefe de setor e Analista Servidor. Analista Terceirizado analisa e não conclui. O perfil `apoio` não analisa. O administrador, no seeder, não tem nenhuma das duas.

## Fluxo evidenciado
1. A ficha vigente carrega os textos padrão ativos para o parecer e o debounce de autosave (configuração `viabiliza.analise.autosave.debounce_ms`, padrão 1500 ms).
2. O autosave grava `parecer` no rascunho. Ficha finalizada recusa alteração.
3. Na conclusão, o indeferimento usa, nesta ordem, o parecer informado na conclusão, o parecer da ficha ou as justificativas das atividades marcadas como indeferidas. Sem nenhum texto, a decisão é recusada.
4. O deferimento grava o parecer informado só se não estiver em branco; pode ficar nulo.
5. `ViabilityDecision` recusa salvar indeferimento com `reason` vazio.
6. Há sugestão de parecer e de justificativa por IA na ficha. A sugestão não grava a decisão.

## Regras de negócio evidenciadas
- Rascunho não decide. A decisão exige ficha finalizada, ou a conclusão da ficha finaliza o rascunho antes de decidir.
- O parecer do indeferimento humano é o campo `reason` da decisão, visível a quem consulta o parecer.
- O motivo de análise (`analysis_reasons`) não é editável pelo analista: o autosave ignora valor vindo do cliente.

## Validações
- Autosave: `parecer` opcional, texto.
- Conclusão de análise: `parecer` opcional, no máximo 5000 caracteres. A obrigatoriedade do motivo no indeferimento é regra de domínio, não do formulário.
- Justificativa REGIN: a rota exige `concluir-analise`. O limite de 10000 caracteres está declarado no form request.

## Critérios de aceite
1. Analista grava parecer no rascunho e a ficha finalizada deixa de aceitar autosave.
2. Indeferir sem parecer na conclusão, sem parecer na ficha e sem justificativa por atividade recusa a decisão e não cria `ViabilityDecision`.
3. Indeferir com parecer grava `reason` preenchido.
4. Usuário sem `concluir-analise` não chama decidir, conclusão da ficha nem justificativa REGIN, ainda que tenha `analisar-processos`.

## Divergências da redação anterior
- A redação anterior atribuía a emissão a um analista genérico. Deferir e indeferir, que publicam o parecer na decisão, exigem `concluir-analise`.
- Não há documento de parecer separado nesta ação. O PDF de indeferimento é outra rota, sob `emitir-tvl`.

## Evidências
- `app/Http/Controllers/Gestao/AnalysisRecordController.php`
- `app/Services/Analise/AnalysisRecordService.php`
- `app/Services/Analise/AnaliseTecnicaDecisionService.php` (`justificativaDoIndeferimento`, `parecerDoDeferimento`)
- `app/Models/ViabilityDecision.php`
- `app/Http/Requests/Gestao/ConcluirAnaliseRequest.php`
- `routes/gestao.php`
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Publicação do parecer ao REGIN como entrega concluída. O listener existe e o binding indisponível audita bloqueio, sem marcar envio.
