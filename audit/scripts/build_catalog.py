#!/usr/bin/env python3
"""Normaliza a recolha pública num catálogo importável e gera os inventários.

Entrada: audit/data/{tree,listings,products,derived,media-manifest}.json e a cache HTML.
Saída:
  catalog/catalog.json            categorias, marcas, produtos, variações, imagens (para o importador WP-CLI)
  docs/inventario/content-inventory.csv   uma linha por URL/conteúdo da origem
  docs/inventario/import-gaps.csv         lacunas e decisões por artigo
  docs/inventario/catalog-stats.json      contagens para reconciliação
Não inventa dados: campos sem valor na origem ficam vazios e são registados como lacunas.
"""
import csv
import hashlib
import html
import json
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path

AUDIT = Path(__file__).resolve().parents[1]
ROOT = AUDIT.parent
sys.path.insert(0, str(AUDIT / "scripts"))
import crawl  # noqa: E402  (reprocessa as páginas a partir da cache, sem pedidos novos)

DATA = AUDIT / "data"
OUT = ROOT / "catalog"
INV = ROOT / "docs" / "inventario"
OUT.mkdir(exist_ok=True)
INV.mkdir(parents=True, exist_ok=True)
BASE = crawl.BASE

TYPE_NAMES = {
    1: ("Vestuário profissional", "vestuario-profissional"),
    2: ("Equipamentos de proteção individual", "equipamentos-de-protecao-individual"),
}


def slugify(s):
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()
    s = re.sub(r"[^a-zA-Z0-9]+", "-", s).strip("-").lower()
    return re.sub(r"-{2,}", "-", s)


def tidy(s):
    s = html.unescape(s or "").replace("\r", "")
    s = re.sub(r"[ \t ]+", " ", s)
    return "\n".join(line.strip() for line in s.split("\n")).strip()


def tidy_name(s):
    s = re.sub(r"\s+", " ", tidy(s)).strip(" -")
    return s


# ---------------------------------------------------------------- tamanhos

LETTER_ORDER = ["3XS", "XXS", "XS", "S", "M", "L", "XL", "XXL", "3XL", "4XL", "5XL", "6XL", "7XL"]
NORMAL = {"2XL": "XXL", "XXXL": "3XL", "XXXXL": "4XL"}
TOKEN = re.compile(r"^(?:(?:3XS|XXS|XS|S|M|L|XL|XXL|XXXL|[2-7]XL)(?:\s*\(\d{2}/\d{2}\)|/\d{2})?|\d{1,2}(?:/\d{1,2})?)$", re.I)


def norm_letter(t):
    u = t.upper()
    return NORMAL.get(u, u)


def parse_sizes(value, child=False):
    """Devolve (lista, estado). estado: ok | unico | vazio | nao-interpretado."""
    v = tidy(value)
    if not v:
        return [], "vazio"
    v = re.sub(r"^(adult|child)\s+", "", v, flags=re.I).strip()
    if re.fullmatch(r"(?i)[úu]nico|tamanho [úu]nico", v):
        return ["Único"], "unico"
    if re.fullmatch(r"(?i)adulto|crian[çc]a", v):
        return [], "vazio"
    # Intervalos: «35 - 48», «7 - 11», «S - XL»
    m = re.fullmatch(r"(\d{1,2})\s*(?:-|a|ao)\s*(\d{1,2})", v)
    if m:
        a, b = int(m.group(1)), int(m.group(2))
        if a < b and b - a <= 16:
            return [str(i) for i in range(a, b + 1)], "ok"
        return [], "nao-interpretado"
    m = re.fullmatch(r"([A-Z0-9]{1,4})\s*-\s*([A-Z0-9]{1,4})", v, re.I)
    if m and norm_letter(m.group(1)) in LETTER_ORDER and norm_letter(m.group(2)) in LETTER_ORDER:
        i, j = LETTER_ORDER.index(norm_letter(m.group(1))), LETTER_ORDER.index(norm_letter(m.group(2)))
        return (LETTER_ORDER[i:j + 1], "ok") if i < j else ([], "nao-interpretado")
    tokens = [t.strip() for t in re.split(r"[;,]\s*", v) if t.strip()]
    out = []
    last_num = -1
    for t in tokens:
        t2 = re.sub(r"(?i)\s*years?$", "", t).strip() if child else t
        if not TOKEN.match(t2):
            return [], "nao-interpretado"
        num = re.match(r"^(\d{1,2})", t2)
        if num:
            n = int(num.group(1))
            if n < last_num:
                return [], "nao-interpretado"
            last_num = n
        label = t2 if num else norm_letter(re.sub(r"\s+", " ", t2)) if t2.upper() in NORMAL else t2.upper()
        if child:
            label = f"{t2} anos"
        out.append(label)
    if len(set(out)) != len(out):
        return [], "nao-interpretado"
    return out, "ok"


