<?php
/**
 * Estrutura de categorias da nova loja (definida pelo cliente) e regras de reclassificação
 * dos artigos importados da loja antiga.
 *
 * - Cada termo novo guarda a chave `n:{categoria}` ou `n:{categoria}/{subcategoria}` em _cdf_source_key.
 * - As chaves da loja antiga (type:/cat:/sub:) ficam como linhas adicionais de _cdf_source_key no termo
 *   novo equivalente, para que os redirecionamentos 301 das URLs antigas continuem a funcionar.
 * - Um artigo pode pertencer a mais do que uma categoria (ex.: ignífugo de alta visibilidade).
 */

defined( 'ABSPATH' ) || exit;

class CDF_Taxonomy {

	const VERSION = 2;

	/** Categoria de recurso para artigos sem lugar na estrutura nova (a decidir pelo cliente). */
	const OTHER = 'outros-artigos';

	/**
	 * Tipo => [ nome, descrição, [ categoria => [ nome, [ subcategoria => nome ] ] ] ].
	 */
	public static function tree(): array {
		$garments = array(
			't-shirts'            => 'T-shirts',
			'camisas'             => 'Camisas',
			'polos'               => 'Pólos',
			'sweats'              => 'Sweats',
			'malhas-e-polares'    => 'Malhas e polares',
			'fatos-macaco'        => 'Fatos-macaco',
			'blusoes-e-casacos'   => 'Blusões e casacos',
			'calcas-e-calcoes'    => 'Calças e calções',
			'batas'               => 'Batas',
			'impermeaveis'        => 'Impermeáveis',
			'coletes'             => 'Coletes',
			'especiais-ignifugos' => 'Especiais (ignífugos)',
			'acessorios'          => 'Acessórios',
		);
		return array(
			'vestuario'                => array(
				'Vestuário',
				array(
					'alta-visibilidade' => array( 'Alta visibilidade', $garments ),
					'industria'         => array( 'Indústria', $garments ),
					'horeca'            => array(
						'Horeca',
						array(
							'camisas'        => 'Camisas',
							'coletes'        => 'Coletes',
							'calcas-e-saias' => 'Calças e saias',
							'aventais'       => 'Aventais',
							'toalhas'        => 'Toalhas',
							'toucas'         => 'Toucas',
							'acessorios'     => 'Acessórios',
						),
					),
					'servicos-e-saude'  => array(
						'Serviços e saúde',
						array(
							'batas-e-tunicas' => 'Batas e túnicas',
							'calcas'          => 'Calças',
							'conjuntos'       => 'Conjuntos',
							'acessorios'      => 'Acessórios',
						),
					),
					'desporto'          => array(
						'Desporto',
						array(
							't-shirts'   => 'T-shirts',
							'polos'      => 'Pólos',
							'calcas'     => 'Calças',
							'casacos'    => 'Casacos',
							'camisolas'  => 'Camisolas',
							'mochilas'   => 'Mochilas',
							'acessorios' => 'Acessórios',
						),
					),
					'calcado'           => array(
						'Calçado',
						array(
							'sapatos'    => 'Sapatos',
							'botas'      => 'Botas',
							'galochas'   => 'Galochas',
							'sapatilhas' => 'Sapatilhas',
							'alimentar'  => 'Alimentar',
							'socas'      => 'Socas',
						),
					),
				),
			),
			'equipamentos-de-protecao' => array(
				'Equipamentos de proteção',
				array(
					'cabeca'          => array(
						'Cabeça',
						array(
							'capacetes'  => 'Capacetes',
							'bones'      => 'Bonés',
							'acessorios' => 'Acessórios',
						),
					),
					'ocular-e-facial' => array(
						'Ocular e facial',
						array(
							'oculos'             => 'Óculos',
							'oculos-panoramicos' => 'Óculos panorâmicos',
							'soldadura'          => 'Soldadura',
							'acessorios'         => 'Acessórios',
						),
					),
					'respiratoria'    => array(
						'Respiratória',
						array(
							'mascaras-descartaveis' => 'Máscaras descartáveis',
							'mascaras-completas'    => 'Máscaras completas',
							'semi-mascaras'         => 'Semi-máscaras',
							'filtros'               => 'Filtros',
							'acessorios'            => 'Acessórios',
						),
					),
					'auditiva'        => array(
						'Auditiva',
						array(
							'abafadores' => 'Abafadores',
							'tampoes'    => 'Tampões',
							'acessorios' => 'Acessórios',
						),
					),
					'maos'            => array(
						'Mãos',
						array(
							'multiusos'     => 'Multiusos',
							'anti-corte'    => 'Anti-corte',
							'termicas'      => 'Térmicas',
							'texteis'       => 'Têxteis',
							'anti-estaticas' => 'Anti-estáticas',
							'dieletricas'   => 'Dielétricas',
							'pele'          => 'Pele',
							'soldadura'     => 'Soldadura',
							'quimicos'      => 'Químicos',
							'descartaveis'  => 'Descartáveis',
							'alimentar'     => 'Alimentar',
							'manguitos'     => 'Manguitos',
						),
					),
					'corpo'           => array(
						'Corpo',
						array(
							'joelheiras'      => 'Joelheiras',
							'cintas-lombares' => 'Cintas lombares',
						),
					),
					'descartaveis'    => array(
						'Descartáveis',
						array(
							'sapatos'      => 'Sapatos',
							'batas'        => 'Batas',
							'conjuntos'    => 'Conjuntos',
							'fatos-macaco' => 'Fatos-macaco',
							'toucas'       => 'Toucas',
						),
					),
					'anti-queda'      => array(
						'Anti-queda',
						array(
							'arneses'              => 'Arneses',
							'absorvedores'         => 'Absorvedores',
							'linhas-de-vida'       => 'Linhas de vida',
							'mosquetoes-e-ganchos' => 'Mosquetões e ganchos',
							'acessorios'           => 'Acessórios',
						),
					),
				),
			),
			self::OTHER                => array( 'Outros artigos', array() ),
		);
	}

