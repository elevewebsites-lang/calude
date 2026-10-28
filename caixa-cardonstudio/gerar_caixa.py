"""Caixa com tampa articulada (dobradiça) e logo branca embutida na tampa.

Gera os STLs prontos para fatiar:
  stl/caixa_base.stl          -> corpo da caixa (imprime em pé, boca para cima)
  stl/tampa_corpo.stl         -> tampa na cor da caixa (imprime de cabeça para baixo)
  stl/tampa_logo_branco.stl   -> logo branca embutida na tampa (mesma posição da tampa)
  stl/pino_dobradica.stl      -> pino opcional (ou use um pedaço de filamento 1,75 mm)

Uso: python3 gerar_caixa.py
Todas as medidas em milímetros. Mude os parâmetros abaixo e rode de novo.
"""
import json
import math
from pathlib import Path

import numpy as np
import trimesh
from manifold3d import CrossSection, FillRule, JoinType, Manifold
from matplotlib.font_manager import FontProperties
from matplotlib.textpath import TextPath

# ---------------- parâmetros ----------------
LARGURA = 200.0        # X (frente)
PROFUNDIDADE = 200.0   # Y
ALTURA = 300.0         # altura total com a tampa fechada
PAREDE = 2.0           # espessura das paredes, do fundo e do topo da tampa
RAIO_CANTO = 8.0       # cantos arredondados (vista de cima)

ALTURA_TAMPA = 15.0    # altura da aba da tampa
ABA_ENCAIXE = 6.0      # quanto a borda interna da tampa entra na caixa
FOLGA = 0.25           # folga do encaixe (por lado)
ESP_ENCAIXE = 1.6      # espessura da borda interna

R_DOBRADICA = 5.0      # raio das articulações
FURO_PINO = 2.0        # furo do pino (filamento 1,75 mm) — use 3.2 para pino/parafuso M3
N_ARTICULACOES = 5     # base fica com as ímpares, tampa com as pares
COMPR_DOBRADICA = 160.0
FOLGA_ARTICULACAO = 0.4

PROF_LOGO = 0.6        # profundidade da logo embutida (3 camadas de 0,2 mm)
FONTE = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
TEXTO_1 = "CARDON"
TEXTO_2 = "STUDIO 3D"

SEG = 96
OUT = Path(__file__).parent
# --------------------------------------------

HB = ALTURA - ALTURA_TAMPA            # altura do corpo
Y_EIXO = PROFUNDIDADE / 2 + R_DOBRADICA + 0.3  # 0,3 mm de folga para a articulação da tampa não raspar na parede
Z_EIXO = HB


def retangulo(w, d, r):
    return CrossSection.square([w - 2 * r, d - 2 * r], center=True).offset(r, JoinType.Round, circular_segments=SEG)


def prisma_x(perfil_yz, x0, x1):
    """Extruda um perfil desenhado no plano (y, z) ao longo do eixo X."""
    m = perfil_yz.extrude(x1 - x0)
    return m.transform([[0, 0, 1, x0], [1, 0, 0, 0], [0, 1, 0, 0]])


def articulacao(z_ini, z_fim):
    circ = CrossSection.circle(R_DOBRADICA, SEG).translate([Y_EIXO, Z_EIXO])
    parede = CrossSection.square([0.01, z_fim - z_ini]).translate([PROFUNDIDADE / 2 - 0.01, z_ini])
    perfil = CrossSection.batch_hull([circ, parede])
    # corta a ponta do perfil que invadiria a peça vizinha (parede da caixa ou aba da tampa)
    acima = z_ini >= Z_EIXO
    vizinha = CrossSection.square([50, 50]).translate([PROFUNDIDADE / 2 - 50, Z_EIXO - 50 if acima else Z_EIXO])
    return perfil - vizinha


def segmentos():
    passo = COMPR_DOBRADICA / N_ARTICULACOES
    for i in range(N_ARTICULACOES):
        x0 = -COMPR_DOBRADICA / 2 + i * passo + (FOLGA_ARTICULACAO / 2 if i else 0)
        x1 = -COMPR_DOBRADICA / 2 + (i + 1) * passo - (FOLGA_ARTICULACAO / 2 if i < N_ARTICULACOES - 1 else 0)
        yield i, x0, x1


def furo_pino():
    return prisma_x(CrossSection.circle(FURO_PINO / 2, 32).translate([Y_EIXO, Z_EIXO]),
                    -COMPR_DOBRADICA / 2 - 1, COMPR_DOBRADICA / 2 + 1)


def base():
    externo = retangulo(LARGURA, PROFUNDIDADE, RAIO_CANTO)
    interno = externo.offset(-PAREDE, JoinType.Round, circular_segments=SEG)
    corpo = externo.extrude(HB) - interno.extrude(HB).translate([0, 0, PAREDE])
    perfil = articulacao(HB - 3 * R_DOBRADICA, HB)
    for i, x0, x1 in segmentos():
        if i % 2 == 0:
            corpo += prisma_x(perfil, x0, x1)
    return corpo - furo_pino()


