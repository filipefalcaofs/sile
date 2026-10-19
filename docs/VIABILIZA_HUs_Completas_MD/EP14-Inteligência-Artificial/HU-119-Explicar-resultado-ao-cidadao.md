# HU-119 — Explicar resultado ao cidadão

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Reescrever, em linguagem simples, a decisão já registrada, sem criar desfecho nem reabrir o mérito.

## História de Usuário
**Como** requerente, **quero** uma explicação do resultado do meu protocolo, **para** entender a decisão. Se ainda não houver decisão, não há texto no lugar dela.

## Atores e permissões
- Consulta do protocolo no portal (`ConsultaProtocoloController`), usuário autenticado dono da consulta.
- Portão `features.ia_explicacao` e provedor de texto.
- O parecer oficial (resultado, motivo, fundamentação) é outra prop, lida da decisão, sem IA.

## Fluxo evidenciado
1. A tela só oferece o card se existe `ViabilityDecision` (`temDecisao`).
2. A prop adiada chama `ExplicacaoCidadaoService::processar` e depois lê o ledger.
3. Sem decisão, sem interruptor ou sem provedor: não despacha e não inventa explicação.
4. Com decisão e portão aberto, grava sugestão do tipo `explicacao`, fiel ao que foi registrado. Não altera a decisão.
5. Idempotência por entrada evita reprocessar o mesmo conteúdo.

## Regras de negócio evidenciadas
- A explicação não decide e não substitui o parecer oficial.
- Sem decisão registrada não há o que explicar.

## Validações
- Card ausente quando `decision` não existe.
- Portão de texto conferido no serviço e de novo no job.

## Critérios de aceite
- Protocolo sem decisão não mostra explicação gerada.
- Protocolo decidido, com a função ligada, grava sugestão e mantém o mesmo desfecho.
- Com a função desligada, o parecer oficial continua visível e não aparece texto de IA simulado.

## Divergências da redação anterior
O texto não condicionava a explicação a uma decisão já gravada nem a separava do parecer oficial.

## Evidências
- `app/Services/Ai/ExplicacaoCidadaoService.php`
- `app/Jobs/Ai/ExplicacaoCidadaoJob.php`
- `app/Ai/Agents/ExplicacaoCidadaoAgent.php`
- `app/Http/Controllers/Portal/ConsultaProtocoloController.php`

## Não evidenciado
- Explicação que altere ou crie `ViabilityDecision`.
- Texto ao cidadão em processo ainda sem decisão.
