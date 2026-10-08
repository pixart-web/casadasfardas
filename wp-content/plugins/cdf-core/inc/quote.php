<?php
/**
 * Pedidos de orçamento e contacto.
 *
 * - Lista de orçamento na sessão (vários artigos, cor, tamanho e quantidade).
 * - Formulário com validação no servidor, proteção anti-spam (honeypot, tempo mínimo, limite por IP, nonce).
 * - Anexo de logótipo: PNG, JPG ou PDF até 5 MB, validado por extensão + MIME real + conteúdo,
 *   guardado fora da raiz pública com nome aleatório e servido só a gestores autenticados.
 * - Registo privado no backoffice (WooCommerce → Pedidos de orçamento) com prazo de conservação.
 * - A confirmação só é mostrada depois de o pedido ficar guardado e a notificação interna ser aceite.
 */

defined( 'ABSPATH' ) || exit;

const CDF_QUOTE_MAX_BYTES = 5 * MB_IN_BYTES;
const CDF_QUOTE_TYPES     = array(
	'png'  => 'image/png',
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'pdf'  => 'application/pdf',
);

// ---------------------------------------------------------------- tipo de conteúdo

add_action( 'init', 'cdf_quote_register_post_type' );

function cdf_quote_register_post_type() {
	register_post_type(
		'cdf_quote',
		array(
			'labels'          => array(
				'name'          => __( 'Pedidos de orçamento', 'cdf-core' ),
				'singular_name' => __( 'Pedido de orçamento', 'cdf-core' ),
				'menu_name'     => __( 'Orçamentos', 'cdf-core' ),
				'edit_item'     => __( 'Pedido', 'cdf-core' ),
				'search_items'  => __( 'Pesquisar pedidos', 'cdf-core' ),
				'not_found'     => __( 'Ainda não há pedidos.', 'cdf-core' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'woocommerce',
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array(
				'create_posts'       => 'do_not_allow',
				'edit_post'          => 'manage_woocommerce',
				'read_post'          => 'manage_woocommerce',
				'delete_post'        => 'manage_woocommerce',
				'edit_posts'         => 'manage_woocommerce',
				'edit_others_posts'  => 'manage_woocommerce',
				'delete_posts'       => 'manage_woocommerce',
				'publish_posts'      => 'manage_woocommerce',
				'read_private_posts' => 'manage_woocommerce',
			),
			'map_meta_cap'    => false,
			'rewrite'         => false,
			'query_var'       => false,
		)
	);
	register_post_status(
		'cdf_new',
		array(
			'label'                     => __( 'Novo', 'cdf-core' ),
			'public'                    => false,
			'internal'                  => false,
			'protected'                 => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: número */
			'label_count'               => _n_noop( 'Novos <span class="count">(%s)</span>', 'Novos <span class="count">(%s)</span>', 'cdf-core' ),
		)
	);
	register_post_status(
		'cdf_answered',
		array(
			'label'                     => __( 'Respondido', 'cdf-core' ),
			'public'                    => false,
			'protected'                 => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: número */
			'label_count'               => _n_noop( 'Respondidos <span class="count">(%s)</span>', 'Respondidos <span class="count">(%s)</span>', 'cdf-core' ),
		)
	);
}

// ---------------------------------------------------------------- lista na sessão

function cdf_quote_session(): ?WC_Session {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return null;
	}
	return WC()->session;
}

function cdf_quote_items(): array {
	$session = cdf_quote_session();
	$items   = $session ? $session->get( 'cdf_quote_items', array() ) : array();
	return is_array( $items ) ? $items : array();
}

function cdf_quote_save_items( array $items ) {
	$session = cdf_quote_session();
	if ( ! $session ) {
		return;
	}
	if ( ! $session->has_session() ) {
		$session->set_customer_session_cookie( true );
	}
	$session->set( 'cdf_quote_items', $items );
}

function cdf_quote_count(): int {
	return count( cdf_quote_items() );
}

function cdf_quote_page_url(): string {
	return cdf_page_url( 'pedido-de-orcamento' ) ?: home_url( '/pedido-de-orcamento/' );
}

/**
 * Normaliza e adiciona um artigo à lista (mesma combinação = soma quantidades).
 */
function cdf_quote_add_item( int $product_id, int $variation_id, string $size, int $qty ): bool {
	$product = wc_get_product( $product_id );
	if ( ! $product || 'publish' !== $product->get_status() || $product->is_type( 'variation' ) ) {
		return false;
	}
	if ( $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation || $variation->get_parent_id() !== $product_id ) {
			$variation_id = 0;
		}
	}
	$sizes = cdf_product_sizes( $product );
	if ( '' !== $size && ! in_array( $size, $sizes, true ) ) {
		$size = '';
	}
	$qty   = max( 1, min( 99999, $qty ) );
	$key   = md5( $product_id . '|' . $variation_id . '|' . $size );
	$items = cdf_quote_items();
	if ( isset( $items[ $key ] ) ) {
		$items[ $key ]['qty'] = min( 99999, $items[ $key ]['qty'] + $qty );
	} else {
		$items[ $key ] = array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'size'         => $size,
			'qty'          => $qty,
		);
	}
	if ( count( $items ) > 60 ) {
		return false;
	}
	cdf_quote_save_items( $items );
	return true;
}

/**
 * Descrição legível de uma linha da lista.
 */
function cdf_quote_item_details( array $item ): array {
	$product = wc_get_product( $item['product_id'] );
	if ( ! $product ) {
		return array();
	}
	$variation = $item['variation_id'] ? wc_get_product( $item['variation_id'] ) : null;
	$color     = '';
	if ( $variation ) {
		$attrs = $variation->get_attributes();
		if ( ! empty( $attrs['pa_cor'] ) ) {
			$term  = get_term_by( 'slug', $attrs['pa_cor'], 'pa_cor' );
			$color = $term ? $term->name : $attrs['pa_cor'];
		}
	}
	return array(
		'name'  => $product->get_name(),
		'sku'   => $variation && $variation->get_sku() ? $variation->get_sku() : $product->get_sku(),
		'color' => $color,
		'size'  => $item['size'],
		'qty'   => (int) $item['qty'],
		'url'   => $product->get_permalink(),
		'image' => $variation && $variation->get_image_id() ? $variation->get_image_id() : $product->get_image_id(),
		'sizes' => cdf_product_sizes( $product ),
	);
}

/**
 * Pedido «adicionar ao orçamento» a partir da ficha de produto (PRG).
 */
