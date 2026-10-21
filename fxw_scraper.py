#!/usr/bin/env python3
"""
fxw_scraper.py — base de datos SQLite local con las Film Simulation Recipes de fujixweekly.com

Uso:
    pip3 install requests beautifulsoup4
    python3 fxw_scraper.py                     # todos los índices (X-Trans I–V, Bayer, EXR, GFX…)
    python3 fxw_scraper.py --only x-trans-v x-trans-iv
    python3 fxw_scraper.py --refresh           # ignora la caché HTML y vuelve a descargar

Opcional, UI con filtros y facetas sobre la BD:
    pip3 install datasette && datasette fujixweekly.db

Notas:
- Solo se parsea el cuerpo del post (no comentarios: ahí la gente pega variantes).
- Las páginas se cachean en .fxw_cache/ para no repetir peticiones; 1 s de pausa entre descargas.
- raw_json guarda todas las claves tal cual aparecen, por si alguna no está normalizada.
"""
import argparse
import hashlib
import json
import re
import sqlite3
import sys
import time
from pathlib import Path

import requests
from bs4 import BeautifulSoup

BASE = "https://fujixweekly.com"
INDEXES = {
    "x-trans-v": "/fujifilm-x-trans-v-recipes/",
    "x-trans-iv": "/fujifilm-x-trans-iv-recipes/",
    "x-trans-iii": "/fujifilm-x-trans-iii-recipes/",
    "x-trans-ii": "/fujifilm-x-trans-ii-recipes/",
    "x-trans-i": "/fujifilm-x-trans-i-recipes/",
    "bayer": "/fujifilm-bayer-recipes/",
    "exr-cmos": "/fujifilm-exr-cmos-film-simulation-recipes/",
    "gfx": "/fujifilm-gfx-recipes/",
    "full-spectrum": "/full-spectrum-recipes/",
}
POST_RE = re.compile(r"^https?://(?:www\.)?fujixweekly\.com/\d{4}/\d{2}/\d{2}/[^/?#]+/?$")
CACHE = Path(".fxw_cache")
DELAY = 1.0
UA = "Mozilla/5.0 (personal recipe index; low-rate)"

# Clave tal como aparece en el post (minúsculas) -> columna canónica
KEY_MAP = {
    "film simulation": "film_simulation",
    "dynamic range": "dynamic_range",
    "d range priority": "d_range_priority",
    "d-range priority": "d_range_priority",
    "grain effect": "grain_effect",
    "grain": "grain_effect",
    "color chrome effect": "color_chrome_effect",
    "colour chrome effect": "color_chrome_effect",
    "color chrome fx blue": "color_chrome_fx_blue",
    "color chrome effect blue": "color_chrome_fx_blue",
    "white balance": "white_balance",
    "tone curve": "tone_curve",
    "highlight": "highlight",
    "highlights": "highlight",
    "highlight tone": "highlight",
    "shadow": "shadow",
    "shadows": "shadow",
    "shadow tone": "shadow",
    "color": "color",
    "colour": "color",
    "saturation": "color",
    "sharpness": "sharpness",
    "sharpening": "sharpness",
    "high iso nr": "high_iso_nr",
    "high iso noise reduction": "high_iso_nr",
    "noise reduction": "high_iso_nr",
    "nr": "high_iso_nr",
    "clarity": "clarity",
    "smooth skin effect": "smooth_skin",
    "monochromatic color": "monochromatic_color",
    "monochrome color": "monochromatic_color",
    "iso": "iso",
    "exposure compensation": "exposure_compensation",
}
BLOCK_TAGS = ["p", "li", "h1", "h2", "h3", "h4", "h5", "h6", "div", "figcaption", "blockquote", "tr"]

