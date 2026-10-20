# HU-105 — Importar dados da REDESIM

## Épica
**EP03 — Cadastro Empresarial**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Incluir ou atualizar empresa e CNAEs a partir de um arquivo JSON no formato de referência tratado como REDESIM.

## História de Usuário
**Como** operação com acesso ao comando de console,  
**quero** importar um arquivo JSON de empresas,  
**para** criar ou atualizar o cadastro e os CNAEs sem abrir solicitação de viabilidade.

## Atores e permissões
- Não há rota de portal nem tela para esta importação.
- O comando `redesim:importar {arquivo}` roda no console. A opção `--queue` enfileira `ImportRedesimJob`.
- O serviço não cria vínculo `company_user`. A empresa importada não aparece em "Minhas empresas" até existir vínculo por outro fluxo.

## Fluxo evidenciado
1. O comando confere se o arquivo existe. Ausente: mensagem "Arquivo não encontrado" e saída de falha.
2. Sem `--queue`, `RedesimImportService::import` lê o JSON. Aceita um objeto com a chave `protocolo` ou uma lista de objetos.
3. Cada item passa por validação. Item válido faz upsert por CNPJ, em transação, e sincroniza o conjunto de CNAEs (um principal e os secundários resolvidos).
4. Empresa nova nasce com `source` `redesim`, `redesim_protocol` e `redesim_synced_at`. Empresa já existente é atualizada sem alterar `source`.
5. O comando imprime lidos, importados, atualizados, rejeitados e avisos. Se todos os itens forem rejeitados, a saída é falha.
6. A auditoria `empresas` / `importacao-redesim` grava o relatório, com versão de regra `redesim-import-v1`. Resultado `falha` somente quando todos os itens lidos foram rejeitados.
7. Com `--queue`, o comando apenas despacha o job e informa que a importação foi enfileirada. O job chama o mesmo serviço.

## Regras de negócio evidenciadas
- O próprio serviço declara que a estrutura do payload é referência, a validar com a SEDUR, e que o transporte (webservice ou fila do integrador) não está neste comando.
- Campos de solicitação (área ocupada, inscrição imobiliária, forma de atuação) são ignorados.
- Rejeição do item, sem gravar a empresa: protocolo vazio, razão social vazia, CNPJ inválido (`ValidCnpj`) ou CNAE principal inexistente na tabela oficial.
- CNAE principal inativo gera aviso e o item segue. CNAE secundário inexistente ou igual ao principal é ignorado com aviso. Secundário inativo gera aviso e entra no conjunto.
- Não há chamada HTTP à REDESIM, ao REGIN ou à JUCEB neste serviço.

## Validações
- Arquivo legível. JSON inválido encerra o comando com "JSON inválido".
- Por item: protocolo obrigatório, razão social obrigatória, CNPJ válido, CNAE principal existente em `cnaes.code` (somente dígitos).

## Critérios de aceite
### CA-01 — Upsert por arquivo
**Dado** um JSON com protocolo, razão social, CNPJ válido e CNAE principal existente,  
**Quando** `redesim:importar` processa o arquivo,  
**Então** a empresa é criada ou atualizada, os CNAEs do item substituem o conjunto anterior e o relatório é auditado.

### CA-02 — Item inválido não grava empresa
**Dado** um item sem protocolo, sem razão social, com CNPJ inválido ou com CNAE principal fora da tabela,  
**Quando** a importação roda,  
**Então** o item entra em rejeitados e não é persistido.

### CA-03 — Sem canal REDESIM
**Dado** este comando e este serviço,  
**Quando** a importação executa,  
**Então** a origem dos dados é o arquivo local. Nenhum webservice REDESIM é chamado e nenhuma solicitação de viabilidade é aberta.

## Divergências da redação anterior
A redação anterior dizia que o sistema recebe dados encaminhados pela REDESIM/integrador para iniciar o fluxo de viabilidade na SEDUR. O código implementa leitura de arquivo JSON, não o recebimento do integrador, e não abre viabilidade nem vínculo com usuário do portal.

## Evidências
- `app/Services/RedesimImportService.php`
- `app/Console/Commands/ImportRedesimCommand.php`
- `app/Jobs/ImportRedesimJob.php`
- `app/Enums/CompanySource.php`

## Não evidenciado
- Endpoint, fila ou webservice que receba o payload do integrador REDESIM/REGIN/JUCEB para este cadastro.
- Abertura de solicitação de viabilidade a partir do arquivo.
- Criação de vínculo do cidadão com a empresa importada.
- Tela de importação no portal ou no console de gestão.
