# HU-142 — Exibir histórico decisório do imóvel e do CNAE na zona

> **Status: Aceita (2026-06-12)** — melhoria além do legado. Consistência decisória: o analista decide vendo os precedentes, não no vácuo.

## Épica
**EP10 — Análise Técnica SEDUR**

## Objetivo
Exibir na ficha de análise os precedentes relevantes: decisões anteriores no mesmo endereço/inscrição imobiliária e estatística de decisões para o mesmo CNAE na mesma zona.

## História de Usuário
**Como** analista SEDUR,  
**quero** ver o histórico decisório do imóvel e do CNAE na zona,  
**para** decidir com consistência e fundamentar melhor o parecer.

## Contexto de Negócio
Decisões divergentes para casos equivalentes geram insegurança jurídica e questionamentos. O dado já existe no banco de processos; falta apresentá-lo no momento da decisão. O SAPS versiona fichas do mesmo processo, mas não cruza processos distintos do mesmo imóvel ou CNAE.

## Fluxo Principal
1. Ao abrir a ficha de análise (HU-135), o sistema busca processos anteriores: mesma inscrição imobiliária/endereço; mesmo CNAE na mesma zona urbanística.
2. Painel "Precedentes" exibe: processos do imóvel (resultado, data, serviço, analista) e estatística do CNAE na zona (ex.: "últimos 12 meses: 14 deferimentos, 2 indeferimentos"), com link para cada processo.
3. Divergência relevante (ex.: decidir diferente do padrão histórico) pede confirmação simples do analista — sem bloquear.
4. Consulta registrada em auditoria.

## Regras de Negócio
- RN-001: Precedente é informação de apoio — não vincula a decisão; divergir é permitido com a fundamentação habitual.
- RN-002: O painel deve deixar explícita a versão de regras das decisões antigas (regra pode ter mudado — comparar com contexto).
- RN-003: Janela temporal e quantidade de precedentes exibidos parametrizáveis (HU-014).
- RN-004: Acesso restrito a perfis de análise/gestão; dados pessoais minimizados (LGPD).

## Critérios de Aceite — BDD

### CA-01 — Precedentes do imóvel
**Dado** um imóvel com processos anteriores,  
**Quando** o analista abrir a ficha,  
**Então** os processos do mesmo endereço/inscrição devem aparecer com resultado, data e link.

### CA-02 — Estatística do CNAE na zona
**Dado** um CNAE com decisões na mesma zona no período parametrizado,  
**Quando** a ficha abrir,  
**Então** a estatística deferidos × indeferidos deve ser exibida com acesso aos casos.

### CA-03 — Sem precedentes
**Dado** que não há histórico,  
**Quando** a ficha abrir,  
**Então** o painel deve indicar explicitamente "sem precedentes" (não ocultar a seção).

## Dependências
- HU-135 (ficha), HU-082 (consulta), dados acumulados de processos (Fases 8–10).

## Prioridade
Média-alta

## Observações
Na Fase 14, a IA pode resumir os precedentes (HU-117) — esta HU entrega a base determinística primeiro.
