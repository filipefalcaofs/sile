#!/usr/bin/env python3
"""Liga cada função da contagem à rota, ao controller, ao model e aos testes que a exercitam."""

from __future__ import annotations

import json
import re
import subprocess
from pathlib import Path

RAIZ = Path(__file__).resolve().parents[2]
PASTA = Path(__file__).resolve().parent
CONTAGEM = PASTA / "contagem-viabiliza-completa.json"
ORIGINAL_TECNICO = Path("/tmp/contagem-completa-backup.json")
ROTAS = Path("/tmp/rotas.json")
SAIDA = Path("/tmp/pf-inventario.json")

NOVAS = {
    "Dados - Ficha de vistoria": "model:Inspection,InspectionAttachment,InspectionReferral",
    "Vistoria - encaminhar processo para vistoria": "InspectionController@encaminhar",
    "Vistoria - consultar ficha": "InspectionController@show,InspectionController@visualizar",
    "Vistoria - salvar rascunho da ficha": "InspectionController@update",
    "Vistoria - concluir ficha": "InspectionController@concluir",
    "Vistoria - validar polígono do imóvel": "InspectionController@validarPoligono",
    "Vistoria - anexar foto ou documento": "InspectionController@storeAnexo",
    "Vistoria - baixar anexo": "InspectionController@downloadAnexo",
    "Vistoria - remover anexo": "InspectionController@destroyAnexo",
    "Vistorias - consultar lista": "VistoriaConsultaController@__invoke",
    "Dados - Valor da TLL": "model:TllValor",
    "TLL - consultar valores": "TllValorController@index",
    "TLL - consultar CNAEs do valor": "TllValorController@cnaes",
    "TLL - incluir valor": "TllValorController@store",
    "TLL - alterar valor": "TllValorController@update",
    "TLL - ativar ou inativar valor": "TllValorController@toggleActivation",
    "TLL - criar exercício por fator de reajuste": "TllValorController@gerarExercicio",
    "TLL - publicar exercício": "TllValorController@publicarExercicio",
    "Dados - Via": "model:Via",
    "Vias - consultar": "ViaController@index",
    "Vias - incluir": "ViaController@store",
    "Vias - alterar": "ViaController@update",
    "Vias - ativar ou inativar": "ViaController@toggleActivation",
    "Dados - Regra territorial": "model:TerritorialRule,TerritorialRuleAddress,TerritorialRuleCnae",
    "Regras territoriais - salvar rascunho": "TerritorialRuleController@storeDraft",
    "Regras territoriais - publicar versão": "TerritorialRuleController@publish",
    "Regras territoriais - ativar versão": "TerritorialRuleController@activate",
    "Regras territoriais - descartar rascunho": "TerritorialRuleController@destroyDraft",
    "Dados - Planilha de enquadramento": "model:TratamentoEnquadramento,TratamentoRegra,TratamentoRegraRamo,TratamentoPergunta,TratamentoCondicionante,TratamentoNota,TratamentoPendencia,TratamentoCnluItem,TratamentoCnaeBinding",
    "Enquadramento - importar planilha": "TratamentoPlanilhaController@importar",
    "Enquadramento - publicar versão": "TratamentoPlanilhaController@publicar",
    "Dados - Texto decisório": "model:DecisionText",
    "Textos decisórios - consultar": "DecisionTextController@index",
    "Textos decisórios - alterar": "DecisionTextController@update",
    "Dados - Aceite de declaração de serviço": "model:ServiceDeclarationAcceptance",
    "Renovação - consultar TVLs renováveis": "RenovacaoController@index",
    "Renovação - localizar TVL por CNPJ": "RenovacaoController@consultarCnpj",
    "Renovação - consultar dados da renovação": "RenovacaoController@show",
    "Renovação - protocolar renovação": "RenovacaoController@store",
    "Dados - Chamada de integração": "model:IntegrationCall",
    "Integrações - consultar chamadas": "IntegrationCallController@index",
    "Integrações - consultar detalhe da chamada": "IntegrationCallController@show",
    "APIs - consultar configuração das integrações": "ApiIntegracaoController@edit",
    "APIs - alterar configuração de integração": "ApiIntegracaoController@update",
    "Prazos - alertar vencimento de análise e de pendência": "command:notificacoes:alertar-vencimentos",
    "SLA - escalonar processos em atraso": "command:notificacoes:escalonar-sla",
    "Pendências - expirar convites vencidos e indeferir": "command:pendencias:expirar",
    "Abuso - detectar padrões de abuso": "command:abuso:detectar",
    "Auditoria preditiva - varrer deferimentos do expresso": "command:ia:auditoria-preditiva",
    "TLL - alertar falta de tabela do exercício": "command:tll:alertar-exercicio",
    "Caixa do setor - consultar central de distribuição": "CaixaSetorController@central",
    "Caixa do setor - sugerir distribuição pela carga": "CaixaSetorController@distribuicaoInteligente",
    "Caixa do setor - distribuir em lote": "CaixaSetorController@distribuirLote",
    "Caixa do setor - redistribuir processo": "CaixaSetorController@redistribuir",
    "Malha fina - consultar caixa": "MalhaFinaCaixaController@__invoke",
    "Malha fina - concluir revisão": "MalhaFinaController@concluir",
    "Análise - concluir com deferimento, indeferimento, desativação, cassação ou revogação": "ConclusaoAnaliseController@__invoke",
    "Ficha de análise - sugerir justificativa": "AnalysisRecordController@sugerirJustificativa",
    "Parâmetros - aprovar alteração proposta": "ParameterController@approve",
    "Parâmetros - rejeitar alteração proposta": "ParameterController@reject",
    "Usuários - alterar forma de autenticação": "UserManagementController@updateAuthType",
    "Perfil do usuário - consultar dados pessoais": "ProfileController@edit",
    "Dados externos - Contato cadastral da SEDUR (CPF)": "service:ConsultaCpfSedurService,SedurContatoClient",
    "Dados externos - Cadastro imobiliário da SEFAZ": "service:SedurSefazPropertyRegistryLookup",
    "Dados externos - Contato cadastral da SEDUR (CNPJ)": "service:SedurCnpjLookup,SedurContatoClient",
    "Atendimento - consultar CPF na base da SEDUR": "ConsultarCpfSedurController@atendimento,ConsultarCpfSedurController@contingencia",
    "Contingência - consultar inscrição imobiliária": "ConsultarInscricaoContingenciaController@__invoke",
    "Dados - Notificação ao usuário": "model:notifications",
    "Dados - Registro de acesso": "model:AccessLog",
}


