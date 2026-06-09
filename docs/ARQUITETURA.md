# SILE — Sistema Integrado de Licenciamento Empresarial
## Especificação Técnica da Arquitetura e Stack Completa (Padrão Ouro — Últimas Versões 2026)

Este documento consolida a arquitetura de software, o ecossistema de tecnologias e os padrões de engenharia recomendados para o desenvolvimento do SILE. O design foi projetado para focar em **segurança jurídica, auditabilidade extrema, precisão normativa e escalabilidade**, utilizando o que há de mais moderno no mercado.

> **Nota de versões (atualizada na implantação do projeto, junho/2026):** o bootstrap do repositório foi feito com as versões estáveis mais recentes, que superam as citadas no texto original: **Laravel 13.15 (PHP 8.5)**, **Inertia.js v3.3** (substitui a v2 — deferred/lazy props continuam disponíveis e ampliados), **React 19.2**, **TypeScript 6** e **Tailwind CSS v4**.

---

## 1. Stack Tecnológica Atualizada (Últimas Versões)

### Front-End (SPA de Alta Performance)
* **Biblioteca Principal:** **React 19**
  * *Destaque:* Uso nativo dos novos hooks de gerenciamento de estado assíncrono, suporte aprimorado a tipagem estrita e o novo compilador do React que elimina a necessidade de `useMemo` manuais na maioria dos cenários de renderização de listas da SEDUR.
* **Linguagem:** TypeScript
* **Camada de Ligação (Monólito Moderno):** **Inertia.js v3** (pacote oficial para React)
  * *Destaque:* Uso de **Deferred Props** e **Lazy Props** para carregar dados geoespaciais pesados em segundo plano, mantendo a transição de telas instantânea para o cidadão.
* **Framework de Estilização:** **Tailwind CSS v4** (Compilador *Oxide* ultra-rápido baseado em Rust).
* **Design System & UI:** **shadcn/ui** (Construído sobre o Radix Primitives para React 19).
* **Gerenciamento de Tabelas Complexas:** TanStack Table v8 (Integrado ao Data Table do shadcn para gerenciar as filas de processos da retaguarda).
* **Mapas Interativos:** Leaflet ou OpenLayers com wrappers nativos para React.

### Back-End (O Motor de Regras e Infraestrutura)
* **Framework Principal:** **Laravel 13 (PHP 8.5)**
  * *Destaque:* Uso de **Asymmetric Visibility** do PHP 8.4+ nos Models e DTOs (propriedades com leitura pública e escrita privada), além do motor otimizado para tarefas em segundo plano.
* **Banco de Dados Relacional:** PostgreSQL 16+
* **Extensão Geoespacial (Crítica):** PostGIS (Cálculo de zonas, vias e polígonos urbanos da LOUOS).
* **Extensão de Vetores (IA):** pgvector (Para busca semântica no portal e suporte a cruzamento documental por IA).
* **Cache, Sessões e Filas:** Redis

---

## 2. Visão Geral da Arquitetura (Monólito Modular)

Para mitigar a complexidade de rede, latência e o custo de infraestrutura gerados por microsserviços, o SILE adota uma abordagem de **Monólito Modular**. O sistema reside em um único repositório, mas possui divisão estrita de limites de domínio.

### Estrutura de Pastas de Domínio (`app/Modules/`)

```
app/Modules/
├── RuleEngine/       # Motor de Regras LOUOS (Isolado, tipado com DTOs e Agnóstico de HTTP)
├── CitizenPortal/    # SILE Cidadão (Controllers, Rotas do Inertia e validações do portal externo)
├── Management/       # SILE Gestão (Retaguarda SEDUR, análises, auditoria e parametrizações)
├── Geoprocessing/    # Camada GIS (Encapsulamento de queries PostGIS e polígonos)
├── AIIntegration/    # Camada de IA (Gateways para LLM/Vision e análise de anexos via Jobs)
└── Audit/            # Trilha de Auditoria Imutável (Registros de fé pública)
```

---

## 3. Fluxo de Dados e Integrações (Arquitetura Orientada a Eventos)

