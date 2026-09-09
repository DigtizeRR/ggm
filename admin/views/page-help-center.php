<?php
/**
 * GGM LMS Help Center — end-to-end documentation for how the plugin works.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ggm-member-dashboard' ) );
}

$settings_url = admin_url( 'admin.php?page=ggm-lms-settings' );
$workshops_url = admin_url( 'edit.php?post_type=workshop' );
$courses_url  = admin_url( 'edit.php?post_type=course' );
$mentors_url  = admin_url( 'edit.php?post_type=ggm_mentor' );
$coupons_url  = admin_url( 'admin.php?page=ggm-lms-coupons' );
$payments_url = admin_url( 'admin.php?page=ggm-lms-payments' );
$users_url    = admin_url( 'admin.php?page=ggm-lms-users' );
?>
<div class="wrap ggm-help-wrap">
	<h1><?php esc_html_e( 'GGM LMS — Help Center', 'ggm-member-dashboard' ); ?></h1>
	<p style="color:#666; max-width:820px;">
		<?php esc_html_e( 'Everything the plugin does, end to end: how members log in, how workshops and courses work, how payments and access are handled, and every setting available. Use the menu on the left to jump to a section.', 'ggm-member-dashboard' ); ?>
	</p>

	<div class="ggm-help-layout">
		<!-- ── Sticky table of contents ─────────────────────────────────────── -->
		<nav class="ggm-help-toc">
			<strong><?php esc_html_e( 'Contents', 'ggm-member-dashboard' ); ?></strong>
			<ul>
				<li><a href="#overview"><?php esc_html_e( 'Overview', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#quick-start"><?php esc_html_e( 'Quick Start Checklist', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#how-it-works"><?php esc_html_e( 'How It Works (Member Journey)', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#workshops"><?php esc_html_e( 'Creating a Workshop', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#time-slots"><?php esc_html_e( 'Time Slots & Live Sessions', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#pricing"><?php esc_html_e( 'Pricing & Currency', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#courses"><?php esc_html_e( 'Creating a Course & Lessons', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#mentors"><?php esc_html_e( 'Mentors', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#coupons"><?php esc_html_e( 'Coupons', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#payments"><?php esc_html_e( 'Checkout & Payments', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#dashboard-member"><?php esc_html_e( 'The Member Dashboard', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#shortcodes"><?php esc_html_e( 'Shortcodes Reference', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#settings-ref"><?php esc_html_e( 'Settings Reference', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#email-otp"><?php esc_html_e( 'Email OTP Login Setup', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#troubleshooting"><?php esc_html_e( 'Troubleshooting & Error Log', 'ggm-member-dashboard' ); ?></a></li>
				<li><a href="#faq"><?php esc_html_e( 'FAQ', 'ggm-member-dashboard' ); ?></a></li>
			</ul>
		</nav>

		<!-- ── Content ───────────────────────────────────────────────────────── -->
		<div class="ggm-help-content">

			<section id="overview" class="ggm-help-section">
				<h2><?php esc_html_e( 'Overview', 'ggm-member-dashboard' ); ?></h2>
				<p><?php esc_html_e( 'GGM Member Dashboard is a self-contained learning/workshop platform built into WordPress. It does not depend on WooCommerce, MemberPress, LearnDash, or any other plugin — authentication, payments, access control, and the member dashboard are all handled directly by this plugin.', 'ggm-member-dashboard' ); ?></p>
				<p><?php esc_html_e( 'The five moving parts:', 'ggm-member-dashboard' ); ?></p>
				<ul class="ggm-help-list">
					<li><strong><?php esc_html_e( 'Login', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'members enter their phone number or email, then receive the OTP only at the matched account\'s registered email address.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Workshops', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'a custom post type for live/recurring sessions with their own pricing, time slots, and Zoom/Meet links.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Courses & Lessons', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'a custom post type with an ordered list of video lessons, sold individually or bundled with a workshop.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Checkout', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'a single Razorpay-powered checkout page for both workshops and courses, with coupon support.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Member Dashboard', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'the logged-in area where a member sees what they purchased, watches lessons, and joins live sessions.', 'ggm-member-dashboard' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Everything is placed on your site via shortcodes on ordinary WordPress pages (Login page, Dashboard page, Checkout page) — there is no dedicated theme required.', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="quick-start" class="ggm-help-section">
				<h2><?php esc_html_e( 'Quick Start Checklist', 'ggm-member-dashboard' ); ?></h2>
				<ol class="ggm-help-steps">
					<li>
						<strong><?php esc_html_e( 'Create three pages', 'ggm-member-dashboard' ); ?></strong> —
						<?php esc_html_e( 'a Login page with the shortcode', 'ggm-member-dashboard' ); ?> <code>[ggm_login]</code>,
						<?php esc_html_e( 'a Dashboard page with', 'ggm-member-dashboard' ); ?> <code>[ggm_dashboard]</code>,
						<?php esc_html_e( 'and a Checkout page (content can stay empty — the plugin renders the checkout form automatically).', 'ggm-member-dashboard' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Point the plugin at those pages', 'ggm-member-dashboard' ); ?></strong> —
						<?php echo wp_kses_post( sprintf( /* translators: %s: settings link */ __( 'go to %s → "Page Routing" tab and choose the Login, Dashboard, and Checkout pages you just made.', 'ggm-member-dashboard' ), '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'ggm-member-dashboard' ) . '</a>' ) ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Configure OTP delivery', 'ggm-member-dashboard' ); ?></strong> —
						<?php esc_html_e( 'in Settings → "Email OTP", set the code expiry and request limit. SMS, WhatsApp, and production demo-code delivery are disabled.', 'ggm-member-dashboard' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Configure Razorpay', 'ggm-member-dashboard' ); ?></strong> —
						<?php esc_html_e( 'in Settings → "Razorpay Checkout", paste your Test (or Live) Key ID and Key Secret. Nothing else is required for payments to work — this plugin talks to Razorpay directly.', 'ggm-member-dashboard' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Set your currency', 'ggm-member-dashboard' ); ?></strong> —
						<?php esc_html_e( 'in Settings → "Customizations & Shortcodes", set the default Currency Symbol/Code (each workshop can still override its own currency individually).', 'ggm-member-dashboard' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Create your first Workshop or Course', 'ggm-member-dashboard' ); ?></strong> —
						<?php echo wp_kses_post( sprintf( /* translators: 1: workshops link, 2: courses link */ __( 'see %1$s or %2$s below.', 'ggm-member-dashboard' ), '<a href="#workshops">' . esc_html__( 'Creating a Workshop', 'ggm-member-dashboard' ) . '</a>', '<a href="#courses">' . esc_html__( 'Creating a Course', 'ggm-member-dashboard' ) . '</a>' ) ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Configure Custom SMTP', 'ggm-member-dashboard' ); ?></strong> —
						<?php esc_html_e( 'OTP login fails closed unless Custom SMTP is enabled and working. Enter your provider details, save them, and send both the SMTP test and Email OTP test before going live.', 'ggm-member-dashboard' ); ?>
					</li>
				</ol>
			</section>

			<section id="how-it-works" class="ggm-help-section">
				<h2><?php esc_html_e( 'How It Works — the Member Journey', 'ggm-member-dashboard' ); ?></h2>
				<ol class="ggm-help-steps">
					<li><strong><?php esc_html_e( 'Visitor lands on a Workshop or Course page', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'built with the workshop/course shortcodes (date, price, curriculum, FAQ, etc.).', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'They click Enroll / Join Now', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'this sends them to the Checkout page with the workshop or course ID in the URL.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Guest verification', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'if an existing member is not logged in, checkout can identify the account by phone while the one-time code is delivered only to that account\'s registered email.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Payment', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'Pay Now opens the real Razorpay popup for the resolved price (Sale Price if set, otherwise Regular Price, minus any coupon).', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Access is granted', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'on successful payment, the plugin records the purchase and the member is redirected straight to their Dashboard page — never to wp-admin.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'They watch/attend', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'from the Dashboard, a purchased workshop shows a "Start Learning" button that unlocks automatically at the right time (see Time Slots below); a purchased course shows its lesson list to watch in order.', 'ggm-member-dashboard' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'Free workshops/courses skip the payment step entirely — access is granted immediately.', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="workshops" class="ggm-help-section">
				<h2><?php esc_html_e( 'Creating a Workshop', 'ggm-member-dashboard' ); ?></h2>
				<p>
					<?php echo wp_kses_post( sprintf( /* translators: %s: workshops link */ __( 'Go to %s → Add New. The "Workshop Details" meta box has every field below.', 'ggm-member-dashboard' ), '<a href="' . esc_url( $workshops_url ) . '">' . esc_html__( 'DZ LMS → Workshops', 'ggm-member-dashboard' ) . '</a>' ) ); ?>
				</p>
				<table class="widefat striped ggm-help-table">
					<thead><tr><th><?php esc_html_e( 'Field', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'What it\'s for', 'ggm-member-dashboard' ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Title / Content / Featured Image', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Standard WordPress fields — title, main body copy, and the image shown everywhere this workshop appears (cards, dashboard, single page).', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Mentor', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Optional. Pick a mentor created under DZ LMS → Mentors; their photo/bio can be shown via [ggm_workshop_mentor].', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Linked Course', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Optional. Attach a follow-up Course — shown on the workshop page and the dashboard as "Includes Course".', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Counter Start Date & Time', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Only used by the [ggm_workshop_countdown] marketing countdown widget — separate from the real Start Learning logic.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Preparatory Date', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Optional additional row included by “date” in [ggm_workshop_icon_list]. When left empty, that row is not shown. Use fields="preparatory" to show it by itself.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Workshop Date Range', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Choose the start and end together. Time Slots repeat every day inside this range. Once the end passes, the workshop is treated as finished.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Currency / Regular Price / Sale Price', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'See "Pricing & Currency" below.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Workshop Mode', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Free-text, e.g. "Zoom" or "In-person" — purely informational, shown on cards/pages.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Workshop Language', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'English or Hindi — shown on the dashboard card and via [ggm_workshop_icon_list fields="...,language"].', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Duration', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Free-text, e.g. "21 Days" — shown when no time slots are configured, and used by [ggm_workshop_heading].', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Is Free', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Check to skip payment entirely for this workshop.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Workshop Time Slots', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'See "Time Slots & Live Sessions" below — this is the most important part of setting up a live workshop.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'You Will Discover / Why This Webinar Is Different / Perfect For You / FAQ', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Optional marketing content blocks (each its own repeater with an "+ Add" button) rendered by the matching shortcodes on the workshop\'s single page. Each block also has an optional heading override; leave it blank to inherit the current default from Settings → Workshop Shortcodes.', 'ggm-member-dashboard' ); ?></td></tr>
					</tbody>
				</table>
				<p><?php esc_html_e( 'Publish the workshop, then build its public page using the Workshop Shortcodes (see Shortcodes Reference) — or just rely on the Dashboard card, which needs no extra page building.', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="time-slots" class="ggm-help-section">
				<h2><?php esc_html_e( 'Time Slots & Live Sessions — how the countdown really works', 'ggm-member-dashboard' ); ?></h2>
				<p><?php esc_html_e( 'A workshop can have any number of Time Slots, each with its own Type (Live / Repeat / Morning Repeat — these are just labels for your own organization, not different behaviors), a Start Time and End Time, and its own Zoom/Google Meet link.', 'ggm-member-dashboard' ); ?></p>
				<p><strong><?php esc_html_e( 'Key idea: a slot is a daily-recurring time, not a one-off date.', 'ggm-member-dashboard' ); ?></strong> <?php esc_html_e( 'If you set a slot to "3:00 PM – 4:00 PM", that session happens every single day from the Workshop Start Date through the Workshop End Date. You never set a date on the slot itself — only the workshop\'s overall Start/End Date decide how many days it repeats for.', 'ggm-member-dashboard' ); ?></p>
				<p><?php esc_html_e( 'On the member Dashboard, each purchased workshop shows one "Start Learning" button that always reflects whichever slot is current or coming up next:', 'ggm-member-dashboard' ); ?></p>
				<ul class="ggm-help-list">
					<li><strong><?php esc_html_e( 'More than 1 hour before the next session', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'button is disabled and reads "Start Soon".', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Within 1 hour of start', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'button shows a live ticking countdown, e.g. "Starts in 59:42".', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( '10 minutes before start, through the end time', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'button becomes active and clicking it opens that slot\'s Zoom/Meet link directly.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'After a session ends', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'the button automatically moves on to whichever slot is next (that same day, or the next day if all of today\'s slots are done).', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'After the Workshop End Date\'s final session', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'the button stays active for 30 more days, on the assumption a recording gets posted to the same Zoom/Meet link — only after those 30 days does it finally switch to "Workshop Completed".', 'ggm-member-dashboard' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'This is all computed on the server using your site\'s configured WordPress timezone (Settings → General → Timezone), never the visitor\'s own device clock — so it\'s accurate no matter where a buyer is.', 'ggm-member-dashboard' ); ?></p>
				<p><strong><?php esc_html_e( 'A purchased workshop never disappears from a member\'s dashboard', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'even long after it has ended, it stays visible showing "Workshop Completed". Only workshops a visitor has NOT purchased get hidden 30 days after their End Date (nothing left to browse/buy).', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="pricing" class="ggm-help-section">
				<h2><?php esc_html_e( 'Pricing & Currency', 'ggm-member-dashboard' ); ?></h2>
				<p><?php esc_html_e( 'Each Workshop has its own Currency (₹, $, €, £, AED, SAR, AUD, CAD), Regular Price, and an optional Sale Price.', 'ggm-member-dashboard' ); ?></p>
				<ul class="ggm-help-list">
					<li><?php esc_html_e( 'If a Sale Price is set (and is lower than the Regular Price), the frontend shows the Regular Price struck through next to the Sale Price — the amount actually charged is the Sale Price.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'If Sale Price is left blank, only the Regular Price shows, and that\'s what is charged.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'If both are blank/zero, the workshop is treated as free.', 'ggm-member-dashboard' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Courses use a single Course Price field (text, so you can also type "Free") — Courses do not currently have the Regular/Sale two-price display that Workshops have.', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="courses" class="ggm-help-section">
				<h2><?php esc_html_e( 'Creating a Course & Lessons', 'ggm-member-dashboard' ); ?></h2>
				<p>
					<?php echo wp_kses_post( sprintf( /* translators: %s: courses link */ __( 'Go to %s → Add New. Fill in the "Course Details" meta box:', 'ggm-member-dashboard' ), '<a href="' . esc_url( $courses_url ) . '">' . esc_html__( 'DZ LMS → Courses', 'ggm-member-dashboard' ) . '</a>' ) ); ?>
				</p>
				<table class="widefat striped ggm-help-table">
					<thead><tr><th><?php esc_html_e( 'Field', 'ggm-member-dashboard' ); ?></th><th><?php esc_html_e( 'What it\'s for', 'ggm-member-dashboard' ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Course Price', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Type a number, or "Free" / 0 to make it free for everyone.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Course Short Description', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Shown on course cards.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Total Duration / Lessons Label', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Free-text badges, e.g. "22 days" and "13 video lessons".', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Instructor Name / Language', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'Free-text, purely informational.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Course Thumbnail', 'ggm-member-dashboard' ); ?></td><td><?php esc_html_e( 'A dedicated image field for course cards (separate from the Featured Image).', 'ggm-member-dashboard' ); ?></td></tr>
					</tbody>
				</table>
				<p><strong><?php esc_html_e( 'Adding lessons:', 'ggm-member-dashboard' ); ?></strong> <?php esc_html_e( 'save the course once first, then a "Course Lessons" box appears below. Click "+ Add New Lesson" to fill in a title, status, order, duration, whether it\'s a free preview, and paste a video embed. Lessons can be dragged to reorder, edited inline, or moved with the ↑/↓ buttons — no need to leave the course screen.', 'ggm-member-dashboard' ); ?></p>
				<p><?php esc_html_e( 'A "Free Preview" lesson is watchable by anyone, even without purchasing the course — useful as a taster lesson.', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="mentors" class="ggm-help-section">
				<h2><?php esc_html_e( 'Mentors', 'ggm-member-dashboard' ); ?></h2>
				<p>
					<?php echo wp_kses_post( sprintf( /* translators: %s: mentors link */ __( 'Go to %s → Add New. Give the mentor a Title (their name), a Photo, and a Bio.', 'ggm-member-dashboard' ), '<a href="' . esc_url( $mentors_url ) . '">' . esc_html__( 'DZ LMS → Mentors', 'ggm-member-dashboard' ) . '</a>' ) ); ?>
					<?php esc_html_e( 'Then assign that mentor to any workshop via its "Mentor" field, and show their photo/bio on the workshop page with the [ggm_workshop_mentor] shortcode.', 'ggm-member-dashboard' ); ?>
				</p>
			</section>

			<section id="coupons" class="ggm-help-section">
				<h2><?php esc_html_e( 'Coupons', 'ggm-member-dashboard' ); ?></h2>
				<p>
					<?php echo wp_kses_post( sprintf( /* translators: %s: coupons link */ __( 'Manage discount codes under %s.', 'ggm-member-dashboard' ), '<a href="' . esc_url( $coupons_url ) . '">' . esc_html__( 'DZ LMS → Coupons', 'ggm-member-dashboard' ) . '</a>' ) ); ?>
				</p>
				<ul class="ggm-help-list">
					<li><?php esc_html_e( 'Discount Type: Fixed Amount or Percentage (with an optional Maximum Discount cap for percentage coupons).', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Minimum/Maximum Purchase limits which order totals the coupon can apply to.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Start Date / Expiry Date control when the coupon is valid.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Maximum Uses and Maximum Uses Per User cap total redemptions.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Applicable Workshops restricts the coupon to specific workshops — leave empty to allow it on everything.', 'ggm-member-dashboard' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'A buyer enters the code on the Checkout page; usage is logged in the "Coupon Usage Reports" table on this same admin page.', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="payments" class="ggm-help-section">
				<h2><?php esc_html_e( 'Checkout & Payments (Razorpay)', 'ggm-member-dashboard' ); ?></h2>
				<p><?php esc_html_e( 'Payments are handled entirely by this plugin talking directly to Razorpay — no WooCommerce or other payment plugin is used or required.', 'ggm-member-dashboard' ); ?></p>
				<ol class="ggm-help-steps">
					<li><?php echo wp_kses_post( sprintf( /* translators: %s: settings link */ __( 'In %s → "Razorpay Checkout", choose Test Mode while setting up, and paste your Razorpay Test Key ID + Key Secret (from your Razorpay dashboard).', 'ggm-member-dashboard' ), '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'ggm-member-dashboard' ) . '</a>' ) ); ?></li>
					<li><?php esc_html_e( 'Test a full purchase end to end. Once confirmed working, switch Mode to Live and paste your Live Key ID + Key Secret.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Optional: set a Payment Webhook URL to receive a JSON notification on every payment success/failure — useful for connecting to Zapier, Google Sheets, or your own CRM.', 'ggm-member-dashboard' ); ?></li>
				</ol>
				<p><?php echo wp_kses_post( sprintf( /* translators: %s: payments link */ __( 'Every transaction (successful or not) is logged under %s, where you can filter by status, search, view full details per payment, mark a payment refunded, and export everything to CSV.', 'ggm-member-dashboard' ), '<a href="' . esc_url( $payments_url ) . '">' . esc_html__( 'DZ LMS → Payments', 'ggm-member-dashboard' ) . '</a>' ) ); ?></p>
				<p><?php echo wp_kses_post( sprintf( /* translators: %s: members link */ __( 'Everyone who has ever purchased something (or logged in via OTP) appears under %s, where you can search and view each member\'s full profile, registrations, and payment history.', 'ggm-member-dashboard' ), '<a href="' . esc_url( $users_url ) . '">' . esc_html__( 'DZ LMS → Members', 'ggm-member-dashboard' ) . '</a>' ) ); ?></p>
			</section>

			<section id="dashboard-member" class="ggm-help-section">
				<h2><?php esc_html_e( 'The Member Dashboard', 'ggm-member-dashboard' ); ?></h2>
				<p><?php esc_html_e( 'This is the logged-in area members land on after login or purchase (the page you assigned in Settings → Page Routing, using the [ggm_dashboard] shortcode). It has four tabs:', 'ggm-member-dashboard' ); ?></p>
				<ul class="ggm-help-list">
					<li><strong><?php esc_html_e( 'Home', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'a quick overview/first purchased workshop.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'My Courses', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'enrolled and available courses, with a link into the lesson player for each.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Workshops', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'free and premium workshops, each as a card showing date, time slots, mode, language, and the Start Learning / Purchase Now button described above. The eye icon on each card opens that workshop\'s full public page.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Profile', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'name, email, and WhatsApp number + country code, editable by the member.', 'ggm-member-dashboard' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'All four tab labels, and the dashboard\'s colors, can be customized in Settings → "Customizations & Shortcodes".', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="shortcodes" class="ggm-help-section">
				<h2><?php esc_html_e( 'Shortcodes Reference', 'ggm-member-dashboard' ); ?></h2>
				<p><?php esc_html_e( 'The three page-level shortcodes go on their own dedicated pages (set under Settings → Page Routing). Everything else can go anywhere — a workshop/course page, an Elementor Shortcode widget, etc.', 'ggm-member-dashboard' ); ?></p>

				<h3><?php esc_html_e( 'Page-level', 'ggm-member-dashboard' ); ?></h3>
				<table class="widefat striped ggm-help-table">
					<tbody>
						<tr><td><code>[ggm_login]</code></td><td><?php esc_html_e( 'The OTP/password login form.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_dashboard]</code></td><td><?php esc_html_e( 'The member dashboard (requires login).', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_checkout]</code></td><td><?php esc_html_e( 'The Razorpay checkout form — reads workshop_id/course_id from the URL.', 'ggm-member-dashboard' ); ?></td></tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Courses & Lessons', 'ggm-member-dashboard' ); ?></h3>
				<table class="widefat striped ggm-help-table">
					<tbody>
						<tr><td><code>[ggm_courses status="all" limit="-1" columns="3"]</code></td><td><?php esc_html_e( 'Course card grid. status: all, enrolled, or available.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_course_short_description]</code></td><td><?php esc_html_e( 'Current course short description. Use course_id="123" to show another course.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[course_curriculum]</code></td><td><?php esc_html_e( 'Grid of a course\'s lessons.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[lesson_navigation]</code></td><td><?php esc_html_e( 'Prev/Next lesson links.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[lesson_count]</code></td><td><?php esc_html_e( 'Total lesson count for a course.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[back_to_course]</code></td><td><?php esc_html_e( 'Link back to a lesson\'s parent course.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_lesson_video]</code></td><td><?php esc_html_e( 'Purchase-gated video embed.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_lesson_sidebar]</code></td><td><?php esc_html_e( 'Sidebar listing every lesson in the current course.', 'ggm-member-dashboard' ); ?></td></tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Workshops', 'ggm-member-dashboard' ); ?></h3>
				<table class="widefat striped ggm-help-table">
					<tbody>
						<tr><td><code>[ggm_workshop_date before="📅 "]</code></td><td><?php esc_html_e( 'Formatted Workshop Start Date.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_time before="🕐 "]</code></td><td><?php esc_html_e( 'Time slot range, or "Multiple time slots available".', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_price]</code></td><td><?php esc_html_e( 'Formatted price (or Free badge).', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_contribution]</code></td><td><?php esc_html_e( 'Selectable Contribution Pricing choices, including an optional Free choice, linked to the verified checkout flow.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_contribution_pills]</code></td><td><?php esc_html_e( 'Compact horizontal contribution-price pills; the default is highlighted and each option links to checkout.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_mode before="📡 "]</code></td><td><?php esc_html_e( 'e.g. Zoom / In-person.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_duration]</code></td><td><?php esc_html_e( 'The Workshop Details “Label Duration” text exactly as entered.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_linked_course]</code></td><td><?php esc_html_e( 'Link to this workshop\'s Linked Course, if any.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_slots_select]</code></td><td><?php esc_html_e( 'Time-slot picker dropdown (only renders with 2+ active slots).', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_enroll class="..."]</code></td><td><?php esc_html_e( 'State-aware CTA — Start Watching / Register Now / Enroll Now.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_discover]</code></td><td><?php esc_html_e( '"You Will Discover" grid.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_why_different]</code></td><td><?php esc_html_e( 'Resolved workshop heading plus the "Why This Webinar Is Different" grid. Do not add a separate Elementor heading above it.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_perfect_for]</code></td><td><?php esc_html_e( 'Resolved workshop heading plus the "Perfect For You If You Want To" list. Do not add a separate Elementor heading above it.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_faq]</code></td><td><?php esc_html_e( 'FAQ accordion.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_icon_list fields="date,preparatory,time,price,mode,language"]</code></td><td><?php esc_html_e( 'Compact icon list. “date” shows the workshop range and “preparatory” conditionally shows Preparatory Date with its icon. Several time slots collapse into one availability row; contribution pricing is shown as one range.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_countdown label="Starts in :"]</code></td><td><?php esc_html_e( 'Marketing countdown to the Counter Start Date & Time field.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_heading before="GGM " after=" Days Course"]</code></td><td><?php esc_html_e( 'Heading built from the Duration field\'s number.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_join_now text="Join Now"]</code></td><td><?php esc_html_e( 'Pill button linking to checkout.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_join_form button_text="Join Now"]</code></td><td><?php esc_html_e( 'Inline Name + Number bar that jumps to checkout pre-filled.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_join_form_full button_text="Pay Now"]</code></td><td><?php esc_html_e( 'Name + Phone row followed by a full-width Email row, with a pre-filled checkout handoff; contribution-priced workshops also show their configured amount choices.', 'ggm-member-dashboard' ); ?></td></tr>
						<tr><td><code>[ggm_workshop_mentor]</code></td><td><?php esc_html_e( 'Assigned mentor\'s photo/name/bio.', 'ggm-member-dashboard' ); ?></td></tr>
					</tbody>
				</table>
				<p><?php esc_html_e( 'Every workshop shortcode accepts an optional id="123" attribute — leave it out and it defaults to whichever workshop page it\'s placed on.', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="settings-ref" class="ggm-help-section">
				<h2><?php esc_html_e( 'Settings Reference', 'ggm-member-dashboard' ); ?></h2>
				<p><?php echo wp_kses_post( sprintf( /* translators: %s: settings link */ __( 'All settings live under %s, organized into tabs.', 'ggm-member-dashboard' ), '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'DZ LMS → Settings', 'ggm-member-dashboard' ) . '</a>' ) ); ?></p>

				<h3><?php esc_html_e( 'Email OTP', 'ggm-member-dashboard' ); ?></h3>
				<ul class="ggm-help-list">
					<li><?php esc_html_e( 'Members may identify an existing account by phone or email, but the code is always sent to that account\'s registered email through Custom SMTP.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'OTP Expiry (Minutes)', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'how long a sent code stays valid (default 10).', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Max OTP Requests Window', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'rate limit on how many successful OTP emails one identifier can request in a 10-minute window (default 3).', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Send Test Email OTP — sends a real six-digit code through the configured Custom SMTP transport.', 'ggm-member-dashboard' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Page Routing', 'ggm-member-dashboard' ); ?></h3>
				<ul class="ggm-help-list">
					<li><strong><?php esc_html_e( 'Dashboard Logo', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'logo shown in the member dashboard sidebar.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Login Page / Dashboard Page / Checkout Page', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'which WordPress pages hold the matching shortcode.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Redirect After Login', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'where a member lands after a normal login (default /dashboard/).', 'ggm-member-dashboard' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Razorpay Checkout', 'ggm-member-dashboard' ); ?></h3>
				<ul class="ggm-help-list">
					<li><strong><?php esc_html_e( 'Mode', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'Test or Live — switches which Key ID/Secret pair below is actually used.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Test/Live Key ID & Key Secret', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'from your Razorpay dashboard.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Payment Webhook URL', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'optional — receives a JSON POST for every payment event.', 'ggm-member-dashboard' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'SMTP Settings', 'ggm-member-dashboard' ); ?></h3>
				<ul class="ggm-help-list">
					<li><strong><?php esc_html_e( 'Enable Custom SMTP', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'required for OTP delivery and used for all other plugin emails.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Host, Port, Encryption, Authentication, Username, Password, From Email, From Name — standard SMTP fields.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Send Test Email — confirms the transport works before relying on OTP login.', 'ggm-member-dashboard' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Email Templates', 'ggm-member-dashboard' ); ?></h3>
				<ul class="ggm-help-list">
					<li><strong><?php esc_html_e( 'OTP Email Subject / Body', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'use the {otp} token to insert the code.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Welcome Email Subject / Body', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'sent to brand-new accounts; supports {name} and {email} tokens.', 'ggm-member-dashboard' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Customizations & Shortcodes', 'ggm-member-dashboard' ); ?></h3>
				<ul class="ggm-help-list">
					<li><?php esc_html_e( 'A read-only shortcode quick-reference (click any code to copy it).', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Appearance & Colors', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'separate color pickers for the Login page and the Member Dashboard (background, card background, primary/accent color).', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Tab Labels', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'rename the dashboard\'s Home / My Courses / Workshops / Profile tabs.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Currency', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'default Currency Symbol and Currency Code used site-wide unless a workshop overrides its own currency.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Course / Lesson Labels', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'rename the words "Instructor", "Duration", "Language", "Videos" wherever they appear.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Action Button Text', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'rename "Start Learning", "Watch Free", "Enroll Now".', 'ggm-member-dashboard' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Workshop Shortcodes', 'ggm-member-dashboard' ); ?></h3>
				<p><?php esc_html_e( 'A read-only reference identical to the Shortcodes Reference section above — handy to keep open in a second tab while building a workshop page in Elementor.', 'ggm-member-dashboard' ); ?></p>

				<h3><?php esc_html_e( 'Error Log', 'ggm-member-dashboard' ); ?></h3>
				<p><?php esc_html_e( 'See Troubleshooting below.', 'ggm-member-dashboard' ); ?></p>
			</section>

			<section id="email-otp" class="ggm-help-section">
				<h2><?php esc_html_e( 'Email OTP Login Setup', 'ggm-member-dashboard' ); ?></h2>
				<p><?php esc_html_e( 'A member can use phone or email on the normal login page. Workshop OTP login uses the entered registered email as the account identity and sends the code to that exact address; the phone remains contact information and cannot redirect authentication to another user.', 'ggm-member-dashboard' ); ?></p>
				<ol class="ggm-help-steps">
					<li><?php esc_html_e( 'Enable Custom SMTP and enter the host, port, encryption, authentication, sender, and credential details supplied by your mail provider.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Save settings, then use "Send Test Email" in SMTP Settings to verify the transport.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Use "Send Test Email OTP" in Email OTP to verify the actual OTP template and route.', 'ggm-member-dashboard' ); ?></li>
					<li><?php esc_html_e( 'Confirm each member account has a valid registered email. Accounts without one cannot use OTP login.', 'ggm-member-dashboard' ); ?></li>
				</ol>
			</section>

			<section id="troubleshooting" class="ggm-help-section">
				<h2><?php esc_html_e( 'Troubleshooting & Error Log', 'ggm-member-dashboard' ); ?></h2>
				<p><?php echo wp_kses_post( sprintf( /* translators: %s: settings link */ __( 'Settings → "Error Log" tab (under %s) captures PHP warnings/notices that happen while saving Workshop/Course/Lesson/Mentor fields — each entry shows the affected post, the message, extra context, and a file:line location. Use "Clear Log" to reset it.', 'ggm-member-dashboard' ), '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'ggm-member-dashboard' ) . '</a>' ) ); ?></p>
				<h3><?php esc_html_e( 'Common issues', 'ggm-member-dashboard' ); ?></h3>
				<ul class="ggm-help-list">
					<li><strong><?php esc_html_e( 'OTP never arrives', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'confirm Custom SMTP is enabled, run the SMTP and Email OTP tests, verify the matched account has a valid registered email, and inspect the Error Log for the SMTP failure stage.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Payment popup doesn\'t open / fails', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'double-check the Razorpay Key ID/Secret match the selected Mode (Test keys only work in Test Mode).', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'Emails not arriving', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'enable Custom SMTP and use "Send Test Email" to verify delivery.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'A workshop\'s repeater fields (You Will Discover, FAQ, etc.) aren\'t saving', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'check the Error Log tab right after saving — it captures the exact field and reason.', 'ggm-member-dashboard' ); ?></li>
					<li><strong><?php esc_html_e( 'A workshop disappeared from the dashboard', 'ggm-member-dashboard' ); ?></strong> — <?php esc_html_e( 'that only happens to workshops a visitor never purchased, 30 days after the End Date. A purchased workshop never disappears — check the buyer\'s Payments/Members record if something looks wrong.', 'ggm-member-dashboard' ); ?></li>
				</ul>
			</section>

			<section id="faq" class="ggm-help-section">
				<h2><?php esc_html_e( 'FAQ', 'ggm-member-dashboard' ); ?></h2>
				<p><strong><?php esc_html_e( 'Do I need WooCommerce or another LMS plugin?', 'ggm-member-dashboard' ); ?></strong><br><?php esc_html_e( 'No — login, payments, and access control are all built into this plugin.', 'ggm-member-dashboard' ); ?></p>
				<p><strong><?php esc_html_e( 'Can a workshop have more than one live session per day?', 'ggm-member-dashboard' ); ?></strong><br><?php esc_html_e( 'Yes — add as many Time Slots as you like; each recurs daily at its own time, and the dashboard always points buyers at whichever one is current or next.', 'ggm-member-dashboard' ); ?></p>
				<p><strong><?php esc_html_e( 'Can I sell a workshop and a course together?', 'ggm-member-dashboard' ); ?></strong><br><?php esc_html_e( 'Set the workshop\'s "Linked Course" field — buyers see it referenced on the workshop page and dashboard, though it is currently informational rather than auto-granting course access on workshop purchase.', 'ggm-member-dashboard' ); ?></p>
				<p><strong><?php esc_html_e( 'How do I change what "Start Learning" says?', 'ggm-member-dashboard' ); ?></strong><br><?php esc_html_e( 'Settings → Customizations & Shortcodes → Action Button Text.', 'ggm-member-dashboard' ); ?></p>
				<p><strong><?php esc_html_e( 'Where do I see who bought what?', 'ggm-member-dashboard' ); ?></strong><br><?php esc_html_e( 'DZ LMS → Payments for transactions, DZ LMS → Members for a per-person profile view.', 'ggm-member-dashboard' ); ?></p>
			</section>

		</div>
	</div>
</div>

<style>
.ggm-help-wrap { max-width: 1200px; }
.ggm-help-layout {
	display: flex;
	gap: 32px;
	margin-top: 20px;
	align-items: flex-start;
}
.ggm-help-toc {
	flex: 0 0 220px;
	position: sticky;
	top: 40px;
	background: #fff;
	border: 1px solid #e0e0e0;
	border-radius: 6px;
	padding: 16px;
}
.ggm-help-toc strong {
	display: block;
	margin-bottom: 8px;
	font-size: 13px;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #888;
}
.ggm-help-toc ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
.ggm-help-toc li {
	margin: 0;
}
.ggm-help-toc a {
	display: block;
	padding: 6px 0;
	font-size: 13px;
	text-decoration: none;
	color: #2271b1;
	border-bottom: 1px solid #f3f3f3;
}
.ggm-help-toc li:last-child a { border-bottom: none; }
.ggm-help-content {
	flex: 1 1 auto;
	min-width: 0;
	background: #fff;
	border: 1px solid #e0e0e0;
	border-radius: 6px;
	padding: 8px 32px 32px;
}
.ggm-help-section {
	padding-top: 28px;
	margin-top: 12px;
	border-top: 1px solid #f0f0f0;
}
.ggm-help-section:first-child {
	border-top: none;
}
.ggm-help-section h2 {
	font-size: 20px;
	margin-bottom: 12px;
}
.ggm-help-section h3 {
	font-size: 15px;
	margin: 20px 0 8px;
}
.ggm-help-section p {
	max-width: 860px;
	line-height: 1.6;
}
.ggm-help-list {
	max-width: 860px;
	line-height: 1.7;
}
.ggm-help-steps {
	max-width: 860px;
	line-height: 1.8;
}
.ggm-help-table {
	max-width: 900px;
	margin: 12px 0 20px;
}
.ggm-help-table th,
.ggm-help-table td {
	padding: 8px 12px;
	vertical-align: top;
	font-size: 13px;
}
.ggm-help-table td:first-child {
	white-space: nowrap;
	font-weight: 600;
}
@media (max-width: 900px) {
	.ggm-help-layout { flex-direction: column; }
	.ggm-help-toc { position: static; width: 100%; flex-basis: auto; }
}
</style>
