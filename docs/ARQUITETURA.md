# Arquitetura, decisões técnicas e plugins

## 1. Visão geral

```
WordPress 7.1.2 (pt_PT) + WooCommerce 11.1.2 (HPOS)          MariaDB 11.4 · PHP 8.3 · Apache
├── tema  casadasfardas      apresentação: templates PHP renderizados no servidor, theme.json, CSS/JS por componente
└── plugin cdf-core          negócio: dados da empresa, preços/IVA, tamanhos, NIF, pesquisa, filtros,
                             orçamento, blocos dinâmicos, SEO, redirecionamentos, privacidade, importador, CLI
```

Sem page builder e sem plugins de terceiros além do WooCommerce (que já inclui Marcas). O tema funciona sem o plugin, com menos funcionalidades. Nenhum ficheiro do core do WordPress ou do WooCommerce foi alterado.

## 2. Tema

| Decisão | Motivo |
|---|---|
| Tema clássico com `theme.json` (não *block theme*) | Controlo fino do HTML do mega menu, das listagens e das fichas, HTML 100% renderizado no servidor, sem dependência do editor de sites. As **páginas comerciais** (início, setores, personalização, sobre, contactos, orçamento) são **blocos Gutenberg editáveis**, com estilos de bloco próprios e blocos dinâmicos do plugin. |
| Overrides WooCommerce mínimos | `archive-product.php`, `content-product.php`, `content-single-product.php`, `loop/no-products-found.php`, `cart/cart-empty.php`. Carrinho, checkout e conta usam os templates originais (só CSS). Versões de referência indicadas no cabeçalho de cada ficheiro, para revisão quando o WooCommerce as alterar. |
| CSS por componente | `base` (sempre), `content` (páginas), `shop` (listagens/blocos de produtos), `product` (ficha), `forms` (orçamento/contacto), `checkout` (carrinho/checkout/conta). Estilos do WooCommerce e da biblioteca de blocos completa não são carregados (só o CSS dos blocos presentes). |
| JS sem dependências | `header.js` (mega menu, gaveta, sugestões), `shop.js` (painel de filtros), `product.js` (galeria, zoom, amostras, barra de compra). jQuery só onde o WooCommerce o exige (formulário de variações, checkout). Sem fragmentos de carrinho AJAX em todas as páginas. |
| Fontes locais | Bricolage Grotesque + Inter (WOFF2 variáveis, OFL), pré-carregadas. |
| Acessibilidade | Ligação «saltar para o conteúdo», landmarks, mega menu por botões com `aria-expanded`, `<dialog>` para a gaveta, combobox ARIA na pesquisa, `aria-invalid` + resumo de erros nos formulários, `prefers-reduced-motion`. |

## 3. Plugin `cdf-core`

| Ficheiro | Responsabilidade |
|---|---|
| `inc/business.php` | Dados da empresa e definições (WooCommerce → Casa das Fardas). |
| `inc/environment.php` | SMTP por variáveis de ambiente, `noindex` e acesso restrito fora de produção, WebP nos tamanhos intermédios, cabeçalhos de segurança, sem XML-RPC nem enumeração de utilizadores, `nocache` em páginas de sessão. |
| `inc/catalog.php` | URLs de categoria/marca, sem avaliações, imagem de categoria, introdução de categoria, caixa «Origem», estado de validação do preço. |
| `inc/pricing.php` | Modo Loja/Só orçamento, nunca vender a 0 €, preço s/ IVA + c/ IVA, disponibilidade «confirmada após a encomenda», dados estruturados sem oferta quando não há preço, termos/privacidade só quando publicados. |
| `inc/sizes.php` | Tamanho validado no carrinho/encomenda. |
| `inc/checkout.php` | NIF opcional com dígito de controlo, guardado na encomenda (HPOS) e mostrado em emails/backoffice. |
| `inc/search.php` | Pesquisa por referência (produto e variações) e REST `cdf/v1/suggest`. |
| `inc/filters.php` | Facetas marca/cor/tamanho com contagens no contexto. |
| `inc/quote.php` | Lista de orçamento, formulário, anexos privados, registo, emails, retenção. |
| `inc/blocks.php` + `assets/blocks-editor.js` | Blocos dinâmicos `cdf/products`, `cdf/categories`, `cdf/form`, `cdf/business-info`. |
| `inc/seo.php` | Sistema único de SEO (ver `SEO-MIGRATION.md`). |
| `inc/redirects.php` | 301/410 das URLs antigas + registo de URLs sem destino. |
| `inc/privacy.php` | Exportação/eliminação de dados (ferramentas nativas do WordPress) e texto sugerido para a política. |
| `inc/gateway-test.php` | Pagamento de teste — só existe fora de produção. |
| `inc/importer/*`, `inc/cli.php` | `wp cdf setup | import | pages | status | redirects`. |