	/**
	 * Lista plana: chave => [ nome, slug, chave do pai ou '', ordem ]. Pais antes dos filhos.
	 */
	public static function terms(): array {
		$out   = array();
		$order = 0;
		foreach ( self::tree() as $type => list( $type_name, $cats ) ) {
			$out[ 'n:' . $type ] = array( $type_name, $type, '', $order++ );
			foreach ( $cats as $cat => list( $cat_name, $subs ) ) {
				$out[ 'n:' . $cat ] = array( $cat_name, $cat, 'n:' . $type, $order++ );
				foreach ( $subs as $sub => $sub_name ) {
					$out[ 'n:' . $cat . '/' . $sub ] = array( $sub_name, $sub . '-' . $cat, 'n:' . $cat, $order++ );
				}
			}
		}
		return $out;
	}

	/**
	 * Categorias novas de um artigo do catálogo (a primeira é a principal).
	 * Regras pela categoria de origem e, quando a origem misturava artigos, pelo nome.
	 */
	public static function map( array $r ): array {
		$k = (string) ( $r['category'] ?? '' );
		$n = strtolower( remove_accents( (string) ( $r['name'] ?? '' ) ) );
		$s = fn( string $re ) => (bool) preg_match( $re, $n );

		$fixed = array(
			// Alta visibilidade.
			'sub:36'  => 'alta-visibilidade/t-shirts',
			'sub:14'  => 'alta-visibilidade/camisas',
			'sub:10'  => 'alta-visibilidade/polos',
			'sub:9'   => 'alta-visibilidade/malhas-e-polares',
			'sub:17'  => 'alta-visibilidade/fatos-macaco',
			'sub:12'  => 'alta-visibilidade/blusoes-e-casacos',
			'sub:13'  => 'alta-visibilidade/calcas-e-calcoes',
			'sub:104' => 'alta-visibilidade/calcas-e-calcoes',
			'sub:11'  => 'alta-visibilidade/batas',
			'sub:7'   => 'alta-visibilidade/impermeaveis',
			'sub:16'  => 'alta-visibilidade/coletes',
			'sub:124' => 'alta-visibilidade/acessorios',
			// Indústria.
			'sub:35'  => 'industria/t-shirts',
			'sub:125' => 'industria/t-shirts',
			'sub:119' => 'industria/t-shirts',
			'sub:4'   => 'industria/camisas',
			'sub:20'  => 'industria/polos',
			'sub:32'  => 'industria/sweats',
			'sub:19'  => 'industria/malhas-e-polares',
			'sub:8'   => 'industria/fatos-macaco',
			'sub:34'  => 'industria/fatos-macaco',
			'sub:2'   => 'industria/blusoes-e-casacos',
			'sub:3'   => 'industria/calcas-e-calcoes',
			'sub:33'  => 'industria/calcas-e-calcoes',
			'sub:117' => 'industria/batas',
			'sub:18'  => 'industria/impermeaveis',
			'sub:6'   => 'industria/coletes',
			'sub:108' => 'industria/acessorios',
			'sub:99'  => 'industria/acessorios',
			// Horeca.
			'sub:105' => 'horeca/camisas',
			'sub:113' => 'horeca/camisas',
			'sub:115' => 'horeca/coletes',
			'sub:111' => 'horeca/calcas-e-saias',
			'sub:116' => 'horeca/calcas-e-saias',
			'sub:112' => 'horeca/aventais',
			'sub:114' => 'horeca/toalhas',
			'sub:109' => 'horeca/toucas',
			'sub:120' => 'horeca/acessorios',
			// Serviços e saúde.
			'sub:24'  => 'servicos-e-saude/batas-e-tunicas',
			'sub:26'  => 'servicos-e-saude/batas-e-tunicas',
			'sub:118' => 'servicos-e-saude/batas-e-tunicas',
			'sub:23'  => 'servicos-e-saude/calcas',
			'sub:27'  => 'servicos-e-saude/acessorios',
			'sub:28'  => 'servicos-e-saude/acessorios',
			'sub:31'  => 'servicos-e-saude/acessorios',
			// Desporto.
			'sub:37'  => 'desporto/t-shirts',
			'sub:93'  => 'desporto/polos',
			'sub:106' => 'desporto/calcas',
			'sub:107' => 'desporto/calcas',
			'sub:123' => 'desporto/calcas',
			'sub:121' => 'desporto/casacos',
			'sub:96'  => 'desporto/mochilas',
			'sub:122' => 'desporto/acessorios',
			// Calçado.
			'sub:68'  => 'calcado/galochas',
			'sub:67'  => 'calcado/alimentar',
			// Cabeça.
			'sub:38'  => 'cabeca/capacetes',
			'sub:39'  => 'cabeca/bones',
			// Ocular e facial.
			'sub:42'  => 'ocular-e-facial/acessorios',
			'sub:43'  => 'ocular-e-facial/acessorios',
			'sub:89'  => 'ocular-e-facial/acessorios',
			'sub:90'  => 'ocular-e-facial/acessorios',
			'cat:12'  => 'ocular-e-facial/soldadura',
			// Respiratória.
			'sub:59'  => 'respiratoria/mascaras-descartaveis',
			'sub:79'  => 'respiratoria/mascaras-descartaveis',
			'sub:61'  => 'respiratoria/mascaras-completas',
			'sub:60'  => 'respiratoria/semi-mascaras',
			'sub:62'  => 'respiratoria/filtros',
			// Auditiva.
			'sub:44'  => 'auditiva/abafadores',
			'sub:45'  => 'auditiva/tampoes',
			'sub:46'  => 'auditiva/acessorios',
			// Mãos.
			'cat:7'   => 'maos/multiusos',
			'sub:56'  => 'maos/multiusos',
			'sub:47'  => 'maos/anti-corte',
			'sub:48'  => 'maos/termicas',
			'sub:50'  => 'maos/termicas',
			'sub:58'  => 'maos/texteis',
			'sub:49'  => 'maos/anti-estaticas',
			'sub:54'  => 'maos/dieletricas',
			'sub:51'  => 'maos/pele',
			'sub:52'  => 'maos/soldadura',
			'sub:57'  => 'maos/quimicos',
			'sub:53'  => 'maos/descartaveis',
			'sub:55'  => 'maos/alimentar',
			// Corpo.
			'sub:91'  => 'corpo/joelheiras',
			'sub:92'  => 'corpo/cintas-lombares',
			// Descartáveis.
			'sub:76'  => 'descartaveis/batas',
			'sub:77'  => 'descartaveis/fatos-macaco',
			'sub:78'  => 'descartaveis/toucas',
			// Anti-queda.
			'sub:71'  => 'anti-queda/arneses',
			'sub:72'  => 'anti-queda/absorvedores',
			'sub:73'  => 'anti-queda/linhas-de-vida',
			'sub:74'  => 'anti-queda/mosquetoes-e-ganchos',
			'sub:70'  => 'anti-queda/acessorios',
			// Sem lugar na estrutura nova: sinalização, cintas de elevação e roquetes, lonas, primeiros socorros.
			'sub:82'  => self::OTHER,
			'sub:83'  => self::OTHER,
			'sub:84'  => self::OTHER,
			'sub:85'  => self::OTHER,
			'sub:86'  => self::OTHER,
			'sub:87'  => self::OTHER,
			'sub:88'  => self::OTHER,
			'cat:18'  => self::OTHER,
			'cat:16'  => self::OTHER,
		);
		if ( isset( $fixed[ $k ] ) ) {
			return array( 'n:' . $fixed[ $k ] );
		}

		switch ( $k ) {
			case 'cat:2': // Alta visibilidade sem subcategoria.
				return array( 'n:alta-visibilidade/' . ( $s( '/^colete/' ) ? 'coletes' : 'blusoes-e-casacos' ) );
			case 'sub:15': // Alta visibilidade > Casacos (inclui polares).
				return array( 'n:alta-visibilidade/' . ( $s( '/polar/' ) ? 'malhas-e-polares' : 'blusoes-e-casacos' ) );
			case 'sub:5': // Indústria > Casacos (inclui polares e jaquetas de malha).
				return array( 'n:industria/' . ( $s( '/polar|ponto (grosso|fino)/' ) ? 'malhas-e-polares' : 'blusoes-e-casacos' ) );
			case 'cat:21': // Vestuário especial ignífugo: também em alta visibilidade quando o é.
				return $s( '/alta visibilidade/' )
					? array( 'n:industria/especiais-ignifugos', 'n:alta-visibilidade/especiais-ignifugos' )
					: array( 'n:industria/especiais-ignifugos' );
			case 'sub:22': // Saúde > Batas (inclui conjuntos túnica + calça).
				return array( 'n:servicos-e-saude/' . ( $s( '/^conjunto/' ) ? 'conjuntos' : 'batas-e-tunicas' ) );
			case 'sub:95': // Fatos de treino: peças separadas.
				return array( 'n:desporto/' . ( $s( '/^calca/' ) ? 'calcas' : 'casacos' ) );
			case 'sub:94': // Futebol.
				if ( $s( '/^t-shirt/' ) ) {
					return array( 'n:desporto/t-shirts' );
				}
				return array( 'n:desporto/' . ( $s( '/^calc/' ) ? 'calcas' : 'camisolas' ) );
			case 'sub:97': // Sapatilhas (inclui sapatos tipo trekking).
				return array( 'n:calcado/' . ( $s( '/trec?king/' ) ? 'sapatos' : 'sapatilhas' ) );
			case 'sub:64':
			case 'sub:65':
			case 'sub:66':
			case 'sub:69':
				if ( $s( '/^soca/' ) ) {
					return array( 'n:calcado/socas' );
				}
				if ( $s( '/^galocha/' ) ) {
					return array( 'n:calcado/galochas' );
				}
				return array( 'n:calcado/' . ( $s( '/^bota/' ) ? 'botas' : 'sapatos' ) );
			case 'sub:41':
				return array( 'n:ocular-e-facial/' . ( $s( '/panor/' ) ? 'oculos-panoramicos' : 'oculos' ) );
			case 'sub:80': // Descartáveis > Diversos.
				if ( $s( '/manguito/' ) ) {
					return array( 'n:maos/manguitos' );
				}
				return array( 'n:descartaveis/' . ( $s( '/sapato/' ) ? 'sapatos' : 'conjuntos' ) );
		}
		return array( 'n:' . self::OTHER );
	}