# ---------------------------------------------------------------- famílias de cor (para filtrar)

FAMILIES = [
    ("Azul", r"blue|aqua|azul|marinho|azulina|celeste|turquesa|royal|denim|ganga|az\.|navy|petr[óo]leo|azafata"),
    ("Verde", r"verde|pist[áa]c?h?io|menta|cammo|selva|selv[áa]tico|tropa|lima|kelly|militar|garrafa|oliva|caqui|kaki"),
    ("Preto", r"preto|[ée]bano|negro"),
    ("Branco", r"branco|cru|marfim|angora"),
    ("Cinzento", r"cinz|chumbo|antracite|marengo|carv[ãa]o|cimento|prata|grafite"),
    ("Vermelho", r"red\b|vermelh|gren[áa]|bordeaux|bord[ôo]|bord[ôo]|cereja|vinho"),
    ("Amarelo", r"mustard|amarel|girassol|lim[ãa]o|ouro|dourad|mostarda"),
    ("Laranja", r"laranja|coral|alperce|p[êe]ssego"),
    ("Rosa", r"rosa|magenta|fuchsia|f[úu]csia"),
    ("Roxo", r"orqu[íi]dea|roxo|violeta|p[úu]rpura|lil[áa]s|beringela|geringela|malva"),
    ("Castanho e bege", r"castanh|bege|chocolate|camel|areia|tabaco|caf[ée]"),
]


def color_families(label):
    low = label.lower()
    found = []
    for fam, rx in FAMILIES:
        if re.search(rx, low) and fam not in found:
            found.append(fam)
    return found


# ---------------------------------------------------------------- descrição

def description_html(text):
    text = tidy(text)
    if not text:
        return ""
    lines = [ln for ln in text.split("\n") if ln and not re.fullmatch(r"_{3,}", ln)]
    out, bullets = [], []

    def flush():
        if bullets:
            out.append("<ul>" + "".join(f"<li>{html.escape(b)}</li>" for b in bullets) + "</ul>")
            bullets.clear()

    for ln in lines:
        if ln.startswith("-") and len(ln) > 1:
            bullets.append(ln.lstrip("- ").rstrip(";").strip())
            continue
        flush()
        if re.fullmatch(r"[^:]{2,40}:", ln):
            out.append(f"<h3>{html.escape(ln.rstrip(':'))}</h3>")
        elif re.match(r"^[A-ZÁÉÍÓÚÇ ]{3,30}:\s", ln):
            k, _, val = ln.partition(":")
            out.append(f"<p><strong>{html.escape(k.strip().capitalize())}:</strong> {html.escape(val.strip())}</p>")
        else:
            out.append(f"<p>{html.escape(ln)}</p>")
    flush()
    return "\n".join(out)


def short_text(listing_short, description):
    """Resumo curto: o texto da listagem se não estiver cortado; caso contrário, a 1.ª frase da descrição."""
    s = tidy_name(listing_short or "")
    if s and not s.endswith("...") and len(s) >= 15:
        return s
    d = re.sub(r"\s+", " ", tidy(description))
    d = re.sub(r"^(Descrição|Características):\s*-?\s*", "", d)
    m = re.match(r"(.{20,180}?[.;])(\s|$)", d)
    if m:
        return m.group(1).rstrip(";") + ("" if m.group(1).endswith(".") else ".")
    return ""


# ---------------------------------------------------------------- construção

