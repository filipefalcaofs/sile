# HU-073 — Consultar processo

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar a fila de processos da retaguarda, com filtros, paginação, exportação do conjunto filtrado e detalhe auditado.

## História de Usuário
**Como** usuário da gestão com `consultar-solicitacoes`, **quero** buscar e abrir um processo, **para** ver dados, decisão, ficha vigente e tramitação.

## Atores e permissões
- Rotas `GET processos`, `GET processos/fila`, `GET processos/busca` e `GET processos/{processo}`: `consultar-solicitacoes`.
- Não há permissão nova só para esta consulta.
- A leitura do detalhe é marcada como dado pessoal na auditoria.

## Fluxo evidenciado
1. O índice aplica filtros (protocolo, BAP, TVL, serviço, setor, analista, categoria, inscrição, nome, CNPJ, CEP, logradouro, bairro, período, busca, fluxo, status e situação da análise), ordena e pagina no servidor.
2. Com `formato` csv, xlsx ou pdf e período válido, exporta o conjunto filtrado pelo exportador de relatórios, que audita a exportação.
3. Período com data inicial posterior à final não consulta: a lista volta vazia e a tela recebe a mensagem de período invertido. A consulta mesmo assim é auditada.
4. O detalhe carrega empresa, setor, responsável, decisão, ficha vigente, transições e encaminhamento, e devolve parecer e explicação da decisão quando existirem.
5. Processo inexistente responde 404 pelo route model binding.

## Regras de negócio evidenciadas
- A consulta é somente leitura nesta rota. Mudar situação da análise é outro endpoint, sob `analisar-processos`.
- Paginação aceita 10, 15, 25 ou 50; fora disso usa o parâmetro `ui.cnaes.per_page` (padrão 15).
- Ordens aceitas: `recentes`, `prazo`, `status`. Outro valor cai em `recentes`.

## Validações
- Datas no formato `YYYY-MM-DD` com início posterior ao fim interrompem a consulta e a exportação.
- Filtros vazios são ignorados pelo serviço de consulta.

## Critérios de aceite
1. Sem `consultar-solicitacoes`, o acesso responde 403.
2. A lista respeita os filtros informados e registra a consulta na auditoria, inclusive os filtros preenchidos.
3. Período invertido não devolve processos e informa o erro na tela.
4. Exportação csv, xlsx ou pdf usa o conjunto filtrado.
5. O detalhe de processo inexistente é 404. O detalhe existente audita `consulta-processo` com dado pessoal.

## Divergências da redação anterior
- A redação anterior listava atores genéricos (cidadão, gestor, administrador) como se todos consultassem esta tela. A rota da retaguarda exige `consultar-solicitacoes`.
- Não há, nesta história, decisão automática nem encaminhamento por regra ausente.

## Evidências
- `app/Http/Controllers/Gestao/ProcessoController.php` (`index`, `show`)
- `app/Services/Analise/ProcessoQueryService.php`
- `routes/gestao.php`

## Não evidenciado
- Consulta do cidadão nesta rota. O portal tem rotas próprias de solicitação.