add_action(
	'wp_loaded',
	static function () {
		if ( empty( $_POST['cdf_quote_add'] ) ) {
			return;
		}
		if ( ! isset( $_POST['cdf_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cdf_quote_nonce'] ), 'cdf_quote_add' ) ) {
			wc_add_notice( __( 'A sessão expirou. Tente novamente.', 'cdf-core' ), 'error' );
			return;
		}
		$product_id   = absint( $_POST['cdf_quote_add'] );
		$variation_id = absint( $_POST['variation_id'] ?? 0 );
		if ( ! $variation_id && ! empty( $_POST['attribute_pa_cor'] ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_type( 'variable' ) ) {
				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = (int) $data_store->find_matching_product_variation( $product, array( 'attribute_pa_cor' => sanitize_title( wp_unslash( $_POST['attribute_pa_cor'] ) ) ) );
			}
		}
		$size = sanitize_text_field( wp_unslash( $_POST['cdf_size'] ?? '' ) );
		$qty  = absint( $_POST['quantity'] ?? 1 );
		if ( cdf_quote_add_item( $product_id, $variation_id, $size, $qty ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: ligação para a lista */
					__( 'Artigo adicionado ao pedido de orçamento. %s', 'cdf-core' ),
					'<a class="button wc-forward" href="' . esc_url( cdf_quote_page_url() ) . '">' . esc_html__( 'Ver pedido de orçamento', 'cdf-core' ) . '</a>'
				),
				'success'
			);
		} else {
			wc_add_notice( __( 'Não foi possível adicionar este artigo ao pedido de orçamento.', 'cdf-core' ), 'error' );
		}
		$back = wp_get_referer() ?: get_permalink( $product_id );
		wp_safe_redirect( $back );
		exit;
	},
	10
);

/**
 * Formulário de orçamento na ficha de artigos sem compra direta.
 */
add_action(
	'woocommerce_single_product_summary',
	static function () {
		global $product;
		if ( $product instanceof WC_Product && cdf_is_quote_only( $product ) ) {
			cdf_render_quote_add_form( $product, true );
		}
	},
	30
);

/**
 * Botão secundário dentro do formulário de compra (artigos com preço).
 */
add_action(
	'woocommerce_after_add_to_cart_button',
	static function () {
		global $product;
		if ( ! $product instanceof WC_Product || cdf_is_quote_only( $product ) ) {
			return;
		}
		wp_nonce_field( 'cdf_quote_add', 'cdf_quote_nonce' );
		printf(
			'<button type="submit" name="cdf_quote_add" value="%1$d" class="button cdf-btn-secondary cdf-quote-add" formnovalidate>%2$s</button>',
			(int) $product->get_id(),
			esc_html__( 'Adicionar ao orçamento', 'cdf-core' )
		);
	}
);

/**
 * Formulário próprio (cor, tamanho, quantidade) para artigos só por orçamento.
 */
function cdf_render_quote_add_form( WC_Product $product, bool $primary = true ) {
	$colors = array();
	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v || 'publish' !== $v->get_status() ) {
				continue;
			}
			$slug = $v->get_attributes()['pa_cor'] ?? '';
			$term = $slug ? get_term_by( 'slug', $slug, 'pa_cor' ) : null;
			if ( $term ) {
				$colors[] = array(
					'id'    => $vid,
					'name'  => $term->name,
					'slug'  => $slug,
					'sku'   => $v->get_sku(),
					'image' => (int) $v->get_image_id(),
				);
			}
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pré-seleção de cor por URL.
	$preset = isset( $_GET['attribute_pa_cor'] ) ? sanitize_title( wp_unslash( $_GET['attribute_pa_cor'] ) ) : '';
	?>
	<form class="cdf-quote-add-form" method="post" action="<?php echo esc_url( $product->get_permalink() ); ?>">
		<?php wp_nonce_field( 'cdf_quote_add', 'cdf_quote_nonce' ); ?>
		<?php if ( $colors ) : ?>
			<fieldset class="cdf-swatches" data-cdf-swatches>
				<legend><?php esc_html_e( 'Cor', 'cdf-core' ); ?> <span class="cdf-swatches__current" aria-live="polite"></span></legend>
				<div class="cdf-swatches__list">
					<?php foreach ( $colors as $i => $c ) : ?>
						<label class="cdf-swatch">
							<input type="radio" name="variation_id" value="<?php echo esc_attr( $c['id'] ); ?>" data-name="<?php echo esc_attr( $c['name'] ); ?>" data-sku="<?php echo esc_attr( $c['sku'] ); ?>" data-image="<?php echo esc_url( (string) wp_get_attachment_image_url( $c['image'], 'woocommerce_single' ) ); ?>" <?php checked( $preset ? $preset === $c['slug'] : false ); ?>>
							<span class="cdf-swatch__label"><?php echo esc_html( $c['name'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>
		<div class="cdf-quote-add-form__row">
			<?php cdf_render_size_field( $product, 'quote' ); ?>
			<div class="cdf-qty-field">
				<label for="cdf-qty-<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Quantidade', 'cdf-core' ); ?></label>
				<input id="cdf-qty-<?php echo esc_attr( $product->get_id() ); ?>" type="number" name="quantity" min="1" max="99999" step="1" value="1" inputmode="numeric">
			</div>
		</div>
		<button type="submit" name="cdf_quote_add" value="<?php echo esc_attr( $product->get_id() ); ?>" class="button <?php echo $primary ? 'cdf-btn-primary' : 'cdf-btn-secondary'; ?> cdf-quote-add">
			<?php esc_html_e( 'Adicionar ao pedido de orçamento', 'cdf-core' ); ?>
		</button>
		<p class="cdf-quote-add-form__note"><?php esc_html_e( 'Sem compromisso. Indique quantidades e personalização no passo seguinte e respondemos com preço e prazo.', 'cdf-core' ); ?></p>
	</form>
	<?php
}

// ---------------------------------------------------------------- armazenamento privado

function cdf_private_dir(): string {
	$base = defined( 'CDF_PRIVATE_DIR' ) ? CDF_PRIVATE_DIR : WP_CONTENT_DIR . '/uploads/cdf-private';
	$dir  = trailingslashit( $base ) . 'quotes/' . gmdate( 'Y/m' );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	// Proteção adicional caso o diretório fique dentro da raiz pública.
	$root = trailingslashit( $base );
	if ( ! file_exists( $root . '.htaccess' ) ) {
		@file_put_contents( $root . '.htaccess', "Require all denied\nOptions -Indexes\n<FilesMatch \".*\">\n  SetHandler none\n  ForceType application/octet-stream\n</FilesMatch>\n" ); // phpcs:ignore
	}
	if ( ! file_exists( $root . 'index.php' ) ) {
		@file_put_contents( $root . 'index.php', "<?php // Silêncio.\n" ); // phpcs:ignore
	}
	return $dir;
}

/**
 * Valida e guarda o ficheiro enviado.
 *
 * @return array|WP_Error|null null quando não foi enviado ficheiro.
 */
function cdf_quote_handle_upload( string $field ) {
	if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return null;
	}
	$file = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
		return null;
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		$too_big = in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true );
		return new WP_Error( 'upload', $too_big ? __( 'O ficheiro excede o tamanho máximo de 5 MB.', 'cdf-core' ) : __( 'O ficheiro não foi recebido corretamente. Tente novamente.', 'cdf-core' ) );
	}
	if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'upload', __( 'O ficheiro não foi recebido corretamente.', 'cdf-core' ) );
	}
	if ( (int) $file['size'] <= 0 || (int) $file['size'] > CDF_QUOTE_MAX_BYTES ) {
		return new WP_Error( 'upload', __( 'O ficheiro excede o tamanho máximo de 5 MB.', 'cdf-core' ) );
	}
	$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( ! isset( CDF_QUOTE_TYPES[ $ext ] ) ) {
		return new WP_Error( 'upload', __( 'Formato não aceite. Envie o logótipo em PNG, JPG ou PDF.', 'cdf-core' ) );
	}
	$finfo = new finfo( FILEINFO_MIME_TYPE );
	$mime  = (string) $finfo->file( $file['tmp_name'] );
	if ( CDF_QUOTE_TYPES[ $ext ] !== $mime ) {
		return new WP_Error( 'upload', __( 'O conteúdo do ficheiro não corresponde ao formato indicado.', 'cdf-core' ) );
	}
	if ( str_starts_with( $mime, 'image/' ) ) {
		$info = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info || $info[0] < 1 || $info[0] > 12000 || $info[1] > 12000 ) {
			return new WP_Error( 'upload', __( 'A imagem não é válida.', 'cdf-core' ) );
		}
	} else {
		$head = (string) file_get_contents( $file['tmp_name'], false, null, 0, 5 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( '%PDF-' !== $head ) {
			return new WP_Error( 'upload', __( 'O PDF não é válido.', 'cdf-core' ) );
		}
	}
	$name = bin2hex( random_bytes( 16 ) ) . '.' . ( 'jpeg' === $ext ? 'jpg' : $ext );
	$dest = trailingslashit( cdf_private_dir() ) . $name;
	if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
		return new WP_Error( 'upload', __( 'Não foi possível guardar o ficheiro.', 'cdf-core' ) );
	}
	@chmod( $dest, 0640 ); // phpcs:ignore
	return array(
		'path'     => $dest,
		'mime'     => $mime,
		'size'     => (int) $file['size'],
		'original' => sanitize_file_name( (string) $file['name'] ),
	);
}