	/** Chave da categoria (nível 2) de uma chave nova. */
	private static function level2( string $key ): string {
		return explode( '/', $key )[0];
	}

	/**
	 * Equivalência categoria antiga → termo novo, para os redirecionamentos.
	 * Tipos: explícito. Categorias/subcategorias: o destino mais frequente dos seus artigos;
	 * categorias vazias seguem o pai.
	 */
	public static function legacy_map( array $catalog ): array {
		$cats   = array_column( $catalog['categories'], null, 'key' );
		$counts = array();
		foreach ( $catalog['products'] as $p ) {
			$primary = self::map( $p )[0];
			$k       = $p['category'];
			while ( $k && isset( $cats[ $k ] ) ) {
				$target                      = 'subcategoria' === ( $cats[ $k ]['level'] ?? '' ) ? $primary : self::level2( $primary );
				$counts[ $k ][ $target ]     = ( $counts[ $k ][ $target ] ?? 0 ) + 1;
				$k                           = $cats[ $k ]['parent'];
			}
		}
		$map = array(
			'type:1' => 'n:vestuario',
			'type:2' => 'n:equipamentos-de-protecao',
		);
		foreach ( $cats as $key => $c ) { // Pais aparecem antes dos filhos no catálogo.
			if ( isset( $map[ $key ] ) ) {
				continue;
			}
			if ( ! empty( $counts[ $key ] ) ) {
				arsort( $counts[ $key ] );
				$map[ $key ] = array_key_first( $counts[ $key ] );
			} else {
				$map[ $key ] = $map[ $c['parent'] ] ?? 'n:' . self::OTHER;
			}
		}
		return $map;
	}

