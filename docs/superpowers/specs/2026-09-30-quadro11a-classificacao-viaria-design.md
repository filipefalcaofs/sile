# Quadro 11A no motor — classificação viária real

**Data:** 2026-09-30
**Status:** aprovado para planejamento
**HUs:** HU-032 (via), HU-041 (condições pela via), HU-018 (cadastro do quadro)

## Problema

O Quadro 11A só é aplicado no simulador REGIN. `ReginProtocoloSimulacaoService::territorioDoSnapshot` grava `CLASSE_VIA_LOUOS` com a via fixa de `protocolos-sedur.json` (sete protocolos). Processo real não tem esse snapshot: a via vem do GIS, sem esse atributo, e o quadro degrada.

`LouosEnquadramentoService::enquadrarCondicoesVia`
procura a classe da via na propriedade `CLASSE_VIA_LOUOS` da feição:

```php
// app/Services/Louos/LouosEnquadramentoService.php:1087
$classe = is_array($propriedades) ? ($propriedades['CLASSE_VIA_LOUOS'] ?? null) : null;
```

Nenhuma camada produz um atributo com esse nome. A camada local de vias vem de
`logradouros:VM_LOGRADOURO`, cujos atributos são `CODLOG`, `NOME_LOGRADOURO` e
`HIERARQUIA`. A busca devolve sempre `null`, o quadro retorna `indisponivel`
com o motivo "pendente SEDUR" e o veredito da via nunca entra na decisão.

O quadro em si está pronto e carregado: `quadro11a-condicoes-via.csv` tem 525
linhas nas 7 classes oficiais (`VA I`, `VA II`, `VC I`, `VC II`, `VE`, `VL`,
`VP`), com `condicoes` em `Sim`, `Não` e
`Objeto de análise particularizada pela CNLU`. O consumidor
(`vereditoQuadro11a`) já sabe indeferir com `Não` e encaminhar à CNLU com `R`.
Falta só o insumo: qual é a classe da via do imóvel.

## Fonte confirmada

Verificado por chamada real ao GeoServer SEDUR em 2026-09-30.

`louos_classificacao_viaria:VM_L_CLASSIF_VIARIA` (alias
`l_classificacao_viaria:VM_L_CLASSIF_VIARIA`) responde com os atributos
`CODLOG`, `LOGRADOURO`, `CLASSIFICACAO_VIARIA`, `OBS`, `INSTITUIDO_POR` e
`ALTERADO_POR`. Amostra real:

| CODLOG | LOGRADOURO | CLASSIFICACAO_VIARIA | OBS |
|---|---|---|---|
| 822 | Avenida Joana Angélica | `VC-I` | - |
| 5502 | Avenida Presidente Costa e Silva | `VA-II` | - |
| 896 | Rua Junqueira Freire | `VC - II` | Da Rua da Independência à Rua da Mangueira |

`sedur:L_CLASSIF_VIARIA` NÃO serve: o atributo de classe se chama `CATEGORIA`
e veio nulo nas três feições. Usar o workspace `louos_classificacao_viaria`.

## Decisões

### 1. O casamento é por CODLOG, não espacial

A camada é de linhas. Um `INTERSECTS` de ponto sobre linha praticamente nunca
casa — foi por isso que a consulta de exemplo só devolveu feições com `bbox`.
O CODLOG já está resolvido no processo antes do enquadramento: sai da via mais
próxima (`TerritoryService::identifyNearest`) ou do logradouro tributário da
inscrição (`TerritorioProcessoService::aplicarLogradouroTributario`). O filtro
é `CQL_FILTER=CODLOG=<n>`.

Consequência honesta: sem CODLOG não há classe, e o quadro continua
`indisponivel` com motivo próprio. Não se infere classe pela geometria.

### 2. A classe entra como propriedade da via, não como novo parâmetro do motor

O enriquecimento acontece em `TerritoryService::identifyNearest`: identificada
a via, o serviço consulta a classificação pelo CODLOG e acrescenta
`CLASSE_VIA_LOUOS` às propriedades da feição. `LouosEnquadramentoService` fica
intocado — ele já lê essa chave. Isso mantém o motor com uma única porta de
entrada para território e evita mudar a assinatura de `EnquadramentoInput`.

