(function () {
	'use strict';

	function positionMenu(picker) {
		var toggle = picker.querySelector('.ggm-country-picker-button');
		var menu = picker._ggmPortaledMenu;
		if (!toggle || !menu) return;

		// Anchor the portaled menu to the complete phone control. Using only the
		// country button can make the menu appear detached from the input when a
		// theme changes the grid/flex sizing of the surrounding form.
		var anchor = picker.closest('.ggm-global-phone-control') || toggle;
		var rect = anchor.getBoundingClientRect();
		var viewportWidth = document.documentElement.clientWidth || window.innerWidth;
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
		var width = Math.min(350, Math.max(260, rect.width), viewportWidth - 32);
		var left = Math.max(16, Math.min(rect.left, viewportWidth - width - 16));
		var availableBelow = viewportHeight - rect.bottom - 16;
		var maxHeight = Math.min(280, Math.max(150, availableBelow));
		var top = rect.bottom + 5;

		if (availableBelow < 180 && rect.top > availableBelow) {
			maxHeight = Math.min(280, Math.max(150, rect.top - 21));
			top = Math.max(16, rect.top - maxHeight - 5);
		}

		// Some globally loaded form styles use !important coordinates for their
		// non-portaled dropdowns. Set portal geometry with the same priority so
		// those generic rules cannot move this menu to the page origin.
		menu.style.setProperty('position', 'fixed', 'important');
		menu.style.setProperty('right', 'auto', 'important');
		menu.style.setProperty('left', left + 'px', 'important');
		menu.style.setProperty('top', top + 'px', 'important');
		menu.style.setProperty('width', width + 'px', 'important');
		menu.style.setProperty('min-width', '0', 'important');
		menu.style.setProperty('max-height', maxHeight + 'px', 'important');
	}

	function restoreMenu(picker) {
		var menu = picker._ggmPortaledMenu;
		if (!menu) return;
		menu.classList.remove('is-portaled');
		menu.removeAttribute('style');
		picker.appendChild(menu);
		picker._ggmPortaledMenu = null;
	}

	function close(picker) {
		if (!picker) return;
		picker.classList.remove('is-open');
		var toggle = picker.querySelector('.ggm-country-picker-button');
		if (toggle) toggle.setAttribute('aria-expanded', 'false');
		restoreMenu(picker);
	}

	function closeAll(except) {
		document.querySelectorAll('.ggm-shared-country-picker.is-open').forEach(function (picker) {
			if (picker !== except) close(picker);
		});
	}

	function open(picker, toggle, menu, search, options) {
		closeAll(picker);
		picker.classList.add('is-open');
		toggle.setAttribute('aria-expanded', 'true');

		// The workshop join form can sit inside Elementor/theme containers that
		// establish a transformed containing block. A position:fixed portal is
		// then measured from that container rather than the viewport and appears
		// at the far-left of the page. Keep this menu in the phone control so its
		// absolute position is always derived from the actual number field.
		var keepAtPhoneField = !!picker.closest('.ggm-ws-join-form-full__phone-control');
		if (!keepAtPhoneField) {
			picker._ggmPortaledMenu = menu;
			menu.classList.add('is-portaled');
			document.body.appendChild(menu);
			positionMenu(picker);
		}
		if (search) {
			search.value = '';
			options.forEach(function (option) { option.hidden = false; });
			window.setTimeout(function () {
				search.focus();
				if (!keepAtPhoneField) positionMenu(picker);
			}, 0);
		}
	}

	function init(picker) {
		if (!picker || picker.dataset.sharedCountryReady === '1') return;
		picker.dataset.sharedCountryReady = '1';

		var toggle = picker.querySelector('.ggm-country-picker-button');
		var hidden = picker.querySelector('input[type="hidden"]');
		var menu = picker.querySelector('.ggm-country-options');
		var search = picker.querySelector('.ggm-country-search');
		var options = [].slice.call(picker.querySelectorAll('.ggm-country-option'));
		if (!toggle || !hidden || !menu) return;

		toggle.addEventListener('click', function () {
			if (picker.classList.contains('is-open')) close(picker);
			else open(picker, toggle, menu, search, options);
		});

		if (search) {
			search.addEventListener('input', function () {
				var query = search.value.toLowerCase().trim();
				options.forEach(function (option) {
					option.hidden = !!query && String(option.dataset.search || option.textContent).toLowerCase().indexOf(query) === -1;
				});
			});
		}

		options.forEach(function (option) {
			option.addEventListener('click', function () {
				hidden.value = option.dataset.code || '+91';
				hidden.dispatchEvent(new Event('change', { bubbles: true }));
				var image = toggle.querySelector('img');
				var text = toggle.querySelector('span');
				if (image && option.dataset.flag) image.src = option.dataset.flag;
				if (text) text.textContent = option.dataset.code || '+91';
				options.forEach(function (item) { item.setAttribute('aria-selected', item === option ? 'true' : 'false'); });
				close(picker);
				toggle.focus();
			});
		});

		picker.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				close(picker);
				toggle.focus();
			}
		});
	}

	function initAll(root) {
		(root || document).querySelectorAll('.ggm-shared-country-picker').forEach(init);
	}

	document.addEventListener('click', function (event) {
		if (!event.target.closest('.ggm-shared-country-picker') && !event.target.closest('.ggm-country-options.is-portaled')) closeAll();
	});
	window.addEventListener('resize', function () {
		document.querySelectorAll('.ggm-shared-country-picker.is-open').forEach(positionMenu);
	}, { passive: true });
	window.addEventListener('scroll', function () {
		document.querySelectorAll('.ggm-shared-country-picker.is-open').forEach(positionMenu);
	}, { passive: true, capture: true });

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { initAll(document); });
	else initAll(document);

	if (window.MutationObserver) {
		new MutationObserver(function (records) {
			records.forEach(function (record) {
				record.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) return;
					if (node.matches && node.matches('.ggm-shared-country-picker')) init(node);
					initAll(node);
				});
			});
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
})();
