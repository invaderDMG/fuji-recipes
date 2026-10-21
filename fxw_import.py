#!/usr/bin/env python3
"""
fxw_import.py — extrae una receta de una captura (OCR) o de una nota de texto y la guarda
como receta propia en fujixweekly.db. Va junto a fxw_scraper.py y fxw_mine.py.

Instalación (una vez):
    pip3 install pyobjc-framework-Vision       # OCR nativo de macOS (recomendado)
    # alternativa: brew install tesseract

Uso:
    python3 fxw_import.py IMG_1234.PNG                      # detecta nombre/autor si aparecen
    python3 fxw_import.py *.PNG --sensor x-trans-v           # varias capturas, una receta por imagen
    python3 fxw_import.py nota.txt --name "Cloudy day"
    pbpaste | python3 fxw_import.py - --name "Last Summer"
    python3 fxw_import.py IMG_1234.PNG --dry-run             # solo ver qué ha leído

Antes de guardar muestra lo que ha entendido, las 3 recetas más parecidas y avisa si es un
duplicado de algo que ya tienes. Pregunta si guardar (s / n / r = renombrar); --yes lo omite.
"""
import argparse
import re
import shutil
import subprocess
import sys
from difflib import SequenceMatcher, get_close_matches
from pathlib import Path

from fxw_scraper import build_row, norm
from fxw_mine import connect, fmt, mine_url, rank, save_mine

IMAGE_EXT = {".png", ".jpg", ".jpeg", ".heic", ".webp", ".tif", ".tiff"}

# ------------------------------------------------------------------ OCR
def ocr_vision(path):
    import Vision
    from Foundation import NSURL

    req = Vision.VNRecognizeTextRequest.alloc().init()
    req.setRecognitionLevel_(Vision.VNRequestTextRecognitionLevelAccurate)
    req.setUsesLanguageCorrection_(False)  # la corrección "arregla" números y los rompe
    handler = Vision.VNImageRequestHandler.alloc().initWithURL_options_(
        NSURL.fileURLWithPath_(str(path)), None)
    ok, err = handler.performRequests_error_([req], None)
    if not ok:
        raise RuntimeError(err)
    obs = []
    for o in req.results() or []:
        bb = o.boundingBox()  # normalizado, origen abajo-izquierda
        obs.append((o.topCandidates_(1)[0].string(), bb.origin.x,
                    bb.origin.y + bb.size.height / 2, bb.size.height))
    return group_rows(obs)


def group_rows(obs):
    """Une fragmentos a la misma altura (tablas etiqueta | valor) en una sola línea."""
    rows = []
    for text, x, yc, h in sorted(obs, key=lambda o: -o[2]):
        for row in rows:
            if abs(row["y"] - yc) < 0.5 * max(row["h"], h):
                row["parts"].append((x, text))
                break
        else:
            rows.append({"y": yc, "h": h, "parts": [(x, text)]})
    return [(" ".join(t for _, t in sorted(r["parts"])), -r["y"] / max(r["h"], 1e-3))
            for r in rows]


def ocr_tesseract(path):
    out = subprocess.run(["tesseract", str(path), "stdout", "--psm", "6"],
                         capture_output=True, text=True, check=True).stdout
    return [(l, float(i)) for i, l in enumerate(out.splitlines())]


def read_lines(src):
    """[(texto, posición_vertical)] — la posición sirve para saber qué está pegado a qué."""
    if src == "-":
        return [(l, float(i)) for i, l in enumerate(sys.stdin.read().splitlines())]
    p = Path(src)
    if p.suffix.lower() not in IMAGE_EXT:
        return [(l, float(i)) for i, l in enumerate(p.read_text(encoding="utf-8").splitlines())]
    try:
        return ocr_vision(p)
    except ImportError:
        if shutil.which("tesseract"):
            return ocr_tesseract(p)
        sys.exit("Para OCR: pip3 install pyobjc-framework-Vision  (o brew install tesseract)")


