# HU-104 — Integrar com Junta Comercial

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Enviar à JUCEB o parecer da viabilidade já decidida, deferida ou indeferida, e só registrar sucesso quando a Junta confirmar o recebimento.

## História de Usuário
**Como** sistema, **quero** devolver o parecer ao REGIN/JUCEB depois da decisão, **para** a Junta receber o resultado municipal sem que uma falha de rede seja tratada como envio feito.

## Atores e permissões
- Disparo pelo listener `ComunicarResultadoRegin` no evento `ResultadoEmitido`, para deferimento e indeferimento.
- No pedido do analista, o envio corre depois da resposta HTTP, para não segurar a tela. Em console, corre na hora.
- Credenciais e URL ficam nos parâmetros da API REGIN (`ReginIntegrationSettings`). Sem usuário ou senha, a chamada não autentica.

## Fluxo evidenciado
1. O binding em uso é `HttpReginParecerNotifier`, que monta o envelope de resposta (serviço `WsProSol098`, função 110) a partir da decisão imutável e do protocolo externo.
2. `ReginHttpClient` autentica em `/acesso/auth` e envia o parecer com Bearer. Token e senha não entram em log nem em exceção.
3. Status da área: 2 se deferida, 4 caso contrário. A justificativa usa o texto gravado no processo; sem ele, motivo, fundamentação e condicionantes, com teto de 10.000 caracteres. Notas internas do motor (GeoServer, indisponibilidade, encaminhamento à análise) não vão para a Junta.
4. Sucesso é somente o corpo `RECEBIDO_SUCESSO` (texto ou JSON). Qualquer outro resultado lança `ReginUnavailableException`.
5. O listener audita `integracoes` / `regin-parecer` com resultado `sucesso` ou `bloqueado`. A falha não desfaz a decisão.
6. O comando `regin:reenviar-pareceres` reenvia quem tem decisão e ainda não tem `sucesso`. Quem já teve sucesso não é reenviado. Falhas seguidas interrompem a passada (padrão 3). `--dry-run` só lista.

## Regras de negócio evidenciadas
- Não há sucesso fictício: pendência fica com resultado `bloqueado`.
- A decisão já foi gravada antes deste envio. Indisponibilidade da Junta não muda o desfecho.
- Processo sem referência externa de protocolo não compõe o envio esperado deste canal.

## Validações
- Credencial vazia impede a autenticação e a exceção é auditada como bloqueio.
- HTTP sem sucesso ou corpo diferente de `RECEBIDO_SUCESSO` não conta como entregue.

## Critérios de aceite
- Com a Junta confirmando `RECEBIDO_SUCESSO`, a trilha registra `sucesso`.
- Com credencial ausente, timeout ou corpo diferente, a trilha registra `bloqueado` e o processo permanece decidido.
- Reenvio não repete protocolo que já tem `sucesso`.

## Divergências da redação anterior
O texto juntava “conformidade de deferimento por atividade” a esta integração. A regra de deferimento é do motor. Aqui só sai o parecer já gravado. O comentário antigo do listener ainda cita um notificador que sempre falha; o binding atual é o cliente HTTP.

## Evidências
- `app/Services/Regin/HttpReginParecerNotifier.php`
- `app/Services/Regin/ReginHttpClient.php` (`enviarParecer`)
- `app/Listeners/ComunicarResultadoRegin.php`
- `app/Providers/AppServiceProvider.php` (binding de `ReginParecerNotifier`)
- `app/Console/Commands/ReginReenviarPareceresCommand.php`

## Não evidenciado
- Confirmação de homologação desta sessão contra o ambiente da JUCEB. O código exige a confirmação `RECEBIDO_SUCESSO`; esta HU não registra uma chamada feita agora.
- Envio de indeferimento por prazo BAP como vínculo de protocolo (o registro BAP continua sem fonte).
