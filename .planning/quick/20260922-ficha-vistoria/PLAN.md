# Ficha de Vistoria — plano de implementação

Pedido: formulário completo de Ficha de Vistoria a partir dos protótipos
(`vistoria.zip` — prints do legado + protótipo HTML). Ficha vinculada ao
processo (`ViabilityRequest`), preenchida pelo vistoriador na retaguarda.

## Decisões

- **Vínculo**: ficha pertence a um processo (`inspections.viability_request_id`).
  Entrada pela ficha de análise (tela-mãe) — não entra no menu (tela-filha,
  regra menu-navegacao).
- **Identificação**: tipo, data de abertura (`opened_at`) e vistoriador
  (`vistoriador_user_id`) gravados na criação da ficha — nunca digitados.
- **Localização**: snapshot do endereço do processo na abertura (somente
  leitura), exceto `ponto_referencia` e `logradouro_correto` (editáveis).
- **Polígono**: nasce do `property_polygon_geojson` do processo; vistoriador
  pode redesenhar (Leaflet, sem dependência nova — editor próprio com
  react-leaflet) e validar; área calculada via `PropertyGeometryWriter`
  (ST_Area no pgsql, planar no sqlite).
- **Parecer**: obrigatório só na conclusão (`InspectionConcludeRequest`);
  rascunho aceita tudo nullable. Ficha concluída é imutável (422).
- **Anexos**: `inspection_attachments` espelhando `ViabilityRequestDocument`
  (disk parametrizado `storage.documentos.disk`, sha256, streaming
  autenticado, nunca URL pública).
- **Permissão**: nova `preencher-ficha-vistoria` (seeder aditivo; roles
  analista/gestor/administrador).
- **Auditoria**: `HasAuditoria` no model + `AuditService` nos eventos
  (abertura, rascunho, conclusão, polígono, anexos) — RN-002.
- **Tipo de imóvel**: select alimentado por `PropertyType` ativo (dado
  administrável já existente).
- **Acesso**: opções do print do legado — Comum, Requerente, Independente,
  Terceiro (o pedido escrito dizia "Recorrente"; o print mostra "Requerente").

## Arquivos

Backend:
- `database/migrations/2026_09_22_100000_create_inspections_table.php`
- `database/migrations/2026_09_22_100001_create_inspection_attachments_table.php`
- `app/Enums/InspectionStatus.php`, `app/Enums/InspectionType.php`
- `app/Models/Inspection.php`, `app/Models/InspectionAttachment.php`
- `database/factories/InspectionFactory.php`, `InspectionAttachmentFactory.php`
- `app/Services/Vistoria/InspectionService.php`
- `app/Http/Requests/Gestao/InspectionUpdateRequest.php`,
  `InspectionConcludeRequest.php`, `InspectionPolygonRequest.php`,
  `InspectionAttachmentRequest.php`
- `app/Http/Controllers/Gestao/InspectionController.php`
- `app/Http/Resources/InspectionResource.php`
- `routes/gestao.php` (grupo `processos/{viabilityRequest}/vistoria`)
- `database/seeders/RolesAndPermissionsSeeder.php` (permissão nova)

Frontend:
- `resources/js/components/geo/map-poligono.tsx` + `mapa-poligono-section.tsx`
- `resources/js/pages/gestao/vistoria/show.tsx`
- `resources/js/pages/gestao/ficha-analise/show.tsx` (link de entrada)

Testes:
- `tests/Feature/Vistoria/InspectionFlowTest.php`

## Ciclo

TDD: teste primeiro (RED) → backend (GREEN) → frontend → pint + build.
