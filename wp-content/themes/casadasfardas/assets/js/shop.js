/* Casa das Fardas — listagens: painel de filtros em ecrãs pequenos e ordenação automática. */
(function () {
	'use strict';

	var panel = document.querySelector('[data-cdf-filters]');
	var openBtn = document.querySelector('[data-cdf-filters-open]');
	var backdrop;

	function open() {
		panel.classList.add('is-open');
		openBtn.setAttribute('aria-expanded', 'true');
		backdrop = document.createElement('div');
		backdrop.className = 'cdf-filters-backdrop';
		backdrop.addEventListener('click', close);
		document.body.appendChild(backdrop);
		document.body.classList.add('cdf-noscroll');
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-modal', 'true');
		var first = panel.querySelector('summary, a, button');
		if (first) {
			first.focus();
		}
	}
	function close() {
		panel.classList.remove('is-open');
		openBtn.setAttribute('aria-expanded', 'false');
		panel.removeAttribute('role');
		panel.removeAttribute('aria-modal');
		if (backdrop) {
			backdrop.remove();
		}
		document.body.classList.remove('cdf-noscroll');
		openBtn.focus();
	}

	if (panel && openBtn) {
		openBtn.addEventListener('click', open);
		panel.querySelectorAll('[data-cdf-filters-close]').forEach(function (b) {
			b.addEventListener('click', close);
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('is-open')) {
				close();
			}
		});
		// Facetas abertas por omissão no desktop; recolhidas no painel móvel exceto a primeira.
		if (window.matchMedia('(max-width: 1023px)').matches) {
			panel.querySelectorAll('details.cdf-facet').forEach(function (d, i) {
				if (i > 0 && !d.querySelector('.is-selected')) {
					d.open = false;
				}
			});
		}
	}

	var sort = document.querySelector('[data-cdf-sort]');
	if (sort) {
		var select = sort.querySelector('select');
		select.addEventListener('change', function () {
			sort.submit();
		});
	}
})();
