# HU-141 — Simular viabilidade antes de protocolar

> **Status: Aceita (2026-06-12)** — melhoria além do legado. Conecta a consulta prévia (EP07) ao formulário de solicitação (EP08) para prevenir indeferimentos.

## Épica
**EP08 — Solicitação de Viabilidade**

## Objetivo
Executar a simulação de viabilidade (motores reais do EP07) com os dados já digitados no formulário, antes do protocolo, alertando o requerente sobre tendência de indeferimento e seus motivos.

## História de Usuário
**Como** requerente,  
**quero** ver a simulação da viabilidade antes de protocolar,  
**para** corrigir atividade, área ou local antes de gastar protocolo, BAP e fila de análise.

## Contexto de Negócio
Parte relevante dos indeferimentos do legado é evitável: atividade incompatível com a zona, área acima da faixa do enquadramento, condicionante não atendida. Antecipar o resultado reduz indeferimento, retrabalho do analista e convites/pendências — e melhora a experiência do empreendedor.

## Fluxo Principal
1. Ao concluir as etapas de imóvel, área e atividades do formulário, o sistema executa a simulação (motor LOUOS + risco + condicionantes) com os dados informados.
2. O resultado aparece como etapa do wizard: tendência (viável / viável com condições / tende a indeferimento), por CNAE, com motivos e fundamentação em linguagem simples.
3. Em tendência de indeferimento, o sistema sugere o que revisar (ex.: "área informada excede a faixa permitida para esta atividade nesta zona").
4. O requerente pode ajustar os dados e re-simular, ou prosseguir mesmo assim (a simulação não bloqueia o protocolo).
5. A simulação e a escolha do requerente são registradas no processo (insumo para o analista e para métricas).

## Regras de Negócio
- RN-001: A simulação usa os motores reais (mesma versão de regras do fluxo oficial) — nunca lógica paralela.
- RN-002: A simulação **não bloqueia** o protocolo: direito de petição preservado; o resultado é orientativo.
- RN-003: O resultado simulado e a versão das regras ficam registrados no processo; o analista vê que o requerente prosseguiu ciente.
- RN-004: Linguagem do resultado adequada ao cidadão (sem jargão), com fundamentação legal disponível em detalhe expansível.
- RN-005: Funcionalidade com feature toggle administrável (HU-014); desativação degrada para fluxo sem simulação, sem falha.

## Critérios de Aceite — BDD

### CA-01 — Simulação no wizard
**Dado** que o requerente preencheu imóvel, área e atividades,  
**Quando** avançar para a etapa de revisão,  
**Então** o sistema deve exibir a tendência por CNAE com motivos, executando os motores reais.

### CA-02 — Não bloqueio
**Dado** um resultado com tendência de indeferimento,  
**Quando** o requerente optar por prosseguir,  
**Então** o protocolo deve ser permitido e a ciência registrada.

### CA-03 — Auditoria
**Dado** uma simulação executada,  
**Quando** o processo for protocolado,  
**Então** resultado simulado, versão das regras e escolha do requerente devem constar do processo.

## Dependências
- EP07 (HU-054 a HU-059 — simulação), HU-061 a HU-065 (formulário), HU-014 (toggle).

## Prioridade
Alta

## Observações
No fluxo Regin, aplicar na etapa do formulário hospedado pelo Simplifica (`ps001_Regin` equivalente); no portal direto, como etapa do wizard. Métrica de sucesso: queda da taxa de indeferimento (HU-128) e dos convites por dado incorreto.
