<?php
/**
 * Estilos de bloco e categoria de padrões (os padrões estão em /patterns).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_block_pattern_category( 'casadasfardas', array( 'label' => __( 'Casa das Fardas', 'casadasfardas' ) ) );

		$styles = array(
			'core/button'    => array(
				'cdf-secondary' => __( 'Secundário (contorno)', 'casadasfardas' ),
				'cdf-light'     => __( 'Claro (para fundos escuros)', 'casadasfardas' ),
				'cdf-link'      => __( 'Ligação com seta', 'casadasfardas' ),
			),
			'core/group'     => array(
				'cdf-band-navy'    => __( 'Faixa azul', 'casadasfardas' ),
				'cdf-band-surface' => __( 'Faixa cinza-claro', 'casadasfardas' ),
				'cdf-band-amber'   => __( 'Faixa âmbar suave', 'casadasfardas' ),
				'cdf-card'         => __( 'Cartão', 'casadasfardas' ),
			),
			'core/image'     => array(
				'cdf-packshot' => __( 'Fotografia de produto (fundo neutro)', 'casadasfardas' ),
			),
			'core/list'      => array(
				'cdf-checks' => __( 'Lista com visto', 'casadasfardas' ),
				'cdf-steps'  => __( 'Passos numerados', 'casadasfardas' ),
			),
			'core/paragraph' => array(
				'cdf-eyebrow' => __( 'Antetítulo', 'casadasfardas' ),
				'cdf-lead'    => __( 'Texto de abertura', 'casadasfardas' ),
			),
		);
		foreach ( $styles as $block => $variants ) {
			foreach ( $variants as $name => $label ) {
				register_block_style( $block, array( 'name' => $name, 'label' => $label ) );
			}
		}
	}
);
