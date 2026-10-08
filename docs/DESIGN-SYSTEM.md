# Casa das Fardas — sistema de design

Conceito proposto: **«A imagem profissional da sua equipa começa aqui.»** É uma frase nova para a nova loja; não vem do site antigo.

Linguagem: editorial e contemporânea na apresentação (títulos grandes, composições assimétricas, fotografia em destaque) e técnica, regular e legível nas áreas de compra (grelha uniforme, referências e especificações sempre visíveis).

Implementação: tokens em `wp-content/themes/casadasfardas/theme.json` (editor) e `assets/css/base.css` (`:root`, front-end). O CSS do front-end não depende dos estilos globais do WordPress, que são desligados nas páginas de loja para reduzir o peso.

## 1. Marca

| Elemento | Decisão |
|---|---|
| Logótipo | Original da loja antiga (`back/fotos/casa1476/design/logo1.png`, PNG 155×100). Usado sem alterações em `assets/img/logo-casadasfardas.png`. **Pendente:** pedir ao cliente o logótipo em vetor (SVG/AI/EPS) para nitidez em ecrãs de alta densidade e para uma versão negativa. Não foi redesenhado. |
| Favicon | Original (`favicon.ico` 16×16). Pendente: versão 512 px a partir do vetor. |
| Cor principal | **Azul #003B71**, extraído dos pixels opacos do logótipo (texto «CASA» e «FARDAS.PT»). |
| Cor de ação | **Âmbar #F3B235**, extraído do logótipo (arqueira e «DAS»). |

O azul #0095CC do rodapé antigo não pertence ao logótipo e não foi usado.

## 2. Cor

| Token | Valor | Uso |
|---|---|---|
| `--cdf-navy` | #003B71 | Cor da marca: ligações, botão secundário, fundos de destaque |
| `--cdf-navy-deep` | #021B36 | Títulos, barra superior, botões de compra em hover |
| `--cdf-navy-700` | #0B4A86 | Variação |
| `--cdf-navy-100` / `-50` | #E5EDF5 / #F2F6FA | Anel de foco de campos, fundos de filtros ativos |
| `--cdf-amber` | #F3B235 | **Ação principal** (fundo de botões, com texto #021B36), sublinhados e marcadores |
| `--cdf-amber-600` | #E5A01C | Hover da ação principal |
| `--cdf-amber-100` | #FDF3DC | Faixa de personalização na ficha |
| `--cdf-ink` | #16202B | Texto principal (grafite) |
| `--cdf-ink-2` | #34404D | Texto corrido |
| `--cdf-muted` | #5A6573 | Texto secundário |
| `--cdf-line` / `-2` | #DDE2E8 / #ECEFF3 | Linhas e contornos |
| `--cdf-surface` | #F4F5F7 | Fundos suaves, rodapé |
| Estados | #1A7F37 sucesso · #B42318 erro · #EEF4FB informação | Avisos e validação |

Contraste (WCAG 2.2, relação de luminância, calculado):

| Combinação | Relação | Resultado |
|---|---|---|
| #021B36 sobre #F3B235 (botão principal) | 9,3:1 | AA/AAA |
| #FFFFFF sobre #003B71 (botão de compra, faixas) | 11,3:1 | AA/AAA |
| #16202B sobre #FFFFFF (texto) | 16,5:1 | AAA |
| #5A6573 sobre #FFFFFF (texto secundário) | 5,9:1 | AA |
| #5A6573 sobre #F4F5F7 | 5,4:1 | AA |
| #003B71 sobre #FFFFFF (ligações) | 11,3:1 | AAA |

O âmbar nunca é usado como cor de texto sobre branco (contraste insuficiente); aparece sempre como fundo, linha ou marcador.

## 3. Tipografia

Duas famílias, alojadas localmente (`assets/fonts`, WOFF2 variável, subconjunto latino, licença SIL OFL 1.1 incluída):

| Família | Uso | Pesos |
|---|---|---|
| **Bricolage Grotesque** | Títulos (H1–H4), números de passos, nomes de categorias | 700 (variável 200–800) |
| **Inter** | Texto, interface, formulários, preços | 400, 500, 600, 650, 700 (variável) |

