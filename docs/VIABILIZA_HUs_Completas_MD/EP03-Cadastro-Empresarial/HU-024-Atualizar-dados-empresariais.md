# HU-024 — Atualizar dados empresariais

## Épica
**EP03 — Cadastro Empresarial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir que quem tem vínculo ativo altere os dados complementares da empresa, sem mudar o CNPJ.

## História de Usuário
**Como** cidadão com vínculo ativo com a empresa,  
**quero** atualizar razão social, endereço e contato,  
**para** manter o cadastro usado nas solicitações.

## Atores e permissões
- Mesmo acesso do portal (sessão, e-mail verificado, termo LGPD).
- A policy `update` exige vínculo ativo do usuário efetivo (o representado, se houver representação).
- Vínculo encerrado permite ver o detalhe e impede a alteração (`abilities.update` falso).
- Quem não tem nenhum vínculo recebe 403 na policy `view` ao abrir o detalhe.

## Fluxo evidenciado
1. No detalhe `/portal/empresas/{company}`, a tela mostra identificação, endereço, contato, origem, protocolo REDESIM e data de sincronização quando existirem.
2. Com permissão de atualizar, o cidadão envia `PUT /portal/empresas/{company}`.
3. `CompanyController::update` grava apenas o que `UpdateCompanyRequest` validou.
4. Volta à mesma página com "Dados da empresa atualizados com sucesso."
5. A alteração da empresa é auditada pelo `HasAuditoria` do modelo.

## Regras de negócio evidenciadas
- CNPJ não entra nas regras de atualização. Valor enviado nesse campo não é gravado.
- Origem (`source`), protocolo REDESIM e data de sincronização não fazem parte do formulário de atualização.
- CNAEs e encerramento de vínculo têm rotas próprias.

## Validações
Iguais às do cadastro, exceto a ausência de CNPJ: razão social obrigatória; demais campos cadastrais opcionais, com os mesmos limites, e-mail e telefone com DDD.

## Critérios de aceite
### CA-01 — Atualização com vínculo ativo
**Dado** vínculo ativo do usuário efetivo,  
**Quando** ele altera a razão social ou os dados complementares válidos,  
**Então** a empresa é atualizada e o CNPJ permanece o mesmo.

### CA-02 — Sem vínculo ativo
**Dado** apenas vínculo encerrado, ou nenhum vínculo,  
**Quando** há tentativa de atualizar,  
**Então** a policy recusa a ação.

## Divergências da redação anterior
A redação anterior falava em "usuário autorizado" e em fluxo genérico de integração. O código autoriza pelo vínculo ativo com a empresa e torna o CNPJ imutável nesta atualização.

## Evidências
- `app/Http/Controllers/Portal/CompanyController.php` (`show`, `update`)
- `app/Http/Requests/Portal/UpdateCompanyRequest.php`
- `app/Policies/CompanyPolicy.php`
- `resources/js/pages/portal/empresas/detalhe.tsx`
- `routes/portal.php` (`portal.empresas.update`)

## Não evidenciado
- Alteração de CNPJ, de origem ou de protocolo REDESIM por esta tela.
- Aprovação da SEDUR antes de gravar a alteração.
