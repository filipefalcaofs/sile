# HU-048 — Aplicar Quadro 11A

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Ler as condições de instalação do Quadro 11A para a classe viária oficial e o grupo de uso, e refletir Não, R e texto de análise da CNLU no consolidado.

## História de Usuário
**Como** sistema,  
**quero** aplicar o Quadro 11A quando a via tiver classe oficial,  
**para** vedar, encaminhar à CNLU ou registrar condição de instalação sem inventar a classe da via.

## Atores e permissões
- Manutenção do quadro: rascunho do domínio `louos_quadro11a`, `manter-louos`, publicação por quatro olhos.
- Classes aceitas no rascunho precisam existir e estar ativas no cadastro de vias.
- A classe do imóvel vem do território (HU-041), não de escolha do analista nesta etapa.

## Fluxo evidenciado
1. Sem via identificada, a dimensão fica `indisponivel`.
2. Via identificada sem `CLASSE_VIA_LOUOS` fica `indisponivel`, com `classe_via_motivo` quando existir. A classe não é inferida da geometria.
3. Com a classe, resolve a versão do domínio. Sem versão ou sem linha: `nao_encontrado`, e a classe consultada permanece no resultado.
4. A busca prefere subgrupo, depois grupo, depois linha da classe com grupo vazio.
5. Dimensão identificada devolve classe, lista `condicoes` e base legal. O motivo diz que o quadro condiciona a instalação e não autoriza o uso.
6. No consolidado, depois do Quadro 10 permitido ou condicionado:
   - token `nao` (também `não`) torna o resultado `nao_permitido`, motivo de via vedada, sem condicionantes;
   - token `r`, ou texto que começa com “objeto de analise particularizada pela cnlu”, torna o resultado `pendente`, motivo de via CNLU;
   - um único `sim` não vira condicionante;
   - outros textos da lista, sem `nao` e sem `r`, viram condicionante do tipo `via`.
7. Classes oficiais reconhecidas na leitura do GeoServer: VA I, VA II, VC I, VC II, VE, VL e VP.

## Regras de negócio evidenciadas
- Quadro 11A não autoriza o uso. A permissão da zona continua sendo o Quadro 10.
- Via vedada indefere no expresso pelo consolidado `nao_permitido`, antes do gate de alto risco.
- Via sem atributo não vira permitido nem não permitido por omissão: o consolidado segue a precedência. Se o Quadro 10 identificou permissão e o 11A está indisponível, o veredito da via não entra (`vereditoQuadro11a` devolve nulo quando o status não é identificado).

## Validações
- Só entram tokens normalizados `nao`, `r` e `sim`, mais o prefixo do texto de análise particularizada da CNLU.
- Classe fora das sete oficiais não chega como `CLASSE_VIA_LOUOS`.

## Critérios de aceite
**Dado** que a via tem classe oficial e existe linha na versão vigente,  
**Quando** o Quadro 11A é aplicado,  
**Então** a dimensão fica `identificado` com a classe, as condições e a base legal.

**Dado** que alguma condição normaliza para `nao`,  
**Quando** o parecer é consolidado e o Quadro 10 não proibiu antes,  
**Então** o resultado é `nao_permitido`.

**Dado** que alguma condição normaliza para `r` ou para o texto de análise da CNLU,  
**Quando** o parecer é consolidado,  
**Então** o resultado é `pendente`.

**Dado** que a via não tem classe,  
**Quando** o quadro é aplicado,  
**Então** a dimensão fica `indisponivel` e nenhuma classe é criada.

## Divergências da redação anterior
A redação anterior não distinguia Quadro 11 de 11A nem os efeitos Não, R e Sim implementados sobre a lista de condições.

## Evidências
- `LouosEnquadramentoService::enquadrarCondicoesVia`, `vereditoQuadro11a`, `tokensVia`
- `LouosQuadro11CondicaoVia`
- `Quadro11aClasseVia`
- `RuleDomain::LouosQuadro11a`

## Não evidenciado
- Quadro 11 em paralelo ao 11A.
- Inferência da classe viária pela distância do ponto à via.
