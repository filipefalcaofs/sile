# Treino da IA de contorno de lote em homologação — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Treinar de verdade, em homologação, o decodificador do SAM 2 para devolver o lote inteiro a partir do ponto e da ortofoto 2024, e entregar ao usuário a galeria do primeiro treino com as variantes comparadas ao CODLOG. As Tasks 1 a 9 constroem as ferramentas; a Task 10 é o treino.

**Architecture:** Pacote Python `scripts/treino-ia/treino_ia` com uma etapa retomável por módulo (dados, recortes, cache do codificador, treino, avaliação, galeria), executado pelo usuário no terminal do pod com tudo gravado em `storage/app/treino-ia/`. No Laravel, um comando exporta a amostra do teste cego e uma rota da gestão, protegida por permissão e por parâmetro desligado, lista e serve as galerias estáticas geradas pelo Python.

**Tech Stack:** Python 3.12, PyTorch CPU, SAM 2 (Meta, commit `2b90b9f5`), shapely, numpy, Pillow, OpenCV headless, pytest; Laravel 13, Inertia React, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-21-treino-ia-contorno-lote-design.md`

## Global Constraints

- Branch `feat/poligono-complementar-edificacoes` (worktree `/Users/filipefalcao/Downloads/fabrica/sile-poligono-complementar`).
- Modelo base `facebook/sam2.1-hiera-small`; SAM 2 instalado do zip `https://github.com/facebookresearch/sam2/archive/2b90b9f5ceec907a1c18123530e92e794ad901a4.zip` com `SAM2_BUILD_CUDA=0` e `--no-build-isolation`. Nunca o pacote `sam2` do PyPI.
- Só o decodificador de máscaras é treinado; o codificador roda uma vez por recorte e vai para cache.
- Tudo o que a ferramenta gera fica em `storage/app/treino-ia/` (raiz configurável por `TREINO_IA_RAIZ`); nada no banco.
- Limites: `nice -n 19`, `torch.set_num_threads(2)`, teto de memória residente de 4 GB conferido pelo script.
- Na avaliação, o modelo recebe só recorte, ponto e, nas variantes que usam, área do Cadastro e eixo do logradouro. O polígono do CODLOG só entra nos indicadores.
- Separação treino/teste por bairro (`bairro_oficial:VM_BAIRRO_OFICIAL`); semente registrada no manifesto.
- Filtros de lote: polígono válido, área entre 40 m² e 20.000 m², chave CODLOG + número sem polígonos distintos.
- Primeiro ciclo: 600 lotes de treino, 60 de teste.
- Recortes crescentes de 60, 120 e 240 m; 800 × 800 px; mesmo cálculo de caixa do `OrtofotoClient`.
- Galeria: rota `GET /gestao/treino-ia`, permissão `manter-parametros`, parâmetro `features.treino_ia_galeria` com padrão `0`; só lê `storage/app/treino-ia/galerias`.
- Publicação só na branch `homologa` do SEMIT, com aprovação do usuário.
- Textos de tela e mensagens em português; sem marca de autoria de IA em código, commits ou PR.

## Review Focus

1. **Pod trocado no meio de uma etapa** (publicação no SEMIT): ao rodar a etapa de novo ela continua de onde parou, sem duplicar nem corromper arquivos. Gravação atômica (arquivo temporário + `os.replace`) em todas as etapas; teste em Task 3 e Task 6.
2. **Memória passando do teto**: o script para com mensagem clara e código de saída 3, sem deixar arquivo pela metade. Teste em Task 1.
3. **GeoServer ou ortofoto falhando no meio do download**: a etapa registra o erro, tenta de novo e, na próxima execução, só busca o que faltou. Teste em Task 2 e Task 3.
4. **Lote maior que o maior recorte (240 m)**: fica de fora e é contado no manifesto, nunca entra cortado. Teste em Task 2.
5. **Caminho malicioso na rota da galeria** (`../`, absoluto, execução inexistente): 404, nunca lê fora de `storage/app/treino-ia/galerias`. Teste em Task 8.

---

### Task 1: Esqueleto do pacote, caminhos e limites de recursos

**Files:**
- Create: `scripts/treino-ia/treino_ia/__init__.py`, `scripts/treino-ia/treino_ia/config.py`, `scripts/treino-ia/treino_ia/recursos.py`, `scripts/treino-ia/treino_ia/arquivos.py`, `scripts/treino-ia/treino_ia/__main__.py`
- Create: `scripts/treino-ia/instalar.sh`, `scripts/treino-ia/requirements.txt`, `scripts/treino-ia/pytest.ini`
- Test: `scripts/treino-ia/tests/test_recursos.py`, `scripts/treino-ia/tests/test_arquivos.py`

