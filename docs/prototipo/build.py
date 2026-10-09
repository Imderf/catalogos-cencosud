#!/usr/bin/env python3
"""
Re-empaqueta el prototipo en un HTML único y autocontenido — el archivo que se
le comparte al cliente, sin servidor: abre con doble clic.

    python3 build.py   → dist/Revisión de Catálogos - Ogilvy × Cencosud.html

Qué hace: toma index.html, mete el contenido de vendor/*.js dentro de etiquetas
<script> y reemplaza cada ruta de assets/ por su data: URI en base64. No hay
cargador ni lógica en tiempo de ejecución: el resultado es el mismo index.html
con todo adentro.
"""
import base64, json, mimetypes, os

RAIZ = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(RAIZ, "dist", "Revisión de Catálogos - Ogilvy × Cencosud.html")

MIMES = {".woff2": "font/woff2", ".webp": "image/webp", ".jpg": "image/jpeg",
         ".png": "image/png", ".js": "text/javascript"}


def mime(ruta):
    ext = os.path.splitext(ruta)[1].lower()
    return MIMES.get(ext) or mimetypes.guess_type(ruta)[0] or "application/octet-stream"


def data_uri(ruta):
    crudo = open(os.path.join(RAIZ, ruta), "rb").read()
    return "data:%s;base64,%s" % (mime(ruta), base64.b64encode(crudo).decode("ascii"))


def assets():
    out = []
    for carpeta in ("assets", "assets/fonts"):
        d = os.path.join(RAIZ, carpeta)
        if os.path.isdir(d):
            out += [carpeta + "/" + n for n in sorted(os.listdir(d))
                    if os.path.isfile(os.path.join(d, n)) and not n.startswith(".")]
    return sorted(out, key=len, reverse=True)


def main():
    html = open(os.path.join(RAIZ, "index.html"), encoding="utf-8").read()

    # 1. vendor/*.js → contenido inline
    vendor = [n for n in sorted(os.listdir(os.path.join(RAIZ, "vendor"))) if n.endswith(".js")]
    # Van como data: URI y no en línea a propósito: el código de dc-runtime trae
    # dentro las cadenas "<script data-dc-script>" y "<x-dc>", y pegado en el HTML
    # confunde tanto al parser del navegador como al propio runtime, que busca
    # "<x-dc>" en el texto del documento para encontrar la plantilla.
    for n in vendor:
        etiqueta = '<script src="vendor/%s"></script>' % n
        if etiqueta not in html:
            raise SystemExit("index.html ya no carga vendor/%s — revisa build.py" % n)
        html = html.replace(etiqueta, '<script src="%s"></script>' % data_uri("vendor/" + n))

    # 2a. assets/... citados literalmente (logos, fuentes) → data: URI en el sitio
    usados = 0
    mapa = {}
    for ruta in assets():
        uri = data_uri(ruta)
        if ruta in html:
            html = html.replace(ruta, uri)
            usados += 1
        else:
            # 2b. Las páginas se resuelven en tiempo de ejecución ("assets/<img>.jpg"),
            #     así que viajan en un mapa que la app consulta con asset().
            mapa[ruta] = uri
    if mapa:
        js = "window.__ASSETS=" + json.dumps(mapa, ensure_ascii=False) + ";"
        html = html.replace("</head>", "<script>" + js + "</script>\n</head>", 1)
        usados += len(mapa)

    os.makedirs(os.path.dirname(SALIDA), exist_ok=True)
    open(SALIDA, "w", encoding="utf-8").write(html)
    print("%s\n  %d scripts + %d assets embebidos · %.1f MB"
          % (SALIDA, len(vendor), usados, os.path.getsize(SALIDA) / 1048576))


if __name__ == "__main__":
    main()
