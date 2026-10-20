# Nomenclatura dos serviços (De x Para)

## Intenção

A SEDUR enviou o documento "Alteração de nomenclatura - Serviços": os nomes dos
eventos que a JUCEB manda (`PEV_NOME_EVENTO`) devem aparecer no Viabiliza com
nomes próprios da SEDUR. A mudança é só visual. Processo, motor de regras,
integração REGIN e gatilhos que leem o código do evento (ex.: 244) não mudam.

A SEDUR precisa trocar os nomes sem depender de deploy, por isso há uma tela de
parametrização.

## Objetivos

- Tabela De x Para editável: nome do evento como a JUCEB envia → nome exibido.
- Carga inicial com as linhas do PDF.
- Evento novo que chegar sem linha na tabela entra sozinho com De = Para, para a
  SEDUR renomear depois.
- Observação 1 do PDF: num processo com vários eventos, só os serviços
  especificados aparecem; os demais do mesmo pedido não são exibidos.

## Não objetivos

- Distinguir Inclusão/Exclusão de atividade ou Revisão de Endereço/Alteração de
  Espaço automaticamente. A JUCEB não manda o estado anterior; cada evento tem
  um nome fixo, escolhido pela SEDUR na tela.
- Mudar o tipo de serviço interno (`viability_service_types`) ou o filtro de
  serviço dos relatórios, que usa só o tipo interno.

## Modelo

Tabela `servico_nomenclaturas` (model `ServicoNomenclatura`):

| coluna | uso |
|---|---|
| `de` | nome do evento como chega (`viability_request_eventos.nome`, já com acento corrigido) |
| `chave` | `de` normalizado (minúsculas, sem acento, espaços colapsados), único — evita duplicar por diferença de caixa/acento/espaço |
| `para` | nome exibido |
| `exibir` | bool: entra na lista de serviços quando o processo tem mais de um evento |
| timestamps | |

`de` não é editável depois de criado (é a chave do mapeamento); `para` e `exibir` são.
Não há exclusão: desligar `exibir` é o caminho para esconder.

### Carga inicial (migration de dados ou seeder chamado pela migration)

Linhas do PDF, todas com `exibir = true`:

| De | Para (inicial) |
|---|---|
| Inscrição de primeiro estabelecimento | Viabilidade de Localização |
| Inscrição dos demais estabelecimentos | Viabilidade de Localização |
| Alteração de atividades econômicas (principal e secundárias) | Revisão de Atividade - Inclusão |
| Alteração de endereço dentro do mesmo município | Revisão de Endereço |
| Alteração de endereço entre estados | Revisão de Endereço |
| Alteração de endereço entre municípios dentro do mesmo estado | Revisão de Endereço |

Depois, os nomes distintos já existentes em `viability_request_eventos` que não
casaram com nenhuma chave entram com De = Para e `exibir = false`.

### Evento novo

Em `ReginEventos::gravar`, para cada evento gravado, garantir a linha
(`firstOrCreate` pela chave) com De = Para e `exibir = false`. O
`regin:preencher-eventos` passa pelo mesmo caminho.

## Regra de exibição (um serviço só: `ServicoNomenclaturaResolver`)

Dado os eventos de um processo:

1. Mapeia cada evento pela chave do nome. Sem linha → usa o próprio nome.
2. Se algum evento tem `exibir = true`, a lista é o `para` desses eventos.
3. Se nenhum tem, a lista é o `para` de todos os eventos.
4. Remove repetidos mantendo a ordem dos eventos.
5. Processo sem eventos continua com o nome do tipo interno (comportamento atual).

O resolver carrega a tabela uma vez por requisição (cache em memória) para não
gerar N+1 nas listagens.

Pontos que passam a usar o resolver (hoje fazem `eventos->pluck('nome')`):
`ProcessoResource`, `CaixaSetorController`, `VistoriaConsultaController`,
`AnalysisRecordController::servicosDoProcesso`.

## Filtro "Serviço"

`servicoOptions` em `CaixaSetorController`, `VistoriaConsultaController` e
`ResultadoExpressoController` passa a listar uma opção por `para` distinto entre
os eventos existentes, com valor `e` + códigos separados por vírgula de todos os
eventos cujo nome resolve para aquele `para`. `ProcessoQueryService::eventoDoServico`
devolve a lista de códigos e o filtro vira `whereIn('codigo', ...)`. Um valor
antigo `e244` continua funcionando (lista de um código). As opções de tipo
interno continuam depois das de evento, como hoje.

## Tela

`/gestao/servicos-nomenclatura`, item "Nomenclatura dos serviços" no menu de
Gestão ao lado de "Tipos de serviço", no mesmo padrão visual de
`gestao/tipos-servico`: lista paginada com busca (De ou Para), filtro por
Exibir, ordenação; modal de criar (De, Para, Exibir) e de editar (Para,
Exibir; De só leitura); ação rápida para ligar/desligar Exibir. Texto curto na
página explicando a regra do Exibir.

Permissão nova `manter-nomenclatura-servicos` no `PermissionCatalog`, grupo
Atendimento. Atribuída aos mesmos perfis que hoje têm `manter-tipos-servico`.

Validação: `de` e `para` obrigatórios, até 255; `de` único pela chave
normalizada (mensagem clara quando já existe).

## Decisões do usuário

- Só o nome exibido muda.
- Evento sem mapeamento entra sozinho com De = Para.
- Os nomes das linhas condicionais ficam a critério da SEDUR, pela tela.

## Decisões minhas (revisáveis)

- Chave pelo nome normalizado, não pelo código: o PDF só traz nomes e o código
  dos eventos não é conhecido aqui (exceto 244).
- Flag `exibir` para cumprir a Observação 1 junto com o cadastro automático, com
  o fallback do passo 3.
- Linhas criadas sozinhas nascem com `exibir = false`.
- Permissão própria em vez de reusar `manter-tipos-servico`.

- Se o nome que a JUCEB envia não bater com o texto do PDF, mesmo depois da
  normalização, o evento ganha uma linha própria (De = Para, `exibir = false`).
  A SEDUR acerta pela tela, editando Para e Exibir. O processo nunca fica sem
  serviço por causa do passo 3.
- O filtro "Serviço" filtra pelo que a JUCEB enviou (códigos), não pelo que
  está visível. Oferece uma opção por `para` distinto entre todos os eventos
  existentes, inclusive os que têm `exibir = false`.
- Escopo de telas: só as que já mostram nome de evento hoje (as listadas acima).
  Os filtros de Processos, Malha fina e Relatórios usam só o tipo interno e
  ficam como estão.
- A permissão entra por migration, no padrão de
  `2026_10_15_130000_cria_permissao_distribuir_qualquer_setor.php`: cria
  `manter-nomenclatura-servicos` e atribui a todo perfil que já tem
  `manter-tipos-servico` no banco. O `RolesAndPermissionsSeeder` acompanha.

## Fica para o construtor

Nomes de classes/rotas, a forma da carga (migration com dados ou seeder), o
layout exato da tela seguindo o padrão existente.
