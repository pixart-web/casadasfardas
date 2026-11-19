# Relatório de recolha, importação e lacunas

Data: 02/10/2026. Fonte: páginas **públicas** de www.casadasfardas.pt. Não houve exportação nem acesso administrativo à loja antiga; o cliente indicou que o conteúdo será revisto e substituído onde necessário.

## 1. Método

1. **Plataforma identificada por evidência**: PHP 5.3.29 + nginx, CMS/loja própria (Optimeios); não é WordPress.
2. **Recolha** (`audit/scripts/crawl.py`): um pedido de cada vez, ≥ 0,7 s entre páginas (0,35 s entre imagens), cache em disco (retoma sem repetir pedidos), `robots.txt` respeitado (`/back` bloqueado exceto `/back/fotos`).
   - Árvore de categorias a partir do menu (2 tipos, 21 categorias, 122 folhas).
   - Listagens AJAX de cada folha (`listar_categoria_corev2.php`), com paginação → 955 artigos, **igual ao total declarado** pela própria loja em cada listagem.
   - Fichas na versão mobile (uma página por artigo, com JSON-LD completo) → 970 (union de sitemap + listagens).
   - Derivados de cor/medida referidos nas fichas → 2913.
   - Imagens `vista_*` de produtos e derivados → 5356.
3. **Normalização** (`audit/scripts/build_catalog.py`): encoding Windows-1252 → UTF-8 (original guardado em `_cdf_description_raw` e na cache), descrições convertidas em HTML (listas, subtítulos), tamanhos interpretados apenas quando inequívocos, famílias de cor para filtros, deduplicação por ID de origem e SKU.
4. **Importação** (`wp cdf import`): APIs CRUD do WooCommerce, idempotente por `_cdf_source_key` + hash do registo, imagens deduplicadas por URL de origem.

## 2. Reconciliação

| Contagem | Origem | Destino | Notas |
|---|---|---|---|
| URLs de produto no sitemap | 962 (+4 páginas) | — | |
| Artigos declarados nas listagens | 955 | **955 publicados** | 1:1 |
| Fichas únicas (sitemap ∪ listagens) | 970 | 958 importados | 12 «Produto não existe!» (removidos na própria loja antiga) → 410 |
| Só no sitemap, mas com ficha válida | 3 | 3 em **rascunho** | não estavam em nenhuma categoria: decisão do cliente |
| Só nas listagens (não no sitemap) | 8 | 8 publicados | artigos mais recentes que o sitemap de 2022 |
| Derivados (cor/medida) | 2913 | 2913 variações | 2883 cores (`pa_cor`), 30 medidas (`pa_medida`) |
| Categorias | 2 tipos + 21 + 118 subcategorias = 141 | 141 | 8 vazias na origem (mantidas, ocultas na navegação) |
| Marcas | 6 | 6 | CLS, Cofra, Roly, Sol's, Valento, Workteam |
| Imagens referidas | 5356 | 5355 | 1 ficheiro inválido na origem (HTML devolvido como JPG) |
| Produtos com preço na origem | 948 | 948 (945 publicados + 3 rascunhos) | preços **sem IVA**, por validar |
| Produtos sem preço | 10 | 10 | ficam «sob orçamento» (nunca vendidos a 0 €) |
| Páginas institucionais | 2 (início, contactos) | reescritas | ver §5 |

Execução final: `wp cdf import` sobre a base já importada → **958 inalterados, 0 criados, 0 erros, 1,3 s** (prova de idempotência). Uma execução interrompida a meio foi retomada sem duplicar imagens (19 imagens reutilizadas pela URL de origem).

## 3. Decisões de modelação

| Tema | Decisão | Porquê |
|---|---|---|
| Cores | Variações reais (`pa_cor`) com referência e foto próprias (ex.: `CANCUN.1`) | Na origem cada cor é um artigo derivado com código e imagem. |
| Tamanhos | Escolha validada contra a lista publicada (`_cdf_sizes`), guardada na linha do carrinho/encomenda; atributo `pa_tamanho` só para filtrar | A origem publica tamanhos como texto e não tem combinações cor × tamanho: gerar todas as combinações inventaria stock. |
| Tamanhos interpretados | 597 produtos com lista clara (ex.: `S, M, L, XL`; `35 - 48` → 35…48) | |
| Tamanhos não interpretados | 50 produtos (texto livre, ex.: `… Marinho até 60`, `8, 9, 108, 9, 10`) → sem seleção, texto mostrado nas especificações | Não inventar tamanhos. |
| Normalização de tamanhos | `2XL` → `XXL`, `XXXL` → `3XL`; tamanhos de criança como «4 anos», «3/4 anos» | Filtros consistentes. |
| Cor para filtrar | 493 nomes de cor dos fabricantes → 11 famílias (`pa_tom`: Azul, Verde, Preto, …) por palavras-chave | 493 opções num filtro não são utilizáveis; o nome exato fica na variação. |
| Preço | `_regular_price` = preço do JSON-LD da origem (sem IVA); estado `source-unvalidated`; cores herdam o preço do pai (`inherited-unvalidated`) | A loja antiga não mostrava preços; o rodapé dizia «A estes valores deverá acrescer IVA». |
| Disponibilidade | `onbackorder` com o texto «Disponibilidade confirmada após a encomenda» | A origem marcava **todos** os artigos como `OutOfStock` sem gestão de stock: stock desconhecido ≠ disponível. |
| Referência repetida | `1107` em dois artigos: o segundo fica sem SKU, código em `_cdf_source_code` | SKU tem de ser único. |
| Slugs | Nome antigo sem o ID (`bota-vouga-s3-composito-pu-src`) | Continuidade e legibilidade. |

