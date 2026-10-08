<?php
/**
 * Páginas, menus e conteúdos editoriais iniciais (wp cdf pages).
 *
 * O conteúdo é composto por blocos nativos (editáveis no Gutenberg) e blocos dinâmicos do cdf-core.
 * Usa apenas factos confirmados na loja antiga. Páginas legais sem informação validada ficam
 * «Pendentes de revisão» (não publicadas) e marcadas no título.
 * Execuções seguintes não sobrepõem páginas editadas, exceto com --force.
 */

defined( 'ABSPATH' ) || exit;

class CDF_Pages {

	private bool $force;
	private array $ids = array();

	public function __construct( bool $force = false ) {
		$this->force = $force;
	}

	// ------------------------------------------------------------ geradores de blocos

	private function p( string $html, string $class = '' ): string {
		$attrs = $class ? ' ' . wp_json_encode( array( 'className' => $class ) ) : '';
		$cls   = $class ? ' class="' . esc_attr( $class ) . '"' : '';
		return "<!-- wp:paragraph$attrs -->\n<p$cls>$html</p>\n<!-- /wp:paragraph -->\n";
	}

	private function h( string $html, int $level = 2, string $class = '' ): string {
		$a = array();
		if ( 2 !== $level ) {
			$a['level'] = $level;
		}
		if ( $class ) {
			$a['className'] = $class;
		}
		$attrs = $a ? ' ' . wp_json_encode( $a ) : '';
		$cls   = 'wp-block-heading' . ( $class ? ' ' . $class : '' );
		return "<!-- wp:heading$attrs -->\n<h$level class=\"" . esc_attr( $cls ) . "\">$html</h$level>\n<!-- /wp:heading -->\n";
	}

	private function group( string $inner, string $class = '', string $align = '' ): string {
		$a = array();
		if ( $align ) {
			$a['align'] = $align;
		}
		if ( $class ) {
			$a['className'] = $class;
		}
		$attrs = $a ? ' ' . wp_json_encode( $a ) : '';
		$cls   = 'wp-block-group' . ( $align ? ' align' . $align : '' ) . ( $class ? ' ' . $class : '' );
		return "<!-- wp:group$attrs -->\n<div class=\"" . esc_attr( $cls ) . "\">$inner</div>\n<!-- /wp:group -->\n";
	}

