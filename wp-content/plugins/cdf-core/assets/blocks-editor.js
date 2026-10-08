/* Casa das Fardas — controlos dos blocos dinâmicos no editor (sem passo de compilação). */
(function (wp) {
	'use strict';
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;

	function preview(name, props) {
		return el('div', useBlockProps(), el(ServerSideRender, { block: name, attributes: props.attributes }));
	}

	function set(props, key) {
		return function (value) {
			var next = {};
			next[key] = value;
			props.setAttributes(next);
		};
	}

	wp.blocks.registerBlockType('cdf/products', {
		apiVersion: 3,
		title: 'Produtos da loja',
		icon: 'products',
		category: 'widgets',
		edit: function (props) {
			var a = props.attributes;
			return el(Fragment, null,
				el(InspectorControls, null,
					el(PanelBody, { title: 'Seleção' },
						el(TextControl, { label: 'Categoria (slug; vários separados por vírgula)', value: a.category, onChange: set(props, 'category'), help: 'Ex.: hotelaria-jaquetas. Ignorado se indicar referências.' }),
						el(TextControl, { label: 'Referências (SKU) por ordem', value: a.skus, onChange: set(props, 'skus'), help: 'Ex.: 0114080, CANCUN' }),
						el(RangeControl, { label: 'Número de produtos', min: 1, max: 24, value: a.limit, onChange: set(props, 'limit') }),
						el(RangeControl, { label: 'Colunas', min: 2, max: 6, value: a.columns, onChange: set(props, 'columns') }),
						el(SelectControl, { label: 'Ordem', value: a.orderby, onChange: set(props, 'orderby'), options: [
							{ label: 'Ordem do catálogo', value: 'menu_order' },
							{ label: 'Mais recentes', value: 'date' },
							{ label: 'Aleatória', value: 'rand' }
						] })
					)
				),
				preview('cdf/products', props)
			);
		},
		save: function () { return null; }
	});

	wp.blocks.registerBlockType('cdf/categories', {
		apiVersion: 3,
		title: 'Categorias da loja',
		icon: 'category',
		category: 'widgets',
		edit: function (props) {
			var a = props.attributes;
			return el(Fragment, null,
				el(InspectorControls, null,
					el(PanelBody, { title: 'Categorias' },
						el(TextControl, { label: 'Categoria-mãe (slug)', value: a.parent, onChange: set(props, 'parent'), help: 'Mostra as subcategorias. Vazio = categorias principais.' }),
						el(TextControl, { label: 'Ou categorias específicas (slugs, por ordem)', value: a.slugs, onChange: set(props, 'slugs') }),
						el(RangeControl, { label: 'Máximo', min: 1, max: 40, value: a.limit, onChange: set(props, 'limit') }),
						el(RangeControl, { label: 'Colunas', min: 2, max: 6, value: a.columns, onChange: set(props, 'columns') }),
						el(SelectControl, { label: 'Apresentação', value: a.style, onChange: set(props, 'style'), options: [
							{ label: 'Com imagem', value: 'tiles' },
							{ label: 'Lista compacta', value: 'list' }
						] })
					)
				),
				preview('cdf/categories', props)
			);
		},
		save: function () { return null; }
	});

	wp.blocks.registerBlockType('cdf/form', {
		apiVersion: 3,
		title: 'Formulário de orçamento / contacto',
		icon: 'feedback',
		category: 'widgets',
		edit: function (props) {
			return el(Fragment, null,
				el(InspectorControls, null,
					el(PanelBody, { title: 'Formulário' },
						el(SelectControl, { label: 'Tipo', value: props.attributes.mode, onChange: set(props, 'mode'), options: [
							{ label: 'Pedido de orçamento (com lista de artigos e logótipo)', value: 'quote' },
							{ label: 'Contacto geral', value: 'contact' }
						] })
					)
				),
				preview('cdf/form', props)
			);
		},
		save: function () { return null; }
	});

	wp.blocks.registerBlockType('cdf/business-info', {
		apiVersion: 3,
		title: 'Dados de contacto da loja',
		icon: 'location',
		category: 'widgets',
		edit: function (props) {
			return el(Fragment, null,
				el(InspectorControls, null,
					el(PanelBody, { title: 'Mostrar' },
						el(SelectControl, { value: props.attributes.show, onChange: set(props, 'show'), options: [
							{ label: 'Tudo', value: 'all' },
							{ label: 'Morada', value: 'address' },
							{ label: 'Contactos', value: 'contacts' },
							{ label: 'Horário', value: 'hours' }
						] }),
						el('p', { className: 'components-base-control__help' }, 'Os dados editam-se em WooCommerce → Casa das Fardas.')
					)
				),
				preview('cdf/business-info', props)
			);
		},
		save: function () { return null; }
	});
})(window.wp);
