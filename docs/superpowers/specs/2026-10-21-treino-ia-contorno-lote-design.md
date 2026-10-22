# Treino da IA de contorno de lote em homologação

Data: 21/10/2026. Pedido em conversa, depois do teste cego do Maxcenter
(`docs/evidencias/2026-10-21-teste-cego-ia-contorno-lote/` na branch de dev).

## Por quê

O contorno por IA (SAM 2, `docker/footprint-ia`) foi feito para achar a
edificação que contém o ponto. No teste cego do processo 1687 (Av. ACM, 846)
ele contornou um volume de 91,8 m² sobre a laje, contra 7.107,6 m² do lote do
CODLOG (IoU 0,013), e passou em todas as regras do `AceiteMascaraIa`. Ajustes de
prompt e de recorte levaram o IoU a 0,43 (edifício inteiro), com teto de 0,55: o
SAM 2 não contorna estacionamento nem recuos, que não têm borda própria na
imagem.

O usuário precisa do **lote inteiro, com estacionamento e recuos**. Ele quer
treinar a IA com os lotes do CODLOG e ver testes antes de decidir o que é
aceitável. O treino roda no ambiente de homologação do Viabiliza.

## Objetivos

1. Treinar, em homologação, uma versão do SAM 2 que devolva o lote a partir do
   ponto do imóvel e do recorte da ortofoto 2024.
2. Medir antes e depois com o mesmo método: lotes de teste de bairros que não
   entraram no treino e, por fim, o teste cego com o ponto do Cadastro de
   processos reais.
3. Mostrar o resultado numa galeria que o usuário abre no Viabiliza de
   homologação, para decidir o nível aceitável.
4. Avaliar, como variantes na mesma galeria, os itens 1 a 3 da conversa:
   recorte que cresce até a máscara não encostar na borda; área do terreno do
   Cadastro só quando a inscrição não é de unidade; pontos negativos no eixo do
   logradouro.

## Fora do escopo

- Ligar a IA em produção ou mudar o fluxo do processo (`GerarPoligonoIaJob`,
  `PoligonoComplementarService`, ordem das fontes). Isso vem depois, numa spec
  própria, com o nível aceitável já decidido.
- Mudar o aceite (`AceiteMascaraIa`). Os indicadores da galeria vão informar a
  nova regra.
- GPU, serviço de treino separado no OKD, outras ortofotos.

## O que não pode acontecer

- **Vazar o lote na entrada.** Na avaliação, o modelo recebe só o recorte da
  ortofoto, o ponto e, nas variantes que usam, a área do Cadastro e o eixo do
  logradouro. O polígono do CODLOG só entra no cálculo dos indicadores.
- **Lote de teste vizinho de lote de treino.** A separação é por bairro.
- **Derrubar a homologação.** O nó tem 24 GB compartilhados e 9,4 GB livres;
  o pod não tem limite de CPU nem de memória.
- **Perder horas de trabalho numa publicação.** Cada push no SEMIT troca o pod.

## Ambiente (medido em 21/10/2026)

| Item | Valor |
|---|---|
| Endereço | `https://viabiliza-hml.salvador.ba.gov.br` (`APP_ENV=production` no `.env`) |
| CPU / GPU | 4 núcleos x86, sem GPU, sem limite no cgroup |
| Memória | sem limite no cgroup; nó com 24 GB, ~14,5 GB em uso |
| Volume persistente | `storage/app`, NFS, 97 GB livres |
| Python | 3.12.12, sem pip (instalar com `ensurepip`) |
| Rede | alcança a ortofoto 2024 (ImageServer) e o pypi |
| `storage/app/public` / `public/storage` | não existem |
| Entrega de código | push no GitLab do SEMIT; o build e o deploy são automáticos e trocam o pod |
| Execução | só pelo terminal web do pod, pelo usuário; eu não alcanço o pod |

## Decisões

### Onde fica cada coisa

- Scripts do treino em `scripts/treino-ia/` no repositório, entregues pelo
  SEMIT. São ferramentas, não código de produção.
- Tudo o que o treino gera fica em `storage/app/treino-ia/`: ambiente Python,
  recortes, cache do codificador, pesos, pontos de retomada, registros e a
  galeria. Nada vai para o banco.
- O ambiente Python (PyTorch CPU do índice oficial e SAM 2 da Meta no commit
  `2b90b9f5`, o mesmo do `DockerFile_footprint_ia`) é criado dentro do volume,
  para sobreviver às trocas de pod.

### Dados

- Lotes de treino: polígonos da camada `enderecamento:VM_ENDERECAMENTO`,
  baixados do GeoServer pelo pod. Ficam de fora polígonos inválidos, com área
  abaixo de 40 m² ou acima de 20.000 m², e chaves CODLOG + número com mais de
  um polígono diferente.
- Separação por bairro (camada de bairros oficial do GeoServer): os bairros
  sorteados para teste não fornecem nenhum lote de treino. A semente fica
  registrada no manifesto do conjunto.
- Tamanho: primeiro ciclo com cerca de 600 lotes de treino e 60 de teste; o
  conjunto cresce para ~2.000 depois de medido o tempo real por recorte (ver
  "Respostas à revisão").
- Ponto de entrada no treino: um ponto sorteado dentro do lote (não o
  centroide), para o modelo não aprender que o lote está sempre centrado.
- Recorte: centrado no ponto e grande o bastante para conter o lote, com
  margem. Lote que não cabe no maior recorte usado fica de fora e é contado.

### Modelo e treino

