<?php
/**
 * Diseases List module.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GGM_Diseases {

	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'admin_init', $this, 'maybe_create_table' );
		$loader->add_action( 'admin_post_ggm_save_disease', $this, 'handle_save' );
		$loader->add_action( 'admin_post_ggm_delete_disease', $this, 'handle_delete' );
		$loader->add_action( 'admin_post_ggm_bulk_delete_diseases', $this, 'handle_bulk_delete' );
		$loader->add_action( 'wp_ajax_ggm_disease_tab', $this, 'ajax_tab' );
		$loader->add_action( 'wp_ajax_ggm_save_disease_ajax', $this, 'ajax_save' );
		$loader->add_action( 'wp_ajax_ggm_delete_disease_ajax', $this, 'ajax_delete' );
		$loader->add_action( 'wp_ajax_ggm_bulk_delete_diseases_ajax', $this, 'ajax_bulk_delete' );
	}

	public function maybe_create_table() {
		global $wpdb;
		$table = self::table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table && class_exists( 'GGM_Database' ) ) {
			GGM_Database::create_tables();
		}
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ggm_diseases';
	}

	public static function active_options() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT id,title FROM " . self::table() . " WHERE status='active' ORDER BY title ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$options = array();
		foreach ( (array) $rows as $row ) {
			$title = trim( (string) $row->title );
			if ( '' !== $title ) {
				$options[] = array(
					'key'   => 'disease_' . (int) $row->id,
					'label' => $title,
				);
			}
		}
		return $options;
	}

	public static function active_labels() {
		return wp_list_pluck( self::active_options(), 'label' );
	}

	public static function assigned_meta_key() {
		return 'ggm_assigned_disease_ids';
	}

	public static function active_id_options() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT id,title FROM " . self::table() . " WHERE status='active' ORDER BY title ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$options = array();
		foreach ( (array) $rows as $row ) {
			$title = trim( (string) $row->title );
			if ( '' !== $title ) {
				$options[] = array(
					'id'    => (int) $row->id,
					'title' => $title,
				);
			}
		}
		return $options;
	}

	public static function assigned_ids_for_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$ids = get_user_meta( $user_id, self::assigned_meta_key(), true );
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	}

	public static function get_by_ids( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE id IN ($placeholders) AND status='active' ORDER BY title ASC",
				$ids
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function assign_to_users( array $user_ids, array $disease_ids, $mode = 'add' ) {
		$user_ids    = array_values( array_unique( array_filter( array_map( 'absint', $user_ids ) ) ) );
		$disease_ids = array_values( array_unique( array_filter( array_map( 'absint', $disease_ids ) ) ) );
		$mode        = 'replace' === sanitize_key( $mode ) ? 'replace' : 'add';
		if ( ! $user_ids || ! $disease_ids ) {
			return 0;
		}

		$valid_ids = wp_list_pluck( self::get_by_ids( $disease_ids ), 'id' );
		$valid_ids = array_values( array_unique( array_filter( array_map( 'absint', $valid_ids ) ) ) );
		if ( ! $valid_ids ) {
			return 0;
		}

		$count = 0;
		foreach ( $user_ids as $user_id ) {
			if ( ! get_userdata( $user_id ) ) {
				continue;
			}
			$current = 'replace' === $mode ? array() : self::assigned_ids_for_user( $user_id );
			update_user_meta( $user_id, self::assigned_meta_key(), array_values( array_unique( array_merge( $current, $valid_ids ) ) ) );
			$count++;
		}
		return $count;
	}

	public static function get_by_titles( array $titles ) {
		global $wpdb;
		$clean = array();
		foreach ( $titles as $title ) {
			$title = trim( sanitize_text_field( wp_unslash( $title ) ) );
			if ( '' !== $title ) {
				$clean[ strtolower( $title ) ] = $title;
			}
		}
		if ( ! $clean ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $clean ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE LOWER(title) IN ($placeholders) AND status='active' ORDER BY title ASC",
				array_keys( $clean )
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	public static function selected_for_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT answers_json,schema_snapshot_json FROM ' . $wpdb->prefix . "ggm_form_submissions WHERE user_id=%d AND status='submitted' ORDER BY submitted_at DESC",
				$user_id
			)
		);

		$titles = array();
		foreach ( (array) $rows as $row ) {
			$answers = json_decode( (string) $row->answers_json, true );
			$schema  = json_decode( (string) $row->schema_snapshot_json, true );
			if ( ! is_array( $answers ) || ! is_array( $schema ) ) {
				continue;
			}
			foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) {
				foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
					if ( 'search_select' !== ( $field['type'] ?? '' ) || empty( $field['disease_source'] ) ) {
						continue;
					}
					$key = $field['key'] ?? '';
					foreach ( (array) ( $answers[ $key ] ?? array() ) as $title ) {
						$title = trim( sanitize_text_field( $title ) );
						if ( '' !== $title ) {
							$titles[ strtolower( $title ) ] = $title;
						}
					}
				}
			}
		}

		$diseases = array();
		foreach ( self::get_by_ids( self::assigned_ids_for_user( $user_id ) ) as $disease ) {
			$diseases[ (int) $disease->id ] = $disease;
		}
		foreach ( self::get_by_titles( array_values( $titles ) ) as $disease ) {
			$diseases[ (int) $disease->id ] = $disease;
		}

		return array_values( $diseases );
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
		}

		if ( function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}

		$paged      = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search     = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id    = absint( $_GET['edit_disease'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice     = sanitize_key( $_GET['disease_notice'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = sanitize_key( $_GET['tab'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = $edit_id || 'create' === $active_tab ? 'create' : 'list';
		$base_url   = self::base_url();
		$list_url = add_query_arg( 'tab', 'list', $base_url );
		$create_url = add_query_arg( 'tab', 'create', $base_url );
		?>
		<div class="wrap ggm-diseases-admin">
			<h1><?php esc_html_e( 'Diseases List', 'ggm-member-dashboard' ); ?></h1>
			<div id="ggm-disease-notices"><?php self::render_notice( $notice ); ?></div>
			<nav class="nav-tab-wrapper ggm-disease-tabs" aria-label="<?php esc_attr_e( 'Diseases List tabs', 'ggm-member-dashboard' ); ?>">
				<a class="nav-tab <?php echo 'list' === $active_tab ? 'nav-tab-active' : ''; ?>" data-ggm-disease-tab="list" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'List', 'ggm-member-dashboard' ); ?></a>
				<a class="nav-tab <?php echo 'create' === $active_tab ? 'nav-tab-active' : ''; ?>" data-ggm-disease-tab="create" href="<?php echo esc_url( $create_url ); ?>"><?php echo $edit_id ? esc_html__( 'Edit', 'ggm-member-dashboard' ) : esc_html__( 'Create', 'ggm-member-dashboard' ); ?></a>
			</nav>
			<div id="ggm-disease-tab-content" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
				<?php self::render_tab_content( $active_tab, $edit_id, $search, $paged ); ?>
			</div>
		</div>
		<style>
			.ggm-diseases-admin{max-width:none}.ggm-disease-tabs{margin:16px 0 18px}.ggm-disease-loading{position:relative;opacity:.58;pointer-events:none}.ggm-disease-loading:after{content:"";position:absolute;inset:0;background:rgba(240,240,241,.35)}.ggm-disease-editor-card{width:100%;max-width:none;padding:6px 0 0;background:transparent}.ggm-disease-titlediv,.ggm-disease-editor-wrap,.ggm-disease-submit{width:100%;max-width:980px}.ggm-disease-titlediv{margin:0 0 14px}.ggm-disease-titlediv input{width:100%;height:46px;margin:0;padding:3px 8px;font-size:1.7em;line-height:1.2;background:#fff;border:1px solid #8c8f94;box-shadow:inset 0 1px 2px rgba(0,0,0,.07)}.ggm-disease-titlediv input::placeholder{color:#646970}.ggm-disease-editor-wrap .wp-editor-wrap{width:100%;max-width:none}.ggm-disease-submit{display:flex;gap:8px;align-items:center}.ggm-disease-inline-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px}.ggm-disease-list-wrap{width:100%;max-width:none}.ggm-disease-list-wrap .search-box{margin:0 0 10px}.ggm-disease-list-wrap .tablenav{height:auto;margin:8px 0 6px}.ggm-disease-list-wrap .bulkactions select{max-width:160px}.ggm-disease-list-wrap .wp-list-table{width:100%}.ggm-disease-list-wrap .column-primary{width:28%}.ggm-disease-list-wrap .check-column{width:2.2em}.ggm-disease-list-wrap td,.ggm-disease-list-wrap th{vertical-align:top}.ggm-disease-list-wrap .submitdelete{color:#b32d2e}
		</style>
		<script>
		(function(){
			var config={
				ajaxUrl:<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
				nonce:<?php echo wp_json_encode( wp_create_nonce( 'ggm_disease_admin' ) ); ?>,
				baseUrl:<?php echo wp_json_encode( $base_url ); ?>,
				confirmDelete:<?php echo wp_json_encode( __( 'Delete this disease?', 'ggm-member-dashboard' ) ); ?>,
				confirmBulkDelete:<?php echo wp_json_encode( __( 'Delete selected diseases?', 'ggm-member-dashboard' ) ); ?>,
				createLabel:<?php echo wp_json_encode( __( 'Create', 'ggm-member-dashboard' ) ); ?>,
				editLabel:<?php echo wp_json_encode( __( 'Edit', 'ggm-member-dashboard' ) ); ?>,
				loadingText:<?php echo wp_json_encode( __( 'Loading...', 'ggm-member-dashboard' ) ); ?>
			};
			var content=document.getElementById('ggm-disease-tab-content'),notices=document.getElementById('ggm-disease-notices'),tabs=document.querySelector('.ggm-disease-tabs');
			if(!content||!tabs)return;
			function editorId(){return 'ggm_disease_description';}
			function removeEditor(){var id=editorId();try{if(window.wp&&wp.editor&&document.getElementById(id)){wp.editor.remove(id);}}catch(e){}try{if(window.tinyMCE&&tinyMCE.get(id)){tinyMCE.remove(tinyMCE.get(id));}}catch(e){}}
			function initEditor(attempt){var id=editorId(),area=document.getElementById(id);if(!area)return;attempt=attempt||0;if(!(window.wp&&wp.editor&&wp.editor.initialize)){if(attempt<30){window.setTimeout(function(){initEditor(attempt+1);},100);}return;}try{wp.editor.initialize(id,{tinymce:true,quicktags:true});}catch(e){}}
			function syncEditor(){try{if(window.tinyMCE){tinyMCE.triggerSave();}}catch(e){}}
			function setBusy(busy){content.classList.toggle('ggm-disease-loading',!!busy);content.setAttribute('aria-busy',busy?'true':'false');}
			function setNotice(html){notices.innerHTML=html||'';}
			function setActive(tab,editing){content.dataset.activeTab=tab;tabs.querySelectorAll('.nav-tab').forEach(function(link){link.classList.toggle('nav-tab-active',link.dataset.ggmDiseaseTab===tab);if(link.dataset.ggmDiseaseTab==='create'){link.textContent=editing?config.editLabel:config.createLabel;}});}
			function urlFor(tab,params){var url=new URL(config.baseUrl,window.location.href);url.searchParams.set('page','ggm-lms-diseases');url.searchParams.set('tab',tab);Object.keys(params||{}).forEach(function(key){if(params[key]!==''&&params[key]!==null&&typeof params[key]!=='undefined'){url.searchParams.set(key,params[key]);}});return url;}
			function ajax(data){var body=new URLSearchParams(data);return fetch(config.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(response){return response.json();}).then(function(response){if(!response.success){throw new Error((response.data&&response.data.message)||'Request failed.');}return response.data;});}
			function loadTab(tab,params,push){params=params||{};setBusy(true);removeEditor();return ajax(Object.assign({action:'ggm_disease_tab',nonce:config.nonce,tab:tab},params)).then(function(data){content.innerHTML=data.html||'';setNotice(data.notice||'');setActive(data.tab||tab,!!data.edit_id);initEditor();if(push!==false){window.history.pushState({ggmDisease:true,tab:data.tab||tab,params:params},'',urlFor(data.tab||tab,params));}}).catch(function(error){setNotice('<div class="notice notice-error"><p>'+escapeHtml(error.message)+'</p></div>');}).finally(function(){setBusy(false);});}
			function escapeHtml(text){var div=document.createElement('div');div.textContent=text;return div.innerHTML;}
			function parseLink(link){var url=new URL(link.href,window.location.href);return {tab:url.searchParams.get('tab')||'list',edit_disease:url.searchParams.get('edit_disease')||'',paged:url.searchParams.get('paged')||'',s:url.searchParams.get('s')||''};}
			tabs.addEventListener('click',function(event){var link=event.target.closest('[data-ggm-disease-tab]');if(!link)return;event.preventDefault();var data=parseLink(link);loadTab(data.tab,data);});
			content.addEventListener('submit',function(event){var form=event.target;if(form.classList.contains('ggm-disease-bulk-form')){event.preventDefault();var fd=new FormData(form),ids=form.querySelectorAll('input[name="disease_ids[]"]:checked'),bulkNotice='',button=form.querySelector('[type="submit"]');if((fd.get('bulk_action')||'')!=='delete'){setNotice('<div class="notice notice-error"><p>'+escapeHtml('<?php echo esc_js( __( 'Please select a bulk action.', 'ggm-member-dashboard' ) ); ?>')+'</p></div>');return;}if(!ids.length){setNotice('<div class="notice notice-error"><p>'+escapeHtml('<?php echo esc_js( __( 'Please select at least one disease.', 'ggm-member-dashboard' ) ); ?>')+'</p></div>');return;}if(!window.confirm(config.confirmBulkDelete))return;if(button)button.disabled=true;fd.set('action','ggm_bulk_delete_diseases_ajax');fetch(config.ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(function(response){return response.json();}).then(function(response){if(!response.success){throw new Error((response.data&&response.data.message)||'Could not delete selected diseases.');}bulkNotice=response.data.notice||'';return loadTab('list',{s:fd.get('s')||'',paged:fd.get('paged')||1},true);}).then(function(){setNotice(bulkNotice);}).catch(function(error){setNotice('<div class="notice notice-error"><p>'+escapeHtml(error.message)+'</p></div>');}).finally(function(){if(button)button.disabled=false;});return;}if(form.classList.contains('ggm-disease-list-search')){event.preventDefault();var data=new FormData(form);loadTab('list',{s:data.get('s')||'',paged:1});return;}if(form.classList.contains('ggm-disease-editor-card')){event.preventDefault();syncEditor();var button=form.querySelector('[type="submit"]');if(button)button.disabled=true;var fd=new FormData(form);fd.set('action','ggm_save_disease_ajax');fetch(config.ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(function(response){return response.json();}).then(function(response){if(!response.success){throw new Error((response.data&&response.data.message)||'Could not save disease.');}form.querySelector('[name="disease_id"]').value=response.data.id||0;setNotice(response.data.notice||'');setActive('create',true);window.history.pushState({ggmDisease:true,tab:'create',params:{edit_disease:response.data.id}},'',urlFor('create',{edit_disease:response.data.id}));}).catch(function(error){setNotice('<div class="notice notice-error"><p>'+escapeHtml(error.message)+'</p></div>');}).finally(function(){if(button)button.disabled=false;});}});
			content.addEventListener('click',function(event){var link=event.target.closest('a');if(!link)return;if(link.classList.contains('ggm-delete-disease')){event.preventDefault();if(!window.confirm(config.confirmDelete))return;var url=new URL(link.href,window.location.href),deleteNotice='';ajax({action:'ggm_delete_disease_ajax',nonce:config.nonce,disease_id:url.searchParams.get('disease_id')||'',_wpnonce:url.searchParams.get('_wpnonce')||''}).then(function(data){deleteNotice=data.notice||'';return loadTab('list',{paged:1},true);}).then(function(){setNotice(deleteNotice);}).catch(function(error){setNotice('<div class="notice notice-error"><p>'+escapeHtml(error.message)+'</p></div>');});return;}if(link.classList.contains('ggm-disease-ajax-link')||link.closest('.tablenav-pages')){event.preventDefault();var data=parseLink(link);loadTab(data.tab,data);}});
			content.addEventListener('change',function(event){var box=event.target;if(!box.matches('.ggm-disease-check-all,.ggm-disease-row-check'))return;var form=box.closest('.ggm-disease-bulk-form');if(!form)return;var rows=form.querySelectorAll('.ggm-disease-row-check'),alls=form.querySelectorAll('.ggm-disease-check-all');if(box.classList.contains('ggm-disease-check-all')){rows.forEach(function(row){row.checked=box.checked;});}var checked=form.querySelectorAll('.ggm-disease-row-check:checked').length;alls.forEach(function(all){all.checked=rows.length>0&&checked===rows.length;all.indeterminate=checked>0&&checked<rows.length;});});
			notices.addEventListener('click',function(event){var add=event.target.closest('[data-ggm-disease-add-another]');if(add){event.preventDefault();loadTab('create',{});return;}var list=event.target.closest('[data-ggm-disease-view-list]');if(list){event.preventDefault();loadTab('list',{});}});
			window.addEventListener('popstate',function(){var url=new URL(window.location.href);var tab=url.searchParams.get('tab')||'list';loadTab(tab,{edit_disease:url.searchParams.get('edit_disease')||'',paged:url.searchParams.get('paged')||'',s:url.searchParams.get('s')||''},false);});
			if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',function(){initEditor();});}else{initEditor();}
		})();
		</script>
		<?php
	}

	private static function base_url() {
		return admin_url( 'admin.php?page=ggm-lms-diseases' );
	}

	private static function render_notice( $notice, $include_actions = false ) {
		$notice = sanitize_key( $notice );
		if ( ! $notice ) {
			return;
		}

		$messages = array(
			'created'      => __( 'Disease created.', 'ggm-member-dashboard' ),
			'saved'        => __( 'Changes saved.', 'ggm-member-dashboard' ),
			'deleted'      => __( 'Disease deleted.', 'ggm-member-dashboard' ),
			'bulk_deleted' => __( 'Selected diseases deleted.', 'ggm-member-dashboard' ),
		);
		$message = $messages[ $notice ] ?? $messages['saved'];
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
			<?php if ( $include_actions ) : ?>
				<div class="ggm-disease-inline-actions">
					<button type="button" class="button button-primary" data-ggm-disease-add-another><?php esc_html_e( 'Add Another', 'ggm-member-dashboard' ); ?></button>
					<button type="button" class="button" data-ggm-disease-view-list><?php esc_html_e( 'View List', 'ggm-member-dashboard' ); ?></button>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function notice_html( $notice, $include_actions = false ) {
		ob_start();
		self::render_notice( $notice, $include_actions );
		return ob_get_clean();
	}

	private static function tab_html( $tab, $edit_id = 0, $search = '', $paged = 1 ) {
		ob_start();
		self::render_tab_content( $tab, $edit_id, $search, $paged );
		return ob_get_clean();
	}

	private static function render_tab_content( $tab, $edit_id = 0, $search = '', $paged = 1 ) {
		if ( 'create' === $tab ) {
			self::render_create_tab( $edit_id );
			return;
		}

		self::render_list_tab( $search, $paged );
	}

	private static function render_create_tab( $edit_id = 0 ) {
		$edit     = self::get_disease( $edit_id );
		$list_url = add_query_arg( 'tab', 'list', self::base_url() );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ggm-disease-editor-card">
			<input type="hidden" name="action" value="ggm_save_disease">
			<input type="hidden" name="disease_id" value="<?php echo esc_attr( $edit ? (int) $edit->id : 0 ); ?>">
			<?php wp_nonce_field( 'ggm_save_disease' ); ?>
			<div id="titlediv" class="ggm-disease-titlediv">
				<div id="titlewrap">
					<label class="screen-reader-text" for="ggm-disease-title"><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?></label>
					<input id="ggm-disease-title" type="text" name="title" size="30" value="<?php echo esc_attr( $edit->title ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Add title', 'ggm-member-dashboard' ); ?>" spellcheck="true" autocomplete="off" required maxlength="255">
				</div>
			</div>
			<div class="ggm-disease-editor-wrap">
				<h2 class="screen-reader-text"><label for="ggm_disease_description"><?php esc_html_e( 'Description', 'ggm-member-dashboard' ); ?></label></h2>
				<textarea id="ggm_disease_description" class="wp-editor-area" name="description" rows="18"><?php echo esc_textarea( (string) ( $edit->description ?? '' ) ); ?></textarea>
			</div>
			<p class="submit ggm-disease-submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Disease', 'ggm-member-dashboard' ); ?></button>
				<?php if ( $edit ) : ?><a class="button ggm-disease-ajax-link" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel', 'ggm-member-dashboard' ); ?></a><?php endif; ?>
			</p>
		</form>
		<?php
	}

	private static function render_list_tab( $search = '', $paged = 1 ) {
		global $wpdb;
		$table    = self::table();
		$per_page = 10;
		$paged    = max( 1, absint( $paged ) );
		$search   = sanitize_text_field( $search );
		$where    = "WHERE status='active'";
		$params   = array();
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (title LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$total_sql = "SELECT COUNT(*) FROM $table $where"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) ) : (int) $wpdb->get_var( $total_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$offset    = ( $paged - 1 ) * $per_page;
		$list_sql  = "SELECT * FROM $table $where ORDER BY updated_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$diseases  = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$base_url  = self::base_url();
		$list_url  = add_query_arg( 'tab', 'list', $base_url );
		$create_url = add_query_arg( 'tab', 'create', $base_url );
		?>
		<div class="ggm-disease-list-wrap">
			<form method="get" class="ggm-disease-list-search">
				<input type="hidden" name="page" value="ggm-lms-diseases">
				<input type="hidden" name="tab" value="list">
				<p class="search-box">
					<label class="screen-reader-text" for="ggm-disease-search-input"><?php esc_html_e( 'Search diseases', 'ggm-member-dashboard' ); ?></label>
					<input id="ggm-disease-search-input" type="search" name="s" value="<?php echo esc_attr( $search ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Search Diseases', 'ggm-member-dashboard' ); ?></button>
				</p>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ggm-disease-bulk-form">
				<input type="hidden" name="action" value="ggm_bulk_delete_diseases">
				<input type="hidden" name="tab" value="list">
				<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
				<input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
				<?php wp_nonce_field( 'ggm_bulk_delete_diseases' ); ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label for="ggm-disease-bulk-action-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'ggm-member-dashboard' ); ?></label>
						<select id="ggm-disease-bulk-action-top" name="bulk_action">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'ggm-member-dashboard' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'ggm-member-dashboard' ); ?></option>
						</select>
						<button type="submit" class="button action"><?php esc_html_e( 'Apply', 'ggm-member-dashboard' ); ?></button>
						<a class="button button-primary ggm-disease-ajax-link" href="<?php echo esc_url( $create_url ); ?>"><?php esc_html_e( 'Create Disease', 'ggm-member-dashboard' ); ?></a>
						<?php if ( $search ) : ?><a class="button ggm-disease-ajax-link" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Clear Search', 'ggm-member-dashboard' ); ?></a><?php endif; ?>
					</div>
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $total, 'ggm-member-dashboard' ), number_format_i18n( $total ) ) ); ?></span>
					</div>
					<br class="clear">
				</div>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead><tr><td class="manage-column column-cb check-column"><input type="checkbox" class="ggm-disease-check-all"><span class="screen-reader-text"><?php esc_html_e( 'Select all', 'ggm-member-dashboard' ); ?></span></td><th scope="col" class="column-primary"><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?></th><th scope="col"><?php esc_html_e( 'Text', 'ggm-member-dashboard' ); ?></th><th scope="col"><?php esc_html_e( 'Updated', 'ggm-member-dashboard' ); ?></th></tr></thead>
					<tbody>
						<?php if ( $diseases ) : foreach ( $diseases as $disease ) : ?>
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" class="ggm-disease-row-check" name="disease_ids[]" value="<?php echo esc_attr( (int) $disease->id ); ?>"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Select %s', 'ggm-member-dashboard' ), $disease->title ) ); ?></span></th>
								<td class="column-primary">
									<strong><a class="row-title ggm-disease-ajax-link" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'create', 'edit_disease' => (int) $disease->id ), $base_url ) ); ?>"><?php echo esc_html( $disease->title ); ?></a></strong>
									<div class="row-actions">
										<span class="edit"><a class="ggm-disease-ajax-link" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'create', 'edit_disease' => (int) $disease->id ), $base_url ) ); ?>"><?php esc_html_e( 'Edit', 'ggm-member-dashboard' ); ?></a> | </span>
										<span class="trash"><a class="ggm-delete-disease submitdelete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ggm_delete_disease&disease_id=' . (int) $disease->id ), 'ggm_delete_disease_' . (int) $disease->id ) ); ?>"><?php esc_html_e( 'Delete', 'ggm-member-dashboard' ); ?></a></span>
									</div>
									<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'ggm-member-dashboard' ); ?></span></button>
								</td>
								<td><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $disease->description ), 18 ) ); ?></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $disease->updated_at ) ); ?></td>
							</tr>
						<?php endforeach; else : ?>
							<tr class="no-items"><td class="colspanchange" colspan="4"><?php esc_html_e( 'No diseases found.', 'ggm-member-dashboard' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
					<tfoot><tr><td class="manage-column column-cb check-column"><input type="checkbox" class="ggm-disease-check-all"><span class="screen-reader-text"><?php esc_html_e( 'Select all', 'ggm-member-dashboard' ); ?></span></td><th scope="col" class="column-primary"><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?></th><th scope="col"><?php esc_html_e( 'Text', 'ggm-member-dashboard' ); ?></th><th scope="col"><?php esc_html_e( 'Updated', 'ggm-member-dashboard' ); ?></th></tr></tfoot>
				</table>
			</form>
			<?php
			$total_pages = max( 1, (int) ceil( $total / $per_page ) );
			if ( $total_pages > 1 ) {
				echo '<div class="tablenav bottom"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( array( 'tab' => 'list', 'paged' => '%#%', 's' => rawurlencode( $search ) ), $base_url ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => __( '&laquo;', 'ggm-member-dashboard' ),
							'next_text' => __( '&raquo;', 'ggm-member-dashboard' ),
						)
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	private static function get_disease( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE id=%d AND status='active'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function save_disease_from_request() {
		global $wpdb;
		$id   = absint( $_POST['disease_id'] ?? 0 );
		$data = array(
			'title'       => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'status'      => 'active',
			'updated_at'  => current_time( 'mysql' ),
		);
		if ( '' === $data['title'] || '' === trim( wp_strip_all_tags( $data['description'] ) ) ) {
			return new WP_Error( 'ggm_disease_required', __( 'Title and description are required.', 'ggm-member-dashboard' ) );
		}

		if ( $id ) {
			$updated = $wpdb->update( self::table(), $data, array( 'id' => $id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated ) {
				return new WP_Error( 'ggm_disease_save_failed', __( 'Could not save disease.', 'ggm-member-dashboard' ) );
			}
			return array(
				'id'     => $id,
				'notice' => 'saved',
			);
		}

		$data['created_by'] = get_current_user_id();
		$data['created_at'] = current_time( 'mysql' );
		$inserted = $wpdb->insert( self::table(), $data, array( '%s', '%s', '%s', '%s', '%d', '%s' ) );
		if ( false === $inserted ) {
			return new WP_Error( 'ggm_disease_save_failed', __( 'Could not create disease.', 'ggm-member-dashboard' ) );
		}

		return array(
			'id'     => (int) $wpdb->insert_id,
			'notice' => 'created',
		);
	}

	private static function delete_diseases( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return new WP_Error( 'ggm_disease_none_selected', __( 'Please select at least one disease.', 'ggm-member-dashboard' ) );
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = 'UPDATE ' . self::table() . " SET status='deleted', updated_at=%s WHERE id IN ($placeholders)";
		$params       = array_merge( array( current_time( 'mysql' ) ), $ids );
		$result       = $wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $result ) {
			return new WP_Error( 'ggm_disease_delete_failed', __( 'Could not delete selected diseases.', 'ggm-member-dashboard' ) );
		}

		return (int) $result;
	}

	public function ajax_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 );
		}
		check_ajax_referer( 'ggm_disease_admin', 'nonce' );

		$tab     = sanitize_key( $_POST['tab'] ?? 'list' );
		$tab     = 'create' === $tab ? 'create' : 'list';
		$edit_id = absint( $_POST['edit_disease'] ?? 0 );
		$search  = sanitize_text_field( wp_unslash( $_POST['s'] ?? '' ) );
		$paged   = max( 1, absint( $_POST['paged'] ?? 1 ) );

		wp_send_json_success(
			array(
				'html'    => self::tab_html( $tab, $edit_id, $search, $paged ),
				'notice'  => '',
				'tab'     => $tab,
				'edit_id' => 'create' === $tab ? $edit_id : 0,
			)
		);
	}

	public function ajax_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 );
		}
		check_ajax_referer( 'ggm_save_disease' );

		$result = self::save_disease_from_request();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'id'     => (int) $result['id'],
				'notice' => self::notice_html( $result['notice'], true ),
			)
		);
	}

	public function ajax_delete() {
		$id = absint( $_POST['disease_id'] ?? 0 );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 );
		}
		check_ajax_referer( 'ggm_disease_admin', 'nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'ggm_delete_disease_' . $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'ggm-member-dashboard' ) ), 403 );
		}

		if ( $id ) {
			global $wpdb;
			$wpdb->update( self::table(), array( 'status' => 'deleted', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
		}

		wp_send_json_success( array( 'notice' => self::notice_html( 'deleted' ) ) );
	}

	public function ajax_bulk_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ), 403 );
		}
		check_ajax_referer( 'ggm_bulk_delete_diseases' );

		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
		if ( 'delete' !== $bulk_action ) {
			wp_send_json_error( array( 'message' => __( 'Please select a bulk action.', 'ggm-member-dashboard' ) ), 400 );
		}

		$result = self::delete_diseases( (array) ( $_POST['disease_ids'] ?? array() ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'notice' => self::notice_html( 'bulk_deleted' ) ) );
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ggm_save_disease' );

		$result = self::save_disease_from_request();
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		wp_safe_redirect( add_query_arg( array( 'tab' => 'list', 'disease_notice' => $result['notice'] ), self::base_url() ) );
		exit;
	}

	public function handle_delete() {
		$id = absint( $_GET['disease_id'] ?? 0 );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ggm_delete_disease_' . $id );
		if ( $id ) {
			global $wpdb;
			$wpdb->update( self::table(), array( 'status' => 'deleted', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
		}
		wp_safe_redirect( add_query_arg( array( 'tab' => 'list', 'disease_notice' => 'deleted' ), self::base_url() ) );
		exit;
	}

	public function handle_bulk_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ggm_bulk_delete_diseases' );

		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
		if ( 'delete' !== $bulk_action ) {
			wp_die( esc_html__( 'Please select a bulk action.', 'ggm-member-dashboard' ), '', array( 'response' => 400 ) );
		}

		$result = self::delete_diseases( (array) ( $_POST['disease_ids'] ?? array() ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'tab'            => 'list',
					'disease_notice' => 'bulk_deleted',
					's'              => sanitize_text_field( wp_unslash( $_POST['s'] ?? '' ) ),
					'paged'          => max( 1, absint( $_POST['paged'] ?? 1 ) ),
				),
				self::base_url()
			)
		);
		exit;
	}

}