SCHEMA = """
CREATE TABLE IF NOT EXISTS posts (
    url TEXT PRIMARY KEY,
    title TEXT,
    published TEXT,
    image_url TEXT
);
CREATE TABLE IF NOT EXISTS recipes (
    id INTEGER PRIMARY KEY,
    post_url TEXT NOT NULL REFERENCES posts(url),
    block_index INTEGER NOT NULL,
    name TEXT,
    film_simulation TEXT,
    dynamic_range TEXT,
    d_range_priority TEXT,
    grain_roughness TEXT,
    grain_size TEXT,
    color_chrome_effect TEXT,
    color_chrome_fx_blue TEXT,
    white_balance TEXT,
    wb_mode TEXT,
    wb_kelvin INTEGER,
    wb_shift_red INTEGER,
    wb_shift_blue INTEGER,
    tone_curve TEXT,
    highlight REAL,
    shadow REAL,
    color REAL,
    monochromatic_color TEXT,
    sharpness REAL,
    high_iso_nr REAL,
    clarity REAL,
    smooth_skin TEXT,
    iso TEXT,
    iso_max INTEGER,
    exposure_compensation TEXT,
    raw_json TEXT,
    UNIQUE(post_url, block_index)
);
CREATE TABLE IF NOT EXISTS recipe_sensors (
    post_url TEXT NOT NULL REFERENCES posts(url),
    sensor TEXT NOT NULL,
    index_label TEXT,
    PRIMARY KEY (post_url, sensor, index_label)
);
CREATE VIEW IF NOT EXISTS v_recipes AS
SELECT r.*, p.title AS post_title, p.published, p.image_url,
       (SELECT group_concat(DISTINCT s.sensor) FROM recipe_sensors s WHERE s.post_url = r.post_url) AS sensors
FROM recipes r JOIN posts p ON p.url = r.post_url;
"""


# ---------------------------------------------------------------- fetching
session = requests.Session()
session.headers["User-Agent"] = UA
_last_fetch = 0.0


def fetch(url, refresh=False):
    CACHE.mkdir(exist_ok=True)
    f = CACHE / (hashlib.sha1(url.encode()).hexdigest() + ".html")
    if f.exists() and not refresh:
        return f.read_text(encoding="utf-8")
    global _last_fetch
    wait = DELAY - (time.time() - _last_fetch)
    if wait > 0:
        time.sleep(wait)
    for attempt in range(3):
        try:
            r = session.get(url, timeout=30)
            _last_fetch = time.time()
            if r.status_code == 200:
                f.write_text(r.text, encoding="utf-8")
                return r.text
            if r.status_code in (429, 503):
                time.sleep(10 * (attempt + 1))
                continue
            print(f"  ! HTTP {r.status_code} {url}", file=sys.stderr)
            return None
        except requests.RequestException as e:
            print(f"  ! {e} ({url})", file=sys.stderr)
            time.sleep(5)
    return None


# ---------------------------------------------------------------- parsing
def norm(s):
    return (s.replace("\xa0", " ").replace("\u2212", "-").replace("\u2013", "-")
             .replace("\u2014", "-").replace("\u2019", "'").replace("\u2018", "'")
             .replace("\u201c", '"').replace("\u201d", '"')).strip()


def content_root(soup):
    for sel in ("div.entry-content", "div.post-content", "article .entry", "article", "main"):
        node = soup.select_one(sel)
        if node:
            break
    else:
        node = soup.body
    for junk in node.select("#comments, .comments-area, .sharedaddy, .jp-relatedposts, "
                            ".wpcnt, .wp-block-jetpack-subscriptions, nav, script, style"):
        junk.decompose()
    return node


def content_lines(node):
    for br in node.find_all("br"):
        br.replace_with("\n")
    for tag in node.find_all(BLOCK_TAGS):
        tag.insert(0, "\n")
        tag.append("\n")
    return [norm(l) for l in node.get_text("").split("\n") if norm(l)]


def split_kv(line):
    if ":" not in line:
        return None
    k, v = line.split(":", 1)
    key = re.sub(r"\s+", " ", k.strip().strip("*").lower())
    if key in KEY_MAP:
        return key, v.strip().strip("*").strip()
    return None


def looks_like_heading(line):
    return (len(line) <= 70 and not line.endswith((".", ",", ":")) and ":" not in line
            and not line.lower().startswith(("example photographs", "this post", "find this")))


