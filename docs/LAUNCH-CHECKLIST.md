# Checklist de lançamento — Casa das Fardas

Estado à data de entrega: **staging local, não publicado.** O site atual, o DNS e os pagamentos reais não foram alterados.
Nada nesta lista está feito em produção; cada ponto tem de ser confirmado por quem tiver o acesso respetivo.

Legenda: ☐ por fazer · ⚠ bloqueia o lançamento.

## 1. Decisões e dados do cliente (bloqueiam o lançamento)

- ⚠ ☐ **Preços**: validar os 948 preços importados (vêm do JSON-LD da loja antiga, não eram mostrados ao público, assumidos **sem IVA**). Marcar como validados (WooCommerce → Produtos → ação em massa «Marcar preço como validado») ou corrigir. Os preços das cores foram herdados do produto-pai (`inherited-unvalidated`).
- ⚠ ☐ **Modo do catálogo**: decidir «Loja» (compra online) ou «Só orçamento» (WooCommerce → Casa das Fardas). O staging está em modo Loja para demonstrar o checkout.
- ⚠ ☐ **Disponibilidade**: a loja antiga marcava todos os artigos como esgotados (`OutOfStock`) sem gestão de stock real. A nova loja vende com «Disponibilidade confirmada após a encomenda». Confirmar esta política ou fornecer stock real.
- ⚠ ☐ **Pagamentos**: escolher fornecedor (ex.: Multibanco/MB WAY/cartão através de um PSP português, Stripe, transferência). Instalar o plugin oficial do fornecedor, configurar credenciais **de produção fora do repositório**, testar em sandbox, desligar o «Pagamento de teste» (desaparece sozinho com `WP_ENVIRONMENT_TYPE=production`).
- ⚠ ☐ **Envios**: transportadora(s), tabela de portes, zonas (continente/ilhas), prazos e eventual levantamento na loja. Hoje: «Envio para morada (portes confirmados antes do envio)» a 0 € e «Levantamento na loja» — ambos **por validar**.
- ⚠ ☐ **IVA**: confirmar 23% (continente); taxas das regiões autónomas (22%/16% — validar regras de localização) e vendas intracomunitárias B2B, se aplicável.
- ⚠ ☐ **Páginas legais** (estão «Pendentes de revisão», não públicas): Termos e condições, Política de privacidade, Política de cookies, Entregas, Trocas e devoluções, Pagamentos. Completar todos os «[A VALIDAR]», rever com o responsável do negócio/jurídico e publicar. Os termos passam a ser obrigatórios no checkout assim que a página for publicada.
- ☐ Livro de Reclamações Eletrónico: confirmar adesão da empresa (ligação no rodapé para livroreclamacoes.pt) e entidade de resolução alternativa de litígios competente.
- ☐ NIF da empresa e dados legais completos (WooCommerce → Casa das Fardas → NIF).
- ☐ Email que recebe pedidos de orçamento em produção (WooCommerce → Casa das Fardas). Em staging vai sempre para `orcamentos@casadasfardas.test` (capturado pelo Mailpit).
- ☐ Prazo de conservação dos pedidos de orçamento (por omissão 365 dias) e alinhamento com a política de privacidade.
- ☐ Os 3 produtos em rascunho (estavam no sitemap mas não em nenhuma categoria) — publicar, manter ocultos ou apagar (ver `docs/inventario/import-gaps.csv`).
- ☐ Logótipo em vetor (SVG/AI/EPS) e favicon de alta resolução.
- ☐ Vantagens da barra superior: confirmar as três frases (todas vêm do texto da loja antiga).
- ☐ Fotografia: recomendada sessão própria (loja, equipa, bordados) para hero e personalização.

## 2. Alojamento e servidor