**Interfaces:**
- Produces:
  - `config.Caminhos(raiz: Path)` com propriedades `dados`, `recortes`, `cache`, `pesos`, `logs`, `galerias`, `cego`, `estado`; `config.caminhos() -> Caminhos` lê `TREINO_IA_RAIZ` (padrão `/opt/app-root/src/storage/app/treino-ia`).
  - `recursos.aplicar_limites(threads: int = 2) -> None`; `recursos.conferir_memoria(teto_mb: int = 4096) -> None` levanta `MemoriaExcedida` (com a memória medida na mensagem).
  - `arquivos.gravar_atomico(caminho: Path, dados: bytes) -> None`, `arquivos.gravar_json(caminho: Path, obj) -> None`, `arquivos.ler_json(caminho: Path, padrao=None)`.
  - CLI: `python -m treino_ia <etapa>` com etapas `dados`, `recortes`, `cache`, `treinar`, `avaliar`, `cego`, `galeria`, `situacao`; `MemoriaExcedida` sai com código 3. Opções: `dados --semente N [--treino 600] [--teste 60]`; `recortes`/`cache --limite N` (processa só os N primeiros e registra o tempo médio por item); `treinar --epocas N --semente N` (registra a perda média por época no log); `avaliar teste|cego`; `situacao` mostra etapa, itens feitos/total, tempo médio, memória de pico e último erro.

- [ ] **Step 1: Write the failing tests**

```python
def test_conferir_memoria_levanta_acima_do_teto(monkeypatch):
    monkeypatch.setattr(recursos, "memoria_residente_mb", lambda: 5000)
    with pytest.raises(recursos.MemoriaExcedida, match="5000"):
        recursos.conferir_memoria(teto_mb=4096)

def test_gravar_atomico_nao_deixa_arquivo_parcial(tmp_path, monkeypatch):
    destino = tmp_path / "a.json"
    monkeypatch.setattr(os, "replace", lambda *a: (_ for _ in ()).throw(OSError("pod trocado")))
    with pytest.raises(OSError):
        arquivos.gravar_atomico(destino, b"{}")
    assert not destino.exists()

def test_cli_sai_com_codigo_3_quando_memoria_excede(monkeypatch):
    monkeypatch.setattr(recursos, "memoria_residente_mb", lambda: 5000)
    assert __main__.main(["situacao"]) == 3
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker run --rm -v /Users/filipefalcao/Downloads/fabrica/sile-poligono-complementar:/w -w /w/scripts/treino-ia viabiliza/footprint-ia:teste-cego sh -c "pip install -q shapely==2.0.6 pytest==8.3.4 && python -m pytest -q tests/test_recursos.py tests/test_arquivos.py"`
Expected: FAIL (`ModuleNotFoundError: treino_ia`).

- [ ] **Step 3: Implement** `config.py`, `recursos.py` (memória residente por `resource.getrusage`/`/proc/self/status`), `arquivos.py` (temporário no mesmo diretório + `os.replace`), `__main__.py` (cada etapa chama `aplicar_limites()` e `conferir_memoria()` antes de começar), `instalar.sh` (cria `$TREINO_IA_RAIZ/py` com `python3 -m venv` ou `ensurepip`, instala `requirements.txt` com o índice CPU do PyTorch e o SAM 2 do zip com `--no-build-isolation`, baixa os pesos para `$TREINO_IA_RAIZ/py/hf`).

`requirements.txt`: `numpy==2.1.3`, `pillow==11.0.0`, `opencv-python-headless==4.10.0.84`, `shapely==2.0.6`, `huggingface_hub==0.26.5`, `pytest==8.3.4`.