- Base: `facebook/sam2.1-hiera-small`, o mesmo do serviço.
- Só o decodificador de máscaras é treinado. O codificador de imagem roda uma
  vez por recorte e o resultado fica em cache no volume. É isso que torna o
  treino viável em CPU.
- Saída do treino: um arquivo de pesos do decodificador, versionado por data e
  semente, que o serviço de IA pode carregar no lugar do original.

### Recursos e retomada

- Execução com `nice`, no máximo 2 threads de cálculo e teto de memória de
  4 GB, conferido pelo próprio script, que para com mensagem clara se passar.
- Cada etapa (baixar recortes, cache do codificador, treino, avaliação, galeria)
  é retomável: grava o que já fez e, ao reiniciar, continua de onde parou.
- Execução com `nohup`, registro em `storage/app/treino-ia/logs/`. Um comando
  de situação mostra a etapa, o andamento, a memória e o último erro.
- Antes de cada publicação no SEMIT, o treino é pausado (ou se aceita que a
  troca de pod o interrompa e ele retome depois).

### Avaliação

- Indicadores por lote: IoU, interseção sobre a área de cada polígono,
  diferença de área (m² e %), distância de Hausdorff, distância média da divisa
  (vértices da IA até a divisa do CODLOG e o inverso, em metros) e se o
  contorno passaria no `AceiteMascaraIa` atual.
- Variantes avaliadas nos mesmos lotes:
  - A: serviço atual (1 ponto, recorte de 60 m, máscara de maior score);
  - B: atual com recorte crescente (60 → 120 → 240 m) e a maior máscara que
    não encosta na borda;
  - C: B com área do terreno do Cadastro na escolha da máscara, só quando a
    inscrição não é de unidade;
  - D: B com pontos negativos no eixo do logradouro;
  - E: modelo treinado;
  - F: modelo treinado com o melhor dos itens 1 a 3.
- Teste cego final: pontos do Cadastro de processos reais com lote do CODLOG,
  sorteados com semente registrada, como no teste do Maxcenter.

### Galeria no Viabiliza de homologação

- Rota na gestão, só para quem tem permissão de administrador (mesma permissão
  das telas de configuração de APIs), que lista e mostra as galerias gravadas
  em `storage/app/treino-ia/galerias/<execução>/`.
- Para cada lote: a ortofoto com o lote do CODLOG e o contorno de cada
  variante, os indicadores e o bairro. Ordenação por qualquer indicador; filtro
  por faixa de área do lote.
- Resumo da execução: mediana e quartis de cada indicador por variante, e
  quantos lotes passariam em limites de IoU e de erro de divisa escolhidos na
  tela, para o usuário testar limites.
- A rota só lê arquivos do volume; não expõe caminhos fora de
  `storage/app/treino-ia/galerias`.

### Respostas à revisão (21/10/2026)

- **Processos reais no teste cego:** o banco de homologação tem processos
  reais. A consulta do sorteio do Maxcenter, rodada no pod de homologação,
  achou 1.488 processos com lote `chave` e ponto `cadastro_imobiliario`. O teste
  cego sai de lá, com semente registrada, e grava só ponto, inscrição, CODLOG,
  número e o polígono gravado no processo (este último só para os indicadores).
- **Área do Cadastro (variante C):** os lotes da `VM_ENDERECAMENTO` não têm
  inscrição. A variante C só é medida no teste cego, onde o processo tem
  inscrição e a consulta ao Cadastro responde de dentro do pod (medido no
  processo 1687). Nos 60 lotes de teste, a comparação é entre A, B, D, E e F.
  *Decisão minha.*
- **Onde rodam as variantes:** dentro do script, com o mesmo `app.py` do
  serviço e os mesmos pesos, sem depender do `footprint-ia` estar implantado
  em homologação (hoje não está). O modelo é carregado uma vez e reaproveitado
  por todas as variantes, dentro do teto de 4 GB. *Decisão minha.*
- **Eixo do logradouro (variante D):** camada `logradouros:VM_LOGRADOURO`,
  filtrada pelo CODLOG do lote, que traz a geometria do eixo (usada no
  experimento do Maxcenter).
- **Publicação:** só na branch `homologa` do SEMIT, não na `main`. A rota da
  galeria nasce desligada por um parâmetro (padrão desligado) e é ligada só em
  homologação, para o caso de o código chegar à produção numa publicação
  futura. *Decisão minha.*
- **Janela sem publicações:** como ainda não há uma janela combinada, o
  primeiro ciclo usa cerca de 600 lotes de treino, grava ponto de retomada a
  cada poucos minutos e cabe em poucas horas. O conjunto cresce para ~2.000 só
  depois que o tempo real por recorte estiver medido e houver uma noite sem
  publicações. *Decisão minha.*
- **Lote do Cadastro diferente do lote do CODLOG** (condomínio, terreno com
  vários lotes, ponto fora de qualquer lote): entra na conta como está e é
  marcado na galeria. O resumo mostra os indicadores com e sem esses casos.
  *Decisão minha.*

### Correção à parte

`docker/footprint-ia/requirements-modelo.txt` pede `sam2 @ <zip>`, mas o pacote
desse commit se chama `SAM-2`; o pip recusa. Corrigir para o mesmo formato do
`DockerFile_footprint_ia`.

## Deixado para quem constrói

- Função de perda, taxa de aprendizado, épocas, aumento de dados.
- Tamanho exato dos recortes e da resolução de entrada.
- Formato dos arquivos de cache e do manifesto.
- Desenho visual da galeria, dentro dos componentes da gestão.

## Pendências do usuário

- O nível aceitável (IoU, erro de divisa em metros) será definido depois de ver
  a galeria.
- Janela sem publicações no SEMIT durante as etapas longas.