O processamento de tarefas pesadas (Motor de Regras, IA, OCR) e a comunicação com APIs externas instáveis (REDESIM) são feitas de forma **assíncrona baseada em eventos e filas (Laravel Queues + Redis)**. Isso evita travamentos na interface do usuário.

```
[Portal do Cidadão (React 19)] ──(Submete Processo via Inertia v3)──> [Laravel Controller (PHP 8.5)]
│
(Dispara Evento Interno)
│
┌────────────────────────────────────────────────┴────────────────────────────────┐
▼                                                ▼                                ▼
[Fila: Motor de Regras]                         [Fila: Integração IA]            [Fila: Webhooks Externos]
- Consulta PostGIS (Zonamento)                  - OCR e Leitura de Anexos        - Consulta REDESIM
- Executa Quadros da LOUOS                      - Check de Inconsistências       - Sincronização Junta/RFB
│                                                │                                │
└────────────────────────────────────────────────┼────────────────────────────────┘
▼
[Gravação do Resultado em JSON]
│
[Trilha de Auditoria Owen-It]
```

### Estratégia de Integrações Críticas
* **REDESIM, Junta Comercial, SEFAZ:** Consumidos via requisições HTTP isoladas com políticas rígidas de *Retry* (tentativas automáticas do Laravel) e *Circuit Breaker* (interrupção de chamadas para APIs fora do ar, evitando que o Redis fique sobrecarregado).
* **GIS / Mapas:** O banco PostGIS serve dados geográficos brutos (GeoJSON ou Vector Tiles). O front-end React 19 consome essas camadas diretamente no mapa interativo através de componentes desacoplados.
* **Notificações:** Acopladas ao *Notification System* do Laravel, despachando avisos de pendências e deferimentos por e-mail e WhatsApp (API Oficial) via Jobs secundários.

---

## 4. Padrões de Projeto Aplicados (Design Patterns)

### A. Specification Pattern (No Motor de Regras)
Cada critério da LOUOS (Quadros 7, 10, 11 e 11A) é encapsulado em uma classe de especificação isolada (ex: `Quadro7AreaSpecification`).
* **Benefício:** Remove condicionais aninhadas (`if/else`) dos controllers. O motor recebe a solicitação, passa pelas especificações e devolve um objeto estruturado detalhando o motivo exato de cada sucesso ou falha.

### B. Strategy Pattern (Na Camada de IA e Integrações)
Garante independência de fornecedores de tecnologia. A comunicação com modelos de linguagem ou gateways de SMS é abstraída por contratos (Interfaces do Laravel).
* **Benefício:** Permite trocar o provedor de IA ou o serviço de mensageria alterando apenas uma linha no arquivo `.env`, sem impactar as telas em React.

### C. Data Transfer Objects (DTOs) com PHP 8.4+
Toda a comunicação que transita entre o front-end React, as APIs externas e o Motor de Regras é estruturada por meio de DTOs nativos usando as propriedades de visibilidade assimétrica do PHP 8.4+ (`public private(set)`).
* **Benefício:** Garante que os dados sejam imutáveis após validados, impedindo que regras de negócio alterem informações coletadas da REDESIM ou do PostGIS de forma acidental durante o fluxo.

---

## 5. Requisitos de Segurança e Fé Pública

1.  **Auditoria Imutável (`owen-it/laravel-auditing`):** Qualquer alteração de parâmetros da LOUOS na retaguarda do *SILE Gestão* ou pareceres emitidos por analistas disparam logs automáticos contendo autor, dados alterados (antigo vs. novo), IP e timestamp.
2.  **Versionamento da Legislação:** As regras do motor são atreladas a uma tabela de vigência (`louos_versions`). Processos iniciados sob a vigência de uma lei antiga serão validados eternamente pelas regras daquela versão, mesmo se a lei mudar posteriormente.
3.  **Decisões Explicáveis (Explainable Logic):** O resultado do motor de regras armazena o snapshot em JSON de todo o caminho lógico percorrido no momento do deferimento ou indeferimento, servindo de lastro jurídico e alimentando o componente React que explica amigavelmente as pendências para o cidadão.