- [ ] **Step 4: Run to verify they pass** (mesmo comando) — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add scripts/treino-ia
git commit -m "feat: esqueleto das ferramentas de treino da IA de contorno com limites de memória e gravação atômica"
```

---

### Task 2: Conjunto de lotes com separação por bairro

**Files:**
- Create: `scripts/treino-ia/treino_ia/wfs.py`, `scripts/treino-ia/treino_ia/geo.py`, `scripts/treino-ia/treino_ia/dados.py`
- Test: `scripts/treino-ia/tests/test_dados.py`, `scripts/treino-ia/tests/test_geo.py`, `scripts/treino-ia/tests/fixtures/lotes.json`, `scripts/treino-ia/tests/fixtures/bairros.json`

**Interfaces:**
- Consumes: `config.caminhos()`, `arquivos.gravar_json`.
- Produces:
  - `wfs.baixar(type_name: str, destino: Path, pagina: int = 2000, sessao=None) -> int` — paginado (`startIndex`), retoma da última página gravada, 3 tentativas por página.
  - `geo.caixa_recorte(lat: float, lng: float, lado_m: float) -> tuple[float, float, float, float]` (oeste, sul, leste, norte), mesmo cálculo do `OrtofotoClient`; `geo.para_pixel(caixa, lat, lng, px=800) -> tuple[int, int]`; `geo.para_coord(caixa, x, y, px=800) -> tuple[float, float]`; `geo.ponto_interno(poligono, rng) -> tuple[float, float]` (lat, lng) dentro do polígono e a pelo menos 1 m da divisa; `geo.lado_minimo(poligono) -> float` (60, 120 ou 240 que contém o lote com margem de 10%, ou `None`).
  - `dados.montar(semente: int, n_treino: int = 600, n_teste: int = 60) -> dict` grava `dados/manifesto.json` com `{"semente", "bairros_teste", "treino": [...], "teste": [...], "descartados": {"invalido", "area", "chave_ambigua", "maior_que_recorte"}}`; cada lote: `{"id", "codlog", "numero", "bairro", "ponto": [lat, lng], "lado_m", "poligono": GeoJSON}`.

- [ ] **Step 1: Write the failing tests**

```python
def test_nenhum_bairro_de_teste_tem_lote_de_treino(fixtures):
    m = dados.montar_de(fixtures.lotes, fixtures.bairros, semente=2110, n_treino=20, n_teste=5)
    assert {l["bairro"] for l in m["treino"]}.isdisjoint(m["bairros_teste"])
    assert all(l["bairro"] in m["bairros_teste"] for l in m["teste"])

def test_mesma_semente_mesmo_conjunto(fixtures):
    a = dados.montar_de(fixtures.lotes, fixtures.bairros, semente=2110, n_treino=20, n_teste=5)
    b = dados.montar_de(fixtures.lotes, fixtures.bairros, semente=2110, n_treino=20, n_teste=5)
    assert [l["id"] for l in a["treino"]] == [l["id"] for l in b["treino"]]

def test_descarta_e_conta_lote_maior_que_240_m(fixtures):
    m = dados.montar_de(fixtures.lotes_com_um_de_300m, fixtures.bairros, semente=1, n_treino=5, n_teste=1)
    assert m["descartados"]["maior_que_recorte"] == 1

def test_descarta_area_fora_de_40_a_20000_e_chave_ambigua(fixtures): ...

def test_ponto_interno_fica_dentro_e_nao_e_o_centroide(): ...

def test_caixa_recorte_igual_ao_ortofotoclient():
    assert geo.caixa_recorte(-12.996143, -38.465385, 60) == pytest.approx(
        (-38.4656615778369, -12.996414311519887, -38.4651084221631, -12.995871688480113), abs=1e-9)

def test_wfs_retoma_da_pagina_gravada_e_tenta_de_novo(tmp_path, sessao_falsa): ...
```

Os valores esperados são o `caixa_4326` do `recorte.json` do teste cego do Maxcenter (`docs/evidencias/2026-10-21-teste-cego-ia-contorno-lote/` na branch de dev).

- [ ] **Step 2: Run to verify they fail** — comando da Task 1 com `tests/test_dados.py tests/test_geo.py`. Expected: FAIL.

- [ ] **Step 3: Implement** `wfs.py` (WFS 1.1, `outputFormat=application/json`, `srsName=EPSG:4326`, decodifica latin-1 como o GeoServer da SEDUR responde), `geo.py` (shapely; área em UTM 24S por projeção local), `dados.py` (`montar` baixa `enderecamento:VM_ENDERECAMENTO` e `bairro_oficial:VM_BAIRRO_OFICIAL` e chama `montar_de`; bairro do lote pelo bairro que contém o ponto interno; sorteia bairros de teste até somar `n_teste` lotes).

- [ ] **Step 4: Run to verify they pass.** Expected: PASS.

- [ ] **Step 5: Commit** — `git commit -m "feat: conjunto de lotes do CODLOG para o treino da IA, separado por bairro e com semente"`

---

### Task 3: Recortes da ortofoto e máscara do lote

**Files:**
- Create: `scripts/treino-ia/treino_ia/recortes.py`
- Test: `scripts/treino-ia/tests/test_recortes.py`

**Interfaces:**
- Consumes: `dados/manifesto.json`, `geo.caixa_recorte`, `geo.para_pixel`.
- Produces: `recortes.baixar_todos(manifesto: dict, sessao=None) -> dict` grava `recortes/<id>_<lado>.png` e `recortes/<id>_<lado>.json` (`caixa`, `lado_m`, `pixel_do_ponto`); `recortes.mascara_lote(poligono, caixa, px=800) -> np.ndarray[bool]` (rasterização do lote no recorte); devolve `{"baixados", "ja_existiam", "falhas"}`. URL do ImageServer: `https://geo.salvador.ba.gov.br/imageserver/rest/services/Ortofotos/2024/ImageServer/exportImage`, mesmos parâmetros do `OrtofotoClient`.

