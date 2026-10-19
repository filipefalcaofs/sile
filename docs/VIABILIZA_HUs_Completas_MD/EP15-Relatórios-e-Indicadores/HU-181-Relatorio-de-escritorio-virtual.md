# HU-181 — Relatório de escritório virtual

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar, por inscrição imobiliária travada, a sede do escritório virtual e os processos abrigados.

## História de Usuário
**Como** gestor, **quero** localizar a sede e quem está abrigado na mesma inscrição, **para** conferir o vínculo pelo número da TVL da sede ou pela inscrição.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/escritorio-virtual`.
- Consulta auditada (`consulta-escritorio-virtual`).

## Fluxo evidenciado
1. `RelatorioSedeEscritorioVirtualService` agrupa a sede (alvo do lock ativo) e os abrigados (`is_virtual_office_tenant`).
2. O recorte da tela é `sede` (número do produto TVL) ou `inscricao`. Os demais filtros do request não entram nesta lista.
3. A lista pagina (10, 15, 25 ou 50).
4. `?formato=` exporta o agrupamento sede e abrigados (`RelatorioSedeReportSource`).
5. `?formato=` com `recorte=sedes` exporta a lista plana de sedes (empresa, CNPJ, bairro e resultado no período), pela `EscritorioVirtualReportSource`.

## Regras de negócio evidenciadas
- Abriga quem a decisão marcou como abrigado e aponta o número da TVL da sede.
- Sem sede nem inscrição informadas, a consulta segue o serviço sem inventar inscrição.

## Validações
- `sede` e `inscricao` são textos opcionais.
- Página fora da lista cai no padrão do controller.

## Critérios de aceite
- A linha mostra a sede e os abrigados da inscrição travada.
- Exportar sem `recorte=sedes` mantém o agrupamento.
- Exportar com `recorte=sedes` sai a lista plana de sedes.
- A consulta é auditada.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`escritorioVirtual`)
- `app/Services/Relatorios/RelatorioSedeEscritorioVirtualService.php`
- `routes/gestao.php` (`relatorios.escritorio-virtual`)

## Não evidenciado
- Criação ou encerramento de sede nesta tela.
