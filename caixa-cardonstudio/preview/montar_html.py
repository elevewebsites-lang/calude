"""Embute os STLs da pré-visualização no modelo.html e gera caixa_animada.html."""
import base64
from pathlib import Path

aqui = Path(__file__).parent
html = (aqui / "modelo.html").read_text()
for nome in ("base", "tampa", "logo"):
    html = html.replace(f"__{nome.upper()}__", base64.b64encode((aqui / f"{nome}.stl").read_bytes()).decode())
html = html.replace("__EIXO__", (aqui / "eixo.json").read_text())
(aqui / "caixa_animada.html").write_text(html)
print(f"caixa_animada.html: {len(html) / 1e6:.2f} MB")
