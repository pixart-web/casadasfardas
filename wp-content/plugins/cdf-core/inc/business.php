<?php
/**
 * Dados da empresa editáveis no backoffice (WooCommerce → Casa das Fardas).
 *
 * Os valores por omissão vêm exclusivamente da página pública de contactos da loja antiga
 * (consultada a 02/10/2026). Tudo o que não está confirmado fica vazio.
 */

defined( 'ABSPATH' ) || exit;

function cdf_business_defaults(): array {
	return array(
		'brand'         => 'Casa das Fardas',
		'legal_name'    => 'Amazonas Indústria LDA.',
		'nif'           => '',
		'street'        => 'Retail Park Vale de Lousas, EN 125 Vale de Lousas',
		'postcode'      => '8365-306',
		'locality'      => 'Alcantarilha',
		'region'        => 'Faro',
		'country'       => 'PT',
		'phone'         => '964395979',
		'phone_note'    => 'Chamada para rede móvel nacional',
		'emails'        => "info@amazonas.pt\nmarian@amazonas.pt",
		'hours'         => "Segunda a sexta: 09h00–13h00 e 14h00–18h00",
		'hours_spec'    => 'Mo-Fr 09:00-13:00,14:00-18:00',
		'maps_url'      => 'https://www.openstreetmap.org/search?query=Retail%20Park%20Vale%20de%20Lousas%20Alcantarilha',
		'topbar'        => "Produção própria de bordados e estamparia\nFornecemos para todo o país\nLoja em Alcantarilha · Seg–Sex 9h–13h e 14h–18h",
		'gifts_url'     => 'https://casadasfardas.impactogift.com/',
		'quote_to'      => '',
		'facebook'      => '',
		'instagram'     => '',
	);
}

/**
 * Dados da empresa (opção + valores por omissão).
 */
function cdf_business( ?string $key = null ) {
	static $data = null;
	if ( null === $data ) {
		$data = wp_parse_args( (array) get_option( 'cdf_business', array() ), cdf_business_defaults() );
	}
	if ( null === $key ) {
		return $data;
	}
	return $data[ $key ] ?? '';
}

function cdf_business_lines( string $key ): array {
	return array_values( array_filter( array_map( 'trim', explode( "\n", (string) cdf_business( $key ) ) ) ) );
}

function cdf_phone_display( string $phone ): string {
	$digits = preg_replace( '/\D+/', '', $phone );
	return 9 === strlen( $digits ) ? trim( chunk_split( $digits, 3, ' ' ) ) : $phone;
}

function cdf_phone_href( string $phone ): string {
	$digits = preg_replace( '/\D+/', '', $phone );
	return 'tel:' . ( 9 === strlen( $digits ) ? '+351' . $digits : $digits );
}

/**
 * Destinatário dos pedidos de orçamento. Fora de produção nunca é o email real da empresa.
 */
function cdf_quote_recipient(): string {
	$to = trim( (string) cdf_business( 'quote_to' ) );
	if ( 'production' !== wp_get_environment_type() ) {
		return 'orcamentos@casadasfardas.test';
	}
	if ( ! $to || ! is_email( $to ) ) {
		$emails = cdf_business_lines( 'emails' );
		$to     = $emails[0] ?? get_option( 'admin_email' );
	}
	return $to;
}

// ---------------------------------------------------------------- página de definições

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'woocommerce',
			__( 'Casa das Fardas', 'cdf-core' ),
			__( 'Casa das Fardas', 'cdf-core' ),
			'manage_woocommerce',
			'cdf-settings',
			'cdf_render_settings_page'
		);
	},
	60
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'cdf_settings',
			'cdf_business',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'cdf_sanitize_business',
			)
		);
		register_setting(
			'cdf_settings',
			'cdf_price_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => static fn( $v ) => in_array( $v, array( 'shop', 'quote' ), true ) ? $v : 'quote',
			)
		);
		register_setting(
			'cdf_settings',
			'cdf_quote_retention_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $v ) => max( 30, min( 1825, absint( $v ) ) ),
			)
		);
	}
);

