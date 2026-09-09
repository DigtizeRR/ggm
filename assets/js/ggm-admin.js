/**
 * Admin Area JavaScript routines for GGM Member Dashboard.
 *
 * Implements AJAX routines for settings saving, membership CRUD populator,
 * dynamic tabs switching, and overlay modal actions.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		$(document).on('submit', '.ggm-add-credit-form', function(e) {
			e.preventDefault();
			var $form = $(this), $feedback = $form.find('.ggm-credit-feedback');
			$form.find('button').prop('disabled', true); $form.find('.spinner').addClass('is-active');
			$.post(ggmAdmin.ajaxurl, {action:'ggm_add_customer_credit', nonce:ggmAdmin.nonce, user_id:$form.data('user-id'), amount:$form.find('[name=amount]').val(), note:$form.find('[name=note]').val()}).done(function(res) {
				$feedback.text(res.data.message).css('color', res.success ? '#16803c' : '#b32d2e');
				if (res.success) { $form.closest('tr').find('.ggm-credit-balance').text(res.data.balance); $form[0].reset(); }
			}).always(function(){ $form.find('button').prop('disabled', false); $form.find('.spinner').removeClass('is-active'); });
		});

		// ─────────────────────────────────────────────────────────────────────
		// 1. Color Pickers (wp-color-picker)
		// ─────────────────────────────────────────────────────────────────────
		if ($.fn.wpColorPicker) {
			$('.ggm-color-picker').wpColorPicker({
				change: function() {},
				clear: function() {}
			});
		}

		// Settings tab switching now lives in ggm-settings-tabs.js (vanilla
		// JS, enqueued with zero script dependencies) so it can't be taken
		// down by a jQuery/wp-color-picker load failure on this page.

		// ─────────────────────────────────────────────────────────────────────
		// 2. Settings: Conditional Fields Visibility
		// ─────────────────────────────────────────────────────────────────────
		// OTP Provider.
		$('#ggm-otp-provider').on('change', function() {
			var selectedProvider = $(this).val();
			$('.ggm-provider-credentials-block').hide();
			$('#ggm-otp-block-' + selectedProvider).fadeIn(150);
		});

		// Razorpay Mode.
		$('#ggm-razorpay-mode').on('change', function() {
			var selectedMode = $(this).val();
			$('.ggm-razorpay-credentials-block').hide();
			$('#ggm-razorpay-block-' + selectedMode).fadeIn(150);
		});

		// ─────────────────────────────────────────────────────────────────────
		// 3. Test Email OTP button
		// ─────────────────────────────────────────────────────────────────────
		$('#ggm-send-test-otp-btn').on('click', function() {
			var $btn      = $(this);
			var $spinner  = $('#ggm-test-otp-spinner');
			var $feedback = $('#ggm-test-otp-feedback');
			var email     = $('#ggm-test-otp-email').val().trim();

			if ( ! email ) {
				$feedback.css('color', '#c0392b').text('Please enter an email address.');
				return;
			}

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$feedback.text('').css('color', '');

			$.ajax({
				url:      ggmAdmin.ajaxurl,
				type:     'POST',
				dataType: 'json',
				data: {
					action: 'ggm_send_test_otp',
					nonce:  ggmAdmin.nonce,
					email:  email
				},
				success: function(response) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					if (response.success) {
						$feedback.css('color', '#0e9e6e').text(response.data.message);
					} else {
						$feedback.css('color', '#c0392b').text(response.data.message || 'Failed to send.');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					$feedback.css('color', '#c0392b').text('Server error. Please try again.');
				}
			});
		});


		// ─────────────────────────────────────────────────────────────────────
		// 4. Settings Form Submit (AJAX)
		// ─────────────────────────────────────────────────────────────────────
		$('#ggm-settings-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $submit = $('#ggm-settings-submit');
			var $spinner = $('#ggm-settings-spinner');
			var $feedback = $('#ggm-settings-feedback');

			// Reset feedback.
			$feedback.hide().removeClass('success error');
			$submit.prop('disabled', true);
			$spinner.addClass('is-active');

			// Sync TinyMCE editors back to their hidden textareas before serializing.
			if (typeof tinyMCE !== 'undefined') {
				tinyMCE.triggerSave();
			}

			var formData = $form.serialize() + '&nonce=' + ggmAdmin.nonce + '&action=ggm_save_settings';

			$.ajax({
				url: ggmAdmin.ajaxurl,
				type: 'POST',
				data: formData,
				dataType: 'json',
				success: function(response) {
					$submit.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						$feedback.addClass('success').html(response.data.message).fadeIn();
					} else {
						$feedback.addClass('error').html(response.data.message || 'An error occurred.').fadeIn();
					}
				},
				error: function() {
					$submit.prop('disabled', false);
					$spinner.removeClass('is-active');
					$feedback.addClass('error').html('Server connection failed.').fadeIn();
				}
			});
		});

		// Media Library Uploader for Settings
		$(document).on('click', '.ggm-media-upload-btn', function(e) {
			e.preventDefault();
			var button = $(this);
			var targetId = button.data('target');
			var targetInput = $('#' + targetId);

			var file_frame = wp.media.frames.file_frame = wp.media({
				title: 'Select Image',
				button: { text: 'Use Image' },
				multiple: false
			});

			file_frame.on('select', function() {
				var attachment = file_frame.state().get('selection').first().toJSON();
				targetInput.val(attachment.url);
				$('#' + targetId + '-preview').attr('src', attachment.url).show();
			});

			file_frame.open();
		});

		$(document).on('click', '.ggm-media-clear-btn', function(e) {
			e.preventDefault();
			var targetId = $(this).data('target');
			$('#' + targetId).val('');
			$('#' + targetId + '-preview').hide();
		});

	});
})(jQuery);
