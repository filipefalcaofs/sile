# HU-139 — Relatório de falhas de comunicação

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar comunicações que falharam ou foram bloqueadas, por processo e por canal, sem o destinatário.

## História de Usuário
**Como** gestor, **quero** ver as notificações não entregues, **para** saber o processo e o canal, sem receber e-mail, telefone ou nome do destinatário nesta lista.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/comunicacoes-falhas`.
- Consulta auditada (`consulta-comunicacoes-falhas`).

## Fluxo evidenciado
1. `ComunicacoesFalhaService` resume e lista comunicações em falha ou bloqueio.
2. O resumo quebra por canal.
3. Filtros da tela: período e canal `email`, `in_app` ou `whatsapp`.
4. A linha do relatório não inclui destinatário.
5. `?formato=` exporta o mesmo recorte (`ComunicacoesFalhaReportSource`), também sem destinatário.

## Regras de negócio evidenciadas
- Entrega com sucesso não entra nesta lista.
- Dado do destinatário não sai na consulta nem na exportação desta rota.

## Validações
- `canal` só aceita `email`, `in_app` ou `whatsapp`.
- Página: 10, 15, 25 ou 50.

## Critérios de aceite
- A lista mostra falha ou bloqueio, processo e canal.
- Não há coluna de destinatário na projeção da linha.
- Canal inválido não passa na validação.
- A consulta é auditada.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`comunicacoesFalhas`)
- `app/Services/Relatorios/ComunicacoesFalhaService.php`
- `app/Http/Requests/Gestao/RelatorioFiltersRequest.php`
- `routes/gestao.php` (`relatorios.comunicacoes-falhas`)

## Não evidenciado
- Reenvio da mensagem a partir desta tela.
- Exibição de e-mail ou telefone do destinatário.