- [ ] **Step 1: Write the failing tests**

```python
def test_nao_baixa_de_novo_o_que_ja_existe(tmp_path, sessao_contadora, manifesto_2_lotes): ...
    # segunda chamada: sessao_contadora.chamadas == 0, ja_existiam == 2

def test_resposta_que_nao_e_imagem_conta_como_falha_e_nao_grava(tmp_path, sessao_html): ...

def test_mascara_do_lote_cobre_a_area_esperada():
    # quadrado de 20 m x 20 m no centro de um recorte de 60 m: ~ (800*20/60)^2 pixels, tolerância 3%
```

- [ ] **Step 2: Run to verify they fail.** Expected: FAIL.
- [ ] **Step 3: Implement** com `arquivos.gravar_atomico` e `conferir_memoria()` a cada 20 recortes.
- [ ] **Step 4: Run to verify they pass.** Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "feat: recortes da ortofoto 2024 e máscara do lote para o treino da IA"`

---

### Task 4: Indicadores de comparação

**Files:**
- Create: `scripts/treino-ia/treino_ia/metricas.py`
- Test: `scripts/treino-ia/tests/test_metricas.py`

**Interfaces:**
- Produces: `metricas.comparar(ia: dict | None, referencia: dict) -> dict` (GeoJSON em EPSG:4326) com `area_ref_m2`, `area_ia_m2`, `dif_area_m2`, `dif_area_pct`, `intersecao_m2`, `iou`, `pct_ia_dentro_ref`, `pct_ref_coberto`, `hausdorff_m`, `divisa_media_ia_ref_m`, `divisa_media_ref_ia_m`, `aceite_atual: bool`, `motivo_recusa`. Com `ia=None`, `iou=0` e `motivo_recusa="sem_objeto"`. Medidas em metros por projeção equirretangular local centrada no lote (sem `pyproj`), suficiente para recortes de até 240 m; o teste do Maxcenter confere contra os números do PostGIS em UTM 24S.
- `metricas.aceite_atual(anel_px, caixa, lat, lng, px=800) -> tuple[bool, str | None]` com as regras do `AceiteMascaraIa`: margem 2 px, área 20–5.000 m², compacidade ≥ 0,2, contém o ponto.

- [ ] **Step 1: Write the failing tests**

```python
def test_reproduz_o_teste_cego_do_maxcenter():
    m = metricas.comparar(ler("ia_maxcenter.json"), ler("ref_maxcenter.json"))
    assert m["iou"] == pytest.approx(0.0129, abs=0.001)
    assert m["area_ref_m2"] == pytest.approx(7107.6, rel=0.005)
    assert m["hausdorff_m"] == pytest.approx(78.2, abs=0.5)

def test_ia_nula_vira_iou_zero(): ...
def test_aceite_recusa_contorno_que_encosta_na_borda(): ...
```

As fixtures `ia_maxcenter.json` e `ref_maxcenter.json` são `resultado_ia.json` (`poligono_ia_4326`) e `referencia_codlog.geojson` das evidências do teste cego; copie para `tests/fixtures/`.

- [ ] **Step 2–4:** falha, implementação, passa.
- [ ] **Step 5: Commit** — `git commit -m "feat: indicadores de comparação entre o contorno da IA e o lote do CODLOG"`

---

### Task 5: Variantes de inferência (A, B, D e o modelo treinado)

**Files:**
- Create: `scripts/treino-ia/treino_ia/variantes.py`
- Test: `scripts/treino-ia/tests/test_variantes.py`

**Interfaces:**
- Consumes: `docker/footprint-ia/app.py` (`contorno_do_ponto`, `EPSILON_PX`) importado pelo caminho, sem cópia; recortes da Task 3; eixo do logradouro por `wfs` filtrado por `CODLOG`.
- Produces:
  - `variantes.Segmentador(pesos_decodificador: Path | None = None)` carrega `facebook/sam2.1-hiera-small` uma vez; `.mascaras(img, pontos, rotulos, caixa_px=None) -> list[tuple[np.ndarray, float]]`.
  - `variantes.rodar(nome: str, lote: dict, seg: Segmentador, contexto: dict) -> dict | None` devolve `{"variante", "lado_m", "area_m2", "score", "poligono": GeoJSON, "anel_px"}`; nomes: `"A"` (60 m, 1 ponto, maior score), `"B"` (60→120→240 m, maior máscara sem encostar na borda), `"C"` (B escolhendo pela área do Cadastro; só quando `contexto["unidade"] is False`), `"D"` (B com pontos negativos no eixo do logradouro, um a cada ~15 m dentro do recorte), `"E"` (como A com o decodificador treinado), `"F"` (E com a melhor regra de B, C ou D apontada na avaliação; recebe a regra em `contexto["regra_f"]`).

- [ ] **Step 1: Write the failing tests** (com `Segmentador` falso que devolve máscaras fixas; não carrega o SAM 2)

```python
def test_A_escolhe_a_mascara_de_maior_score(seg_falso, lote): ...
def test_B_cresce_o_recorte_ate_a_mascara_nao_encostar_na_borda(seg_falso_encosta_em_60_e_120, lote):
    assert variantes.rodar("B", lote, seg, ctx)["lado_m"] == 240