# ------------------------------------------------------------------ etiquetas
# clave canónica (las de KEY_MAP de fxw_scraper) -> variantes que aparecen por ahí
LABELS = {
    "film simulation": ["film simulation", "film sim", "simulation"],
    "dynamic range": ["dynamic range", "dr"],
    "d range priority": ["d range priority", "drange priority"],
    "grain effect": ["grain effect", "grain"],
    "color chrome fx blue": ["color chrome fx blue", "color chrome effect blue",
                             "colour chrome fx blue", "colour chrome effect blue", "cc fx blue",
                             "ccfx blue", "chrome effect blue", "chrome fx blue", "fx blue"],
    "color chrome effect": ["color chrome effect", "colour chrome effect", "color chrome",
                            "chrome effect", "cce"],
    "white balance": ["white balance", "wb"],
    "highlight": ["highlight", "highlights", "highlight tone"],
    "shadow": ["shadow", "shadows", "shadow tone"],
    "color": ["color", "colour", "saturation"],
    "sharpness": ["sharpness", "sharpening"],
    "high iso nr": ["noise reduction high iso nr", "noise reduction iso nr", "high iso nr",
                    "high iso noise reduction", "noise reduction", "nr"],
    "clarity": ["clarity"],
    "smooth skin effect": ["smooth skin effect", "smooth skin"],
    "monochromatic color": ["monochromatic color", "monochrome color"],
    "iso": ["iso"],
    "exposure compensation": ["exposure compensation", "exposure comp", "exposure"],
}
ALIASES = sorted(((a.split(), key) for key, al in LABELS.items() for a in al),
                 key=lambda t: -len(t[0]))
NUMERIC = {"highlight", "shadow", "color", "sharpness", "high iso nr", "clarity"}
LEVELS = {"off": "Off", "weak": "Weak", "strong": "Strong"}
SIMS = ["Provia", "Velvia", "Astia", "Classic Chrome", "Reala Ace", "PRO Neg. Hi",
        "PRO Neg. Std", "Classic Negative", "Nostalgic Neg.", "Eterna", "Eterna Bleach Bypass",
        "Acros", "Acros+Ye", "Acros+R", "Acros+G", "Monochrome", "Monochrome+Ye",
        "Monochrome+R", "Monochrome+G", "Sepia"]
SIM_ALIASES = {"standard": "Provia", "provia standard": "Provia", "vivid": "Velvia",
               "velvia vivid": "Velvia", "soft": "Astia", "astia soft": "Astia",
               "classic neg": "Classic Negative", "nostalgic negative": "Nostalgic Neg.",
               "pro neg hi": "PRO Neg. Hi", "pro negative hi": "PRO Neg. Hi",
               "pro neg std": "PRO Neg. Std", "pro negative std": "PRO Neg. Std",
               "pro negative standard": "PRO Neg. Std", "eterna cinema": "Eterna",
               "bleach bypass": "Eterna Bleach Bypass"}


def word_eq(a, b):
    if len(b) <= 3:
        return a == b
    return SequenceMatcher(None, a, b).ratio() >= 0.65


def match_label(line):
    """('clave canónica', valor) si la línea contiene una etiqueta conocida (tolera erratas y
    basura de OCR delante; a mitad de línea solo vale una etiqueta de ≥2 palabras o seguida de ':')."""
    toks = list(re.finditer(r"[A-Za-z]+", line))
    for s in range(len(toks)):
        at_start = s == 0 and toks[0].start() <= 2
        for words, key in ALIASES:
            k = len(words)
            if s + k > len(toks):
                continue
            seg = toks[s:s + k]
            # entre las palabras de la etiqueta solo separadores, nada de cifras
            if any(re.search(r"\d", line[seg[i].end():seg[i + 1].start()]) for i in range(k - 1)):
                continue
            if not all(word_eq(seg[i].group().lower(), words[i]) for i in range(k)):
                continue
            rest = line[seg[-1].end():]
            if not at_start and k < 2 and not rest.lstrip().startswith(":"):
                continue
            return key, re.sub(r"^[\s:|=·.]+", "", rest).strip()
    return None


# ------------------------------------------------------------------ valores
def ocr_number(s):
    """'+I' -> '+1', 'o' -> '0', '−2' -> '-2' (tipografías con cifras antiguas)."""
    m = re.match(r"^\s*([+\-]?)\s*([0-9oOIl|i]+)(?:[.,]([0-9oO]+))?", s)
    if not m:
        return None
    tr = str.maketrans("oOIl|i", "001111")
    n = m.group(2).translate(tr) + ("." + m.group(3).translate(tr) if m.group(3) else "")
    return (m.group(1) + n) if float(n) else "0"


def signed(v):
    return f"{v:+d}" if v else "0"


