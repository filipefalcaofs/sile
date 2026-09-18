# Fase 6 — Vocabulário único front × back

## Escopo desta execução

- **6.1** Endpoint + prop compartilhada com rótulos dos enums `RiscoMunicipal`, `RiscoSanitario`, `AnalysisCategory`, `ResultadoViabilidade`, `Quadro10Permissao`. Fonte única no backend.
- **6.4** `ExportMenu` passa a respeitar `relatorios.export.formatos_habilitados` (já no catálogo; o componente ignorava).
- **6.2 / 6.3** bloqueados: `baixo_c` e “malha fina / sede de escritório vs AnalysisCategory” dependem da SEDUR. Não inventar.

## Design

- `App\Support\Vocabulario::catalog()` serializa `cases()` + `label()`.
- `GET /gestao/metadados` (JSON, `auth:gestao`) devolve o catálogo.
- `HandleInertiaRequests` compartilha o mesmo catálogo em `vocabulario` (portal e gestão) e `export_formatos` a partir de `Settings`.
- Telas deixam de copiar rótulos desses cinco enums. `CATEGORIA_OPTIONS` com 4 valores permanece local até a SEDUR responder 6.3.

## Verificação

- Feature test do endpoint e da prop compartilhada.
- Feature test: parâmetro `formatos_habilitados = ["csv"]` aparece em `export_formatos`.
- `tsc --noEmit`.
