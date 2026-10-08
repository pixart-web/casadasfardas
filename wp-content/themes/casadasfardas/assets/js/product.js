/* Casa das Fardas — ficha de produto: galeria, zoom, amostras de cor, barra de compra móvel. */
(function () {
	'use strict';

	// ---------------------------------------------------------------- galeria
	var gallery = document.querySelector('[data-cdf-gallery]');
	var main = gallery && gallery.querySelector('.cdf-gallery__main');
	var img = gallery && gallery.querySelector('.cdf-gallery__img');
	var zoomLink = gallery && gallery.querySelector('[data-cdf-zoom]');
	var original = img ? { src: img.src, srcset: img.getAttribute('srcset'), full: zoomLink.getAttribute('href'), alt: img.alt } : null;

	function show(src, srcset, full, alt) {
		if (!img) {
			return;
		}
		main.classList.add('is-swapping');
		var done = function () {
			main.classList.remove('is-swapping');
			img.removeEventListener('load', done);
		};
		img.addEventListener('load', done);
		if (srcset) {
			img.setAttribute('srcset', srcset);
		} else {
			img.removeAttribute('srcset');
		}
		img.src = src;
		if (alt) {
			img.alt = alt;
		}
		zoomLink.setAttribute('href', full || src);
		setTimeout(done, 600);
	}

	if (gallery) {
		gallery.querySelectorAll('.cdf-gallery__thumb').forEach(function (btn) {
			btn.addEventListener('click', function () {
				gallery.querySelectorAll('.cdf-gallery__thumb').forEach(function (b) {
					b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
				});
				show(btn.dataset.src, btn.dataset.srcset, btn.dataset.full);
			});
		});

		// Zoom ao passar o rato (desktop) e ampliação em janela (todos os dispositivos).
		var fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
		if (fine && main) {
			main.addEventListener('mousemove', function (e) {
				var r = main.getBoundingClientRect();
				img.style.setProperty('--zx', ((e.clientX - r.left) / r.width * 100) + '%');
				img.style.setProperty('--zy', ((e.clientY - r.top) / r.height * 100) + '%');
			});
			main.addEventListener('mouseenter', function () { main.classList.add('is-zooming'); });
			main.addEventListener('mouseleave', function () { main.classList.remove('is-zooming'); });
		}
		if (zoomLink && typeof HTMLDialogElement === 'function') {
			var dialog = document.createElement('dialog');
			dialog.className = 'cdf-lightbox';
			dialog.setAttribute('aria-label', 'Imagem ampliada');
			dialog.innerHTML = '<button type="button" class="cdf-iconbtn cdf-lightbox__close" aria-label="Fechar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button><img alt="">';
			document.body.appendChild(dialog);
			dialog.querySelector('button').addEventListener('click', function () { dialog.close(); });
			dialog.addEventListener('click', function (e) { if (e.target === dialog) { dialog.close(); } });
			zoomLink.addEventListener('click', function (e) {
				e.preventDefault();
				var big = dialog.querySelector('img');
				big.src = zoomLink.getAttribute('href');
				big.alt = img.alt;
				dialog.showModal();
			});
		}
	}

	// Imagem da cor escolhida (formulário de orçamento — evento emitido por forms.js).
	document.addEventListener('cdf:variation-image', function (e) {
		if (e.detail && e.detail.src) {
			show(e.detail.src, null, e.detail.src, e.detail.alt);
		}
	});

	// Imagem e referência da variação escolhida no formulário de compra (eventos do WooCommerce).
	if (window.jQuery) {
		var $form = window.jQuery('form.variations_form');
		var sku = document.querySelector('[data-cdf-sku]');
		var baseSku = sku ? sku.textContent : '';
		$form.on('found_variation', function (event, variation) {
			if (variation.image && variation.image.src) {
				show(variation.image.src, variation.image.srcset, variation.image.full_src, variation.image.alt);
			}
			if (sku) {
				sku.textContent = variation.sku || baseSku;
			}
		});
		$form.on('reset_data', function () {
			if (original) {
				show(original.src, original.srcset, original.full, original.alt);
			}
			if (sku) {
				sku.textContent = baseSku;
			}
		});
	}

	// ---------------------------------------------------------------- amostras (cor/medida) sobre o select nativo
	document.querySelectorAll('[data-cdf-swatch-select]').forEach(function (wrap) {
		var select = wrap.querySelector('select');
		if (!select) {
			return;
		}
		var group = document.createElement('div');
		group.className = 'cdf-swatches__list';
		group.setAttribute('role', 'radiogroup');
		var label = document.querySelector('label[for="' + select.id + '"]');
		if (label) {
			group.setAttribute('aria-label', label.textContent.trim());
		}
		function build() {
			group.innerHTML = '';
			Array.prototype.forEach.call(select.options, function (opt) {
				if (!opt.value) {
					return;
				}
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'cdf-swatch-btn';
				b.setAttribute('role', 'radio');
				b.setAttribute('aria-checked', opt.value === select.value ? 'true' : 'false');
				if (opt.disabled) {
					b.setAttribute('aria-disabled', 'true');
				}
				b.textContent = opt.textContent;
				b.dataset.value = opt.value;
				b.tabIndex = (opt.value === select.value || (!select.value && group.children.length === 0)) ? 0 : -1;
				b.addEventListener('click', function () {
					if (b.getAttribute('aria-disabled') === 'true') {
						return;
					}
					select.value = select.value === opt.value ? '' : opt.value;
					select.dispatchEvent(new Event('change', { bubbles: true }));
					if (window.jQuery) {
						window.jQuery(select).trigger('change');
					}
					sync();
				});
				group.appendChild(b);
			});
		}
		function sync() {
			Array.prototype.forEach.call(group.children, function (b) {
				var on = b.dataset.value === select.value;
				b.setAttribute('aria-checked', on ? 'true' : 'false');
				b.tabIndex = on ? 0 : -1;
			});
			if (!select.value && group.firstChild) {
				group.firstChild.tabIndex = 0;
			}
		}
		group.addEventListener('keydown', function (e) {
			var keys = ['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'];
			if (keys.indexOf(e.key) === -1) {
				return;
			}
			e.preventDefault();
			var btns = Array.prototype.slice.call(group.children);
			var i = btns.indexOf(document.activeElement);
			var next = btns[(i + (e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : -1) + btns.length) % btns.length];
			next.focus();
			next.click();
		});
		build();
		wrap.appendChild(group);
		wrap.classList.add('is-enhanced');
		select.setAttribute('tabindex', '-1');
		select.setAttribute('aria-hidden', 'true');
		// O WooCommerce reconstrói as opções quando as combinações mudam.
		new MutationObserver(function () { build(); sync(); }).observe(select, { childList: true, subtree: true, attributes: true });
		select.addEventListener('change', sync);
	});

	// ---------------------------------------------------------------- barra de compra móvel
	var bar = document.querySelector('[data-cdf-buybar]');
	var buy = document.querySelector('[data-cdf-buy]');
	if (bar && buy && 'IntersectionObserver' in window) {
		bar.hidden = false;
		var footer = document.querySelector('.cdf-footer');
		var buyVisible = true;
		var footerVisible = false;
		var update = function () {
			bar.classList.toggle('is-visible', !buyVisible && !footerVisible && window.scrollY > 300);
		};
		new IntersectionObserver(function (entries) {
			buyVisible = entries[0].isIntersecting || entries[0].boundingClientRect.top > window.innerHeight;
			update();
		}).observe(buy);
		if (footer) {
			new IntersectionObserver(function (entries) {
				footerVisible = entries[0].isIntersecting;
				update();
			}).observe(footer);
		}
		window.addEventListener('scroll', update, { passive: true });
	}
	document.querySelectorAll('[data-cdf-scroll-buy]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			if (!buy) {
				return;
			}
			e.preventDefault();
			buy.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
			var first = buy.querySelector('select, input:not([type="hidden"]), button');
			if (first) {
				setTimeout(function () { first.focus({ preventScroll: true }); }, 350);
			}
		});
	});
})();
