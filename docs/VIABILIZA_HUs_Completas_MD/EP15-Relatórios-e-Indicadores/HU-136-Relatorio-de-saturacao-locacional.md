# HU-136 — Relatório de saturação locacional

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 17/10/2026, com o código-fonte como referência.

## Objetivo
Comparar estabelecimentos deferidos por bairro e CNAE com a capacidade recomendada e classificar o par.

## História de Usuário
**Como** gestor, **quero** ver quais pares bairro e CNAE estão acima da capacidade cadastrada, **para** ler concentração, e ver explícito o CNAE sem capacidade em vez de um percentual inventado.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/saturacao`.
- Consulta auditada (`consulta-saturacao`).

## Fluxo evidenciado
1. `SaturacaoService` agrupa o bairro oficial (`bairro_oficial`, com o digitado `address_neighborhood` de reserva) e o CNAE dos deferidos do recorte.
2. A capacidade vem do parâmetro `relatorios.saturacao.capacidades`. Valor não numérico ou não positivo é ignorado.
3. Sem capacidade, a situação é `sem_capacidade`, percentual nulo.
4. Com capacidade, o percentual frente aos limiares `relatorios.saturacao.alerta_percentual` (padrão 80) e `relatorios.saturacao.bloqueio_percentual` (padrão 100) classifica `ok`, `saturando` ou `saturado`.
5. O resumo conta pares avaliados, saturados, saturando e sem capacidade.
6. O recorte territorial desta rota é o bairro oficial, com o digitado de reserva. O filtro de bairro usa a mesma regra.
7. `?formato=` exporta solicitações filtradas (`SolicitacoesReportSource`), não a matriz de saturação.

## Regras de negócio evidenciadas
- Sem capacidade não há percentual fabricado.
- O relatório não indefere nem bloqueia novo protocolo. A situação é leitura.
- “Bloqueio” no limiar é o nome do corte de classificação `saturado`, não uma trava do processo.

## Validações
- Filtros comuns, inclusive bairro e CNAE.
- Capacidade inválida no parâmetro não entra no mapa.

## Critérios de aceite
- Par com capacidade acima ou igual ao corte de bloqueio sai `saturado`.
- Par entre o alerta e o bloqueio sai `saturando`.
- CNAE sem capacidade sai `sem_capacidade` e percentual nulo.
- A consulta é auditada.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`saturacao`)
- `app/Services/Relatorios/SaturacaoService.php`
- `routes/gestao.php` (`relatorios.saturacao`)

## Não evidenciado
- Agregação por zona urbanística nesta rota.
- Impedimento automático de nova solicitação ao atingir `saturado`.
