# Ficha de Análise e Layout — Parte 2 (SEDUR)

Data: 2026-10-20
Origem: documento "Solicitações de Alterações - Viabiliza (Ficha de Análise e Layout) - Parte 2", 19 itens.
Branch base: `feat/portal-servicos-navegacao` (v0.87.5).

## Intenção

As analistas da SEDUR usam a Ficha de Análise e as caixas (entrada, setor, vistoria) o dia todo. O pedido tem três motivos:

- a ficha ficou longa e cheia de informação do motor que elas não usam na decisão;
- faltam atalhos do trabalho real: encaminhar para a chefia de dentro da ficha, sugestões da biblioteca, parecer maior;
- algumas caixas mostram dados errados ou alheios: contadores, aba Analisados, vistoriador vendo processo dos colegas.

Há ainda um erro em produção: o 422 ao colocar em convite.

Sucesso é a analista abrir a ficha e ver só o que precisa para decidir, e cada caixa mostrar exatamente os processos dela.

## Situação de partida

O diagnóstico de 2026-10-20 apontou:

| Situação | Itens |
|---|---|
| Já atendidos, sem mudança | 1, 5 |
| Parciais | 3, 9, 14 |
| Não iniciados | os demais |

Os itens 1 e 5 ficam fora deste trabalho. No item 1, a SEDUR decidiu manter o título e o texto atuais do modal.

## Decisões tomadas com o usuário

- **Item 6:** "Fundamentação legal" e "Condicionantes" saem da justificativa **também no texto enviado à JUCEB** (`JUSTIFICATIVA_ANALISE`), não só da tela.
- **Item 12:**
  - Tipo e Validade aparecem em **todas as telas** que usam o formulário de conclusão: ficha, detalhe do processo e malha fina.
  - O tipo e a validade ficam gravados na decisão.
  - Aparecem no **documento da TVL**, no **portal do requerente** e valem para o **controle de renovação**.
  - **Pré-operacional pode ser renovado** como o Provisório; Deferido (definitivo) não.
- **Item 14b:** "Encaminhar para Chefia" manda para a aba Para conclusão **sem escolher pessoa**.
- **Item 3:** a aba Analisados conta **pelo histórico de tramitação** da analista, mesmo que outra pessoa mexa na ficha depois.
- **Item 19:** os cards se sobrepõem. "Aguardando análise" mostra o total da caixa; "Em análise" mostra o subconjunto com ficha aberta.
- **Item 10:** tirar o indicador de confiança da IA **dos dois lugares**: a minuta de parecer e o selo do card de justificativa por IA.

## O que muda, por bloco

### Bloco A — Ficha de Análise: limpeza e layout
Arquivo: `resources/js/pages/gestao/ficha-analise/show.tsx`.

- **Item 7:** remover o quadro "Por que não seguiu o fluxo expresso" (≈1918-1926). O campo `motivo_fora_do_expresso` continua no backend e é usado em outros lugares.
- **Item 10:** remover " · confiança …" da minuta (≈3279-3282) e o selo "Confiança" do card de justificativa (≈3383-3387). Retirar `confiancaLabel` se ficar sem uso.
- **Item 11:** a síntese do topo (`SinteseLeitura` / `sintese.ts`) passa a mostrar **só** "Deferido" ou "Indeferido", e **só** quando o processo está concluído (`status` deferida/indeferida).
  - Nos demais estados o bloco não aparece.
  - Saem as linhas por CNAE, os quadros, os motivos da fila e o link "Ver o parecer completo".
  - Rótulo masculino, como o cliente escreveu.
- **Item 13:** remover o card "Classificação de Risco e Fundamentação Legal" (≈2080-2205) e o código que ficar sem uso.
- **Item 18:** mover "Camadas do imóvel" (`CamadasImovelLista`, hoje no card da esquerda, ≈1654-1665) para a coluna da direita, logo abaixo do mapa do card Polígono. Ajustar a descrição do card da esquerda, que cita "camadas geográficas".
- **Item 2 (polígono sem contorno):** a causa não foi reproduzida. O mais provável é o zoom fixo em 17 sem enquadramento: o contorno de um lote pequeno fica escondido sob o ícone do marcador. Correções:
  - `MapImovel` (`resources/js/components/geo/map-imovel.tsx`) passa a enquadrar a geometria (`fitBounds` com padding), como já faz `map-poligono.tsx` da vistoria;
  - `centroideDoPoligono` (`show.tsx:440`) passa a tratar MultiPolygon, ou é trocado por `centroDaGeometria`;
  - os `<GeoJSON>` ganham `key` derivada do conteúdo, para redesenhar quando a geometria mudar.
  - **Validação obrigatória:** antes de dar como resolvido, conferir num processo real com polígono (prop `localizacao` da página) que o contorno aparece.
  - **Escolha do implementador:** conferir na base local, se houver processo com polígono. O banco do OKD não é acessível daqui. Se não houver dado local, o item sai como "correção provável" e a SEDUR confirma na homologação. Isso é dito na entrega e não bloqueia o bloco.
