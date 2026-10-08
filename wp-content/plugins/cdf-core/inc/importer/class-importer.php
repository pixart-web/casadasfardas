<?php
/**
 * Importador do catálogo normalizado (catalog/catalog.json) para WooCommerce.
 *
 * - Idempotente: cada objeto guarda a chave de origem (_cdf_source_key) e um hash do registo;
 *   execuções seguintes atualizam só o que mudou e nunca duplicam produtos, termos ou imagens.
 * - Imagens: reutilizadas pela URL de origem (_cdf_source_url) e lidas de ficheiros locais
 *   (audit/media), sem voltar a pedir à loja antiga.
 * - Usa as APIs CRUD do WooCommerce (WC_Product_*), nunca escrita direta em tabelas.
 * - --dry-run valida tudo sem escrever. Erros por artigo não interrompem o lote e ficam no relatório.
 */

defined( 'ABSPATH' ) || exit;

class CDF_Importer {

	const ATTRIBUTES = array(
		'cor'     => 'Cor',
		'tom'     => 'Tom',
		'tamanho' => 'Tamanho',
		'medida'  => 'Medida',
	);

	private array $catalog;
	private bool $dry;
	private bool $force;
	private bool $skip_images;
	private string $media_dir;
	private array $report = array(
		'categories' => array( 'created' => 0, 'updated' => 0, 'unchanged' => 0 ),
		'brands'     => array( 'created' => 0, 'existing' => 0 ),
		'products'   => array( 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0 ),
		'variations' => array( 'created' => 0, 'updated' => 0, 'removed' => 0 ),
		'images'     => array( 'imported' => 0, 'reused' => 0, 'missing' => 0 ),
		'errors'     => array(),
		'warnings'   => array(),
	);
	private array $term_ids = array();

	public function __construct( string $file, array $opts = array() ) {
		if ( ! is_readable( $file ) ) {
			throw new RuntimeException( "Catálogo não encontrado: $file" );
		}
		$this->catalog     = json_decode( (string) file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
		$this->dry         = ! empty( $opts['dry-run'] );
		$this->force       = ! empty( $opts['force'] );
		$this->skip_images = ! empty( $opts['skip-images'] );
		$this->media_dir   = rtrim( $opts['media-dir'] ?? ( ( defined( 'CDF_IMPORT_DIR' ) ? CDF_IMPORT_DIR : ABSPATH ) . '/media' ), '/' );
	}

	public function report(): array {
		return $this->report;
	}

	private function log( string $msg ) {
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::log( $msg );
		}
	}

