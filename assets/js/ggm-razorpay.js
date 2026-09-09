/**
 * GGM Razorpay Checkout — coupon apply, order creation, then opens Razorpay payment modal.
 */
/* global Razorpay, ggmCheckout, jQuery */
(function ($) {
	'use strict';

	var appliedCoupon = '';
	var freshNonce    = null;
	var accountCredit = 0;

	function init() {
		refreshNonce();
		$('#ggm-pay-btn').on('click', handlePay);
		$('#ggm-apply-coupon-btn').on('click', handleApplyCoupon);

		// A WordPress nonce is tied to the current user ID — a nonce fetched
		// above while a brand-new visitor was still a guest becomes invalid
		// the instant they verify their phone and get logged in mid-page (the
		// checkout template's own script fires this event right after that
		// happens). Re-fetch so Pay Now uses a nonce that matches who they
		// are *now*, not who they were when the page first loaded.
		$(document).on('ggm:contact-verified', refreshNonce);
	}

	// Fetch a nonce generated right now, for whoever is actually viewing this
	// page — server-side, from their real cookies. A page-cache plugin can
	// serve the exact same checkout HTML (with a nonce baked in from
	// whoever's session it was generated for) to every visitor; this AJAX
	// call is never itself cached, so it always reflects the real visitor.
	function refreshNonce() {
		$.post(ggmCheckout.ajaxurl, { action: 'ggm_get_checkout_nonce', ggm_currency: selectedCurrency() }).done(function (res) {
			if (!res || !res.success || !res.data) {
				return;
			}
			if (res.data.nonce) {
				freshNonce = res.data.nonce;
			}
			accountCredit = parseFloat(res.data.credit_balance) || 0;
			ggmCheckout.credit_currency_allowed = res.data.credit_currency_allowed !== false;
			refreshCreditDisplay();
			// Fill in the real current visitor's own contact details — the
			// template deliberately leaves these fields blank server-side so
			// a cached copy of this page can never leak a different
			// visitor's name/phone/email. Only fill in what the visitor
			// hasn't already typed (or wasn't pre-filled via URL params).
			var c = res.data.contact || {};
			if (c.name && !$('#ggm-checkout-name').val()) {
				$('#ggm-checkout-name').val(c.name);
			}
			if (c.email && !$('#ggm-checkout-email').val()) {
				$('#ggm-checkout-email').val(c.email);
			}
			if (c.phone && !$('#ggm-checkout-phone').val()) {
				$('#ggm-checkout-phone').val(c.phone);
			}
		});
	}

	// Prefer the freshly-fetched nonce; fall back to whatever's in the page
	// markup only if that request hasn't come back yet (e.g. a very fast
	// click) or failed outright.
	function currentNonce(fallback) {
		return freshNonce || fallback;
	}

	function selectedCurrency() {
		return window.ggmSelectedCurrency || $('#ggm-pay-btn').data('currency') || (ggmCheckout && ggmCheckout.currency) || '';
	}

	// A brand-new visitor's just-set auth cookie isn't reliable to depend on
	// for the very next AJAX call (see checkout.php's inline OTP script,
	// which sets window.ggmVerifiedAuth right after login). Send this signed
	// uid/token pair along on every checkout request so the server can
	// authenticate them the same cookie-independent way
	// ajax_complete_profile() already does, instead of only via session.
	function verifiedAuthParams() {
		var a = window.ggmVerifiedAuth;
		if (a && a.uid && a.token) {
			return { uid: a.uid, auth_token: a.token };
		}
		return {};
	}

	function contactParams() {
		return {
			contact_name:         $('#ggm-checkout-name').val() || '',
			contact_phone:        $('#ggm-checkout-phone').val() || '',
			contact_country_code: $('#ggm-checkout-country-code').val() || '+91',
			contact_email:        $('#ggm-checkout-email').val() || ''
		};
	}

	function getCurrencySymbol() {
		var text = $('#ggm-checkout-subtotal').text() || '';
		var match = text.match(/^[^\d]+/);
		return match ? match[0] : '';
	}

	function refreshCreditDisplay() {
		var total = parseFloat((($('#ggm-checkout-final').text() || '').match(/[\d,.]+/) || ['0'])[0].replace(/,/g, '')) || 0;
		// The current total may already include credit; rebuild from the button's
		// base price and any coupon discount kept in data.
		var $pay = $('#ggm-pay-btn');
		var isContribution = String($pay.data('contribution') || '0') === '1';
		var creditAllowed = ggmCheckout.credit_currency_allowed !== false;
		var beforeCredit = parseFloat($pay.data('amount-before-credit'));
		if (isNaN(beforeCredit)) { beforeCredit = total; $pay.data('amount-before-credit', beforeCredit); }
		// Contribution pricing intentionally does not consume account credit
		// server-side. Keep the displayed total/button consistent with the
		// amount Razorpay will actually charge.
		var used = (isContribution || !creditAllowed) ? 0 : Math.min(accountCredit, beforeCredit);
		if (used > 0) {
			$('#ggm-credit-balance-label').text((ggmCheckout.available_credit || 'Available') + ': ' + getCurrencySymbol() + accountCredit.toFixed(2));
			$('#ggm-checkout-credit').text('-' + getCurrencySymbol() + used.toFixed(2));
			$('#ggm-checkout-credit-row').show();
		} else { $('#ggm-checkout-credit-row').hide(); }
		var payable = Math.max(0, beforeCredit - used);
		$('#ggm-checkout-final').text(getCurrencySymbol() + payable.toFixed(2));
		var payLabel;
		if (beforeCredit <= 0) {
			payLabel = ggmCheckout.enroll_free || 'Enroll for Free';
		} else if (payable <= 0) {
			payLabel = ggmCheckout.use_credit || 'Complete with Credit';
		} else {
			payLabel = 'Pay ' + getCurrencySymbol() + payable.toFixed(2) + ' Securely';
		}
		$pay.html('<span aria-hidden="true">&#128274;</span> ' + payLabel);
	}

	function handleApplyCoupon() {
		var $btn    = $('#ggm-apply-coupon-btn');
		var $msg    = $('#ggm-coupon-message');
		var $pay    = $('#ggm-pay-btn');
		var code    = $('#ggm-coupon-input').val();
		var type    = $pay.data('type');
		var itemId  = 'workshop' === type ? $pay.data('workshop') : $pay.data('course');
		var nonce   = currentNonce($pay.data('nonce'));

		if ( ! code ) {
			return;
		}

		$btn.prop('disabled', true).text(ggmCheckout.applying || 'Applying…');
		$msg.hide();

		$.post(ggmCheckout.ajaxurl, $.extend({
			action:      'ggm_apply_coupon',
			nonce:       nonce,
			coupon_code: code,
			type:        type,
			item_id:     itemId,
			ggm_currency: selectedCurrency()
		}, contactParams(), verifiedAuthParams())).done(function (res) {
			$btn.prop('disabled', false).text(ggmCheckout.apply_coupon || 'Apply Coupon');
			if (res.data && res.data.uid && res.data.auth_token) {
				window.ggmVerifiedAuth = { uid: res.data.uid, token: res.data.auth_token };
			}
			if ( ! res.success ) {
				appliedCoupon = '';
				showError($msg, res.data.message);
				resetTotals();
				return;
			}
			appliedCoupon = code;
			updateTotals(res.data);
			$msg.text(res.data.message || (ggmCheckout.coupon_applied || 'Coupon applied.'))
				.removeClass('ggm-notice-error').addClass('ggm-notice-success').show();
		}).fail(function () {
			$btn.prop('disabled', false).text(ggmCheckout.apply_coupon || 'Apply Coupon');
			showError($msg, ggmCheckout.error || 'Something went wrong.');
		});
	}

	function updateTotals(data) {
		var symbol = getCurrencySymbol();
		var finalAmount = parseFloat(data.final_amount) || 0;
		var originalAmount = parseFloat(data.original_amount) || 0;
		var saleDiscount = parseFloat($('#ggm-pay-btn').data('display-discount')) || 0;
		if (saleDiscount > 0) {
			$('#ggm-checkout-subtotal').html('<del>' + symbol + (originalAmount + saleDiscount).toFixed(2) + '</del> ' + symbol + originalAmount.toFixed(2));
		} else {
			$('#ggm-checkout-subtotal').text(symbol + originalAmount.toFixed(2));
		}
		if (saleDiscount + parseFloat(data.discount_amount) > 0) {
			$('#ggm-checkout-discount').text('-' + symbol + (saleDiscount + parseFloat(data.discount_amount)).toFixed(2));
			$('#ggm-checkout-discount-row').show();
		} else {
			$('#ggm-checkout-discount-row').hide();
		}
		$('#ggm-checkout-final').text(symbol + finalAmount.toFixed(2));
		$('#ggm-pay-btn').data('amount-before-credit', finalAmount);
		refreshCreditDisplay();
	}

	function resetTotals() {
		$('#ggm-checkout-discount-row').hide();
		var $pay = $('#ggm-pay-btn');
		var base = parseFloat($pay.data('amount')) || 0;
		var saleDiscount = parseFloat($pay.data('display-discount')) || 0;
		if (saleDiscount > 0) {
			$('#ggm-checkout-subtotal').html('<del>' + getCurrencySymbol() + (base + saleDiscount).toFixed(2) + '</del> ' + getCurrencySymbol() + base.toFixed(2));
			$('#ggm-checkout-discount').text('-' + getCurrencySymbol() + saleDiscount.toFixed(2));
			$('#ggm-checkout-discount-row').show();
		}
		$('#ggm-checkout-final').text(getCurrencySymbol() + base.toFixed(2));
		$pay.data('amount-before-credit', base);
		refreshCreditDisplay();
	}

	function restorePayButton($btn) {
		var idleHtml = $btn.data('idle-html');
		$btn.prop('disabled', false);
		if (idleHtml) {
			$btn.html(idleHtml);
		} else {
			$btn.text(ggmCheckout.pay_btn || 'Pay Now');
		}
	}

	function handlePay() {
		var $btn        = $('#ggm-pay-btn');
		var $fb         = $('#ggm-checkout-feedback');
		var workshopId  = $btn.data('workshop') || 0;
		var courseId    = $btn.data('course') || 0;
		var nonce       = currentNonce($btn.data('nonce'));
		var name        = $('#ggm-checkout-name').val() ? $('#ggm-checkout-name').val().trim() : '';
		var phone       = $('#ggm-checkout-phone').val() ? $('#ggm-checkout-phone').val().trim() : '';
		var countryCode = $('#ggm-checkout-country-code').length ? $('#ggm-checkout-country-code').val() : '+91';
		var email       = $('#ggm-checkout-email').val() ? $('#ggm-checkout-email').val().trim() : '';

		if ( ! name || ! phone || ! email) {
			showError($fb, ggmCheckout.fill_details || 'Please fill in your name, WhatsApp number, and email.');
			return;
		}

		if ($btn.data('needs-verify') && ! $('#ggm-checkout-phone').data('verified')) {
			showError($fb, ggmCheckout.verify_phone || 'Please verify your WhatsApp number before proceeding.');
			return;
		}

		if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			showError($fb, ggmCheckout.invalid_email || 'Please enter a valid email address.');
			return;
		}

		$btn.data('idle-html', $btn.html()).prop('disabled', true).text(ggmCheckout.creating || 'Creating order…');
		$fb.hide();

		$.post(ggmCheckout.ajaxurl, $.extend({
			action:        'ggm_create_order',
			nonce:         nonce,
			workshop_id:   workshopId,
			course_id:     courseId,
			coupon_code:   appliedCoupon,
			contact_name:  name,
			contact_phone: phone,
			contact_country_code: countryCode,
			contact_email: email,
			contribution_option_id: $btn.attr('data-contribution-option') || '',
			ggm_currency: selectedCurrency()
		}, verifiedAuthParams())).done(function (res) {
			if ( ! res.success ) {
				showError($fb, res.data.message);
				restorePayButton($btn);
				return;
			}
			if (res.data.free) {
				window.location.href = res.data.redirect;
				return;
			}
			if (res.data.uid && res.data.auth_token) {
				window.ggmVerifiedAuth = { uid: res.data.uid, token: res.data.auth_token };
			}
			openRazorpay(res.data, nonce, $btn, $fb, { name: name, phone: phone, email: email });
		}).fail(function () {
			showError($fb, ggmCheckout.error || 'Something went wrong.');
			restorePayButton($btn);
		});
	}

	function openRazorpay(order, nonce, $btn, $fb, contact) {
		contact = contact || {};
		var options = {
			key:          order.key_id,
			amount:       order.amount,
			currency:     order.currency,
			name:         ggmCheckout.site_name || 'GGM',
			description:  ggmCheckout.description || 'Workshop/Course Purchase',
			order_id:     order.order_id,
			handler:      function (response) {
				verifyPayment(response, order.payment_id, nonce, $fb);
			},
			prefill: {
				name:    contact.name  || '',
				email:   contact.email || '',
				contact: contact.phone || '',
			},
			theme: { color: ggmCheckout.theme_color || '#7c3aed' },
			modal: {
				ondismiss: function () {
					restorePayButton($btn);
				},
			},
		};

		var rzp = new Razorpay(options);
		rzp.on('payment.failed', function (response) {
			showError($fb, response.error.description || 'Payment failed.');
			restorePayButton($btn);
		});
		rzp.open();
	}

	function verifyPayment(response, paymentDbId, nonce, $fb) {
		$.post(ggmCheckout.ajaxurl, $.extend({
			action:              'ggm_verify_payment',
			nonce:               nonce,
			razorpay_order_id:   response.razorpay_order_id,
			razorpay_payment_id: response.razorpay_payment_id,
			razorpay_signature:  response.razorpay_signature,
			payment_id:          paymentDbId,
		}, verifiedAuthParams())).done(function (res) {
			if ( res.success ) {
				window.location.href = res.data.redirect || '/checkout/success/';
			} else {
				showError($fb, res.data.message);
			}
		}).fail(function () {
			showError($fb, ggmCheckout.error || 'Verification failed.');
		});
	}

	function showError($el, msg) {
		$el.text(msg)
		   .removeClass('ggm-notice-success')
		   .addClass('ggm-notice-error')
		   .show();
	}

	$(document).ready(init);

}(jQuery));