// ---------------------------------------------------------------- submissão

/**
 * Token anti-bot assinado com a hora de abertura do formulário.
 */
function cdf_form_token(): string {
	$t = (string) time();
	return $t . '.' . hash_hmac( 'sha256', $t, wp_salt( 'nonce' ) );
}

function cdf_form_token_ok( string $token ): bool {
	$parts = explode( '.', $token );
	if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) {
		return false;
	}
	$age = time() - (int) $parts[0];
	return $age >= 3 && $age <= DAY_IN_SECONDS;
}

/** Resultado da submissão no pedido atual (para o bloco do formulário). */
$GLOBALS['cdf_form_state'] = array();

add_action( 'template_redirect', 'cdf_handle_form_post', 20 );

function cdf_handle_form_post() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['cdf_form'] ) ) {
		return;
	}
	$mode = 'contact' === $_POST['cdf_form'] ? 'contact' : 'quote';

	// Remover um artigo da lista sem perder o que foi escrito.
	if ( 'quote' === $mode && ! empty( $_POST['cdf_remove'] ) ) {
		if ( isset( $_POST['cdf_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['cdf_nonce'] ), 'cdf_form_quote' ) ) {
			cdf_quote_sync_items_from_post();
			$items = cdf_quote_items();
			unset( $items[ sanitize_key( $_POST['cdf_remove'] ) ] );
			cdf_quote_save_items( $items );
			cdf_form_save_draft( $mode );
		}
		wp_safe_redirect( cdf_quote_page_url() . '#cdf-quote-items' );
		exit;
	}

	$errors = array();
	$values = cdf_form_collect_values( $mode );

	if ( ! isset( $_POST['cdf_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cdf_nonce'] ), 'cdf_form_' . $mode ) ) {
		$errors['form'] = __( 'A sessão do formulário expirou. Verifique os dados e envie novamente.', 'cdf-core' );
	}
	// Honeypot e tempo mínimo: bots são recusados sem detalhes.
	$bot = ! empty( $_POST['cdf_website'] ) || ! cdf_form_token_ok( sanitize_text_field( wp_unslash( $_POST['cdf_ts'] ?? '' ) ) );
	if ( $bot ) {
		$errors['form'] = __( 'Não foi possível validar o envio. Aguarde uns segundos e tente novamente.', 'cdf-core' );
	}
	if ( ! $errors && ! cdf_rate_limit( 'form_' . $mode, 6, HOUR_IN_SECONDS ) ) {
		$errors['form'] = __( 'Recebemos vários pedidos seguidos a partir desta ligação. Tente mais tarde ou contacte-nos por telefone.', 'cdf-core' );
	}

	if ( '' === $values['nome'] ) {
		$errors['nome'] = __( 'Indique o seu nome.', 'cdf-core' );
	}
	if ( ! is_email( $values['email'] ) ) {
		$errors['email'] = __( 'Indique um email válido.', 'cdf-core' );
	}
	if ( '' !== $values['telefone'] && ! preg_match( '/^\+?[0-9 ()-]{6,20}$/', $values['telefone'] ) ) {
		$errors['telefone'] = __( 'O telefone só pode ter números, espaços e o indicativo.', 'cdf-core' );
	}
	if ( empty( $_POST['cdf_consent'] ) ) {
		$errors['consent'] = __( 'É necessário aceitar o tratamento dos dados para respondermos ao pedido.', 'cdf-core' );
	}

	$items = array();
	if ( 'quote' === $mode ) {
		cdf_quote_sync_items_from_post();
		$items = array_filter( array_map( 'cdf_quote_item_details', cdf_quote_items() ) );
		if ( '' === $values['empresa'] ) {
			$errors['empresa'] = __( 'Indique a empresa ou instituição (ou o seu nome, se for particular).', 'cdf-core' );
		}
		if ( ! $items && '' === $values['artigos'] ) {
			$errors['artigos'] = __( 'Indique os artigos ou referências que pretende, com quantidades.', 'cdf-core' );
		}
		foreach ( $items as $item ) {
			if ( $item['sizes'] && '' === $item['size'] && '' === $values['tamanhos'] ) {
				$errors['items'] = __( 'Escolha o tamanho de cada artigo da lista. Se precisar de vários tamanhos do mesmo artigo, indique a distribuição no campo «Distribuição de tamanhos».', 'cdf-core' );
			}
		}
	} elseif ( '' === $values['mensagem'] ) {
		$errors['mensagem'] = __( 'Escreva a sua mensagem.', 'cdf-core' );
	}

	$upload = null;
	if ( ! $errors && 'quote' === $mode ) {
		$upload = cdf_quote_handle_upload( 'logotipo' );
		if ( is_wp_error( $upload ) ) {
			$errors['logotipo'] = $upload->get_error_message();
			$upload             = null;
		}
	}

	if ( $errors ) {
		$GLOBALS['cdf_form_state'] = array(
			'mode'   => $mode,
			'errors' => $errors,
			'values' => $values,
		);
		status_header( 422 );
		return;
	}

	$ref     = cdf_quote_next_reference( $mode );
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'cdf_quote',
			'post_status' => 'cdf_new',
			'post_title'  => $ref . ' — ' . ( $values['empresa'] ?: $values['nome'] ),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		if ( $upload ) {
			wp_delete_file( $upload['path'] );
		}
		$GLOBALS['cdf_form_state'] = array(
			'mode'   => $mode,
			'errors' => array( 'form' => __( 'Não foi possível registar o pedido. Tente novamente ou contacte-nos por telefone ou email.', 'cdf-core' ) ),
			'values' => $values,
		);
		status_header( 500 );
		return;
	}
	update_post_meta( $post_id, '_cdf_ref', $ref );
	update_post_meta( $post_id, '_cdf_mode', $mode );
	update_post_meta( $post_id, '_cdf_values', $values );
	update_post_meta( $post_id, '_cdf_items', array_values( array_map( static fn( $i ) => array_diff_key( $i, array_flip( array( 'sizes', 'image' ) ) ), $items ) ) );
	update_post_meta( $post_id, '_cdf_email', strtolower( $values['email'] ) );
	if ( $upload ) {
		update_post_meta( $post_id, '_cdf_file', $upload );
	}

	$sent = cdf_quote_notify( $post_id );
	if ( ! $sent ) {
		update_post_meta( $post_id, '_cdf_notify_failed', 1 );
		$GLOBALS['cdf_form_state'] = array(
			'mode'   => $mode,
			'errors' => array(
				'form' => sprintf(
					/* translators: 1: telefone, 2: email */
					__( 'O pedido ficou registado, mas não conseguimos confirmar o envio da notificação à equipa. Para garantir resposta, contacte-nos também pelo %1$s ou %2$s.', 'cdf-core' ),
					cdf_phone_display( cdf_business( 'phone' ) ),
					cdf_business_lines( 'emails' )[0] ?? ''
				),
			),
			'values' => $values,
		);
		status_header( 502 );
		return;
	}
	cdf_quote_confirm_customer( $post_id );

	if ( 'quote' === $mode ) {
		cdf_quote_save_items( array() );
	}
	cdf_form_clear_draft( $mode );
	$token = wp_generate_password( 20, false );
	set_transient( 'cdf_ok_' . $token, array( 'ref' => $ref, 'mode' => $mode ), HOUR_IN_SECONDS );
	$target = remove_query_arg( array( 'enviado' ), wp_get_referer() ?: get_permalink() );
	wp_safe_redirect( add_query_arg( 'enviado', $token, $target ) . '#cdf-form' );
	exit;
}

