#!/usr/bin/env python3
"""
Lee una receta de una captura (OCR con Vision en macOS o Tesseract en Linux) o de un fichero de texto y la
devuelve como JSON. Reutiliza el lector de fxw_import.py sin modificarlo.

Uso: recipe_from_capture.py <carpeta con fxw_import.py> <imagen o .txt>

Salida (stdout): {"recipe": {...} | null, "name", "author", "lines": [...]}
  recipe = fila con las columnas de fxw_scraper.build_row(), como en fujixweekly.db
Errores: código de salida 1 y el mensaje en stderr.
"""
import json
import sys
import warnings


def main():
    if len(sys.argv) != 3:
        sys.exit("Uso: recipe_from_capture.py <carpeta de fxw_import.py> <imagen o .txt>")
    warnings.filterwarnings("ignore")  # avisos de urllib3/LibreSSL al importar requests
    sys.path.insert(0, sys.argv[1])
    from fxw_import import parse_recipe, read_lines
    from fxw_scraper import build_row

    lines = read_lines(sys.argv[2])
    raw, name, author = parse_recipe(lines)
    print(json.dumps({
        "recipe": build_row({k: str(v) for k, v in raw.items()}) if raw else None,
        "name": name,
        "author": author,
        "lines": [text for text, _ in lines],
    }, ensure_ascii=False))


if __name__ == "__main__":
    main()
