# Consulta prévia completa e confiável — design

## Intenção

A consulta prévia do portal serve para o cidadão saber, antes de protocolar, se a viabilidade
dele tende a ser bem-sucedida. Hoje ela pede só endereço, CNAE e área, e pode devolver um
veredito que parece firme mesmo quando faltam dados. O objetivo é que **todo resultado
mostrado como conclusivo seja confiável**: só há veredito quando o local foi resolvido e todos
os dados que o motor usa foram informados. Quando não for possível, a tela diz o que falta ou
que não foi possível concluir, sem inventar parecer.

## O que a consulta é (e não é)

- É uma simulação **informativa**. Não cria processo, não gera número, não dá viabilidade a
  ninguém e não vincula a decisão. O protocolo é feito pela JUCEB; a decisão oficial sai da
  análise do processo.
- A tela **não orienta o próximo passo** (não manda procurar a SEDUR nem a JUCEB). Só informa o
  resultado e o aviso fixo de que é uma simulação.
- O log existente continua: auditoria RN-002 para toda consulta e snapshot imutável no
  histórico pessoal (`ViabilityQuery`) quando o usuário está autenticado. Serve de comprovação
  de que o usuário consultou. Não muda de natureza.

## Objetivos

1. Resultado em três estados explícitos de confiabilidade (abaixo).
2. Coletar na tela todos os dados que o motor realmente usa para decidir.
3. Nenhum veredito (permitido / permitido com condicionante / não permitido) sem local resolvido
   e sem dados completos.
4. O estado de confiabilidade e o que faltou entram no snapshot do histórico e na auditoria.

## Não objetivos

- Gravar processo, protocolar, reservar, numerar ou emitir qualquer documento.
- Orientar o cidadão a procurar órgão ou a protocolar.
- Mudar as regras dos Quadros 10, 11A, risco ou planilha. O motor continua sendo a única
  fonte do veredito; esta mudança só controla entrada e apresentação.

## Anti-objetivos (seria falha mesmo que funcione)

- Mostrar "permitido" quando faltou dado ou a zona não foi identificada.
- Pedir ao cidadão um dado que não muda o resultado (pergunta de fachada).
- Tratar "não foi possível concluir" como aprovação ou como reprovação.

## Estados de confiabilidade

| Estado | Quando | O que a tela mostra |
|---|---|---|
| **Conclusiva** | Local resolvido (zona identificada; via quando o Quadro 11A exigir), área informada, todas as perguntas visíveis da planilha respondidas, demais dados exigidos pelo CNAE informados | O veredito do motor, base legal, condicionantes e risco |
| **Incompleta** | Falta dado de entrada que o cidadão pode fornecer | Lista do que falta; sem veredito |
| **Inconclusiva** | O motor degradou: zona ou via não identificada, base territorial indisponível, CNAE sem ramo resolvido, consulta sem local | Diz que a consulta não conseguiu concluir e por quê (motivo vindo do motor); sem veredito. Não orienta próximo passo |

O estado é derivado do que o motor devolve (`veredito_locacional.resultado = pendente`, avisos,
perguntas sem resposta), nunca decidido por um critério paralelo. A regra "sem zona → pendente"
continua sendo do motor (HU-044).

## Entrada

- **Caminho principal: endereço.** Sem local não há resposta sobre deferimento.
- Dados coletados, todos consumidos hoje pelo motor:
  - CNAE principal, área (m²) e perguntas da planilha — já existem.
  - **Tipo de imóvel** (`tipoImovel`), **categoria da empresa** (`categoriaEmpresa`) e
    **vagas declaradas** (estacionamento e carga/descarga, HU-042) — o motor aceita, mas o
    request e a tela do portal ainda não enviam.
- Área passa a ser **obrigatória** na consulta por endereço (hoje é opcional e o ramo da
  planilha degrada sem ela).
- **CNAEs secundários ficam fora desta entrega.** `EnquadramentoInput` tem o campo, mas
  `LouosEnquadramentoService` não o avalia; pedi-lo na tela seria pergunta de fachada. Entra se
  o motor passar a avaliá-los (decisão posterior).

## Aba CNAE e aba Inscrição imobiliária

Decisão minha, a confirmar na leitura do documento:

- **CNAE sem endereço** deixa de poder terminar em estado conclusivo. Fica como consulta de
  risco e exigências gerais, sempre rotulada como "sem análise do local" (estado inconclusivo
  quanto ao veredito locacional).