- **Item 14a:** botão "Salvar Ficha" com fundo laranja. Usar o token `--color-orange-500`, via variante nova do `Button` ou classe local.
- **Item 17:** o campo do parecer técnico pode ser ampliado.
  - Tirar `resize-none` e o teto de 160px **só para o parecer**, permitindo redimensionar na vertical.
  - Incluir um botão "Expandir" que abre o parecer num modal grande, editando o mesmo valor.
  - Os modais de convite e vistoria, que usam o mesmo `Textarea` local, ficam como estão.
- **Item 16:** sugestões da biblioteca ao digitar no campo "Adicionar condicionante (texto livre)".
  - Fonte: os textos-padrão já carregados na ficha (`textosPadrao`), preferindo a categoria de condicionante, se existir.
  - Casamento por termo, sem diferenciar acentos nem maiúsculas, a partir de 3 letras.
  - A lista aparece abaixo do campo, navegável por teclado (combobox acessível).
  - Selecionar uma sugestão chama o mesmo `adicionarCondicao`.
  - Ajustar o texto da tela que cita uma "busca" inexistente (≈2269-2270).
- **Item 9 (Precedentes):** os processos decididos (deferidos e indeferidos) usam o mesmo formato dos que estão em análise.
  - Formato: BAP em destaque + VIA ao lado, em link que abre **em nova aba** (`target="_blank" rel="noopener noreferrer"`).
  - Sai o cartão "serviço · analista · data".
  - O resultado continua visível como uma marca pequena colorida ao lado (verde deferido, vermelho indeferido). **Essa marca é escolha do implementador**, não pedido do cliente.
  - Backend: `PostgisPrecedentRepository` passa a devolver `external_reference` (BAP). Atualizar o contrato, o fake de teste e o tipo `PrecedenteImovel`.
  - Excluir o próprio processo da lista de decididos.

### Bloco B — Conclusão de análise com Tipo e Validade (item 12)
- **Formulário** `resources/js/components/analise/conclusao-analise-form.tsx`:
  - com Resultado = Deferido, aparece **Tipo**: Deferido / Provisório / Pré-operacional, obrigatório;
  - com Tipo Provisório ou Pré-operacional, aparece **Data da Validade**: 6 meses / 1 ano / 2 anos, obrigatório;
  - com outro resultado, os campos somem e não são enviados.
- **Validação:** `ConcluirAnaliseRequest`, com `required_if` em cascata.
- **Modelo:**
  - ampliar `TvlKind` com `PreOperacional` ('pre_operacional', rótulo "Pré-operacional");
  - mapear Deferido → `Definitiva`, Provisório → `Provisoria`, Pré-operacional → `PreOperacional`;
  - `tvl_valid_until` = data da conclusão + prazo escolhido; Definitiva fica sem validade.
  - Colunas: `viability_decisions.tvl_kind` e `tvl_valid_until`, que já existem. Conferir se `tvl_kind` é string ou enum de banco; se for enum de banco, criar migration.
- **Gravação:** `ConclusaoAnaliseService` grava tipo e validade nos dois caminhos, conclusão pela ficha e `revisarConclusao`, para **qualquer** processo, não só renovação.
  - O fluxo de renovação (`ProcessoDecisaoController` / diálogo "Decidir processo") continua funcionando com Provisória/Definitiva e data livre.
- **Processo já deferido** (decisão do usuário): na malha fina ou revisão, escolher Deferido num processo que já está deferido, por exemplo pelo expresso, **atualiza o tipo e a validade na decisão existente**.
  - A validade conta a partir da data dessa conclusão.
  - A troca é registrada na auditoria e na linha do tempo.
  - Hoje `ConclusaoAnaliseService::concluir` não faz nada quando o status é o mesmo, então é preciso um caminho próprio para esse caso.
  - A validação também entra no `ConcluirMalhaFinaRequest`.
- **Processo de renovação** (decisão do usuário): o formulário de conclusão **não** mostra Tipo e Validade. Continua valendo o diálogo "Decidir processo", com Provisória/Definitiva e data livre, sem mudança.
- **Renovação:** `RenovacaoElegibilidadeService` passa a aceitar `Provisoria` **e** `PreOperacional`. A mensagem de bloqueio da definitiva continua.
- **Documento da TVL** (`resources/views/tvl/documento.blade.php`): mostrar o tipo e, quando houver, "Válido até dd/mm/aaaa".
- **Portal:** onde o requerente vê o resultado do processo deferido, mostrar o tipo e a validade. `Portal/RenovacaoController` já lê os dois.

### Bloco C — Caixas e encaminhamento
- **Item 14b, "Encaminhar para Chefia" na ficha:**
  - Novo botão no card Ações, com confirmação, que chama `POST /gestao/processos/{id}/status-analise` com `status=para_conclusao` e sem destinatário.
  - Aparece só quando a transição é permitida (`analysis_status` atual = `em_analise` e permissão `analisar-processos`). A ficha precisa passar a receber `analysis_status` e as próximas transições.
  - Após o envio, a mensagem confirma que o processo foi para a aba Para conclusão e a tela volta para a Caixa de entrada.
  - Não exige ficha salva nem finalizada.