	private function error( string $context, string $msg ) {
		$this->report['errors'][] = array( 'context' => $context, 'message' => $msg );
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::warning( "$context: $msg" );
		}
	}

	// ------------------------------------------------------------ atributos

	public function ensure_attributes() {
		foreach ( self::ATTRIBUTES as $slug => $label ) {
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
				if ( $this->dry ) {
					$this->log( "[dry-run] criaria atributo $label" );
					continue;
				}
				$id = wc_create_attribute(
					array(
						'name'         => $label,
						'slug'         => $slug,
						'type'         => 'select',
						'order_by'     => 'tamanho' === $slug ? 'menu_order' : 'name',
						'has_archives' => false,
					)
				);
				if ( is_wp_error( $id ) ) {
					throw new RuntimeException( $id->get_error_message() );
				}
			}
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
			}
		}
	}

	// ------------------------------------------------------------ categorias e marcas

	public function import_categories() {
		foreach ( $this->catalog['categories'] as $cat ) {
			$parent_id = $cat['parent'] ? ( $this->term_ids[ $cat['parent'] ] ?? 0 ) : 0;
			$existing  = cdf_term_by_source_key( $cat['key'] );
			$desc      = $cat['title'] ? $cat['title'] . '.' : '';
			if ( $this->dry ) {
				$this->term_ids[ $cat['key'] ] = $existing ? $existing->term_id : -1;
				$this->report['categories'][ $existing ? 'unchanged' : 'created' ]++;
				continue;
			}
			if ( $existing ) {
				$changed = $existing->name !== $cat['name'] || (int) $existing->parent !== (int) $parent_id;
				if ( $changed || $this->force ) {
					wp_update_term( $existing->term_id, 'product_cat', array( 'name' => $cat['name'], 'parent' => $parent_id ) );
					$this->report['categories']['updated']++;
				} else {
					$this->report['categories']['unchanged']++;
				}
				$term_id = $existing->term_id;
			} else {
				$slug = $cat['slug'];
				$res  = wp_insert_term( $cat['name'], 'product_cat', array( 'slug' => $slug, 'parent' => $parent_id, 'description' => $desc ) );
				if ( is_wp_error( $res ) ) {
					$this->error( 'categoria ' . $cat['key'], $res->get_error_message() );
					continue;
				}
				$term_id = (int) $res['term_id'];
				update_term_meta( $term_id, '_cdf_source_key', $cat['key'] );
				if ( $cat['title'] ) {
					update_term_meta( $term_id, 'cdf_intro', $cat['title'] . '.' );
				}
				$this->report['categories']['created']++;
			}
			update_term_meta( $term_id, 'order', (int) $cat['order'] );
			if ( $cat['source_url'] ) {
				update_term_meta( $term_id, '_cdf_source_url', $cat['source_url'] );
			}
			$this->term_ids[ $cat['key'] ] = $term_id;
		}
	}

	private function brand_term_id( string $brand ): int {
		if ( '' === $brand || ! taxonomy_exists( 'product_brand' ) ) {
			return 0;
		}
		$key = 'brand:' . $brand;
		if ( isset( $this->term_ids[ $key ] ) ) {
			return $this->term_ids[ $key ];
		}
		$term = get_term_by( 'name', $brand, 'product_brand' );
		if ( $term ) {
			$this->report['brands']['existing']++;
			return $this->term_ids[ $key ] = (int) $term->term_id;
		}
		if ( $this->dry ) {
			$this->report['brands']['created']++;
			return $this->term_ids[ $key ] = -1;
		}
		$res = wp_insert_term( $brand, 'product_brand', array( 'slug' => sanitize_title( $brand ) ) );
		if ( is_wp_error( $res ) ) {
			$this->error( 'marca ' . $brand, $res->get_error_message() );
			return 0;
		}
		$this->report['brands']['created']++;
		return $this->term_ids[ $key ] = (int) $res['term_id'];
	}

	private function attribute_term( string $attr, string $label, int $order = 0 ): string {
		$taxonomy = wc_attribute_taxonomy_name( $attr );
		$slug     = sanitize_title( $label );
		$cache    = $taxonomy . ':' . $slug;
		if ( isset( $this->term_ids[ $cache ] ) ) {
			return $slug;
		}
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term && ! $this->dry ) {
			$res = wp_insert_term( $label, $taxonomy, array( 'slug' => $slug ) );
			if ( is_wp_error( $res ) ) {
				if ( 'term_exists' === $res->get_error_code() ) {
					$term = get_term( (int) $res->get_error_data(), $taxonomy );
				} else {
					throw new RuntimeException( $res->get_error_message() );
				}
			} else {
				$term = get_term( (int) $res['term_id'], $taxonomy );
			}
			if ( $order && $term ) {
				update_term_meta( $term->term_id, 'order', $order );
			}
		}
		$this->term_ids[ $cache ] = $term ? (int) $term->term_id : -1;
		return $term ? $term->slug : $slug;
	}

	// ------------------------------------------------------------ imagens

	private function attachment_for( string $source_url, int $parent, string $alt ): int {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_cdf_source_url' AND meta_value = %s LIMIT 1", $source_url ) );
		if ( $id ) {
			$this->report['images']['reused']++;
			return $id;
		}
		$file = $this->media_dir . '/products/' . basename( (string) wp_parse_url( $source_url, PHP_URL_PATH ) );
		if ( ! is_readable( $file ) ) {
			$this->report['images']['missing']++;
			$this->report['warnings'][] = "imagem em falta localmente: $source_url";
			return 0;
		}
		if ( $this->dry || $this->skip_images ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( basename( $file ) );
		copy( $file, $tmp );
		$name = sanitize_file_name( sanitize_title( $alt ) . '-' . basename( $file ) );
		$id   = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), $parent, $alt );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			$this->error( 'imagem ' . $source_url, $id->get_error_message() );
			return 0;
		}
		update_post_meta( $id, '_cdf_source_url', $source_url );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		$this->report['images']['imported']++;
		return (int) $id;
	}

	// ------------------------------------------------------------ produtos

	public function import_products( int $offset = 0, int $limit = 0 ) {
		$products = $this->catalog['products'];
		if ( $offset || $limit ) {
			$products = array_slice( $products, $offset, $limit ?: null );
		}
		$total    = count( $products );
		$fallback = get_option( 'cdf_legacy_fallbacks', array() );
		foreach ( $products as $i => $record ) {
			try {
				$this->import_product( $record );
			} catch ( Throwable $e ) {
				$this->report['products']['failed']++;
				$this->error( 'produto ' . $record['source_key'] . ' ' . $record['name'], $e->getMessage() );
			}
			if ( 'draft' === $record['status'] && $record['category'] ) {
				$fallback[ $record['source_key'] ] = $record['category'];
			}
			if ( 0 === ( $i + 1 ) % 25 ) {
				$this->log( sprintf( '  %d/%d produtos', $i + 1, $total ) );
				$this->free_memory();
			}
		}
		if ( ! $this->dry ) {
			update_option( 'cdf_legacy_fallbacks', $fallback, false );
			update_option( 'cdf_legacy_removed', array_column( $this->catalog['removed'] ?? array(), 'source_key' ), false );
		}
	}

	private function free_memory() {
		global $wpdb;
		$wpdb->queries = array();
		wp_cache_flush_runtime();
	}

	private function import_product( array $r ) {
		$hash        = md5( wp_json_encode( $r ) . ( $this->skip_images ? 'noimg' : '' ) );
		$existing_id = cdf_post_by_source_id( $r['source_key'] );
		if ( $existing_id && ! $this->force && get_post_meta( $existing_id, '_cdf_import_hash', true ) === $hash ) {
			$this->report['products']['unchanged']++;
			return;
		}
		$category_id = $r['category'] ? ( $this->term_ids[ $r['category'] ] ?? 0 ) : 0;
		if ( ! $category_id ) {
			$this->report['warnings'][] = "{$r['source_key']} sem categoria";
		}
		if ( $this->dry ) {
			if ( $r['sku'] ) {
				$other = wc_get_product_id_by_sku( $r['sku'] );
				if ( $other && (int) $other !== $existing_id && get_post_meta( $other, '_cdf_source_key', true ) !== $r['source_key'] ) {
					$this->error( 'produto ' . $r['source_key'], "SKU {$r['sku']} já usado pelo produto $other" );
				}
			}
			foreach ( $r['images'] as $u ) {
				$this->attachment_for( $u, 0, $r['name'] );
			}
			$this->report['products'][ $existing_id ? 'updated' : 'created' ]++;
			$this->report['variations']['created'] += count( $r['variations'] );
			return;
		}

		$is_variable = ! empty( $r['variations'] );
		$product     = $existing_id ? wc_get_product( $existing_id ) : null;
		if ( $product && $product->is_type( 'variable' ) !== $is_variable ) {
			// Mudou de tipo na origem: converte mantendo o ID.
			wp_set_object_terms( $existing_id, $is_variable ? 'variable' : 'simple', 'product_type' );
			$product = wc_get_product( $existing_id );
		}
		if ( ! $product ) {
			$product = $is_variable ? new WC_Product_Variable() : new WC_Product_Simple();
		}

		$product->set_name( $r['name'] );
		$product->set_slug( $r['slug'] );
		$product->set_status( $r['status'] );
		$product->set_catalog_visibility( 'visible' );
		$product->set_description( $r['description_html'] );
		$product->set_short_description( $r['short_description'] ? '<p>' . esc_html( $r['short_description'] ) . '</p>' : '' );
		$product->set_menu_order( (int) $r['menu_order'] );
		$product->set_reviews_allowed( false );
		$product->set_category_ids( $category_id > 0 ? array( $category_id ) : array() );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'onbackorder' );
		$product->set_backorders( 'notify' );
		$product->set_tax_status( 'taxable' );
		$product->set_tax_class( '' );
		if ( $r['sku'] ) {
			try {
				$product->set_sku( $r['sku'] );
			} catch ( WC_Data_Exception $e ) {
				$this->report['warnings'][] = "{$r['source_key']}: SKU {$r['sku']} recusado ({$e->getMessage()})";
			}
		}
		if ( ! $is_variable ) {
			$product->set_regular_price( $r['price_ex_vat'] ? (string) $r['price_ex_vat'] : '' );
		}

		// Atributos: tamanhos (escolha validada), família de cor (filtro) e o atributo de variação.
		$attributes = array();
		$position   = 0;
		if ( $r['sizes'] ) {
			$slugs = array();
			foreach ( $r['sizes'] as $k => $size ) {
				$slugs[] = $this->attribute_term( 'tamanho', $size, (int) ( cdf_size_rank( $size ) * 10 ) + $k );
			}
			$attributes[] = $this->make_attribute( 'tamanho', $slugs, $position++, true, false );
		}
		if ( $r['color_families'] ) {
			$slugs = array_map( fn( $f ) => $this->attribute_term( 'tom', $f ), $r['color_families'] );
			$attributes[] = $this->make_attribute( 'tom', $slugs, $position++, false, false );
		}
		$variation_attr = null;
		$variation_map  = array();
		if ( $is_variable ) {
			$variation_attr = $r['variations'][0]['attribute'];
			$seen           = array();
			foreach ( $r['variations'] as $v ) {
				$label = $v['label'];
				$n     = 2;
				while ( isset( $seen[ sanitize_title( $label ) ] ) ) {
					$label = $v['label'] . ' (' . $n++ . ')';
				}
				$slug                               = $this->attribute_term( $variation_attr, $label );
				$seen[ $slug ]                      = true;
				$variation_map[ $v['source_key'] ] = $slug;
			}
			$attributes[] = $this->make_attribute( $variation_attr, array_values( $variation_map ), $position++, true, true );
		}
		$product->set_attributes( $attributes );

		$product_id = $product->save();

		// Imagens (destaque + galeria).
		if ( ! $this->skip_images ) {
			$ids = array();
			foreach ( $r['images'] as $k => $url ) {
				$aid = $this->attachment_for( $url, $product_id, $k ? $r['name'] . ' — vista ' . ( $k + 1 ) : $r['name'] );
				if ( $aid ) {
					$ids[] = $aid;
				}
			}
			$product->set_image_id( $ids[0] ?? 0 );
			$product->set_gallery_image_ids( array_slice( $ids, 1 ) );
		}

		// Marca.
		$brand_id = $this->brand_term_id( $r['brand'] );
		if ( $brand_id > 0 ) {
			wp_set_object_terms( $product_id, array( $brand_id ), 'product_brand' );
		}

		$product->update_meta_data( '_cdf_source_key', $r['source_key'] );
		$product->update_meta_data( '_cdf_source_url', $r['source_url'] );
		$product->update_meta_data( '_cdf_source_code', $r['source_code'] );
		$product->update_meta_data( '_cdf_source_price', null === $r['price_ex_vat'] ? '' : (string) $r['price_ex_vat'] );
		if ( 'validated' !== $product->get_meta( '_cdf_price_status' ) ) {
			$product->update_meta_data( '_cdf_price_status', $r['price_ex_vat'] ? 'source-unvalidated' : 'none' );
		}
		$product->update_meta_data( '_cdf_source_availability', $r['source_availability'] );
		$product->update_meta_data( '_cdf_specs', $r['specs'] );
		$product->update_meta_data( '_cdf_sizes', $r['sizes'] );
		$product->update_meta_data( '_cdf_imported_at', gmdate( 'Y-m-d H:i' ) . ' UTC' );
		$product->update_meta_data( '_cdf_description_raw', $r['description_raw'] );
		$product->save();

		if ( $is_variable ) {
			$this->sync_variations( $product, $r, $variation_attr, $variation_map );
			WC_Product_Variable::sync( $product_id );
		}
		update_post_meta( $product_id, '_cdf_import_hash', $hash );
		$this->report['products'][ $existing_id ? 'updated' : 'created' ]++;
	}

	private function make_attribute( string $attr, array $slugs, int $position, bool $visible, bool $variation ): WC_Product_Attribute {
		$taxonomy = wc_attribute_taxonomy_name( $attr );
		$a        = new WC_Product_Attribute();
		$a->set_id( wc_attribute_taxonomy_id_by_name( $attr ) );
		$a->set_name( $taxonomy );
		$ids = array();
		foreach ( $slugs as $slug ) {
			$t = get_term_by( 'slug', $slug, $taxonomy );
			if ( $t ) {
				$ids[] = (int) $t->term_id;
			}
		}
		$a->set_options( $ids );
		$a->set_position( $position );
		$a->set_visible( $visible );
		$a->set_variation( $variation );
		return $a;
	}

	private function sync_variations( WC_Product_Variable $product, array $r, string $attr, array $map ) {
		$taxonomy = wc_attribute_taxonomy_name( $attr );
		$existing = array();
		foreach ( $product->get_children() as $child_id ) {
			$key = get_post_meta( $child_id, '_cdf_source_key', true );
			if ( $key ) {
				$existing[ $key ] = $child_id;
			}
		}
		foreach ( $r['variations'] as $order => $v ) {
			$vid       = $existing[ $v['source_key'] ] ?? 0;
			$variation = $vid ? wc_get_product( $vid ) : new WC_Product_Variation();
			$variation->set_parent_id( $product->get_id() );
			$variation->set_attributes( array( $taxonomy => $map[ $v['source_key'] ] ) );
			$variation->set_status( 'publish' );
			$variation->set_menu_order( $order );
			$variation->set_manage_stock( false );
			$variation->set_stock_status( 'onbackorder' );
			$variation->set_backorders( 'notify' );
			$variation->set_regular_price( $r['price_ex_vat'] ? (string) $r['price_ex_vat'] : '' );
			if ( $v['sku'] ) {
				try {
					$variation->set_sku( $v['sku'] );
				} catch ( WC_Data_Exception $e ) {
					$this->report['warnings'][] = "{$v['source_key']}: SKU {$v['sku']} recusado";
				}
			}
			if ( ! $this->skip_images && $v['images'] ) {
				$aid = $this->attachment_for( $v['images'][0], $product->get_id(), $r['name'] . ' — ' . $v['label'] );
				if ( $aid ) {
					$variation->set_image_id( $aid );
				}
			}
			$variation->update_meta_data( '_cdf_source_key', $v['source_key'] );
			$variation->update_meta_data( '_cdf_source_url', $v['source_url'] );
			// O preço da origem está só no produto pai: as cores herdam-no (a validar).
			$variation->update_meta_data( '_cdf_price_status', $r['price_ex_vat'] ? 'inherited-unvalidated' : 'none' );
			$variation->save();
			$this->report['variations'][ $vid ? 'updated' : 'created' ]++;
			unset( $existing[ $v['source_key'] ] );
		}
		// Variações que deixaram de existir na origem.
		foreach ( $existing as $child_id ) {
			wp_delete_post( $child_id, true );
			$this->report['variations']['removed']++;
		}
	}

	// ------------------------------------------------------------ fim

	public function finish() {
		if ( $this->dry ) {
			return;
		}
		// A hierarquia de termos fica em cache numa opção; com a invalidação suspensa durante a importação
		// tem de ser reconstruída explicitamente.
		foreach ( array( 'product_cat', 'product_brand', 'pa_cor', 'pa_tom', 'pa_tamanho', 'pa_medida' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				delete_option( $tax . '_children' );
				clean_taxonomy_cache( $tax );
				_get_term_hierarchy( $tax );
			}
		}
		wc_recount_all_terms();
		delete_transient( 'wc_term_counts' );
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%cdf\_%' OR option_name LIKE '\_transient\_timeout\_%cdf\_%'" );
		wc_delete_product_transients();
		if ( function_exists( 'wc_update_product_lookup_tables_is_running' ) && ! wc_update_product_lookup_tables_is_running() ) {
			wc_update_product_lookup_tables();
		}
	}
}
