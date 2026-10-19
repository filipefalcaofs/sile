# Edificações complementares (Overture, Microsoft e IA)

Quando as fontes da Prefeitura não acham o lote, o Viabiliza usa como polígono
do lote a **edificação que contém o ponto do imóvel**. Esse polígono entra na
maior porção (art. 18, § 2º, da Lei 9.148/2016) do mesmo jeito que o lote da
SEDUR. No mapa, a legenda nomeia a fonte do contorno.

Spec: `docs/superpowers/specs/2026-10-19-poligono-complementar-edificacoes-design.md`.

## Ordem das fontes

1. Endereçamento SEDUR por CODLOG + número
2. SEMAP por inscrição
3. **Overture** (base local)
4. **Microsoft** (base local; o que a Overture já tem é descartado)
5. **IA** sobre a ortofoto da Prefeitura (job próprio)
6. Endereçamento SEDUR pelo ponto
7. Nada encontrado: segue sem polígono. Sem ponto, vai para análise.

Regras:

- O prédio escolhido é o que **contém** o ponto. Se mais de um contém, vale o
  de menor área. Ponto fora de qualquer prédio conta como não encontrado
  naquela fonte.
- Ponto do Nominatim que achou só a rua (sem número) pula os passos 3 a 5.
- O primeiro polígono gravado fica. Um lote oficial achado depois não o
  substitui.
- O texto da decisão continua dizendo "Maior porção do lote".

## Instalação

1. **PostGIS:** as tabelas `building_footprints`, `building_footprint_imports`
   e `lot_polygon_resolutions` são criadas pelas migrations. O índice espacial
   só existe em PostgreSQL.
2. **CLI da Overture** na máquina que faz a extração:
   ```bash
   pipx install overturemaps
   ```
3. **Serviço de inferência** (opcional, só para o passo 5): veja
   `docker/footprint-ia/README.md`. Suba o contêiner e informe a URL no
   painel.

## Configuração

Em **Configuração de APIs → Edificações complementares**:

| Campo | Padrão | Para que serve |
|---|---|---|
| Edificações da Overture | desligado | Passo 3 |
| Edificações da Microsoft | desligado | Passo 4 |
| Contorno por IA na ortofoto | desligado | Passo 5 |
| Atualização agendada das bases | desligado | Importações automáticas |
| URL do serviço de inferência | vazio | `http://<host>:8080` |
| Ortofoto 2024 (ImageServer) | `geo.salvador.ba.gov.br/.../Ortofotos/2024/ImageServer` | Imagem da IA |
| Ortofoto 2016/2017 (WMS) | `mapeamento.salvador.ba.gov.br/wms` | Reserva da 2024 |
| Token da ortofoto | vazio | Só se o ImageServer passar a exigir |

As constantes técnicas ficam em `config/viabiliza.php`, bloco `edificacoes`:

- faixa de área aceita na importação: 10 a 20.000 m²;
- retângulo de Salvador usado nos downloads;
- tamanho do recorte da IA: 60 m e 800 px;
- prazo de espera da IA: 3 passadas extras do job de decisão, de 60 s cada,
  além das tentativas da espera do Cadastro. Uma IA que responde depois disso
  guarda o prédio na base, mas não muda o processo, que já seguiu sem ela;
- critérios de aceite da IA: área de 20 a 5.000 m², compacidade ≥ 0,2 e
  margem de 2 px até a borda do recorte.

## Ingestão inicial

Rode nesta ordem, e só depois ligue as fontes no painel.

1. **Limite do município** (GeoServer SEDUR, 5 feições, contando as ilhas):
   ```bash
   php artisan geo:importar-wfs limite_municipio limite_salvador:VM_LIMITE_SALVADOR --versao=sedur-limite-2026-10 --ordenar=MUNICIPIOS
   ```
2. **Overture:**
   ```bash
   scripts/overture-salvador.sh 2026-09-23.1 storage/app/overture/salvador.geojsonseq
   php artisan geo:edificacoes:importar-overture storage/app/overture/salvador.geojsonseq --release=2026-09-23.1
   ```
3. **Microsoft:** baixa só os tiles do Brasil que cruzam Salvador.
   ```bash
   php artisan geo:edificacoes:importar-microsoft --release=2026-08-13
   ```
   Sem internet no servidor, baixe os `.csv.gz` em outra máquina e importe
   **todos numa única execução**, repetindo `--arquivo=caminho.csv.gz`.

