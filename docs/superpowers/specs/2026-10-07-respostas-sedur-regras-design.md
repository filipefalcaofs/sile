# Respostas da SEDUR às dúvidas da planilha de regras 20.08.26

Fonte: respostas por e-mail de 07/10/2026 às dúvidas enviadas em 06/10/2026.

## Regra geral (SEDUR)
Quando o sistema não consegue enquadrar, encaminha para análise. Nunca indefere
automaticamente por falta de enquadramento. Indeferimento automático só nos casos
que a regra manda indeferir (veto do Quadro 10 e do Quadro 11A, restrição territorial
RT-01, bloqueios das regras 38, 39 e 55).

## Parte 1 — CNLU
- A lista da aba Análise CNLU vale assim:
  - `nR3-08` e `nR3-14` (só esses dois);
  - `nRa-02` (a planilha escreve `nRa`; a SEDUR restringiu ao subgrupo 02);
  - `nR4` com todos os subgrupos;
  - os onze itens do art. 132 (ID3-01, 02, 03, 04, 05, 07, 13, 14, 17, 19, 21) em qualquer
    subgrupo ("subgrupo 1" ou "subgrupo 2").
- Os itens "(subgrupo 2)" deixam de ficar em espera: entram na lista. A nota
  `cnlu_aguardando_sedur` deixa de ser gerada.
- O casamento do subgrupo da linha com o código da lista ignora espaços e o sufixo
  "(subgrupo N)" (`ID3- 07`, `ID3-02 (subgrupo 2)`, `ID3-13 (subgrupo 1`). Hoje esses
  nunca casam.
- **Quadro 10 prevalece sobre a CNLU.** Enquadramento sinalizado como CNLU com a zona
  proibindo o uso é indeferido pelo Quadro 10; com a zona permitindo ou condicionando
  (ou o Quadro 10 indisponível), vai para análise CNLU.

## Parte 2 — Bloqueios 38, 39 e 55
- "Não permitir o prosseguimento do cadastro" = o processo é cadastrado e indeferido
  logo em seguida, com a mensagem da regra como justificativa.
- Categoria da empresa (órgão público ou não) vem do REGIN: natureza jurídica do
  cadastro da empresa. Órgão público = natureza jurídica do grupo 1 (Administração
  Pública) da tabela da Receita, parametrizável.
- Imóvel residencial vem do REGIN: tipo de imóvel "edificação residencial".
- Regra 38: empresa que não é órgão público, ou órgão público com P7 = NÃO, é
  indeferida. Órgão público com P7 = SIM enquadra 07.11.03 (expresso; semiexpresso em
  galpão ou container).
- Regras 38, 39 e 55: imóvel residencial é indeferido.
- Regra 39: combinações de categoria da empresa e P6 incompatíveis são indeferidas; as
  compatíveis enquadram 07.11.03 (órgão público, P6 = NÃO) ou 07.12.13 (demais, P6 = SIM).

## Parte 3 — Regras 30, 31, 41 e 42
- Regra 31: o caso "NÃO na P11 e área acima de 1.250 m²" segue o fluxo expresso. O corte
  de 350 m² não é regra de fluxo: vem do enquadramento da aba CNAES (até 350 m² = nR1-03;
  acima, e não escritório = nR2-02), já importado.
- Regra 30: fora das condições do expresso (área acima de 1.250 m² ou galpão, edificação
  residencial, container), segue para análise (semiexpresso).
- Regras 41 e 42 (CNAE 8211-3/00): a verificação de sede de escritório virtual existente
  confere só a mesma inscrição imobiliária e só roda quando o requerente responde SIM em
  "quer ser sede de escritório virtual". A base local de sedes ativas
  (`VirtualOfficeInscriptionLock`) é a fonte desta entrega. A SEDUR indicou os bancos SAPS
  e SIGS como fonte; não há integração com eles neste repositório, e a consulta fica como
  ponto de extensão até a SEDUR informar o acesso.

## Parte 4 — Respostas de múltipla escolha e regras compostas

