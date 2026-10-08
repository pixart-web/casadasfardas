# SEO e migração — Casa das Fardas

«Preparado para SEO» significa aqui requisitos implementados e verificados por testes (`tools/tests/seo.spec.mjs`). Não há promessa de posições, tráfego ou pontuações.

## 1. Origem

| Facto | Evidência |
|---|---|
| Plataforma antiga | PHP 5.3.29 + nginx, CMS/loja próprios da Optimeios (cabeçalho `X-Powered-By`, rodapé). **Não é WordPress.** |
| Encoding | ISO-8859-1/Windows-1252; convertido para UTF-8 na recolha (cópia bruta guardada em `audit/cache/`, texto original em `_cdf_description_raw`). |
| Sitemap | `sitemap.xml` (índice) → `produtos.xml` (966 URLs, última alteração 2022-06-13); `posts.xml` vazio. O `robots.txt` aponta para `sitemap.txt`, que está vazio. |
| Versão mobile | `/m/…` duplicada (mesmos conteúdos). |
| Listagens | Carregadas por AJAX (`listar_categoria_corev2.php`), daí o «PRODUTOS A CARREGAR» numa extração sem JavaScript. |
| Indexação | Sem canonical; JSON-LD de produto com preço que **não era mostrado** na página e `OutOfStock` em todos os artigos; Google Analytics + Meta Pixel sem consentimento prévio. |

## 2. Estrutura de URLs nova

