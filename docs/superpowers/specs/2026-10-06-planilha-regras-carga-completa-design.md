# Carga completa da Planilha de regras 20.08.26 no motor

## Objetivo
Tudo da planilha que faz parte do enquadramento e das regras fica na base de dados e
é lido pelo motor. O que depende de interpretação da SEDUR fica fora do motor e
visível como pendência até a resposta.

## Situação encontrada
- O seed da versão vigente (`TratamentoRegrasSeeder`) lê CSVs curados e não carrega a
  aba Análise CNLU, as notas gerais, três colunas de metadados do CNAES e as variantes
  de vagas (Carga e Descarga, Pátio e Embarque, 1 a 3, com Aplica, análise e quantidade).
- O parser de CNLU do xlsx reduz `nR3 (nR3-08 e nR3-14)` a `nR3`, e o motor casa por
  prefixo: o grupo inteiro iria à CNLU.
- 12 das 51 regras (29, 30, 31, 37, 38, 39, 40, 41, 42, 43, 53, 55) não têm fluxo curado.
  O extrator automático as interpreta de forma errada (a regra 38 vira "pergunta 7 →
  07.11.03" sem bloqueio), por isso não são importadas como ramos.
- A pendência hoje só aparece para rascunho, não para a versão vigente.

## Decisões
1. **Carga complementar, sem trocar a base curada.** Os CSVs seguem como fonte dos
   ramos curados. Um passo novo lê o xlsx (que passa a morar em
   `database/data/regras-20-08-26/`, porque `docs/` não vai ao SEMIT) e completa a
   versão: metadados e vagas por enquadramento, notas gerais, lista CNLU e pendências.
2. **CNLU literal.** `nR3 (nR3-08 e nR3-14)` vira `nR3-08` e `nR3-14`; `nRa` e `nR4`
   ficam como escritos; do bloco do art. 132 entram ID3-01, 04, 05, 07, 17 e 21. Os itens
   "(subgrupo 2)" (ID3-02, 03, 13, 14, 19) não entram: ficam na nota
   `cnlu_aguardando_sedur` até a SEDUR responder.
3. **12 regras em prosa viram pendência** com o motivo "Aguardando confirmação da SEDUR",
   gravada na versão vigente e exibida na aba Enquadramento do Motor de regras.
4. **Idempotência.** O seed completa a versão por upsert/atualização e não apaga dado em uso.

## Fora do escopo
Ramos das 12 regras, selo "aguardando validação" por ramo e mudança no motor de decisão.
Entram quando a SEDUR responder ao e-mail de dúvidas.

## Testes
- Metadados e variantes de vagas gravados por linha do CNAES, contagem igual à planilha.
- CNLU: códigos exatos; `nR3-01` fora da lista, `nR3-08` dentro; "subgrupo 2" fora.
- Pendências das 12 regras na versão vigente e exibidas na tela.
- Seed duas vezes não duplica nem apaga.
