# HU-085 — Forçar encaminhamento para análise

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Colocar um processo na fila da análise por envio manual, em qualquer status canônico, e permitir que a chefia force uma transição do eixo operacional fora do menu comum.

## História de Usuário
**Como** usuário com `enviar-tvl-analise`, **quero** pesquisar um protocolo e enviá-lo à análise, **para** iniciar o eixo operacional sem depender do expresso. **Como** chefia com `analisar-processos` e `forcar-transicao-analise`, **quero** forçar a situação da análise, **para** aplicar uma transição que o menu manual não oferece.

## Atores e permissões
- Tela e ações de enviar para análise (`GET` e `POST` em `processos/enviar-para-analise`): somente `enviar-tvl-analise`. No seeder, Analista Servidor, Analista Terceirizado, chefias e administrador têm essa permissão. O apoio não tem.
- Mudança manual de situação: rota sob `analisar-processos`. O override só vale se o corpo pede `force` e o usuário tem `forcar-transicao-analise`. No seeder, essa permissão está nas chefias (Coordenador, Subcoordenador, Chefe de setor). Analista, apoio e administrador não a têm no seeder.
- As duas ações são independentes. Enviar para análise não consulta `forcar-transicao-analise`.

## Fluxo evidenciado
1. A pesquisa localiza o protocolo com match exato, depois do trim. Vazio ou inexistente responde 404 na pesquisa, com a mensagem de config `viabiliza.analise.enviar_analise.mensagem_nao_encontrado`. Não muda estado.
2. O preview traz protocolo, serviço, status, requerente, se já há situação de análise e o rótulo dessa situação.
3. Enviar com `analysis_status` já preenchido não duplica: devolve aviso de que já está em análise e não audita novo envio.
4. Enviar efetivo transiciona o eixo de nulo para `para_distribuir`, grava etapa de distribuição e prazo, audita `enviar-analise` e, depois do commit, dispara `EncaminhadoParaAnalise` (pré-análise, HU-059). O status canônico (`ViabilityRequestStatus`) não é alterado. Vale para qualquer status, sede ou abrigado.
5. A transição manual sem `force` só aceita o que `AnalysisStatus::proximas()` lista. Estado dirigido por evento (convite respondido ou expirado) não entra nesse menu. Fora da lista, a tela recebe erro e a máquina não roda.
6. Com `force` e a permissão, o controller não aplica `proximas()` e a máquina grava a transição mesmo fora do grafo, com `forcado` verdadeiro na auditoria. O campo `motivo` é opcional (até 2000 caracteres). O comentário da máquina fala em justificativa obrigatória no caller; o form request não torna `motivo` obrigatório.
7. Situação `encaminhado` exige setor e usuário pertencente a esse setor, e grava setor, responsável e data. Situação `para_conclusao` pode gravar o destinatário, que precisa ter `concluir-analise`.

## Regras de negócio evidenciadas
- Envio manual é idempotente pelo eixo operacional já iniciado, não pelo status canônico.
- Forçar não dispensa `analisar-processos`: a rota continua nesse middleware. A permissão extra só libera o override.
- Transição recusada não é silenciosa: volta `flash` de erro.

## Validações
- Protocolo em branco é tratado como não encontrado.
- `AnalysisStatusRequest`: `status` obrigatório no enum; `force` booleano opcional; setor e usuário obrigatórios quando o status é `encaminhado`; destinatário da conclusão, se informado, precisa ter `concluir-analise`.
- Usuário de encaminhamento que não pertence ao setor informado: erro, sem transição.

## Critérios de aceite
1. Sem `enviar-tvl-analise`, a tela de envio responde 403.
2. Protocolo inexistente não altera processo e informa que não foi encontrado.
3. Primeiro envio põe `para_distribuir`, prazo de distribuição e auditoria, sem mudar o status canônico, e dispara a pré-análise depois do commit.
4. Segundo envio do mesmo processo não cria outra tramitação.
5. Sem `forcar-transicao-analise`, `force` não ignora o menu `proximas()`.
6. Chefia com as duas permissões e `force` grava transição fora do menu e a auditoria marca `forcado`.

## Divergências da redação anterior
- Não havia redação anterior. O encaminhamento automático do expresso, que muda o status canônico para `em_analise`, é a HU-069, não esta tela.

## Evidências
- `app/Http/Controllers/Gestao/EnviarParaAnaliseController.php`
- `app/Services/Analise/EnviarParaAnaliseService.php`
- `app/Http/Controllers/Gestao/ProcessoController.php` (`atualizarStatusAnalise`)
- `app/Http/Requests/Gestao/AnalysisStatusRequest.php`
- `app/Services/Analise/AnalysisStatusStateMachine.php`
- `app/Enums/AnalysisStatus.php` (`proximas`)
- `routes/gestao.php`
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Motivo obrigatório no override. O request aceita `motivo` nulo.
- Uso de `forcar-transicao-analise` para pular a idempotência do envio manual. São endpoints diferentes.
