# HU-102 — Monitorar conformidade LGPD

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Acompanhar, em métricas, consentimento do termo, retenção de logs de acesso e volume de consultas a dado pessoal.

## História de Usuário
**Como** servidor com permissão de monitoramento LGPD, **quero** ver indicadores de conformidade, **para** saber cobertura de aceite e retenção sem abrir dado pessoal na tela.

## Atores e permissões
- Permissão `monitorar-lgpd` em `/gestao/lgpd`.
- A consulta é auditada em `lgpd` / `consulta-painel` e marcada como dado pessoal.

## Fluxo evidenciado
1. `LgpdMonitorService::consentimentos` conta usuários e aceites da versão vigente de `LegalTerm` tipo `lgpd`. Sem termo publicado, informa `sem_termo_publicado` e não inventa versão.
2. `retencao` lê os dias de `retencao.access_logs.dias` e o último evento real `pruning-access-logs` na trilha (`log_name` retencao). Decisões de viabilidade ficam fora desse pruning.
3. `acessosDadoPessoal` agrega a marca `personal_data` da `activity_log`, sem listar o conteúdo.
4. A prop `direitosTitular` declara status `pendente-dpo`: eliminação e anonimização do titular não têm rito nesta tela.

## Regras de negócio evidenciadas
- O painel não exibe nome, CPF, CNPJ nem texto de atividade.
- Percentual de aceite é zero quando não há usuários ou não há termo; não há percentual inventado de conformidade.
- Apagar trilha de decisão não faz parte deste monitoramento.

## Validações
- Não há filtro nem formulário. A rota só lê agregados.

## Critérios de aceite
- Com termo vigente, a tela mostra versão, aceites e pendentes de reaceite.
- Sem termo publicado, a tela declara a ausência em vez de simular aceite.
- A seção de direitos do titular informa que não há eliminação nem anonimização automatizada.
- A abertura do painel gera auditoria de dado pessoal.

## Divergências da redação anterior
O texto pedia “monitorar LGPD” de forma genérica, inclusive fluxos de integração. O código agrega três fontes internas e deixa explícita a pendência do DPO para os direitos do art. 18.

## Evidências
- `app/Http/Controllers/Gestao/LgpdMonitorController.php`
- `app/Services/Lgpd/LgpdMonitorService.php`
- `routes/gestao.php` (grupo `lgpd`)

## Não evidenciado
- Pedido do titular de eliminação, anonimização ou portabilidade.
- Relatório nominativo de quem acessou dado pessoal.