- **Item 3, aba Analisados** (`ProcessoQueryService::analisados`):
  - Além dos critérios atuais (ficha finalizada por ela, decisão tomada por ela), incluir os processos com `analysis_status_transitions.actor_user_id = usuário` e `to_status` em `para_conclusao`, `encaminhado` ou `analise_concluida`.
  - **Escolha do implementador:** `encaminhado` só conta quando a origem **não** é `para_distribuir`. Isso evita que a distribuição feita por apoio ou chefia encha a aba Analisados de quem distribuiu.
  - O período de 7/30/90 dias usa a data da transição.
  - Sem duplicar processo.
- **Item 15:** esconder o filtro "Analista" só na Caixa de entrada.
  - Usar uma prop no `processo-filtros.tsx`; as caixas do setor e de vistoria mantêm o filtro.
  - A fila deixa de enviar `analistaOptions` e ignora a chave `analista`.
- **Item 19, cards da Caixa de entrada** (`ProcessoQueryService::contadores`):
  - "Aguardando análise" = total de processos da caixa (mesma base da lista da fila);
  - "Em análise" = os que têm ficha de análise aberta por alguém (`analysisRecords` com `analyst_user_id` não nulo). A pré-análise do motor nasce sem analista e não conta.
- **Item 4, vistoriador só vê o que é dele:**
  - Quem não tem `distribuir-processos` vê, na caixa de Vistoria (lista, cards e abas), só os processos atribuídos a ele (responsável ou vistoriador da ficha), seguindo o padrão de `ProcessoQueryService::escopo`.
  - A aba "Para distribuir" some para ele.
  - A mesma regra vale no acesso direto: capa de consulta, PDF, abertura e visualização da ficha de vistoria e download de anexo. O acesso alheio retorna 403.
  - **Escolha do implementador:** no acesso direto, também pode entrar **quem foi analista do processo**: autor de ficha de análise do processo ou ator de alguma transição dele. Assim a analista que mandou o processo para a vistoria continua vendo a ficha de vistoria e os anexos na tramitação (commit 6582ccf9).
  - Quem distribui continua vendo tudo, como hoje.

### Bloco D — Justificativa e erro do convite
- **Item 6:** `HttpReginParecerNotifier::justificativaPara` deixa de anexar "Fundamentação legal: …" e "Condicionantes: …".
  - A justificativa passa a ser o motivo da decisão.
  - Sem motivo, usar a abertura atual ("Deferida/Indeferida …") sem a lista de fundamentos. **O texto exato nesse caso é escolha do implementador**, e deve continuar fazendo sentido sozinho.
  - Justificativa gravada pelo analista (`regin_justificativa`) continua indo como está.
  - O card `JustificativaReginCard` mostra o mesmo texto.
- **Item 8, 422 no convite:**
  - `ProcessoPendenciaController::store` troca `abort(422)` por erro de validação no campo `descricao`, para a mensagem aparecer dentro do modal.
  - O botão "Colocar em convite" só aparece com `processo.status === 'em_analise'`.
  - Além disso, em `bootstrap/app.php`, uma visita Inertia (`X-Inertia`) que termina em 4xx que não seja de validação passa a voltar para a página anterior com flash de erro, no mesmo padrão do 419 já tratado. Isso evita o "Oops" nos outros controllers que usam `abort(422)`.

## Fora do escopo / não fazer
- Itens 1 e 5: já atendidos.
- Não mudar o fluxo de decisão de renovação existente.
- Não apagar `motivo_fora_do_expresso` nem dados de risco do banco: só sai da tela.
- Não alterar o texto já enviado à JUCEB de processos passados.
- Não mexer na mudança não commitada de `VistoriaConsultaController.php:269` (perfis que recebem vistoria): é trabalho alheio a este, e o implementador não deve incluí-la nos commits.

## Testes
Testes que hoje fixam o comportamento antigo e serão reescritos:
- `ProcessoFilaTest::test_contadores_da_caixa_de_entrada_sao_do_usuario`
- `VistoriaConsultaTest`
- `ProcessoPendenciaEndpointTest::test_abrir_fora_de_em_analise_e_recusado_com_422`
- `JustificativaReginTest`
- `HttpReginParecerNotifierTest`
- `sintese.test.ts`

Testes novos:
- Analisados por transição.
- Contadores.
- Isolamento entre vistoriadores, incluindo o acesso direto por URL.
- Conclusão com Tipo e Validade, cobrindo validação, gravação e renovação de Pré-operacional.
- Precedentes com BAP.
- Visita Inertia com 4xx virando flash.
- Vitest para a síntese, as sugestões de condicionante e o centroide de MultiPolygon.

Rodar a suíte das áreas tocadas e o Pint.

## Ordem sugerida
D (erro em produção) → C → B → A. Cada bloco é independente e pode virar commit e versão separados.

## Fica com o implementador
- Nomes de componentes, variante do botão, layout do modal "Expandir".
- Formato visual da marca de resultado nos precedentes.
- Texto da justificativa sem motivo (item 6).
- Como tratar o 4xx Inertia de forma geral, desde que não engula `ValidationException` e mantenha o 419 atual.
