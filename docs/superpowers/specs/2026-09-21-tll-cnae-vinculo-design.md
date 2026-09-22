# Spec — Vínculo CNAE na tela de tarifas TLL (somente leitura)

Data: 2026-09-21
Origem: após a carga da tabela oficial Simplifica TLL 2026 — "Deve ter o vínculo com o CNAE".
Estende: `2026-09-20-tll-valores-exercicio-dam-sefaz-design.md` e HU-015 RN-007 / HU-071 RN-004.

## 1. Decisão

O vínculo CNAE → TLL vive **somente** em `treatment_enquadramentos` (planilha 20.08.26).
`tll_valores` continua sendo só a tarifa por `(codigo_tll, exercicio, especificacao)`.

A tela `/gestao/tll` passa a **mostrar** o vínculo derivado da versão **vigente** de
`RuleDomain::RiscoTratamento`. Não persiste CNAE em `tll_valores`. Não edita o mapeamento.

## 2. Por que não gravar CNAE na tarifa

Um CNAE pode resolver mais de um TLL (ex.: `0111-3/01` → `1.01` e `1.18`). Um TLL cobre
centenas de CNAEs (`1.01` > 1.300). Pivot ou coluna em `tll_valores` duplicaria a planilha
versionada com quatro olhos.

## 3. Comportamento

- Lista: cada linha traz `cnaes_count` (CNAEs distintos da planilha vigente com aquele
  `codigo_tll`). Rascunho/substituída não entram.
- Clique na contagem abre drawer/modal com lista paginada (`cnae`, `denominacao`),
  rótulo "vinculados na planilha vigente — somente leitura".
- Busca da lista também aceita CNAE mascarado (`0111-3/01`) ou só dígitos (`0111301`)
  e devolve os códigos TLL que a planilha vigente resolve para aquele CNAE.
- `6.00` ISENTA não lista os CNAEs residuais do mesmo código: ISENTA casa com linha
  `0.00`/`ISENTA` da planilha; residual casa com `6.00` sem "ISENTA".
- Sem planilha vigente: contagem 0 e lista vazia (pendência honesta).

## 4. Fora de escopo

- Editar o vínculo na tela de TLL.
- Nova coluna/tabela de CNAE em `tll_valores`.
- Item novo no menu.

## 5. Critérios de aceite

- **CA-01** A lista de TLL expõe `cnaes_count` da planilha vigente; rascunho não conta.
- **CA-02** `GET /gestao/tll/{id}/cnaes` devolve CNAEs distintos da vigente para o código
  (e distingue ISENTA vs residual no `6.00`).
- **CA-03** Busca por CNAE (máscara ou dígitos) devolve os TLL resolvidos, inclusive
  o caso multi-TLL (`0111-3/01` → `1.01` e `1.18`).
- **CA-04** Sem permissão `manter-parametros` → 403. Nenhuma escrita em
  `treatment_enquadramentos` nem coluna CNAE em `tll_valores`.