## 4. Checkout e encomendas

- **Estratégia: checkout clássico (shortcodes `[woocommerce_cart]` / `[woocommerce_checkout]`)**, não os blocos de checkout. Motivos: compatibilidade direta com o gateway de teste e com os gateways portugueses comuns (que suportam ambos), campo NIF com validação simples via hooks clássicos, e templates estilizados pelo tema. O plugin declara compatibilidade com HPOS e **incompatibilidade com os blocos de checkout**, para o WooCommerce avisar se alguém trocar a página.
- **HPOS ativo** (`custom_orders_table`), sem sincronização para tabelas antigas. Todo o código usa `wc_get_order()`/`WC_Order` e meta via CRUD; nenhuma escrita direta em tabelas de encomendas.
- Ao escolher o gateway real: confirmar que suporta HPOS e o checkout clássico; não misturar extensões de checkout incompatíveis.
- Convidado permitido; criação de conta opcional no checkout; recuperação de acesso pelo email.
- Envios e pagamentos reais: **pendentes** (ver `LAUNCH-CHECKLIST.md`).

## 5. Pedidos de orçamento — segurança

| Ameaça | Controlo |
|---|---|
| Spam/bots | Honeypot, token assinado com tempo mínimo de 3 s (sem captcha), nonce, limite de 6 envios/hora por IP (hash HMAC, IP não guardado). |
| Ficheiros maliciosos | Só PNG/JPG/PDF ≤ 5 MB; extensão **e** MIME real (`finfo`) **e** conteúdo (`getimagesize` / assinatura `%PDF-`); SVG recusado; nome aleatório de 128 bits; `chmod 0640`. |
| Exposição de anexos | Guardados em `CDF_PRIVATE_DIR` **fora da raiz pública** (volume Docker `/var/www/cdf-private`); `.htaccess` «deny all» como segunda barreira; download só por `admin-post.php` com capacidade `manage_woocommerce` + nonce, `Content-Disposition: attachment`, `nosniff`. |
| Confirmação falsa | Mensagem de sucesso só depois de o pedido ser gravado e o email interno aceite; token de sucesso de uso único (transient) — recarregar não repete a confirmação. |
| Envio para o cliente real em testes | Fora de produção o destinatário é sempre `orcamentos@casadasfardas.test` e o SMTP aponta para o Mailpit. |
| Privacidade | Retenção configurável (365 dias por omissão) com limpeza diária; exportação/eliminação por email nas ferramentas de privacidade do WordPress. |

## 6. Plugins

| Plugin | Finalidade | Custo/licença | Compatibilidade | Alternativa |
|---|---|---|---|---|
| WooCommerce 11.1.2 | Loja, carrinho, checkout, conta, encomendas, IVA, Marcas | Gratuito, GPL | HPOS ativo | — |
| Casa das Fardas — Núcleo (`cdf-core`) | Negócio (ver §3) | Próprio, GPL | WP 6.8+, PHP 8.1+, Woo 9.6+ | — |

**A escolher pelo cliente** (não instalados): gateway(s) de pagamento, eventual plugin de transportadora, ferramenta de consentimento de cookies (só se forem ativadas medições/marketing), plugin de faturação certificada (se a fatura for emitida a partir da loja). Nenhum plugin premium foi instalado.

## 7. Ambiente e operação

- `docker-compose.yml`: `db` (MariaDB), `wordpress` (Apache/PHP 8.3), `mailpit` (captura de email), `cli` (WP-CLI). Volumes: base de dados, WordPress, anexos privados.
- Segredos fora do repositório: `.env` (a partir de `.env.example`), `.credentials.local` (gerado). SMTP real via `CDF_SMTP_*`.
- Domínio de staging nunca codificado no tema/plugin (todas as URLs vêm de `home_url()`/APIs).
- Cópias de segurança e restauro: ver `LAUNCH-CHECKLIST.md` §2.
