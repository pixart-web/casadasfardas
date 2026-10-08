/* Casa das Fardas — cabeçalho: mega menu, gaveta móvel, sugestões de pesquisa. Sem dependências. */
(function () {
	'use strict';

	var cfg = window.cdfHeader || {};
	var header = document.querySelector('[data-cdf-header]');

	// Sombra ao fazer scroll.
	if (header) {
		var onScroll = function () {
			header.classList.toggle('is-scrolled', window.scrollY > 40);
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	// ---------------------------------------------------------------- mega menu
	var items = Array.prototype.slice.call(document.querySelectorAll('[data-cdf-mega]'));
	var hoverCapable = window.matchMedia('(hover: hover) and (pointer: fine)');

	function closeAll(except) {
		items.forEach(function (item) {
			if (item !== except) {
				setOpen(item, false);
			}
		});
	}

	function setOpen(item, open) {
		var btn = item.querySelector('.cdf-nav__trigger');
		var panel = item.querySelector('.cdf-mega');
		if (!btn || !panel) {
			return;
		}
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		panel.hidden = !open;
	}

	items.forEach(function (item) {
		var btn = item.querySelector('.cdf-nav__trigger');
		var openTimer;
		var closeTimer;
		btn.addEventListener('click', function () {
			var open = btn.getAttribute('aria-expanded') !== 'true';
			closeAll(item);
			setOpen(item, open);
		});
		item.addEventListener('mouseenter', function () {
			if (!hoverCapable.matches) {
				return;
			}
			clearTimeout(closeTimer);
			openTimer = setTimeout(function () {
				closeAll(item);
				setOpen(item, true);
			}, 110);
		});
		item.addEventListener('mouseleave', function () {
			if (!hoverCapable.matches) {
				return;
			}
			clearTimeout(openTimer);
			closeTimer = setTimeout(function () {
				setOpen(item, false);
			}, 180);
		});
		item.addEventListener('focusout', function (event) {
			if (!item.contains(event.relatedTarget)) {
				setOpen(item, false);
			}
		});
		item.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') {
				setOpen(item, false);
				btn.focus();
			}
		});
	});
	document.addEventListener('click', function (event) {
		if (!event.target.closest('[data-cdf-mega]')) {
			closeAll();
		}
	});

	// ---------------------------------------------------------------- gaveta móvel
	var drawer = document.querySelector('[data-cdf-drawer]');
	var opener = document.querySelector('[data-cdf-drawer-open]');
	if (drawer && opener && typeof drawer.showModal === 'function') {
		opener.addEventListener('click', function () {
			drawer.showModal();
			opener.setAttribute('aria-expanded', 'true');
			document.body.classList.add('cdf-noscroll');
		});
		drawer.addEventListener('close', function () {
			opener.setAttribute('aria-expanded', 'false');
			document.body.classList.remove('cdf-noscroll');
			opener.focus();
		});
		drawer.querySelectorAll('[data-cdf-drawer-close]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				drawer.close();
			});
		});
		drawer.addEventListener('click', function (event) {
			if (event.target === drawer) {
				drawer.close();
			}
		});
	}

	// ---------------------------------------------------------------- sugestões de pesquisa
	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function highlight(text, q) {
		var safe = escapeHtml(text);
		var terms = q.trim().split(/\s+/).filter(function (t) { return t.length > 1; }).map(function (t) {
			return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		});
		if (!terms.length) {
			return safe;
		}
		return safe.replace(new RegExp('(' + terms.join('|') + ')', 'gi'), '<mark>$1</mark>');
	}

	document.querySelectorAll('[data-cdf-search]').forEach(function (form) {
		var input = form.querySelector('[role="combobox"]');
		var panel = form.querySelector('[role="listbox"]');
		var status = form.querySelector('[data-cdf-search-status]');
		if (!input || !panel || !cfg.suggestUrl || !window.fetch) {
			return;
		}
		var timer;
		var controller;
		var active = -1;
		var cache = {};

		function options() {
			return Array.prototype.slice.call(panel.querySelectorAll('[role="option"]'));
		}
		function close() {
			panel.hidden = true;
			input.setAttribute('aria-expanded', 'false');
			input.removeAttribute('aria-activedescendant');
			active = -1;
		}
		function setActive(i) {
			var opts = options();
			if (!opts.length) {
				return;
			}
			active = (i + opts.length) % opts.length;
			opts.forEach(function (o, k) {
				o.setAttribute('aria-selected', k === active ? 'true' : 'false');
			});
			input.setAttribute('aria-activedescendant', opts[active].id);
			opts[active].scrollIntoView({ block: 'nearest' });
		}
		function render(data, q) {
			var id = input.id;
			var html = '';
			var n = 0;
			if (data.categories && data.categories.length) {
				html += '<div class="cdf-sugg__group" role="group" aria-label="Categorias"><div class="cdf-sugg__label" aria-hidden="true">Categorias</div>';
				data.categories.forEach(function (c) {
					html += '<a class="cdf-sugg__opt cdf-sugg__opt--cat" role="option" aria-selected="false" id="' + id + '-o' + (n++) + '" href="' + escapeHtml(c.url) + '">' +
						'<span><span class="cdf-sugg__name">' + highlight(c.name, q) + '</span>' + (c.parent ? '<span class="cdf-sugg__meta">' + escapeHtml(c.parent) + '</span>' : '') + '</span>' +
						'<span class="cdf-sugg__meta">' + c.count + (c.count === 1 ? ' artigo' : ' artigos') + '</span></a>';
				});
				html += '</div>';
			}
			if (data.products && data.products.length) {
				html += '<div class="cdf-sugg__group" role="group" aria-label="Artigos"><div class="cdf-sugg__label" aria-hidden="true">Artigos</div>';
				data.products.forEach(function (p) {
					html += '<a class="cdf-sugg__opt" role="option" aria-selected="false" id="' + id + '-o' + (n++) + '" href="' + escapeHtml(p.url) + '">' +
						(p.image ? '<img class="cdf-sugg__img" src="' + escapeHtml(p.image) + '" alt="" width="48" height="48" loading="lazy">' : '<span class="cdf-sugg__img"></span>') +
						'<span><span class="cdf-sugg__name">' + highlight(p.name, q) + '</span><span class="cdf-sugg__meta">' + (p.sku ? 'Ref. ' + highlight(p.sku, q) : '') + (p.category ? (p.sku ? ' · ' : '') + escapeHtml(p.category) : '') + '</span></span></a>';
				});
				html += '</div>';
			}
			if (!n) {
				html = '<p class="cdf-sugg__empty">Sem sugestões para «' + escapeHtml(q) + '». Prima Enter para pesquisar.</p>';
			} else {
				html += '<a class="cdf-sugg__all" href="' + escapeHtml(data.all) + '">Ver todos os resultados para «' + escapeHtml(q) + '» →</a>';
			}
			panel.innerHTML = html;
			panel.hidden = false;
			input.setAttribute('aria-expanded', 'true');
			active = -1;
			if (status) {
				status.textContent = n ? n + ' sugestões disponíveis.' : 'Sem sugestões.';
			}
		}
		function query() {
			var q = input.value.trim();
			if (q.length < 2) {
				close();
				return;
			}
			if (cache[q]) {
				render(cache[q], q);
				return;
			}
			if (controller) {
				controller.abort();
			}
			controller = window.AbortController ? new AbortController() : null;
			fetch(cfg.suggestUrl + (cfg.suggestUrl.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q), { signal: controller ? controller.signal : undefined, credentials: 'omit' })
				.then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
				.then(function (data) {
					cache[q] = data;
					if (input.value.trim() === q) {
						render(data, q);
					}
				})
				.catch(function () { /* a pesquisa normal continua a funcionar */ });
		}

		input.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(query, 180);
		});
		input.addEventListener('focus', function () {
			if (input.value.trim().length >= 2 && panel.innerHTML) {
				panel.hidden = false;
				input.setAttribute('aria-expanded', 'true');
			}
		});
		input.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown') {
				event.preventDefault();
				if (panel.hidden) {
					query();
				} else {
					setActive(active + 1);
				}
			} else if (event.key === 'ArrowUp') {
				event.preventDefault();
				setActive(active - 1);
			} else if (event.key === 'Enter' && active > -1) {
				event.preventDefault();
				options()[active].click();
			} else if (event.key === 'Escape') {
				if (!panel.hidden) {
					event.preventDefault();
					close();
				}
			}
		});
		form.addEventListener('focusout', function (event) {
			if (!form.contains(event.relatedTarget)) {
				close();
			}
		});
	});
})();
