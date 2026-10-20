# HU-160 — Simular enquadramento da atividade

## Épica
**EP07 — Consulta Prévia de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Exibir, na mesma consulta prévia, o enquadramento de uso devolvido pelo motor da LOUOS.

## História de Usuário
**Como** cidadão que consulta a viabilidade,  
**quero** ver o enquadramento da atividade,  
**para** saber o grupo de uso aplicado pela planilha vigente e o veredito que o motor propagou.

## Atores e permissões
- Não há rota separada de simulação. O enquadramento sai no JSON de `POST /portal/viabilidade/endereco`, `/cnae` e `/inscricao`.
- Acesso público, com o mesmo throttle da consulta.

## Fluxo evidenciado
1. Toda consulta bem-sucedida chama `LouosEnquadramentoService::enquadrar` com CNAE, área, respostas da planilha e, quando existe ponto, o território.
2. O resultado agrega `enquadramento` em `ConsultaViabilidadeResult`. O veredito locacional (`resultado`, `label`, `motivo`) é lido do consolidado desse enquadramento.
3. A tela "Enquadramento de uso" mostra:
   - `identificado`: grupo, subgrupo, código LOUOS e versão da regra, quando vierem preenchidos;
   - `nao_encontrado`: "Sem enquadramento" e o motivo, ou o texto de CNAE sem ramo na planilha vigente;
   - `indisponivel`: selo de indisponível e o motivo, ou "Planilha de tratamento indisponível."
4. A fundamentação legal da consulta junta as referências do enquadramento com as do risco, sem duplicar.

## Regras de negócio evidenciadas
- O orquestrador da consulta não recalcula permitido ou não permitido. Repete o resultado do motor.
- Sem território (consulta por CNAE, ou inscrição degradada), o enquadramento roda mesmo assim e o veredito locacional fica o que o motor devolver sem zona.
- Perguntas do ramo do CNAE são anexadas pelo `TratamentoRamoResolver` e podem voltar na consulta seguinte como `respostas`.

## Validações
- As da entrada usada (endereço, CNAE ou inscrição). Não há validação exclusiva de enquadramento.
- Respostas da planilha, quando enviadas, passam por `RespostaPlanilhaValida`.

## Critérios de aceite
### CA-01 — Enquadramento na consulta
**Dado** uma consulta prévia concluída,  
**Quando** o resultado é exibido,  
**Então** a seção de enquadramento mostra o status devolvido pelo motor (identificado, não encontrado ou indisponível) e o veredito locacional corresponde ao consolidado desse enquadramento.

### CA-02 — Sem endpoint próprio
**Dado** o portal,  
**Quando** se procura uma simulação isolada de enquadramento,  
**Então** ela não existe: o enquadramento só aparece como parte do resultado da consulta.

## Divergências da redação anterior
A redação anterior pedia simular o enquadramento conforme o Quadro 7, como função do sistema. O código não oferece essa simulação isolada. O que a consulta mostra é o enquadramento do `LouosEnquadramentoService` (grupo de uso da planilha vigente), junto com o restante do resultado.

## Evidências
- `app/Services/Viabilidade/ConsultaViabilidadeResult.php`
- `app/Services/Viabilidade/ConsultaViabilidadeService.php`
- `resources/js/components/viabilidade/resultado-viabilidade.tsx`
- `app/Http/Controllers/Portal/ConsultaViabilidadeController.php` (`jsonComPerguntas`)

## Não evidenciado
- Tela ou rota só para simular o Quadro 7.
- Enquadramento calculado fora do motor LOUOS.