Comportamento comum às importações:

- São idempotentes: a mesma fonte, release e checksum não reimporta.
- Rodam numa transação: uma falha no meio desfaz a carga, e a base anterior
  continua valendo.
- Prédios que sumiram na release nova ficam aposentados (`retired_at`).
  Nenhuma linha é apagada.
- Uma carga com menos da metade dos prédios ativos da fonte é tratada como
  parcial (um tile, um arquivo cortado). Ela grava o que trouxe, mas não
  aposenta nada (`aposentadoria_suspensa = 1` nos contadores).

**Worker da fila:** o job da IA pode levar até 180 s. Rode o worker com
`--timeout` acima disso e ajuste `retry_after` da conexão `database` para mais
que 180 s, senão o job pode rodar duas vezes.

## Atualização

Com "Atualização agendada das bases" ligada:

- **Overture:** todo dia 25, às 03:00. Roda o script e depois a importação, com
  a release rotulada `auto-AAAA-MM-DD`. O CLI sempre baixa a release mais
  recente.
- **Microsoft:** a cada trimestre, no dia 15, às 03:30.

Os dois agendamentos usam `withoutOverlapping()->onOneServer()`. Calendário de
releases da Overture: https://docs.overturemaps.org/release-calendar/

## Operação e métricas

```bash
php artisan geo:edificacoes:metricas --desde=2026-10-01
php artisan geo:edificacoes:metricas --json
```

O comando mostra:

- **Cobertura:** % dos processos do período por fonte do polígono e % sem
  polígono.
- **Base:** prédios ativos e duplicados por fonte, e a data da última
  importação concluída.
- **Latência:** p50 e p95 por fonte.
- **Falhas:** quantidade por fonte e por motivo, por exemplo
  `imagem_indisponivel`, `servico_fora`, `nao_contem_o_ponto`,
  `prazo_esgotado`, `ponto_inelegivel`.
- **IA:** tentativas, encontrados, não resolvidos, indisponíveis e tempo médio.

Cada tentativa fica em `lot_polygon_resolutions` com fonte, id original,
versão, status, método de associação, área, confiança (só quando a fonte
fornece), ponto usado e latência. As chamadas externas aparecem no log de
integrações como `overture`, `edificacoes_microsoft`, `ortofoto` e
`edificacoes_ia`.

**Precisão:** não há medida automática. Proposta: todo mês, conferir na ficha
uma amostra de processos com polígono complementar sobre a ortofoto e anotar
os casos em que o contorno não corresponde ao imóvel.

## Licenças e atribuições

- **Overture Maps (buildings):** ODbL. A atribuição "© OpenStreetMap
  contributors, Overture Maps Foundation" aparece no mapa quando o contorno
  vem da Overture. Dados derivados que forem distribuídos ficam sob a mesma
  licença.
- **Microsoft GlobalMLBuildingFootprints:** o README atual cita CDLA
  Permissive 2.0, mas a página de atribuição da Overture cita ODbL para esses
  dados. A divergência não foi resolvida, então convém tratar como ODbL até
  confirmar. Atribuição: "Microsoft".
- **Ortofoto 2016/2017 (Prefeitura):** acesso irrestrito, proibido
  comercializar, e a Prefeitura deve ser citada. Atribuição: "Ortofoto:
  Prefeitura Municipal de Salvador".
- **Ortofoto 2024 (ImageServer GeoSalvador):** os termos estão em branco no
  serviço. **Pendente:** a SEDUR precisa confirmar por escrito com a
  Cartografia (cartografia@salvador.ba.gov.br) o uso automatizado.
- **Modelo de IA (SAM 2):** Apache 2.0.
- Google, Bing, Esri e Mapbox **não** são usados: os termos deles proíbem
  extrair contornos das imagens.

## Dependências externas

| Item | Situação |
|---|---|
| Contêiner `docker/footprint-ia` no OKD | A subir. Sem ele, a IA registra `servico_fora` e o processo segue para o passo 6 |
| GPU para a IA | Opcional. Em CPU funciona para dezenas de casos por dia |
| Saída para S3 (Overture) e Azure Blob (Microsoft) | Necessária na máquina da extração, ou importação por arquivo |
| Token da ortofoto 2024 | Não exigido hoje (medido em 19/10/2026). O mesmo host já passou a exigir token no SEMAP |
| Confirmação da licença da ortofoto 2024 | Pendente com a Cartografia |
