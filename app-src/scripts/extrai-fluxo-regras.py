#!/usr/bin/env python3
"""Extrai a tabela de decisão (ramo → fluxo) dos textos oficiais das regras.

Lê database/data/regras-20-08-26/regras.csv e emite regras-fluxo.csv na mesma
pasta. A gramática dos bullets da SEDUR:

  Se a resposta for "SIM"/"NÃO" na pergunta X [e/com área menor ou igual a
  1.250 m² | área superior a 1.250m²] [e/tipo de espaço/imóvel "GALPÃO" ...]
  ... (código LOUOS – 07.12.13)

Bullets fora dessa gramática vão ao relatório de pendências (stdout) — NUNCA
inferência silenciosa: o que não parseia não vira dado.
"""

import csv
import re
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent / "database" / "data" / "regras-20-08-26"
REGRAS = BASE / "regras.csv"
SAIDA = BASE / "regras-fluxo.csv"

SECAO = re.compile(r"Fluxo\s+(Semi\s?-?\s?[Ee]xpresso|Semiexpresso|Semi Expresso|Expresso)", re.IGNORECASE)
BULLET = re.compile(r"Se a resposta for\s+[“\"']?(SIM|NÃO|NAO)[”\"'']?(?:\s+na\s+pergunta\s+(\d+))?(.*?)(?=Se a resposta for|\Z)", re.IGNORECASE | re.DOTALL)
AREA = re.compile(r"[áa]rea\s+(menor ou igual a|superior a)\s*1[.,]?250\s*m", re.IGNORECASE)
TIPO = re.compile(r"(GALPÃO|GALPAO|CONTAINER|EDIFICAÇÃO RESIDENCIAL|EDIFICACAO RESIDENCIAL)", re.IGNORECASE)
LOUOS = re.compile(r"código LOUOS\s*[–-]\s*([\d.]+)", re.IGNORECASE)


def fluxo_da_secao(rotulo: str) -> str:
    return "semiexpresso" if "semi" in rotulo.lower() else "expresso"


def main() -> int:
    with REGRAS.open() as f:
        regras = {r[0]: r[1] for r in csv.reader(f) if r and r[0].isdigit()}

    linhas = []
    pendencias = []
    vistos = set()

    for numero, texto in regras.items():
        partes = SECAO.split(texto)

        if len(partes) < 3:
            pendencias.append(f"regra {numero}: sem seção de fluxo reconhecida")
            continue

        for i in range(1, len(partes) - 1, 2):
            fluxo = fluxo_da_secao(partes[i])
            corpo = partes[i + 1]

            for bullet in BULLET.finditer(corpo):
                # Bullet sem "na pergunta X" (ex.: regra 50): a pergunta fica
                # vazia e o resolver casa com a pergunta vinculada do CNAE.
                resposta, pergunta, resto = bullet.group(1), bullet.group(2) or "", bullet.group(3)
                area = AREA.search(resto)
                faixa = ""
                if area:
                    faixa = "ate_1250" if "menor" in area.group(1).lower() else "acima_1250"

                tipo = "1" if TIPO.search(resto) else ""
                codigos = LOUOS.findall(resto)
                codigo = codigos[0] if codigos else ""

                if not codigo:
                    pendencias.append(f"regra {numero}: bullet sem código LOUOS ({fluxo}, P{pergunta}={resposta})")

                # O texto oficial repete bullets idênticos — dedup pela chave
                # do ramo, senão o upsert por chave perde a idempotência.
                chave = (numero, pergunta, resposta.upper().replace("NAO", "NÃO"), faixa, tipo, codigo, fluxo)

                if chave in vistos:
                    continue

                vistos.add(chave)
                linhas.append([numero, pergunta, resposta.upper().replace("NAO", "NÃO"), faixa, tipo, codigo, fluxo])

            # Bullets sem "Se a resposta for" na seção (ex.: condição solta)
            if not list(BULLET.finditer(corpo)):
                pendencias.append(f"regra {numero}: seção {fluxo} sem bullet parseável")

    with SAIDA.open("w", newline="") as f:
        w = csv.writer(f)
        w.writerow(["regra", "pergunta", "resposta", "faixa", "tipo_dirige", "codigo_louos", "fluxo"])
        w.writerows(linhas)

    # Suplemento curado à mão (gramáticas fora do padrão, ex.: regra 27 —
    # "Se selecionado as opções 1, 2 e 4 na pergunta 20"): mesclado DEPOIS do
    # extraído, sem sobrescrever (a chave do ramo é única).
    manual = BASE / "regras-fluxo.manual.csv"
    mescladas = 0

    if manual.is_file():
        existentes = {(str(r[0]), str(r[1]), str(r[2]), str(r[3]), str(r[4]), str(r[5])) for r in linhas}

        with manual.open() as f:
            for row in csv.DictReader(f):
                chave = (row["regra"], row["pergunta"], row["resposta"], row["faixa"], row["tipo_dirige"], row["codigo_louos"])

                if chave in existentes:
                    continue

                linhas.append([row["regra"], row["pergunta"], row["resposta"], row["faixa"], row["tipo_dirige"], row["codigo_louos"], row["fluxo"]])
                mescladas += 1

        with SAIDA.open("w", newline="") as f:
            w = csv.writer(f)
            w.writerow(["regra", "pergunta", "resposta", "faixa", "tipo_dirige", "codigo_louos", "fluxo"])
            w.writerows(linhas)

    print(f"ramos extraídos: {len(linhas)} ({mescladas} curados à mão) → {SAIDA.name}")
    print(f"pendências de parse: {len(pendencias)}")
    for p in pendencias:
        print(" -", p)

    return 0


if __name__ == "__main__":
    sys.exit(main())