| Tipo | Padrão | Indexável | Notas |
|---|---|---|---|
| Página inicial | `/` | sim | |
| Loja | `/loja/` (+ `/loja/page/N/`) | sim | |
| Categoria | `/categoria/{slug}/` (+ `/page/N/`) | sim | URLs planas e estáveis (independentes da hierarquia). Subcategorias com nomes repetidos levam o nome da secção: `/categoria/calcas-hotelaria/`. |
| Marca | `/marca/{slug}/` | sim | 6 marcas reais (Workteam, CLS, Roly, Valento, Sol's, Cofra). |
| Produto | `/produto/{slug}/` | sim | Slug = nome antigo sem o ID numérico (ex.: `…/BOTA-VOUGA-S3-COMPOSITO-PU-SRC-4590.html` → `/produto/bota-vouga-s3-composito-pu-src/`). |
| Cor de um produto | `/produto/{slug}/?attribute_pa_cor={cor}` | não (canonical para o produto) | **Decisão:** uma página por produto; cores como variações com parâmetro. Os 2913 derivados antigos (páginas próprias por cor) redirecionam para o produto com a cor pré-selecionada. Dados estruturados com `AggregateOffer` no produto (WooCommerce). |
| Páginas | `/personalizacao/`, `/setores/{setor}/`, `/sobre/`, `/contactos/`, `/pedido-de-orcamento/` | sim | |
| Filtros | `?marca=`, `?filter_tom=`, `?filter_tamanho=`, `?orderby=` | **não** (`noindex,follow` + canonical sem parâmetros) | Ligações `rel="nofollow"`. Não geram páginas indexáveis concorrentes. |
| Pesquisa interna | `/?s=…&post_type=product` | não | Resultado único redireciona para a ficha (pesquisa por referência). |
| Carrinho, checkout, conta | `/carrinho/`, `/finalizar-compra/`, `/minha-conta/` | não | Também fora do sitemap; sem cache partilhada. |

### Paginação
URL própria por página (`/page/2/`), **canonical para si própria** (não para a página 1), `rel="prev/next"`, ligações HTML rastreáveis.

## 3. Sistema único de SEO

Um só sistema: `cdf-core/inc/seo.php` (títulos, meta description, canonical, robots, Open Graph, JSON-LD da loja) + sitemaps nativos do WordPress. Desliga-se sozinho se for instalado Yoast/Rank Math/SEOPress, para não haver duplicação.

| Elemento | Implementação |
|---|---|
| Título | `{Página} | Casa das Fardas`; categorias com secção (`Calças — Hotelaria | Casa das Fardas`); início `Casa das Fardas | Fardas, vestuário de trabalho e EPI`. |
| Descrição | Produto: descrição curta real + referência + marca. Categoria: introdução real (do menu antigo) + n.º de artigos + subcategorias. Páginas: excerto editável. |
| H1 | Um por página (verificado em 17 páginas). |
| Dados estruturados | Produto e BreadcrumbList do WooCommerce (marca real, preço sem IVA com `valueAddedTaxIncluded=false`, sem avaliações, **sem oferta quando não há preço**); `ClothingStore` com morada, telefone, horário (início, contactos, sobre). Sem GTIN, avaliações ou certificações inventadas. |
| Open Graph | Título, descrição, URL canónico, imagem real (produto/categoria/hero). |
| Imagens | `alt` = nome do produto (+ cor/vista); WebP nos tamanhos intermédios; `srcset/sizes`; lazy-load abaixo da dobra; `fetchpriority=high` na imagem principal da ficha. |
| Sitemap | `wp-sitemap.xml`: páginas publicadas (sem carrinho/checkout/conta/páginas pendentes), produtos visíveis, categorias e marcas com artigos. Sem utilizadores nem anexos. |
| robots.txt | Gerado pelo WordPress + `Disallow` apenas para URLs de ação (`?add-to-cart=`). Não é usado para esconder páginas privadas (essas têm `noindex`). |
| Erros | 404 com código correto e página útil; 410 para artigos que a própria loja antiga já dava como inexistentes. |
| Staging | `noindex,nofollow` em meta e cabeçalho `X-Robots-Tag` enquanto `WP_ENVIRONMENT_TYPE ≠ production`; acesso opcional por chave (`CDF_STAGING_KEY`). |

## 4. Mapa de redirecionamentos

Ficheiros:
- `docs/inventario/content-inventory.csv` — uma linha por URL/conteúdo da origem (página, categoria, produto, derivado de cor, imagem, ligação externa) com estado e destino.
- `docs/inventario/redirect-map.csv` — URL antiga → URL nova, código HTTP, motivo e **resultado do teste HTTP real** contra a loja local (sem cadeias: o destino tem de responder 200).

Regras (resolvidas por identificador de origem, não por texto):

| URL antiga | Destino |
|---|---|
| `/info/{Nome}-{ID}.html` e `/m/info/…` | produto com `_cdf_source_key = p:{ID}` |
| `/info/{Cor}-{ID}.html` (derivado) | produto-pai + `?attribute_pa_cor=` (ou `attribute_pa_medida`) |
| `/info/…-{ID}-subcategoria-lpc`, `…-{ID}-categoria-lpc` | categoria `sub:{ID}` / `cat:{ID}` |
| `/info/listar_categoria.php?nivelm=…&id=` | idem |
| `/info/ver_produtos_core.php?id=`, `imprimir.php?id=` | produto |
| `/info/contactos.php` | `/contactos/` |
| `/`, `/index.php`, `/m/`, `/m/index.php` | `/` |
| `/info/listar.php?procurar=` | pesquisa da nova loja |
| `/info/listar.php`, `listar_marcas.php` | `/loja/` |
| `/clientes/…` | `/minha-conta/` |
| 12 produtos «Produto X não existe!» | **410** (já não existiam na loja antiga) |
| `/rss.php`, `produtos.xml`, `posts.xml`, `sitemap.txt` | 410 |

Nada é enviado em massa para a página inicial. URLs antigas pedidas sem destino ficam registadas em WooCommerce → URLs antigas.

## 5. Conteúdo e palavras-chave

- Temas a validar com pesquisa real (não há volumes estimados): fardas profissionais, vestuário de trabalho, fardas de hotelaria, vestuário para saúde, calçado de segurança, personalização de fardas, alta visibilidade, EPI.
- Páginas que os cobrem: categorias (com introdução real), setores (5), personalização, sobre, pedido de orçamento.
- Ligações internas: setores → categorias → produtos; ficha → categoria, personalização, orçamento; homepage → setores, categorias, destaques, personalização.
- **Não** foram criadas páginas por localidade nem textos para «encher»; as descrições de produto são as originais (convertidas para HTML com listas).

## 6. Pendente (requer acesso do cliente)

Search Console, Merchant Center, Google Business Profile, dados de campo (CrUX). Ver `LAUNCH-CHECKLIST.md` §4–5.
