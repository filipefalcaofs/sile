# Reunião cliente — 2026-07-16

Fonte: Google Meet `dhv-isxt-ibp` (vídeo `dhv-isxt-ibp (2026-07-16 11_00 GMT-3).mp4`, ~37 min).

Participante principal: **Lisa Sousa Cerqueira Santos** (SEDUR / Simplifica SAPS).  
Tema: **Escritório Virtual** (sede × abrigado), relatórios e envio para análise.

## Pacote completo

| Ordem | Arquivo | Uso |
|---|---|---|
| 1 | [`ata-itens-para-specs.md`](./ata-itens-para-specs.md) | Resumo de negócio |
| 2 | [`inventario-telas-ui.md`](./inventario-telas-ui.md) | Telas legado → paridade + melhorias |
| 3 | [`prints/catalogo/`](./prints/catalogo/) | 14 prints de referência visual |
| 4 | [`transcricao.md`](./transcricao.md) | Transcrição com timestamps |
| 5 | Specs em `docs/superpowers/specs/` (abaixo) | Design formal para implementação |

### Specs geradas

| Spec | Arquivo |
|---|---|
| Motor sede × abrigado | [`2026-07-16-escritorio-virtual-motor-design.md`](../../superpowers/specs/2026-07-16-escritorio-virtual-motor-design.md) |
| Telas e relatórios | [`2026-07-16-escritorio-virtual-telas-relatorios-design.md`](../../superpowers/specs/2026-07-16-escritorio-virtual-telas-relatorios-design.md) |
| Ficha + enviar para análise | [`2026-07-16-escritorio-virtual-ficha-envio-analise-design.md`](../../superpowers/specs/2026-07-16-escritorio-virtual-ficha-envio-analise-design.md) |

Specs irmãs já existentes: `2026-07-14-desfecho-analise-produto-design.md`, `2026-07-14-status-analise-processo-design.md`.

## Catálogo de prints

| # | Arquivo | Tela |
|---|---|---|
| 01 | `01-consulta-processo-filtros.jpg` | Consulta Processo + Sede de Escritório |
| 02 | `02-detalhar-processo-poligono.jpg` | Detalhe / polígono |
| 02b | `02b-detalhar-atividades-escritorio-virtual-cnae.jpg` | Atividades / CNAE / sede |
| 03–05 | `03*.jpg` … `05*.jpg` | Produto TVL / End. Virtual |
| 06 | `06-relatorio-sede-escritorio-virtual.jpg` | Relatório sede × abrigados |
| 07–08 | `07*.jpg`, `08*.jpg` | Tempo de emissão TVL |
| 09–10 | `09*.jpg`, `10*.jpg` | Enviar TVL para análise |
| 11 | `11-portal-sedur-revista-digital.jpg` | Fora de escopo Viabiliza |

## Próximos passos

1. **Review das 3 specs** (você) — fechar `[OPEN-*]` ou aceitar premissas.  
2. `/gsd-plan-phase` ou plano writing-plans por spec (motor primeiro).  
3. Implementar UI na ordem do inventário: relatório sede → tempo emissão → envio → ficha/produto.