- ☐ PHP 8.1+ (testado 8.3), MySQL 8 / MariaDB 10.6+ (testado MariaDB 11.4), HTTPS válido, HTTP/2 ou 3, compressão gzip/brotli.
- ☐ `WP_ENVIRONMENT_TYPE=production`, `DISALLOW_FILE_EDIT=true`, `WP_DEBUG=false`, salts novos.
- ☐ `CDF_PRIVATE_DIR` fora da raiz pública (anexos dos pedidos de orçamento); garantir permissões de escrita do PHP e **backup** deste diretório.
- ☐ SMTP transacional autenticado (SPF, DKIM, DMARC do domínio) via `CDF_SMTP_*` em variáveis de ambiente; testar emails de encomenda, recuperação de acesso e orçamento.
- ☐ Cron real do sistema a chamar `wp-cron.php` (limpeza diária de pedidos antigos, Action Scheduler do WooCommerce) e `DISABLE_WP_CRON=true`.
- ☐ Cache de página **com exclusões**: `/carrinho/`, `/finalizar-compra/`, `/minha-conta/*`, `/pedido-de-orcamento/`, `/contactos/`, `?add-to-cart=`, `wc-ajax`, e bypass quando existirem cookies `woocommerce_items_in_cart`, `woocommerce_cart_hash`, `wp_woocommerce_session_*`, `wordpress_logged_in_*`. Confirmar que o contador do carrinho/orçamento não aparece a outro visitante.
- ☐ Cópias de segurança diárias (base de dados + `wp-content/uploads` + `CDF_PRIVATE_DIR`) com teste de **restauração** documentado.
- ☐ Atualizações automáticas de segurança (core) e plano de atualização mensal de plugins.

## 3. Migração de conteúdos e domínio

- ☐ Importar o catálogo no servidor: `wp cdf import --dry-run` → `wp cdf import --report=…` (idempotente, retoma por lotes com `--offset/--limit`).
- ☐ `wp cdf pages` (não sobrepõe páginas já editadas).
- ☐ Troca de domínio de staging para `https://www.casadasfardas.pt` com `wp search-replace 'http://staging…' 'https://www.casadasfardas.pt' --all-tables --precise` (trata dados serializados); confirmar `home`/`siteurl`.
- ☐ Uniformizar hostname e protocolo: `http://` e `casadasfardas.pt` sem `www` → 301 para `https://www.casadasfardas.pt` num único salto.
- ☐ Redirecionamentos da loja antiga: ativos pelo plugin (`cdf-core/inc/redirects.php`). Testar no servidor: `wp cdf redirects docs/inventario/content-inventory.csv /tmp/redirect-map.csv --base-url=https://www.casadasfardas.pt` → 0 falhas.
- ☐ Sitemap: `https://www.casadasfardas.pt/wp-sitemap.xml` (só URLs canónicas indexáveis).

## 4. Abrir à indexação (só no dia do lançamento)

- ☐ Confirmar `WP_ENVIRONMENT_TYPE=production` (retira o `noindex` global do staging e o cabeçalho `X-Robots-Tag`).
- ☐ Definições → Leitura: «Desencorajar motores de pesquisa» **desligado**.
- ☐ Se usar `CDF_STAGING_KEY`, remover a variável.
- ☐ Verificar com `curl -I` a página inicial, uma categoria, um produto: sem `noindex`; carrinho, checkout, conta e pesquisa: com `noindex`.
- ☐ Search Console: verificar propriedade do domínio, submeter o sitemap, inspecionar 5 URLs (início, categoria, produto, personalização, contactos). **Requer acesso do cliente — não foi feito.**
- ☐ Merchant Center (opcional): só com preços validados e política de devoluções/envios publicada; feed alinhado com a escolha de variantes (uma página por produto, cor por parâmetro `attribute_pa_cor`).
- ☐ Analytics/pixel: só se o cliente quiser, e apenas depois de consentimento (instalar gestor de consentimento; nenhum está ativo).

## 5. Verificação pós-lançamento

| Quando | O quê |
|---|---|
| Dia 0 | Encomenda real de valor baixo com o gateway real + reembolso; pedido de orçamento real; emails recebidos; 20 URLs antigas aleatórias a responder 301 → 200. |
| Dia 7 | Search Console: cobertura, erros 404/5xx; WooCommerce → URLs antigas (registo de URLs antigas sem destino); Core Web Vitals de laboratório. |
| Dia 14 | Repetir; corrigir URLs antigas com tráfego sem destino; rever pedidos de orçamento e encomendas. |
| Dia 30 | Dados de campo (CrUX/Search Console) se houver tráfego suficiente; posições e impressões das páginas migradas vs. antigas. |

Estas verificações futuras **não foram feitas**; dependem do lançamento e de acesso às ferramentas do cliente.
