#!/usr/bin/env python3
"""Recolha pública do catálogo de www.casadasfardas.pt.

Só usa páginas públicas (sitemap, menu, listagens, fichas mobile e derivados de cor).
Pedidos sequenciais com intervalo, cache em disco (retoma sem repetir pedidos) e
respeito pelo robots.txt (não acede a /back exceto /back/fotos).

Uso:
  python crawl.py tree        # árvore de categorias a partir do menu
  python crawl.py listings    # listagens de todas as categorias (paginadas)
  python crawl.py products    # fichas de produto (versão mobile, uma página por artigo)
  python crawl.py derived     # derivados (cores) referidos nas fichas
  python crawl.py media       # imagens vista_* de produtos e derivados
  python crawl.py all
Saída: audit/data/*.json e audit/media/products/.
"""
import hashlib
import html
import json
import re
import sys
import time
from pathlib import Path

import requests

BASE = "https://www.casadasfardas.pt"
ROOT = Path(__file__).resolve().parents[1]
CACHE = ROOT / "cache"
DATA = ROOT / "data"
MEDIA = ROOT / "media" / "products"
DELAY = 0.7
UA_DESKTOP = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/128 Safari/537.36 (CasaDasFardas-migracao)"
UA = "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1 (CasaDasFardas-migracao)"

for d in (CACHE, DATA, MEDIA):
    d.mkdir(parents=True, exist_ok=True)

session = requests.Session()
session.headers["User-Agent"] = UA
_last = [0.0]


def fetch(url, binary=False, retries=3, ua=None):
    """GET com cache em disco. Devolve texto (cp1252 → UTF-8) ou bytes; None em 404."""
    key = hashlib.sha1((url + (ua or "")).encode()).hexdigest()
    path = CACHE / (key + (".bin" if binary else ".html"))
    miss = CACHE / (key + ".404")
    if path.exists():
        b = path.read_bytes()
        return b if binary else b.decode("cp1252", errors="replace")
    if miss.exists():
        return None
    for attempt in range(retries):
        wait = (DELAY / 2 if binary else DELAY) - (time.time() - _last[0])
        if wait > 0:
            time.sleep(wait)
        try:
            r = session.get(url, timeout=40, headers={"User-Agent": ua} if ua else None)
            _last[0] = time.time()
        except requests.RequestException as e:
            print("  erro", url, e, file=sys.stderr)
            time.sleep(3 * (attempt + 1))
            continue
        if r.status_code == 404:
            miss.write_text("404")
            return None
        if r.status_code >= 500 or r.status_code == 429:
            time.sleep(5 * (attempt + 1))
            continue
        path.write_bytes(r.content)
        return r.content if binary else r.content.decode("cp1252", errors="replace")
    return None


def clean(s):
    s = html.unescape(re.sub(r"<[^>]+>", " ", s or ""))
    return re.sub(r"\s+", " ", s).strip()


# ---------------------------------------------------------------- árvore

def build_tree():
    s = fetch(BASE + "/", ua=UA_DESKTOP)
    tree = {"external": [], "types": []}
    for m in re.finditer(r"onclick=\"location.href='(https?://[^']+)'\"[^>]*>\s*([^<]+)</div>", s):
        tree["external"].append({"name": clean(m.group(2)), "url": m.group(1)})
    # tipos: <div id="marcaN" onclick="muda_submenu(N);">Nome</div><div id='submenuN'> ... </div>
    parts = re.split(r'<div title="[^"]*" id="marca(\d+)" onclick="muda_submenu\(\d+\);" class="ymvp2">', s)
    for i in range(1, len(parts), 2):
        tid = int(parts[i])
        block = parts[i + 1]
        tname = clean(block[: block.find("</div>")])
        typ = {"id": tid, "name": tname, "categories": []}
        # categorias com subcategorias
        for cm in re.finditer(
            r'<a(?:\s+title="(?P<title>[^"]*)")?\s+href="javascript:muda_submenu2\((?P<id>\d+)\);">(?P<name>[^<]+)</a>\s*</td></tr>\s*<tr><td[^>]*><div id=\'subsubmenu(?P=id)\'.*?<table[^>]*class=\'tabscat\'>(?P<subs>.*?)</table>',
            block, re.S):
            cat = {"id": int(cm["id"]), "name": clean(cm["name"]), "title": clean(cm["title"] or ""), "subcategories": []}
            for sm in re.finditer(r'<a(?:\s+title="[^"]*")?\s+href="info/([^"]+-(\d+)-subcategoria-lpc)">([^<]+)</a>', cm["subs"]):
                cat["subcategories"].append({"id": int(sm.group(2)), "name": clean(sm.group(3)), "path": "info/" + sm.group(1)})
            typ["categories"].append(cat)
        # categorias sem subcategorias (ligação direta)
        for lm in re.finditer(r'<a(?:\s+title="([^"]*)")?\s+href="info/([^"]+-(\d+)-categoria-lpc)">([^<]+)</a>', block):
            typ["categories"].append({"id": int(lm.group(3)), "name": clean(lm.group(4)), "title": clean(lm.group(1) or ""), "path": "info/" + lm.group(2), "subcategories": []})
        tree["types"].append(typ)
    (DATA / "tree.json").write_text(json.dumps(tree, ensure_ascii=False, indent=1))
    n = sum(len(c["subcategories"]) or 1 for t in tree["types"] for c in t["categories"])
    print("tipos", len(tree["types"]), "folhas", n)
    return tree


