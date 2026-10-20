# HU-075 — Exibir histórico decisório do imóvel e do CNAE na zona

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Mostrar, na ficha, decisões anteriores do mesmo imóvel e a estatística do CNAE na zona, sem reinventar o motor e sem expor dado pessoal além do que o serviço de precedentes devolve.

## História de Usuário
**Como** analista com `analisar-processos`, **quero** ver precedentes do imóvel e do CNAE na zona, **para** apoiar a análise sem que esse painel decida o processo.

## Atores e permissões
- `GET processos/{processo}/precedentes`: `analisar-processos`.
- A consulta é auditada com o número da revisão vigente.

## Fluxo evidenciado
1. O controller obtém a revisão vigente (o que pode acionar a pré-análise, HU-059) e chama `PrecedentService::forRecord`.
2. A resposta JSON tem `imovel` e `cnae_zona`.
3. O imóvel lista decisões anteriores da mesma inscrição: id da solicitação, protocolo, outcome, data, tipo de serviço e analista, no formato declarado pelo serviço.
4. A zona é lida do snapshot do motor (`por_cnae[].consulta.territorio.zona`). Sem CNAE ou sem zona, a seção do CNAE na zona não calcula estatística.
5. A janela da estatística vem da configuração usada pelo serviço. A consulta é de apoio: não grava decisão nem status.

## Regras de negócio evidenciadas
- Sem zona no snapshot ou sem histórico, o painel degrada sem inventar precedente.
- O painel não é fonte da decisão humana. A decisão continua sendo a ficha finalizada.

## Validações
- Processo inexistente: 404 do route model binding.
- Sem permissão: 403.

## Critérios de aceite
1. Analista recebe o JSON com precedentes do imóvel e, quando há CNAE e zona no snapshot, a estatística da zona.
2. Sem zona, a seção do CNAE na zona não fabrica números.
3. A consulta gera auditoria `precedentes`.
4. Usuário sem `analisar-processos` não acessa o endpoint.

## Divergências da redação anterior
- A redação anterior tratava o histórico como funcionalidade genérica de vários atores. A rota é da ficha de análise e exige `analisar-processos`.

## Evidências
- `app/Http/Controllers/Gestao/PrecedenteController.php`
- `app/Services/Analise/PrecedentService.php`
- `routes/gestao.php`

## Não evidenciado
- Uso do precedente como voto automático ou como bloqueio de deferimento.
- Exibição deste painel no portal do cidadão.
