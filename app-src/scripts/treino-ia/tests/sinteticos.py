"""Bairros e lotes sintéticos em volta de Salvador, em graus."""

import math

LAT0, LNG0 = -12.98, -38.47
M_LAT = 110574
M_LNG = 111320 * math.cos(math.radians(LAT0))


def retangulo(x_m, y_m, largura_m, altura_m):
    def ponto(dx, dy):
        return [LNG0 + dx / M_LNG, LAT0 + dy / M_LAT]

    return {"type": "Polygon", "coordinates": [[
        ponto(x_m, y_m), ponto(x_m + largura_m, y_m), ponto(x_m + largura_m, y_m + altura_m),
        ponto(x_m, y_m + altura_m), ponto(x_m, y_m),
    ]]}


def bairros(n=4, lado_m=500):
    return [
        {"type": "Feature", "properties": {"NOME_BAIRRO": f"Bairro {i}"}, "geometry": retangulo(i * lado_m, 0, lado_m, lado_m)}
        for i in range(n)
    ]


def lote(codlog, numero, x_m, y_m, largura_m=30, altura_m=20):
    return {"type": "Feature", "properties": {"CODLOG": codlog, "NUMERACAO_METRICA": numero}, "geometry": retangulo(x_m, y_m, largura_m, altura_m)}


def lotes_em_grade(n_bairros=4, por_bairro=10, lado_m=500):
    saida = []
    for b in range(n_bairros):
        for k in range(por_bairro):
            saida.append(lote(100 + b, k + 1, b * lado_m + 40 + (k % 5) * 80, 60 + (k // 5) * 80))
    return saida