def test_C_nao_roda_para_unidade(seg_falso, lote):
    assert variantes.rodar("C", lote, seg, {"unidade": True, "area_cadastro": 36}) is None
def test_D_poe_pontos_negativos_no_eixo(seg_que_registra, lote, eixo): ...
    # rótulos 0 só em pixels do eixo dentro do recorte
def test_nenhuma_variante_le_o_poligono_do_lote(seg_falso, lote_sem_poligono):
    # lote sem a chave "poligono" roda todas as variantes sem KeyError
```

- [ ] **Step 2: Run to verify they fail.**
- [ ] **Step 3: Implement.** `Segmentador` usa `SAM2ImagePredictor` com `torch.no_grad()`; pesos do decodificador carregados por `state_dict` sobre `predictor.model.sam_mask_decoder` quando informados.
- [ ] **Step 4: Run to verify they pass.** Depois, uma checagem real (não automatizada) dentro da imagem `viabiliza/footprint-ia:teste-cego`: `rodar("A", ...)` no recorte do Maxcenter dá área entre 90 e 94 m².
- [ ] **Step 5: Commit** — `git commit -m "feat: variantes de inferência do SAM 2 para a avaliação do contorno de lote"`

---

### Task 6: Cache do codificador e treino do decodificador

**Files:**
- Create: `scripts/treino-ia/treino_ia/cache.py`, `scripts/treino-ia/treino_ia/treino.py`
- Test: `scripts/treino-ia/tests/test_cache.py`, `scripts/treino-ia/tests/test_treino.py`

**Interfaces:**
- Consumes: recortes e máscaras da Task 3; `Segmentador` da Task 5.
- Produces:
  - `cache.gerar(manifesto, seg) -> dict` grava `cache/<id>_<lado>.pt` com as saídas do codificador em `float16` (embedding da imagem e as features de alta resolução que o decodificador usa); retomável; `{"gerados", "ja_existiam"}`.
  - `treino.treinar(manifesto, epocas: int, semente: int) -> Path` treina só `sam_mask_decoder` com um ponto interno sorteado por lote a cada época, perda Dice + BCE sobre a máscara do lote, grava `pesos/estado.pt` (pesos, otimizador, época, passo) a cada 5 minutos e no fim `pesos/decodificador_<data>_s<semente>.pt`; retoma de `estado.pt`.

- [ ] **Step 1: Write the failing tests**

```python
def test_cache_nao_recalcula_o_que_existe(tmp_path, seg_falso_contador, manifesto_2_lotes): ...
def test_treino_retoma_do_estado_salvo(tmp_path, cache_minusculo):
    treino.treinar(m, epocas=2, semente=1, parar_apos_passos=3)
    estado = torch.load(caminhos.pesos / "estado.pt")
    treino.treinar(m, epocas=2, semente=1)
    assert torch.load(caminhos.pesos / "estado.pt")["passo"] > estado["passo"]
def test_treino_so_altera_o_decodificador(tmp_path, cache_minusculo, seg_real_pequeno): ...
    # pesos do image_encoder iguais antes e depois
