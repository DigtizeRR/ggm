/**
 * GGM Login — wires the 3-step auth flow to the actual template IDs.
 *
 * Step 1: #ggm-login-step-1 — identifier + Send OTP / password form
 * Step 2: #ggm-login-step-2 — 6-digit OTP entry
 * Step 3: #ggm-login-step-3 — first-time profile completion
 *
 * Localized as `ggmData` by class-ggm-public.php.
 */
/* global ggmData, jQuery */
(function ($) {
	'use strict';

	$(document).ready(function () {

	// Stored identifier between step 1 → step 2.
	var currentIdentifier = '';
	var currentOtpRequest = '';
	var otpRequestInFlight = false;
	var otpResendInFlight  = false;
	var resendTimer        = null;

	// ── Helpers ────────────────────────────────────────────────────────────────

	function showStep(n) {
		$('#ggm-login-step-1, #ggm-login-step-2, #ggm-login-step-3').hide();
		$('#ggm-login-step-' + n).fadeIn(180);
	}

	function setError($el, msg) {
		$el.text(msg).show();
	}

	// Read once, reused across all three auth steps (OTP verify, password
	// login, complete-profile) so whichever step ends up being the "last"
	// one for a given visitor still lands them back at ?redirect_to=.
	function getRedirectTo() {
		try {
			return new URLSearchParams(window.location.search).get('redirect_to') || '';
		} catch (e) {
			return '';
		}
	}

	function clearError($el) {
		$el.hide().text('');
	}

	function identifierValue(inputSelector, countrySelector) {
		var value = $(inputSelector).val().trim();
		if (!value || value.indexOf('@') !== -1) return value;
		var digits = value.replace(/\D/g, '');
		// Do not add the selected country code to a number the visitor already
		// pasted with +91/91/0. The server owns final validation.
		if (value.charAt(0) === '+' || (digits.length === 12 && digits.indexOf('91') === 0) || (digits.length === 11 && digits.charAt(0) === '0')) {
			return value;
		}
		return ($(countrySelector).val() || '+91') + digits;
	}

	$('.ggm-auth-identifier-control input[type="text"]').on('input', function () {
		$(this).closest('.ggm-auth-identifier-control').toggleClass('is-email-mode', this.value.indexOf('@') !== -1);
	});

	// ── Step 1: Send OTP ───────────────────────────────────────────────────────

	$('#ggm-send-otp').on('click', function () {
		var $btn = $(this);
		var id   = identifierValue('#ggm-identifier', '#ggm-login-country-code');
		var $err = $('#ggm-otp-error');

		if (!id) {
			setError($err, 'Please enter your phone number or email.');
			return;
		}
		clearError($err);
		$btn.prop('disabled', true).text(ggmData.sending || 'Sending…');

		$('#ggm-account-not-found').hide();

		$.post(ggmData.ajaxurl, {
			action:     'ggm_send_otp',
			nonce:      ggmData.nonce,
			identifier: id,
		}).done(function (res) {
			if (res.success) {
				currentIdentifier = id;
				currentOtpRequest = (res.data && res.data.request_id) ? res.data.request_id : '';
				// Show masked destination in step 2.
				var masked = (res.data && res.data.masked) ? res.data.masked : id;
				$('#ggm-otp-sent-to').text('OTP sent to ' + masked);
				// Clear any previous digits.
				$('.ggm-otp-digit').val('');
				$('.ggm-otp-digit').first().focus();
				startResend(30);
				showStep(2);
			} else if (res.data && res.data.account_not_found) {
				$('#ggm-create-account-link').attr('href', ggmData.signup_url || '#');
				$('#ggm-account-not-found').show();
			} else {
				setError($err, (res.data && res.data.message) ? res.data.message : 'Failed to send OTP.');
			}
		}).fail(function () {
			setError($err, ggmData.error || 'Something went wrong.');
		}).always(function () {
			$btn.prop('disabled', false).text(ggmData.send_otp || 'Send OTP');
		});
	});

	// ── Step 2: Verify OTP ─────────────────────────────────────────────────────

	// Auto-advance between digit boxes.
	$(document).on('keyup', '.ggm-otp-digit', function (e) {
		var $this = $(this);
		if ($this.val().length === 1 && /^\d$/.test($this.val())) {
			$this.next('.ggm-otp-digit').focus();
		}
		if (e.key === 'Backspace' && $this.val() === '') {
			$this.prev('.ggm-otp-digit').focus();
		}
		// Auto-submit when all 6 filled.
		var combined = '';
		$('.ggm-otp-digit').each(function () { combined += $(this).val(); });
		if (combined.length === 6) {
			submitOtp(combined);
		}
	});

	// Paste support.
	$(document).on('paste', '.ggm-otp-digit', function (e) {
		e.preventDefault();
		var pasted = (e.originalEvent.clipboardData || window.clipboardData).getData('text').trim();
		if (/^\d{6}$/.test(pasted)) {
			$('.ggm-otp-digit').each(function (i) { $(this).val(pasted[i]); });
			submitOtp(pasted);
		}
	});

	$('#ggm-verify-otp').on('click', function () {
		var combined = '';
		$('.ggm-otp-digit').each(function () { combined += $(this).val(); });
		submitOtp(combined);
	});

	function submitOtp(code) {
		var $btn = $('#ggm-verify-otp');
		var $err = $('#ggm-verify-error');

		if (otpRequestInFlight) {
			return;
		}
		if (code.length !== 6 || !currentOtpRequest) {
			setError($err, 'Please enter all 6 digits.');
			return;
		}
		clearError($err);
		otpRequestInFlight = true;
		$('.ggm-otp-digit').prop('disabled', true);
		$btn.prop('disabled', true).text(ggmData.verifying || 'Verifying…');

		$.post(ggmData.ajaxurl, {
			action:      'ggm_verify_otp',
			nonce:       ggmData.nonce,
			identifier:  currentIdentifier,
			otp:         code,
			request_id:  currentOtpRequest,
			redirect_to: getRedirectTo(),
		}).done(function (res) {
			if (res && res.success === true && res.data && res.data.authenticated === true) {
				clearInterval(resendTimer);

				// Store uid + auth_token for the profile-save fallback auth.
				if (res.data) {
					ggmData.uid        = res.data.uid        || 0;
					ggmData.auth_token = res.data.auth_token || '';
				}

				if (res.data && res.data.needs_profile) {
					// Pre-fill phone if user logged in via phone number.
					if (res.data.is_phone && res.data.identifier) {
						$('#ggm-profile-phone')
							.val(res.data.identifier)
							.prop('readonly', true)
							.css({'background':'#f1f5f9','color':'#64748b'});
						$('#ggm-profile-phone-note').show();
					}
					showStep(3);
				} else {
					var dest = (res.data && res.data.redirect) ? res.data.redirect : '/dashboard/';
					window.location.href = dest;
				}
			} else {
				otpRequestInFlight = false;
				$('.ggm-otp-digit').prop('disabled', false);
				$btn.prop('disabled', false).text(ggmData.verify_otp || 'Verify OTP');
				setError($err, (res.data && res.data.message) ? res.data.message : 'Invalid or expired OTP. Please try again.');
			}
		}).fail(function () {
			otpRequestInFlight = false;
			$('.ggm-otp-digit').prop('disabled', false);
			$btn.prop('disabled', false).text(ggmData.verify_otp || 'Verify OTP');
			setError($err, ggmData.error || 'Something went wrong.');
		});
	}

	// Back to step 1.
	$('#ggm-back-to-step1').on('click', function () {
		showStep(1);
	});

	// Resend OTP.
	$('#ggm-resend-otp').on('click', function () {
		var $btn = $(this);
		var $err = $('#ggm-verify-error');
		if (otpResendInFlight || !currentIdentifier) {
			return;
		}
		otpResendInFlight = true;
		clearError($('#ggm-verify-error'));
		$btn.prop('disabled', true).text('Sending…');

		$.post(ggmData.ajaxurl, {
			action:     'ggm_send_otp',
			nonce:      ggmData.nonce,
			identifier: currentIdentifier,
		}).done(function (res) {
			if (res.success) {
				currentOtpRequest = (res.data && res.data.request_id) ? res.data.request_id : '';
				$('.ggm-otp-digit').val('');
				$('.ggm-otp-digit').first().focus();
				startResend(30);
			} else {
				setError($err, (res.data && res.data.message) ? res.data.message : 'Failed to resend OTP. Your previous code may still be valid.');
				$btn.prop('disabled', false).text('Resend OTP');
			}
		}).fail(function () {
			setError($err, ggmData.error || 'Unable to resend OTP. Check your connection and try again.');
			$btn.prop('disabled', false).text('Resend OTP');
		}).always(function () {
			otpResendInFlight = false;
		});
	});

	function startResend(seconds) {
		clearInterval(resendTimer);
		var $btn = $('#ggm-resend-otp');
		$btn.prop('disabled', true);

		resendTimer = setInterval(function () {
			seconds--;
			$btn.text('Resend OTP (' + seconds + 's)');
			if (seconds <= 0) {
				clearInterval(resendTimer);
				$btn.prop('disabled', false).text('Resend OTP');
			}
		}, 1000);
	}

	// ── Login method switcher: OTP view ⇄ Password view (exclusive, no reload) ──

	$('#ggm-toggle-password-login').on('click', function () {
		$('#ggm-otp-login-view').hide();
		$('#ggm-account-not-found').hide();
		$('#ggm-password-login-view').show();
		clearError($('#ggm-pw-error'));
	});

	$('#ggm-back-to-otp-login').on('click', function () {
		$('#ggm-password-login-view').hide();
		$('#ggm-otp-login-view').show();
		clearError($('#ggm-otp-error'));
	});

	// ── Forgot Password (reset via OTP) ─────────────────────────────────────────

	var fpIdentifier = '';
	var fpOtpRequest = '';

	function fpShowRequestStage() {
		$('#ggm-fp-reset-stage').hide();
		$('#ggm-fp-request-stage').show();
		clearError($('#ggm-fp-request-error'));
		$('#ggm-fp-account-not-found').hide();
	}

	$('#ggm-forgot-password-link').on('click', function () {
		$('#ggm-password-login-view').hide();
		fpShowRequestStage();
		$('#ggm-forgot-password-view').show();
	});

	$('#ggm-back-to-password-login').on('click', function () {
		$('#ggm-forgot-password-view').hide();
		$('#ggm-password-login-view').show();
	});

	$('#ggm-fp-change-identifier').on('click', function () {
		fpShowRequestStage();
	});

	$('#ggm-fp-send-otp').on('click', function () {
		var $btn = $(this);
		var id   = identifierValue('#ggm-fp-identifier', '#ggm-fp-country-code');
		var $err = $('#ggm-fp-request-error');

		if (!id) {
			setError($err, 'Please enter your phone number or email.');
			return;
		}
		clearError($err);
		$('#ggm-fp-account-not-found').hide();
		$btn.prop('disabled', true).text(ggmData.sending || 'Sending…');

		$.post(ggmData.ajaxurl, {
			action:     'ggm_send_otp',
			nonce:      ggmData.nonce,
			identifier: id,
		}).done(function (res) {
			if (res.success) {
				fpIdentifier = id;
				fpOtpRequest = (res.data && res.data.request_id) ? res.data.request_id : '';
				var masked = (res.data && res.data.masked) ? res.data.masked : id;
				$('#ggm-fp-otp-sent-to').text('OTP sent to ' + masked);
				$('#ggm-fp-otp, #ggm-fp-new-password, #ggm-fp-confirm-password').val('');
				$('#ggm-fp-request-stage').hide();
				$('#ggm-fp-reset-stage').show();
			} else if (res.data && res.data.account_not_found) {
				$('#ggm-fp-create-account-link').attr('href', ggmData.signup_url || '#');
				$('#ggm-fp-account-not-found').show();
			} else {
				setError($err, (res.data && res.data.message) ? res.data.message : 'Failed to send OTP.');
			}
		}).fail(function () {
			setError($err, ggmData.error || 'Something went wrong.');
		}).always(function () {
			$btn.prop('disabled', false).text(ggmData.send_otp || 'Send OTP');
		});
	});

	$('#ggm-fp-reset-submit').on('click', function () {
		var $btn     = $(this);
		var otp      = $('#ggm-fp-otp').val().trim();
		var pass     = $('#ggm-fp-new-password').val();
		var confirm  = $('#ggm-fp-confirm-password').val();
		var $err     = $('#ggm-fp-reset-error');

		if (otp.length !== 6) {
			setError($err, 'Please enter the 6-digit OTP.');
			return;
		}
		if (!pass || pass.length < 6) {
			setError($err, 'Password must be at least 6 characters.');
			return;
		}
		if (pass !== confirm) {
			setError($err, 'Passwords do not match.');
			return;
		}
		clearError($err);
		$btn.prop('disabled', true).text('Resetting…');

		$.post(ggmData.ajaxurl, {
			action:           'ggm_reset_password',
			nonce:            ggmData.nonce,
			identifier:       fpIdentifier,
			otp:              otp,
			request_id:       fpOtpRequest,
			new_password:     pass,
			confirm_password: confirm,
			redirect_to:      getRedirectTo(),
		}).done(function (res) {
			if (res.success) {
				var dest = (res.data && res.data.redirect) ? res.data.redirect : '/dashboard/';
				window.location.href = dest;
			} else {
				$btn.prop('disabled', false).text('Reset Password');
				setError($err, (res.data && res.data.message) ? res.data.message : 'Could not reset password.');
			}
		}).fail(function () {
			$btn.prop('disabled', false).text('Reset Password');
			setError($err, ggmData.error || 'Something went wrong.');
		});
	});

	$('#ggm-pw-login').on('click', function () {
		var $btn  = $(this);
		var email = $('#ggm-pw-email').val().trim();
		var pass  = $('#ggm-pw-password').val();
		var $err  = $('#ggm-pw-error');

		if (!email || !pass) {
			setError($err, 'Please enter email and password.');
			return;
		}
		clearError($err);
		$btn.prop('disabled', true).text('Logging in…');

		$.post(ggmData.ajaxurl, {
			action:      'ggm_password_login',
			nonce:       ggmData.nonce,
			login:       email,
			password:    pass,
			redirect_to: getRedirectTo(),
		}).done(function (res) {
			if (res.success) {
				var dest = (res.data && res.data.redirect) ? res.data.redirect : '/dashboard/';
				window.location.href = dest;
			} else {
				$btn.prop('disabled', false).text('Login');
				setError($err, (res.data && res.data.message) ? res.data.message : 'Invalid credentials.');
			}
		}).fail(function () {
			$btn.prop('disabled', false).text('Login');
			setError($err, ggmData.error || 'Something went wrong.');
		});
	});

	// ── Step 3: Profile Completion ─────────────────────────────────────────────

	$('#ggm-save-profile-step3').on('click', function () {
		var $btn         = $(this);
		var fname        = $('#ggm-profile-fname').val().trim();
		var lname        = $('#ggm-profile-lname').val().trim();
		var phone        = $('#ggm-profile-phone').val().trim();
		var countryCode  = $('#ggm-profile-country-code').val() || '+91';
		var $err         = $('#ggm-profile-step3-error');

		if (!fname) {
			setError($err, 'Please enter your first name.');
			return;
		}
		clearError($err);
		$btn.prop('disabled', true).text('Saving…');

		$.post(ggmData.ajaxurl, {
			action:       'ggm_complete_profile',
			nonce:        ggmData.nonce,
			uid:          ggmData.uid        || 0,
			auth_token:   ggmData.auth_token || '',
			first_name:   fname,
			last_name:    lname,
			phone:        phone,
			country_code: countryCode,
			redirect_to:  getRedirectTo(),
		}).done(function (res) {
			if (res.success) {
				var dest = (res.data && res.data.redirect) ? res.data.redirect : '/dashboard/';
				window.location.href = dest;
			} else {
				$btn.prop('disabled', false).text('Save & Continue →');
				setError($err, (res.data && res.data.message) ? res.data.message : 'Could not save profile.');
			}
		}).fail(function () {
			$btn.prop('disabled', false).text('Save & Continue →');
			setError($err, ggmData.error || 'Something went wrong.');
		});
	});

	}); // end document.ready

}(jQuery));
