# Relatório de QA — Casa das Fardas

Ambiente: staging local (`http://casadasfardas.localhost:8092`), WordPress 7.1.2, WooCommerce 11.1.2, PHP 8.3.35, MariaDB 11.4.13, Chromium (Playwright 1.55). Data: 02/10/2026.

**Resultado: 51/51 testes automáticos aprovados**, 21/21 páginas válidas no editor de blocos, 4025/4025 URLs antigas com o destino esperado.
Resultados brutos: `docs/qa/resultados-testes.json`. Lighthouse: `docs/qa/lighthouse/`. Capturas: `docs/capturas/` (desktop 1440 px, tablet 768 px, telemóvel 390 px).

## 1. Critérios de aceitação (secção 13 da especificação)

| # | Critério | Como foi verificado | Resultado |
|---|---|---|---|
| 1 | Menus e pesquisa por referência e nome; resultados e vazio | `navegacao.spec` (mega menu clique/Esc, acordeão móvel), `pesquisa.spec` (nome, ref. `0114080`, ref. de cor `CANCUN.1`, estado vazio, combobox com teclado, sugestão por referência via REST) | ✅ |
| 2 | Filtros combinados, ordenação, paginação, retroceder | `filtros.spec` (marca + tamanho, remoção por chip, `noindex` nos filtros, ordenação por preço crescente verificada nos valores, por nome, `/page/2/` com canonical próprio, botão Retroceder repõe filtros, painel móvel com Esc) | ✅ |
| 3 | Simples/variáveis, combinações inválidas, sem stock, sem preço | `produto.spec` (tamanho obrigatório validado no servidor, botão desativado até escolher cor, referência muda com a cor, cor + tamanho no carrinho, artigo sem preço nunca vendido e enviado para orçamento, sem oferta nos dados estruturados) | ✅ |
| 4 | Carrinho, quantidades, IVA, portes e totais | `checkout.spec`: 2 × 23,89 € → subtotal 47,78 €, IVA 23% = 10,99 €, atualização para 3 unidades; métodos de envio presentes. Exemplo manual: 7,15 € + IVA 1,64 € = **8,79 €** (captura `carrinho-desktop.png`). | ✅ (portes reais pendentes) |
| 5 | Checkout sandbox aprovado/falhado, encomenda e email | `checkout.spec`: NIF inválido recusado → pagamento recusado (aviso) → aprovado → página de encomenda recebida com IVA e NIF → email ao cliente capturado no Mailpit | ✅ com gateway de teste (gateway real pendente) |
| 6 | Login, recuperação, isolamento entre clientes | `checkout.spec`: login do cliente A; acesso à encomenda do cliente B recusado; recuperação de palavra-passe envia email | ✅ |
| 7 | Orçamento, ficheiro válido/inválido, erro, confirmação real | `orcamento.spec`: limite de 6 envios/hora por ligação comprovado, erros acessíveis (resumo + `aria-invalid`), ficheiro PHP disfarçado de PNG recusado, pedido com lista + logótipo PNG → referência `ORC-2026-NNNN`, email à equipa **com anexo** e cópia ao cliente, lista esvaziada, confirmação não repetida ao recarregar, POST sem nonce/token → 422 sem gravar | ✅ |
| 8 | Overflow, imagens partidas, botões sem ação, placeholder público | `responsivo.spec`: sem scroll horizontal a 360/390/768/1280/1440 px em 8 páginas; 0 imagens partidas; 0 ligações sem destino; sem «A VALIDAR»/«TODO»/lorem | ✅ |
| 9 | HTTP, metadados, canonical, schema, sitemap, redirects | `seo.spec` + verificação em modo produção (§3) + `wp cdf redirects` (§4) | ✅ |
| 10 | Contagens origem/destino e itens em falta | `docs/IMPORTACAO.md` §2 e `docs/inventario/import-gaps.csv` | ✅ (lacunas declaradas) |

## 2. Acessibilidade (objetivo WCAG 2.2 AA nos percursos implementados)

- axe-core (`wcag2a/aa`, `wcag21a/aa`, `wcag22aa`) em 11 páginas: início, categoria, produto variável, produto sob orçamento, orçamento, contactos, personalização, setor, pesquisa, carrinho, conta → **0 violações sérias ou críticas**.
- Lighthouse acessibilidade: **100** em todas as páginas medidas (mobile e desktop).
- Teclado: «Saltar para o conteúdo» move o foco para `<main>`; mega menu, gaveta, filtros, sugestões e amostras de cor operáveis por teclado (setas nas amostras e nas sugestões).
- Corrigido durante o QA: contraste do texto «Sem fotografia» e das opções de filtro vazias; ícones do cabeçalho sem nome acessível em mobile; botão «Adicionar» desativado com contraste 3,7:1 → agora 8,1:1; «Limpar opções» do WooCommerce era `<a href="#">` em inglês → botão em português.
- **Não feito**: teste com leitor de ecrã real (VoiceOver/NVDA) e com utilizadores. Verificação automática não equivale a certificação.