	private function buttons( array $buttons ): string {
		$out = "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">";
		foreach ( $buttons as $b ) {
			$style = $b[2] ?? '';
			$attrs = $style ? ' ' . wp_json_encode( array( 'className' => $style ) ) : '';
			$cls   = 'wp-block-button' . ( $style ? ' ' . $style : '' );
			$out  .= "<!-- wp:button$attrs -->\n<div class=\"" . esc_attr( $cls ) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $b[1] ) . '">' . esc_html( $b[0] ) . "</a></div>\n<!-- /wp:button -->";
		}
		return $out . "</div>\n<!-- /wp:buttons -->\n";
	}

	private function lst( array $items, string $class = '' ): string {
		$attrs = $class ? ' ' . wp_json_encode( array( 'className' => $class ) ) : '';
		$out   = "<!-- wp:list$attrs -->\n<ul class=\"" . esc_attr( 'wp-block-list' . ( $class ? ' ' . $class : '' ) ) . '">';
		foreach ( $items as $item ) {
			$out .= "<!-- wp:list-item -->\n<li>$item</li>\n<!-- /wp:list-item -->";
		}
		return $out . "</ul>\n<!-- /wp:list -->\n";
	}

	/**
	 * Imagem importada (pela fotografia original da loja antiga). Sem imagem → sem bloco.
	 */
	private function img( string $source_file, string $alt, string $caption = '', string $href = '', string $class = '', bool $editorial = true ): string {
		$id = $editorial ? $this->editorial( $source_file ) : $this->attachment( $source_file );
		if ( ! $id ) {
			return '';
		}
		$src = wp_get_attachment_image_url( $id, 'full' );
		$a   = array( 'id' => $id, 'sizeSlug' => 'full', 'linkDestination' => $href ? 'custom' : 'none' );
		if ( $class ) {
			$a['className'] = $class;
		}
		$img  = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $id . '"/>';
		$body = $href ? '<a href="' . esc_url( $href ) . '">' . $img . '</a>' : $img;
		if ( $caption ) {
			$body .= '<figcaption class="wp-element-caption">' . $caption . '</figcaption>';
		}
		$cls = 'wp-block-image size-full' . ( $class ? ' ' . $class : '' );
		return '<!-- wp:image ' . wp_json_encode( $a ) . " -->\n<figure class=\"" . esc_attr( $cls ) . "\">$body</figure>\n<!-- /wp:image -->\n";
	}

	private function attachment( string $source_file ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_cdf_source_url' AND meta_value LIKE %s LIMIT 1",
				'%/' . $wpdb->esc_like( $source_file )
			)
		);
	}

	/**
	 * Cópia recortada (só para composições editoriais) de uma fotografia que vem da origem com margens brancas.
	 * O produto continua a usar a fotografia original; nada é alterado além do recorte das margens.
	 */
	private function editorial( string $source_file ): int {
		$orig = $this->attachment( $source_file );
		if ( ! $orig ) {
			return 0;
		}
		$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_cdf_editorial_of', 'meta_value' => $orig, 'posts_per_page' => 1, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $found ) {
			return (int) $found[0];
		}
		$path = wp_get_original_image_path( $orig ) ?: get_attached_file( $orig );
		$img  = ( $path && function_exists( 'imagecreatefromstring' ) ) ? @imagecreatefromstring( (string) file_get_contents( $path ) ) : false; // phpcs:ignore
		if ( ! $img ) {
			return $orig;
		}
		$w      = imagesx( $img );
		$h      = imagesy( $img );
		$is_ink = static function ( $x, $y ) use ( $img ) {
			$c = imagecolorat( $img, $x, $y );
			$l = ( ( $c >> 16 ) & 255 ) * 0.299 + ( ( $c >> 8 ) & 255 ) * 0.587 + ( $c & 255 ) * 0.114;
			return $l < 236;
		};
		$row_has = static function ( $y ) use ( $w, $is_ink ) {
			$n = 0;
			for ( $x = 0; $x < $w; $x += 3 ) {
				$n += $is_ink( $x, $y ) ? 1 : 0;
			}
			return $n > $w / 3 / 20;
		};
		$col_has = static function ( $x ) use ( $h, $is_ink ) {
			$n = 0;
			for ( $y = 0; $y < $h; $y += 3 ) {
				$n += $is_ink( $x, $y ) ? 1 : 0;
			}
			return $n > $h / 3 / 20;
		};
		$top = 0;
		while ( $top < $h - 1 && ! $row_has( $top ) ) {
			++$top;
		}
		$bottom = $h - 1;
		while ( $bottom > $top && ! $row_has( $bottom ) ) {
			--$bottom;
		}
		$left = 0;
		while ( $left < $w - 1 && ! $col_has( $left ) ) {
			++$left;
		}
		$right = $w - 1;
		while ( $right > $left && ! $col_has( $right ) ) {
			--$right;
		}
		imagedestroy( $img );
		$cw = $right - $left + 1;
		$ch = $bottom - $top + 1;
		// Só recorta fotografias de ambiente com margens claras (não fotografias de produto em fundo branco).
		if ( ( $cw * $ch ) > 0.94 * $w * $h || $cw < $w * 0.5 || $ch < $h * 0.5 ) {
			return $orig;
		}
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return $orig;
		}
		$editor->crop( $left + 2, $top + 2, $cw - 4, $ch - 4 );
		$upload = wp_upload_dir();
		$dest   = trailingslashit( $upload['path'] ) . 'editorial-' . pathinfo( $path, PATHINFO_FILENAME ) . '.jpg';
		$saved  = $editor->save( $dest, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			return $orig;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => get_the_title( $orig ) . ' (recorte editorial)',
				'post_status'    => 'inherit',
			),
			$saved['path']
		);
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $saved['path'] ) );
		update_post_meta( $id, '_cdf_editorial_of', $orig );
		update_post_meta( $id, '_wp_attachment_image_alt', get_post_meta( $orig, '_wp_attachment_image_alt', true ) );
		return (int) $id;
	}

	private function block( string $name, array $attrs ): string {
		return '<!-- wp:' . $name . ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . " /-->\n";
	}

	private function cat( string $source_key ): string {
		$term = cdf_term_by_source_key( $source_key );
		if ( ! $term ) {
			return '';
		}
		$link = get_term_link( $term );
		return is_wp_error( $link ) ? '' : $link;
	}

	private function cat_slug( string $source_key ): string {
		$term = cdf_term_by_source_key( $source_key );
		return $term ? $term->slug : '';
	}

	private function url( string $path ): string {
		return home_url( '/' . trim( $path, '/' ) . '/' );
	}

	private function section_head( string $eyebrow, string $title, string $link_text = '', string $link = '', string $text = '' ): string {
		$inner = $this->group( ( $eyebrow ? $this->p( $eyebrow, 'is-style-cdf-eyebrow' ) : '' ) . $this->h( $title ) . ( $text ? $this->p( $text ) : '' ), 'cdf-section__title' );
		if ( $link ) {
			$inner .= $this->buttons( array( array( $link_text, $link, 'is-style-cdf-link' ) ) );
		}
		return $this->group( $inner, 'cdf-section__head' );
	}

	// ------------------------------------------------------------ páginas

	private function upsert( string $slug, string $title, string $content, array $opts = array() ): int {
		$parent = $opts['parent'] ?? 0;
		// Páginas pendentes não guardam slug: a página semeada é identificada pela meta _cdf_seed_slug.
		$found    = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_cdf_seed_slug', 'meta_value' => $slug, 'posts_per_page' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$existing = $found ? $found[0] : get_page_by_path( $parent ? get_post_field( 'post_name', $parent ) . '/' . $slug : $slug, OBJECT, 'page' );
		$data     = array(
			'post_type'    => 'page',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_excerpt' => $opts['excerpt'] ?? '',
			'post_status'  => $opts['status'] ?? 'publish',
			'post_parent'  => $parent,
			'menu_order'   => $opts['order'] ?? 0,
		);
		if ( $existing ) {
			$this->ids[ $slug ] = $existing->ID;
			$edited             = get_post_meta( $existing->ID, '_cdf_seed_hash', true ) !== md5( $existing->post_content );
			if ( $edited && ! $this->force ) {
				WP_CLI::log( "  mantida (editada no backoffice): $slug" );
				return $existing->ID;
			}
			$data['ID'] = $existing->ID;
			wp_update_post( wp_slash( $data ) );
			$id = $existing->ID;
		} else {
			$id = wp_insert_post( wp_slash( $data ) );
		}
		if ( ! empty( $opts['template'] ) ) {
			update_post_meta( $id, '_wp_page_template', $opts['template'] );
		}
		if ( ! empty( $opts['thumbnail'] ) ) {
			$tid = $this->attachment( $opts['thumbnail'] );
			if ( $tid ) {
				set_post_thumbnail( $id, $tid );
			}
		}
		update_post_meta( $id, '_cdf_seed_hash', md5( get_post_field( 'post_content', $id ) ) );
		update_post_meta( $id, '_cdf_seeded', 1 );
		update_post_meta( $id, '_cdf_seed_slug', $slug );
		$this->ids[ $slug ] = $id;
		WP_CLI::log( "  página: $slug ($id)" );
		return $id;
	}

	public function run() {
		$this->category_images();
		$this->woo_pages();
		$this->home();
		$this->personalization();
		$this->sectors();
		$this->about();
		$this->contacts();
		$this->quote();
		$this->legal();
		$this->menus();
		$this->settings();
	}

	/**
	 * Imagem representativa das categorias principais (só se o gestor ainda não escolheu outra).
	 */
	private function category_images() {
		$map = array(
			'type:1' => 'vista_1328_0.jpg',
			'type:2' => 'vista_518_0.jpg',
			'cat:4'  => 'vista_2132_0.jpg',
			'cat:3'  => 'vista_2395_0.jpg',
			'cat:1'  => 'vista_1543_0.jpg',
			'cat:2'  => 'vista_474_0.jpg',
			'cat:11' => 'vista_492_0.jpg',
			'cat:5'  => 'vista_3542_0.jpg',
			'cat:7'  => 'vista_518_0.jpg',
		);
		foreach ( $map as $key => $file ) {
			$term = cdf_term_by_source_key( $key );
			$id   = $this->attachment( $file );
			if ( $term && $id && ( $this->force || ! get_term_meta( $term->term_id, 'thumbnail_id', true ) ) ) {
				update_term_meta( $term->term_id, 'thumbnail_id', $id );
				delete_transient( 'cdf_term_img_' . $term->term_id );
			}
		}
	}

	private function woo_pages() {
		$shop     = $this->upsert( 'loja', 'Loja', '', array( 'excerpt' => '' ) );
		$cart     = $this->upsert( 'carrinho', 'Carrinho', "<!-- wp:shortcode -->\n[woocommerce_cart]\n<!-- /wp:shortcode -->\n" );
		$checkout = $this->upsert( 'finalizar-compra', 'Finalizar compra', "<!-- wp:shortcode -->\n[woocommerce_checkout]\n<!-- /wp:shortcode -->\n" );
		$account  = $this->upsert( 'minha-conta', 'Minha conta', "<!-- wp:shortcode -->\n[woocommerce_my_account]\n<!-- /wp:shortcode -->\n" );
		update_option( 'woocommerce_shop_page_id', $shop );
		update_option( 'woocommerce_cart_page_id', $cart );
		update_option( 'woocommerce_checkout_page_id', $checkout );
		update_option( 'woocommerce_myaccount_page_id', $account );
	}

	private function home() {
		$quote = $this->url( 'pedido-de-orcamento' );
		$shop  = $this->url( 'loja' );

		$hero_copy = $this->group(
			$this->p( 'Fardas · Vestuário de trabalho · EPI', 'is-style-cdf-eyebrow' ) .
			$this->h( 'A imagem profissional da sua equipa <mark>começa aqui</mark>.', 1, 'cdf-hero__title' ) .
			$this->p( 'Vestuário de trabalho, calçado de segurança e equipamentos de proteção individual para empresas e instituições — com bordados e estamparia de produção própria e fornecimento para todo o país.', 'is-style-cdf-lead' ) .
			$this->buttons( array( array( 'Explorar a loja', $shop ), array( 'Equipar a minha empresa', $quote, 'is-style-cdf-secondary' ) ) ) .
			$this->lst(
				array(
					'<strong>Bordados e estamparia</strong>Produção própria, com o logótipo da sua empresa',
					'<strong>Todo o país</strong>Fornecemos empresas e instituições de norte a sul',
					'<strong>Loja em Alcantarilha</strong>Retail Park Vale de Lousas, EN 125',
				),
				'cdf-hero__facts'
			),
			'cdf-hero__copy'
		);
		$hero_visual = $this->group(
			$this->group(
				$this->img( 'vista_1816_0.jpg', 'Profissionais de saúde com fardas de duas peças em azul e vermelho', '<a href="' . esc_url( $this->cat( 'cat:3' ) ) . '">Saúde e serviços</a>' ) .
				$this->img( 'vista_474_0.jpg', 'Parka de alta visibilidade amarela com bandas refletoras', '<a href="' . esc_url( $this->cat( 'cat:2' ) ) . '">Alta visibilidade</a>', '', '', false ) .
				$this->img( 'vista_492_0.jpg', 'Bota de segurança em pele preta', '<a href="' . esc_url( $this->cat( 'cat:11' ) ) . '">Calçado de segurança</a>', '', '', false ),
				'cdf-hero__mosaic'
			),
			'cdf-hero__visual'
		);
		$hero = $this->group( $this->group( $hero_copy . $hero_visual, 'cdf-hero__grid' ), 'cdf-hero', 'full' );

		$sectors = '';
		foreach ( $this->sector_list() as $s ) {
			$sectors .= $this->group(
				$this->img( $s['image'], '', '', '' ) .
				$this->h( '<a href="' . esc_url( $this->url( 'setores/' . $s['slug'] ) ) . '">' . esc_html( $s['short'] ) . '</a>', 3 ) .
				$this->p( esc_html( $s['card'] ) ),
				'cdf-sector-card'
			);
		}
		$sectors_section = $this->group(
			$this->section_head( 'Por setor', 'Fardas e equipamento para cada setor', 'Ver todos os setores', $this->url( 'setores' ) ) .
			$this->group( $sectors, 'cdf-sector-grid' ),
			'cdf-section',
			'full'
		);

		$cats = array_filter( array( $this->cat_slug( 'cat:11' ), $this->cat_slug( 'cat:2' ), $this->cat_slug( 'cat:1' ), $this->cat_slug( 'cat:4' ), $this->cat_slug( 'cat:3' ), $this->cat_slug( 'cat:7' ), $this->cat_slug( 'cat:5' ), $this->cat_slug( 'cat:10' ) ) );
		$quick = $this->group(
			$this->section_head( 'Compra rápida', 'Comece pela categoria certa', 'Ver toda a loja', $shop ) .
			$this->block( 'cdf/categories', array( 'slugs' => implode( ',', $cats ), 'limit' => 8, 'columns' => 4, 'style' => 'tiles' ) ),
			'cdf-section is-style-cdf-band-surface',
			'full'
		);

		$featured = $this->group(
			$this->section_head( 'Em destaque', 'Uma seleção do catálogo', 'Ver toda a loja', $shop, 'Artigos escolhidos para mostrar a variedade da loja — da cozinha à obra.' ) .
			$this->block( 'cdf/products', array( 'skus' => implode( ',', $this->featured_skus() ), 'limit' => 8, 'columns' => 4 ) ),
			'cdf-section',
			'full'
		);

		$custom = $this->group(
			$this->group(
				$this->group(
					$this->p( 'Personalização', 'is-style-cdf-eyebrow' ) .
					$this->h( 'Bordados e estamparia com produção própria' ) .
					$this->p( 'Coloque a marca da sua empresa nas fardas da equipa. Junte os artigos ao pedido de orçamento, indique quantidades e tamanhos e anexe o logótipo — respondemos com uma proposta.' ) .
					$this->lst( array( 'Pólos, t-shirts, sweats, casacos e fardas de trabalho', 'Pedidos com vários artigos, cores e tamanhos', 'Envio do logótipo em PNG, JPG ou PDF no próprio pedido' ), 'is-style-cdf-checks' ) .
					$this->buttons( array( array( 'Pedir orçamento', $quote ), array( 'Como funciona', $this->url( 'personalizacao' ), 'is-style-cdf-light' ) ) )
				) .
				$this->group(
					$this->img( 'vista_2_0.jpg', 'Pólo de trabalho azul com detalhes contrastantes', 'Pólos', '', '', false ) .
					$this->img( 'vista_1525_0.jpg', 'Blusão de trabalho cinzento acolchoado', 'Casacos e blusões', '', '', false ),
					'cdf-custom-band__media'
				),
				'cdf-custom-band__grid'
			),
			'cdf-custom-band is-style-cdf-band-navy',
			'full'
		);

		$shoes_links = '';
		foreach ( array( 'sub:64', 'sub:65', 'sub:67', 'sub:66', 'sub:68', 'sub:69' ) as $k ) {
			$t = cdf_term_by_source_key( $k );
			if ( $t && $t->count ) {
				$shoes_links .= '<li><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>';
			}
		}
		$epi_links = '';
		foreach ( array( 'cat:7', 'cat:10', 'cat:8', 'cat:9', 'cat:6', 'cat:13' ) as $k ) {
			$t = cdf_term_by_source_key( $k );
			if ( $t ) {
				$epi_links .= '<li><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>';
			}
		}
		$duo = $this->group(
			$this->group(
				$this->group(
					$this->group(
						$this->p( 'Calçado', 'is-style-cdf-eyebrow' ) .
						$this->h( 'Calçado de segurança' ) .
						$this->p( 'Botas e sapatos com biqueira em aço ou compósito, modelos antiestáticos, brancos para alimentação e saúde, e de borracha.' ) .
						"<!-- wp:html -->\n<ul class=\"cdf-links\">$shoes_links</ul>\n<!-- /wp:html -->\n",
						'cdf-duo__text'
					) . $this->img( 'vista_499_0.jpg', 'Sapato de segurança desportivo preto e verde', '', $this->cat( 'cat:11' ), '', false ),
					'cdf-duo__tile'
				) .
				$this->group(
					$this->group(
						$this->p( 'Proteção', 'is-style-cdf-eyebrow' ) .
						$this->h( 'Proteção individual (EPI)' ) .
						$this->p( 'Luvas, proteção respiratória e auditiva, óculos e viseiras, capacetes, anti-quedas, descartáveis e sinalização.' ) .
						"<!-- wp:html -->\n<ul class=\"cdf-links\">$epi_links</ul>\n<!-- /wp:html -->\n",
						'cdf-duo__text'
					) . $this->img( 'vista_518_0.jpg', 'Luvas de proteção anti-corte com palma revestida', '', $this->cat( 'cat:7' ), '', false ),
					'cdf-duo__tile cdf-duo__tile--dark'
				),
				'cdf-duo'
			),
			'cdf-section',
			'full'
		);

		$store = $this->group(
			$this->group(
				$this->group(
					$this->p( 'A loja', 'is-style-cdf-eyebrow' ) .
					$this->h( 'Uma loja física em Alcantarilha, ao serviço de todo o país' ) .
					$this->p( 'A Casa das Fardas é a loja de vestuário profissional da Amazonas Indústria, Lda., no Retail Park Vale de Lousas. Fornecemos todo o tipo de vestuário de trabalho, casual e desporto, e equipamentos de proteção individual, para empresas e instituições.' ) .
					$this->buttons( array( array( 'Sobre a Casa das Fardas', $this->url( 'sobre' ), 'is-style-cdf-secondary' ), array( 'Contactos', $this->url( 'contactos' ), 'is-style-cdf-link' ) ) )
				) .
				$this->block( 'cdf/business-info', array( 'show' => 'all' ) ),
				'cdf-store'
			),
			'cdf-section',
			'full'
		);

		$content = $hero . $sectors_section . $quick . $featured . $custom . $duo . $store;
		$id      = $this->upsert(
			'inicio',
			'Início',
			$content,
			array( 'excerpt' => 'Fardas, vestuário de trabalho, calçado de segurança e EPI para empresas, com bordados e estamparia de produção própria. Loja em Alcantarilha, fornecimento para todo o país.' )
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );
		update_option( 'cdf_home_description', 'Fardas, vestuário de trabalho, calçado de segurança e EPI para empresas, com bordados e estamparia de produção própria. Loja em Alcantarilha, fornecimento para todo o país.' );
		$share = $this->attachment( 'vista_1816_0.jpg' );
		if ( $share ) {
			update_option( 'cdf_default_share_image', $share );
		}
	}

	/**
	 * Setores: textos baseados apenas nas categorias e descrições da loja antiga.
	 */
	private function sector_list(): array {
		return array(
			array(
				'slug'  => 'hotelaria-e-restauracao',
				'title' => 'Fardas para hotelaria e restauração',
				'short' => 'Hotelaria e restauração',
				'card'  => 'Jaquetas, calças, aventais, coletes, toucas e têxteis para cozinha e sala.',
				'intro' => 'Jaquetas de cozinha, calças, aventais, coletes, saias, t-shirts e toucas para cozinha e sala, além de mantas e toalhas. Tudo pode levar o logótipo do restaurante ou do hotel, em bordado ou estampagem.',
				'cats'  => array( 'cat:4' ),
				'more'  => array( 'sub:67', 'sub:78' ),
				'image' => 'vista_3395_0.jpg',
			),
			array(
				'slug'  => 'saude-e-servicos',
				'title' => 'Vestuário para saúde, limpeza e serviços',
				'short' => 'Saúde e serviços',
				'card'  => 'Batas, túnicas, calças, aventais e toucas para clínicas, lares, limpeza e infantários.',
				'intro' => 'Batas, conjuntos de túnica e calça, camisas, aventais, coletes, saias e toucas para clínicas, lares, serviços de limpeza e infantários — com calçado de proteção branco e descartáveis.',
				'cats'  => array( 'cat:3' ),
				'more'  => array( 'sub:67', 'cat:14' ),
				'image' => 'vista_2368_0.jpg',
			),
			array(
				'slug'  => 'industria',
				'title' => 'Vestuário de trabalho para a indústria',
				'short' => 'Indústria e oficinas',
				'card'  => 'Calças, blusões, fatos-macaco, pólos e vestuário técnico para fábricas e oficinas.',
				'intro' => 'Vestuário para as várias indústrias — automóvel, metalomecânica, construção e outras: calças, jardineiras, blusões e parkas, fatos-macaco, pólos, sweats, polares e vestuário técnico, além de vestuário especial ignífugo, antiestático e para soldadura.',
				'cats'  => array( 'cat:1', 'cat:21' ),
				'more'  => array( 'cat:7', 'cat:11' ),
				'image' => 'vista_1328_0.jpg',
			),
			array(
				'slug'  => 'construcao-e-obras',
				'title' => 'Alta visibilidade e proteção para construção e obras',
				'short' => 'Construção e obras',
				'card'  => 'Alta visibilidade, capacetes, anti-quedas, sinalização e calçado de segurança.',
				'intro' => 'Vestuário de alta visibilidade (coletes, casacos, parkas, calças, pólos e t-shirts) com o equipamento de proteção que completa a farda em obra: capacetes, sistemas anti-quedas, luvas, sinalização e calçado de segurança.',
				'cats'  => array( 'cat:2' ),
				'more'  => array( 'cat:6', 'cat:13', 'cat:15', 'cat:11' ),
				'image' => 'vista_4086_0.jpg',
			),
			array(
				'slug'  => 'desporto',
				'title' => 'Equipamento de desporto para equipas',
				'short' => 'Desporto',
				'card'  => 'T-shirts técnicas, pólos, fatos de treino, futebol, sacos e sapatilhas.',
				'intro' => 'T-shirts técnicas, pólos, calças e calções, fatos de treino, equipamentos de futebol, coletes, meias, saias, sacos e mochilas e sapatilhas — para equipas, clubes e eventos, com personalização.',
				'cats'  => array( 'cat:5' ),
				'more'  => array(),
				'image' => 'vista_3699_0.jpg',
			),
		);
	}

	private function featured_skus(): array {
		$wanted = get_option( 'cdf_featured_source_ids', array( 4086, 2132, 1816, 492, 2, 518, 3542, 2142 ) );
		$skus   = array();
		foreach ( $wanted as $sid ) {
			$pid = cdf_post_by_source_id( 'p:' . $sid );
			$sku = $pid ? get_post_meta( $pid, '_sku', true ) : '';
			if ( $sku ) {
				$skus[] = $sku;
			}
		}
		return $skus;
	}

	private function personalization() {
		$quote   = $this->url( 'pedido-de-orcamento' );
		$hero    = $this->group(
			$this->group(
				$this->group(
					$this->p( 'Personalização', 'is-style-cdf-eyebrow' ) .
					$this->h( 'Bordados e estamparia para a sua equipa', 1 ) .
					$this->p( 'Temos produção própria de bordados e estamparia. Escolha os artigos, envie-nos o logótipo e preparamos uma proposta para vestir a equipa com a imagem da sua empresa.', 'is-style-cdf-lead' ) .
					$this->buttons( array( array( 'Pedir orçamento', $quote ), array( 'Escolher artigos', $this->url( 'loja' ), 'is-style-cdf-secondary' ) ) )
				) .
				$this->img( 'vista_1302_0.jpg', 'Grupo com pólos de várias cores', '' ),
				'cdf-page-hero__grid'
			),
			'cdf-page-hero',
			'full'
		);
		$techniques = $this->group(
			$this->section_head( 'Técnicas', 'Bordado ou estampagem?' ) .
			$this->group(
				$this->group( $this->h( 'Bordado', 3 ) . $this->p( 'O desenho é feito com linha diretamente no tecido. Tem relevo e é muito resistente às lavagens — é a escolha habitual para logótipos em pólos, camisas, jaquetas e casacos.' ) ) .
				$this->group( $this->h( 'Estampagem', 3 ) . $this->p( 'A imagem é aplicada sobre o tecido. Permite áreas maiores e mais detalhe — indicada, por exemplo, para costas de t-shirts e sweats ou equipamentos de desporto.' ) ) .
				$this->group( $this->h( 'Não sabe qual escolher?', 3 ) . $this->p( 'Indique no pedido «preciso de aconselhamento». Dizemos-lhe qual a técnica mais adequada ao artigo, à quantidade e ao seu logótipo.' ) ),
				'cdf-cards'
			),
			'cdf-section',
			'full'
		);
		$steps = $this->group(
			$this->section_head( 'Como funciona', 'Do pedido à equipa equipada' ) .
			$this->lst(
				array(
					'<strong>Escolha os artigos</strong>Na loja, use «Adicionar ao pedido de orçamento» em cada artigo, com cor, tamanho e quantidade — ou descreva o que precisa no formulário.',
					'<strong>Envie o logótipo</strong>Anexe o ficheiro (PNG, JPG ou PDF) e indique onde o quer: peito, costas, manga.',
					'<strong>Receba a proposta</strong>Respondemos por email com preços e prazo. Pode ajustar quantidades ou artigos antes de confirmar.',
					'<strong>Produção e entrega</strong>Depois de aprovada a proposta, tratamos da personalização e da entrega.',
				),
				'is-style-cdf-steps'
			),
			'cdf-section is-style-cdf-band-surface',
			'full'
		);
		$what = $this->group(
			$this->section_head( 'O que personalizar', 'Artigos mais pedidos para personalização', 'Ver toda a loja', $this->url( 'loja' ) ) .
			$this->block( 'cdf/categories', array( 'slugs' => implode( ',', array_filter( array( $this->cat_slug( 'sub:20' ), $this->cat_slug( 'sub:35' ), $this->cat_slug( 'sub:32' ), $this->cat_slug( 'sub:2' ), $this->cat_slug( 'sub:105' ), $this->cat_slug( 'sub:37' ), $this->cat_slug( 'sub:16' ), $this->cat_slug( 'sub:112' ) ) ) ), 'limit' => 8, 'columns' => 4 ) ),
			'cdf-section',
			'full'
		);
		$cta = $this->group(
			$this->group(
				$this->h( 'Pronto para pedir?' ) .
				$this->p( 'Envie-nos a lista de artigos e o logótipo. Sem compromisso.' ) .
				$this->buttons( array( array( 'Pedir orçamento', $quote ) ) ),
				'cdf-prose-block'
			),
			'cdf-section is-style-cdf-band-amber',
			'full'
		);
		$this->upsert(
			'personalizacao',
			'Personalização',
			$hero . $techniques . $steps . $what . $cta,
			array(
				'template' => 'page-wide.php',
				'excerpt'  => 'Bordados e estamparia com produção própria: personalize pólos, t-shirts, casacos e fardas com o logótipo da sua empresa. Peça orçamento com o logótipo anexado.',
				'order'    => 3,
			)
		);
	}

	private function sectors() {
		$cards = '';
		foreach ( $this->sector_list() as $s ) {
			$cards .= $this->group(
				$this->img( $s['image'], '' ) .
				$this->h( '<a href="' . esc_url( $this->url( 'setores/' . $s['slug'] ) ) . '">' . esc_html( $s['short'] ) . '</a>', 3 ) .
				$this->p( esc_html( $s['card'] ) ),
				'cdf-sector-card'
			);
		}
		$parent = $this->upsert(
			'setores',
			'Setores',
			$this->group(
				$this->group(
					$this->group(
						$this->p( 'Setores', 'is-style-cdf-eyebrow' ) .
						$this->h( 'Fardas e equipamento para cada setor', 1 ) .
						$this->p( 'Vestimos equipas de hotelaria, saúde, indústria, construção e desporto. Escolha o seu setor para ver os artigos mais adequados.', 'is-style-cdf-lead' )
					),
					'cdf-page-hero__grid'
				),
				'cdf-page-hero cdf-page-hero--plain',
				'full'
			) .
			$this->group( $this->group( $cards, 'cdf-sector-grid' ), 'cdf-section cdf-section--tight', 'full' ),
			array(
				'template' => 'page-wide.php',
				'excerpt'  => 'Fardas e equipamento de proteção por setor: hotelaria e restauração, saúde e serviços, indústria, construção e obras, desporto.',
				'order'    => 2,
			)
		);
		foreach ( $this->sector_list() as $i => $s ) {
			$main_cats = array_filter( array_map( fn( $k ) => $this->cat_slug( $k ), $s['cats'] ) );
			$content   = $this->group(
				$this->group(
					$this->group(
						$this->p( 'Setor', 'is-style-cdf-eyebrow' ) .
						$this->h( esc_html( $s['title'] ), 1 ) .
						$this->p( esc_html( $s['intro'] ), 'is-style-cdf-lead' ) .
						$this->buttons( array( array( 'Pedir orçamento para a equipa', $this->url( 'pedido-de-orcamento' ) ), array( 'Ver artigos', $main_cats ? get_term_link( $main_cats[ array_key_first( $main_cats ) ], 'product_cat' ) : $this->url( 'loja' ), 'is-style-cdf-secondary' ) ) )
					) .
					$this->img( $s['image'], '' ),
					'cdf-page-hero__grid'
				),
				'cdf-page-hero',
				'full'
			);
			foreach ( $s['cats'] as $k ) {
				$term = cdf_term_by_source_key( $k );
				if ( ! $term ) {
					continue;
				}
				$has_children = (bool) get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true, 'fields' => 'ids', 'number' => 1 ) );
				$content     .= $this->group(
					$this->section_head( '', esc_html( $term->name ), 'Ver todos', get_term_link( $term ) ) .
					( $has_children ? $this->block( 'cdf/categories', array( 'parent' => $term->slug, 'limit' => 20, 'columns' => 4, 'style' => 'tiles' ) ) : '' ) .
					$this->block( 'cdf/products', array( 'category' => $term->slug, 'limit' => 8, 'columns' => 4 ) ),
					'cdf-section cdf-section--tight',
					'full'
				);
			}
			if ( $s['more'] ) {
				$slugs    = array_filter( array_map( fn( $k ) => $this->cat_slug( $k ), $s['more'] ) );
				$content .= $this->group(
					$this->section_head( 'Complete a farda', 'Também para este setor' ) .
					$this->block( 'cdf/categories', array( 'slugs' => implode( ',', $slugs ), 'limit' => 6, 'columns' => 4 ) ),
					'cdf-section cdf-section--tight is-style-cdf-band-surface',
					'full'
				);
			}
			$this->upsert(
				$s['slug'],
				$s['short'],
				$content,
				array(
					'parent'    => $parent,
					'template'  => 'page-wide.php',
					'excerpt'   => $s['intro'],
					'order'     => $i + 1,
					'thumbnail' => $s['image'],
				)
			);
		}
	}

	private function about() {
		$content = $this->group(
			$this->group(
				$this->group(
					$this->p( 'Sobre nós', 'is-style-cdf-eyebrow' ) .
					$this->h( 'Casa das Fardas', 1 ) .
					$this->p( 'Somos fornecedores de todo o tipo de vestuário de trabalho e equipamentos de proteção individual. Tudo o que a sua empresa ou instituição precisa para vestir e proteger a equipa.', 'is-style-cdf-lead' )
				) . $this->img( 'vista_1289_0.jpg', 'Equipa com pólos de trabalho', '' ),
				'cdf-page-hero__grid'
			),
			'cdf-page-hero',
			'full'
		) .
		$this->group(
			$this->section_head( 'O que fazemos', 'Vestuário, calçado e proteção num só fornecedor' ) .
			$this->group(
				$this->group( $this->h( 'Vestuário profissional', 3 ) . $this->p( 'Alta visibilidade, hotelaria e restauração, saúde e serviços, indústria em geral — automóvel, metalomecânica, construção, autarquias — e vestuário especial ignífugo, antiestático e para soldadura.' ) ) .
				$this->group( $this->h( 'Calçado e EPI', 3 ) . $this->p( 'Calçado de segurança, luvas de proteção, proteção auditiva e respiratória, óculos, capacetes, anti-quedas, descartáveis, sinalização e primeiros socorros.' ) ) .
				$this->group( $this->h( 'Têxtil promocional e desporto', 3 ) . $this->p( 'Pólos, t-shirts e equipamentos de desporto, com bordados e estamparia de produção própria. Os brindes publicitários estão num catálogo dedicado.' ) ),
				'cdf-cards'
			),
			'cdf-section',
			'full'
		) .
		$this->group(
			$this->group(
				$this->group(
					$this->h( 'Onde estamos' ) .
					$this->p( 'A loja fica no Retail Park Vale de Lousas, na EN 125, em Alcantarilha. Fornecemos para todo o país.' ) .
					$this->buttons( array( array( 'Contactos', $this->url( 'contactos' ), 'is-style-cdf-secondary' ) ) )
				) .
				$this->block( 'cdf/business-info', array( 'show' => 'all' ) ),
				'cdf-store'
			),
			'cdf-section is-style-cdf-band-surface',
			'full'
		);
		$this->upsert(
			'sobre',
			'Sobre a Casa das Fardas',
			$content,
			array(
				'template' => 'page-wide.php',
				'excerpt'  => 'A Casa das Fardas (Amazonas Indústria, Lda.) fornece vestuário de trabalho e equipamentos de proteção individual, com bordados e estamparia de produção própria. Loja em Alcantarilha.',
				'order'    => 5,
			)
		);
	}

	private function contacts() {
		$content = $this->group(
			$this->group(
				$this->group(
					$this->p( 'Contactos', 'is-style-cdf-eyebrow' ) .
					$this->h( 'Fale connosco', 1 ) .
					$this->p( 'Envie-nos o seu pedido por email ou pelo formulário para lhe apresentarmos a melhor proposta. Também pode visitar a loja em Alcantarilha.', 'is-style-cdf-lead' )
				),
				'cdf-page-hero__grid'
			),
			'cdf-page-hero cdf-page-hero--plain',
			'full'
		) .
		$this->group(
			$this->group(
				$this->group(
					$this->h( 'Envie uma mensagem' ) .
					$this->p( 'Para pedidos com vários artigos ou personalização, use o <a href="' . esc_url( $this->url( 'pedido-de-orcamento' ) ) . '">pedido de orçamento</a> — pode anexar o logótipo.' ) .
					$this->block( 'cdf/form', array( 'mode' => 'contact' ) )
				) .
				$this->block( 'cdf/business-info', array( 'show' => 'all' ) ),
				'cdf-store'
			),
			'cdf-section cdf-section--tight',
			'full'
		);
		$this->upsert(
			'contactos',
			'Contactos',
			$content,
			array(
				'template' => 'page-wide.php',
				'excerpt'  => 'Contactos da Casa das Fardas: Retail Park Vale de Lousas, EN 125, 8365-306 Alcantarilha. Telefone 964 395 979, info@amazonas.pt. Segunda a sexta, 9h–13h e 14h–18h.',
				'order'    => 6,
			)
		);
	}

	private function quote() {
		$content = $this->group(
			$this->group(
				$this->group(
					$this->p( 'Para empresas e instituições', 'is-style-cdf-eyebrow' ) .
					$this->h( 'Pedido de orçamento', 1 ) .
					$this->p( 'Diga-nos que artigos, quantidades e tamanhos precisa e se quer bordado ou estampagem. Respondemos por email com preço e prazo, sem compromisso.', 'is-style-cdf-lead' )
				) .
				$this->lst(
					array(
						'<strong>Junte artigos da loja</strong>Use «Adicionar ao pedido de orçamento» nas fichas — aparecem aqui em baixo.',
						'<strong>Ou descreva o que precisa</strong>Referências, quantidades e cores no campo de artigos.',
						'<strong>Anexe o logótipo</strong>PNG, JPG ou PDF até 5 MB, se quiser personalização.',
					),
					'is-style-cdf-steps'
				),
				'cdf-page-hero__grid'
			),
			'cdf-page-hero',
			'full'
		) .
		$this->group( $this->block( 'cdf/form', array( 'mode' => 'quote' ) ), 'cdf-section cdf-section--tight', 'full' );
		$this->upsert(
			'pedido-de-orcamento',
			'Pedido de orçamento',
			$content,
			array(
				'template' => 'page-wide.php',
				'excerpt'  => 'Peça orçamento para fardas e vestuário de trabalho com bordado ou estampagem: vários artigos, quantidades, tamanhos e logótipo no mesmo pedido.',
				'order'    => 4,
			)
		);
	}

	/**
	 * Páginas legais e de serviço: estrutura proposta com os factos conhecidos.
	 * Ficam «Pendentes de revisão» até validação pelo responsável do negócio.
	 */
	private function legal() {
		$pending = '<!-- wp:paragraph {"className":"cdf-pending-note"} -->' . "\n" . '<p class="cdf-pending-note"><strong>[PENDENTE DE VALIDAÇÃO]</strong> Rascunho preparado na migração. Não publicar sem confirmar com o responsável do negócio (e, quando aplicável, com aconselhamento jurídico) todos os pontos marcados «A VALIDAR».</p>' . "\n<!-- /wp:paragraph -->\n";
		$b       = cdf_business();
		$pages   = array(
			'entregas'                => array(
				'Entregas',
				$pending .
				$this->h( 'Onde entregamos' ) . $this->p( 'Fornecemos para todo o país. [A VALIDAR: entregas nas regiões autónomas e no estrangeiro.]' ) .
				$this->h( 'Portes e prazos' ) . $this->p( '[A VALIDAR: transportadora(s), tabela de portes, prazos de entrega por tipo de artigo (em stock, por encomenda, personalizado) e condições de portes grátis, se existirem.]' ) .
				$this->h( 'Levantamento na loja' ) . $this->p( '[A VALIDAR: possibilidade de levantar encomendas na loja de Alcantarilha (' . esc_html( $b['hours'] ) . ').]' ),
			),
			'trocas-e-devolucoes'     => array(
				'Trocas e devoluções',
				$pending .
				$this->h( 'Direito de livre resolução' ) . $this->p( '[A VALIDAR: prazo e condições do direito de livre resolução para consumidores, nos termos da lei em vigor, e respetivas exceções — por exemplo, artigos personalizados com bordado ou estampagem.]' ) .
				$this->h( 'Trocas de tamanho' ) . $this->p( '[A VALIDAR: política de trocas de tamanho, prazo e estado dos artigos.]' ) .
				$this->h( 'Como pedir' ) . $this->p( 'Contacte-nos por ' . esc_html( cdf_business_lines( 'emails' )[0] ?? '' ) . ' ou pelo telefone ' . esc_html( cdf_phone_display( $b['phone'] ) ) . ' (' . esc_html( $b['phone_note'] ) . '). [A VALIDAR: procedimento e custos de devolução.]' ),
			),
			'pagamentos'              => array(
				'Pagamentos',
				$pending .
				$this->h( 'Meios de pagamento' ) . $this->p( '[A VALIDAR: meios de pagamento aceites online (por exemplo, Multibanco, MB WAY, cartão, transferência) e fornecedor de pagamentos contratado.]' ) .
				$this->h( 'Preços e IVA' ) . $this->p( 'Os preços dos artigos são apresentados sem IVA, com o valor com IVA à taxa legal em vigor indicado em cada artigo e no carrinho. [A VALIDAR: taxas aplicáveis às regiões autónomas e vendas a empresas de outros Estados-Membros.]' ) .
				$this->h( 'Faturação' ) . $this->p( 'Indique o NIF no checkout para a fatura com contribuinte. [A VALIDAR: emissão e envio de faturas.]' ),
			),
			'termos-e-condicoes'      => array(
				'Termos e condições',
				$pending .
				$this->h( 'Identificação' ) . $this->p( esc_html( $b['legal_name'] ) . ', ' . esc_html( $b['street'] ) . ', ' . esc_html( $b['postcode'] . ' ' . $b['locality'] ) . '. [A VALIDAR: NIF, registo comercial e capital social.]' ) .
				$this->h( 'Encomendas' ) . $this->p( '[A VALIDAR: formação do contrato, confirmação de disponibilidade (os artigos são vendidos com disponibilidade confirmada após a encomenda), cancelamentos.]' ) .
				$this->h( 'Resolução de litígios' ) . $this->p( 'Em caso de litígio, o consumidor pode recorrer a uma entidade de resolução alternativa de litígios de consumo. [A VALIDAR: entidade competente e ligação para o Livro de Reclamações Eletrónico.]' ),
			),
			'politica-de-privacidade' => array(
				'Política de privacidade',
				$pending .
				$this->h( 'Responsável pelo tratamento' ) . $this->p( esc_html( $b['legal_name'] ) . ', ' . esc_html( $b['street'] ) . ', ' . esc_html( $b['postcode'] . ' ' . $b['locality'] ) . '. Contacto: ' . esc_html( cdf_business_lines( 'emails' )[0] ?? '' ) . '.' ) .
				$this->h( 'Pedidos de orçamento e contactos' ) . $this->p( 'Os dados enviados nos formulários (nome, empresa, email, telefone, NIF se indicado, artigos, observações e o logótipo anexado) são usados para responder ao pedido. Ao contrário do site anterior, os pedidos ficam guardados no sistema da loja e são apagados automaticamente ao fim de ' . (int) cdf_option( 'quote_retention_days', 365 ) . ' dias. O logótipo é guardado fora da área pública do site. [A VALIDAR: prazo de conservação.]' ) .
				$this->h( 'Encomendas e conta de cliente' ) . $this->p( '[A VALIDAR: dados tratados nas encomendas, fundamento (execução do contrato, obrigações fiscais), prazos de conservação e subcontratantes — alojamento, email, pagamentos, transportadoras.]' ) .
				$this->h( 'Direitos' ) . $this->p( 'Pode pedir acesso, retificação ou apagamento dos seus dados através de ' . esc_html( cdf_business_lines( 'emails' )[0] ?? '' ) . '. Tem também o direito de apresentar reclamação à Comissão Nacional de Proteção de Dados (CNPD).' ) .
				$this->h( 'Cookies' ) . $this->p( 'Ver a <a href="' . esc_url( $this->url( 'politica-de-cookies' ) ) . '">política de cookies</a>.' ),
			),
			'politica-de-cookies'     => array(
				'Política de cookies',
				$pending .
				$this->h( 'Cookies essenciais' ) . $this->p( 'O site usa apenas os cookies necessários ao funcionamento da loja: sessão, carrinho, pedido de orçamento e início de sessão na conta de cliente (WooCommerce/WordPress).' ) .
				$this->h( 'Medição e marketing' ) . $this->p( 'O site anterior usava Google Analytics e Meta Pixel. Na nova loja não estão ativos. [A VALIDAR: se forem ativados, só depois do consentimento do visitante, com uma ferramenta de gestão de consentimento.]' ),
			),
		);
		foreach ( $pages as $slug => $page ) {
			$id = $this->upsert( $slug, $page[0], $page[1], array( 'status' => 'pending' ) );
			if ( 'politica-de-privacidade' === $slug ) {
				update_option( 'wp_page_for_privacy_policy', $id );
			}
			if ( 'termos-e-condicoes' === $slug ) {
				update_option( 'woocommerce_terms_page_id', $id );
			}
		}
	}

	// ------------------------------------------------------------ menus

	private function menu( string $name, string $location ): int {
		$menu = wp_get_nav_menu_object( $name );
		if ( $menu ) {
			if ( ! $this->force ) {
				$this->assign( $location, $menu->term_id );
				WP_CLI::log( "  menu mantido: $name" );
				return 0;
			}
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				wp_delete_post( $item->ID, true );
			}
			$id = $menu->term_id;
		} else {
			$id = wp_create_nav_menu( $name );
		}
		$this->assign( $location, $id );
		return $id;
	}

	private function assign( string $location, int $menu_id ) {
		$locations              = get_theme_mod( 'nav_menu_locations', array() );
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	private function add_term( int $menu, string $source_key, int $parent = 0, string $title = '', string $description = '' ): int {
		$term = cdf_term_by_source_key( $source_key );
		if ( ! $term ) {
			return 0;
		}
		return (int) wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-object-id'   => $term->term_id,
				'menu-item-object'      => 'product_cat',
				'menu-item-type'        => 'taxonomy',
				'menu-item-parent-id'   => $parent,
				'menu-item-title'       => $title,
				'menu-item-description' => $description,
				'menu-item-status'      => 'publish',
			)
		);
	}

	private function add_page( int $menu, string $slug, int $parent = 0, string $title = '', string $description = '' ): int {
		$id = $this->ids[ $slug ] ?? 0;
		if ( ! $id ) {
			return 0;
		}
		return (int) wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-object-id'   => $id,
				'menu-item-object'      => 'page',
				'menu-item-type'        => 'post_type',
				'menu-item-parent-id'   => $parent,
				'menu-item-title'       => $title,
				'menu-item-description' => $description,
				'menu-item-status'      => 'publish',
			)
		);
	}

	private function add_link( int $menu, string $title, string $url, int $parent = 0, bool $external = false ): int {
		return (int) wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'     => $title,
				'menu-item-url'       => $url,
				'menu-item-type'      => 'custom',
				'menu-item-parent-id' => $parent,
				'menu-item-target'    => $external ? '_blank' : '',
				'menu-item-status'    => 'publish',
			)
		);
	}

	/**
	 * Adiciona uma categoria e as subcategorias (nível 3).
	 */
	private function add_branch( int $menu, string $source_key, int $parent ) {
		$id   = $this->add_term( $menu, $source_key, $parent );
		$term = cdf_term_by_source_key( $source_key );
		if ( ! $id || ! $term ) {
			return;
		}
		$children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => false, 'menu_order' => 'ASC' ) );
		foreach ( $children as $child ) {
			$key = get_term_meta( $child->term_id, '_cdf_source_key', true );
			if ( $key ) {
				$this->add_term( $menu, $key, $id );
			}
		}
	}

	private function menus() {
		$menu = $this->menu( 'Principal', 'primary' );
		if ( $menu ) {
			$v = $this->add_term( $menu, 'type:1', 0, 'Vestuário profissional', 'Alta visibilidade, hotelaria, saúde, indústria e desporto — com personalização.' );
			foreach ( array( 'cat:2', 'cat:4', 'cat:3', 'cat:1', 'cat:5' ) as $k ) {
				$this->add_branch( $menu, $k, $v );
			}
			$this->add_term( $menu, 'cat:21', $v );
			$b = $this->add_link( $menu, 'Brindes publicitários', cdf_business( 'gifts_url' ), $v, true );

			$c = $this->add_term( $menu, 'cat:11', 0, 'Calçado', 'Botas e sapatos de segurança com biqueira em aço ou compósito.' );
			$this->add_branch( $menu, 'cat:11', $c );
			$this->add_branch( $menu, 'sub:97', $c );

			$e = $this->add_term( $menu, 'type:2', 0, 'Proteção e segurança', 'Luvas, proteção respiratória e auditiva, capacetes, anti-quedas e sinalização.' );
			foreach ( array( 'cat:7', 'cat:10', 'cat:8', 'cat:9', 'cat:6', 'cat:13', 'cat:14', 'cat:15', 'cat:17', 'cat:19', 'cat:20' ) as $k ) {
				$this->add_branch( $menu, $k, $e );
			}
			$o = $this->add_link( $menu, 'Outros', $this->cat( 'type:2' ), $e );
			foreach ( array( 'cat:16', 'cat:12', 'cat:18' ) as $k ) {
				$this->add_term( $menu, $k, $o );
			}

			$s = $this->add_page( $menu, 'setores' );
			foreach ( $this->sector_list() as $sector ) {
				$this->add_page( $menu, $sector['slug'], $s, $sector['short'], $sector['card'] );
			}
			$this->add_page( $menu, 'personalizacao' );
			$this->add_page( $menu, 'contactos' );
		}

		$shop = $this->menu( 'Rodapé — Loja', 'footer_shop' );
		if ( $shop ) {
			$this->add_term( $shop, 'type:1', 0, 'Vestuário profissional' );
			$this->add_term( $shop, 'cat:11', 0, 'Calçado de segurança' );
			$this->add_term( $shop, 'type:2', 0, 'Proteção individual (EPI)' );
			$this->add_term( $shop, 'cat:2' );
			$this->add_term( $shop, 'cat:4' );
			$this->add_term( $shop, 'cat:3' );
			$this->add_link( $shop, 'Brindes publicitários', cdf_business( 'gifts_url' ), 0, true );
		}
		$help = $this->menu( 'Rodapé — Apoio', 'footer_help' );
		if ( $help ) {
			$this->add_page( $help, 'pedido-de-orcamento' );
			$this->add_page( $help, 'personalizacao' );
			$this->add_page( $help, 'entregas' );
			$this->add_page( $help, 'trocas-e-devolucoes' );
			$this->add_page( $help, 'pagamentos' );
			$this->add_page( $help, 'minha-conta', 0, 'Área de cliente' );
			$this->add_page( $help, 'contactos' );
		}
		$company = $this->menu( 'Rodapé — Empresa', 'footer_company' );
		if ( $company ) {
			$this->add_page( $company, 'sobre', 0, 'Sobre nós' );
			$this->add_page( $company, 'setores' );
			$this->add_link( $company, 'Livro de Reclamações', 'https://www.livroreclamacoes.pt/', 0, true );
		}
		$legal = $this->menu( 'Rodapé — Legal', 'footer_legal' );
		if ( $legal ) {
			$this->add_page( $legal, 'termos-e-condicoes' );
			$this->add_page( $legal, 'politica-de-privacidade', 0, 'Privacidade' );
			$this->add_page( $legal, 'politica-de-cookies', 0, 'Cookies' );
		}
	}

	private function settings() {
		update_option( 'woocommerce_checkout_privacy_policy_text', 'Os seus dados pessoais serão usados para processar a encomenda e apoiar a sua experiência neste site, conforme descrito na nossa [privacy_policy].' );
		update_option( 'woocommerce_registration_privacy_policy_text', 'Os seus dados pessoais serão usados para gerir a sua conta, conforme descrito na nossa [privacy_policy].' );
		flush_rewrite_rules( false );
	}
}