function cdf_sanitize_business( $input ): array {
	$input    = is_array( $input ) ? $input : array();
	$out      = array();
	$textarea = array( 'emails', 'hours', 'topbar' );
	$urls     = array( 'maps_url', 'gifts_url', 'facebook', 'instagram' );
	foreach ( cdf_business_defaults() as $key => $default ) {
		$value = $input[ $key ] ?? '';
		if ( in_array( $key, $textarea, true ) ) {
			$out[ $key ] = sanitize_textarea_field( wp_unslash( $value ) );
		} elseif ( in_array( $key, $urls, true ) ) {
			$out[ $key ] = esc_url_raw( wp_unslash( $value ) );
		} elseif ( 'quote_to' === $key ) {
			$out[ $key ] = sanitize_email( wp_unslash( $value ) );
		} else {
			$out[ $key ] = sanitize_text_field( wp_unslash( $value ) );
		}
	}
	return $out;
}

function cdf_render_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$data   = cdf_business();
	$labels = array(
		'brand'      => 'Marca',
		'legal_name' => 'Denominação social',
		'nif'        => 'NIF (opcional — preencher apenas com o valor confirmado)',
		'street'     => 'Morada',
		'postcode'   => 'Código postal',
		'locality'   => 'Localidade',
		'region'     => 'Distrito',
		'country'    => 'País (código ISO)',
		'phone'      => 'Telefone',
		'phone_note' => 'Nota de custo da chamada',
		'emails'     => 'Emails (um por linha)',
		'hours'      => 'Horário (texto)',
		'hours_spec' => 'Horário (formato schema.org, ex.: Mo-Fr 09:00-13:00,14:00-18:00)',
		'maps_url'   => 'Ligação para o mapa',
		'topbar'     => 'Barra informativa — uma vantagem confirmada por linha (máx. 3)',
		'gifts_url'  => 'Catálogo externo de brindes publicitários',
		'quote_to'   => 'Email que recebe os pedidos de orçamento (só usado em produção)',
		'facebook'   => 'Facebook (URL)',
		'instagram'  => 'Instagram (URL)',
	);
	$mode      = cdf_price_mode();
	$retention = (int) cdf_option( 'quote_retention_days', 365 );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Casa das Fardas — definições', 'cdf-core' ); ?></h1>
		<p><?php esc_html_e( 'Estes dados alimentam o cabeçalho, o rodapé, a página de contactos, os dados estruturados e os emails. Altere-os aqui sem mexer no código.', 'cdf-core' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'cdf_settings' ); ?>
			<h2><?php esc_html_e( 'Política de preços', 'cdf-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Modo do catálogo', 'cdf-core' ); ?></th>
					<td>
						<fieldset>
							<label><input type="radio" name="cdf_price_mode" value="quote" <?php checked( $mode, 'quote' ); ?>> <?php esc_html_e( 'Só orçamento — nenhum preço visível, todos os artigos são pedidos por orçamento.', 'cdf-core' ); ?></label><br>
							<label><input type="radio" name="cdf_price_mode" value="shop" <?php checked( $mode, 'shop' ); ?>> <?php esc_html_e( 'Loja — artigos com preço podem ser comprados; artigos sem preço ficam sob orçamento.', 'cdf-core' ); ?></label>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Os preços importados da loja antiga não foram validados. Antes de ativar o modo Loja em produção, confirme preços, IVA e disponibilidade.', 'cdf-core' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cdf_quote_retention_days"><?php esc_html_e( 'Conservação dos pedidos de orçamento (dias)', 'cdf-core' ); ?></label></th>
					<td><input type="number" min="30" max="1825" id="cdf_quote_retention_days" name="cdf_quote_retention_days" value="<?php echo esc_attr( $retention ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Pedidos e anexos mais antigos são apagados automaticamente. A política de privacidade deve indicar o mesmo prazo.', 'cdf-core' ); ?></p></td>
				</tr>
			</table>
			<h2><?php esc_html_e( 'Empresa e contactos', 'cdf-core' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( $labels as $key => $label ) : ?>
					<tr>
						<th scope="row"><label for="cdf_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<?php if ( in_array( $key, array( 'emails', 'hours', 'topbar' ), true ) ) : ?>
								<textarea class="large-text" rows="3" id="cdf_<?php echo esc_attr( $key ); ?>" name="cdf_business[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $data[ $key ] ); ?></textarea>
							<?php else : ?>
								<input class="regular-text" type="text" id="cdf_<?php echo esc_attr( $key ); ?>" name="cdf_business[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $data[ $key ] ); ?>">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
