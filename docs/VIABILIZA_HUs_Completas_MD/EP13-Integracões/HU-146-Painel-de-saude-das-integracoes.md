# HU-146 — Painel de saúde das integrações

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Não há painel que mostre a saúde de cada integração, a fila de retentativa e um reprocessamento manual na tela.

## História de Usuário
**Como** gestor, **quero** ver o estado das integrações e reprocessar falha pela tela. Essa tela não existe.

## Atores e permissões
- Não há rota de painel de saúde.
- O log de chamadas (`/gestao/auditoria/integracoes`) é consulta, com `consultar-auditoria`, e não reenvia.
- Teste de conexão de REGIN e de inscrição imobiliária fica na configuração (`manter-parametros`) e só exercita a URL informada.

## Fluxo evidenciado
1. Não há controller que agregue estado de REGIN, SEFAZ, GIS, Receita e cadastro imobiliário com ação de reprocessar.
2. O comando `regin:reenviar-pareceres` reenvia pareceres REGIN ainda sem confirmação. É linha de comando, só desse canal, e não é um painel.
3. SEFAZ de viabilidade nem chega a HTTP: o binding lança indisponibilidade. Não há fila de retentativa dessa transmissão na tela.

## Regras de negócio evidenciadas
- Consultar o log não conta como reprocessar.
- Teste de conexão não marca a integração como saudável para o negócio.

## Validações
- Não há formulário de reprocessamento neste painel, porque o painel não existe.

## Critérios de aceite
- Não há rota de gestão cujo nome ou controller seja painel de saúde das integrações.
- Abrir o log de chamadas não dispara novo envio.

## Divergências da redação anterior
O texto pedia estado, últimas falhas, fila e reprocessamento manual de Regin, SEFAZ, GIS, Receita e cadastro. Isso não está numa tela. Log, teste de URL e o comando de reenvio de parecer são peças distintas.

## Evidências
- Ausência de controller de saúde em `app/Http/Controllers` e em `routes/gestao.php`.
- `app/Http/Controllers/Gestao/IntegrationCallController.php` (somente leitura)
- `app/Console/Commands/ReginReenviarPareceresCommand.php` (CLI, só parecer REGIN)

## Não evidenciado
- Fila visual de retentativas e botão de reprocessar por integração.
