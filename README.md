# Casa das Fardas — nova loja WordPress + WooCommerce

Redesign integral da loja [casadasfardas.pt](https://www.casadasfardas.pt/) (Amazonas Indústria, Lda., Alcantarilha): tema próprio, plugin de negócio, catálogo real migrado da loja antiga, pedidos de orçamento para empresas, checkout WooCommerce e SEO de migração.

> **Estado: staging local, não publicado.** O site atual, o DNS e os pagamentos reais não foram alterados.
> Os preços importados vêm da loja antiga e **ainda não foram validados** pelo cliente; o pagamento é um gateway de teste.
> Ver [`docs/RESUMO.md`](docs/RESUMO.md) (implementado / testado / pendente) e [`docs/LAUNCH-CHECKLIST.md`](docs/LAUNCH-CHECKLIST.md).

## Arranque

Requisitos: Docker (`docker compose` ou `docker-compose`), porta 8092 livre, Internet na primeira instalação. Python 3.9+ apenas para voltar a recolher o catálogo.

```bash
bin/setup.sh
```

| | |
|---|---|
| Loja | <http://casadasfardas.localhost:8092/> |
| Backoffice | <http://casadasfardas.localhost:8092/wp-admin/> — credenciais em `.credentials.local` (geradas na instalação, não versionadas) |
| Emails (Mailpit) | <http://localhost:8028> — **todo** o email local é capturado aqui |

`bin/setup.sh` é idempotente: instala WordPress (pt_PT), WooCommerce, o tema e o plugin, configura a loja (`wp cdf setup`), importa o catálogo (`wp cdf import`) e cria páginas e menus (`wp cdf pages`). `bin/setup.sh --reset` apaga tudo e reinstala de raiz.

### Demo completa a partir do repositório

O repositório inclui as **5356 fotografias originais** do catálogo (`audit/media/products/`, 354 MB) e o catálogo normalizado (`catalog/catalog.json`). Num servidor ou computador novo, `bin/setup.sh` importa os 958 produtos com fotografias e cria a homepage, setores e restantes páginas com as imagens do hero e dos cartões. A importação das imagens demora cerca de 30–45 min (gera as versões WebP); pode ser acelerada por lotes paralelos:

```bash
bin/wp cdf categories                               # cria a estrutura de categorias antes dos lotes paralelos
bin/wp cdf import --offset=0 --limit=450 &  bin/wp cdf import --offset=450 &  wait
bin/wp cdf import && bin/wp cdf pages --force     # passagem final (idempotente) e páginas com imagens
```

Num servidor sem Docker, os mesmos comandos correm com o WP-CLI do alojamento, definindo `CDF_IMPORT_DIR` para a pasta que contém `catalog/` e `media/` (ver `docker-compose.yml`).

## Versões efetivamente instaladas

| Componente | Versão |
|---|---|
| WordPress | 7.1.2 (imagem `wordpress:7.1-php8.3-apache`) |
| PHP | 8.3 |
| WooCommerce | 11.1.2 (HPOS ativo, checkout clássico) |
| MariaDB | 11.4 |
| Plugins de terceiros | **só o WooCommerce** (Brands incluído no WooCommerce) |

## Estrutura

| Caminho | Conteúdo |
|---|---|
| `wp-content/themes/casadasfardas/` | Tema próprio (apresentação): `theme.json`, cabeçalho com mega menu, templates WooCommerce mínimos (`woocommerce/`), CSS/JS por componente, fontes locais OFL, logótipo original. |
| `wp-content/plugins/cdf-core/` | Lógica de negócio: dados da empresa, preços/IVA, tamanhos, NIF, pesquisa por referência, filtros, orçamento com anexos privados, blocos dinâmicos, SEO, redirecionamentos, privacidade, gateway de teste, importador e WP-CLI. |
| `audit/` | Recolha pública da loja antiga: `scripts/crawl.py` (cache, retoma, robots.txt respeitado), `scripts/build_catalog.py` (normalização), `data/` (JSON brutos), `media/` (imagens originais), `raw/` (amostras HTML). |
| `catalog/catalog.json` | Catálogo normalizado (entrada do importador). |
| `docs/` | Documentação, inventários, mapa de redirecionamentos, QA, capturas. |
| `tools/` | Testes Playwright + axe, verificação do editor de blocos, capturas, Lighthouse. |
| `bin/` | `setup.sh`, `wp` (atalho WP-CLI), `test-fixtures.sh`. |

## Comandos

```bash
bin/wp cdf status                                   # resumo da loja
bin/wp cdf import --dry-run                         # valida o catálogo sem escrever
bin/wp cdf categories && bin/wp cdf pages --force   # reaplica a estrutura de categorias (class-taxonomy.php) e refaz menus
bin/wp cdf import --report=/exports/import-report.json   # importa/atualiza sem duplicar (retoma: --offset=N --limit=M)
bin/wp cdf import --skip-images                     # só estrutura (rápido)
bin/wp cdf pages                                    # páginas e menus (não sobrepõe edições; --force repõe)
bin/wp cdf redirects /docs/inventario/content-inventory.csv /docs/inventario/redirect-map.csv --base-url=http://wordpress   # gera e testa o mapa
bin/test-fixtures.sh                                # clientes e encomenda de teste
```

Recolher de novo o catálogo público (só leitura, 1 pedido de cada vez, com cache):

```bash
python3 -m venv .venv && .venv/bin/pip install requests pillow
.venv/bin/python audit/scripts/crawl.py all
.venv/bin/python audit/scripts/build_catalog.py
```

Testes:

```bash
cd tools && npm install
npx playwright test                 # funcionais, SEO, acessibilidade (axe), responsivo
node editor-check.mjs               # todas as páginas abrem no editor sem blocos inválidos
node screenshots.mjs                # capturas em docs/capturas
./lighthouse.sh                     # laboratório (mobile/desktop)
```

## Operação (backoffice)

- **WooCommerce → Casa das Fardas**: morada, telefone, emails, horário, barra informativa, modo do catálogo (Loja / Só orçamento), email que recebe orçamentos, prazo de conservação dos pedidos.
- **WooCommerce → Orçamentos**: pedidos de orçamento e contacto, com anexo (download só para gestores) e estado Novo/Respondido.
- **WooCommerce → URLs antigas**: endereços da loja antiga pedidos sem destino.
- **Produtos**: coluna «Preço» com o estado de validação; ação em massa «Marcar preço como validado»; caixa «Origem (loja antiga)» em cada produto.
- **Páginas**: homepage, setores, personalização, sobre, contactos e orçamento são blocos editáveis (inclui blocos «Produtos da loja», «Categorias da loja», «Formulário», «Dados de contacto»).
- **Aspeto → Menus**: menu principal (mega menu) e menus do rodapé.

## Documentação

[Resumo](docs/RESUMO.md) · [Sistema de design](docs/DESIGN-SYSTEM.md) · [SEO e migração](docs/SEO-MIGRATION.md) · [QA](docs/QA-REPORT.md) · [Checklist de lançamento](docs/LAUNCH-CHECKLIST.md) · [Arquitetura e plugins](docs/ARQUITETURA.md) · [Relatório de importação e lacunas](docs/IMPORTACAO.md) · [Inventário](docs/inventario/content-inventory.csv) · [Redirecionamentos](docs/inventario/redirect-map.csv) · [Capturas](docs/capturas/)
