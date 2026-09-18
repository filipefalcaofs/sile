# Manual operacional — CSV padronizado dos Quadros da LOUOS

Para quem publica nova versão dos Quadros 7, 10 e 11A em **Gestão → Quadros da LOUOS**. A mesma orientação está na tela **Manual de CSV** (`/gestao/louos/manual`), ao lado de **Baixar modelo CSV**.

O sistema **não lê o PDF da lei**. O PDF é a fonte jurídica; o arquivo que entra no rascunho é um CSV em **formato longo** (uma linha por combinação). Cabeçalho errado rejeita o arquivo inteiro.

## 1. O que cada Quadro precisa

| Quadro | O que o motor usa | Fontes para montar o CSV | Cabeçalho obrigatório |
|---|---|---|---|
| **7** | CNAE + área → grupo/subgrupo nR | PDF do Quadro 7 (faixas de uso) **e** planilha CNAE→uso | `cnae,grupo,subgrupo,area_min,area_max,observacao` |
| **10** | Zona × uso → S / N / S(c) | PDF/matriz do Quadro 10 da Lei nº 9.148/2016 | `zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal` |
| **11A** | Classe de via × uso → condição | PDF/matriz do Quadro 11A | `classe_via,grupo_uso,condicoes,base_legal` |

O PDF do Quadro 7 **não tem CNAE**. Sem a planilha de enquadramento (hoje: versão 20.08.26), o CSV do 7 não se monta.

Carga atual de referência (seed): 1.971 faixas / 1.331 CNAEs no 7; 1.323 células / 21 zonas no 10; 525 linhas / 7 vias no 11A.

## 2. Fluxo na tela (os três Quadros)

Permissão: `manter-louos`.

1. Abra **Gestão → Quadros da LOUOS** e selecione o Quadro.
2. Abra ou retome o **rascunho** (informe o identificador da nova versão, por exemplo `quadro7-rev-2026-09`).
3. O rascunho começa com a cópia da vigente. A vigente **não muda** até a publicação.
4. **Baixar modelo CSV** — baixa o cabeçalho exato mais uma linha de exemplo.
5. Monte o arquivo (seções 4 a 6).
6. **Importar CSV**. Deixe **substituir** marcado quando o arquivo for a carga completa da nova lei (padrão da tela). Desmarque só se for um complemento pontual sobre o que já está no rascunho.
7. Leia o relatório: `lidos`, `importados`, `atualizados`, `rejeitados` (motivo por linha).
8. Confira o **diff** do rascunho versus a vigente (novas / alteradas / excluídas).
9. **Outro usuário** publica (quatro olhos: o autor do rascunho não publica).

Arquivo: CSV ou TXT, no máximo **5 MB**.

## 3. Regras comuns do arquivo

- **UTF-8**, sem linha acima do cabeçalho.
- **Separador vírgula**. O Excel em português costuma gravar **ponto e vírgula** — o importador recusa o cabeçalho. Em Excel: Dados → Texto para colunas / gravar como “CSV UTF-8 (delimitado por vírgulas)”. Se o arquivo abrir numa coluna só, está com `;`.
- Nomes das colunas **idênticos** aos da tabela (minúsculas, sem acento no nome da coluna).
- Número de colunas fixo em **todas** as linhas. Texto com vírgula interna vai entre aspas (`"Estacionamento nos fundos; acesso único"`).
- Sem BOM do Word/Excel no começo (se o sistema disser “cabeçalho inesperado”, abra o arquivo num editor e apague o caractere invisível antes de `cnae` / `zona` / `classe_via`).
- Ponto decimal no número (`350.01`), não vírgula (`350,01`).
- Importação é **upsert** pela chave natural do Quadro. Linha inválida vai para rejeitados; o restante entra. Cabeçalho errado aborta tudo.

## 4. Quadro 7 — CNAE e faixa de área

### 4.1 Como o motor lê

O CNAE escolhe as linhas; a **área** escolhe a faixa. `area_min` é inclusivo; `area_max` vazio = sem teto.

Exemplo real (minimercado `4712-1/00`):

```csv
cnae,grupo,subgrupo,area_min,area_max,observacao
4712-1/00,nR1,nR1-01,0,350,07.01.05
4712-1/00,nR2,nR2-01,350.01,,07.01.05
```

Três faixas (serviço agropecuário `0161-0/99`):

```csv
0161-0/99,nR1,nR1-08,0,500,07.08.07
0161-0/99,nR2,nR2-08,500.01,5000,07.08.07
0161-0/99,nR3,nR3-08,5000.01,,07.08.07
```

Faixa única (qualquer área): `area_min=0` e `area_max` vazio.

### 4.2 Colunas

| Coluna | Obrigatório | Regra |
|---|---|---|
| `cnae` | Sim | Máscara `4712-1/00` ou só dígitos. Sete dígitos após limpar não-numéricos. |
| `grupo` | Sim | Grupo de uso (`nR1`, `nR2`, `nRa`, `ID1`…). |
| `subgrupo` | Não | `nR1-01`. Vazio vira nulo. |
| `area_min` | Sim | Número. |
| `area_max` | Não | Número ou vazio (sem teto). Não pode ser menor que `area_min`. |
| `observacao` | Não | Código da planilha (`07.01.05`) ou texto livre. **Reimportação não sobrescreve** observação já gravada. |