# ---------------------------------------------------------------- listagens

def listing_url(nivel, cid, page):
    return (f"{BASE}/info/listar_categoria_corev2.php?width_disponivel=960&nivelm={nivel}&id={cid}"
            f"&xpag={page}&ordem_lista=asc&ordena=nome&ltipa=1&lpfiltro=&filtro_de=&filtro_ate="
            f"&num_seguinte=&slim=&listind=&rangemin=&rangemax=")


def crawl_listings():
    tree = json.loads((DATA / "tree.json").read_text())
    out = []
    for t in tree["types"]:
        for c in t["categories"]:
            leaves = [("subcategoria", sc["id"], sc["name"], sc["path"]) for sc in c["subcategories"]] or \
                     [("categoria", c["id"], c["name"], c.get("path"))]
            for nivel, lid, lname, lpath in leaves:
                items, page, total = [], 0, None
                while True:
                    s = fetch(listing_url(nivel, lid, page))
                    if not s:
                        break
                    m = re.search(r"de (\d+) produto\(s\).*?Página (\d+) de (\d+)", s)
                    total = int(m.group(1)) if m else 0
                    pages = int(m.group(3)) if m else 1
                    for bm in re.finditer(
                            r'<div id="box_(\d+)" class="listav2_box">.*?href="\.\./info/([^"]+)">(.*?)</a>\s*</div>\s*'
                            r'(?:<div class="listav2_desc">(.*?)</div>)?', s, re.S):
                        items.append({"id": int(bm.group(1)), "slug": bm.group(2), "name": clean(bm.group(3)),
                                      "short": clean(bm.group(4) or "")})
                    page += 1
                    if page >= pages:
                        break
                out.append({"type": t["name"], "type_id": t["id"], "category": c["name"], "category_id": c["id"],
                            "level": nivel, "leaf_id": lid, "leaf": lname, "path": lpath,
                            "declared_total": total, "items": items})
                print(f"  {t['name'][:12]:12} {c['name'][:22]:22} {lname[:24]:24} {len(items):4}/{total}")
    (DATA / "listings.json").write_text(json.dumps(out, ensure_ascii=False, indent=1))


# ---------------------------------------------------------------- fichas

def parse_jsonld_product(s):
    for raw in re.findall(r'application/ld\+json">(.*?)</script>', s, re.S):
        if '"Product"' not in raw:
            continue
        get = lambda k: (re.search(r'"%s"\s*:\s*"(.*?)"\s*,?\s*\n' % k, raw, re.S) or [None, None])[1]
        props = re.findall(r'"name":\s*"([^"]*)",\s*"value":\s*"([^"]*)"', raw)
        return {
            "name": get("name"), "sku": get("sku"), "description": get("description"),
            "category_path": get("category"), "price": get("price"), "currency": get("priceCurrency"),
            "availability": get("availability"),
            "brand": (re.search(r'"brand".*?"name":\s*"(.*?)"', raw, re.S) or [None, None])[1],
            "images": re.findall(r'(https://[^"]+/produtos/vista_\d+_\d+\.jpg)', raw),
            "properties": [(clean(k), clean(v)) for k, v in props],
        }
    return {}


