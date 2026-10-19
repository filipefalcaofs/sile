# HU-011 — Manter CNAEs

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter a tabela de subclasses CNAE e, na mesma ficha, o grau de risco municipal vigente daquela subclasse.

## História de Usuário
**Como** administrador com a permissão `manter-cnaes`,
**quero** consultar, cadastrar, alterar, ativar ou desativar e excluir subclasses CNAE, com o grau de risco municipal na ficha,
**para** o motor usar uma base de atividades atualizada.

## Atores e permissões
- Consulta e exportação da listagem: permissão `consultar-cnaes`. Menu Regras, item CNAEs (`/gestao/cnaes`).
- Criar, editar, alterar situação e excluir: permissão `manter-cnaes`. A rota de edição não tem modo somente leitura.
- Quem não tem a permissão da rota é recusado pelo middleware `permission`.

## Fluxo evidenciado
1. A listagem busca por prefixo de código (somente dígitos) ou por denominação, filtra situação (`active` 0 ou 1), ordena por código ou denominação e pagina em 10, 15, 25 ou 50 itens. O padrão de página vem do parâmetro `ui.cnaes.per_page` (15 se ausente).
2. Com `formato` igual a `csv`, `xlsx` ou `pdf`, a mesma listagem exporta o conjunto filtrado.
3. O cadastro grava a subclasse e, na mesma transação, cria ou atualiza a classificação de risco municipal na versão vigente do domínio `risco_municipal`.
4. A edição altera os dados da subclasse e a classificação vigente. Se o código de 7 dígitos mudar, as linhas que apontam para o código antigo (classificação municipal, classificação sanitária, condicionante de risco e CNAE de escritório virtual) passam a apontar para o código novo.
5. A listagem altera só a situação (`active`), sem reenviar o restante da ficha.
6. A exclusão física é recusada quando o CNAE está vinculado a empresas. A restrição também existe no banco (`company_cnae.cnae_id`).

## Regras de negócio evidenciadas
- O código aceito é a subclasse de 7 dígitos. A máscara `DDDD-D/SS` é normalizada para dígitos antes da validação. A exibição usa o código formatado.
- Sem versão vigente de risco municipal, criar ou editar é recusado com a mensagem de que não há versão vigente para registrar a classificação.
- A gravação na ficha faz `updateOrCreate` na linha vigente. A publicação em lote com quatro olhos permanece no import oficial da planilha (`RiscoMunicipalImportService`), fora desta tela.
- CNAE novo nasce ativo: a coluna `active` tem padrão verdadeiro no banco e o cadastro não envia situação.
- CNAE vinculado a empresa não é excluído. A desativação lógica continua disponível.
- A hierarquia IBGE da ficha é seção, divisão, grupo e classe, cada uma com código e descrição.
- Flags da ficha: exige responsável técnico, exige RT apenas se alto risco, possui fator multiplicador e exige detalhamento do multiplicador.
- A alteração do model é auditada pelo `HasAuditoria` do `Cnae`.

## Validações
- Código obrigatório, exatamente 7 dígitos, único em `cnaes.code` (na edição, ignora o próprio registro).
- Denominação obrigatória, até 255 caracteres.
- Códigos e descrições de seção (1), divisão (2), grupo (5) e classe (7), todos obrigatórios, descrições até 255.
- `risco_municipal` obrigatório e restrito ao enum `RiscoMunicipal` (`baixo_a`, `baixo_b`, `alto`).
- As quatro flags de RT e fator multiplicador são booleanos obrigatórios.
- Na edição e na troca de situação, `active` é booleano obrigatório.
- Mensagem do código inválido: o código deve ter 7 dígitos no padrão `DDDD-D/SS`.

## Critérios de aceite
### CA-01 — Listar e exportar
**Dado** um usuário com `consultar-cnaes`,
**Quando** abre `/gestao/cnaes` e informa busca, situação, ordenação ou `formato`,
**Então** a lista respeita o filtro e, com `formato` `csv`, `xlsx` ou `pdf`, exporta esse conjunto.

### CA-02 — Cadastrar com risco vigente
**Dado** que existe versão vigente de risco municipal e o usuário tem `manter-cnaes`,
**Quando** grava uma subclasse de 7 dígitos ainda não cadastrada, com hierarquia, flags e grau de risco,
**Então** o CNAE é criado e a classificação fica na versão vigente.

### CA-03 — Recusa sem versão de risco
**Dado** que não há versão vigente de risco municipal,
**Quando** o usuário tenta cadastrar ou atualizar um CNAE,
**Então** a gravação não ocorre e a tela informa que não há versão vigente para registrar a classificação.

### CA-04 — Exclusão com vínculo
**Dado** um CNAE vinculado a empresa,
**Quando** o usuário com `manter-cnaes` tenta excluí-lo,
**Então** o registro permanece e a mensagem informa que CNAE vinculado a empresas não pode ser excluído.

### CA-05 — Troca de código
**Dado** um CNAE cuja classificação ou vínculo de escritório virtual aponta para o código atual,
**Quando** a edição grava outro código de 7 dígitos,
**Então** essas linhas passam a referenciar o código novo.

## Divergências da redação anterior
A redação anterior era um texto genérico de administração, com fluxos de GIS, REDESIM e decisão automática que esta tela não executa. A observação sobre a planilha CNAE 2.3 do IBGE e o modelo do SIGVISA (perguntas condicionantes com reclassificação sanitária e vínculo N:N com requisito) descrevia uma ficha que o código não entrega aqui. O `CnaeController` registra que o e-mail da SEDUR de 21/09/2026 retirou a validação de condicionantes da VISA desta ficha. O vínculo de requisito documental é a HU-155. O grau de risco na ficha é a HU-020; os gatilhos semi-expresso são a HU-164.

## Evidências
- `routes/gestao.php`: grupo `consultar-cnaes` (`cnaes.index`) e grupo `manter-cnaes` (`cnaes.create`, `store`, `edit`, `update`, `situacao.update`, `destroy`).
- `app/Http/Controllers/Gestao/CnaeController.php`.
- `app/Http/Requests/Gestao/StoreCnaeRequest.php` e `UpdateCnaeRequest.php`.
- `resources/js/navigation/gestao-nav.ts`: item CNAEs em Regras.
- `app/Models/Cnae.php` (`HasAuditoria`) e migration `create_cnaes_table` (`active` padrão verdadeiro).

## Não evidenciado
- Importação da planilha IBGE por esta tela. O cadastro é manual, registro a registro.
- Quatro olhos na gravação da ficha.
- Perguntas de condicionante sanitária na ficha do CNAE.
- Modo somente leitura da ficha para quem só tem `consultar-cnaes`.