Chave de upsert: versão + CNAE (só dígitos) + `area_min`.

### 4.3 Sobreposição (rejeita o CNAE inteiro)

As faixas do **mesmo CNAE** não podem se cruzar.

- Contíguas passam: `0–350` e `350.01–` (o segundo começa **depois** do teto do primeiro).
- `0–350` e `300–` rejeita: a segunda invade o teto da primeira.
- Faixa sem teto **não pode** vir antes de outra do mesmo CNAE.

### 4.4 Como montar a partir do PDF + planilha

O PDF lista **usos** (nR1-12, nR2-01…) e cortes de área. A planilha operacional liga cada **CNAE** a um código LOUOS e às colunas de faixa.

Colunas da planilha 20.08.26 (`cnae-enquadramentos.csv`):

`cnae`, `codigo_louos`, `enquadramento1`, `ate_m2_1`, `enquadramento2`, `ate_m2_2`, `enquadramento3`, `acima_m2`

Passo a passo:

1. Exporte a planilha homologada para CSV UTF-8 (vírgula).
2. Um CNAE pode ter **várias** linhas (escritório genérico `07.12.13` **e** uso específico). Fique com **um** uso:
   - descarte `07.12.13` se existir uso específico;
   - se sobrar mais de um, prefira prefixo `07`, depois `08A`, `08`, `09`;
   - `9900-8/00` fica de fora (não é CNAE-Subclasses 2.3).
3. Em cada CNAE escolhido, leia até três bandas:
   - `enquadramento1` + `ate_m2_1` → primeira faixa (`area_min` 0 até o valor);
   - `enquadramento2` + `ate_m2_2` → faixa do meio;
   - `enquadramento3` + `acima_m2` → última faixa (sem teto).
4. Célula `-`, `Q` ou vazia na banda = essa banda não existe.
5. Texto de uso no formato `nR1-12` vira `grupo=nR1` e `subgrupo=nR1-12`.
6. Confira no PDF do Quadro 7 se o corte de área da planilha ainda é o da lei nova. Se a lei mudou o teto e a planilha não, **a planilha é que precisa ser republicada** — não invente o CNAE a partir do PDF.

Conferência mínima antes de importar:

- todo CNAE tem 7 dígitos válidos;
- nenhum CNAE com faixas sobrepostas;
- amostra de 10 CNAEs bate com o PDF (grupo/subgrupo e teto);
- total de CNAEs distinto próximo do catálogo esperado (hoje 1.331).

## 5. Quadro 10 — permissão por zona

### 5.1 Formato longo (não copie a matriz)

No PDF cada **célula** é zona × grupo/subgrupo. No CSV cada célula vira **uma linha**.

```csv
zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal
ZPR 1,nR1,nR1-01,S,,Lei nº 9.148/2016 — Quadro 10
ZEIS 1,nR1,nR1-08,S(c),Quadro 12,Lei nº 9.148/2016 — Quadro 10
ZIT,nR2,nR2-01,N,,Lei nº 9.148/2016 — Quadro 10
ZPR 1,R1,,S,,Lei nº 9.148/2016 — Quadro 10
```

`R1` na matriz sem subcategoria: `subgrupo` vazio.

### 5.2 Colunas

| Coluna | Obrigatório | Regra |
|---|---|---|
| `zona` | Sim | Sigla **igual** à da lei (espaço e hífen importam): `ZPR 1`, `ZCMe 1/01`, `ZCMe - CA`, `ZCMu 1 - IPITANGA`. |
| `grupo_uso` | Sim | `nR1`, `R1`, `EHIS`… |
| `subgrupo` | Não | `nR1-01` ou vazio. |
| `permissao` | Sim | Ver sinais abaixo. |
| `condicionante_ref` | Não | Ex.: `Quadro 12`. Se vazio e o sinal for `S(a)` / `S(b)` / `S(c)`, o sistema preenche a observação da lei. |
| `base_legal` | Não | Ex.: `Lei nº 9.148/2016 — Quadro 10`. |

Chave de upsert: versão + zona + grupo_uso + subgrupo.

### 5.3 Sinais aceitos

| No PDF | No CSV (qualquer um) | Gravado |
|---|---|---|
| S | `S`, `Sim`, `permitido` | permitido |
| N | `N`, `Não`, `nao`, `proibido` | proibido |
| S(c), S(a), S(b) | `S(c)`, `S(C)`, `S(a)`, `permitido_condicionado` | permitido_condicionado |

Outro valor: linha rejeitada.

### 5.4 Como transcrever o PDF

