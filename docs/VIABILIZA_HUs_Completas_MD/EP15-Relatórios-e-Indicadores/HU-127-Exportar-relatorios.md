# HU-127 — Exportar relatórios

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Exportar o recorte de cada relatório em CSV, XLSX ou PDF, no ato ou por arquivo gerado em fila, e baixar esse arquivo por link assinado.

## História de Usuário
**Como** gestor, **quero** baixar o mesmo conjunto que filtrei na tela, **para** usar o recorte fora do console sem outro universo de dados.

## Atores e permissões
- `consultar-relatorios` nas rotas de relatório. `?formato=csv|xlsx|pdf` dispara o exportador.
- Download assíncrono: `GET /gestao/relatorios/exportacoes/{exportFile}/download`, com middleware `signed`.
- Além da permissão da rota, o download exige ser o dono do arquivo ou ter `consultar-relatorios`. Disco `public` responde 404.

## Fluxo evidenciado
1. O mesmo `ReportExporter` atende os relatórios e também as listagens de CNAEs, usuários, perfis, parâmetros, processos, trilha de auditoria e histórico de acessos, cada uma com a própria fonte e os filtros da tela.
2. `ReportExporter` conta as linhas da definição, audita título, filtros, formato e volume, e marca dado pessoal quando a fonte declara.
3. Até `relatorios.export.assincrono_limiar_linhas` (padrão 5000), ou se a fonte é só síncrona, devolve o arquivo. Acima do limiar, enfileira `GerarExportacaoJob` com o filtro serializado e responde 202.
4. O job reconstrói o mesmo recorte. Não serializa closure.
5. Formato fora dos drivers habilitados (csv, xlsx, pdf) lança exceção.
6. O download faz stream do disco não público. Arquivo já removido pela retenção responde 404, sem arquivo vazio. O download é auditado.

## Regras de negócio evidenciadas
- O arquivo é o recorte filtrado, não a página corrente.
- Exportação de trilha por processo não usa este contrato: o PDF dela é outra ação.
- Produtividade leva o modo nominal para dentro da fonte, para o assíncrono não perder o sigilo.

## Validações
- `formato` na query. Valor desconhecido não gera arquivo.
- URL de download sem assinatura válida não chega ao controller.

## Critérios de aceite
- Formato habilitado abaixo do limiar devolve o arquivo do recorte.
- Volume acima do limiar responde 202 e o arquivo posterior respeita os mesmos filtros.
- Arquivo ausente ou disco público não é entregue.
- Cada exportação e cada download entram na auditoria.

## Divergências da redação anterior
O texto prometia exportar qualquer listagem da gestão. O contrato único existe e é reutilizado, mas só nas telas que chamam `ReportExporter`: relatórios, trilha de auditoria, processos, CNAEs, usuários, perfis, parâmetros e histórico de acessos. Feriados, setores e demais cadastros sem `?formato=` não exportam por este caminho.

## Evidências
- `app/Services/Relatorios/Export/ReportExporter.php`
- `app/Http/Controllers/Gestao/ExportacaoController.php`
- `app/Http/Controllers/Gestao/RelatorioController.php`
- `app/Http/Controllers/Gestao/AuditoriaController.php`
- `app/Http/Controllers/Gestao/ProcessoController.php`
- `app/Http/Controllers/Gestao/CnaeController.php`
- `app/Http/Controllers/Gestao/UserManagementController.php`
- `app/Http/Controllers/Gestao/RoleController.php`
- `app/Http/Controllers/Gestao/ParameterController.php`
- `app/Http/Controllers/Gestao/AccessHistoryController.php`
- `routes/gestao.php` (`relatorios.exportacoes.download`)

## Não evidenciado
- Exportação de todo datatable do console. Cadastros sem branch `?formato=` (feriados, setores e outros) não entram neste contrato.
