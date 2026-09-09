<?php
/** Dashboard tab showing diseases selected in form responses. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$diseases = class_exists( 'GGM_Diseases' ) ? GGM_Diseases::selected_for_user( get_current_user_id() ) : array();
?>
<div class="ggm-section-header">
	<div>
		<h2><?php esc_html_e( 'Disease', 'ggm-member-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'Diseases selected from your submitted forms are shown here.', 'ggm-member-dashboard' ); ?></p>
	</div>
</div>

<?php if ( $diseases ) : ?>
	<div class="ggm-disease-dashboard-list" style="align-items:start;grid-auto-rows:auto;">
		<?php foreach ( $diseases as $index => $disease ) : ?>
			<?php
			$body_id    = 'ggm-disease-card-body-' . absint( $disease->id ?: $index );
			$card_style = 'align-self:start;display:flex;flex-direction:column;min-width:0;height:450px!important;max-height:450px!important;overflow:hidden!important;';
			$body_style = 'position:relative;flex:1 1 auto;min-height:0;overflow:hidden!important;';
			?>
			<article
				class="ggm-disease-dashboard-card has-more"
				style="<?php echo esc_attr( $card_style ); ?>"
				data-collapsed-height="450"
			>
				<h3><?php echo esc_html( $disease->title ); ?></h3>
				<div id="<?php echo esc_attr( $body_id ); ?>" class="ggm-disease-dashboard-body" style="<?php echo esc_attr( $body_style ); ?>">
					<?php echo wp_kses_post( wpautop( $disease->description ) ); ?>
				</div>
				<button
					type="button"
					class="ggm-disease-dashboard-toggle"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $body_id ); ?>"
					data-read-more="<?php esc_attr_e( 'Read more', 'ggm-member-dashboard' ); ?>"
					data-read-less="<?php esc_attr_e( 'Read less', 'ggm-member-dashboard' ); ?>"
					style="align-self:flex-start;flex:0 0 auto;margin-top:12px;padding:8px 14px;border:0;border-radius:7px;background:var(--ggm-dash-primary,#0e9e6e);color:#fff;font:inherit;font-size:13px;font-weight:700;line-height:1.2;cursor:pointer;"
				>
					<?php esc_html_e( 'Read more', 'ggm-member-dashboard' ); ?>
				</button>
			</article>
		<?php endforeach; ?>
	</div>
	<style>
		#tab-disease .ggm-disease-dashboard-list {
			align-items: start !important;
			grid-auto-rows: auto !important;
		}
		#tab-disease .ggm-disease-dashboard-card {
			align-self: start !important;
			display: flex !important;
			flex-direction: column !important;
			min-width: 0;
		}
		#tab-disease .ggm-disease-dashboard-card.has-more:not(.is-expanded) {
			height: 450px !important;
			max-height: 450px !important;
			overflow: hidden !important;
		}
		#tab-disease .ggm-disease-dashboard-body {
			position: relative;
			min-height: 0;
		}
		#tab-disease .ggm-disease-dashboard-card.has-more:not(.is-expanded) .ggm-disease-dashboard-body {
			flex: 1 1 auto;
			max-height: none !important;
			overflow: hidden !important;
		}
		#tab-disease .ggm-disease-dashboard-card.has-more:not(.is-expanded) .ggm-disease-dashboard-body::after {
			content: "";
			position: absolute;
			right: 0;
			bottom: 0;
			left: 0;
			height: 56px;
			background: linear-gradient(to bottom, rgba(255,255,255,0), #fff);
			pointer-events: none;
		}
		#tab-disease .ggm-disease-dashboard-card.is-expanded {
			height: auto !important;
			max-height: none !important;
			overflow: visible !important;
		}
		#tab-disease .ggm-disease-dashboard-card.is-expanded .ggm-disease-dashboard-body {
			flex: 0 1 auto;
			max-height: none !important;
			overflow: visible !important;
		}
		#tab-disease .ggm-disease-dashboard-toggle {
			align-self: flex-start;
			flex: 0 0 auto;
			margin-top: 12px;
			padding: 8px 14px;
			border: 0;
			border-radius: 7px;
			background: var(--ggm-dash-primary, #0e9e6e);
			color: #fff;
			font: inherit;
			font-size: 13px;
			font-weight: 700;
			line-height: 1.2;
			cursor: pointer;
		}
		#tab-disease .ggm-disease-dashboard-toggle:hover,
		#tab-disease .ggm-disease-dashboard-toggle:focus {
			background: #087d58;
			color: #fff;
		}
		#tab-disease .ggm-disease-dashboard-card:not(.has-more) .ggm-disease-dashboard-body {
			max-height: none !important;
			overflow: visible !important;
		}
	</style>
	<script>
	(function(){
		var root = document.getElementById('tab-disease');
		if (!root) return;
		var readMore = <?php echo wp_json_encode( __( 'Read more', 'ggm-member-dashboard' ) ); ?>;
		var readLess = <?php echo wp_json_encode( __( 'Read less', 'ggm-member-dashboard' ) ); ?>;

		root.querySelectorAll('.ggm-disease-dashboard-card').forEach(function(card, index){
			var body = card.querySelector('.ggm-disease-dashboard-body') || card.querySelector('h3 + div');
			if (!body) return;
			body.classList.add('ggm-disease-dashboard-body');
			if (!body.id) body.id = 'ggm-disease-card-body-runtime-' + index;

			var button = card.querySelector('.ggm-disease-dashboard-toggle');
			if (!button) {
				button = document.createElement('button');
				button.type = 'button';
				button.className = 'ggm-disease-dashboard-toggle';
				button.setAttribute('aria-expanded', 'false');
				button.setAttribute('aria-controls', body.id);
				button.dataset.readMore = readMore;
				button.dataset.readLess = readLess;
				button.textContent = readMore;
				card.appendChild(button);
			}

			card.classList.add('has-more');
			card.classList.remove('is-expanded');
			card.style.alignSelf = 'start';
			card.style.display = 'flex';
			card.style.flexDirection = 'column';
			card.style.height = '450px';
			card.style.maxHeight = '450px';
			card.style.overflow = 'hidden';
			body.style.flex = '1 1 auto';
			body.style.minHeight = '0';
			body.style.overflow = 'hidden';
			button.hidden = false;
			button.setAttribute('aria-expanded', 'false');
			button.textContent = button.dataset.readMore || readMore;
		});

		if (!root.dataset.diseaseToggleBound) {
			root.dataset.diseaseToggleBound = '1';
			root.addEventListener('click', function(event){
				var button = event.target.closest('.ggm-disease-dashboard-toggle');
				if (!button || !root.contains(button)) return;
				event.preventDefault();
				event.stopPropagation();
				var card = button.closest('.ggm-disease-dashboard-card');
				if (!card) return;
				var body = card.querySelector('.ggm-disease-dashboard-body');
				var expanded = button.getAttribute('aria-expanded') === 'true';
				if (expanded) {
					card.classList.remove('is-expanded');
					card.style.height = '450px';
					card.style.maxHeight = '450px';
					card.style.overflow = 'hidden';
					if (body) {
						body.style.flex = '1 1 auto';
						body.style.overflow = 'hidden';
					}
				} else {
					card.classList.add('is-expanded');
					card.style.height = 'auto';
					card.style.maxHeight = 'none';
					card.style.overflow = 'visible';
					if (body) {
						body.style.flex = '0 1 auto';
						body.style.maxHeight = 'none';
						body.style.overflow = 'visible';
					}
				}
				button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
				button.textContent = expanded ? (button.dataset.readMore || readMore) : (button.dataset.readLess || readLess);
			});
		}
	})();
	</script>
<?php else : ?>
	<div class="ggm-empty-state ggm-dashboard-form-empty">
		<span class="dashicons dashicons-heart" aria-hidden="true"></span>
		<h3><?php esc_html_e( 'No disease selected yet', 'ggm-member-dashboard' ); ?></h3>
		<p><?php esc_html_e( 'After you select diseases in a form, their details will appear here.', 'ggm-member-dashboard' ); ?></p>
	</div>
<?php endif; ?>
