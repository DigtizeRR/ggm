/**
 * GGM Auth — handles login form step transitions (identifier → OTP → profile).
 */
/* global ggmData, jQuery */
(function ($) {
	'use strict';

	var $loginWrap   = null;
	var $step1       = null;
	var $step2       = null;
	var $step3       = null;
	var resendTimer  = null;
	var otpRequestId = '';
	var verifyingOtp = false;

	function init() {
		$loginWrap = $('#ggm-login-wrap');
		if ( ! $loginWrap.length ) { return; }

		$step1 = $loginWrap.find('#ggm-step-identifier');
		$step2 = $loginWrap.find('#ggm-step-otp');
		$step3 = $loginWrap.find('#ggm-step-profile');

		bindIdentifierForm();
		bindOtpForm();
		bindProfileForm();
		bindPasswordForm();
		bindResend();
		bindTabSwitch();
	}

	function showStep(step) {
		$step1.hide();
		$step2.hide();
		$step3.hide();
		step.fadeIn(200);
	}

	function setFeedback($el, msg, isError) {
		$el.text(msg)
		   .removeClass('ggm-error ggm-success')
		   .addClass(isError ? 'ggm-error' : 'ggm-success')
		   .show();
	}

	// ── Identifier Step ─────────────────────────────────────────────────────

	function bindIdentifierForm() {
		$step1.find('#ggm-identifier-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var $btn  = $form.find('button[type=submit]');
			var $fb   = $step1.find('.ggm-feedback');
			var id    = $form.find('#ggm_identifier').val().trim();

			if ( ! id ) { return; }
			$btn.prop('disabled', true).text(ggmData.sending || 'Sending…');
			$fb.hide();

			$.post(ggmData.ajaxurl, {
				action:     'ggm_send_otp',
				nonce:      ggmData.nonce,
				identifier: id,
			}).done(function (res) {
				if ( res.success ) {
					otpRequestId = res.data.request_id || '';
					$step2.find('.ggm-masked-id').text(res.data.masked || id);
					$step2.find('#ggm_otp_identifier').val(id);
					startResendCountdown(30);
					showStep($step2);
				} else {
					setFeedback($fb, res.data.message, true);
				}
			}).fail(function () {
				setFeedback($fb, ggmData.error || 'Something went wrong.', true);
			}).always(function () {
				$btn.prop('disabled', false).text(ggmData.send_otp || 'Send OTP');
			});
		});
	}

	// ── OTP Step ─────────────────────────────────────────────────────────────

	function bindOtpForm() {
		$step2.find('#ggm-otp-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var $btn  = $form.find('button[type=submit]');
			var $fb   = $step2.find('.ggm-feedback');
			var id    = $form.find('#ggm_otp_identifier').val().trim();
			var otp   = $form.find('#ggm_otp_code').val().trim();
			if ( verifyingOtp ) { return; }

			verifyingOtp = true;
			$btn.prop('disabled', true).text(ggmData.verifying || 'Verifying…');
			$fb.hide();

			$.post(ggmData.ajaxurl, {
				action:     'ggm_verify_otp',
				nonce:      ggmData.nonce,
				identifier: id,
				otp:        otp,
				request_id: otpRequestId,
			}).done(function (res) {
				if ( res && res.success === true && res.data && res.data.authenticated === true ) {
					if ( res.data.needs_profile ) {
						showStep($step3);
					} else {
						window.location.href = res.data.redirect || '/dashboard/';
					}
				} else {
					verifyingOtp = false;
					setFeedback($fb, (res && res.data && res.data.message) || 'Invalid or expired OTP. Please try again.', true);
				}
			}).fail(function () {
				verifyingOtp = false;
				setFeedback($fb, ggmData.error || 'Something went wrong.', true);
			}).always(function () {
				$btn.prop('disabled', false).text(ggmData.verify_otp || 'Verify OTP');
			});
		});

		// Back link
		$step2.find('.ggm-back-link').on('click', function (e) {
			e.preventDefault();
			showStep($step1);
		});
	}

	// ── Profile Step ─────────────────────────────────────────────────────────

	function bindProfileForm() {
		$step3.find('#ggm-profile-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var $btn  = $form.find('button[type=submit]');
			var $fb   = $step3.find('.ggm-feedback');

			$btn.prop('disabled', true);
			$fb.hide();

			$.post(ggmData.ajaxurl, {
				action:     'ggm_complete_profile',
				nonce:      ggmData.nonce,
				first_name: $form.find('#ggm_first_name').val(),
				last_name:  $form.find('#ggm_last_name').val(),
				phone:      $form.find('#ggm_phone').val(),
			}).done(function (res) {
				if ( res.success ) {
					window.location.href = res.data.redirect || '/dashboard/';
				} else {
					setFeedback($fb, res.data.message, true);
				}
			}).fail(function () {
				setFeedback($fb, ggmData.error || 'Something went wrong.', true);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});
	}

	// ── Password Form ────────────────────────────────────────────────────────

	function bindPasswordForm() {
		$loginWrap.find('#ggm-password-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			var $btn  = $form.find('button[type=submit]');
			var $fb   = $form.find('.ggm-feedback');

			$btn.prop('disabled', true);
			$fb.hide();

			$.post(ggmData.ajaxurl, {
				action:   'ggm_password_login',
				nonce:    ggmData.nonce,
				email:    $form.find('#ggm_email').val(),
				password: $form.find('#ggm_password').val(),
			}).done(function (res) {
				if ( res.success ) {
					window.location.href = res.data.redirect || '/dashboard/';
				} else {
					setFeedback($fb, res.data.message, true);
				}
			}).fail(function () {
				setFeedback($fb, ggmData.error || 'Something went wrong.', true);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});
	}

	// ── Resend ───────────────────────────────────────────────────────────────

	function bindResend() {
		$loginWrap.find('#ggm-resend-link').on('click', function (e) {
			e.preventDefault();
			var id = $step2.find('#ggm_otp_identifier').val();
			var $fb = $step2.find('.ggm-feedback');

			$.post(ggmData.ajaxurl, {
				action:     'ggm_resend_otp',
				nonce:      ggmData.nonce,
				identifier: id,
			}).done(function (res) {
				if ( res.success ) {
					otpRequestId = res.data.request_id || '';
					startResendCountdown(res.data.countdown || 30);
					setFeedback($fb, ggmData.otp_resent || 'OTP resent.', false);
				} else {
					setFeedback($fb, res.data.message, true);
				}
			});
		});
	}

	function startResendCountdown(seconds) {
		clearInterval(resendTimer);
		var $link  = $loginWrap.find('#ggm-resend-link');
		var $timer = $loginWrap.find('#ggm-resend-timer');
		$link.hide();
		$timer.text(seconds + 's').show();

		resendTimer = setInterval(function () {
			seconds--;
			$timer.text(seconds + 's');
			if ( seconds <= 0 ) {
				clearInterval(resendTimer);
				$timer.hide();
				$link.show();
			}
		}, 1000);
	}

	// ── Login Method Tab Switch ───────────────────────────────────────────────

	function bindTabSwitch() {
		$loginWrap.find('.ggm-login-tab').on('click', function () {
			var target = $(this).data('target');
			$loginWrap.find('.ggm-login-tab').removeClass('active');
			$(this).addClass('active');
			$loginWrap.find('.ggm-login-method').hide();
			$loginWrap.find('#' + target).fadeIn(200);
		});
	}

	$(document).ready(init);

}(jQuery));
