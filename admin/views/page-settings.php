<?php
/**
 * DZ LMS Settings Frame.
 *
 * Scaffolds the tab wrappers, settings sub-forms, and AJAX submit handlers.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'ggm_settings', array() );
?>
<div class="wrap ggm-admin-wrap">
	<h1 class="ggm-admin-title"><?php esc_html_e( 'DZ LMS Settings', 'ggm-member-dashboard' ); ?></h1>
	<p class="ggm-admin-subtitle"><?php esc_html_e( 'Configure email OTP, checkout redirection, payments, SMTP, and email layouts.', 'ggm-member-dashboard' ); ?></p>

	<!-- Tabs Navigation -->
	<nav class="nav-tab-wrapper ggm-nav-tab-wrapper" style="margin-bottom: 20px;">
		<a href="#ggm-tab-otp" class="nav-tab nav-tab-active" data-tab="otp"><?php esc_html_e( 'Email OTP', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-pages" class="nav-tab" data-tab="pages"><?php esc_html_e( 'Page Routing', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-razorpay" class="nav-tab" data-tab="razorpay"><?php esc_html_e( 'Razorpay Checkout', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-multicurrency" class="nav-tab" data-tab="multicurrency"><?php esc_html_e( 'Multi Currency', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-ghl" class="nav-tab" data-tab="ghl"><?php esc_html_e( 'GHL Integration', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-smtp" class="nav-tab" data-tab="smtp"><?php esc_html_e( 'SMTP Settings', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-emails" class="nav-tab" data-tab="emails"><?php esc_html_e( 'Email Templates', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-custom" class="nav-tab" data-tab="custom"><?php esc_html_e( 'Customizations & Shortcodes', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-workshop-sc" class="nav-tab" data-tab="workshop-sc"><?php esc_html_e( 'Workshop Shortcodes', 'ggm-member-dashboard' ); ?></a>
		<a href="#ggm-tab-error-log" class="nav-tab" data-tab="error-log"><?php esc_html_e( 'Error Log', 'ggm-member-dashboard' ); ?></a>
	</nav>

	<div class="ggm-dashboard-card">
		<div class="card-body">
			<form id="ggm-settings-form" method="post">
				<!-- Tab Contents -->
				<div id="ggm-tab-otp-content" class="ggm-tab-content active">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-otp.php'; ?>
				</div>

				<div id="ggm-tab-pages-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-pages.php'; ?>
				</div>

				<div id="ggm-tab-razorpay-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-razorpay.php'; ?>
				</div>

				<div id="ggm-tab-multicurrency-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-multicurrency.php'; ?>
				</div>

				<div id="ggm-tab-ghl-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-ghl.php'; ?>
				</div>

				<div id="ggm-tab-smtp-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-smtp.php'; ?>
				</div>

				<div id="ggm-tab-emails-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-emails.php'; ?>
				</div>

				<div id="ggm-tab-custom-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-customizations.php'; ?>
				</div>

				<div id="ggm-tab-workshop-sc-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-workshop-shortcodes.php'; ?>
				</div>

				<div id="ggm-tab-error-log-content" class="ggm-tab-content" style="display: none;">
					<?php include GGM_PLUGIN_DIR . 'admin/views/partials/settings-error-log.php'; ?>
				</div>

				<!-- Form Controls -->
				<div class="ggm-form-submit" style="margin-top: 30px; border-top: 1px solid #e0e0e0; padding-top: 20px;">
					<input type="submit" id="ggm-settings-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save DZ LMS Settings', 'ggm-member-dashboard' ); ?>">
					<span class="spinner" id="ggm-settings-spinner"></span>
				</div>
				<div id="ggm-settings-feedback" class="ggm-feedback-msg" style="margin-top:15px; display:none;"></div>
			</form>
		</div>
	</div>
</div>
<?php
/*
 * Tab-switching JS, printed inline rather than via wp_enqueue_script().
 *
 * Diagnostic history: this screen's tab clicks did nothing even after
 * moving the logic to a zero-dependency script with its own enqueue call
 * (no jQuery, no wp-color-picker) — it failed identically to the original
 * jQuery version, which ruled out every dependency-chain theory. With two
 * independently-enqueued scripts both silently absent from execution, the
 * common factor is wp_enqueue_script() itself: something on this install
 * (a script-optimization/defer/combine plugin filtering the $wp_scripts
 * queue) is dropping our handle before it prints. Printing the same logic
 * as a literal inline <script> tag in this template's own HTML output
 * sidesteps the queue entirely — there is no handle for a queue-filter to
 * recognize and drop.
 */
