# HU-041 — Identificar classificação da via

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Obter o CODLOG da via do imóvel e a classe viária da LOUOS usada pelo Quadro 11A, sem inferir a classe a partir da geometria.

## História de Usuário
**Como** sistema,  
**quero** saber o código do logradouro e a classe oficial da via,  
**para** aplicar as condições de instalação do Quadro 11A somente quando a classe existir.

## Atores e permissões
- Identificação do ponto: `consultar-territorio`.
- A classe é lida pelo sistema no GeoServer. O interruptor é `geoserver_via_classe`.
- A camada local de vias só entra depois de carga WFS (`geo:importar-wfs`, fonte `logradouro:VM_LOGRADOURO`). Até lá o seed deixa a camada `pendente_fonte`.

## Fluxo evidenciado
1. Com camada local de via vigente, `identifyNearest` busca a feição mais próxima dentro do raio em metros. O resultado inclui `distancia_m`.
2. Sem camada vigente, a via local fica `indisponivel`, motivo da base de logradouros pendente no GeoServer, e a distância fica nula.
3. Quando a via local foi identificada, `comClasseViaria` lê `CODLOG` nas propriedades e consulta `GeoServerClassificacaoViariaClient`.
4. A consulta principal é WFS por CODLOG na camada `louos_classificacao_viaria:VM_L_CLASSIF_VIARIA`, atributo `CLASSIFICACAO_VIARIA`. Não é interseção de ponto com linha.
5. Se essa camada não tem feição, o cliente tenta a hierarquia do logradouro (`HIERARQUIA` em `logradouros:VM_LOGRADOURO`). Falha dessa segunda chamada vira `nao_encontrado`, sem derrubar a consulta inteira.
6. `Quadro11aClasseVia::oficializar` aceita só VA I, VA II, VC I, VC II, VE, VL e VP. Hífen e espaço são ignorados na comparação (`VC-II` e `VC II` são a mesma classe). Valor fora da lista não vira classe.
7. Sem CODLOG na via do ponto, `TerritoryService::comCodLogOficial` pode repor o código vindo da inscrição e então buscar a classe. `TerritorioProcessoService::aplicarLogradouroTributario` grava o CODLOG tributário da inscrição e audita quando substitui o da via mais próxima.
8. Com CODLOG no processo, a etapa do motor “via” usa a classe do GeoServer, não o status da busca espacial. A classe identificada também entra em `camadas_incidentes.via`.

## Regras de negócio evidenciadas
- Toggle desligado, CODLOG vazio, GeoServer fora, CODLOG sem linha ou classe fora das sete oficiais não produzem classe inventada. O motivo fica em `classe_via_motivo`.
- O Quadro 11A só roda quando `CLASSE_VIA_LOUOS` está preenchido na via identificada.
- Via mais próxima e logradouro tributário podem divergir. O tributário prevalece quando a inscrição o traz.

## Validações
- CODLOG vazio não consulta o GeoServer.
- Classe bruta que não oficializa permanece sem `CLASSE_VIA_LOUOS`.

## Critérios de aceite
**Dado** que `geoserver_via_classe` está ligado e o CODLOG tem classe entre as sete oficiais,  
**Quando** a via é identificada,  
**Então** `CLASSE_VIA_LOUOS` recebe a grafia oficial e a etapa da via fica ok.

**Dado** que a camada local de vias está pendente e não há CODLOG de inscrição,  
**Quando** o ponto é identificado,  
**Então** a via fica `indisponivel` e o Quadro 11A não recebe classe.

**Dado** que o GeoServer devolve uma classificação fora das sete classes,  
**Quando** o CODLOG é consultado,  
**Então** a classe não é gravada e o motivo registra que o valor está fora do Quadro 11A.

## Divergências da redação anterior
A redação anterior não separava geometria da via, CODLOG e classe da LOUOS, nem citava as sete classes oficiais nem o fallback de hierarquia.

## Evidências
- `TerritoryService::identifyNearest`, `comClasseViaria`, `comCodLogOficial`
- `GeoServerClassificacaoViariaClient`
- `Quadro11aClasseVia`
- `TerritorioProcessoService::registrarClasseViaria` e `aplicarLogradouroTributario`
- `GeoLayerSeeder` (via pendente até a carga WFS)

## Não evidenciado
- Classificação viária calculada pela distância do ponto à linha.
- Quadro 11 separado do Quadro 11A. A classe alimenta só o domínio `louos_quadro11a`.
