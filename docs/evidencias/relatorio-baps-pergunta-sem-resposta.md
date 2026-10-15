# Relatório — BAPs em análise por pergunta sem resposta

Quarta coleta, 15/10/2026, ambiente Viabiliza (SEDUR), após a correção das perguntas 4 e 5 e o esvaziamento da fila de decisões. Origem: motivo do encaminhamento à análise, conferido contra o XML da JUCEB (`regin_recebimentos.envelope.xml`). Hash SHA-256 de cada XML na planilha.

## Resumo

- **102 processos** na lista (primeira coleta: 113). Saíram 24 e entraram 13.
- Só o grupo C é requerente que seguiu sem escolher atividade. A e B são ausência de dado na JUCEB. O F era falha do Viabiliza, já corrigida e implantada; D e E foram resolvidos.
- Na série 2601…, todos os BAPs sem perguntas têm número até 2601851764 e todos os que trazem perguntas, a partir de 2601852825. O corte segue o número do BAP, não a hora de geração. Exceção: BAP2680155638, fora da série, também sem perguntas.
- Na primeira coleta o solicitante era o mesmo em todos os registros (Fillipe Falcão), o que sugere carga de teste/homologação. O solicitante dos BAPs novos não foi consultado.

## Situação

| Grupo | Situação | Processos | Origem provável |
|---|---|---|---|
| A | A JUCEB não enviou nenhuma pergunta (XML sem GROUPPERGUNTAS) | 56 | JUCEB |
| B | A JUCEB enviou outras perguntas, mas não a pendente (3, 9, 11, 12, 16 ou 18) | 26 | JUCEB |
| C | Pergunta 1: requerente seguiu sem marcar atividade | 20 | Requerente |
| D | Pergunta 1: atividade marcada SIM no XML, motivo ainda cita pergunta 1 | 0 | Viabiliza (resolvido) |
| E | Pergunta 5: resposta com outro enunciado, motivo ainda cita pergunta 5 | 0 | Viabiliza (resolvido) |
| F | Pergunta 5: pergunta 4 respondida SIM no XML, motivo ainda cita a pergunta 5 | 0 | Viabiliza (resolvido) |
| | **Total** | **102** | |

## O que mudou desde a primeira coleta

- **24 saíram da lista:** 18 deferidos (o 865769 e os 5 do grupo F: 853921, 854704, 858106, 865716 e BAB2601859522, além de 12 da coleta anterior), 2 indeferidos (862021 e 862314) e 4 em análise por outro motivo (852181, 852238, 857499 e 865072: fora do fluxo expresso).
- **7 avançaram** da pergunta 5 para a 11 (grupo E: 853153, 856343, 857473, 863772, 865971; grupo F: 859850 e 860087): a 11 não está no XML, então estão no grupo B.
- **13 BAPs novos** entraram: BAB2601866356, BAB2601869910, BAP2601866498, BAP2601866881, BAP2601867302, BAP2601867664, BAP2601868883, BAP2601869072, BAP2601869481, BAP2601869936, BAP2601871151, BAP2601871421, BAP2680155638. O 2680155638 não traz perguntas (grupo A). O 2601867664 e o 2601869481 trazem a pergunta-mãe (2 no CNAE 1091102; 8 no CNAE 9319199) gravada como SIM, e a JUCEB não enviou a pergunta filha (3 e 9; os textos "artesanal" e "atletas" aparecem 0 vezes no XML): grupo B, sem falha do Viabiliza.
- **Grupo F (7): resolvido.** O snapshot grava a pergunta 4 (SIM) e, com a fila esvaziada, 5 dos 7 saíram da lista e 2 (859850 e 860087) avançaram para a pergunta 11, que a JUCEB não enviou.

## O que foi corrigido no Viabiliza