def norm_wb(v):
    low = v.lower()
    k = re.search(r"(\d{4,5})\s*k\b", low)
    if k:
        mode = f"{k.group(1)}K"
    else:
        mode = next((m.title() for m in ("auto white priority", "auto ambience priority", "auto",
                                          "daylight", "shade", "fluorescent", "incandescent",
                                          "underwater") if m in low), v.split(",")[0].strip())
    shift = {}
    for color, pat in (("Red", r"red|r\b"), ("Blue", r"blue|b\b")):
        m = re.search(r"([+\-]?\s*[0-9oOIl]+)\s*(?:" + pat + ")", low.replace("&", " "))
        if m:
            shift[color] = int(ocr_number(m.group(1).replace(" ", "")) or 0)
    if not shift:
        return mode
    return f"{mode}, {signed(shift.get('Red', 0))} Red & {signed(shift.get('Blue', 0))} Blue"


def norm_grain(v):
    low = v.lower()
    if "off" in low:
        return "Off"
    rough = "Strong" if "strong" in low else "Weak" if "weak" in low else None
    size = "Large" if "large" in low else "Small" if "small" in low else None
    return ", ".join(x for x in (rough, size) if x) or v


def norm_sim(v):
    words = re.sub(r"[^a-z+ ]", " ", v.lower()).split()
    lower = {s.lower(): s for s in SIMS}
    pool = list(lower) + list(SIM_ALIASES)
    for k in range(min(len(words), 4), 0, -1):  # prefijo más largo que encaje (ignora basura detrás)
        key = " ".join(words[:k])
        if key in SIM_ALIASES:
            return SIM_ALIASES[key]
        hit = get_close_matches(key, pool, n=1, cutoff=0.8)
        if hit:
            return lower.get(hit[0]) or SIM_ALIASES[hit[0]]
    return v.strip()


def clean_text(t):
    """Quita fragmentos que son solo símbolos ('<< = :') de nombres y autores."""
    return " ".join(w for w in (t or "").split() if re.search(r"[A-Za-z0-9@]", w)) or None


def norm_value(key, v):
    v = norm(v)
    if key in NUMERIC:
        return ocr_number(v)
    if key == "film simulation":
        return norm_sim(v)
    if key == "white balance":
        return norm_wb(v)
    if key == "grain effect":
        return norm_grain(v)
    if key in ("color chrome effect", "color chrome fx blue", "smooth skin effect"):
        return next((lab for w, lab in LEVELS.items() if w in v.lower().replace(" ", "")), v)
    if key == "dynamic range":
        m = re.search(r"(\d{3})", re.sub(r"(?<=\d)[oO]|[oO](?=\d)", "0", re.sub(r"[oO]{2}\b", "00", v)))
        return f"DR{m.group(1)}" if m else ("DR-Auto" if "auto" in v.lower() else v)
    return v


# ------------------------------------------------------------------ receta completa
UI_NOISE = re.compile(r"^\d{1,2}:\d{2}|buscar|search|comentario|comment|seguir|follow|^[<>@#]$",
                      re.I)


