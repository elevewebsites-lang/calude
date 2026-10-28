# Caixa Cardon Studio 3D

Caixa com tampa de dobradiça (abre e fecha) e a logo branca embutida na tampa, para guardar chaveiros e outros produtos.

- Medida externa fechada: **200 × 200 × 300 mm** (largura × profundidade × altura)
- Paredes, fundo e topo da tampa: **2 mm**
- Espaço interno útil: 196 × 196 × 277 mm
- Prévia animada (abrindo e fechando): `preview/caixa_animada.html`

## Arquivos para imprimir (`stl/`)

| Arquivo | Cor | Como imprimir |
|---|---|---|
| `caixa_base.stl` | cor da caixa | em pé, boca para cima |
| `tampa_corpo.stl` | cor da caixa | já vem de cabeça para baixo (topo na mesa) |
| `tampa_logo_branco.stl` | **branco** | junto com a tampa, na mesma posição |
| `pino_dobradica.stl` | qualquer | opcional, dá para usar um pedaço de filamento 1,75 mm |

### Tampa em duas cores (Bambu Studio / Orca)
1. Importe `tampa_corpo.stl` e `tampa_logo_branco.stl` **juntos**.
2. Quando perguntar se quer carregar como um objeto só com várias peças, responda **Sim**.
3. Coloque a cor da caixa na peça da tampa e **branco** na peça da logo.

A logo fica embutida 0,6 mm (3 camadas de 0,2 mm), rente à superfície e encostada na mesa, por isso sai lisa e sem suporte.

### Montagem
Encaixe a tampa na caixa alinhando as articulações de trás e passe o pino (ou um pedaço de filamento 1,75 mm de 164 mm) pelo furo. Para um pino de metal M3, mude `FURO_PINO = 3.2` e gere de novo.

## Atenção ao tamanho da impressora
A caixa tem 290 mm de altura sem a tampa. Impressoras como Bambu A1/P1/X1 (256 mm) não alcançam essa altura numa peça só. Para caber, use `ALTURA = 265` ou menos, ou uma impressora maior.

## Mudar medidas ou o texto da logo
Edite os parâmetros no topo de `gerar_caixa.py` (largura, profundidade, altura, parede, texto) e rode:

```bash
pip install manifold3d trimesh matplotlib numpy
python3 gerar_caixa.py
python3 preview/montar_html.py
```

O script também testa se a tampa abre de 0° a 180° sem bater na caixa.
