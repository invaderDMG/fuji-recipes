#!/usr/bin/env python3
"""
fxw_mine.py — tus propias recetas en fujixweekly.db + búsqueda de las más parecidas.
Va en la misma carpeta que fxw_scraper.py (reutiliza su parser).

Formato de entrada: texto plano, igual que en los posts ("Clave: valor" por línea):
    Film Simulation: Classic Negative
    Dynamic Range: DR400
    Grain Effect: Weak, Small
    White Balance: Auto, +2 Red & -4 Blue
    Highlight: -1
    ...

Uso:
    python3 fxw_mine.py add mi_receta.txt --name "Mi Gold" --sensor x-trans-v
    pbpaste | python3 fxw_mine.py add - --name "Mi Gold"        # desde el portapapeles
    python3 fxw_mine.py similar "Mi Gold"                        # top 10 de fujixweekly
    python3 fxw_mine.py similar - -n 5 < receta.txt              # sin guardarla
    python3 fxw_mine.py similar "Mi Gold" --sensor x-trans-v --same-sim
    python3 fxw_mine.py list
    python3 fxw_mine.py rm "Mi Gold"

Las recetas propias van con post_url = "mine:<nombre>", así el scraper nunca las toca.
"""
import argparse
import json
import re
import sqlite3
import sys

from fxw_scraper import SCHEMA, build_row, extract_blocks, norm

# ------------------------------------------------------------------ similitud
# Familias de simulación: misma familia = penalización menor que simulación distinta.
SIM_FAMILY = {
    "pro neg std": "proneg", "pro neg. std": "proneg", "pro neg hi": "proneg",
    "pro neg. hi": "proneg", "reala ace": "proneg",
    "classic negative": "neg", "classic neg": "neg", "nostalgic neg": "neg",
    "nostalgic negative": "neg",
    "classic chrome": "chrome",
    "eterna": "eterna", "eterna bleach bypass": "eterna",
    "provia": "slide", "provia/standard": "slide", "astia": "slide", "astia/soft": "slide",
    "velvia": "vivid", "velvia/vivid": "vivid",
    "acros": "mono", "monochrome": "mono", "sepia": "mono",
}
LEVEL = {"off": 0, "weak": 1, "strong": 2, "small": 0, "large": 1}

# peso por unidad de diferencia
W = {
    "highlight": 1.0, "shadow": 1.0, "color": 1.0, "clarity": 0.4,
    "sharpness": 0.2, "high_iso_nr": 0.1,
    "wb_shift_red": 0.5, "wb_shift_blue": 0.5,
    "dr": 0.75, "grain_roughness": 0.3, "grain_size": 0.2,
    "color_chrome_effect": 0.5, "color_chrome_fx_blue": 0.5,
}
SIM_SAME_FAMILY, SIM_DIFFERENT = 2.0, 6.0


def sim_key(s):
    s = re.sub(r"\s+", " ", (s or "").lower().replace("negative", "neg").strip())
    s = re.sub(r"\s*\(.*\)$", "", s)  # "Acros (Yellow)" -> "acros"
    return s


def family(s):
    k = sim_key(s)
    for name, fam in SIM_FAMILY.items():
        if k == sim_key(name) or k.startswith(sim_key(name)):
            return fam
    return k


def dr_value(v):
    m = re.search(r"(\d{3})", v or "")
    return {"100": 0, "200": 1, "400": 2}.get(m.group(1)) if m else (1 if v else None)


def level(v):
    if not v:
        return None
    return LEVEL.get(v.strip().lower().split()[0])


def features(r):
    return {
        "highlight": r["highlight"], "shadow": r["shadow"], "color": r["color"],
        "clarity": r["clarity"], "sharpness": r["sharpness"], "high_iso_nr": r["high_iso_nr"],
        "wb_shift_red": r["wb_shift_red"], "wb_shift_blue": r["wb_shift_blue"],
        "dr": dr_value(r["dynamic_range"]),
        "grain_roughness": level(r["grain_roughness"]),
        "grain_size": level(r["grain_size"]) if level(r["grain_roughness"]) else None,
        "color_chrome_effect": level(r["color_chrome_effect"]),
        "color_chrome_fx_blue": level(r["color_chrome_fx_blue"]),
    }


