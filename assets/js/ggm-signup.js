/**
 * GGM Signup — [ggm_signup] form submission.
 * Localized as `ggmSignupData` by class-ggm-public.php.
 */
/* global ggmSignupData, jQuery */
(function ($) {
	'use strict';

	$(document).ready(function () {

	function setError($el, msg) {
		$el.text(msg).show();
	}

	function clearError($el) {
		$el.hide().text('');
	}

	function getRedirectTo() {
		try {
			return new URLSearchParams(window.location.search).get('redirect_to') || '';
		} catch (e) {
			return '';
		}
	}

	$('#ggm-signup-submit').on('click', function () {
		var $btn     = $(this);
		var fname    = $('#ggm-signup-fname').val().trim();
		var lname    = $('#ggm-signup-lname').val().trim();
		var email    = $('#ggm-signup-email').val().trim();
		var phone    = $('#ggm-signup-phone').val().trim();
		var countryCode = $('#ggm-signup-country-code').val() || '+91';
		var password = $('#ggm-signup-password').val();
		var confirm  = $('#ggm-signup-confirm-password').val();
		var $err     = $('#ggm-signup-error');

		if (!fname || !email || !password) {
			setError($err, ggmSignupData.fill_required || 'Please fill in all required fields.');
			return;
		}
		if (password !== confirm) {
			setError($err, ggmSignupData.password_mismatch || 'Passwords do not match.');
			return;
		}
		clearError($err);
		$btn.prop('disabled', true).text(ggmSignupData.creating || 'Creating account…');

		$.post(ggmSignupData.ajaxurl, {
			action:           'ggm_signup',
			nonce:            ggmSignupData.nonce,
			first_name:       fname,
			last_name:        lname,
			email:            email,
			phone:            phone,
			country_code:     countryCode,
			password:         password,
			confirm_password: confirm,
			redirect_to:      getRedirectTo(),
		}).done(function (res) {
			if (res.success) {
				var dest = (res.data && res.data.redirect) ? res.data.redirect : '/dashboard/';
				window.location.href = dest;
			} else {
				$btn.prop('disabled', false).text(ggmSignupData.create_account || 'Create Account');
				setError($err, (res.data && res.data.message) ? res.data.message : 'Something went wrong.');
			}
		}).fail(function () {
			$btn.prop('disabled', false).text(ggmSignupData.create_account || 'Create Account');
			setError($err, ggmSignupData.error || 'Something went wrong.');
		});
	});

	}); // end document.ready

}(jQuery));
