# HU-071 — Distribuir processo

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Atribuir processos em análise da caixa do setor a um analista, em um ou em lote, recalculando o SLA na distribuição e sem recalcular na redistribuição.

## História de Usuário
**Como** gestor, chefia ou apoio com permissão de tramitar, **quero** distribuir e redistribuir processos da caixa do setor, **para** definir o responsável sem tirar o processo da fila de trabalho do setor, salvo quando o apoio entrega para outro setor.

## Atores e permissões
- Ver a caixa: `analisar-processos` ou `distribuir-processos`.
- Distribuir, redistribuir, central e distribuição inteligente: somente `distribuir-processos`.
- No seeder, `distribuir-processos` está nas chefias (Coordenador, Subcoordenador, Chefe de setor), no perfil `apoio` e no `administrador`. Analista Servidor e Analista Terceirizado não distribuem.
- `distribuir-qualquer-setor` (apoio e administrador) enxerga todos os setores e pode entregar a analista de outro setor; o processo passa para o setor de destino. Sem essa permissão, o analista precisa pertencer ao setor do processo e o processo permanece nesse setor.
- Destinatário da análise: usuário com `analisar-processos`. Linha em vistoria: destinatário com `preencher-ficha-vistoria`.
- Quem só tem `distribuir-processos` não assume (a assunção exige `analisar-processos`).

## Fluxo evidenciado
1. A caixa lista processos com status `em_analise` dos setores do usuário, ou de todos os setores se ele distribui em qualquer setor.
2. Abas: para distribuir (sem responsável e fora de `para_conclusao`), distribuídos e para conclusão.
3. Distribuição simples ou em lote chama `DistribuicaoService`, grava responsável, data, setor de destino, recalcula o SLA da etapa e audita `distribuir` por processo. Falha de um item do lote não cancela os demais.
4. Redistribuição só ocorre se `analysis_status` permite (`para_distribuir`, `encaminhado`, `analisar`, `em_analise`). Troca o responsável e o setor quando couber, audita `redistribuir` com o analista anterior e não recalcula o prazo.
5. A consulta da caixa é auditada.

## Regras de negócio evidenciadas
- Processo fora da caixa ou analista sem vínculo com o setor de destino não é gravado (`DistribuicaoException`).
- Redistribuição depois da conclusão, em convite ou em vistoria é recusada.
- O prazo é do processo: redistribuir não zera atraso.
- A caixa não remove o processo do trabalho do setor, exceto a mudança de setor feita por quem distribui em qualquer setor.

## Validações
- `DistribuirProcessoRequest` e `DistribuirEmLoteRequest` validam a estrutura; a rota já exige `distribuir-processos`.
- O vínculo do analista com o setor é regra do serviço, não só do formulário.

## Critérios de aceite
1. Sem `distribuir-processos`, a caixa pode ser vista por quem analisa, mas distribuir e redistribuir respondem 403.
2. Distribuição grava responsável, recalcula SLA e audita cada processo.
3. Lote isola falha por item.
4. Redistribuição recusada quando o status operacional não permite, e não altera `analysis_due_at` quando permitida.
5. Apoio com `distribuir-qualquer-setor` entrega fora do próprio setor e move o processo para o setor do analista.

## Divergências da redação anterior
- A redação anterior dizia que qualquer analista do setor mantém visibilidade e pode assumir, misturando distribuição e assunção. No código, assumir é outra permissão e outra ação (HU-072).
- Não é verdade que o processo nunca sai do setor: quem tem `distribuir-qualquer-setor` muda o `sector_id`.

## Evidências
- `app/Http/Controllers/Gestao/CaixaSetorController.php`
- `app/Services/Analise/DistribuicaoService.php`
- `routes/gestao.php` (grupo `caixa-setor`)
- `database/seeders/RolesAndPermissionsSeeder.php`
- `app/Enums/AnalysisStatus.php` (`permiteRedistribuicao`)

## Não evidenciado
- Distribuição automática por carga como decisão do sistema. A tela de distribuição inteligente existe na rota, mas a escolha de quem recebe continua sendo executada pelo serviço a partir da alocação recebida.
