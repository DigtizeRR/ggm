(function () {
	'use strict';

	function config() {
		return window.ggmFormPopup || {};
	}

	function text(key, fallback) {
		return config()[key] || fallback;
	}

	function executeScripts(container) {
		Array.prototype.slice.call(container.querySelectorAll('script')).forEach(function (script) {
			var fresh = document.createElement('script');
			Array.prototype.slice.call(script.attributes).forEach(function (attr) {
				fresh.setAttribute(attr.name, attr.value);
			});
			fresh.text = script.text || script.textContent || '';
			script.parentNode.replaceChild(fresh, script);
		});
	}

	function openModal(formId, trigger) {
		var previousFocus = document.activeElement;
		var overlay = document.createElement('div');
		var dialog = document.createElement('div');
		var title = document.createElement('h2');
		var close = document.createElement('button');
		var body = document.createElement('div');
		var labelId = 'ggm-form-popup-title-' + Date.now().toString(36);

		overlay.className = 'ggm-form-popup-modal';
		dialog.className = 'ggm-form-popup-dialog';
		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-labelledby', labelId);
		title.className = 'ggm-form-popup-title';
		title.id = labelId;
		title.textContent = text('label', 'GGM form');
		close.type = 'button';
		close.className = 'ggm-form-popup-close';
		close.setAttribute('aria-label', text('close_label', 'Close form popup'));
		close.innerHTML = '<span aria-hidden="true">&times;</span>';
		body.className = 'ggm-form-popup-body';
		body.innerHTML = '<div class="ggm-form-popup-loading">' + text('loading', 'Loading form...') + '</div>';

		function cleanup() {
			document.removeEventListener('keydown', onKeydown);
			document.body.classList.remove('ggm-form-popup-open');
			overlay.remove();
			if (previousFocus && previousFocus !== document.body && document.contains(previousFocus) && typeof previousFocus.focus === 'function') {
				previousFocus.focus();
			} else if (trigger && typeof trigger.focus === 'function') {
				trigger.focus();
			}
		}

		function onKeydown(event) {
			if ('Escape' === event.key) {
				event.preventDefault();
				cleanup();
				return;
			}
			if ('Tab' !== event.key) {
				return;
			}
			var focusable = Array.prototype.slice.call(dialog.querySelectorAll('a[href], button:not(:disabled), input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])')).filter(function (item) {
				return item.offsetParent !== null;
			});
			if (!focusable.length) {
				event.preventDefault();
				close.focus();
				return;
			}
			var first = focusable[0];
			var last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}

		close.addEventListener('click', cleanup);
		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				cleanup();
			}
		});
		document.addEventListener('keydown', onKeydown);
		dialog.append(title, close, body);
		overlay.appendChild(dialog);
		document.body.classList.add('ggm-form-popup-open');
		document.body.appendChild(overlay);
		window.requestAnimationFrame(function () {
			overlay.classList.add('is-open');
			close.focus();
		});

		var data = new FormData();
		data.set('action', 'ggm_render_popup_form');
		data.set('form_id', formId);
		fetch(config().ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (response) { return response.json(); })
			.then(function (response) {
				if (!response.success) {
					throw new Error((response.data && response.data.message) || text('error', 'Could not load this form.'));
				}
				body.innerHTML = response.data.html || '';
				executeScripts(body);
			})
			.catch(function (error) {
				body.innerHTML = '<div class="ggm-form-popup-error" role="alert">' + error.message + '</div>';
			});
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('a[href^="#ggm-form-popup-"]');
		if (!link) {
			return;
		}
		var match = (link.getAttribute('href') || '').match(/^#ggm-form-popup-(\d+)$/);
		if (!match) {
			return;
		}
		event.preventDefault();
		openModal(match[1], link);
	});
}());