```

`seg_real_pequeno` usa o SAM 2 real e só roda dentro da imagem `viabiliza/footprint-ia:teste-cego` (marcar com `@pytest.mark.sam2`, pulado se `sam2` não importa).

- [ ] **Step 2–4:** falha, implementação, passa (incluindo `-m sam2` dentro da imagem).
- [ ] **Step 5: Commit** — `git commit -m "feat: cache do codificador e treino retomável do decodificador do SAM 2 com lotes do CODLOG"`

---

### Task 7: Avaliação, teste cego e galeria estática

**Files:**
- Create: `scripts/treino-ia/treino_ia/avaliacao.py`, `scripts/treino-ia/treino_ia/galeria.py`, `scripts/treino-ia/treino_ia/modelos/galeria.html`
- Create: `app/Console/Commands/TreinoIaExportarTesteCegoCommand.php`
- Test: `scripts/treino-ia/tests/test_avaliacao.py`, `scripts/treino-ia/tests/test_galeria.py`, `tests/Feature/TreinoIa/ExportarTesteCegoCommandTest.php`

**Interfaces:**
- Consumes: Tasks 2–6.
- Produces:
  - `avaliacao.avaliar(conjunto: str, variantes: list[str]) -> Path` (`conjunto` = `teste` ou `cego`) grava `avaliacoes/<execucao>/resultados.json` com uma linha por lote e variante (`metricas.comparar` + `bairro`, `faixa_area`, `cadastro_diferente`), retomável por lote.
  - Artisan `treino-ia:exportar-teste-cego {--semente=} {--quantidade=60}`: sorteia com `setseed` entre processos com `address_polygon_source = chave`, `property_location_source = cadastro_imobiliario`, ponto preenchido e sem `property_polygon_geojson`; grava em `storage/app/treino-ia/cego/` dois arquivos: `entrada.json` (id, protocolo, inscrição, CODLOG, número, complemento, ponto, área do terreno e se é unidade, pelo `PropertyRegistryLookup`) e `referencia.json` (id → `address_polygon_geojson`). A unidade é detectada por `SubUnidade` diferente de vazio ou complemento com `SALA`, `APTO`, `AP`, `LOJA`, `BLOCO`.
  - `galeria.gerar(execucao: str) -> Path` grava `galerias/<execucao>/index.html`, `galerias/<execucao>/resumo.json` (mediana e quartis de cada indicador por variante, com e sem `cadastro_diferente`) e `galerias/<execucao>/img/<id>.png` (recorte com o lote em azul e cada variante numa cor, legenda).

- [ ] **Step 1: Write the failing tests**

```python
def test_avaliacao_entrega_ao_modelo_lote_sem_poligono(monkeypatch, manifesto): ...
    # variantes.rodar recebe dicionário sem "poligono"; comparar recebe o polígono
def test_avaliacao_retoma_por_lote(tmp_path): ...
def test_resumo_traz_mediana_com_e_sem_cadastro_diferente(resultados_fixos): ...
def test_galeria_html_tem_um_card_por_lote_e_ordena_por_iou(resultados_fixos): ...
```

```php
public function test_exporta_entrada_sem_poligono_e_referencia_separada(): void
// entrada.json não contém address_polygon_geojson; referencia.json contém
public function test_mesma_semente_mesma_amostra(): void
public function test_marca_unidade_pelo_complemento_sala(): void
```

- [ ] **Step 2: Run to verify they fail.** Python: comando da Task 1. PHP: `php artisan test --compact tests/Feature/TreinoIa/ExportarTesteCegoCommandTest.php`.
- [ ] **Step 3: Implement.** A página `galeria.html` é estática, com JavaScript embutido para ordenar por qualquer indicador, filtrar por faixa de área e testar limites de IoU e de erro de divisa (contagem de lotes que passariam). Sem dependência externa.
- [ ] **Step 4: Run to verify they pass.**
- [ ] **Step 5: Commit** — `git commit -m "feat: avaliação das variantes, teste cego exportado do Viabiliza e galeria dos resultados"`

---

### Task 8: Galeria na gestão, atrás de permissão e parâmetro

**Files:**
- Create: `app/Http/Controllers/Gestao/TreinoIaGaleriaController.php`, `resources/js/pages/gestao/treino-ia/index.tsx`
- Modify: `routes/gestao.php` (dentro do grupo `permission:manter-parametros`, junto de `config-apis`), `database/seeders/ParameterSeeder.php` (novo `features.treino_ia_galeria`), `config/viabiliza.php` (`'treino_ia_galeria' => false` em `features`)
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php:18,853` e `tests/Feature/Seeders/DatabaseSeederTest.php:104,401` (123 → 124)
- Test: `tests/Feature/Gestao/TreinoIaGaleriaControllerTest.php`