?>
<script>
( function () {
	'use strict';

	var wrapper = document.querySelector( '.ggm-nav-tab-wrapper' );
	if ( ! wrapper ) {
		return;
	}

	var tabs = Array.prototype.slice.call( wrapper.querySelectorAll( '.nav-tab' ) );
	if ( ! tabs.length ) {
		return;
	}

	function panelFor( tab ) {
		return document.getElementById( 'ggm-tab-' + tab.getAttribute( 'data-tab' ) + '-content' );
	}

	wrapper.setAttribute( 'role', 'tablist' );
	tabs.forEach( function ( tab ) {
		tab.setAttribute( 'role', 'tab' );
		var panel = panelFor( tab );
		if ( panel ) {
			panel.setAttribute( 'role', 'tabpanel' );
		}
	} );

	function tabForHash() {
		var slug = window.location.hash.replace( '#tab-', '' );
		if ( ! slug ) {
			return null;
		}
		return tabs.filter( function ( t ) {
			return t.getAttribute( 'data-tab' ) === slug;
		} )[ 0 ] || null;
	}

	function activate( tab ) {
		if ( ! tab ) {
			return;
		}

		tabs.forEach( function ( t ) {
			var isActive = t === tab;
			t.classList.toggle( 'nav-tab-active', isActive );
			t.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			t.setAttribute( 'tabindex', isActive ? '0' : '-1' );

			var panel = panelFor( t );
			if ( panel ) {
				panel.classList.toggle( 'active', isActive );
				panel.style.display = isActive ? 'block' : 'none';
				panel.hidden = ! isActive;
			}
		} );

		if ( 'emails' === tab.getAttribute( 'data-tab' ) && typeof tinyMCE !== 'undefined' ) {
			window.setTimeout( function () {
				[ 'ggm_otp_email_body', 'ggm_welcome_email_body' ].forEach( function ( id ) {
					var editor = tinyMCE.get( id );
					if ( editor ) {
						editor.execCommand( 'mceRepaint' );
					}
				} );
			}, 50 );
		}
	}

	function goTo( tab ) {
		var hash = '#tab-' + tab.getAttribute( 'data-tab' );
		if ( window.location.hash === hash ) {
			activate( tab );
		} else {
			window.location.hash = hash;
		}
	}

	function syncFromHash() {
		activate( tabForHash() || wrapper.querySelector( '.nav-tab-active' ) || tabs[ 0 ] );
	}

	wrapper.addEventListener( 'click', function ( event ) {
		var tab = event.target.closest( '.nav-tab' );
		if ( ! tab || ! wrapper.contains( tab ) ) {
			return;
		}
		event.preventDefault();
		goTo( tab );
		tab.focus();
	} );

	wrapper.addEventListener( 'keydown', function ( event ) {
		if ( 'ArrowRight' !== event.key && 'ArrowLeft' !== event.key ) {
			return;
		}
		var current = tabs.indexOf( document.activeElement );
		if ( -1 === current ) {
			return;
		}
		event.preventDefault();
		var delta = 'ArrowRight' === event.key ? 1 : -1;
		var next  = tabs[ ( current + delta + tabs.length ) % tabs.length ];
		goTo( next );
		next.focus();
	} );

	window.addEventListener( 'hashchange', syncFromHash );

	syncFromHash();
}() );
</script>
