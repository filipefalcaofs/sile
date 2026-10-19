# HU-133 — Vincular protocolo BAP

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Bloqueada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O vínculo do protocolo BAP com o processo não é obtido: a fonte ligada na aplicação não devolve vínculo.

## História de Usuário
**Como** sistema, **quero** associar o protocolo BAP da Junta ao processo da SEDUR quando a base existir. Sem essa fonte, nenhum número de BAP é inventado.

## Atores e permissões
- `BapRegistry` está ligado a `UnavailableBapRegistry`.
- Não há tela para digitar um BAP fictício no lugar da consulta.

## Fluxo evidenciado
1. `findLinkage` devolve `null`.
2. O comentário do serviço registra que nada entra em espera de BAP por esse retorno: ausência de vínculo não é exceção e não fabrica protocolo.
3. O indeferimento por prazo sem BAP é outra regra de decisão. Não cria o vínculo que esta HU pede.
4. O recebimento REGIN grava o protocolo do envelope. Isso não consulta a base de BAP.

## Regras de negócio evidenciadas
- Sem fonte oficial não há `BapLinkage`.
- A troca futura seria só do binding; o indisponível não simula sucesso.

## Validações
- Não há parsing de protocolo BAP neste registro.

## Critérios de aceite
- Consultar o registro BAP de um processo retorna nulo.
- Nenhum processo ganha número BAP gerado localmente por este serviço.

## Divergências da redação anterior
O texto dizia que receber o BAP liberava o motor. O binding atual não recebe nem grava esse vínculo.

## Evidências
- `app/Services/Regin/UnavailableBapRegistry.php`
- `app/Providers/AppServiceProvider.php` (binding de `BapRegistry`)

## Não evidenciado
- Cliente HTTP ou fila que leia protocolo BAP na Junta.
- Estado `aguardando_bap` alimentado por este registro.