function cdf_form_collect_values( string $mode ): array {
	$text  = static fn( $k, $len = 200 ) => mb_substr( sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ), 0, $len ); // phpcs:ignore WordPress.Security.NonceVerification
	$area  = static fn( $k, $len = 4000 ) => mb_substr( sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? '' ) ), 0, $len ); // phpcs:ignore WordPress.Security.NonceVerification
	$types = array( 'nenhuma', 'bordado', 'estampagem', 'bordado-estampagem', 'aconselhamento' );
	$depts = array( 'Comercial', 'Técnico', 'Administrativo', 'Gerência' );
	$v     = array(
		'nome'     => $text( 'nome', 120 ),
		'empresa'  => $text( 'empresa', 160 ),
		'email'    => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
		'telefone' => $text( 'telefone', 30 ),
		'nif'      => preg_replace( '/[^0-9A-Za-z]/', '', $text( 'nif', 20 ) ),
	);
	if ( 'quote' === $mode ) {
		$p                   = $text( 'personalizacao', 40 );
		$v['personalizacao'] = in_array( $p, $types, true ) ? $p : 'nenhuma';
		$v['artigos']        = $area( 'artigos' );
		$v['tamanhos']       = $area( 'tamanhos', 2000 );
		$v['prazo']          = $text( 'prazo', 120 );
		$v['observacoes']    = $area( 'observacoes' );
	} else {
		$d             = $text( 'departamento', 40 );
		$v['departamento'] = in_array( $d, $depts, true ) ? $d : 'Comercial';
		$v['assunto']  = $text( 'assunto', 160 );
		$v['mensagem'] = $area( 'mensagem' );
	}
	return $v;
}

/**
 * Atualiza quantidades e tamanhos da lista com os valores enviados no formulário.
 */
function cdf_quote_sync_items_from_post() {
	// phpcs:ignore WordPress.Security.NonceVerification -- verificado pelo chamador.
	$posted = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
	$items  = cdf_quote_items();
	foreach ( $items as $key => &$item ) {
		if ( ! isset( $posted[ $key ] ) || ! is_array( $posted[ $key ] ) ) {
			continue;
		}
		$qty = absint( $posted[ $key ]['qty'] ?? $item['qty'] );
		$item['qty'] = max( 1, min( 99999, $qty ) );
		$size        = sanitize_text_field( $posted[ $key ]['size'] ?? '' );
		$product     = wc_get_product( $item['product_id'] );
		if ( $product && ( '' === $size || in_array( $size, cdf_product_sizes( $product ), true ) ) ) {
			$item['size'] = $size;
		}
	}
	unset( $item );
	cdf_quote_save_items( $items );
}

function cdf_form_save_draft( string $mode ) {
	$session = cdf_quote_session();
	if ( $session ) {
		$session->set( 'cdf_draft_' . $mode, cdf_form_collect_values( $mode ) );
	}
}

function cdf_form_get_draft( string $mode ): array {
	$session = cdf_quote_session();
	$draft   = $session ? $session->get( 'cdf_draft_' . $mode, array() ) : array();
	return is_array( $draft ) ? $draft : array();
}

function cdf_form_clear_draft( string $mode ) {
	$session = cdf_quote_session();
	if ( $session ) {
		$session->set( 'cdf_draft_' . $mode, null );
	}
}

function cdf_quote_next_reference( string $mode ): string {
	$year    = gmdate( 'Y' );
	$key     = 'cdf_ref_counter_' . $year;
	$counter = (int) get_option( $key, 0 ) + 1;
	update_option( $key, $counter, false );
	return ( 'contact' === $mode ? 'CTT' : 'ORC' ) . '-' . $year . '-' . str_pad( (string) $counter, 4, '0', STR_PAD_LEFT );
}