**Interfaces:**
- Consumes: `storage/app/treino-ia/galerias/<execucao>/{index.html,resumo.json,img/*.png}`.
- Produces: `GET /gestao/treino-ia` (`gestao.treino-ia.index`, Inertia `gestao/treino-ia/index` com `execucoes: list<{execucao, gerada_em, lotes, variantes, mediana_iou_por_variante}>`) e `GET /gestao/treino-ia/{execucao}/{arquivo?}` (`gestao.treino-ia.arquivo`, serve `index.html` por padrão), ambos 404 com `Settings::enabled('treino_ia_galeria')` falso.

- [ ] **Step 1: Write the failing tests**

```php
public function test_parametro_desligado_devolve_404(): void
public function test_sem_manter_parametros_devolve_403(): void
public function test_lista_execucoes_a_partir_dos_resumos(): void
public function test_serve_o_index_e_as_imagens_da_execucao(): void
public function test_caminho_com_ponto_ponto_ou_absoluto_devolve_404(): void
// arquivo = '../../.env', '%2e%2e/%2e%2e/.env', '/etc/passwd', execução inexistente
public function test_sem_galerias_mostra_estado_vazio(): void
public function test_parametro_nasce_desligado(): void
```

Use `Storage::fake('local')` e `User::factory()->administrador()->withAcceptedLgpdTerm()->create()`.

- [ ] **Step 2: Run to verify they fail** — `php artisan test --compact tests/Feature/Gestao/TreinoIaGaleriaControllerTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement.** O arquivo é resolvido com `realpath` e precisa começar com o `realpath` de `storage/app/treino-ia/galerias/<execucao>`; extensões aceitas `html`, `json`, `png`. Página com `GestaoLayout`, `PageHeader` e `EmptyState`, uma linha por execução com link para a galeria (nova aba) e a mediana do IoU por variante. Sem item no menu: o endereço é `/gestao/treino-ia`.
- [ ] **Step 4: Run to verify they pass**, e depois `php artisan test --compact tests/Feature/Seeders` e `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 5: Commit** — `git commit -m "feat: galeria do treino da IA na gestão, só para quem mantém parâmetros e atrás de parâmetro desligado"`

---

### Task 9: Correção do pacote do SAM 2, guia de execução e verificação

**Files:**
- Modify: `docker/footprint-ia/requirements-modelo.txt` (linha do SAM 2 vira `SAM-2 @ https://github.com/facebookresearch/sam2/archive/2b90b9f5ceec907a1c18123530e92e794ad901a4.zip`)
- Create: `scripts/treino-ia/README.md`

**Interfaces:**
- Consumes: todas as tarefas.

- [ ] **Step 1: Verificar a correção do pacote**

Run: `docker run --rm python:3.12-slim pip download --no-deps --no-build-isolation -d /tmp/x "SAM-2 @ https://github.com/facebookresearch/sam2/archive/2b90b9f5ceec907a1c18123530e92e794ad901a4.zip" 2>&1 | tail -2`
Expected: `Successfully downloaded SAM-2` (ou equivalente sem "inconsistent name").

- [ ] **Step 2: Escrever `README.md`** com os comandos do pod, nesta ordem, cada um com `nohup nice -n 19 … > $TREINO_IA_RAIZ/logs/<etapa>.log 2>&1 &`: `instalar.sh`; `python -m treino_ia dados --semente <n>`; `recortes`; `cache`; `treinar --epocas <n>`; `php artisan treino-ia:exportar-teste-cego --semente <n>`; `avaliar teste`; `cego`; `galeria`; `situacao`. Inclui: ligar `features.treino_ia_galeria` em Configuração de APIs/Parâmetros só em homologação; o que fazer antes de uma publicação no SEMIT (rodar `situacao`, esperar o ponto de retomada); como apagar tudo (`rm -rf $TREINO_IA_RAIZ`).

- [ ] **Step 3: Rodar todas as verificações**

Run: `docker run --rm -v /Users/filipefalcao/Downloads/fabrica/sile-poligono-complementar:/w -w /w/scripts/treino-ia viabiliza/footprint-ia:teste-cego sh -c "pip install -q shapely==2.0.6 pytest==8.3.4 && python -m pytest -q"` — Expected: todos passam.
Run: `php artisan test --compact tests/Feature/Gestao/TreinoIaGaleriaControllerTest.php tests/Feature/TreinoIa tests/Feature/Seeders` — Expected: todos passam.

- [ ] **Step 4: Ensaio local de ponta a ponta** com `TREINO_IA_RAIZ` numa pasta temporária, 20 lotes de treino, 5 de teste e 1 época, dentro da imagem `viabiliza/footprint-ia:teste-cego` (com VPN ligada). Expected: `galerias/<execucao>/index.html` abre e mostra 5 lotes com as variantes A, B, D e E.

