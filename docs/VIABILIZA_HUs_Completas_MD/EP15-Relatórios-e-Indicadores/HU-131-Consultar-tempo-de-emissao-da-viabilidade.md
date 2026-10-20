# HU-131 — Consultar tempo de emissão da viabilidade

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar processos decididos com o tempo entre abertura e emissão da viabilidade, em minutos úteis.

## História de Usuário
**Como** gestor, **quero** a tabela de tempo de emissão da TVL, **para** ver cada processo decidido no recorte, separado do gráfico de tempo médio.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/tempo-emissao-tvl`.
- Consulta auditada (`consulta-tempo-emissao-tvl`).

## Fluxo evidenciado
1. `RelatorioTempoEmissaoTvlService` lista processos decididos e calcula emissão menos abertura em minutos úteis.
2. O tipo exibido é sempre “Viabilidade”. `tipo=revisao` devolve conjunto vazio: revisão via REDESIM não é simulada.
3. Filtros incluem serviço (tipo de serviço ativo), resultado `deferida` ou `indeferida`, além do período.
4. O dropdown de serviço sai do catálogo ativo. Não há opção inventada de “atividades em residência” se ela não estiver cadastrada.
5. `?formato=` exporta o mesmo recorte (`RelatorioTempoEmissaoTvlReportSource`).

## Regras de negócio evidenciadas
- Tempo ausente fica em branco na linha, não vira zero inventado.
- Revisão não homologada não gera linhas de exemplo.
- Esta página não é o bloco agregado da rota de tempo médio.

## Validações
- `servico` inteiro opcional; `resultado` só `deferida` ou `indeferida`; `tipo` só `viabilidade` ou `revisao`.
- Página: 10, 15, 25 ou 50.

## Critérios de aceite
- A lista traz processos decididos do recorte com o tempo em minutos úteis.
- `tipo=revisao` não devolve linha fabricada.
- Serviços do filtro são os tipos ativos cadastrados.
- Exportar repete o recorte.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`tempoEmissaoTvl`)
- `app/Services/Relatorios/RelatorioTempoEmissaoTvlService.php`
- `routes/gestao.php` (`relatorios.tempo-emissao-tvl`)

## Não evidenciado
- Série de revisão REDESIM.
- Edição de decisão nesta tela.