def wb_mode_penalty(a, b):
    ma, mb = (a["wb_mode"] or "").lower(), (b["wb_mode"] or "").lower()
    if not ma or not mb:
        return 0.5
    if a["wb_kelvin"] and b["wb_kelvin"]:
        return min(abs(a["wb_kelvin"] - b["wb_kelvin"]) / 500, 3.0)
    if ma == mb:
        return 0.0
    if ma.startswith("auto") and mb.startswith("auto"):
        return 0.5
    return 1.5


def distance(a, b):
    """Menor = más parecido. Devuelve (total, desglose por campo)."""
    parts = {}
    if sim_key(a["film_simulation"]) == sim_key(b["film_simulation"]):
        parts["film_simulation"] = 0.0
    elif family(a["film_simulation"]) == family(b["film_simulation"]):
        parts["film_simulation"] = SIM_SAME_FAMILY
    else:
        parts["film_simulation"] = SIM_DIFFERENT
    parts["wb_mode"] = wb_mode_penalty(a, b)
    fa, fb = features(a), features(b)
    for k, w in W.items():
        va, vb = fa[k], fb[k]
        if va is None and vb is None:
            continue
        if va is None or vb is None:  # campo que una no tiene (sensores antiguos)
            parts[k] = w * 0.5
        else:
            parts[k] = w * abs(va - vb)
    return sum(parts.values()), parts


# ------------------------------------------------------------------ helpers
def connect(path):
    db = sqlite3.connect(path)
    db.row_factory = sqlite3.Row
    db.executescript(SCHEMA)
    cols = {r[1] for r in db.execute("PRAGMA table_info(posts)")}
    for c in ("author", "source"):
        if c not in cols:
            db.execute(f"ALTER TABLE posts ADD COLUMN {c} TEXT")
    return db


def save_mine(db, name, row, sensor="x-trans-v", author=None, source=None):
    url = mine_url(name)
    db.execute("DELETE FROM recipes WHERE post_url=?", (url,))
    db.execute("DELETE FROM recipe_sensors WHERE post_url=?", (url,))
    db.execute("INSERT OR REPLACE INTO posts(url,title,published,image_url,author,source) "
               "VALUES (?,?,date('now'),NULL,?,?)", (url, name, author, source))
    row = dict(row, name=name, post_url=url, block_index=0)
    db.execute(f"INSERT INTO recipes({','.join(row)}) VALUES ({','.join('?' * len(row))})",
               list(row.values()))
    db.execute("INSERT INTO recipe_sensors VALUES (?,?,?)", (url, sensor, "mine"))
    db.commit()


def rank(db, target, n=10, sensor=None, same_sim=False, include_mine=False):
    """[(distancia, desglose, receta)] ordenado de más a menos parecida."""
    sql, args = "SELECT * FROM v_recipes WHERE post_url != ?", [target.get("post_url") or ""]
    if not include_mine:
        sql += " AND post_url NOT LIKE 'mine:%'"
    if sensor:
        sql += " AND sensors LIKE ?"
        args.append(f"%{sensor}%")
    cands = [dict(r) for r in db.execute(sql, args)]
    if same_sim:
        cands = [c for c in cands if sim_key(c["film_simulation"]) == sim_key(target["film_simulation"])]
    scored = [(*distance(target, c), c) for c in cands]
    return sorted(scored, key=lambda x: x[0])[:n]


def read_text(src):
    return sys.stdin.read() if src == "-" else open(src, encoding="utf-8").read()


def parse_text(text):
    lines = [norm(l) for l in text.splitlines() if norm(l)]
    blocks = extract_blocks(lines)
    if not blocks:
        sys.exit("No encuentro una receta: debe empezar por 'Film Simulation:' y tener ≥4 campos.")
    return build_row(blocks[0][1])


def mine_url(name):
    return "mine:" + name


def get_mine(db, name):
    r = db.execute("SELECT * FROM recipes WHERE post_url=?", (mine_url(name),)).fetchone()
    if not r:
        sys.exit(f"No tienes ninguna receta llamada '{name}'. Mira: fxw_mine.py list")
    return r


def fmt(v):
    if v is None:
        return "—"
    if isinstance(v, float):
        return f"{v:+g}" if v else "0"
    return str(v)


