<?php
/**
 * Dashboard Tab — Workshops.
 * JS renders into #ggm-free-list via renderWorkshops().
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div id="ggm-free-list">
	<div class="ggm-home-section">
		<h3 class="ggm-section-title"><?php esc_html_e( 'Free Live Workshops', 'ggm-member-dashboard' ); ?></h3>
		<div class="ggm-cards-grid">
			<div class="ggm-card ggm-empty">
				<p style="color:#94a3b8; text-align:center; padding:20px 0;"><?php esc_html_e( 'Loading workshops…', 'ggm-member-dashboard' ); ?></p>
			</div>
		</div>
	</div>
</div>
