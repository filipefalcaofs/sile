# HU-099 — Exportar auditoria

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Exportar o conjunto filtrado da trilha de auditoria em CSV, XLSX ou PDF.

## História de Usuário
**Como** auditor, **quero** baixar a trilha filtrada, **para** levar o recorte ao controle interno sem outro conjunto de dados.

## Atores e permissões
- `consultar-auditoria` em `/gestao/auditoria/export` e também quando o index recebe `?formato=`.
- O arquivo assíncrono, quando o volume passa do limiar, é baixado depois por URL assinada (`ExportacaoController`), com `consultar-relatorios` na rota de download.

## Fluxo evidenciado
1. A fonte `acessos` usa `AcessosReportSource`. Atividade e alterações usam `AtividadesReportSource`, com `fonte` no filtro para o job assíncrono reconstruir o recorte.
2. Formato ausente ou desconhecido no export direto vira CSV. No index, só csv, xlsx e pdf disparam exportação.
3. `ReportExporter` audita tela, filtros, formato e volume, marca dado pessoal e escolhe o caminho: até o limiar (`relatorios.export.assincrono_limiar_linhas`, padrão 5000) devolve o arquivo; acima disso responde 202 e enfileira `GerarExportacaoJob`.
4. O export usa os mesmos filtros da consulta.

## Regras de negócio evidenciadas
- O arquivo não é um recorte diferente da tela.
- Formato fora dos habilitados na configuração gera exceção, não um arquivo vazio.
- A guarda de volume histórica da auditoria permanece via `maxRows` da fonte.

## Validações
- Formatos com driver: csv, xlsx e pdf, ainda sujeitos a `relatorios.export.formatos_habilitados`.

## Critérios de aceite
- Exportar com os filtros da tela gera o mesmo conjunto filtrado.
- CSV é o formato quando `formato` não é informado em `/export`.
- Volume acima do limiar responde 202 e não trava a requisição com o arquivo inteiro.
- A exportação é auditada como dado pessoal.

## Divergências da redação anterior
O texto genérico não distinguia fonte nem formato. O código exporta atividade ou acesso, em três formatos, síncrono ou assíncrono conforme o volume.

## Evidências
- `app/Http/Controllers/Gestao/AuditoriaController.php` (`export`, `formato`)
- `app/Services/Relatorios/Export/ReportExporter.php`
- `app/Services/Relatorios/Export/Sources/AtividadesReportSource.php`
- `app/Services/Relatorios/Export/Sources/AcessosReportSource.php`

## Não evidenciado
- Exportação de alertas de abuso ou do painel LGPD por esta rota.