- [ ] **Step 5: Commit** — `git commit -m "fix: nome do pacote do SAM 2 no requirements do serviço de IA e guia do treino em homologação"`

- [ ] **Step 6: Publicar no SEMIT, só na branch `homologa`**, depois de aprovação explícita do usuário, seguindo a publicação incremental (copiar os arquivos e fazer o merge de 3 vias, sem trocar a `app-src`).

---

### Task 10: Primeiro ciclo de treino em homologação

Esta tarefa é executada em conversa: em cada passo eu entrego o comando, o usuário roda no terminal do pod de homologação e cola a saída, e eu confiro o critério antes do próximo passo. Nenhum passo começa sem o anterior aprovado. Os comandos longos rodam com `nohup nice -n 19` e registram em `$TREINO_IA_RAIZ/logs/`; `python -m treino_ia situacao` mostra o andamento a qualquer momento.

**Interfaces:**
- Consumes: Tasks 1–9 publicadas na `homologa` (Task 9, Step 6) e `features.treino_ia_galeria` ligado só em homologação.
- Produces: `pesos/decodificador_<data>_s<semente>.pt`, `avaliacoes/<execucao>/resultados.json` e a galeria em `https://viabiliza-hml.salvador.ba.gov.br/gestao/treino-ia`.

- [ ] **Step 1: Instalar o ambiente no volume**

Run (pod): `cd /opt/app-root/src && sh scripts/treino-ia/instalar.sh`
Critério: termina com o PyTorch CPU e o SAM 2 importando (`import torch, sam2`) e os pesos `sam2.1-hiera-small` em `$TREINO_IA_RAIZ/py/hf`; `df -h storage/app` com mais de 80 GB livres.

- [ ] **Step 2: Montar o conjunto (600 treino, 60 teste)**

Run (pod): `python -m treino_ia dados --semente <AAAAMMDD do dia>`
Critério: `manifesto.json` com 600 lotes de treino e 60 de teste, bairros de teste sem lote de treino, contagem de descartados por motivo; a semente fica registrada no relatório final.

- [ ] **Step 3: Recortes e medição de tempo nos primeiros 50**

Run (pod): `python -m treino_ia recortes --limite 50`, depois `python -m treino_ia cache --limite 50`
Critério: tempo médio por recorte no cache (codificador) medido e memória de pico abaixo de 4 GB. Com o tempo medido, decidir junto com o usuário: manter 600, reduzir, ou ampliar para ~2.000 numa noite sem publicações.

- [ ] **Step 4: Recortes e cache completos**

Run (pod): `nohup nice -n 19 python -m treino_ia recortes > … &` e, ao terminar, o mesmo para `cache`
Critério: `situacao` mostra 100% dos lotes com recorte e cache, falhas listadas (e repetidas uma vez); combinar com o usuário a janela sem publicações no SEMIT.

- [ ] **Step 5: Treino**

Run (pod): `nohup nice -n 19 python -m treino_ia treinar --epocas 10 --semente <mesma semente> > … &`
Critério: perda caindo nas primeiras épocas (registrada por época no log); se não cair após 3 épocas, parar e revisar taxa de aprendizado antes de seguir. Ao fim, `pesos/decodificador_<data>_s<semente>.pt` gravado.

- [ ] **Step 6: Teste cego com processos reais**

Run (pod): `php artisan treino-ia:exportar-teste-cego --semente <outra semente> --quantidade 60`, depois `python -m treino_ia cego`
Critério: `cego/entrada.json` sem nenhum polígono; `referencia.json` com 60 polígonos; avaliação das variantes A, B, C, D, E sobre esses 60.

- [ ] **Step 7: Avaliação dos 60 lotes de teste e galeria**

Run (pod): `python -m treino_ia avaliar teste`, depois `python -m treino_ia galeria`
Critério: galeria abre em `/gestao/treino-ia` com os 60 lotes de teste e os 60 do teste cego; o resumo traz mediana e quartis de IoU, diferença de área e erro de divisa por variante.

- [ ] **Step 8: Análise com o usuário e próximo ciclo**

Comparar a variante E (treinada) com A (atual) e B (melhor sem treino), por faixa de área e com e sem casos de Cadastro diferente do CODLOG. Escolher a regra da variante F e, se o resultado ainda não for aceitável, definir o próximo ciclo (mais lotes, mais épocas, recorte maior). Registrar os números e a decisão em `docs/evidencias/<data>-treino-ia-ciclo-1/README.md` na branch de dev.

