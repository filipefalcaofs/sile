import base64
import io
import json
from types import SimpleNamespace

import numpy as np
from PIL import Image

from treino_ia import juiza


class ClienteFalso:
    def __init__(self, texto='{"escolha": 2, "justificativa": "contorna o lote"}', stop_reason="end_turn"):
        self.pedidos = []
        self.texto = texto
        self.stop_reason = stop_reason
        self.beta = SimpleNamespace(messages=SimpleNamespace(create=self._create))

    def _create(self, **pedido):
        self.pedidos.append(pedido)
        conteudo = [] if self.stop_reason == "refusal" else [SimpleNamespace(type="text", text=self.texto)]
        return SimpleNamespace(stop_reason=self.stop_reason, content=conteudo, stop_details=None)


def _foto():
    return np.random.default_rng(0).integers(0, 255, (800, 800, 3), dtype=np.uint8)


ANEIS = [[[300, 300], [500, 300], [500, 500], [300, 500]], [[200, 200], [600, 200], [600, 600], [200, 600]]]


def test_escolha_devolve_o_numero_do_candidato_e_a_justificativa():
    cliente = ClienteFalso()

    r = juiza.Juiza(cliente, provedor="anthropic").escolher(_foto(), ANEIS, 400, 400)

    assert r == {"escolha": 2, "justificativa": "contorna o lote", "recusa": False}


def test_pedido_leva_so_a_imagem_com_os_candidatos_e_o_texto():
    cliente = ClienteFalso()

    juiza.Juiza(cliente, provedor="anthropic").escolher(_foto(), ANEIS, 400, 400)

    pedido = cliente.pedidos[0]
    assert pedido["model"] == "claude-opus-5-5"
    assert pedido["fallbacks"] == "default"
    assert "server-side-fallback-2026-07-01" in pedido["betas"]
    assert pedido["output_config"]["format"]["type"] == "json_schema"
    blocos = pedido["messages"][0]["content"]
    assert [b["type"] for b in blocos] == ["image", "text"]
    png = Image.open(io.BytesIO(base64.b64decode(blocos[0]["source"]["data"])))
    assert png.size == (800, 800)
    assert "2" in blocos[1]["text"]


def test_zero_quer_dizer_que_nenhum_candidato_serve():
    r = juiza.Juiza(provedor="anthropic", cliente=ClienteFalso('{"escolha": 0, "justificativa": "nenhum"}')).escolher(_foto(), ANEIS, 400, 400)

    assert r["escolha"] is None and r["recusa"] is False


def test_numero_fora_dos_candidatos_vira_nenhum():
    r = juiza.Juiza(provedor="anthropic", cliente=ClienteFalso('{"escolha": 7, "justificativa": "?"}')).escolher(_foto(), ANEIS, 400, 400)

    assert r["escolha"] is None


def test_recusa_do_modelo_vira_nenhum_marcado_como_recusa():
    r = juiza.Juiza(provedor="anthropic", cliente=ClienteFalso(stop_reason="refusal")).escolher(_foto(), ANEIS, 400, 400)

    assert r == {"escolha": None, "justificativa": None, "recusa": True}


def test_sem_candidatos_nao_chama_a_api():
    cliente = ClienteFalso()

    r = juiza.Juiza(cliente, provedor="anthropic").escolher(_foto(), [], 400, 400)

    assert r["escolha"] is None and cliente.pedidos == []


def test_resposta_sem_texto_vira_nenhum():
    cliente = ClienteFalso()
    cliente.texto = None
    cliente.beta.messages.create = lambda **p: SimpleNamespace(stop_reason="max_tokens", content=[], stop_details=None)

    assert juiza.Juiza(cliente, provedor="anthropic").escolher(_foto(), ANEIS, 400, 400)["escolha"] is None


class ClienteOpenAIFalso:
    def __init__(self, texto='{"escolha": 1, "justificativa": "lote com estacionamento"}', recusa=None):
        self.pedidos = []
        self.texto, self.recusa = texto, recusa
        self.chat = SimpleNamespace(completions=SimpleNamespace(create=self._create))

    def _create(self, **pedido):
        self.pedidos.append(pedido)
        mensagem = SimpleNamespace(content=None if self.recusa else self.texto, refusal=self.recusa)
        return SimpleNamespace(choices=[SimpleNamespace(message=mensagem, finish_reason="stop")])


def test_openai_e_o_provedor_padrao_com_o_modelo_do_viabiliza(monkeypatch):
    monkeypatch.delenv("TREINO_IA_JUIZA_PROVEDOR", raising=False)
    monkeypatch.delenv("TREINO_IA_JUIZA_MODELO", raising=False)

    assert juiza.provedor() == "openai"
    assert juiza.modelo("openai") == "gpt-4o-mini"
    assert juiza.modelo("anthropic") == "claude-opus-5-5"


def test_openai_recebe_a_imagem_e_devolve_a_escolha():
    cliente = ClienteOpenAIFalso()

    r = juiza.Juiza(cliente, provedor="openai").escolher(_foto(), ANEIS, 400, 400)

    assert r == {"escolha": 1, "justificativa": "lote com estacionamento", "recusa": False}
    pedido = cliente.pedidos[0]
    assert pedido["model"] == "gpt-4o-mini"
    assert pedido["response_format"]["type"] == "json_schema"
    assert pedido["response_format"]["json_schema"]["strict"] is True
    blocos = pedido["messages"][0]["content"]
    assert [b["type"] for b in blocos] == ["image_url", "text"]
    url = blocos[0]["image_url"]["url"]
    assert url.startswith("data:image/png;base64,")
    assert Image.open(io.BytesIO(base64.b64decode(url.split(",", 1)[1]))).size == (800, 800)


def test_openai_recusa_vira_nenhum_marcado_como_recusa():
    r = juiza.Juiza(ClienteOpenAIFalso(recusa="não posso ajudar"), provedor="openai").escolher(_foto(), ANEIS, 400, 400)

    assert r == {"escolha": None, "justificativa": None, "recusa": True}


def test_openai_escolha_zero_e_fora_da_lista_vira_nenhum():
    for texto in ('{"escolha": 0, "justificativa": "nenhum"}', '{"escolha": 9, "justificativa": "?"}'):
        assert juiza.Juiza(ClienteOpenAIFalso(texto), provedor="openai").escolher(_foto(), ANEIS, 400, 400)["escolha"] is None
