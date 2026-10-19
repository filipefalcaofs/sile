# HU-124 — Solicitações por zona

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
A série “por zona” da tela de indicadores não agrupa zona urbanística: agrupa o bairro digitado e declara essa degradação.

## História de Usuário
**Como** gestor, **quero** ver a concentração territorial das solicitações. Nesta tela o recorte é o bairro informado no processo, com rótulo de que a zona não entrou na agregação.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/indicadores`.
- O painel por bairro e zona materializada é outra rota (`geo-bairro`).

## Fluxo evidenciado
1. `porZona` filtra `address_neighborhood` preenchido, agrupa por esse bairro e devolve `degradacao` `bairro` e o rótulo “Zona urbanística indisponível — agrupado por bairro”.
2. Solicitação sem bairro não vira um grupo “vazio”.
3. Período invertido devolve a mesma estrutura com lista vazia e o mesmo rótulo.
4. A zona oficial gravada em `zona_codigo` não é a chave desta série.

## Regras de negócio evidenciadas
- A tela não inventa código de zona para completar a série.
- O rótulo de degradação é fixo no serviço, mesmo quando outras telas já leem zona materializada.

## Validações
- Mesmos filtros da tela de indicadores. Período invertido não consulta.

## Critérios de aceite
- A resposta traz `degradacao` `bairro` e itens com bairro e total, não código de zona.
- Processo sem bairro fica de fora da lista.
- Não aparece zona atribuída sem identificador gravado.

## Divergências da redação anterior
O texto pedia relatório territorial por zona. O método desta HU entrega bairro e avisa a substituição. Zona oficial consultável está no painel por bairro, não aqui.

## Evidências
- `app/Services/Relatorios/IndicadoresViabilidadeService.php` (`porZona`)
- `app/Http/Controllers/Gestao/RelatorioController.php` (`indicadores`)

## Não evidenciado
- Agregação por `zona_codigo` nesta série.