# Posts antiguos (2017–2022): la simulación va sola en una línea, sin "Film Simulation:"
SIM_LINE = re.compile(
    r"^(?:classic chrome|classic neg|nostalgic neg|pro neg|reala|eterna|acros|monochrome|b&w|"
    r"black (?:&|and) white|sepia|provia|velvia|astia|standard|vivid|soft)\b", re.I)


def is_bare_sim(lines, i):
    """Línea que es solo el nombre de una simulación y va seguida de ajustes clave:valor."""
    return (not split_kv(lines[i]) and len(lines[i]) <= 60 and SIM_LINE.match(lines[i])
            and i + 1 < len(lines) and split_kv(lines[i + 1]) is not None)


def extract_blocks(lines):
    """Un bloque es una racha de líneas clave:valor conocidas. Empieza en 'Film Simulation:',
    en una línea con solo el nombre de la simulación (formato antiguo) o en cualquier clave."""
    blocks, i = [], 0
    while i < len(lines):
        kv = split_kv(lines[i])
        bare = is_bare_sim(lines, i)
        if not kv and not bare:
            i += 1
            continue
        start = i
        raw, misses, j = {}, 0, i
        if bare:
            raw["film simulation"] = lines[i]
            j = i + 1
        while j < len(lines) and misses < 2:
            kv = split_kv(lines[j])
            if kv:
                if KEY_MAP[kv[0]] == "film_simulation" and raw:
                    break
                raw.setdefault(kv[0], kv[1])
                misses = 0
            elif raw and is_bare_sim(lines, j):
                break  # empieza otra receta en formato antiguo
            else:
                misses += 1
            j += 1
        keys = {KEY_MAP[k] for k in raw}
        if "film_simulation" not in keys and start > 0 and SIM_LINE.match(lines[start - 1]) \
                and len(lines[start - 1]) <= 60:
            raw = {"film simulation": lines[start - 1], **raw}
            start -= 1
            keys.add("film_simulation")
        if len(raw) >= 4 and ("film_simulation" in keys or len(raw) >= 6):
            heading = next((lines[k] for k in range(start - 1, max(start - 4, -1), -1)
                            if looks_like_heading(lines[k])), None)
            blocks.append((heading, raw))
        i = max(j, i + 1)
    return blocks


def num(v):
    if v is None:
        return None
    m = re.match(r"^\s*([+\-]?\d+(?:\.\d+)?)", v)
    return float(m.group(1)) if m else None


def parse_wb(v):
    out = {"wb_mode": None, "wb_kelvin": None, "wb_shift_red": None, "wb_shift_blue": None}
    if not v:
        return out
    out["wb_mode"] = v.split(",")[0].strip()
    k = re.search(r"(\d{4,5})\s*K\b", v)
    if k:
        out["wb_kelvin"] = int(k.group(1))
    r = re.search(r"([+\-]?\d+)\s*(?:R\b|Red)", v)
    b = re.search(r"([+\-]?\d+)\s*(?:B\b|Blue)", v)
    if r:
        out["wb_shift_red"] = int(r.group(1))
    if b:
        out["wb_shift_blue"] = int(b.group(1))
    return out


def build_row(raw):
    c = {KEY_MAP[k]: v for k, v in raw.items()}
    grain = c.get("grain_effect")
    gr = gs = None
    if grain:
        parts = [p.strip() for p in grain.split(",")]
        gr = parts[0]
        gs = parts[1] if len(parts) > 1 else None
    iso = c.get("iso")
    m = re.search(r"(?:up to|max(?:imum)?|to|-)\s*(?:ISO\s*)?(\d{3,6})", iso or "", re.I)
    row = {
        "film_simulation": c.get("film_simulation"),
        "dynamic_range": c.get("dynamic_range"),
        "d_range_priority": c.get("d_range_priority"),
        "grain_roughness": gr,
        "grain_size": gs,
        "color_chrome_effect": c.get("color_chrome_effect"),
        "color_chrome_fx_blue": c.get("color_chrome_fx_blue"),
        "white_balance": c.get("white_balance"),
        "tone_curve": c.get("tone_curve"),
        "highlight": num(c.get("highlight")),
        "shadow": num(c.get("shadow")),
        "color": num(c.get("color")),
        "monochromatic_color": c.get("monochromatic_color"),
        "sharpness": num(c.get("sharpness")),
        "high_iso_nr": num(c.get("high_iso_nr")),
        "clarity": num(c.get("clarity")),
        "smooth_skin": c.get("smooth_skin"),
        "iso": iso,
        "iso_max": int(m.group(1)) if m else None,
        "exposure_compensation": c.get("exposure_compensation"),
        "raw_json": json.dumps(raw, ensure_ascii=False),
    }
    row.update(parse_wb(c.get("white_balance")))
    return row