Escala (fluida com `clamp`): 13 · 14 · 16 · 17–19 · 22–28 · 28–44 · 38–76 px. Títulos com entrelinha 1,02–1,2 e espaçamento −0,02 a −0,035 em; texto 1,6. Pré-carregamento das duas fontes e `font-display: swap`.

## 4. Espaço, grelha e forma

- Largura máxima do conteúdo: **1360 px** (`--cdf-wide`); texto corrido 760 px.
- Margem lateral fluida: 16 px (telemóvel) a 40 px.
- Escala de espaço: 4 · 8 · 12 · 16 · 24 · 32 · 48 · 48–80 · 64–120 px.
- Raio: 6 px (botões, campos), 12 px (cartões, imagens). Pílulas só em etiquetas pequenas (subcategorias, filtros ativos).
- Sombras: uma única sombra suave para painéis flutuantes (sugestões, mega menu, cartões em hover).
- Breakpoints: 360/390 (telemóvel), 768 (tablet), 1024 (filtros laterais), 1100 (navegação desktop), 1200 (4 colunas).

## 5. Componentes

| Componente | Ficheiro | Notas |
|---|---|---|
| Barra informativa | `header.php` | Só vantagens confirmadas (editáveis em WooCommerce → Casa das Fardas). Desaparece ao fazer scroll (cabeçalho fixo sem a barra). |
| Cabeçalho | `header.php`, `base.css` | Logótipo, pesquisa em destaque, Orçamento (com contador), Conta, Carrinho (só no modo loja). |
| Mega menu | `inc/navigation.php`, `header.js` | A partir de Aspeto → Menus: nível 1 secções, nível 2 grupos, nível 3 ligações (máx. 7 por grupo + «Mais N»). Abre por clique/teclado e por hover com atraso; Esc fecha. Itens de páginas sem netos aparecem como cartões com imagem. |
| Menu móvel | `<dialog>` + `<details>` | Acordeão nativo, foco gerido pelo diálogo. |
| Pesquisa | `cdf_render_search()` | Combobox ARIA com sugestões de categorias e artigos (nome e referência). Funciona sem JavaScript. |
| Cartão de produto | `woocommerce/content-product.php` | Imagem 1:1 sem corte (`object-fit: contain`), marca, nome (2 linhas), referência, n.º de cores, preço s/ IVA + c/ IVA ou «Preço sob orçamento». Cartão inteiro clicável. |
| Filtros | `archive-product.php`, `shop.js` | Facetas reais (marca, cor por família, tamanho) com contagens; ligações GET `rel="nofollow"`; painel lateral no telemóvel. |
| Ficha | `content-single-product.php`, `product.js` | Galeria com zoom e ampliação, amostras de cor sobre o select nativo, tamanho validado, compra + «Adicionar ao orçamento», especificações só da origem, barra de compra fixa no telemóvel. |
| Formulário de orçamento | `cdf-core/inc/quote.php`, `forms.css` | Resumo de erros com ligações, `aria-invalid`, confirmação só após sucesso. |
| Botões | `base.css` | Principal (âmbar), secundário (contorno azul), escuro (azul, para compra), claro (sobre azul), ligação com seta. |
| Estilos de bloco | `inc/block-styles.php` | Faixa azul/cinza/âmbar, cartão, fotografia de produto, lista com visto, passos numerados, antetítulo, texto de abertura. |

## 6. Fotografia

- Usadas **apenas fotografias reais do catálogo** (fotos dos fabricantes publicadas na loja antiga, 587×587 px). Nenhuma imagem foi gerada nem alterada.
- Os produtos são mostrados inteiros (sem corte) nas grelhas; fotos de ambiente (pessoas a usar as fardas) são usadas em recorte nos blocos editoriais (hero, setores).
- Os banners antigos (985×280 com texto embutido) não foram migrados.
- Limitação: 587 px é pouco para um hero de largura total — por isso o hero usa um mosaico de três imagens. **Recomendado:** sessão fotográfica da loja e de clientes reais, ou fotografias de alta resolução dos fabricantes.

## 7. Movimento e acessibilidade

- Transições de 150–450 ms; sem scroll hijacking, sem preloader, sem vídeo.
- `prefers-reduced-motion` desliga animações e scroll suave.
- Foco visível (anel azul de 2 px com folga branca) em todos os elementos interativos; ligação «Saltar para o conteúdo».
- Alvos de toque ≥ 40–48 px.