function cdf_personalization_label( string $key ): string {
	$labels = array(
		'nenhuma'            => __( 'Sem personalização', 'cdf-core' ),
		'bordado'            => __( 'Bordado', 'cdf-core' ),
		'estampagem'         => __( 'Estampagem', 'cdf-core' ),
		'bordado-estampagem' => __( 'Bordado e estampagem', 'cdf-core' ),
		'aconselhamento'     => __( 'Ainda não sei — preciso de aconselhamento', 'cdf-core' ),
	);
	return $labels[ $key ] ?? $key;
}

// ---------------------------------------------------------------- emails

function cdf_quote_summary_text( int $post_id ): string {
	$values = (array) get_post_meta( $post_id, '_cdf_values', true );
	$items  = (array) get_post_meta( $post_id, '_cdf_items', true );
	$mode   = get_post_meta( $post_id, '_cdf_mode', true );
	$labels = array(
		'nome'           => 'Nome',
		'empresa'        => 'Empresa',
		'email'          => 'Email',
		'telefone'       => 'Telefone',
		'nif'            => 'NIF',
		'departamento'   => 'Departamento',
		'assunto'        => 'Assunto',
		'mensagem'       => 'Mensagem',
		'personalizacao' => 'Personalização',
		'artigos'        => 'Outros artigos / referências',
		'tamanhos'       => 'Tamanhos e quantidades',
		'prazo'          => 'Prazo pretendido',
		'observacoes'    => 'Observações',
	);
	$lines = array();
	foreach ( $labels as $key => $label ) {
		if ( ! empty( $values[ $key ] ) ) {
			$value   = 'personalizacao' === $key ? cdf_personalization_label( $values[ $key ] ) : $values[ $key ];
			$lines[] = $label . ': ' . $value;
		}
	}
	if ( 'quote' === $mode && $items ) {
		$lines[] = '';
		$lines[] = 'Artigos:';
		foreach ( $items as $item ) {
			$lines[] = sprintf(
				'- %d × %s (Ref. %s)%s%s',
				$item['qty'],
				$item['name'],
				$item['sku'] ?: '—',
				$item['color'] ? ' · Cor: ' . $item['color'] : '',
				$item['size'] ? ' · Tamanho: ' . $item['size'] : ''
			);
		}
	}
	return implode( "\n", $lines );
}

function cdf_quote_notify( int $post_id ): bool {
	$ref     = get_post_meta( $post_id, '_cdf_ref', true );
	$values  = (array) get_post_meta( $post_id, '_cdf_values', true );
	$file    = get_post_meta( $post_id, '_cdf_file', true );
	$mode    = get_post_meta( $post_id, '_cdf_mode', true );
	$subject = sprintf( '[%s] %s — %s', cdf_business( 'brand' ), 'contact' === $mode ? 'Novo contacto' : 'Novo pedido de orçamento', $ref );
	$body    = cdf_quote_summary_text( $post_id ) . "\n\n" .
		'Ver no backoffice: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";
	$headers = array( 'Reply-To: ' . $values['nome'] . ' <' . $values['email'] . '>' );
	$attach  = ( is_array( $file ) && ! empty( $file['path'] ) && file_exists( $file['path'] ) ) ? array( $file['path'] ) : array();
	return (bool) wp_mail( cdf_quote_recipient(), $subject, $body, $headers, $attach );
}

function cdf_quote_confirm_customer( int $post_id ) {
	$ref    = get_post_meta( $post_id, '_cdf_ref', true );
	$values = (array) get_post_meta( $post_id, '_cdf_values', true );
	$mode   = get_post_meta( $post_id, '_cdf_mode', true );
	$brand  = cdf_business( 'brand' );
	$body   = sprintf( "Olá %s,\n\n", $values['nome'] ) .
		( 'contact' === $mode
			? "Recebemos a sua mensagem com a referência $ref. Responderemos para este email.\n\n"
			: "Recebemos o seu pedido de orçamento com a referência $ref. A nossa equipa vai analisá-lo e responder para este email.\n\n" ) .
		"Resumo do que nos enviou:\n\n" . cdf_quote_summary_text( $post_id ) . "\n\n" .
		$brand . "\n" . cdf_business( 'street' ) . ', ' . cdf_business( 'postcode' ) . ' ' . cdf_business( 'locality' ) . "\n" .
		'Tel. ' . cdf_phone_display( cdf_business( 'phone' ) ) . ' (' . cdf_business( 'phone_note' ) . ")\n";
	wp_mail( $values['email'], sprintf( '%s — recebemos o seu pedido (%s)', $brand, $ref ), $body );
}

// ---------------------------------------------------------------- formulário (apresentação)

/**
 * Renderiza o formulário de orçamento ou de contacto.
 */