def parse_index(html):
    soup = BeautifulSoup(html, "html.parser")
    node = content_root(soup)
    seen = {}
    for a in node.find_all("a", href=True):
        href = a["href"].split("#")[0].split("?")[0]
        if POST_RE.match(href):
            href = href.rstrip("/") + "/"
            label = norm(a.get_text(" "))
            if label and href not in seen:
                seen[href] = label
    return seen  # url -> etiqueta en el índice


def parse_post(html):
    soup = BeautifulSoup(html, "html.parser")
    meta = lambda p: (soup.find("meta", property=p) or {}).get("content")
    title = norm(meta("og:title") or (soup.title.string if soup.title else "") or "")
    lines = content_lines(content_root(soup))
    return {
        "title": title,
        "published": meta("article:published_time"),
        "image_url": meta("og:image"),
        "blocks": extract_blocks(lines),
    }


# ---------------------------------------------------------------- main
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--db", default="fujixweekly.db")
    ap.add_argument("--only", nargs="*", choices=list(INDEXES), help="índices a procesar")
    ap.add_argument("--refresh", action="store_true", help="ignorar caché")
    args = ap.parse_args()

    db = sqlite3.connect(args.db)
    db.executescript(SCHEMA)

    targets = {k: INDEXES[k] for k in (args.only or INDEXES)}
    posts = {}  # url -> [(sensor, label)]
    for sensor, path in targets.items():
        html = fetch(BASE + path, refresh=True)  # los índices siempre frescos
        if not html:
            continue
        links = parse_index(html)
        print(f"[{sensor}] {len(links)} enlaces")
        for url, label in links.items():
            posts.setdefault(url, []).append((sensor, label))

    no_recipe = []
    for n, (url, refs) in enumerate(sorted(posts.items()), 1):
        html = fetch(url, refresh=args.refresh)
        if not html:
            continue
        p = parse_post(html)
        print(f"  {n}/{len(posts)} {len(p['blocks'])} receta(s) · {p['title'][:70]}")
        db.execute("INSERT OR REPLACE INTO posts(url,title,published,image_url) VALUES (?,?,?,?)",
                   (url, p["title"], p["published"], p["image_url"]))
        for sensor, label in refs:
            db.execute("INSERT OR IGNORE INTO recipe_sensors VALUES (?,?,?)", (url, sensor, label))
        if not p["blocks"]:
            no_recipe.append(url)
        db.execute("DELETE FROM recipes WHERE post_url=?", (url,))
        label = refs[0][1]
        for i, (heading, raw) in enumerate(p["blocks"]):
            row = build_row(raw)
            row["name"] = label if len(p["blocks"]) == 1 else (heading or f"{label} #{i + 1}")
            row["post_url"], row["block_index"] = url, i
            cols = ",".join(row)
            db.execute(f"INSERT INTO recipes({cols}) VALUES ({','.join('?' * len(row))})",
                       list(row.values()))
        db.commit()

    total = db.execute("SELECT count(*) FROM recipes").fetchone()[0]
    print(f"\nListo: {total} recetas en {args.db}")
    if no_recipe:
        print(f"{len(no_recipe)} posts sin bloque detectado (revisar a mano):")
        for u in no_recipe:
            print("  ", u)


if __name__ == "__main__":
    main()