### 3.1 Estrutura de categorias nova (19/11/2026)

O cliente definiu uma árvore nova, que substitui a da loja antiga. Está em `cdf-core/inc/importer/class-taxonomy.php` (`CDF_Taxonomy::tree()`) e aplica-se com `wp cdf categories` (também corre em cada `wp cdf import`). Depois disso, `wp cdf pages --force` refaz os menus e as páginas.

- **Vestuário**: Alta visibilidade, Indústria (13 subcategorias cada, incluindo Especiais (ignífugos)), Horeca, Serviços e saúde, Desporto, Calçado.
- **Equipamentos de proteção**: Cabeça, Ocular e facial, Respiratória, Auditiva, Mãos, Corpo, Descartáveis, Anti-queda.
- **Outros artigos** (fora da lista do cliente, a decidir): sinalização (15), cintas de elevação e roquetes (11), primeiros socorros (3), lona (1). É também a categoria por omissão do WooCommerce.

Reclassificação (`CDF_Taxonomy::map()`): pela categoria de origem e, onde a origem misturava artigos, pelo nome — ex.: calçado de segurança dividido em botas/sapatos/galochas/socas; calçado branco → Calçado › Alimentar; casacos polares → Malhas e polares; conjuntos túnica + calça → Conjuntos; fatos de treino → Calças/Casacos; equipamentos de futebol → Camisolas; jaquetas de cozinha → Horeca › Camisas; bandanas e máscaras de tecido → Serviços e saúde › Acessórios; viseiras e lava-olhos → Ocular e facial › Acessórios; proteção de soldadura → Ocular e facial › Soldadura; luvas de malha de aço → Mãos › Alimentar; manguitos descartáveis → Mãos › Manguitos. O vestuário ignífugo fica em Indústria › Especiais e, quando é de alta visibilidade, também em Alta visibilidade › Especiais.

Subcategorias ainda sem artigos (não aparecem nos menus): Alta visibilidade › Sweats, Cabeça › Acessórios, Respiratória › Acessórios.

As chaves da loja antiga ficam registadas no termo novo equivalente, por isso as URLs antigas de categorias continuam a dar 301 direto (verificado: 4025/4025).

## 4. Lacunas (`docs/inventario/import-gaps.csv`, 133 linhas)

| N.º | Lacuna | Tratamento |
|---|---|---|
| 53 | Marca não indicada na origem | Importado sem marca. |
| 50 | Tamanhos em texto livre não interpretável | Sem seleção de tamanho; texto nas especificações; tamanho indicado no pedido. |
| 12 | Página antiga «Produto não existe!» ainda no sitemap | Não importados; URL antiga → 410. |
| 10 | Sem preço na origem | Só orçamento. |
| 3 | No sitemap mas fora das categorias | Rascunho; URLs antigas → categoria. |
| 1 | Imagem inválida na origem | Produto 446 sem foto (placeholder). |
| 1 | Sem fotografia | Placeholder neutro. |
| 1 | Ficha sem dados estruturados | Só campos visíveis (sem descrição/preço). |
| 1 | Derivados com dois tipos de atributo | Um atributo por produto (o primeiro). |
| 1 | Referência 1107 repetida | Ver §3. |

**Não disponível publicamente** (precisa de exportação ou do cliente): stock real, preços validados e regras de desconto, custos de envio, tabelas de medidas, fichas técnicas/certificados em PDF, condições de personalização (mínimos, preços, prazos), clientes e encomendas antigas, avaliações.

## 5. Conteúdo institucional

| Origem | Destino |
|---|---|
| Texto de apresentação da homepage («Somos fornecedores de todo o tipo de vestuário de trabalho…», «Temos produção de bordados e estamparia», «Fornecemos para todo o país») | Base de toda a comunicação: barra informativa, hero, sobre, personalização. |
| Contactos (Amazonas Indústria LDA., morada, telemóvel com nota de custo, 2 emails, horário, departamentos) | WooCommerce → Casa das Fardas; página de contactos; rodapé; dados estruturados. |
| Formulário de contacto com captcha e texto RGPD | Novo formulário com anti-spam sem captcha; **texto RGPD antigo não copiado** (dizia que os dados não eram guardados no site — na nova loja são guardados com prazo). |
| Banners 985×280 com texto embutido | Não migrados (substituídos por secções editáveis). |
| «Brindes publicitários» (catálogo externo impactogift) | Ligação externa no menu e rodapé. |
| Google Analytics, Meta Pixel, diretório «Funcionalidade RIO», crédito do fornecedor antigo | **Excluídos** (scripts sem consentimento, publicidade de terceiros). |

## 6. Repetir a importação

```bash
.venv/bin/python audit/scripts/crawl.py all        # usa a cache; só pede o que falta
.venv/bin/python audit/scripts/build_catalog.py
bin/wp cdf import --dry-run
bin/wp cdf import --report=/exports/import-report.json    # ou por lotes: --offset=0 --limit=200
```

Com uma exportação oficial (CSV/XML/BD), basta gerar o mesmo `catalog.json` a partir dela: o importador atualiza os produtos existentes pela chave de origem (`p:{id}`), sem duplicar.

## 7. Relatórios de execução

`docs/inventario/relatorios-importacao/`: lotes paralelos `b` (produtos 450–699), `c` (700–957) e `a2` (400–449, retoma), e a passagem final completa (`import-report.json`, tudo inalterado). O primeiro lote (0–~400) foi interrompido de propósito para evitar sobreposição com os lotes paralelos e não deixou relatório; a sua integridade foi confirmada pela passagem final (958 inalterados, 0 erros) e pela verificação de anexos (sem duplicados; 1 anexo órfão da interrupção removido).