	/**
	 * Cria/atualiza a estrutura nova, apaga os termos antigos e regista as chaves antigas nos novos.
	 * Devolve chave => term_id.
	 */
	public static function sync( array $catalog, bool $dry = false ): array {
		$ids    = array();
		$report = array( 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0 );
		$terms  = self::terms();

		// 1. Termos antigos (sem chave n:) libertam os slugs antes de criar os novos.
		$old = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'meta_key' => '_cdf_source_key', 'update_term_meta_cache' => false ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$default = (int) get_option( 'default_product_cat' );
		foreach ( is_wp_error( $old ) ? array() : $old as $t ) {
			if ( (int) $t->term_id === $default ) {
				continue;
			}
			$keys = (array) get_term_meta( $t->term_id, '_cdf_source_key', false );
			if ( ! array_filter( $keys, fn( $k ) => str_starts_with( (string) $k, 'n:' ) ) ) {
				if ( ! $dry ) {
					wp_delete_term( $t->term_id, 'product_cat' );
				}
				$report['deleted']++;
			}
		}

		// 2. Estrutura nova.
		foreach ( $terms as $key => list( $name, $slug, $parent_key, $order ) ) {
			$parent   = $parent_key ? (int) ( $ids[ $parent_key ] ?? 0 ) : 0;
			$existing = cdf_term_by_source_key( $key );
			if ( $dry ) {
				$ids[ $key ] = $existing ? $existing->term_id : -1;
				$report[ $existing ? 'unchanged' : 'created' ]++;
				continue;
			}
			if ( $existing ) {
				if ( $existing->name !== $name || (int) $existing->parent !== $parent ) {
					wp_update_term( $existing->term_id, 'product_cat', array( 'name' => $name, 'parent' => $parent ) );
					$report['updated']++;
				} else {
					$report['unchanged']++;
				}
				$id = (int) $existing->term_id;
			} else {
				$res = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug, 'parent' => $parent ) );
				if ( is_wp_error( $res ) ) {
					throw new RuntimeException( "categoria $key: " . $res->get_error_message() );
				}
				$id = (int) $res['term_id'];
				add_term_meta( $id, '_cdf_source_key', $key );
				$report['created']++;
			}
			update_term_meta( $id, 'order', $order );
			$ids[ $key ] = $id;
		}

		// 3. Chaves antigas → termo novo (redirecionamentos e fallbacks de artigos em rascunho).
		if ( ! $dry ) {
			foreach ( self::legacy_map( $catalog ) as $legacy => $key ) {
				$id = $ids[ $key ] ?? 0;
				if ( ! $id ) {
					continue;
				}
				$holder = cdf_term_by_source_key( $legacy );
				if ( $holder && (int) $holder->term_id === $id ) {
					continue;
				}
				if ( $holder ) {
					delete_term_meta( $holder->term_id, '_cdf_source_key', $legacy );
				}
				add_term_meta( $id, '_cdf_source_key', $legacy );
			}
			// Artigos sem categoria caem em «Outros artigos» (em vez do «Uncategorized» do WooCommerce).
			$other = (int) ( $ids[ 'n:' . self::OTHER ] ?? 0 );
			if ( $other && $default !== $other ) {
				$old_default = get_term( $default, 'product_cat' );
				update_option( 'default_product_cat', $other );
				if ( $old_default instanceof WP_Term && ! $old_default->count ) {
					wp_delete_term( $old_default->term_id, 'product_cat' );
				}
			}
			update_option( 'cdf_taxonomy_version', self::VERSION, false );
		}
		return array( $ids, $report );
	}

	/** IDs dos termos de um artigo. */
	public static function term_ids( array $r, array $ids ): array {
		return array_values( array_filter( array_map( fn( $k ) => (int) ( $ids[ $k ] ?? 0 ), self::map( $r ) ), fn( $id ) => $id > 0 ) );
	}
}