## 3. SEO e indexação (verificado com `WP_ENVIRONMENT_TYPE=production` temporariamente)

| Verificação | Resultado |
|---|---|
| Início, categoria, produto, orçamento, página 2 de categoria | indexáveis (`max-image-preview:large`), sem `X-Robots-Tag`, canonical para si próprias |
| Filtros (`?marca=`), carrinho, pesquisa | `noindex, follow`; filtro com canonical para a categoria sem parâmetros |
| Produto com `?attribute_pa_cor=` | canonical para o produto |
| `robots.txt` | sem `Disallow: /`; regras de ação do WooCommerce; `Sitemap:` |
| Sitemap | 12 páginas públicas (sem carrinho/checkout/conta/pendentes), 955 produtos, 114 categorias com artigos, 6 marcas; sem utilizadores |
| Dados estruturados | `Product` + `BreadcrumbList` (WooCommerce) com marca real e sem avaliações; `ClothingStore` com morada/telefone/horário; artigos sem preço sem `offers` |
| Gateways em produção | nenhum (o de teste desaparece) — **checkout só funciona depois de configurar o gateway real** |
| Destinatário dos orçamentos em produção | `info@amazonas.pt` (staging: endereço de teste) |
| Staging | `noindex,nofollow` (meta + cabeçalho) confirmado depois de voltar ao modo staging |

## 4. Redirecionamentos

`wp cdf redirects` leu as 4036 linhas do inventário e pediu cada URL antiga à loja local, sem seguir redirecionamentos:
- **4013 → 301** direto para página que responde **200** (sem cadeias);
- **12 → 410** (artigos que a loja antiga já dava como inexistentes);
- 1 página inicial (mesmo caminho);
- **0 falhas, 0 sem destino** (2 cores de produtos em rascunho seguem o destino do produto-pai).
Mapa completo: `docs/inventario/redirect-map.csv`.

## 5. Desempenho (laboratório — Lighthouse 12, simulação)

| Página | Mobile (desempenho · LCP · CLS · TBT) | Desktop |
|---|---|---|
| Início | 96 · 2,7 s · 0 · 0 ms | 100 · 0,6 s · 0 |
| Categoria | 97 · 2,5 s · 0 · 0 ms | 100 · 0,5 s · 0 |
| Produto | 96 · 2,6 s · 0 · 0 ms | 100 · 0,5 s · 0,003 |
| Orçamento | 98 · 2,3 s · 0 · 0 ms | 100 · 0,5 s · 0 |

- O LCP mobile em laboratório fica **no limite de 2,5 s** com a simulação de 4G lento do Lighthouse; desktop ~0,5 s. Respostas do servidor local: 60–110 ms. HTML da loja comprimido: 17 KB.
- Os SEO de 69 no Lighthouse devem-se **apenas** ao `noindex` intencional do staging.
- **Dados de campo (CrUX/Core Web Vitals reais) não existem**: exigem tráfego real após o lançamento. Os valores dependem também do alojamento e da cache de página.
- Otimizações feitas: WebP nos tamanhos, `srcset/sizes`, `fetchpriority=high` na imagem principal, primeiras imagens da grelha sem lazy-load, CSS dos blocos só quando usados, sem jQuery fora das páginas que o exigem, fontes locais pré-carregadas.

## 6. Editor de blocos

`node tools/editor-check.mjs`: as 21 páginas (15 publicadas + 6 pendentes) abrem no editor **sem blocos inválidos nem em falta** (até 93 blocos na página inicial).

## 7. Inspeção visual

Capturas reais em `docs/capturas/` para início, loja, categoria, categoria filtrada, produto, produto sob orçamento, produto adicionado, pesquisa, personalização, setor, orçamento, contactos, 404, carrinho, checkout e menus — em 1440, 768 e 390 px. Sem erros de JavaScript durante as capturas. Os testes de overflow cobrem também 360 e 1280 px.

## 8. Limitações conhecidas

- Preços, disponibilidade, portes, gateways e páginas legais por validar (ver `LAUNCH-CHECKLIST.md`).
- Fotografias de 587 px (origem): suficientes para grelhas e fichas; o zoom amplia pouco.
- O mosaico do hero e os cartões de setor usam recortes das fotografias de catálogo (sem alteração do produto).
- Testes executados em Chromium; Safari e Firefox não foram testados automaticamente.
