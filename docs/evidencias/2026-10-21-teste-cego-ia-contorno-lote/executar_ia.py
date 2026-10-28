"""Teste cego do contorno por IA: reproduz OrtofotoClient + InferenciaEdificacaoClient +
AceiteMascaraIa com o ponto do Cadastro, sem nenhuma informação do polígono do CODLOG."""
import json, math, sys, urllib.parse, urllib.request
from datetime import datetime, timezone

PASTA = sys.argv[1]
ETAPA = sys.argv[2]  # recorte | inferencia
LAT, LNG = -12.996143, -38.465385
LADO_M, PIXELS = 60.0, 800
URL_ORTO = 'https://geo.salvador.ba.gov.br/imageserver/rest/services/Ortofotos/2024/ImageServer'
URL_IA = 'http://127.0.0.1:8080'

meio_lat = LADO_M / 2 / 110574
meio_lng = LADO_M / 2 / (111320 * math.cos(math.radians(LAT)))
oeste, sul, leste, norte = LNG - meio_lng, LAT - meio_lat, LNG + meio_lng, LAT + meio_lat


def pixel(lat, lng):
    return (round((lng - oeste) / (leste - oeste) * PIXELS), round((norte - lat) / (norte - sul) * PIXELS))


def coordenada(x, y):
    return [oeste + x / PIXELS * (leste - oeste), norte - y / PIXELS * (norte - sul)]


if ETAPA == 'recorte':
    q = urllib.parse.urlencode({'bbox': f'{oeste},{sul},{leste},{norte}', 'bboxSR': 4326, 'imageSR': 4326,
                                'size': f'{PIXELS},{PIXELS}', 'format': 'png', 'f': 'image'})
    with urllib.request.urlopen(URL_ORTO + '/exportImage?' + q, timeout=60) as r:
        tipo = r.headers.get('Content-Type'); dados = r.read()
    open(f'{PASTA}/recorte.png', 'wb').write(dados)
    json.dump({'ponto': {'lat': LAT, 'lng': LNG}, 'caixa_4326': [oeste, sul, leste, norte], 'lado_m': LADO_M,
               'pixels': PIXELS, 'servico': URL_ORTO, 'ano': '2024', 'content_type': tipo, 'bytes': len(dados),
               'pixel_do_ponto': pixel(LAT, LNG), 'baixado_em': datetime.now(timezone.utc).isoformat()},
              open(f'{PASTA}/recorte.json', 'w'), indent=2)
    print(tipo, len(dados), pixel(LAT, LNG))
    sys.exit()

x, y = pixel(LAT, LNG)
limite = '----testecego'
corpo = b''
for nome, valor in [('x', x), ('y', y), ('largura', PIXELS), ('altura', PIXELS)]:
    corpo += f'--{limite}\r\nContent-Disposition: form-data; name="{nome}"\r\n\r\n{valor}\r\n'.encode()
corpo += f'--{limite}\r\nContent-Disposition: form-data; name="imagem"; filename="recorte.png"\r\nContent-Type: image/png\r\n\r\n'.encode()
corpo += open(f'{PASTA}/recorte.png', 'rb').read() + f'\r\n--{limite}--\r\n'.encode()
req = urllib.request.Request(URL_IA + '/segmentar', data=corpo, headers={'Content-Type': f'multipart/form-data; boundary={limite}'})
inicio = datetime.now(timezone.utc)
with urllib.request.urlopen(req, timeout=600) as r:
    resposta = json.load(r)
fim = datetime.now(timezone.utc)
json.dump(resposta, open(f'{PASTA}/resposta_ia_bruta.json', 'w'), indent=2)

# Aceite: mesmas regras e constantes do AceiteMascaraIa (margem 2 px, área 20-5000 m², compacidade 0,2)
anel = resposta.get('anel') or []
motivo = None
if resposta.get('status') != 'ok' or len(anel) < 3:
    motivo = 'sem_objeto'
elif any(px <= 2 or py <= 2 or px >= PIXELS - 3 or py >= PIXELS - 3 for px, py in anel):
    motivo = 'encosta_na_borda'
coords = [coordenada(px, py) for px, py in anel]
dentro = False
for i in range(len(coords)):
    xi, yi = coords[i]; xj, yj = coords[i - 1]
    if (yi > LAT) != (yj > LAT) and LNG < (xj - xi) * (LAT - yi) / (yj - yi) + xi:
        dentro = not dentro
mx, my = 111320 * math.cos(math.radians(LAT)), 110574
pts = [(c[0] * mx, c[1] * my) for c in coords]
area = abs(sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1] for i in range(len(pts)))) / 2 if pts else 0
perim = sum(math.dist(pts[i], pts[(i + 1) % len(pts)]) for i in range(len(pts))) if pts else 0
compacidade = 4 * math.pi * area / perim ** 2 if perim else 0
if motivo is None and not dentro:
    motivo = 'nao_contem_o_ponto'
if motivo is None and not (20 <= area <= 5000):
    motivo = 'area_fora_dos_limites'
if motivo is None and compacidade < 0.2:
    motivo = 'compacidade_baixa'
geo = {'type': 'Polygon', 'coordinates': [coords + coords[:1]]} if coords else None
registro = {'aceito_pelo_sistema': motivo is None, 'motivo_recusa': motivo, 'area_m2': round(area, 2),
            'perimetro_m': round(perim, 2), 'compacidade': round(compacidade, 3), 'contem_o_ponto': dentro,
            'score_modelo': resposta.get('score'), 'modelo': resposta.get('modelo'), 'vertices': len(anel),
            'pixel_do_ponto': [x, y], 'inicio': inicio.isoformat(), 'fim': fim.isoformat(),
            'segundos': round((fim - inicio).total_seconds(), 1), 'poligono_ia_4326': geo}
json.dump(registro, open(f'{PASTA}/resultado_ia.json', 'w'), indent=2)
print(json.dumps({k: v for k, v in registro.items() if k != 'poligono_ia_4326'}, indent=2))