- **Inscrição imobiliária** continua degradando para CNAE enquanto o contrato
  `PropertyRegistryLookup` estiver pendente da SEDUR, com o mesmo rótulo; não inventa ponto.

## Resultado e interface (comportamento)

- O resultado exibe o estado de confiabilidade em destaque, antes do veredito.
- Aviso fixo e curto em todo resultado: é uma simulação informativa, não vincula a decisão
  oficial, que sai da análise do processo.
- Quando incompleta, o formulário aponta os campos faltantes (inclusive perguntas da planilha
  ainda sem resposta) em vez de mostrar resultado parcial.
- Layout e texto exatos ficam para o builder, seguindo o padrão visual atual de
  `portal/viabilidade/consulta.tsx` e o catálogo de textos (`DecisionTextCatalog`).

## Log

- `registrarHistorico` continua gravando só para usuário autenticado; consulta anônima segue
  apenas na auditoria RN-002 (comportamento atual, mantido).
- O snapshot e a auditoria passam a incluir `confiabilidade` (conclusiva / incompleta /
  inconclusiva) e a lista do que faltou ou do motivo da inconclusão.
- Consulta que termina em erro honesto (endereço não localizado, serviço indisponível) continua
  sem gerar histórico.

## Restrições

- Laravel 13 / Inertia v3 / React 19, PHPUnit; seguir a estrutura existente (serviço
  `ConsultaViabilidadeService`, `ConsultaViabilidadeResult`, controller do portal, requests).
- Toda decisão de veredito permanece nos motores; o serviço só compõe e propaga.
- Textos novos no catálogo de textos existente, não hardcoded quando houver precedente.
- Sem novas dependências.

## Testes esperados

- Os três estados, incluindo: zona proibida, permitido com condicionante por vaga, endereço sem
  zona identificada (inconclusivo), área ausente (incompleta), pergunta da planilha sem resposta
  (incompleta), CNAE sem endereço (nunca conclusivo).
- Que nenhum caminho produza veredito quando o estado não é conclusivo.
- Snapshot do histórico e auditoria com `confiabilidade`.

## Deixado ao builder

- Onde derivar o estado (resultado do serviço vs. camada de apresentação), desde que a regra
  venha do motor.
- Forma exata dos campos novos (tipo de imóvel, categoria, vagas) e reaproveitamento de
  componentes e validações existentes.
- Migração do histórico, se a coluna nova for necessária no snapshot (pode ir só no JSON
  `result`, que já existe).

## Decisões tomadas após a revisão (minhas, a conferir)

- **Incompleta** tem dois caminhos: dado obrigatório que o formulário já conhece (área, campos
  fixos) é validado antes do motor (422, sem log); pergunta da planilha só é conhecida depois
  da primeira passada (o endpoint já devolve `perguntas`), então volta como estado
  `incompleta` no resultado. Incompleta entra na auditoria RN-002 mas **não** gera snapshot
  no histórico; conclusiva e inconclusiva geram (usuário autenticado).
- **Linha entre inconclusiva e erro:** zona/via não identificada e consulta sem local são
  degradações do motor (inconclusiva, com snapshot). Endereço não localizado, fora de Salvador
  e geocoder indisponível continuam erro honesto, sem snapshot.
- **Consulta anônima:** recebe os mesmos estados e campos; a auditoria leva `confiabilidade` e
  o que faltou.
- **Outros chamadores** de `ConsultaViabilidadeService` (simulação pré-protocolo e REGIN, via
  `consultarPorPontoConhecido` / `consultarComTerritorio`) não mudam de comportamento: a
  obrigatoriedade de área vale só nos endpoints públicos do portal.
- **Dados "exigidos pelo CNAE":** só se pede o que muda o resultado. Perguntas da planilha vêm
  de `TratamentoRamoResolver::perguntasExibidas`; vagas só aparecem quando a regra da via
  (Quadro 11A) as exigir; tipo de imóvel e categoria seguem o catálogo/enum já usado pelo
  motor (`TipoImovelCatalog`), reaproveitando as opções do formulário interno.
- A aba CNAE: no motor, sem território o Quadro 10 fica indisponível e o consolidado já vem
  `pendente`, então o estado inconclusivo sai do próprio motor.