### 3. Normalização para a grafia da lei

O GeoServer devolve `VC-I`, `VA-II`, `VC - II`. O cadastro do Quadro 11A e o
catálogo de vias (`ViaSeeder`, alimentado pelos valores distintos de
`classe_via` da versão vigente) usam `VC I`, `VA II`, `VC II`. Sem normalizar,
`buscarCondicaoVia` não acha a linha e o quadro cai em `nao_encontrado` —
falha silenciosa disfarçada de "sem regra".

Novo `App\Support\Louos\Quadro11aClasseVia`, espelhando `Quadro10Zona`:
lista oficial das 7 classes, `oficializar()` que normaliza hífen, espaços
múltiplos e caixa. Valor fora da lista devolve `null` — classe inventada não é
promovida a classe da lei.

### 4. Pendência SEDUR vira configuração, não bloqueio

A documentação registra a dúvida: a classe do Quadro 11A é o
`CLASSIFICACAO_VIARIA` desta camada ou o `HIERARQUIA` do logradouro? No recorte
verificado os dois coincidiram, mas são cadastros distintos e isso não prova
equivalência na cidade inteira.

`type_name` e `atributo` ficam em `config/sile.php` (constante técnica,
precedente [02-02]). Trocar a fonte para `HIERARQUIA` da camada de logradouro
é mudança de config, não de código. A pendência continua registrada; o motor
deixa de ficar parado por causa dela.

### 5. Interruptor próprio

`features.geoserver_via_classe`, na aba GeoServer do catálogo de integrações,
junto de `geoserver_zona` e `geoserver_territorio`. Desligado, a classe é
`null` e o quadro degrada com motivo — nunca decisão sem o dado.

## Degradação (sem fachada)

| Situação | `quadro11a.status` | Motivo |
|---|---|---|
| Via não identificada | `indisponivel` | motivo da via |
| Via sem CODLOG | `indisponivel` | `louos.motivo.via_sem_codlog` (novo) |
| Interruptor desligado | `indisponivel` | `louos.motivo.via_classe_desligada` (novo) |
| GeoServer fora / fora do contrato | `indisponivel` | `louos.motivo.via_classe_indisponivel` (novo) |
| CODLOG sem linha na camada | `indisponivel` | `louos.motivo.via_sem_atributo` (existente) |
| Classe fora das 7 oficiais | `indisponivel` | `louos.motivo.via_classe_desconhecida` (novo) |
| Classe válida, sem linha no quadro | `nao_encontrado` | `louos.motivo.via_sem_regra` (existente) |
| Classe válida com regra | `identificado` | template `louos.template.quadro_via` |

Nenhum caminho produz deferimento ou indeferimento sem a classe real.

## Critérios de aceite

- **CA-01** Via com CODLOG 822 e camada respondendo `VC-I` produz
  `quadro11a.classe_via = 'VC I'` e `status = identificado`.
- **CA-02** `VC - II` (com espaços) normaliza para `VC II`.
- **CA-03** Classe cuja linha do quadro tem `Não` consolida o veredito em
  `nao_permitido`, com a fundamentação do Quadro 11-A.
- **CA-04** Classe cuja linha tem `Objeto de análise particularizada pela CNLU`
  consolida em `pendente`.
- **CA-05** Interruptor desligado, GeoServer fora, CODLOG ausente e classe
  desconhecida devolvem `indisponivel` com o motivo específico, sem veredito.
- **CA-06** Uma consulta por CODLOG por execução (memo), como nos demais
  clientes WFS.
- **CA-07** A consulta é auditada com CODLOG, classe bruta, classe oficial e
  resultado (RN-002).
- **CA-08** Comando de verificação devolve a classe de um CODLOG contra o
  GeoServer real, para evidência de homologação.

## Fora de escopo

- Trocar a fonte para `HIERARQUIA` (config, quando a SEDUR confirmar).
- Importar a camada de classificação para o banco local. A consulta é por
  CODLOG, pontual e memoizada — não justifica carga.
- Revisar o conteúdo do Quadro 11A: o CSV oficial já está carregado.