def parse_product(pid, slug):
    url = f"{BASE}/m/info/{slug}"
    s = fetch(url)
    if s is None:
        return {"id": pid, "slug": slug, "status": "404"}
    ld = parse_jsonld_product(s)
    rec = {"id": pid, "slug": slug, "url_desktop": f"{BASE}/info/{slug}", "status": "ok" if ld else "sem-dados", **ld}
    title = re.search(r"<title>(.*?)</title>", s, re.S)
    rec["title"] = clean(title.group(1)) if title else None
    md = re.search(r'<meta name="description" content="([^"]*)"', s)
    rec["meta_description"] = clean(md.group(1)) if md else None
    # especificações visíveis (tabela)
    specs = re.findall(r"class='letra12y'>(.*?)</p></td>\s*<td[^>]*class='letra12x'[^>]*><p[^>]*>(.*?)</p>", s, re.S)
    rec["specs"] = [(clean(a), clean(b)) for a, b in specs if clean(b)]
    # derivados (cores/variantes): blocos com título e linhas
    der = []
    for block in re.finditer(r"<p class=\"fp_tit\">(.*?)</p>(.*?)(?=<p class=\"fp_tit\">|<div class=\"fp_espec|ESPECIFICA|$)", s, re.S):
        group = clean(block.group(1))
        for dm in re.finditer(r"location.href='\.\./info/([^']+-(\d+)\.html)'\">.*?<td align=\"left\">(.*?)</td>", block.group(2), re.S):
            der.append({"group": group, "id": int(dm.group(2)), "slug": dm.group(1), "label": clean(dm.group(3))})
    rec["derived"] = der
    rec["images"] = sorted(set(rec.get("images") or []) | set(
        BASE + "/back/fotos/casa1476/produtos/" + x for x in re.findall(r"produtos/(vista_\d+_\d+\.jpg)", s)),
        key=lambda u: int(re.search(r"_(\d+)\.jpg$", u).group(1)))
    mk = re.search(r"<b>Marca:</b>\s*(.*?)</p>", s)
    if mk:
        rec["brand_visible"] = clean(mk.group(1))
    cd = re.search(r"<b>C[óo]digo:</b>\s*(.*?)</p>", s)
    if cd:
        rec["code_visible"] = clean(cd.group(1))
    cp = re.search(r"([^<>]+ :: [^<>]+)</", s)
    rec["category_visible"] = clean(cp.group(1)) if cp else None
    ids = re.search(r"catid=(\d+)&scatid=(\d+)&rid=(\d+)", s)
    if ids:
        rec["source_catid"], rec["source_scatid"] = int(ids.group(1)), int(ids.group(2))
    rec["has_buy_button"] = "compras_adicionar.php" in s and "bt_comprar" in s
    rec["shows_price"] = bool(re.search(r"\d+,\d{2}\s*(?:€|&euro;)", s[s.find("ficha_compra") - 3000: s.find("ficha_compra") + 3000] if "ficha_compra" in s else ""))
    return rec


def crawl_products():
    ids = {}
    for u in (ROOT / "raw" / "product-urls.txt").read_text().split():
        m = re.search(r"/info/(.+-(\d+)\.html)$", u)
        if m:
            ids[int(m.group(2))] = {"slug": m.group(1), "sources": ["sitemap"]}
    lp = DATA / "listings.json"
    if lp.exists():
        for leaf in json.loads(lp.read_text()):
            for it in leaf["items"]:
                e = ids.setdefault(it["id"], {"slug": it["slug"], "sources": []})
                if "listing" not in e["sources"]:
                    e["sources"].append("listing")
    out = []
    for n, (pid, e) in enumerate(sorted(ids.items())):
        rec = parse_product(pid, e["slug"])
        rec["sources"] = e["sources"]
        out.append(rec)
        if n % 50 == 0:
            print(f"  {n}/{len(ids)} {pid} {rec.get('name')}")
    (DATA / "products.json").write_text(json.dumps(out, ensure_ascii=False, indent=1))
    print("produtos", len(out))


def crawl_derived():
    prods = json.loads((DATA / "products.json").read_text())
    out = []
    seen = set()
    for p in prods:
        for d in p.get("derived", []):
            if d["id"] in seen:
                continue
            seen.add(d["id"])
            rec = parse_product(d["id"], d["slug"])
            rec["parent_id"] = p["id"]
            rec["group"] = d["group"]
            rec["label"] = d["label"]
            out.append(rec)
            if len(out) % 100 == 0:
                print("  derivados", len(out))
    (DATA / "derived.json").write_text(json.dumps(out, ensure_ascii=False, indent=1))
    print("derivados", len(out))


def crawl_media():
    urls = []
    for f in ("products.json", "derived.json"):
        fp = DATA / f
        if fp.exists():
            for p in json.loads(fp.read_text()):
                urls += p.get("images") or []
    manifest = []
    for n, u in enumerate(dict.fromkeys(urls)):
        name = u.rsplit("/", 1)[1]
        dest = MEDIA / name
        if not dest.exists():
            b = fetch(u, binary=True)
            if b is None:
                manifest.append({"url": u, "file": None, "status": "404"})
                continue
            dest.write_bytes(b)
        manifest.append({"url": u, "file": "media/products/" + name, "bytes": dest.stat().st_size,
                         "sha1": hashlib.sha1(dest.read_bytes()).hexdigest(), "status": "ok"})
        if n % 200 == 0:
            print("  media", n, len(urls))
    (DATA / "media-manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=1))
    print("imagens", len(manifest))


if __name__ == "__main__":
    step = sys.argv[1] if len(sys.argv) > 1 else "all"
    if step in ("tree", "all"):
        build_tree()
    if step in ("listings", "all"):
        crawl_listings()
    if step in ("products", "all"):
        crawl_products()
    if step in ("derived", "all"):
        crawl_derived()
    if step in ("media", "all"):
        crawl_media()
