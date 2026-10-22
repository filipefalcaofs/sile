from treino_ia import config, situacao


def test_sem_etapas_informa_que_nada_comecou(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))

    assert situacao.resumo(config.caminhos()) == "Nenhuma etapa iniciada."


def test_registra_andamento_e_mostra_no_resumo(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    c = config.caminhos()

    situacao.registrar(c, "recortes", feitos=12, total=50, tempo_medio_s=1.5, ultimo_erro="HTTP 500")

    texto = situacao.resumo(c)
    assert "recortes: 12/50" in texto
    assert "1.5 s" in texto
    assert "HTTP 500" in texto
