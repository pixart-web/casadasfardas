# Resumo — nova loja Casa das Fardas

**Estado:** loja WordPress + WooCommerce funcional em **staging local**, com o catálogo real da loja antiga, pronta para demonstração ao cliente. **Não publicada.** O site atual, o DNS e os pagamentos reais não foram alterados.

## Implementado

- **Tema próprio `casadasfardas`**: direção visual nova com as cores extraídas do logótipo (azul #003B71, âmbar #F3B235), fontes locais, hero editorial com fotografias reais, mega menu, gaveta móvel, listagens com filtros, ficha de produto com galeria/zoom/amostras de cor e barra de compra móvel, carrinho/checkout/conta estilizados, 404 útil e estados vazios.
- **Plugin `cdf-core`**: modo «Loja» ou «Só orçamento», preços sem IVA + valor com IVA, artigos sem preço nunca vendidos a 0 €, disponibilidade honesta, tamanho validado, NIF com dígito de controlo, pesquisa por nome e referência (incluindo a referência de cada cor) com sugestões, filtros por marca/cor/tamanho, **pedido de orçamento B2B** com lista de artigos, personalização e logótipo (anexo privado e seguro), registo no backoffice com retenção, SEO técnico, redirecionamentos da loja antiga, ferramentas de privacidade e gateway de teste.
- **Catálogo migrado**: 955 artigos publicados (igual ao total declarado nas listagens da loja antiga) + 3 em rascunho para decisão, 2913 variações de cor/medida com referência e foto, nova estrutura de 106 categorias definida pelo cliente (Vestuário e Equipamentos de proteção), 6 marcas, 5355 fotografias originais. Importador repetível com dry-run, retoma por lotes e relatório.
- **Páginas em blocos editáveis**: início, 5 setores, personalização, sobre, contactos, pedido de orçamento. Páginas legais e de serviço em rascunho «pendente de validação».
- **Ambiente reproduzível**: Docker (`bin/setup.sh`), `.env.example` sem segredos, Mailpit a capturar todo o email.

## Testado

- 51 testes automáticos (Playwright + axe): navegação, pesquisa, filtros, ordenação, paginação, produtos simples/variáveis/sem preço, carrinho com IVA, checkout aprovado/recusado, emails, login, recuperação de acesso, isolamento entre clientes, orçamento com ficheiro válido/inválido, SEO, redirecionamentos, acessibilidade, overflow em 5 larguras — **todos aprovados**.
- 4025 URLs antigas pedidas uma a uma: 4013 → 301 direto para página 200, 12 → 410, **0 falhas**.
- 21 páginas válidas no editor de blocos.
- Comportamento de produção verificado (indexação, sitemap, gateways, destinatário dos orçamentos).
- Lighthouse (laboratório): desempenho 96–98 mobile / 100 desktop, acessibilidade 100, boas práticas 100, CLS 0; LCP mobile 2,3–2,7 s em 4G simulado.

Detalhe: [QA-REPORT.md](QA-REPORT.md).

## Pendente (depende do cliente)

| Tema | O que falta |
|---|---|
| Preços | Validar os 948 preços importados (sem IVA) e os preços herdados pelas cores. |
| Stock | A origem não tem stock real; confirmar a política «disponibilidade confirmada após a encomenda» ou fornecer stock. |
| Pagamentos | Escolher e contratar o(s) gateway(s); configurar credenciais em sandbox e produção. |
| Envios | Transportadora, portes, zonas e prazos. |
| IVA | Regiões autónomas e vendas intracomunitárias B2B. |
| Páginas legais | Completar e validar termos, privacidade, cookies, entregas, trocas e pagamentos; RAL e Livro de Reclamações. |
| Catálogo | Decidir onde ficam os 30 «Outros artigos» (sinalização, cintas de elevação, primeiros socorros, lona) e os 3 rascunhos; completar marcas em falta (53), tamanhos não interpretáveis (50), artigos sem preço (10), tabelas de medidas e fichas técnicas. |
| Marca | Logótipo em vetor; fotografia própria da loja e de bordados. |
| Personalização | Mínimos, preços e prazos de bordado/estampagem (hoje só «pedir orçamento»). |

## Acessos necessários para lançar

1. Alojamento (PHP 8.1+, MariaDB/MySQL, HTTPS, SSH/WP-CLI, cron) e cópias de segurança.
2. SMTP transacional do domínio (SPF/DKIM/DMARC).
3. Conta do fornecedor de pagamentos (sandbox + produção).
4. DNS de casadasfardas.pt (só no dia do lançamento).
5. Google Search Console (e Merchant Center, se aplicável).
6. Idealmente, exportação oficial do catálogo da loja antiga (stock, preços, marcas) — o importador já aceita.

Passos e verificações: [LAUNCH-CHECKLIST.md](LAUNCH-CHECKLIST.md).
