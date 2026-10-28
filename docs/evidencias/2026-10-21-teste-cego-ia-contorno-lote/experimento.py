"""Variantes de prompt e de escolha de máscara do SAM 2 sobre os recortes do Maxcenter.
Roda dentro da imagem do footprint-ia. Só usa ortofoto, ponto do Cadastro, área do
Cadastro e eixo do logradouro; o polígono do CODLOG não entra aqui."""
import json, math, sys
import cv2
import numpy as np
from PIL import Image
from sam2.sam2_image_predictor import SAM2ImagePredictor

D = sys.argv[1]
LAT, LNG = -12.996143, -38.465385
AREA_CADASTRO = 36.0
AREA_DIAGNOSTICO = float(sys.argv[2]) if len(sys.argv) > 2 else None
PX = 800
modelo = SAM2ImagePredictor.from_pretrained('facebook/sam2.1-hiera-small', device='cpu')
eixo = json.load(open(f'{D}/eixo_acm.json'))[0]['coordinates']
mlng = 111320 * math.cos(math.radians(LAT))


def contexto(lado):
    o, s, l, n = json.load(open(f'{D}/recorte_{lado}.json'))['caixa']
    img = np.array(Image.open(f'{D}/recorte_{lado}.png').convert('RGB'))
    pix = lambda la, lo: (round((lo - o) / (l - o) * PX), round((n - la) / (n - s) * PX))
    geo = lambda x, y: [o + x / PX * (l - o), n - y / PX * (n - s)]
    return img, pix, geo, (lado / PX) ** 2


def anel(mascara, x, y):
    cs, _ = cv2.findContours(mascara.astype(np.uint8), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    cs = [c for c in cs if cv2.pointPolygonTest(c, (float(x), float(y)), False) >= 0]
    if not cs:
        return None
    return [[float(p[0][0]), float(p[0][1])] for p in cv2.approxPolyDP(max(cs, key=cv2.contourArea), 1.5, True)]


def encosta(a):
    return any(px <= 2 or py <= 2 or px >= PX - 3 or py >= PX - 3 for px, py in a)


def negativos(pix):
    pts = []
    for (a, b), (c, d) in zip(eixo, eixo[1:]):
        for t in np.linspace(0, 1, 12):
            x, y = pix(b + (d - b) * t, a + (c - a) * t)
            if 5 <= x < PX - 5 and 5 <= y < PX - 5:
                pts.append([x, y])
    return pts[::2]


def prever(lado, neg=False, caixa_m2=None):
    img, pix, geo, m2px = contexto(lado)
    x, y = pix(LAT, LNG)
    modelo.set_image(img)
    coords, labels = [[x, y]], [1]
    if neg:
        n = negativos(pix)
        coords += n; labels += [0] * len(n)
    kw = {}
    if caixa_m2:
        meio = math.sqrt(caixa_m2) / 2 / (lado / PX)
        kw['box'] = np.array([x - meio, y - meio, x + meio, y + meio], dtype=np.float32)
    ms, sc, _ = modelo.predict(point_coords=np.array(coords, dtype=np.float32), point_labels=np.array(labels, dtype=np.int32), multimask_output=True, **kw)
    saida = []
    for m, s in zip(ms, sc):
        a = anel(m > 0, x, y)
        if a is None or len(a) < 3:
            continue
        area = cv2.contourArea(np.array(a, dtype=np.float32)) * m2px
        g = [geo(px, py) for px, py in a]
        saida.append({'lado': lado, 'score': round(float(s), 4), 'area_m2': round(area, 1), 'encosta': encosta(a), 'negativos': len(coords) - 1, 'geo': g + g[:1]})
    return saida


res = {}
res['A_atual'] = max(prever(60), key=lambda m: m['score'])
res['B_mascaras'] = {lado: prever(lado) for lado in (60, 120, 240)}
todas60 = res['B_mascaras'][60]
res['C_area_cadastro_36'] = min(todas60, key=lambda m: abs(math.log(m['area_m2'] / AREA_CADASTRO)))


def maior_sem_borda(neg):
    for lado in (60, 120, 240):
        ok = [m for m in prever(lado, neg=neg) if not m['encosta']]
        if ok and len(ok) == len(prever(lado, neg=neg)):
            return max(ok, key=lambda m: m['area_m2'])
        if ok and lado == 240:
            return max(ok, key=lambda m: m['area_m2'])
    return None


res['D_maior_sem_borda'] = maior_sem_borda(False)
res['E_maior_sem_borda_negativos'] = maior_sem_borda(True)
if AREA_DIAGNOSTICO:
    lado = max(60, min(240, 2.5 * math.sqrt(AREA_DIAGNOSTICO)))
    lado = min((60, 120, 240), key=lambda v: abs(v - lado))
    cands = prever(lado, neg=True, caixa_m2=AREA_DIAGNOSTICO * 1.2) + prever(lado, neg=True)
    res['F_diagnostico_area_do_lote'] = min(cands, key=lambda m: abs(math.log(m['area_m2'] / AREA_DIAGNOSTICO)))
json.dump(res, open(f'{D}/experimento.json', 'w'))
for k, v in res.items():
    if k == 'B_mascaras':
        for lado, ms in v.items():
            print(k, lado, [(m['area_m2'], m['score'], m['encosta']) for m in ms])
    elif v:
        print(k, v['lado'], v['area_m2'], v['score'], v['encosta'], v.get('negativos'))
