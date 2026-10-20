# HU-084 — Reprocessar processo

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Refazer, sob demanda, as etapas do motor que ficaram indisponíveis e reavaliar o veredito, parando na primeira etapa que continuar sem serviço.

## História de Usuário
**Como** usuário com `reprocessar-processos`, **quero** pedir o reprocesso de um processo, **para** retomar localização, território e decisão quando o serviço de terceiro voltar.

## Atores e permissões
- `POST processos/{processo}/reprocessar`: `reprocessar-processos`.
- No seeder, a permissão está no conjunto de análise (Analista Servidor, Analista Terceirizado e chefias) e no administrador. O apoio não tem.
- A auditoria grava o ator quando a requisição tem usuário.

## Fluxo evidenciado
1. O serviço busca etapas com situação indisponível, na ordem do enum.
2. Se não há etapa e o processo também não tem nenhuma etapa gravada, e o expresso ainda pode reavaliar, refaz o passe completo.
3. Se não há o que refazer, devolve mensagem: nenhuma etapa indisponível, ou processo já decidido ou análise já assumida.
4. Se a localização pela inscrição continua indisponível, para nessa etapa e a mensagem diz que nova tentativa foi agendada. As etapas seguintes não rodam.
5. Com localização possível, completa o território. Se falta polígono e há ponto, e o expresso pode reavaliar, enfileira `DecidirFluxoExpressoJob` e avisa que o veredito sai em seguida. Caso contrário, reavalia na hora.
6. A auditoria `reprocesso` registra parada, status do processo e se a decisão foi agendada. Resultado `sucesso` ou `parcial`.

## Regras de negócio evidenciadas
- Não inventa veredito quando a localização segue indisponível.
- Processo já decidido ou com análise assumida, sem etapa gravada e sem poder reavaliar, não é reprocessado.
- O reprocesso não é o envio manual à análise (HU-085) nem a redistribuição.

## Validações
- A rota não recebe corpo específico no controller: o processo vem da URL. Sem a permissão, 403.
- Processo inexistente: 404.

## Critérios de aceite
1. Sem `reprocessar-processos`, a rota responde 403.
2. Etapa de localização ainda indisponível interrompe o passe e audita resultado parcial, sem decidir.
3. Etapas disponíveis disparam reavaliação síncrona ou o job do polígono, e a tela recebe a mensagem do resultado.
4. Processo sem etapa pendente e que não pode ser reavaliado não muda o veredito e informa que não há o que reprocessar.

## Divergências da redação anterior
- Não havia redação anterior. O comando agendado `processos:reprocessar-etapas` existe à parte; esta história é o pedido manual na rota do processo.

## Evidências
- `app/Http/Controllers/Gestao/ReprocessarProcessoController.php`
- `app/Services/Motor/ReprocessarEtapasService.php`
- `routes/gestao.php`
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Reprocesso que ignore etapa indisponível e decida mesmo assim.
- Alteração de responsável ou de setor nesta ação.
