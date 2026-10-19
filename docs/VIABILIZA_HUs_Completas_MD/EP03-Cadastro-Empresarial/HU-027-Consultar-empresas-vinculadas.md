# HU-027 — Consultar empresas vinculadas

## Épica
**EP03 — Cadastro Empresarial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar e abrir as empresas com as quais o usuário efetivo tem ou teve vínculo.

## História de Usuário
**Como** cidadão autenticado no portal,  
**quero** ver minhas empresas e o detalhe de cada uma,  
**para** saber CNPJ, CNAE principal, papel e se o vínculo segue ativo.

## Atores e permissões
- Portal autenticado, e-mail verificado e termo LGPD aceito.
- A lista usa o usuário efetivo: o representado, quando há representação ativa.
- Ver o detalhe: policy `view`, qualquer vínculo (ativo ou encerrado) desse usuário.
- Menu "Minhas empresas" (`/portal/empresas`) e ação "Gerenciar empresas" no painel (não pública na landing).
- O total exibido (`totalCompanies`) conta só vínculos ativos. A lista inclui também os encerrados.

## Fluxo evidenciado
1. `GET /portal/empresas` lista empresas que tenham vínculo do usuário efetivo, com CNAE principal e o vínculo mais recente desse usuário.
2. Busca por trecho da razão social ou do nome fantasia, sem diferenciar maiúsculas, ou por prefixo numérico de CNPJ.
3. Ordenação permitida: `legal_name` ascendente ou descendente. Fora isso, o padrão é razão social ascendente.
4. Paginação: 10, 15, 25 ou 50. Sem escolha válida, usa `ui.companies.per_page` (padrão 15).
5. Cada linha mostra razão social, nome fantasia, CNPJ formatado, origem, CNAE principal (código e descrição) e o vínculo (papel, ativo ou encerrado, início e fim).
6. `GET /portal/empresas/{company}` mostra dados cadastrais, CNAEs principal e secundários, e a lista de vínculos (nome, papel, início, fim, motivo), marcando o do usuário efetivo.

## Regras de negócio evidenciadas
- Empresa sem vínculo com o usuário efetivo não entra na lista e o detalhe é negado pela policy.
- Vínculo encerrado continua visível na lista e no detalhe.
- As habilidades de atualizar, gerir CNAEs e encerrar vínculo são calculadas à parte e exigem vínculo ativo.

## Validações
- `sort` só aceita `legal_name`. `direction` só `asc` ou `desc`. `per_page` só 10, 15, 25 ou 50.
- Busca é texto livre; dígitos no termo também filtram CNPJ por prefixo.

## Critérios de aceite
### CA-01 — Lista do próprio usuário
**Dado** empresas com vínculo do usuário efetivo,  
**Quando** ele abre Minhas empresas,  
**Então** vê somente essas empresas, com CNAE principal e a situação do vínculo.

### CA-02 — Busca e paginação
**Dado** a lista,  
**Quando** informa busca, ordenação ou tamanho de página aceitos,  
**Então** o resultado respeita esses filtros. Valor de ordenação ou página fora da lista cai no padrão.

### CA-03 — Detalhe e acesso negado
**Dado** vínculo ativo ou encerrado,  
**Quando** abre o detalhe,  
**Então** vê os dados e os vínculos. Sem nenhum vínculo, o acesso é recusado.

## Divergências da redação anterior
A redação anterior não separava lista e detalhe, não mencionava vínculo encerrado ainda visível nem representação. O código lista só empresas do usuário efetivo e mantém o histórico de vínculo encerrado consultável.

## Evidências
- `app/Http/Controllers/Portal/CompanyController.php` (`index`, `show`)
- `app/Policies/CompanyPolicy.php`
- `app/Models/Company.php` (`countForUser`)
- `resources/js/pages/portal/empresas/index.tsx`
- `resources/js/pages/portal/empresas/detalhe.tsx`
- `resources/js/navigation/portal-nav.ts`

## Não evidenciado
- Lista de todas as empresas do município para o cidadão.
- Consulta pública de empresa sem login.
