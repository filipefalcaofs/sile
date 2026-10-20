# HU-130 — Consultar SLA e vencimentos

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar processos em andamento com prazo, do mais urgente ao menos urgente, com resumo, aging, atraso por etapa e cumprimento no período.

## História de Usuário
**Como** gestor, **quero** ver o que está vencido ou por vencer, **para** acompanhar o prazo da análise em curso.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/sla`.
- Consulta auditada (`consulta-sla-vencimentos`).
- A lista de analistas do filtro são usuários com `analisar-processos`.

## Fluxo evidenciado
1. Estoque e lista usam setor e analista. Datas do pedido não filtram essa lista: o estoque é o momento atual.
2. O período (`data_de` e `data_ate`) recorta só o cumprimento.
3. O resumo junta em andamento, vencidos, vencendo na janela, aging, atrasados por etapa e cumprimento.
4. A lista pagina e ordena pelo mais urgente.
5. `?formato=` exporta o recorte de estoque (`SlaVencimentosReportSource`), isto é, setor e analista.

## Regras de negócio evidenciadas
- Processo já decidido não é o estoque desta lista.
- Cumprimento e estoque não compartilham o mesmo filtro de data.
- Semáforo e aging saem do prazo materializado, não de um prazo recalculado à parte na tela.

## Validações
- `setor` e `analista` são inteiros opcionais.
- Datas do cumprimento seguem o request de relatório.
- Página: 10, 15, 25 ou 50.

## Critérios de aceite
- A lista mostra processos em andamento com prazo, mais urgentes primeiro.
- Informar período muda o cumprimento e não esvazia o estoque por data de decisão.
- Exportar respeita setor e analista.
- A consulta é auditada.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`slaVencimentos`)
- `app/Services/Relatorios/SlaVencimentosService.php`
- `routes/gestao.php` (`relatorios.sla`)

## Não evidenciado
- Alteração de prazo ou redistribuição a partir desta tela.
