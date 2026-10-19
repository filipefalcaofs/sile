# HU-025 — Vincular CNAE principal

## Épica
**EP03 — Cadastro Empresarial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Definir ou trocar o CNAE principal da empresa a partir da tabela oficial de CNAEs ativos.

## História de Usuário
**Como** cidadão com vínculo ativo com a empresa,  
**quero** escolher o CNAE principal,  
**para** registrar a atividade principal usada no cadastro.

## Atores e permissões
- Portal autenticado, e-mail verificado e termo LGPD aceito.
- Policy `manageCnaes`: vínculo ativo do usuário efetivo. Sem esse vínculo, o formulário de CNAE não é autorizado (403).
- A busca `GET /portal/cnaes` devolve no máximo 20 CNAEs ativos, por código (prefixo de dígitos) ou descrição.

## Fluxo evidenciado
1. No detalhe da empresa, o cidadão pesquisa e seleciona um CNAE ativo.
2. A tela pede confirmação: a troca impacta o enquadramento das próximas solicitações.
3. `PUT /portal/empresas/{company}/cnae-principal` com `cnae_id`.
4. `CompanyCnaeService::setPrimary`, em transação: o principal atual deixa de ser principal e permanece vinculado; o escolhido passa a ser o único principal (`syncWithoutDetaching`).
5. Auditoria `empresas` / `cnae-principal` com código anterior e código novo.
6. Retorno: "CNAE principal definido com sucesso."

## Regras de negócio evidenciadas
- Só CNAE com `active` verdadeiro. Mensagem: "O CNAE informado não está ativo na tabela oficial."
- A troca não remove o antigo do cadastro: ele segue como não principal.
- A invariante de um único principal é mantida na aplicação, na ordem rebaixar e depois promover.

## Validações
- `cnae_id`: obrigatório, inteiro, existente em `cnaes` com `active` verdadeiro (`UpdatePrimaryCnaeRequest`).

## Critérios de aceite
### CA-01 — Definir principal ativo
**Dado** vínculo ativo e um CNAE ativo da tabela oficial,  
**Quando** o cidadão confirma a definição,  
**Então** esse CNAE fica como único principal e a troca é auditada com o código anterior e o novo.

### CA-02 — CNAE inativo ou inexistente
**Dado** um identificador de CNAE inativo ou ausente,  
**Quando** a requisição é enviada,  
**Então** o sistema recusa com a mensagem de CNAE não ativo na tabela oficial.

### CA-03 — Sem vínculo ativo
**Dado** usuário sem vínculo ativo,  
**Quando** tenta definir o principal,  
**Então** a policy `manageCnaes` recusa.

## Divergências da redação anterior
A redação anterior não distinguia CNAE ativo, não descrevia a permanência do principal anterior como secundário e tratava a ação como fluxo genérico de sistema. O código exige CNAE ativo e audita a troca no serviço de CNAE da empresa.

## Evidências
- `app/Http/Controllers/Portal/CompanyCnaeController.php`
- `app/Services/CompanyCnaeService.php`
- `app/Http/Requests/Portal/UpdatePrimaryCnaeRequest.php`
- `app/Http/Controllers/Portal/CnaeSearchController.php`
- `app/Policies/CompanyPolicy.php`
- `resources/js/pages/portal/empresas/detalhe.tsx`

## Não evidenciado
- Aceite de CNAE inativo na seleção manual do portal (o import por arquivo REDESIM tem regra diferente).
- Enquadramento LOUOS executado no momento em que o principal é salvo.