1. Liste as zonas da matriz (carga atual: 21).

   `ZPR 1`, `ZPR 2`, `ZPR 3`, `ZEIS 1` a `ZEIS 5`, `ZCMe 1/01`, `ZCMe 1/02`, `ZCMe 1/03`, `ZCMe 2`, `ZCMe - CA`, `ZCMu 1 - IPITANGA`, `ZCMu 2`, `ZCLMe`, `ZCLMu`, `ZDE 1`, `ZDE 2`, `ZUSI`, `ZIT`.

2. Para cada coluna de uso da matriz, crie uma linha por zona.
3. Copie o sinal da célula sem traduzir “à mão” para texto longo — `S` / `N` / `S(c)` bastam.
4. Conferência: `zonas × usos = total de linhas`. Hoje: 21 × 63 usos = **1.323**. Se a lei ganhar zona ou coluna, o produto muda; o total antigo não é meta.

## 6. Quadro 11A — condições pela via

### 6.1 Formato longo

```csv
classe_via,grupo_uso,condicoes,base_legal
VL,nR1-01,Sim,Lei nº 9.148/2016 — Quadro 11A
VE,nR3-08,Não,Lei nº 9.148/2016 — Quadro 11A
VA I,ID2-05,Objeto de análise particularizada pela CNLU,Lei nº 9.148/2016 — Quadro 11A
```

Várias condições na mesma célula: separe com `;` (o sistema grava lista).

```csv
Arterial I,nR1-01,"Estacionamento nos fundos; acesso único",Art. 92
```

### 6.2 Colunas

| Coluna | Obrigatório | Regra |
|---|---|---|
| `classe_via` | Sim | Sigla da lei. Carga atual: `VP`, `VL`, `VC II`, `VC I`, `VA II`, `VA I`, `VE`. |
| `grupo_uso` | Sim na prática | Na matriz atual é o **uso da coluna** (`R1`, `nR1-01`, `ID2-05`). Vazio é aceito pelo importador, mas não casa com o motor. |
| `condicoes` | Não | `Sim`, `Não`, texto da CNLU, ou lista com `;`. |
| `base_legal` | Não | Ex.: `Lei nº 9.148/2016 — Quadro 11A`. |

Chave de upsert: versão + classe_via + grupo_uso.

Linha com número de colunas diferente é **ignorada** (não entra em rejeitados). Conte as colunas antes de importar.

### 6.3 Como transcrever o PDF

1. Sete classes de via nas linhas (ou colunas) da matriz.
2. Cada uso da matriz vira `grupo_uso`.
3. Célula `Sim` / `Não` / `R` (análise CNLU) vira o texto em `condicoes`. Na carga vigente, `R` foi gravado como `Objeto de análise particularizada pela CNLU`.
4. Conferência: `7 vias × N usos = total`. Hoje **525** linhas (75 grupos de uso).

O 11A só incide no motor quando o território trouxer a classe de via oficial. Sem isso, a decisão degrada para análise técnica — o CSV mesmo assim precisa estar correto para o dia da integração.

## 7. Checklist do importador

Antes de subir:

- [ ] Quadro certo na tela (7, 10 ou 11A) — arquivo de um Quadro no rascunho de outro quebra o cabeçalho.
- [ ] Primeira linha = cabeçalho exato do modelo baixado.
- [ ] Vírgula, UTF-8, até 5 MB.
- [ ] Amostra de 5–10 linhas confrontada com o PDF.
- [ ] Totais batem com o produto da matriz (10 e 11A) ou com o catálogo CNAE (7).
- [ ] **Substituir** marcado na carga completa (senão linhas da lei antiga podem sobrar no rascunho).

Depois do relatório:

- [ ] `rejeitados` vazio, ou cada motivo entendido e corrigido num segundo arquivo.
- [ ] Diff do rascunho faz sentido (não “zero alteração” numa lei nova; não “todas excluídas” sem querer).
- [ ] Publicação por usuário diferente do autor.

## 8. O que não fazer

- Anexar o PDF e esperar o sistema gerar os três CSVs — isso não existe. Extração automática de tabela jurídica sem revisão publica regra errada.
- Montar o Quadro 7 só com o PDF (faltam os CNAEs).
- Recortar a matriz do PDF e colar como está (formato largo). Uma coluna deslocada inverte S/N em silêncio.
- Usar a planilha 20.08.26 para os Quadros 10 e 11A — ela só é ponte CNAE→uso do 7.
- Publicar com rejeições sem ler: o arquivo “entra”, mas as linhas ruins ficam de fora.

## 9. Referência dos arquivos oficiais atuais

No repositório, só para comparar layout — **não** substituem a lei nova:

- `database/data/louos/oficial/quadro7-faixas.csv`
- `database/data/louos/oficial/quadro10-permissoes.csv`
- `database/data/louos/oficial/quadro11a-condicoes-via.csv`
- Ponte CNAE do 7: `database/data/regras-20-08-26/cnae-enquadramentos.csv` (origem: `docs/artefatos/Planilha de regras - versão 20.08.26.xlsx`)

Modelo vazio pela tela: **Baixar modelo CSV** no rascunho do Quadro.
