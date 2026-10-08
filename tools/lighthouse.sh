#!/usr/bin/env bash
# Lighthouse (laboratório, simulação) em mobile e desktop: início, categoria, produto, orçamento.
# Resultados em docs/qa/lighthouse/. Nota: em staging o SEO perde pontos só pelo noindex (is-crawlable).
set -euo pipefail
cd "$(dirname "$0")"
BASE="${CDF_URL:-http://casadasfardas.localhost:8092}"
export CHROME_PATH="${CHROME_PATH:-$(node -e "import('./browser.mjs').then(m=>console.log(m.chromiumPath()))")}"
OUT=../docs/qa/lighthouse; mkdir -p "$OUT"
for page in "inicio:/" "categoria:/categoria/hotelaria/" "produto:/produto/t-shirt-senhora-modelo-cancun/" "orcamento:/pedido-de-orcamento/"; do
  name="${page%%:*}"; path="${page#*:}"
  for form in mobile desktop; do
    extra=""; [ "$form" = "desktop" ] && extra="--preset=desktop"
    npx -y lighthouse@12 "${BASE}${path}" --quiet --chrome-flags="--headless=new" $extra \
      --only-categories=performance,accessibility,best-practices,seo --output=json --output-path="$OUT/${name}-${form}.json" >/dev/null 2>&1 || echo "falhou: $name $form"
  done
done
python3 - "$OUT" <<'PY'
import json, glob, os, sys
out = sys.argv[1]; rows = []
for f in sorted(glob.glob(f'{out}/*.json')):
    d = json.load(open(f)); a = d['audits']; c = d['categories']
    n = os.path.basename(f)[:-5]
    rows.append(f"| {n} | {round(c['performance']['score']*100)} | {round(c['accessibility']['score']*100)} | {round(c['best-practices']['score']*100)} | {round(c['seo']['score']*100)} | {a['largest-contentful-paint']['displayValue']} | {a['cumulative-layout-shift']['displayValue']} | {a['total-blocking-time']['displayValue']} | {a['total-byte-weight']['displayValue']} |")
open(f'{out}/resumo.md', 'w').write('| Página-formato | Desempenho | Acessibilidade | Boas práticas | SEO* | LCP | CLS | TBT | Peso |\n|---|---|---|---|---|---|---|---|---|\n' + '\n'.join(rows) + '\n\n*Em staging o SEO perde pontos apenas pelo `noindex` intencional.\n')
print(open(f'{out}/resumo.md').read())
PY