def chave_original(item: dict) -> str:
    obs = item.get("observacoes", "")
    metodo = re.search(r"método (\w+) no (\w+)\.php", obs)
    if metodo:
        return f"{metodo.group(2)}@{metodo.group(1)}"
    model = re.search(r"model (\w+)\.php", obs)
    if model:
        return f"model:{model.group(1)}"
    servico = re.search(r"de (\w+)\.php", obs)
    if servico:
        return f"service:{servico.group(1)}"
    raise SystemExit(f"Sem origem: {item['nome']}")


def arquivo_de(classe: str, pasta: str) -> str | None:
    achados = sorted((RAIZ / pasta).rglob(f"{classe}.php"))
    return str(achados[0].relative_to(RAIZ)) if achados else None


def caminho_fixo(uri: str) -> str:
    fixo = uri.split("{")[0].rstrip("/")
    return fixo if fixo.count("/") >= 2 else ""


def testes_que_citam(termos: list[str]) -> list[str]:
    encontrados: set[str] = set()
    for termo in termos:
        if not termo:
            continue
        resultado = subprocess.run(
            ["rg", "-l", "-F", termo, "tests"], cwd=RAIZ, capture_output=True, text=True
        )
        encontrados.update(l for l in resultado.stdout.splitlines() if l)
    return sorted(encontrados)[:6]


def main() -> None:
    contagem = json.loads(CONTAGEM.read_text(encoding="utf-8"))
    original = json.loads(ORIGINAL_TECNICO.read_text(encoding="utf-8"))
    rotas = json.loads(ROTAS.read_text(encoding="utf-8"))

    rotas_por_acao: dict[str, list[dict]] = {}
    for rota in rotas:
        acao = (rota.get("action") or "").split("\\")[-1]
        if "@" not in acao:
            acao = f"{acao}@__invoke"
        rotas_por_acao.setdefault(acao, []).append({
            "metodo": rota["method"],
            "uri": "/" + rota["uri"].lstrip("/"),
            "nome": rota.get("name"),
            "middleware": [m for m in rota.get("middleware", []) if m.startswith(("can:", "permission:", "auth", "guest"))],
        })

    grupos_originais = original["grupos"]
    total_grupos = len(contagem["grupos"])
    inventario = []

    for gi, grupo in enumerate(contagem["grupos"]):
        if gi < 84:
            origem = grupos_originais[gi]["itens"]
        elif gi == total_grupos - 1:
            origem = grupos_originais[84]["itens"]
        else:
            origem = []

        for ii, item in enumerate(grupo["itens"]):
            chave = chave_original(origem[ii]) if ii < len(origem) else NOVAS[item["nome"]]
            registro = {
                "grupo_id": grupo["id"],
                "grupo": grupo["nome"],
                "indice": ii,
                "nome": item["nome"],
                "tipo": item["tipo"],
                "td_atual": item["td"],
                "ar_atual": item["ar"],
                "chave": chave,
                "rotas": [],
                "controller": None,
                "existe": True,
            }

            if chave.startswith("model:"):
                registro["arquivos"] = [arquivo_de(m, "app/Models") or m for m in chave[6:].split(",")]
                registro["testes"] = testes_que_citam(chave[6:].split(",")[:1])
            elif chave.startswith("service:"):
                registro["arquivos"] = [arquivo_de(s, "app") or s for s in chave[8:].split(",")]
                registro["testes"] = testes_que_citam(chave[8:].split(",")[:1])
            elif chave.startswith("command:"):
                registro["arquivos"] = ["routes/console.php"]
                registro["testes"] = testes_que_citam([chave[8:]])
            else:
                termos = []
                for parte in chave.split(","):
                    controller = parte.split("@")[0]
                    registro["controller"] = arquivo_de(controller, "app/Http/Controllers")
                    achadas = rotas_por_acao.get(parte, [])
                    registro["rotas"].extend(achadas)
                    termos.extend(r["nome"] for r in achadas if r["nome"])
                    termos.extend(caminho_fixo(r["uri"]) for r in achadas)
                    termos.append(controller)
                registro["existe"] = bool(registro["rotas"])
                registro["testes"] = testes_que_citam(termos)

            inventario.append(registro)

    SAIDA.write_text(json.dumps(inventario, ensure_ascii=False, indent=1), encoding="utf-8")
    sem_rota = [r for r in inventario if not r["existe"]]
    sem_teste = [r for r in inventario if not r["testes"]]
    print(f"Funções: {len(inventario)} | sem rota: {len(sem_rota)} | sem teste: {len(sem_teste)}")
    for r in sem_rota:
        print("  SEM ROTA:", r["nome"], r["chave"])
    for r in sem_teste:
        print("  SEM TESTE:", r["nome"], r["chave"])


if __name__ == "__main__":
    main()