### Modelo de resposta
Toda resposta à planilha é Sim/Não (legado, `bool`), uma opção (`int`) ou um conjunto de
opções (`list<int>`). Não há campo numérico livre: na regra 53, P27 (A = 2 plataformas,
B = 3) e P28 (opções 1 a 8, cada uma com um teto em kg) são opções, e o produto
plataformas × kg é calculado pelo motor.
- Classe `RespostaPlanilha` (valor imutável): `sim()`, `contem(int $opcao)`, `algumaDe(array $opcoes)`,
  `opcao()`. Compatibilidade: para pergunta Sim/Não, `true` equivale à opção 1 e `false` à opção 2.
- `treatment_questions` ganha `tipo` (`sim_nao`, `opcao_unica`, `multipla`) e `opcoes`
  (json). O `tipo` e as `opcoes` saem do enunciado no import: linhas "1.", "A." etc.;
  enunciado com "Identifique entre as opções" ou "selecione quais" é `multipla`; opções
  exatamente Sim/Não é `sim_nao`; o resto é `opcao_unica`.
- Portal: `consulta.tsx` renderiza por `tipo` (radio Sim/Não, radio de opções, checkboxes)
  e envia `respostas` com `bool | int | int[]`. Os três FormRequests do portal e o de
  simulação passam a validar `boolean|integer|array` (itens inteiros).
- REGIN: `RecebimentoProcesso` e `ReginProcessoProtocolador` reconhecem "1", "1,2",
  "1 e 2", "A" e Sim/Não conforme o `tipo` da pergunta; a string bruta continua guardada.
- Persistência: `simulation_snapshot` (jsonb) guarda os valores como vieram, sem migration
  de dados. `ViabilityRequest::respostasDaPlanilha` deixa de forçar `boolval`.
- O motor (`TratamentoRamoInput`, `RiscoInput`, `ConsultaViabilidade*`) troca
  `array<int,bool>` por `array<int, bool|int|list<int>>`.

### Condições e ações dos ramos
`treatment_regra_ramos` ganha `condicoes` (json) e `acao` (json), ao lado das colunas atuais,
que continuam valendo.
- `condicoes` (todas opcionais, combinadas por E): `perguntas` (por número: `{"sim": bool}`
  ou `{"opcoes": [..]}`, qualquer uma marcada), `categoria` (`orgao_publico` ou `outra`,
  da natureza jurídica do REGIN), `tipo_imovel` (`residencial`, `galpao_container`),
  `faixa` (`ate_1250`, `acima_1250`) e `cnae_unico` (o pedido só tem o CNAE).
- `acao`: `enquadrar` (código LOUOS, `segundo` para "segundo enquadramento disponível", ou
  `grupo` forçado como `nR1-04`), `fluxo`, `bloqueia` com `mensagem` (chave do catálogo de
  textos), `critica` (remeter à crítica do analista) e `condicionante` (chave do catálogo).
- "Segundo enquadramento disponível" é a segunda linha de enquadramento do CNAE na aba CNAES.
- Regra 53: `grupo` = `nR1-04` se plataformas × kg ≤ 1560, senão `nR2-04`, sempre com crítica
  do analista; fluxo somente semiexpresso.
- "Enquadrar separadamente cada atividade e validar pelo mais restritivo" já é o consolidado
  do motor (por CNAE, pior caso); ganha teste com dois CNAEs divergentes (regras 29 e 37).
- Os ramos das 12 regras viram dado curado em `database/data/regras-20-08-26/regras-fluxo-sedur.json`
  (um item por ramo, com a transcrição da frase da planilha), carregado pelo seed junto
  dos CSVs. As pendências "aguardando a SEDUR" deixam de existir para as regras que ganharem ramo.

## Mensagens
Todas as mensagens de bloqueio e de condicionante saem do catálogo `decision_texts`
(chaves `tratamento.bloqueio.*` e `tratamento.condicionante.*`), com o texto da planilha como padrão.

## Fora do escopo
Integração com os bancos SAPS e SIGS (sem acesso informado).

## Testes
- Unidade: `RespostaPlanilha`, parser de enunciados, `CnluLista`, matcher de condições.
- Motor: um teste por ramo curado das 12 regras, com a frase da planilha no nome.
- Fluxo: portal (consulta com opções e múltipla), REGIN (recebimento de "1,2"), indeferimento
  imediato dos bloqueios, CNLU com Quadro 10 proibindo e permitindo.
- Regressão: golden files booleanos de risco e Louos seguem passando.