def logo_2d():
    prop = FontProperties(fname=FONTE)

    def texto(s, largura):
        polys = [p for p in TextPath((0, 0), s, size=100, prop=prop).to_polygons(closed_only=True) if len(p) > 2]
        cs = CrossSection(polys, FillRule.EvenOdd)
        x0, y0, x1, y1 = cs.bounds()
        k = largura / (x1 - x0)
        return cs.translate([-(x0 + x1) / 2, -(y0 + y1) / 2]).scale([k, k]), (y1 - y0) * k

    util = min(LARGURA, PROFUNDIDADE)
    t1, h1 = texto(TEXTO_1, util * 0.72)
    t2, h2 = texto(TEXTO_2, util * 0.46)
    gap = util * 0.05
    linha_h = 1.6
    total = h1 + gap + linha_h + gap + h2
    topo = total / 2
    t1 = t1.translate([0, topo - h1 / 2])
    linha = CrossSection.square([util * 0.46, linha_h], center=True).translate([0, topo - h1 - gap - linha_h / 2])
    t2 = t2.translate([0, -topo + h2 / 2])
    # a frente da caixa fica em -Y: o texto é lido de frente
    return t1 + linha + t2


def tampa():
    externo = retangulo(LARGURA, PROFUNDIDADE, RAIO_CANTO)
    interno_base = externo.offset(-PAREDE, JoinType.Round, circular_segments=SEG)
    enc_ext = interno_base.offset(-FOLGA, JoinType.Round, circular_segments=SEG)
    enc_int = enc_ext.offset(-ESP_ENCAIXE, JoinType.Round, circular_segments=SEG)

    corpo = externo.extrude(ALTURA_TAMPA).translate([0, 0, HB])
    corpo -= enc_int.extrude(ALTURA_TAMPA - PAREDE).translate([0, 0, HB])
    corpo += (enc_ext - enc_int).extrude(ABA_ENCAIXE).translate([0, 0, HB - ABA_ENCAIXE])

    # puxador na frente (chanfro de 45° para imprimir sem suporte de cabeça para baixo)
    puxador = CrossSection([[[0, HB], [6, HB], [6, HB + 3], [0, HB + 9]]])
    puxador = puxador.mirror([1, 0]).translate([-PROFUNDIDADE / 2 + 0.5, 0])
    corpo += prisma_x(puxador, -20, 20)

    perfil = articulacao(HB, HB + ALTURA_TAMPA)
    for i, x0, x1 in segmentos():
        if i % 2 == 1:
            corpo += prisma_x(perfil, x0, x1)
    corpo -= furo_pino()

    logo = logo_2d().extrude(PROF_LOGO).translate([0, 0, ALTURA - PROF_LOGO])
    return corpo - logo, logo


def pino():
    return Manifold.cylinder(COMPR_DOBRADICA + 4, FURO_PINO / 2 - 0.15, circular_segments=32)


def para_trimesh(m):
    mesh = m.to_mesh()
    return trimesh.Trimesh(np.asarray(mesh.vert_properties)[:, :3], np.asarray(mesh.tri_verts), process=False)


def girar_tampa(m, graus):
    a = math.radians(graus)
    c, s = math.cos(a), math.sin(a)
    # gira em torno do eixo da dobradiça: a frente sobe e vai para trás
    rot = np.array([[1, 0, 0], [0, c, s], [0, -s, c]])
    p = np.array([0, Y_EIXO, Z_EIXO])
    t = p - rot @ p
    return m.transform(np.hstack([rot, t[:, None]]).tolist())


def main():
    (OUT / "stl").mkdir(exist_ok=True)
    b = base()
    tc, tl = tampa()

    # checagem: a tampa abre sem bater na caixa
    pior = 0.0
    for g in range(0, 181, 5):
        v = (girar_tampa(tc, g) ^ b).volume()
        pior = max(pior, v)
        if v > 0.01:
            print(f"  aviso: colisão de {v:.2f} mm³ a {g}°")
            break
    print(f"abertura testada até {g}°, colisão máx {pior:.3f} mm³")

    # tampa de cabeça para baixo para imprimir: logo encostada na mesa
    virar = [[1, 0, 0, 0], [0, -1, 0, 0], [0, 0, -1, ALTURA]]
    arquivos = {
        "caixa_base.stl": b,
        "tampa_corpo.stl": tc.transform(virar),
        "tampa_logo_branco.stl": tl.transform(virar),
        "pino_dobradica.stl": pino(),
    }
    for nome, m in arquivos.items():
        t = para_trimesh(m)
        assert t.is_watertight, nome
        t.export(OUT / "stl" / nome)
        print(f"{nome:26s} {t.extents.round(1)} mm  vol {t.volume / 1000:.1f} cm³")

    # modelos na posição montada para a pré-visualização animada
    (OUT / "preview").mkdir(exist_ok=True)
    for nome, m in {"base": b, "tampa": tc, "logo": tl}.items():
        para_trimesh(m).export(OUT / "preview" / f"{nome}.stl")
    (OUT / "preview" / "eixo.json").write_text(json.dumps({"y": Y_EIXO, "z": Z_EIXO}))


if __name__ == "__main__":
    main()