# ------------------------------------------------------------------ comandos
def cmd_add(db, a):
    row = parse_text(read_text(a.file))
    save_mine(db, a.name, row, a.sensor)
    print(f"Guardada '{a.name}' ({row['film_simulation']}).")


def cmd_similar(db, a):
    if a.target == "-" or a.file:
        target = parse_text(read_text(a.file or "-"))
        target["post_url"], target["name"] = "", "(entrada)"
    else:
        target = dict(get_mine(db, a.target))

    scored = rank(db, target, a.n, a.sensor, a.same_sim, a.include_mine)
    if not scored:
        sys.exit("No hay candidatas (¿has ejecutado fxw_scraper.py?).")

    cols = [("film_simulation", "Sim"), ("dynamic_range", "DR"), ("highlight", "H"),
            ("shadow", "S"), ("color", "Col"), ("clarity", "Clar"),
            ("wb_mode", "WB"), ("wb_shift_red", "R"), ("wb_shift_blue", "B"),
            ("grain_roughness", "Grano"), ("color_chrome_effect", "CCE"),
            ("color_chrome_fx_blue", "FXB")]
    print(f"\nObjetivo: {target['name']}")
    print("  " + "  ".join(f"{h}={fmt(target.get(k))}" for k, h in cols))
    print(f"\nTop {len(scored)} (distancia: 0 = idéntica)\n")
    for pos, (total, parts, c) in enumerate(scored, 1):
        print(f"{pos:2}. {total:5.2f}  {c['name']}  [{c['sensors']}]")
        diffs = [f"{h} {fmt(c.get(k))}" for k, h in cols
                 if fmt(c.get(k)) != fmt(target.get(k))]
        print("      difiere en: " + (", ".join(diffs) if diffs else "nada visible"))
        top = sorted(((v, k) for k, v in parts.items() if v), reverse=True)[:3]
        if top:
            print("      más peso:   " + ", ".join(f"{k} {v:.1f}" for v, k in top))
        print(f"      {c['post_url']}")


def cmd_list(db, a):
    rows = db.execute("SELECT name, film_simulation, dynamic_range, highlight, shadow, color "
                      "FROM recipes WHERE post_url LIKE 'mine:%' ORDER BY name").fetchall()
    if not rows:
        print("Todavía no has guardado ninguna receta propia.")
    for r in rows:
        print(f"- {r['name']}: {r['film_simulation']}, {r['dynamic_range']}, "
              f"H {fmt(r['highlight'])} S {fmt(r['shadow'])} Col {fmt(r['color'])}")


def cmd_rm(db, a):
    url = mine_url(a.name)
    n = db.execute("DELETE FROM recipes WHERE post_url=?", (url,)).rowcount
    db.execute("DELETE FROM recipe_sensors WHERE post_url=?", (url,))
    db.execute("DELETE FROM posts WHERE url=?", (url,))
    db.commit()
    print("Borrada." if n else "No existía.")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--db", default="fujixweekly.db")
    sub = ap.add_subparsers(dest="cmd", required=True)

    p = sub.add_parser("add", help="guardar una receta propia")
    p.add_argument("file", help="fichero de texto o '-' para stdin")
    p.add_argument("--name", required=True)
    p.add_argument("--sensor", default="x-trans-v")
    p.set_defaults(fn=cmd_add)

    p = sub.add_parser("similar", help="recetas de fujixweekly más parecidas")
    p.add_argument("target", help="nombre de una receta tuya, o '-' para leer de stdin")
    p.add_argument("--file", help="comparar un fichero sin guardarlo")
    p.add_argument("-n", type=int, default=10)
    p.add_argument("--sensor", help="limitar a un índice, p. ej. x-trans-v")
    p.add_argument("--same-sim", action="store_true", help="solo misma simulación base")
    p.add_argument("--include-mine", action="store_true", help="comparar también con tus recetas")
    p.set_defaults(fn=cmd_similar)

    sub.add_parser("list", help="listar tus recetas").set_defaults(fn=cmd_list)
    p = sub.add_parser("rm", help="borrar una receta tuya")
    p.add_argument("name")
    p.set_defaults(fn=cmd_rm)

    a = ap.parse_args()
    a.fn(connect(a.db), a)


if __name__ == "__main__":
    main()
