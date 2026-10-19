# HU-184 — Consultar log de integrações

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar as chamadas externas gravadas, com direção, resultado e o corpo da troca.

## História de Usuário
**Como** auditor, **quero** ver o log das chamadas de integração, **para** saber o que foi enviado e o que voltou, sem reprocessar a integração nesta tela.

## Atores e permissões
- `consultar-auditoria` em `/gestao/auditoria/integracoes` e no detalhe numérico.
- Cada listagem e cada detalhe são auditados como dado pessoal (`consulta-integracoes` e `consulta-integracao`).

## Fluxo evidenciado
1. A lista pagina 20 registros, do mais recente ao mais antigo.
2. Filtros aceitos: direção `entrada` ou `saida`, resultado `sucesso` ou `erro`, e integração cujo slug case com o padrão gravado.
3. Cada linha mostra data, direção, integração (rótulo conhecido ou o slug), método, URL, status HTTP, resultado, duração e mensagem de erro.
4. As opções de integração saem dos slugs distintos já gravados. Rótulos conhecidos: REGIN/JUCEB, GeoServer, geocodificação, CNPJ, gov.br, inscrição imobiliária, SEDUR contato, SEDUR autenticação e IA.
5. O detalhe acrescenta truncamento, correlação, IP, agente, cabeçalhos e corpos de pedido e resposta.

## Regras de negócio evidenciadas
- Valor de filtro fora da lista permitida é ignorado; a lista não quebra.
- A tela não dispara nova chamada externa nem reprocessa falha.
- Slug sem rótulo cadastrado aparece como foi gravado.

## Validações
- `integracao` só entra se for string de 1 a 120 caracteres em `a-z`, dígito, ponto, underline ou hífen.
- O identificador do detalhe é numérico.

## Critérios de aceite
- Filtro `resultado=erro` lista só chamadas com esse desfecho.
- Abrir o detalhe mostra corpo e cabeçalhos gravados e registra a consulta.
- Não há botão, nesta rota, que reenvie a integração.

## Divergências da redação anterior
Não havia redação anterior neste pacote. O painel de saúde com fila de retentativa descrito na HU de integrações não é esta tela.

## Evidências
- `app/Http/Controllers/Gestao/IntegrationCallController.php`
- `app/Models/IntegrationCall.php`
- `routes/gestao.php` (`auditoria.integracoes.index` e `show`)

## Não evidenciado
- Reprocessamento manual a partir do log.
- Indicador agregado de saúde por integração.
