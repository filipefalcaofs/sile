# HU-023 — Cadastrar empresa

## Épica
**EP03 — Cadastro Empresarial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir que o cidadão cadastre uma empresa no portal e se torne responsável ativo dela.

## História de Usuário
**Como** cidadão autenticado no portal,  
**quero** informar os dados da empresa,  
**para** vinculá-la à minha conta como responsável.

## Atores e permissões
- Cidadão com sessão do portal, e-mail verificado e termo LGPD aceito.
- Em representação ativa, a empresa e o vínculo são criados para o representado (`CurrentRepresentation`).
- Menu: "Minhas empresas" (`/portal/empresas`). O cadastro é tela filha: `/portal/empresas/cadastrar`.
- Quem grava torna-se responsável. Não há escolha de papel nesta tela.

## Fluxo evidenciado
1. O cidadão abre "Cadastrar empresa". A página informa se a busca de CNPJ está ligada (`cnpjLookupEnabled`).
2. Preenche o formulário. A busca de CNPJ, quando usada, só pré-preenche; a gravação é o `POST /portal/empresas`.
3. `CompanyController::store` cria a empresa com `source` `manual` e, na mesma transação, o vínculo `responsavel` com `started_at` agora, para o usuário efetivo.
4. Redireciona para a lista com a mensagem "Empresa cadastrada com sucesso."
5. A auditoria de criação da empresa e do vínculo fica a cargo do mecanismo `HasAuditoria` dos modelos.

## Regras de negócio evidenciadas
- CNPJ duplicado é recusado com "Já existe empresa cadastrada com este CNPJ."
- A origem do cadastro feito por esta tela é sempre manual. Protocolo REDESIM não é preenchido aqui.
- O cadastro não exige CNAE. CNAE principal e secundários entram depois, no detalhe.

## Validações
`StoreCompanyRequest`, após normalizar CNPJ (sem máscara, maiúsculas), telefone e CEP (só dígitos):
- CNPJ: obrigatório, `ValidCnpj`, único em `companies.cnpj`.
- Razão social: obrigatória, até 255 caracteres.
- Nome fantasia, natureza (código até 4 e descrição), porte (código até 2 e descrição), logradouro, número (até 20), complemento, bairro, cidade: opcionais, até 255 quando houver limite.
- UF: opcional, 2 caracteres. CEP: opcional, 8 dígitos.
- E-mail: opcional, formato de e-mail. Telefone: opcional, até 11 dígitos, regra `TelefoneComDdd`.

## Critérios de aceite
### CA-01 — Cadastro com vínculo de responsável
**Dado** um CNPJ válido ainda não cadastrado e a razão social,  
**Quando** o cidadão envia o formulário,  
**Então** a empresa é gravada com origem manual e o usuário efetivo fica com vínculo ativo de responsável.

### CA-02 — CNPJ repetido
**Dado** que já existe empresa com o mesmo CNPJ,  
**Quando** o cidadão tenta cadastrar,  
**Então** o sistema recusa e informa que já existe empresa cadastrada com este CNPJ.

### CA-03 — Representação
**Dado** uma representação ativa,  
**Quando** o procurador cadastra a empresa,  
**Então** o vínculo de responsável é do representado.

## Divergências da redação anterior
A redação anterior era um fluxo genérico de "usuário" com integração e auditoria indistintas. O código restringe o ator ao portal autenticado, fixa a origem manual e cria o vínculo de responsável na mesma transação do cadastro.

## Evidências
- `app/Http/Controllers/Portal/CompanyController.php` (`create`, `store`)
- `app/Http/Requests/Portal/StoreCompanyRequest.php`
- `routes/portal.php` (`portal.empresas.create`, `portal.empresas.store`)
- `resources/js/pages/portal/empresas/cadastrar.tsx`
- `resources/js/navigation/portal-nav.ts`

## Não evidenciado
- Cadastro por servidor da SEDUR nesta tela.
- Importação REDESIM acoplada ao botão de salvar.
- Definição de CNAE no momento do cadastro.
