#!/usr/bin/env bash
# Dados de TESTE para a demonstração (clientes fictícios e uma encomenda do cliente B).
# Credenciais geradas localmente e guardadas em .credentials.local (não versionado).
set -euo pipefail
cd "$(dirname "$0")/.."
touch .credentials.local && chmod 600 .credentials.local
grep -q '^CLIENTE_A_PASS=' .credentials.local && { echo "Fixtures já existem."; exit 0; }
PA="$(openssl rand -hex 10)"; PB="$(openssl rand -hex 10)"
bin/wp user create cliente-a cliente.a@casadasfardas.test --role=customer --first_name="Cliente" --last_name="A (teste)" --user_pass="$PA" --porcelain >/dev/null 2>&1 || true
bin/wp user create cliente-b cliente.b@casadasfardas.test --role=customer --first_name="Cliente" --last_name="B (teste)" --user_pass="$PB" --porcelain >/dev/null 2>&1 || true
ORDER=$(bin/wp eval '
$u = get_user_by("login","cliente-b");
$o = wc_create_order(array("customer_id"=>$u->ID));
$pid = wc_get_product_id_by_sku("B4035");
$o->add_product(wc_get_product($pid), 1);
$o->set_address(array("first_name"=>"Cliente","last_name"=>"B (teste)","email"=>"cliente.b@casadasfardas.test","address_1"=>"Rua Fictícia 2","postcode"=>"8000-000","city"=>"Faro","country"=>"PT"), "billing");
$o->calculate_totals(); $o->update_status("processing","Encomenda de teste (fixtures)."); echo $o->get_id();
' 2>/dev/null | tail -1)
{
  echo "CLIENTE_A_USER=cliente-a"; echo "CLIENTE_A_EMAIL=cliente.a@casadasfardas.test"; echo "CLIENTE_A_PASS=$PA"
  echo "CLIENTE_B_USER=cliente-b"; echo "CLIENTE_B_PASS=$PB"; echo "CLIENTE_B_ORDER=$ORDER"
} >> .credentials.local
echo "Clientes de teste criados; encomenda do cliente B: $ORDER"
