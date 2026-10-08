#!/usr/bin/env bash
# Instala a loja Casa das Fardas localmente e importa o catálogo (idempotente).
#
#   bin/setup.sh            instala se necessário e (re)importa sem duplicar
#   bin/setup.sh --reset    apaga base de dados, ficheiros e anexos locais e reinstala de raiz
#
# Requisitos: Docker (docker compose ou docker-compose), porta 8092 livre, Internet na 1.ª instalação.
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
set -a; . ./.env; set +a
if docker compose version >/dev/null 2>&1; then COMPOSE="docker compose"; else COMPOSE="docker-compose"; fi
DOMAIN="${CDF_DOMAIN:-casadasfardas.localhost}"
PORT="${WP_PORT:-8092}"
URL="http://${DOMAIN}:${PORT}"

if [ "${1:-}" = "--reset" ]; then
  read -r -p "Apagar a base de dados e os ficheiros locais desta loja? [s/N] " ok
  [ "$ok" = "s" ] || exit 1
  $COMPOSE down -v --remove-orphans
  rm -f .credentials.local
fi

$COMPOSE up -d db wordpress mailpit
echo "A aguardar o servidor web em ${URL} ..."
for _ in $(seq 1 60); do curl -s -o /dev/null "${URL}/wp-login.php" && break; sleep 2; done
$COMPOSE exec -T wordpress chown www-data:www-data /var/www/cdf-private

wp() { $COMPOSE run --rm -T cli wp "$@"; }

if ! wp core is-installed >/dev/null 2>&1; then
  ADMIN_PASS="$(openssl rand -hex 12)"
  wp core install --url="${URL}" --title="Casa das Fardas" --admin_user=cdf-admin \
    --admin_password="${ADMIN_PASS}" --admin_email=admin@casadasfardas.test --skip-email
  printf 'URL=%s/wp-admin/\nUSER=cdf-admin\nPASS=%s\n' "${URL}" "${ADMIN_PASS}" > .credentials.local
  chmod 600 .credentials.local
  echo "Credenciais locais em .credentials.local (não versionar)."
fi

wp language core install pt_PT --activate >/dev/null 2>&1 || echo "Aviso: pacote pt_PT indisponível."
wp plugin is-installed woocommerce || wp plugin install woocommerce
wp plugin activate woocommerce
wp language plugin install woocommerce pt_PT >/dev/null 2>&1 || true
wp plugin delete akismet hello >/dev/null 2>&1 || true
wp theme activate casadasfardas
wp plugin activate cdf-core
wp theme delete twentytwentythree twentytwentyfour twentytwentyfive >/dev/null 2>&1 || true

# Loja (moeda, IVA, envios, pagamento de teste, emails), catálogo, páginas e menus.
wp cdf setup
if [ ! -f catalog/catalog.json ]; then
  echo "Falta catalog/catalog.json — correr audit/scripts/build_catalog.py (ver README)."; exit 1
fi
FORCE="${CDF_FORCE:+--force}"
wp cdf import ${FORCE} --report=/exports/import-report.json
wp cdf pages ${FORCE}
wp wc update >/dev/null 2>&1 || true
wp action-scheduler run --batches=5 >/dev/null 2>&1 || true
wp rewrite flush
wp cdf status

echo
echo "Loja:          ${URL}/"
echo "Backoffice:    ${URL}/wp-admin/ (credenciais em .credentials.local)"
echo "Emails locais: http://localhost:${MAILPIT_PORT:-8028}"