function cdf_render_form( string $mode = 'quote' ): string {
	$mode  = 'contact' === $mode ? 'contact' : 'quote';
	$state = $GLOBALS['cdf_form_state'] ?? array();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$token = isset( $_GET['enviado'] ) ? sanitize_key( wp_unslash( $_GET['enviado'] ) ) : '';
	$ok    = $token ? get_transient( 'cdf_ok_' . $token ) : false;

	ob_start();
	echo '<div class="cdf-form-wrap" id="cdf-form">';
	if ( $ok && ( $ok['mode'] ?? '' ) === $mode ) {
		?>
		<div class="cdf-form-success" role="status" tabindex="-1" data-cdf-focus>
			<?php echo cdf_icon( 'check', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<h2><?php echo 'contact' === $mode ? esc_html__( 'Mensagem enviada', 'cdf-core' ) : esc_html__( 'Pedido de orçamento enviado', 'cdf-core' ); ?></h2>
			<p><?php printf( /* translators: %s referência */ esc_html__( 'Referência %s. Enviámos uma cópia para o seu email e a nossa equipa vai responder com a maior brevidade possível.', 'cdf-core' ), '<strong>' . esc_html( $ok['ref'] ) . '</strong>' ); ?></p>
			<p><a class="cdf-btn cdf-btn-secondary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Continuar a explorar a loja', 'cdf-core' ); ?></a></p>
		</div>
		<?php
		echo '</div>';
		return (string) ob_get_clean();
	}

	$errors = ( $state['mode'] ?? '' ) === $mode ? ( $state['errors'] ?? array() ) : array();
	$values = ( $state['mode'] ?? '' ) === $mode ? ( $state['values'] ?? array() ) : cdf_form_get_draft( $mode );
	// Pré-preenchimento a partir da conta do cliente.
	if ( ! $values && is_user_logged_in() ) {
		$user   = wp_get_current_user();
		$values = array(
			'nome'    => trim( $user->first_name . ' ' . $user->last_name ),
			'email'   => $user->user_email,
			'empresa' => (string) get_user_meta( $user->ID, 'billing_company', true ),
		);
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'contact' === $mode && empty( $values['assunto'] ) && ! empty( $_GET['assunto'] ) ) {
		$values['assunto'] = sanitize_text_field( wp_unslash( $_GET['assunto'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$v       = static fn( $k ) => (string) ( $values[ $k ] ?? '' );
	$err     = static function ( $k ) use ( $errors ) {
		return isset( $errors[ $k ] ) ? '<p class="cdf-field__error" id="err-' . esc_attr( $k ) . '">' . cdf_icon( 'alert', 16 ) . esc_html( $errors[ $k ] ) . '</p>' : '';
	};
	$invalid = static fn( $k ) => isset( $errors[ $k ] ) ? ' aria-invalid="true" aria-describedby="err-' . esc_attr( $k ) . '"' : '';
	$privacy = get_privacy_policy_url();
	$items   = 'quote' === $mode ? cdf_quote_items() : array();
	?>
	<form class="cdf-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>#cdf-form" enctype="multipart/form-data" novalidate data-cdf-form>
		<?php if ( $errors ) : ?>
			<div class="cdf-form-errors" role="alert" tabindex="-1" data-cdf-focus>
				<h2><?php esc_html_e( 'Não foi possível enviar', 'cdf-core' ); ?></h2>
				<ul>
					<?php foreach ( $errors as $key => $message ) : ?>
						<li><?php echo in_array( $key, array( 'form', 'items' ), true ) ? esc_html( $message ) : '<a href="#f-' . esc_attr( $key ) . '">' . esc_html( $message ) . '</a>'; ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<input type="hidden" name="cdf_form" value="<?php echo esc_attr( $mode ); ?>">
		<input type="hidden" name="cdf_ts" value="<?php echo esc_attr( cdf_form_token() ); ?>">
		<?php wp_nonce_field( 'cdf_form_' . $mode, 'cdf_nonce' ); ?>
		<div class="cdf-hp" aria-hidden="true">
			<label for="cdf_website">Website</label>
			<input type="text" id="cdf_website" name="cdf_website" tabindex="-1" autocomplete="off">
		</div>

		<?php if ( 'quote' === $mode ) : ?>
			<fieldset class="cdf-form__section" id="cdf-quote-items">
				<legend><?php esc_html_e( 'Artigos', 'cdf-core' ); ?></legend>
				<?php if ( $items ) : ?>
					<?php echo $err( 'items' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<ul class="cdf-quote-items">
						<?php
						foreach ( $items as $key => $item ) :
							$d = cdf_quote_item_details( $item );
							if ( ! $d ) {
								continue;
							}
							?>
							<li class="cdf-quote-item">
								<div class="cdf-quote-item__media"><?php echo wp_get_attachment_image( $d['image'], 'thumbnail', false, array( 'alt' => '' ) ); ?></div>
								<div class="cdf-quote-item__info">
									<a href="<?php echo esc_url( $d['url'] ); ?>"><?php echo esc_html( $d['name'] ); ?></a>
									<span class="cdf-quote-item__meta">
										<?php echo esc_html( sprintf( 'Ref. %s', $d['sku'] ?: '—' ) ); ?>
										<?php echo $d['color'] ? ' · ' . esc_html( sprintf( 'Cor: %s', $d['color'] ) ) : ''; ?>
									</span>
								</div>
								<div class="cdf-quote-item__fields">
									<?php if ( $d['sizes'] ) : ?>
										<label><span><?php esc_html_e( 'Tamanho', 'cdf-core' ); ?></span>
											<select name="items[<?php echo esc_attr( $key ); ?>][size]">
												<option value=""><?php esc_html_e( 'Escolher', 'cdf-core' ); ?></option>
												<?php foreach ( $d['sizes'] as $size ) : ?>
													<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $d['size'], $size ); ?>><?php echo esc_html( $size ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
									<?php endif; ?>
									<label><span><?php esc_html_e( 'Qtd.', 'cdf-core' ); ?></span>
										<input type="number" name="items[<?php echo esc_attr( $key ); ?>][qty]" min="1" max="99999" value="<?php echo esc_attr( $d['qty'] ); ?>" inputmode="numeric">
									</label>
									<button type="submit" class="cdf-link-button" name="cdf_remove" value="<?php echo esc_attr( $key ); ?>" formnovalidate aria-label="<?php echo esc_attr( sprintf( __( 'Remover %s', 'cdf-core' ), $d['name'] ) ); ?>"><?php echo cdf_icon( 'x', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Remover', 'cdf-core' ); ?></span></button>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="cdf-form__hint"><?php printf( wp_kses_post( __( 'A sua lista está vazia. Pode <a href="%s">escolher artigos na loja</a> e adicioná-los ao orçamento, ou descrever o que procura abaixo.', 'cdf-core' ) ), esc_url( wc_get_page_permalink( 'shop' ) ) ); ?></p>
				<?php endif; ?>
				<div class="cdf-field" id="f-artigos">
					<label for="cdf-artigos"><?php echo $items ? esc_html__( 'Outros artigos ou referências (opcional)', 'cdf-core' ) : esc_html__( 'Artigos ou referências e quantidades', 'cdf-core' ) . ' <span class="cdf-req">*</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
					<textarea id="cdf-artigos" name="artigos" rows="3" placeholder="<?php esc_attr_e( 'Ex.: 20 pólos azul-marinho, 10 calças de trabalho cinzentas', 'cdf-core' ); ?>"<?php echo $invalid( 'artigos' ); // phpcs:ignore ?>><?php echo esc_textarea( $v( 'artigos' ) ); ?></textarea>
					<?php echo $err( 'artigos' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="cdf-field">
					<label for="cdf-tamanhos"><?php esc_html_e( 'Distribuição de tamanhos (se já souber)', 'cdf-core' ); ?></label>
					<textarea id="cdf-tamanhos" name="tamanhos" rows="2" placeholder="<?php esc_attr_e( 'Ex.: 4 S, 8 M, 6 L, 2 XL', 'cdf-core' ); ?>"><?php echo esc_textarea( $v( 'tamanhos' ) ); ?></textarea>
				</div>
			</fieldset>

			<fieldset class="cdf-form__section">
				<legend><?php esc_html_e( 'Personalização', 'cdf-core' ); ?></legend>
				<div class="cdf-choice-grid" role="radiogroup">
					<?php foreach ( array( 'nenhuma', 'bordado', 'estampagem', 'bordado-estampagem', 'aconselhamento' ) as $opt ) : ?>
						<label class="cdf-choice"><input type="radio" name="personalizacao" value="<?php echo esc_attr( $opt ); ?>" <?php checked( $v( 'personalizacao' ) ?: 'nenhuma', $opt ); ?>><span><?php echo esc_html( cdf_personalization_label( $opt ) ); ?></span></label>
					<?php endforeach; ?>
				</div>
				<div class="cdf-field" id="f-logotipo">
					<label for="cdf-logotipo"><?php esc_html_e( 'Logótipo (opcional)', 'cdf-core' ); ?></label>
					<input type="file" id="cdf-logotipo" name="logotipo" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf" data-max="<?php echo esc_attr( CDF_QUOTE_MAX_BYTES ); ?>"<?php echo $invalid( 'logotipo' ); // phpcs:ignore ?> aria-describedby="hint-logotipo">
					<p class="cdf-field__hint" id="hint-logotipo"><?php esc_html_e( 'PNG, JPG ou PDF até 5 MB. Se tiver o logótipo em vetor (AI, EPS, SVG), indique-o nas observações e pedimos-lho por email.', 'cdf-core' ); ?></p>
					<?php echo $err( 'logotipo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<fieldset class="cdf-form__section">
			<legend><?php esc_html_e( 'Os seus dados', 'cdf-core' ); ?></legend>
			<div class="cdf-form__grid">
				<div class="cdf-field" id="f-nome">
					<label for="cdf-nome"><?php esc_html_e( 'Nome', 'cdf-core' ); ?> <span class="cdf-req">*</span></label>
					<input type="text" id="cdf-nome" name="nome" autocomplete="name" required maxlength="120" value="<?php echo esc_attr( $v( 'nome' ) ); ?>"<?php echo $invalid( 'nome' ); // phpcs:ignore ?>>
					<?php echo $err( 'nome' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="cdf-field" id="f-empresa">
					<label for="cdf-empresa"><?php esc_html_e( 'Empresa ou instituição', 'cdf-core' ); ?><?php echo 'quote' === $mode ? ' <span class="cdf-req">*</span>' : ''; // phpcs:ignore ?></label>
					<input type="text" id="cdf-empresa" name="empresa" autocomplete="organization" maxlength="160" <?php echo 'quote' === $mode ? 'required' : ''; ?> value="<?php echo esc_attr( $v( 'empresa' ) ); ?>"<?php echo $invalid( 'empresa' ); // phpcs:ignore ?>>
					<?php echo $err( 'empresa' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="cdf-field" id="f-email">
					<label for="cdf-email"><?php esc_html_e( 'Email', 'cdf-core' ); ?> <span class="cdf-req">*</span></label>
					<input type="email" id="cdf-email" name="email" autocomplete="email" required maxlength="120" value="<?php echo esc_attr( $v( 'email' ) ); ?>"<?php echo $invalid( 'email' ); // phpcs:ignore ?>>
					<?php echo $err( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="cdf-field" id="f-telefone">
					<label for="cdf-telefone"><?php esc_html_e( 'Telefone (opcional)', 'cdf-core' ); ?></label>
					<input type="tel" id="cdf-telefone" name="telefone" autocomplete="tel" maxlength="30" value="<?php echo esc_attr( $v( 'telefone' ) ); ?>"<?php echo $invalid( 'telefone' ); // phpcs:ignore ?>>
					<?php echo $err( 'telefone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<?php if ( 'quote' === $mode ) : ?>
					<div class="cdf-field">
						<label for="cdf-nif"><?php esc_html_e( 'NIF (opcional)', 'cdf-core' ); ?></label>
						<input type="text" id="cdf-nif" name="nif" inputmode="numeric" maxlength="20" value="<?php echo esc_attr( $v( 'nif' ) ); ?>">
					</div>
					<div class="cdf-field">
						<label for="cdf-prazo"><?php esc_html_e( 'Para quando precisa? (opcional)', 'cdf-core' ); ?></label>
						<input type="text" id="cdf-prazo" name="prazo" maxlength="120" value="<?php echo esc_attr( $v( 'prazo' ) ); ?>">
					</div>
				<?php else : ?>
					<div class="cdf-field">
						<label for="cdf-departamento"><?php esc_html_e( 'Departamento', 'cdf-core' ); ?></label>
						<select id="cdf-departamento" name="departamento">
							<?php foreach ( array( 'Comercial', 'Técnico', 'Administrativo', 'Gerência' ) as $dep ) : ?>
								<option <?php selected( $v( 'departamento' ) ?: 'Comercial', $dep ); ?>><?php echo esc_html( $dep ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="cdf-field">
						<label for="cdf-assunto"><?php esc_html_e( 'Assunto', 'cdf-core' ); ?></label>
						<input type="text" id="cdf-assunto" name="assunto" maxlength="160" value="<?php echo esc_attr( $v( 'assunto' ) ); ?>">
					</div>
				<?php endif; ?>
			</div>
			<?php if ( 'quote' === $mode ) : ?>
				<div class="cdf-field">
					<label for="cdf-observacoes"><?php esc_html_e( 'Observações', 'cdf-core' ); ?></label>
					<textarea id="cdf-observacoes" name="observacoes" rows="4"><?php echo esc_textarea( $v( 'observacoes' ) ); ?></textarea>
				</div>
			<?php else : ?>
				<div class="cdf-field" id="f-mensagem">
					<label for="cdf-mensagem"><?php esc_html_e( 'Mensagem', 'cdf-core' ); ?> <span class="cdf-req">*</span></label>
					<textarea id="cdf-mensagem" name="mensagem" rows="5" required<?php echo $invalid( 'mensagem' ); // phpcs:ignore ?>><?php echo esc_textarea( $v( 'mensagem' ) ); ?></textarea>
					<?php echo $err( 'mensagem' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			<?php endif; ?>
		</fieldset>

		<div class="cdf-field cdf-field--consent" id="f-consent">
			<label class="cdf-check">
				<input type="checkbox" name="cdf_consent" value="1" required<?php echo $invalid( 'consent' ); // phpcs:ignore ?>>
				<span>
					<?php
					$days = (int) cdf_option( 'quote_retention_days', 365 );
					if ( $privacy ) {
						printf( wp_kses_post( __( 'Autorizo o tratamento destes dados para resposta ao pedido. O pedido fica guardado no nosso sistema até %1$d dias. Saiba mais na <a href="%2$s">política de privacidade</a>.', 'cdf-core' ) ), (int) $days, esc_url( $privacy ) );
					} else {
						printf( esc_html__( 'Autorizo o tratamento destes dados para resposta ao pedido. O pedido fica guardado no nosso sistema até %d dias.', 'cdf-core' ), (int) $days );
					}
					?>
				</span>
			</label>
			<?php echo $err( 'consent' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>

		<div class="cdf-form__actions">
			<button type="submit" class="cdf-btn cdf-btn-primary" data-cdf-submit><?php echo 'quote' === $mode ? esc_html__( 'Enviar pedido de orçamento', 'cdf-core' ) : esc_html__( 'Enviar mensagem', 'cdf-core' ); ?></button>
			<p class="cdf-form__hint"><span class="cdf-req">*</span> <?php esc_html_e( 'Campos obrigatórios', 'cdf-core' ); ?></p>
		</div>
	</form>
	<?php
	echo '</div>';
	return (string) ob_get_clean();
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_register_script( 'cdf-forms', CDF_CORE_URL . 'assets/forms.js', array(), CDF_CORE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		if ( is_product() ) {
			wp_enqueue_script( 'cdf-forms' );
		}
	}
);

// ---------------------------------------------------------------- backoffice

add_filter( 'manage_cdf_quote_posts_columns', static function () {
	return array(
		'cb'        => '<input type="checkbox">',
		'title'     => __( 'Pedido', 'cdf-core' ),
		'cdf_type'  => __( 'Tipo', 'cdf-core' ),
		'cdf_email' => __( 'Email', 'cdf-core' ),
		'cdf_items' => __( 'Artigos', 'cdf-core' ),
		'cdf_file'  => __( 'Anexo', 'cdf-core' ),
		'date'      => __( 'Data', 'cdf-core' ),
	);
} );

add_action( 'manage_cdf_quote_posts_custom_column', static function ( string $col, int $post_id ) {
	switch ( $col ) {
		case 'cdf_type':
			echo 'contact' === get_post_meta( $post_id, '_cdf_mode', true ) ? esc_html__( 'Contacto', 'cdf-core' ) : esc_html__( 'Orçamento', 'cdf-core' );
			if ( get_post_meta( $post_id, '_cdf_notify_failed', true ) ) {
				echo '<br><span style="color:#b32d2e">' . esc_html__( 'Notificação falhou', 'cdf-core' ) . '</span>';
			}
			break;
		case 'cdf_email':
			$email = get_post_meta( $post_id, '_cdf_email', true );
			echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
			break;
		case 'cdf_items':
			echo esc_html( (string) count( (array) get_post_meta( $post_id, '_cdf_items', true ) ) );
			break;
		case 'cdf_file':
			echo get_post_meta( $post_id, '_cdf_file', true ) ? '✓' : '—';
			break;
	}
}, 10, 2 );

add_action( 'add_meta_boxes_cdf_quote', static function () {
	remove_meta_box( 'submitdiv', 'cdf_quote', 'side' );
	add_meta_box( 'cdf-quote-details', __( 'Detalhes do pedido', 'cdf-core' ), 'cdf_quote_details_box', 'cdf_quote', 'normal', 'high' );
	add_meta_box( 'cdf-quote-status', __( 'Estado', 'cdf-core' ), 'cdf_quote_status_box', 'cdf_quote', 'side', 'high' );
} );

function cdf_quote_details_box( WP_Post $post ) {
	echo '<pre style="white-space:pre-wrap;font:14px/1.6 -apple-system,system-ui,sans-serif">' . esc_html( cdf_quote_summary_text( $post->ID ) ) . '</pre>';
	$file = get_post_meta( $post->ID, '_cdf_file', true );
	if ( is_array( $file ) && ! empty( $file['path'] ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=cdf_quote_file&id=' . $post->ID ), 'cdf_quote_file_' . $post->ID );
		printf(
			'<p><a class="button" href="%s">%s</a> <span class="description">%s · %s</span></p>',
			esc_url( $url ),
			esc_html__( 'Descarregar anexo', 'cdf-core' ),
			esc_html( $file['original'] ),
			esc_html( size_format( $file['size'] ) )
		);
	}
}

function cdf_quote_status_box( WP_Post $post ) {
	wp_nonce_field( 'cdf_quote_status', 'cdf_quote_status_nonce' );
	?>
	<p><label><input type="radio" name="cdf_status" value="cdf_new" <?php checked( $post->post_status, 'cdf_new' ); ?>> <?php esc_html_e( 'Novo', 'cdf-core' ); ?></label><br>
	<label><input type="radio" name="cdf_status" value="cdf_answered" <?php checked( $post->post_status, 'cdf_answered' ); ?>> <?php esc_html_e( 'Respondido', 'cdf-core' ); ?></label></p>
	<p><button class="button button-primary" type="submit"><?php esc_html_e( 'Guardar', 'cdf-core' ); ?></button></p>
	<p><a class="submitdelete" style="color:#b32d2e" href="<?php echo esc_url( get_delete_post_link( $post->ID, '', true ) ); ?>"><?php esc_html_e( 'Eliminar pedido e anexo', 'cdf-core' ); ?></a></p>
	<?php
}

add_action( 'save_post_cdf_quote', static function ( int $post_id ) {
	if ( ! isset( $_POST['cdf_quote_status_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['cdf_quote_status_nonce'] ), 'cdf_quote_status' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$status = sanitize_key( $_POST['cdf_status'] ?? '' );
	if ( in_array( $status, array( 'cdf_new', 'cdf_answered' ), true ) ) {
		remove_all_actions( 'save_post_cdf_quote' );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ) );
	}
} );

/**
 * Download do anexo apenas para gestores, com verificação de nonce.
 */
add_action( 'admin_post_cdf_quote_file', static function () {
	$id = absint( $_GET['id'] ?? 0 );
	if ( ! $id || ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'cdf_quote_file_' . $id ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'cdf-core' ), 403 );
	}
	$file = get_post_meta( $id, '_cdf_file', true );
	if ( ! is_array( $file ) || empty( $file['path'] ) || ! is_readable( $file['path'] ) ) {
		wp_die( esc_html__( 'Ficheiro não encontrado.', 'cdf-core' ), 404 );
	}
	$ext = pathinfo( $file['path'], PATHINFO_EXTENSION );
	nocache_headers();
	header( 'Content-Type: ' . $file['mime'] );
	header( 'Content-Disposition: attachment; filename="logotipo-' . sanitize_file_name( get_post_meta( $id, '_cdf_ref', true ) ) . '.' . $ext . '"' );
	header( 'Content-Length: ' . filesize( $file['path'] ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $file['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
} );

/**
 * Apagar um pedido apaga também o anexo.
 */
add_action( 'before_delete_post', static function ( int $post_id ) {
	if ( 'cdf_quote' !== get_post_type( $post_id ) ) {
		return;
	}
	$file = get_post_meta( $post_id, '_cdf_file', true );
	if ( is_array( $file ) && ! empty( $file['path'] ) && file_exists( $file['path'] ) ) {
		wp_delete_file( $file['path'] );
	}
} );

/**
 * Conservação: apaga pedidos (e anexos) mais antigos do que o prazo definido.
 */
add_action( 'cdf_daily_maintenance', static function () {
	$days = (int) cdf_option( 'quote_retention_days', 365 );
	$old  = get_posts(
		array(
			'post_type'      => 'cdf_quote',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'date_query'     => array( array( 'before' => $days . ' days ago' ) ),
		)
	);
	foreach ( $old as $id ) {
		wp_delete_post( $id, true );
	}
} );
