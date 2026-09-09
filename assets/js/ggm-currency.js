/**
 * GGM multi-currency browser selection.
 */
(function () {
	'use strict';

	var config = window.ggmCurrency || {};
	if (!config.enabled || !config.currencies) {
		return;
	}

	function enabled(code) {
		return !!(code && config.currencies[String(code).toUpperCase()]);
	}

	function getCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function setCookie(name, value) {
		document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=31536000; SameSite=Lax';
	}

	function queryCurrency() {
		try {
			return new URLSearchParams(window.location.search).get('ggm_currency') || '';
		} catch (e) {
			return '';
		}
	}

	function localeCountry() {
		var langs = navigator.languages && navigator.languages.length ? navigator.languages : [ navigator.language || '' ];
		for (var i = 0; i < langs.length; i++) {
			var parts = String(langs[i]).split('-');
			if (parts.length > 1 && parts[1]) {
				return parts[1].toUpperCase();
			}
		}
		return '';
	}

	function timezoneCurrency() {
		var zone = '';
		try {
			zone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
		} catch (e) {}
		var map = {
			'Asia/Kolkata': 'INR',
			'Asia/Dubai': 'AED',
			'Asia/Riyadh': 'SAR',
			'Asia/Singapore': 'SGD',
			'Europe/London': 'GBP',
			'Australia/Sydney': 'AUD',
			'Pacific/Auckland': 'NZD',
			'Asia/Tokyo': 'JPY'
		};
		if (map[zone]) return map[zone];
		if (zone.indexOf('America/') === 0) return enabled('USD') ? 'USD' : 'CAD';
		if (zone.indexOf('Europe/') === 0) return enabled('EUR') ? 'EUR' : '';
		return '';
	}

	function detectCurrency() {
		var tz = timezoneCurrency();
		if (enabled(tz)) {
			return tz;
		}
		var country = localeCountry();
		if (country && config.country_map && enabled(config.country_map[country])) {
			return config.country_map[country];
		}
		return '';
	}

	function savedCurrency() {
		var value = '';
		try {
			value = window.localStorage.getItem('ggm_currency') || '';
			if (enabled(value)) return value.toUpperCase();
		} catch (e) {}
		value = getCookie('ggm_currency');
		return enabled(value) ? value.toUpperCase() : '';
	}

	function chooseCurrency() {
		var query = queryCurrency();
		if (enabled(query)) return query.toUpperCase();

		var detected = config.auto_detect ? detectCurrency() : '';
		var saved = savedCurrency();
		var fallback = enabled(config.current) ? config.current : config.default;
		if (detected && (!saved || saved === config.base || saved === fallback)) {
			return detected.toUpperCase();
		}

		var selected = saved || detected;
		if (!enabled(selected)) {
			selected = fallback;
		}
		if (!enabled(selected)) {
			selected = config.base;
		}
		return String(selected || config.base).toUpperCase();
	}

	function remember(code) {
		if (!enabled(code)) return;
		window.ggmSelectedCurrency = code;
		setCookie('ggm_currency', code);
		try {
			window.localStorage.setItem('ggm_currency', code);
		} catch (e) {}
	}

	function withCurrency(url, code) {
		try {
			var parsed = new URL(url, window.location.href);
			parsed.searchParams.set('ggm_currency', code);
			return parsed.toString();
		} catch (e) {
			return url;
		}
	}

	function syncLinks(code) {
		document.querySelectorAll('a[href*="checkout"], a[href*="membership-checkout"], a[href*="workshop_id="], a[href*="course_id="]').forEach(function (link) {
			link.href = withCurrency(link.href, code);
		});
		document.querySelectorAll('form').forEach(function (form) {
			if (!form.querySelector('input[name="ggm_currency"]')) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'ggm_currency';
				form.appendChild(input);
			}
			form.querySelector('input[name="ggm_currency"]').value = code;
		});
	}

	function injectSelector(code) {
		var hosts = document.querySelectorAll('.ggm-checkout-heading, .ggm-ws-join-form-full, #ggm-dash .ggm-main, .woocommerce-products-header, .woocommerce div.product, .woocommerce');
		if (!hosts.length || document.querySelector('.ggm-currency-switcher')) {
			return;
		}
		var wrap = document.createElement('label');
		wrap.className = 'ggm-currency-switcher';
		wrap.textContent = config.label || 'Currency';
		var select = document.createElement('select');
		Object.keys(config.currencies).forEach(function (currencyCode) {
			var item = config.currencies[currencyCode];
			var option = document.createElement('option');
			option.value = currencyCode;
			option.textContent = currencyCode + ' - ' + item.label;
			option.selected = currencyCode === code;
			select.appendChild(option);
		});
		wrap.appendChild(select);
		hosts[0].parentNode.insertBefore(wrap, hosts[0].nextSibling);
		select.addEventListener('change', function () {
			remember(select.value);
			window.location.href = withCurrency(window.location.href, select.value);
		});
	}

	var hasQueryCurrency = !!queryCurrency();
	var previousSavedCurrency = savedCurrency();
	var selected = chooseCurrency();
	var shouldRedirect = !hasQueryCurrency && config.auto_detect && selected && selected !== config.current && (!previousSavedCurrency || previousSavedCurrency === config.base);
	remember(selected);

	document.addEventListener('DOMContentLoaded', function () {
		syncLinks(selected);
		injectSelector(selected);
		if (shouldRedirect) {
			window.location.replace(withCurrency(window.location.href, selected));
		}
	});
}());
