# Treino da IA de contorno de lote

Ferramentas para treinar, no pod de homologação, o decodificador do SAM 2.1
(`facebook/sam2.1-hiera-small`) a devolver o lote inteiro a partir do ponto do
imóvel e da ortofoto 2024, e para comparar o resultado com o lote do CODLOG.

Desenho: `docs/superpowers/specs/2026-10-21-treino-ia-contorno-lote-design.md`.
Plano: `docs/superpowers/plans/2026-10-22-treino-ia-contorno-lote.md`.

## Onde fica cada coisa

Tudo o que as ferramentas geram fica em `storage/app/private/treino-ia/`
(variável `TREINO_IA_RAIZ`), no volume persistente: ambiente Python, recortes,
cache do codificador, pesos, avaliações, galerias e registros. Nada vai para o
banco. Para apagar tudo: `rm -rf storage/app/private/treino-ia`.

## Na máquina local (macOS com Apple Silicon)

O treino roda direto no macOS, fora do Docker, para usar a GPU (MPS). Os dados
ficam em `~/treino-ia`. As etapas `dados` e `recortes` baixam do GeoServer e da
ortofoto da prefeitura e precisam da VPN; `cache` e `treinar` rodam sem rede.

```sh
export TREINO_IA_RAIZ=$HOME/treino-ia
export HF_HOME=$TREINO_IA_RAIZ/py/hf
export PY=$TREINO_IA_RAIZ/py/bin/python
export TREINO_IA_THREADS=8 TREINO_IA_TETO_MB=10240
cd scripts/treino-ia
PYTHON=/opt/homebrew/bin/python3.13 sh instalar.sh
```

Depois, os mesmos comandos da tabela abaixo. O dispositivo é escolhido sozinho
(`mps` quando disponível); `TREINO_IA_DISPOSITIVO=cpu` força a CPU. O teste
cego precisa de processos reais: o `cego/entrada.json` vem do comando
`treino-ia:exportar-teste-cego` rodado num ambiente que tenha esse comando e
esses processos, e é copiado para `$TREINO_IA_RAIZ/cego/`.

## Comandos no pod de homologação

Rode a partir de `/opt/app-root/src`. Defina uma vez por sessão do terminal:

```sh
export TREINO_IA_RAIZ=/opt/app-root/src/storage/app/private/treino-ia
export HF_HOME=$TREINO_IA_RAIZ/py/hf
export PY=$TREINO_IA_RAIZ/py/bin/python
cd /opt/app-root/src/scripts/treino-ia
```

| Ordem | Etapa | Comando |
|---|---|---|
| 1 | Instalar o ambiente (uma vez) | `sh instalar.sh` |
| 2 | Montar o conjunto | `$PY -m treino_ia dados --semente AAAAMMDD` (`--teste-por-bairro N` espalha o teste por mais bairros) |
| 3 | Recortes (medição nos primeiros 50) | `$PY -m treino_ia recortes --limite 50` |
| 4 | Cache do codificador (medição) | `nice -n 19 $PY -m treino_ia cache --limite 50` |
| 5 | Recortes e cache completos | `nohup nice -n 19 $PY -m treino_ia recortes > $TREINO_IA_RAIZ/logs/recortes.log 2>&1 &` e depois o mesmo com `cache` |
| 6 | Treino | `nohup nice -n 19 $PY -m treino_ia treinar --epocas 10 --semente AAAAMMDD > $TREINO_IA_RAIZ/logs/treinar.log 2>&1 &` |
| 7 | Amostra do teste cego | `cd /opt/app-root/src && php artisan treino-ia:exportar-teste-cego --semente N --quantidade 60` |
| 8 | Avaliar os lotes de teste | `nohup nice -n 19 $PY -m treino_ia avaliar teste > $TREINO_IA_RAIZ/logs/avaliar.log 2>&1 &` |
| 9 | Avaliar o teste cego | `nohup nice -n 19 $PY -m treino_ia cego > $TREINO_IA_RAIZ/logs/cego.log 2>&1 &` |
| 10 | Gerar as galerias | `$PY -m treino_ia galeria` |
| — | Andamento a qualquer momento | `$PY -m treino_ia situacao` |

A perda média de cada época fica em `$TREINO_IA_RAIZ/logs/treino.log`.

## Limites

Cada etapa usa no máximo 2 threads e para sozinha, com código de saída 3 e a
mensagem `PARADO: Memória em uso (…) passou do teto de 4096 MB`, se passar de
4 GB. Rode as etapas longas com `nice -n 19`.

## Publicações no SEMIT

Cada publicação troca o pod e interrompe o que estiver rodando. Todas as etapas
retomam de onde pararam: basta rodar o mesmo comando de novo. Antes de publicar,
rode `situacao`; se o treino estiver no meio de uma época, ele perde no máximo
os últimos 5 minutos.

## Galeria

Ligue o parâmetro `features.treino_ia_galeria` em Parâmetros, **só em
homologação** (a galeria mostra lotes e endereços de processos reais). Ela fica
em `/gestao/treino-ia`, para quem tem a permissão de manter parâmetros.

## Testes

```sh
docker run --rm -v "$PWD/../..":/r -w /r/scripts/treino-ia <imagem com SAM 2, shapely e pytest> python -m pytest -q
```
