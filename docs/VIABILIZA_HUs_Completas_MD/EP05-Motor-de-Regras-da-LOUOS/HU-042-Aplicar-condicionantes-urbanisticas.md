# HU-042 — Aplicar condicionantes urbanísticas

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Juntar as condicionantes que acompanham o parecer e, quando incidem, mudar o consolidado de permitido para permitido com condições. Nenhuma delas, sozinha, torna o uso não permitido. A exigência de vagas por grupo nasce vazia.

## História de Usuário
**Como** sistema,  
**quero** listar as condições urbanísticas aplicáveis ao caso,  
**para** o parecer registrar o que precisa ser observado sem transformar condição em proibição.

## Atores e permissões
- O motor monta a lista durante o enquadramento. Não há tela exclusiva de “aplicar condicionante”.
- A exigência de vagas é o parâmetro `louos.vagas.exigencia_por_grupo` (JSON). O padrão gravado é `{}`.
- Os textos numerados vêm das condicionantes da versão `risco_tratamento` citada no enquadramento.

## Fluxo evidenciado
1. A coleta só ocorre quando o consolidado não caiu em não permitido, pendente ou bloqueio antes dessa etapa.
2. Quadro 10 `permitido_condicionado` gera condicionante `zona`, com `condicionante_ref` da linha.
3. Condição textual do Quadro 11A, que não seja só `sim` nem `nao`/`r`, gera condicionante `via`.
4. Vagas: sem grupo, não gera item. Sem exigência parametrizada para o grupo, gera item `vagas` com `conforme` nulo e motivo de exigência não parametrizada. Esse item não rebaixa o veredito. Com exigência, `conforme` é verdadeiro só se cada item declarado for maior ou igual ao exigido. Item omitido conta como zero. Não conforme rebaixa para `permitido_com_condicoes`. Não vira `nao_permitido`.
5. Escritório virtual: `CondicionanteEscritorioVirtual` pode acrescentar texto do tipo `planilha` a partir do CNAE e das respostas. Texto de planilha é informativo e não rebaixa sozinho.
6. Números em `condicionantes_planilha` do ramo são lidos em `TratamentoCondicionante` da mesma versão. Também são do tipo `planilha` e não rebaixam sozinhos.
7. Restrições territoriais identificadas entram aqui (HU-043) e rebaixam.
8. Resultado permitido ou permitido com condições leva o motivo que encadeia CNAE, área, grupo, zona e a frase de condição.

## Regras de negócio evidenciadas
- Incidem (rebaixam): zona condicionada, via textual, vagas não conformes e restrição.
- Não incidem: vagas conformes, vagas sem parâmetro e textos da planilha.
- O parâmetro de vagas vazio deixa a conformidade nula. O motor não bloqueia por isso.

## Validações
- Quantidade declarada é comparada como número. Ausência vale zero diante de exigência maior que zero.
- Sem grupo de uso, a verificação de vagas não roda.

## Critérios de aceite
**Dado** que o Quadro 10 devolve permitido condicionado e o restante não veda o uso,  
**Quando** o parecer é consolidado,  
**Então** existe condicionante do tipo `zona` e o resultado é `permitido_com_condicoes`.

**Dado** que o parâmetro de vagas do grupo está vazio,  
**Quando** as condicionantes são coletadas,  
**Então** o item de vagas fica com `conforme` nulo e não muda um resultado que seria permitido.

**Dado** que a exigência do grupo está parametrizada e o declarado fica abaixo,  
**Quando** o parecer é consolidado,  
**Então** o resultado é `permitido_com_condicoes` e o motivo fala em imóvel não conforme quanto a vagas.

## Divergências da redação anterior
A redação anterior tratava condicionante urbanística como bloqueio genérico. O código separa o que rebaixa o veredito do que só entra na ficha, e a tabela de vagas começa vazia.

## Evidências
- `LouosEnquadramentoService::coletarCondicionantes`, `avaliarVagas`, `temCondicionanteIncidente`
- Parâmetro `louos.vagas.exigencia_por_grupo`
- `CondicionanteEscritorioVirtual`
- `TratamentoCondicionante`

## Não evidenciado
- Tabela de vagas preenchida com índices da lei. O padrão do parâmetro é objeto vazio.
- Condicionante da planilha que, sozinha, mude permitido para permitido com condições ou para não permitido.