def parse_recipe(lines):
    """-> (raw dict, nombre detectado, autor detectado)"""
    raw, idx = {}, []
    lines = [(norm(t), y) for t, y in lines if norm(t)]
    i = 0
    while i < len(lines):
        m = match_label(lines[i][0])
        if m and m[0] not in raw:
            key, value = m
            if not value and i + 1 < len(lines) and not match_label(lines[i + 1][0]):
                value = lines[i + 1][0]  # etiqueta y valor en líneas separadas
                i += 1
            val = norm_value(key, value)
            if val:
                raw[key] = val
                idx.append(i)
        i += 1
    if "film simulation" not in raw or len(raw) < 4:
        return None, None, None

    first = idx[0]
    gaps = [lines[b][1] - lines[a][1] for a, b in zip(idx, idx[1:]) if b == a + 1]
    step = sorted(gaps)[len(gaps) // 2] if gaps else 1.0
    name = author = None
    for j in range(first - 1, max(first - 3, -1), -1):  # título justo encima del bloque
        t = clean_text(re.sub(r"[\s:;=<>|_~.,-]+$", "", lines[j][0])) or ""
        letters = len(re.findall(r"[A-Za-z]", t))
        words = re.findall(r"[A-Za-z]+", t)
        short = sum(len(w) <= 2 and w.lower() not in ("by", "of", "de", "la", "el")
                    for w in words)
        if (lines[first][1] - lines[j][1] <= 2.5 * step and 3 <= len(t) <= 60
                and letters >= 3 and letters >= 0.7 * len(t.replace(" ", ""))
                and short <= len(words) // 4
                and ":" not in t and not UI_NOISE.search(t)):
            name = t
            break
    name = clean_text(name)
    if name:
        m = re.match(r"(.+?)\s+by\s+(.+)$", name, re.I)
        if m:
            name, author = m.group(1).strip(), m.group(2).strip()
        name = name.title() if name.isupper() else name
    for t, _ in lines[idx[-1] + 1: idx[-1] + 4]:  # "Credit: @fulano" debajo
        m = re.match(r"(?:credit|credits|by|recipe by)\s*:?\s*(.+)$", t, re.I)
        if m:
            author = m.group(1).strip()
            break
    return raw, name, clean_text(author)


def edit_in_editor(raw, name, author):
    """Abre la receta en $EDITOR (nano por defecto) para corregir lo que el OCR leyó mal."""
    import os
    import tempfile
    txt = f"Name: {name}\nAuthor: {author or ''}\n" + "\n".join(
        f"{k.title()}: {v}" for k, v in raw.items())
    with tempfile.NamedTemporaryFile("w", suffix=".txt", delete=False) as f:
        f.write(txt + "\n")
    subprocess.call([os.environ.get("EDITOR", "nano"), f.name])
    lines, meta = [], {}
    for l in Path(f.name).read_text(encoding="utf-8").splitlines():
        m = re.match(r"^(name|author)\s*:\s*(.*)$", l.strip(), re.I)
        if m:
            meta[m.group(1).lower()] = m.group(2).strip() or None
        else:
            lines.append(l)
    os.unlink(f.name)
    new_raw, _, _ = parse_recipe([(l, float(i)) for i, l in enumerate(lines)])
    return (new_raw or raw), meta.get("name") or name, meta.get("author")


def show(raw, name, author):
    print(f"\n  Nombre: {name}" + (f"   ·   Autor: {author}" if author else ""))
    for k, v in raw.items():
        print(f"  {k.title():<24} {v}")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("inputs", nargs="+", help="capturas, ficheros de texto, o '-' para stdin")
    ap.add_argument("--db", default="fujixweekly.db")
    ap.add_argument("--name", help="nombre (si hay varias entradas, se numera)")
    ap.add_argument("--author")
    ap.add_argument("--sensor", default="x-trans-v")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--yes", "-y", action="store_true", help="guardar sin preguntar")
    a = ap.parse_args()

    db = connect(a.db)
    interactive = sys.stdin.isatty() and not a.yes and "-" not in a.inputs
    for n, src in enumerate(a.inputs, 1):
        print(f"\n=== {src}")
        raw, name, author = parse_recipe(read_lines(src))
        if not raw:
            print("  ! No encuentro una receta (falta 'Film Simulation' o hay <4 ajustes).")
            continue
        if a.name:
            name = a.name if len(a.inputs) == 1 else f"{a.name} {n}"
        name = name or f"{raw['film simulation']} ({Path(src).stem if src != '-' else 'nota'})"
        author = a.author or author
        missing = [k for k in ("dynamic range", "white balance", "highlight", "shadow", "color")
                   if k not in raw]
        show(raw, name, author)
        if missing:
            print(f"  (no leído: {', '.join(missing)})")

        while True:
            row = build_row(raw)
            top = rank(db, dict(row, post_url=mine_url(name)), n=3, include_mine=True)
            if top:
                print("\n  Más parecidas:")
                for d, _, c in top:
                    tag = "  ← ¡duplicado?" if d < 1.0 else ""
                    print(f"   {d:5.2f}  {c['name']}{tag}")
            if a.dry_run or not interactive:
                ans = ""
                break
            ans = input(f"\n¿Guardar como '{name}'? [S/n/e=editar/r=renombrar] ").strip().lower()
            if ans == "e":
                raw, name, author = edit_in_editor(raw, name, author)
                show(raw, name, author)
                continue
            if ans == "r":
                name = input("Nombre: ").strip() or name
            break
        if a.dry_run or ans == "n":
            continue
        if db.execute("SELECT 1 FROM posts WHERE url=?", (mine_url(name),)).fetchone():
            print(f"  (ya existía '{name}': se sobrescribe)")
        save_mine(db, name, row, a.sensor, author, src)
        print(f"  ✓ Guardada '{name}'")


if __name__ == "__main__":
    main()
