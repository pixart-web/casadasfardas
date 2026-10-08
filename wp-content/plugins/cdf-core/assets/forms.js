/* Casa das Fardas — formulários (melhoria progressiva; tudo funciona sem JavaScript). */
(function () {
	'use strict';

	// Foco no resumo de erros ou na confirmação depois de um envio.
	var focusTarget = document.querySelector('[data-cdf-focus]');
	if (focusTarget) {
		focusTarget.focus({ preventScroll: false });
	}

	document.querySelectorAll('[data-cdf-form]').forEach(function (form) {
		var file = form.querySelector('input[type="file"][data-max]');
		if (file) {
			file.addEventListener('change', function () {
				var max = parseInt(file.getAttribute('data-max'), 10);
				var f = file.files && file.files[0];
				var msg = '';
				if (f) {
					var ok = /\.(png|jpe?g|pdf)$/i.test(f.name);
					if (!ok) {
						msg = 'Formato não aceite. Envie o logótipo em PNG, JPG ou PDF.';
					} else if (f.size > max) {
						msg = 'O ficheiro excede o tamanho máximo de 5 MB.';
					}
				}
				file.setCustomValidity(msg);
				var hint = form.querySelector('#hint-logotipo');
				if (hint) {
					hint.classList.toggle('is-error', !!msg);
					hint.textContent = msg || 'PNG, JPG ou PDF até 5 MB. Se tiver o logótipo em vetor (AI, EPS, SVG), indique-o nas observações e pedimos-lho por email.';
				}
			});
		}
		form.addEventListener('submit', function (event) {
			var submitter = event.submitter;
			if (submitter && submitter.name === 'cdf_remove') {
				return;
			}
			if (!form.checkValidity()) {
				event.preventDefault();
				var first = form.querySelector(':invalid');
				if (first) {
					first.focus();
					if (first.reportValidity) {
						first.reportValidity();
					}
				}
				return;
			}
			var button = form.querySelector('[data-cdf-submit]');
			if (button) {
				button.setAttribute('aria-disabled', 'true');
				button.classList.add('is-loading');
				button.dataset.label = button.textContent;
				button.textContent = 'A enviar…';
			}
		});
	});

	// Ficha de produto sob orçamento: cor escolhida, referência e imagem.
	document.querySelectorAll('[data-cdf-swatches]').forEach(function (group) {
		var current = group.querySelector('.cdf-swatches__current');
		var skuEl = document.querySelector('[data-cdf-sku]');
		var baseSku = skuEl ? skuEl.textContent : '';
		function update() {
			var checked = group.querySelector('input:checked');
			if (current) {
				current.textContent = checked ? '— ' + checked.dataset.name : '';
			}
			if (skuEl) {
				skuEl.textContent = checked && checked.dataset.sku ? checked.dataset.sku : baseSku;
			}
			if (checked && checked.dataset.image) {
				document.dispatchEvent(new CustomEvent('cdf:variation-image', { detail: { src: checked.dataset.image, alt: checked.dataset.name } }));
			}
		}
		group.addEventListener('change', update);
		update();
	});
})();