def main():
    tree = json.loads((DATA / "tree.json").read_text())
    listings = json.loads((DATA / "listings.json").read_text())
    products_raw = json.loads((DATA / "products.json").read_text())
    derived_raw = json.loads((DATA / "derived.json").read_text()) if (DATA / "derived.json").exists() else []
    manifest = json.loads((DATA / "media-manifest.json").read_text()) if (DATA / "media-manifest.json").exists() else []
    media_ok = {}
    invalid_media = []
    for m in manifest:
        if m.get("status") != "ok":
            continue
        head = (AUDIT / m["file"]).read_bytes()[:8]
        # Só JPEG/PNG verdadeiros: o servidor antigo devolve por vezes uma página HTML com código 200.
        if head.startswith(b"\xff\xd8") or head.startswith(b"\x89PNG"):
            media_ok[m["url"]] = m
        else:
            invalid_media.append(m["url"])
    have_media = bool(manifest)

    # Reprocessa a partir da cache com o parser atual.
    products_raw = [dict(p, **{k: v for k, v in crawl.parse_product(p["id"], p["slug"]).items()}) for p in products_raw]
    derived_by_id = {d["id"]: d for d in derived_raw}

    # ---------- categorias
    categories, order = [], 0
    name_count = Counter(sc["name"].strip().lower() for t in tree["types"] for c in t["categories"] for sc in c["subcategories"])
    used_slugs = set()

    def unique_slug(base):
        s, i = base, 2
        while s in used_slugs:
            s, i = f"{base}-{i}", i + 1
        used_slugs.add(s)
        return s

    cat_by_key = {}
    for t in tree["types"]:
        tname, tslug = TYPE_NAMES.get(t["id"], (t["name"], slugify(t["name"])))
        order += 1
        tk = f"type:{t['id']}"
        cat_by_key[tk] = {"key": tk, "name": tname, "slug": unique_slug(tslug), "parent": None, "order": order,
                          "source_name": t["name"], "source_url": None, "title": "", "level": "tipo"}
        categories.append(cat_by_key[tk])
        for c in t["categories"]:
            order += 1
            ck = f"cat:{c['id']}"
            cname = tidy_name(c["name"])
            if cname.startswith("Especiais - "):
                cname = "Vestuário especial — " + cname.split(" - ", 1)[1]
            cat_by_key[ck] = {"key": ck, "name": cname, "slug": unique_slug(slugify(cname)), "parent": tk, "order": order,
                              "source_name": c["name"], "title": c.get("title", ""),
                              "source_url": f"{BASE}/{c['path']}" if c.get("path") else f"{BASE}/info/listar_categoria.php?nivelm=categoria&id={c['id']}",
                              "level": "categoria"}
            categories.append(cat_by_key[ck])
            for sc in c["subcategories"]:
                order += 1
                sk = f"sub:{sc['id']}"
                sname = tidy_name(sc["name"])
                ambiguous = name_count[sc["name"].strip().lower()] > 1
                base = slugify(f"{sname} {cname}") if ambiguous else slugify(sname)
                cat_by_key[sk] = {"key": sk, "name": sname, "slug": unique_slug(base), "parent": ck, "order": order,
                                  "source_name": sc["name"], "title": "", "source_url": f"{BASE}/{sc['path']}", "level": "subcategoria"}
                categories.append(cat_by_key[sk])

    leaf_of = {}
    declared = {}
    for leaf in listings:
        key = ("sub:" if leaf["level"] == "subcategoria" else "cat:") + str(leaf["leaf_id"])
        declared[key] = leaf["declared_total"]
        for i, it in enumerate(leaf["items"]):
            leaf_of.setdefault(it["id"], (key, i, it))

    # ---------- produtos
    gaps = []
    products, sku_seen, var_sku_seen, slug_seen = [], {}, set(), set()
    removed = []
    size_status = Counter()
    for p in sorted(products_raw, key=lambda x: x["id"]):
        pid = p["id"]
        lk = leaf_of.get(pid)
        if lk:
            cat_key, pos, listing_item = lk
        else:
            sc, cc = p.get("source_scatid"), p.get("source_catid")
            cat_key = f"sub:{sc}" if sc and f"sub:{sc}" in cat_by_key else (f"cat:{cc}" if cc and f"cat:{cc}" in cat_by_key else None)
            pos, listing_item = 9999, {}
        name = tidy_name(p.get("name") or p.get("title") or "")
        if re.fullmatch(r"Produto \d+ não existe!", name):
            removed.append({"source_id": pid, "source_key": f"p:{pid}", "source_url": f"{BASE}/info/{p['slug']}"})
            gaps.append({"tipo": "produto removido", "id_origem": pid, "referencia": "", "titulo": p["slug"], "url": f"{BASE}/info/{p['slug']}",
                         "observacao": "a página antiga responde «Produto não existe» (artigo removido mas ainda no sitemap): não importado; URL antiga responde 410"})
            continue
        if name.endswith(" - Casa das Fardas"):
            name = name[: -len(" - Casa das Fardas")]
        status = "publish"
        notes = []
        if not lk:
            status = "draft"
            notes.append("presente no sitemap mas não listado em nenhuma categoria da loja antiga: importado como rascunho para decisão")
        if not cat_key:
            notes.append("categoria não identificada")
        if p["status"] != "ok":
            notes.append("ficha sem dados estruturados: usados apenas os campos visíveis (sem descrição nem preço)")

        sku = (p.get("sku") or p.get("code_visible") or "").strip()
        if sku and sku in sku_seen:
            notes.append(f"referência {sku} repetida (também no artigo {sku_seen[sku]}): SKU deixado vazio, código guardado em _cdf_source_code")
            source_code, sku = sku, ""
        else:
            source_code = sku
            if sku:
                sku_seen[sku] = pid

        slug = slugify(re.sub(r"-\d+\.html$", "", p["slug"]))
        if slug in slug_seen:
            slug = f"{slug}-{slugify(source_code) or pid}"
        slug_seen.add(slug)

        price = None
        try:
            if p.get("price") not in (None, "") and float(p["price"]) > 0:
                price = round(float(p["price"]), 2)
        except ValueError:
            pass
        if price is None:
            notes.append("sem preço na origem: fica sob orçamento")

        props = [(k.strip(), tidy_name(v)) for k, v in (p.get("properties") or []) if tidy_name(v)]
        if not props:
            props = [(k, v) for k, v in (p.get("specs") or []) if v]
        specs, sizes_adult, sizes_child, size_state = [], [], [], "vazio"
        for k, v in props:
            kn = "Norma europeia" if k.lower() == "norma europeia" else k
            if k.lower() in ("tamanhos adulto", "tamanhos", "tamanho"):
                s, st = parse_sizes(v)
                sizes_adult += s
                size_state = st if size_state in ("vazio", "unico") else size_state
            elif k.lower() == "tamanhos criança":
                s, st = parse_sizes(v, child=True)
                sizes_child += s
                if st == "nao-interpretado":
                    size_state = st
            specs.append([kn, v])
        sizes = [s for s in sizes_adult + sizes_child if s != "Único"]
        if size_state == "nao-interpretado":
            sizes = []
            notes.append("tamanhos em texto livre não interpretável: sem seleção de tamanho (mostrado nas especificações)")
        size_status[size_state if not (size_state == "ok" and len(sizes) < 2) else "um-tamanho"] += 1

        # Variações (derivados).
        variations, attr_groups = [], Counter()
        for d in p.get("derived", []):
            dr = derived_by_id.get(d["id"], {})
            group = d["group"]
            # «Tamanhos» na origem são medidas/modelos (cintas, lonas, postes): atributo «Medida».
            attr = "cor" if group.lower().startswith("cor") else "medida" if group.lower().startswith("tamanho") else slugify(group)
            attr_groups[attr] += 1
            vsku = (dr.get("code_visible") or "").strip()
            if vsku and (vsku in var_sku_seen or vsku in sku_seen):
                vsku = ""
            if vsku:
                var_sku_seen.add(vsku)
            imgs = [u for u in (dr.get("images") or []) if not have_media or u in media_ok]
            variations.append({
                "source_id": d["id"], "source_key": f"d:{d['id']}", "attribute": attr, "label": tidy_name(d["label"]),
                "sku": vsku, "images": imgs[:1], "source_url": f"{BASE}/info/{d['slug']}",
                "families": color_families(d["label"]) if attr == "cor" else [],
                "fetched": bool(dr),
            })
        if len(attr_groups) > 1:
            notes.append("derivados com mais de um tipo de atributo: cada derivado é uma variação de um único atributo")
        families = []
        for v in variations:
            for f_ in v["families"]:
                if f_ not in families:
                    families.append(f_)

        images = [u for u in p.get("images", []) if not have_media or u in media_ok]
        bad = [u for u in p.get("images", []) if u in invalid_media]
        if bad:
            notes.append("ficheiro de imagem inválido na origem (o servidor devolve HTML): " + ", ".join(u.rsplit("/", 1)[1] for u in bad))
        if not images:
            notes.append("sem fotografia na origem")
        brand = (p.get("brand") or p.get("brand_visible") or "").strip()
        if not brand:
            notes.append("marca não indicada na origem")

        description = tidy(p.get("description") or "")
        products.append({
            "source_id": pid,
            "source_key": f"p:{pid}",
            "source_url": f"{BASE}/info/{p['slug']}",
            "slug": slug,
            "name": name,
            "status": status,
            "sku": sku,
            "source_code": source_code,
            "brand": brand,
            "category": cat_key,
            "menu_order": pos,
            "short_description": short_text(listing_item.get("short"), description),
            "description_html": description_html(description),
            "description_raw": description,
            "specs": specs,
            "sizes": sizes,
            "color_families": families,
            "price_ex_vat": price,
            "source_availability": (p.get("availability") or "").rsplit("/", 1)[-1],
            "images": images,
            "variations": variations,
            "sources": p.get("sources", []),
            "notes": notes,
        })
        for n in notes:
            gaps.append({"tipo": "produto", "id_origem": pid, "referencia": source_code, "titulo": name, "url": f"{BASE}/info/{p['slug']}", "observacao": n})

    brands = sorted({p["brand"] for p in products if p["brand"]})
    colors = sorted({v["label"] for p in products for v in p["variations"] if v["attribute"] == "cor"})

    catalog = {
        "generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "source": BASE,
        "notes": "Recolha pública (sitemap, menu, listagens, fichas mobile e derivados). Preços da origem sem IVA e não validados.",
        "categories": categories,
        "brands": brands,
        "colors": colors,
        "products": products,
        "removed": removed,
    }
    (OUT / "catalog.json").write_text(json.dumps(catalog, ensure_ascii=False, indent=1))

    # ---------- inventário de conteúdos
    rows = []

    def row(url, tipo, sid, ref, titulo, cat, estado, origem, destino, obs=""):
        rows.append({"url_antiga": url, "tipo": tipo, "id_origem": sid, "referencia": ref, "titulo": titulo, "categoria": cat,
                     "estado_extracao": estado, "origem": origem, "destino_proposto": destino, "observacoes": obs})

    row(f"{BASE}/", "página", "home", "", "Página inicial", "", "extraído", "html", "/", "texto de apresentação reutilizado; banners antigos com texto embutido não migrados")
    row(f"{BASE}/index.php", "página", "home", "", "Página inicial (index.php)", "", "extraído", "sitemap", "/ (301)")
    row(f"{BASE}/m/index.php", "página", "home-m", "", "Página inicial mobile", "", "extraído", "sitemap", "/ (301)")
    row(f"{BASE}/info/contactos.php", "página", "contactos", "", "Contactos", "", "extraído", "sitemap", "/contactos/", "morada, telefone, emails, horário e departamentos; texto RGPD antigo NÃO copiado (afirmava não guardar dados no site)")
    for ext in tree.get("external", []):
        row(ext["url"], "ligação externa", "brindes", "", ext["name"], "", "registado", "menu", ext["url"], "catálogo de brindes num serviço externo; mantido como ligação externa no menu")
    for c in categories:
        if c["level"] == "tipo":
            row("", "tipo de produto", c["key"], "", c["source_name"], "", "extraído", "menu", f"/categoria/{c['slug']}/", "agrupador do menu sem URL própria na origem")
            continue
        d = declared.get(c["key"])
        obs = c["title"]
        if d == 0:
            obs = (obs + "; " if obs else "") + "categoria vazia na origem"
        row(c["source_url"], c["level"], c["key"], "", c["source_name"], cat_by_key[c["parent"]]["name"], "extraído", "menu+listagem",
            f"/categoria/{c['slug']}/", obs + (f"; {d} artigos declarados" if d else ""))
    for p in products:
        row(p["source_url"], "produto", p["source_key"], p["source_code"], p["name"], cat_by_key[p["category"]]["name"] if p["category"] else "",
            "extraído" if p["description_raw"] or p["specs"] else "parcial", "+".join(p["sources"]),
            f"/produto/{p['slug']}/" + (" (rascunho)" if p["status"] == "draft" else ""), "; ".join(p["notes"]))
        for v in p["variations"]:
            row(v["source_url"], f"derivado ({v['attribute']})", v["source_key"], v["sku"], f"{p['name']} — {v['label']}", "",
                "extraído" if v["fetched"] else "pendente", "ficha do produto", f"/produto/{p['slug']}/?attribute_pa_{v['attribute']}={slugify(v['label'])}", "variação do produto pai")
    for r_ in removed:
        row(r_["source_url"], "produto", r_["source_key"], "", "(removido na origem)", "", "removido", "sitemap", "410 Gone", "página antiga diz «Produto não existe»; ainda listada no sitemap antigo")
    for name in ("tela_27.jpg", "tela_29.jpg", "tela_31.jpg", "tela_32.jpg", "tela_33.jpg", "tela_34.jpg"):
        row(f"{BASE}/back/fotos/casa1476/paineis/{name}", "imagem (banner)", name, "", "Banner da página inicial", "", "descarregado", "html", "não migrado",
            "composição antiga 985×280 com texto embutido; substituída por secções editáveis com fotografias de produto")
    row(f"{BASE}/back/fotos/casa1476/design/logo1.png", "imagem (logótipo)", "logo", "", "Logótipo", "", "descarregado", "html", "tema (assets/img)", "só existe em PNG 155×100; pedir versão vetorial")
    with (INV / "content-inventory.csv").open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows(rows)

    with (INV / "import-gaps.csv").open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=["tipo", "id_origem", "referencia", "titulo", "url", "observacao"])
        w.writeheader()
        w.writerows(gaps)

    stats = {
        "fonte": {
            "urls_sitemap_produtos": len((AUDIT / "raw" / "product-urls.txt").read_text().split()),
            "folhas_menu": sum(len(c["subcategories"]) or 1 for t in tree["types"] for c in t["categories"]),
            "artigos_declarados_listagens": sum(l["declared_total"] for l in listings),
            "artigos_listados_unicos": len(leaf_of),
            "fichas_recolhidas": len(products_raw),
            "fichas_com_dados_estruturados": sum(1 for p in products_raw if p["status"] == "ok"),
            "derivados_referidos": sum(len(p.get("derived", [])) for p in products_raw),
            "derivados_recolhidos": len(derived_raw),
            "imagens_referidas": len({u for p in products_raw for u in p.get("images", [])} | {u for d in derived_raw for u in d.get("images", [])}),
            "imagens_descarregadas": len(media_ok) + len(invalid_media),
            "imagens_invalidas_na_origem": len(invalid_media),
        },
        "catalogo": {
            "categorias": len(categories),
            "categorias_vazias_na_origem": sum(1 for k, v in declared.items() if v == 0),
            "produtos": len(products),
            "removidos_na_origem": len(removed),
            "produtos_publicados": sum(1 for p in products if p["status"] == "publish"),
            "produtos_rascunho": sum(1 for p in products if p["status"] == "draft"),
            "produtos_com_preco": sum(1 for p in products if p["price_ex_vat"]),
            "produtos_variaveis": sum(1 for p in products if p["variations"]),
            "variacoes": sum(len(p["variations"]) for p in products),
            "produtos_com_tamanhos_selecionaveis": sum(1 for p in products if len(p["sizes"]) >= 2),
            "estado_tamanhos": dict(size_status),
            "marcas": len(brands),
            "cores": len(colors),
            "cores_sem_familia": sorted({v["label"] for p in products for v in p["variations"] if v["attribute"] == "cor" and not v["families"]}),
            "imagens_produto": sum(len(p["images"]) for p in products),
            "imagens_variacao": sum(len(v["images"]) for p in products for v in p["variations"]),
            "lacunas_registadas": len(gaps),
        },
    }
    (INV / "catalog-stats.json").write_text(json.dumps(stats, ensure_ascii=False, indent=1))
    print(json.dumps(stats, ensure_ascii=False, indent=1))


if __name__ == "__main__":
    main()