- **Grupo E (pergunta 5):** corrigido no commit 95b55882 (casa a resposta da JUCEB pelo texto da opção e refaz as respostas), com teste. Nenhum BAP do grupo na lista atual.
- **Grupo D (pergunta 1):** os 14 saíram da lista com a correção geral, sem correção específica. Os 2 últimos (865072 e 865769) saíram na coleta atual.
- **Grupo F (pergunta 4):** corrigido no commit 23a62b70 (casa a pergunta Sim/Não reescrita pela JUCEB por semelhança do enunciado), publicado no SEMIT como 0.75.1 e implantado. Testes novos em `ReginProcessoProtocoladorTest` e `TratamentoRamoResolverTest`; as suítes Regin e Tratamento (271 testes) e o Pint passam.
- **Grupos A, B e C:** não são erro do Viabiliza (dado ausente na JUCEB ou requerente sem atividade marcada). A planilha em produção (planilha-20-08-26) foi comparada com a oficial: 3.114 vínculos CNAE-pergunta e 32 perguntas idênticos, nada faltando nem sobrando, e o complemento está completo. As perguntas cobradas no grupo B são exigidas pela planilha.

## Próximos passos

- Levar à JUCEB também as perguntas filhas ausentes do 867664 e do 869481.
- Os 2 indeferidos (862021, 862314): conferir o motivo da decisão automática.
- A e B: levar à JUCEB com a evidência e comparar o hash com o XML original.

## Lista por grupo

### A — A JUCEB não enviou nenhuma pergunta (XML sem GROUPPERGUNTAS) (56)

BAB2601850252, BAB2601851193, BAP2601843931, BAP2601844262, BAP2601844474, BAP2601844794, BAP2601844964, BAP2601845669, BAP2601845740, BAP2601845936, BAP2601846041, BAP2601846175, BAP2601846329, BAP2601846649, BAP2601846862, BAP2601846908, BAP2601847093, BAP2601847317, BAP2601847475, BAP2601847483, BAP2601847672, BAP2601847726, BAP2601847753, BAP2601848001, BAP2601848162, BAP2601848269, BAP2601848293, BAP2601848340, BAP2601848366, BAP2601848571, BAP2601848617, BAP2601848822, BAP2601848856, BAP2601848883, BAP2601849026, BAP2601849115, BAP2601849682, BAP2601849702, BAP2601849739, BAP2601849925, BAP2601850013, BAP2601850047, BAP2601851266, BAP2601851339, BAP2601851390, BAP2601851444, BAP2601851463, BAP2601851497, BAP2601851533, BAP2601851586, BAP2601851614, BAP2601851633, BAP2601851675, BAP2601851722, BAP2601851764, BAP2680155638

### B — A JUCEB enviou outras perguntas, mas não a pendente (3, 9, 11, 12, 16 ou 18) (26)

BAB2601869910, BAP2601853153, BAP2601853913, BAP2601854356, BAP2601855800, BAP2601856101, BAP2601856343, BAP2601857473, BAP2601857871, BAP2601859850, BAP2601860087, BAP2601861735, BAP2601862217, BAP2601862422, BAP2601863772, BAP2601864396, BAP2601864647, BAP2601864763, BAP2601865627, BAP2601865971, BAP2601866498, BAP2601866881, BAP2601867664, BAP2601869072, BAP2601869481, BAP2601871151

### C — Pergunta 1: requerente seguiu sem marcar atividade (20)

BAB2601866356, BAP2601852825, BAP2601852860, BAP2601854071, BAP2601854731, BAP2601854935, BAP2601854943, BAP2601855024, BAP2601859981, BAP2601862071, BAP2601862626, BAP2601862715, BAP2601863321, BAP2601863355, BAP2601863691, BAP2601866152, BAP2601867302, BAP2601868883, BAP2601869936, BAP2601871421

### D — Pergunta 1: atividade marcada SIM no XML, motivo ainda cita pergunta 1 (0)

—

### E — Pergunta 5: resposta com outro enunciado, motivo ainda cita pergunta 5 (0)

—

### F — Pergunta 5: pergunta 4 respondida SIM no XML, motivo ainda cita a pergunta 5 (0)

—

### Saíram da lista (24)

BAB2601857588, BAB2601859522, BAP2601852181, BAP2601852238, BAP2601852817, BAP2601853921, BAP2601854233, BAP2601854704, BAP2601855494, BAP2601855622, BAP2601857499, BAP2601858106, BAP2601858665, BAP2601858746, BAP2601859297, BAP2601859583, BAP2601859753, BAP2601861334, BAP2601862021, BAP2601862314, BAP2601862553, BAP2601865072, BAP2601865716, BAP2601865769
