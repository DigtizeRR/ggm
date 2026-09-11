<?php
/**
 * Versioned member form builder, placement resolver and submission handler.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class GGM_Form_Builder {
	private static $rendered = array();
	private static $public_styles_printed = false;
	private static $contact_cache = array();
	private static $editing_answers = array();

	public function init( GGM_Loader $loader ) {
		$loader->add_action( 'admin_post_ggm_save_form', $this, 'save_form' );
		$loader->add_action( 'admin_post_ggm_archive_form', $this, 'archive_form' );
		$loader->add_action( 'admin_post_ggm_delete_form_submission', $this, 'delete_form_submission' );
		$loader->add_action( 'admin_post_ggm_review_form_submission', $this, 'review_form_submission' );
		$loader->add_action( 'wp_ajax_ggm_delete_form', $this, 'ajax_delete_form' );
		$loader->add_action( 'admin_post_ggm_form_file', $this, 'download_file' );
		$loader->add_action( 'admin_post_ggm_preview_form', $this, 'preview_form' );
		$loader->add_action( 'wp_ajax_ggm_submit_builder_form', $this, 'submit_form' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_submit_builder_form', $this, 'submit_form' );
		$loader->add_action( 'wp_ajax_ggm_update_builder_form_after_submit', $this, 'update_after_submit_fields' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_update_builder_form_after_submit', $this, 'update_after_submit_fields' );
		$loader->add_action( 'wp_ajax_ggm_create_form_payment', $this, 'create_form_payment' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_create_form_payment', $this, 'create_form_payment' );
		$loader->add_action( 'wp_ajax_ggm_refresh_form_payment_nonce', $this, 'refresh_form_payment_nonce' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_refresh_form_payment_nonce', $this, 'refresh_form_payment_nonce' );
		$loader->add_action( 'wp_ajax_ggm_verify_form_payment', $this, 'verify_form_payment' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_verify_form_payment', $this, 'verify_form_payment' );
		$loader->add_action( 'wp_ajax_ggm_render_popup_form', $this, 'ajax_render_popup_form' );
		$loader->add_action( 'wp_ajax_nopriv_ggm_render_popup_form', $this, 'ajax_render_popup_form' );
		add_shortcode( 'ggm_form', array( $this, 'shortcode' ) );
		add_shortcode( 'ggm_assigned_forms', array( $this, 'assigned_forms_shortcode' ) );
	}

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	public static function get_form( $form_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'ggm_forms' ) . ' WHERE id=%d', absint( $form_id ) ) );
	}

	public static function get_forms( $status = '' ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'ggm_forms' );
		if ( $status ) {
			$sql .= $wpdb->prepare( ' WHERE status=%s', $status );
		}
		return $wpdb->get_results( $sql . ' ORDER BY updated_at DESC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_schema( $form, $version = 0 ) {
		global $wpdb;
		if ( ! $form ) { return array(); }
		$version = absint( $version ) ?: absint( $form->current_version );
		$json = $wpdb->get_var( $wpdb->prepare(
			'SELECT schema_json FROM ' . self::table( 'ggm_form_versions' ) . ' WHERE form_id=%d AND version=%d',
			$form->id,
			$version
		) );
		$schema = json_decode( (string) $json, true );
		return is_array( $schema ) ? self::normalize_runtime_schema( $schema ) : array();
	}

	/**
	 * Return a builder-ready copy of the former workshop health intake.
	 *
	 * Historical legacy submissions and reports remain stored independently. This
	 * schema gives administrators the same question set as an editable starting
	 * point for the versioned form builder used on the member dashboard.
	 *
	 * @return array Template title, settings and schema.
	 */
	public static function workshop_health_template() {
		$field = static function ( $key, $type, $label, $required = false, $extra = array() ) {
			return array_merge(
				array(
					'key'         => $key,
					'type'        => $type,
					'label'       => $label,
					'description' => '',
					'required'    => $required,
				),
				$extra
			);
		};

		return array(
			'title'       => __( 'Workshop Health Information Form', 'ggm-member-dashboard' ),
			'description' => __( 'Please complete this health information form for your workshop.', 'ggm-member-dashboard' ),
			'settings'    => array(
				'back_label'    => __( 'Back', 'ggm-member-dashboard' ),
				'next_label'    => __( 'Next', 'ggm-member-dashboard' ),
				'submit_label'  => __( 'Submit Health Form', 'ggm-member-dashboard' ),
				'confirmation'  => __( 'Your health form has been submitted successfully.', 'ggm-member-dashboard' ),
				'progress'      => 'none',
				'allow_multiple'=> false,
				'allow_guests'  => false,
			),
			'schema'      => array(
				'sections' => array(
					array(
						'key'         => 'workshop_health_information',
						'title'       => __( 'Workshop Health Information', 'ggm-member-dashboard' ),
						'description' => __( 'Please provide your contact and health details, then confirm the declaration.', 'ggm-member-dashboard' ),
						'fields'      => array(
							$field( 'full_name', 'short_answer', __( 'Full Name', 'ggm-member-dashboard' ), true ),
							$field( 'phone', 'short_answer', __( 'WhatsApp Mobile Number', 'ggm-member-dashboard' ), true, array( 'validation_type'=>'phone' ) ),
							$field( 'gender', 'dropdown', __( 'Gender', 'ggm-member-dashboard' ), true, array(
								'select_prompt' => __( 'Select', 'ggm-member-dashboard' ),
								'options'       => array(
									array( 'key'=>'male', 'label'=>__( 'Male', 'ggm-member-dashboard' ) ),
									array( 'key'=>'female', 'label'=>__( 'Female', 'ggm-member-dashboard' ) ),
									array( 'key'=>'other', 'label'=>__( 'Other', 'ggm-member-dashboard' ) ),
									array( 'key'=>'prefer_not_to_say', 'label'=>__( 'Prefer not to say', 'ggm-member-dashboard' ) ),
								),
							) ),
							$field( 'dob_age', 'short_answer', __( 'Date of Birth / Approx. Age', 'ggm-member-dashboard' ), true ),
							$field( 'email', 'short_answer', __( 'Email', 'ggm-member-dashboard' ), true, array( 'validation_type'=>'email' ) ),
							$field( 'city', 'short_answer', __( 'City', 'ggm-member-dashboard' ), true ),
							$field( 'address', 'paragraph', __( 'Full Address', 'ggm-member-dashboard' ), true ),
							$field( 'state', 'short_answer', __( 'State', 'ggm-member-dashboard' ), true ),
							$field( 'weight', 'short_answer', __( 'Weight', 'ggm-member-dashboard' ) ),
							$field( 'bp', 'short_answer', __( 'Blood Pressure', 'ggm-member-dashboard' ) ),
							$field( 'sugar_level', 'short_answer', __( 'Sugar / Glucose Level', 'ggm-member-dashboard' ) ),
							$field( 'diseases', 'paragraph', __( 'List up to 3 diseases or ailments you want to heal naturally', 'ggm-member-dashboard' ), true, array( 'placeholder'=>"1)\n2)\n3)" ) ),
							$field( 'mentioned_diseases', 'paragraph', __( 'Mention diseases (one per line)', 'ggm-member-dashboard' ), false, array( 'placeholder'=>"1)\n2)\n3)" ) ),
							$field( 'medical_report', 'file_upload', __( 'Medical reports (PDF, JPG or PNG; maximum 10 MB)', 'ggm-member-dashboard' ), false, array(
								'allowed_type_groups' => array( 'pdf' ),
								'custom_extensions'   => array( 'jpg', 'jpeg', 'png' ),
								'max_files'           => 1,
								'max_size'            => 10,
							) ),
							$field( 'note', 'paragraph', __( 'Additional Note', 'ggm-member-dashboard' ) ),
							$field( 'declaration', 'checkboxes', __( 'Declaration', 'ggm-member-dashboard' ), true, array(
								'options' => array(
									array(
										'key'   => 'confirmed',
										'label' => __( 'I confirm that the information is complete and correct. I understand the workshop is educational, does not replace professional medical care, and I remain responsible for consulting a qualified physician for medical conditions.', 'ggm-member-dashboard' ),
									),
								),
							) ),
						),
					),
				),
			),
		);
	}

	private static function normalize_runtime_schema( array $schema ) {
		foreach ( (array) ( $schema['sections'] ?? array() ) as $section_index=>$section ) {
			foreach ( (array) ( $section['fields'] ?? array() ) as $field_index=>$field ) {
				foreach ( array( 'options','rows' ) as $prop ) {
					$items = $field[ $prop ] ?? array();
					if ( is_string( $items ) ) { $items = preg_split( '/\r?\n/', $items ); }
					// Checkboxes option labels keep their inline HTML (bold/italic/
					// link) on every render — everything else stays plain text.
					$rich_label = 'options' === $prop && 'checkboxes' === ( $field['type'] ?? '' );
					$normalized = array();
					foreach ( (array) $items as $item_index=>$item ) {
						$raw_label = is_array( $item ) ? ( $item['label'] ?? '' ) : $item;
						$label = $rich_label ? wp_kses( (string) $raw_label, self::inline_html_tags() ) : sanitize_text_field( $raw_label );
						if ( '' === trim( wp_strip_all_tags( $label ) ) ) { continue; }
						$key = is_array( $item ) ? sanitize_key( $item['key'] ?? '' ) : '';
						if ( ! $key ) { $key = 'legacy_' . substr( md5( $field_index . '|' . $prop . '|' . $item_index . '|' . $label ), 0, 16 ); }
						$normalized[] = array( 'key'=>$key, 'label'=>$label );
					}
					$field[ $prop ] = $normalized;
				}
				$field['validation_type'] = $field['validation_type'] ?? ( in_array( $field['subtype'] ?? '', array( 'email','number','phone' ), true ) ? $field['subtype'] : 'none' );
				$field['phone_country_enabled'] = 'phone' === $field['validation_type'] || ! empty( $field['phone_country_enabled'] );
				$field['phone_country_iso'] = self::sanitize_country_iso( $field['phone_country_iso'] ?? '' );
				$field['phone_country_code'] = self::country_dial_for_iso( $field['phone_country_iso'] );
				$field['field_width'] = self::sanitize_field_width( $field['field_width'] ?? '100' );
				$field['show_after_submit'] = ! empty( $field['show_after_submit'] );
				$field['allow_multiple_entries'] = ! empty( $field['allow_multiple_entries'] );
				$field['show_uploaded_files_to_user'] = ! empty( $field['show_uploaded_files_to_user'] );
				$field['allowed_type_groups'] = isset( $field['allowed_type_groups'] ) && is_array( $field['allowed_type_groups'] ) ? $field['allowed_type_groups'] : array( 'image','pdf','document' );
				$field['duration_units'] = isset( $field['duration_units'] ) && is_array( $field['duration_units'] ) ? $field['duration_units'] : array( 'hours','minutes' );
				$field['max_files'] = max( 1, min( 50, absint( $field['max_files'] ?? 1 ) ) );
				// Date fields always use the native full-date picker. Keep this
				// legacy property normalized for schemas created by older versions.
				$field['include_year'] = true;
				$field['selection_mode'] = in_array( $field['selection_mode'] ?? '', array( 'single','multiple' ), true ) ? $field['selection_mode'] : 'multiple';
				$field['allow_custom_values'] = ! empty( $field['allow_custom_values'] );
				$field['min_selections'] = max( 0, absint( $field['min_selections'] ?? 0 ) );
				$field['max_selections'] = 'search_select' === ( $field['type'] ?? '' )
					? max( 1, absint( $field['max_selections'] ?? 1 ) )
					: max( 0, absint( $field['max_selections'] ?? 0 ) );
				$field['disease_source'] = ! empty( $field['disease_source'] );
				$field = self::hydrate_dynamic_field( $field );
				$schema['sections'][ $section_index ]['fields'][ $field_index ] = $field;
			}
		}
		return $schema;
	}

	private static function disease_options() {
		return class_exists( 'GGM_Diseases' ) ? GGM_Diseases::active_options() : array();
	}

	private static function hydrate_dynamic_field( array $field ) {
		if ( 'search_select' === ( $field['type'] ?? '' ) && ! empty( $field['disease_source'] ) ) {
			$field['options'] = self::disease_options();
		}
		return $field;
	}

	public static function get_assignments( $form_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table( 'ggm_form_assignments' ) . " WHERE form_id=%d AND status='active' ORDER BY sort_order,id",
			absint( $form_id )
		) );
	}

	public static function get_elementor_popup_forms() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT DISTINCT f.id,f.title FROM ' . self::table( 'ggm_forms' ) . ' f INNER JOIN ' . self::table( 'ggm_form_assignments' ) . " a ON a.form_id=f.id WHERE f.status='published' AND a.status='active' AND a.location_type='elementor_popup' ORDER BY f.title ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function clean_schema( $raw ) {
		$schema = json_decode( wp_unslash( (string) $raw ), true );
		if ( ! is_array( $schema ) ) { return new WP_Error( 'schema', __( 'The form structure is invalid.', 'ggm-member-dashboard' ) ); }
		$allowed = array( 'short_answer','paragraph','multiple_choice','checkboxes','dropdown','file_upload','linear_scale','multiple_choice_grid','checkbox_grid','date','time','chip_selector','search_select' );
		$clean = array( 'sections' => array() );
		foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) {
			$section_key = sanitize_key( $section['key'] ?? '' ) ?: wp_generate_uuid4();
			$fields = array();
			foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
				$type = sanitize_key( $field['type'] ?? '' );
				if ( ! in_array( $type, $allowed, true ) ) { continue; }
				$options = array();
				$option_seen = array();
				$raw_options = $field['options'] ?? array();
				if ( is_string( $raw_options ) ) { $raw_options = preg_split( '/\r?\n/', $raw_options ); }
				// Checkboxes option labels allow a small set of inline HTML tags
				// (bold/italic/link) from the builder's rich text control; every
				// other option-bearing field type keeps plain-text labels.
				$rich_option_label = 'checkboxes' === $type;
				foreach ( (array) $raw_options as $option ) {
					$raw_label = is_array( $option ) ? ( $option['label'] ?? '' ) : $option;
					$label = $rich_option_label ? wp_kses( (string) $raw_label, self::inline_html_tags() ) : sanitize_text_field( $raw_label );
					$option_key = is_array( $option ) ? sanitize_key( $option['key'] ?? '' ) : '';
					$identity = strtolower( trim( wp_strip_all_tags( $label ) ) );
					if ( '' !== $identity && ! isset( $option_seen[ $identity ] ) ) {
						$option_seen[ $identity ] = true;
						$options[] = array( 'key' => $option_key ?: wp_generate_uuid4(), 'label' => $label );
					}
				}
				$rows = array();
				$raw_rows = $field['rows'] ?? array();
				if ( is_string( $raw_rows ) ) { $raw_rows = preg_split( '/\r?\n/', $raw_rows ); }
				foreach ( (array) $raw_rows as $row ) {
					$label = sanitize_text_field( is_array( $row ) ? ( $row['label'] ?? '' ) : $row );
					$row_key = is_array( $row ) ? sanitize_key( $row['key'] ?? '' ) : '';
					if ( '' !== $label ) { $rows[] = array( 'key' => $row_key ?: wp_generate_uuid4(), 'label' => $label ); }
				}
				$fields[] = array(
					'key' => sanitize_key( $field['key'] ?? '' ) ?: wp_generate_uuid4(),
					'type' => $type,
					'label' => sanitize_text_field( $field['label'] ?? '' ),
					// Checkboxes' help text has a rich text control in the builder
					// (bold/italic/lists/links); every other field type keeps a
					// plain-text description.
					'description' => 'checkboxes' === $type ? wp_kses_post( $field['description'] ?? '' ) : sanitize_textarea_field( $field['description'] ?? '' ),
					'required' => ! empty( $field['required'] ),
					'placeholder' => 'paragraph' === $type ? sanitize_textarea_field( $field['placeholder'] ?? '' ) : sanitize_text_field( $field['placeholder'] ?? '' ),
					'subtype' => sanitize_key( $field['subtype'] ?? 'text' ),
					'validation_type' => $this->allowed_value( $field['validation_type'] ?? ( $field['subtype'] ?? 'none' ), array( 'none','text','email','number','phone','url','regex' ), 'none' ),
					'phone_country_enabled' => ! empty( $field['phone_country_enabled'] ),
					'phone_country_iso' => self::sanitize_country_iso( $field['phone_country_iso'] ?? '' ),
					'phone_country_code' => self::country_dial_for_iso( self::sanitize_country_iso( $field['phone_country_iso'] ?? '' ) ),
					'field_width' => self::sanitize_field_width( $field['field_width'] ?? '100' ),
					'show_after_submit' => ! empty( $field['show_after_submit'] ),
					'allow_multiple_entries' => ! empty( $field['show_after_submit'] ) && ! empty( $field['allow_multiple_entries'] ),
					'show_uploaded_files_to_user' => 'file_upload' === $type && ! empty( $field['show_uploaded_files_to_user'] ),
					'number_rule' => $this->allowed_value( $field['number_rule'] ?? 'none', array( 'none','greater_than','greater_or_equal','less_than','less_or_equal','equal','not_equal','between' ), 'none' ),
					'number_value' => isset( $field['number_value'] ) && '' !== (string) $field['number_value'] ? (float) $field['number_value'] : null,
					'number_value_to' => isset( $field['number_value_to'] ) && '' !== (string) $field['number_value_to'] ? (float) $field['number_value_to'] : null,
					'regex_pattern' => substr( sanitize_text_field( $field['regex_pattern'] ?? '' ), 0, 500 ),
					'min_length' => min( 100000, absint( $field['min_length'] ?? 0 ) ),
					'max_length' => min( 100000, absint( $field['max_length'] ?? 0 ) ),
					'validation_message' => sanitize_text_field( $field['validation_message'] ?? '' ),
					'options' => $options,
					'rows' => $rows,
					'allow_other' => ! empty( $field['allow_other'] ),
					'shuffle_options' => ! empty( $field['shuffle_options'] ),
					'selection_rule' => $this->allowed_value( $field['selection_rule'] ?? 'none', array( 'none','at_least','at_most','exactly' ), 'none' ),
					'selection_count' => min( 1000, absint( $field['selection_count'] ?? 0 ) ),
					'select_prompt' => sanitize_text_field( $field['select_prompt'] ?? __( 'Choose an option', 'ggm-member-dashboard' ) ),
					'min' => isset( $field['min'] ) ? (float) $field['min'] : null,
					'max' => isset( $field['max'] ) ? (float) $field['max'] : null,
					'min_label' => sanitize_text_field( $field['min_label'] ?? '' ),
					'max_label' => sanitize_text_field( $field['max_label'] ?? '' ),
					'allowed_type_groups' => array_values( array_intersect( array( 'image','pdf','document','spreadsheet','presentation','video','audio' ), array_map( 'sanitize_key', (array) ( $field['allowed_type_groups'] ?? array( 'image','pdf','document' ) ) ) ) ),
					'custom_extensions' => $this->sanitize_extensions( $field['custom_extensions'] ?? '' ),
					'max_files' => max( 1, min( 50, absint( $field['max_files'] ?? 1 ) ) ),
					'max_size' => max( 1, min( 100, absint( $field['max_size'] ?? 10 ) ) ),
					'require_each_row' => isset( $field['require_each_row'] ) ? ! empty( $field['require_each_row'] ) : ! empty( $field['required'] ),
					'limit_one_per_column' => ! empty( $field['limit_one_per_column'] ),
					'shuffle_rows' => ! empty( $field['shuffle_rows'] ),
					// Retained in the schema for backwards compatibility. Date
					// answers are now always stored canonically as YYYY-MM-DD.
					'include_year' => true,
					'min_date' => $this->sanitize_date_config( $field['min_date'] ?? '' ),
					'max_date' => $this->sanitize_date_config( $field['max_date'] ?? '' ),
					'time_type' => $this->allowed_value( $field['time_type'] ?? 'time_of_day', array( 'time_of_day','duration' ), 'time_of_day' ),
					'time_format' => $this->allowed_value( (string) ( $field['time_format'] ?? '24' ), array( '12','24' ), '24' ),
					'duration_units' => $this->sanitize_duration_units( $field['duration_units'] ?? array( 'hours','minutes' ) ),
					'selection_mode' => $this->allowed_value( $field['selection_mode'] ?? 'multiple', array( 'single','multiple' ), 'multiple' ),
					'allow_custom_values' => ! empty( $field['allow_custom_values'] ),
					'min_selections' => min( 100, absint( $field['min_selections'] ?? 0 ) ),
					'max_selections' => min( 100, absint( $field['max_selections'] ?? 0 ) ),
					'disease_source' => ! empty( $field['disease_source'] ),
				);
				$last_field = count( $fields ) - 1;
				if ( 'chip_selector' === $type ) {
					if ( 'single' === $fields[ $last_field ]['selection_mode'] ) {
						$fields[ $last_field ]['min_selections'] = 0;
						$fields[ $last_field ]['max_selections'] = 1;
					} elseif ( $fields[ $last_field ]['max_selections'] && $fields[ $last_field ]['max_selections'] < $fields[ $last_field ]['min_selections'] ) {
						$fields[ $last_field ]['max_selections'] = $fields[ $last_field ]['min_selections'];
					}
				}
				if ( 'search_select' === $type ) {
					$fields[ $last_field ]['selection_mode'] = 'multiple';
					$fields[ $last_field ]['allow_custom_values'] = false;
					$fields[ $last_field ]['min_selections'] = 0;
					$fields[ $last_field ]['max_selections'] = max( 1, min( 100, absint( $field['max_selections'] ?? 1 ) ) );
					if ( ! empty( $fields[ $last_field ]['disease_source'] ) ) {
						$fields[ $last_field ]['options'] = self::disease_options();
					}
				}
			}
			if ( $fields || trim( (string) ( $section['title'] ?? '' ) ) || trim( (string) ( $section['description'] ?? '' ) ) ) {
				$clean['sections'][] = array(
					'key' => $section_key,
					'title' => sanitize_text_field( $section['title'] ?? '' ),
					'description' => sanitize_textarea_field( $section['description'] ?? '' ),
					'fields' => $fields,
				);
			}
		}
		if ( ! $clean['sections'] ) { return new WP_Error( 'empty', __( 'Add at least one section or field.', 'ggm-member-dashboard' ) ); }
		return $clean;
	}

	/**
	 * Allowed tags for the small inline rich text controls (checkboxes option
	 * labels) — intentionally inline-only, no block-level tags, since a single
	 * choice label isn't a place for a bulleted list or a paragraph break.
	 */
	private static function inline_html_tags() {
		return array(
			'strong' => array(),
			'b' => array(),
			'em' => array(),
			'i' => array(),
			'br' => array(),
			'a' => array( 'href' => true, 'rel' => true, 'target' => true ),
		);
	}

	public static function country_calling_codes() {
		static $countries = null;
		if ( null !== $countries ) { return $countries; }
		$raw = 'AF:+93 AX:+358 AL:+355 DZ:+213 AS:+1 AD:+376 AO:+244 AI:+1 AQ:+672 AG:+1 AR:+54 AM:+374 AW:+297 AU:+61 AT:+43 AZ:+994 BS:+1 BH:+973 BD:+880 BB:+1 BY:+375 BE:+32 BZ:+501 BJ:+229 BM:+1 BT:+975 BO:+591 BQ:+599 BA:+387 BW:+267 BV:+47 BR:+55 IO:+246 BN:+673 BG:+359 BF:+226 BI:+257 CV:+238 KH:+855 CM:+237 CA:+1 KY:+1 CF:+236 TD:+235 CL:+56 CN:+86 CX:+61 CC:+61 CO:+57 KM:+269 CG:+242 CD:+243 CK:+682 CR:+506 CI:+225 HR:+385 CU:+53 CW:+599 CY:+357 CZ:+420 DK:+45 DJ:+253 DM:+1 DO:+1 EC:+593 EG:+20 SV:+503 GQ:+240 ER:+291 EE:+372 SZ:+268 ET:+251 FK:+500 FO:+298 FJ:+679 FI:+358 FR:+33 GF:+594 PF:+689 TF:+262 GA:+241 GM:+220 GE:+995 DE:+49 GH:+233 GI:+350 GR:+30 GL:+299 GD:+1 GP:+590 GU:+1 GT:+502 GG:+44 GN:+224 GW:+245 GY:+592 HT:+509 HM:+672 VA:+39 HN:+504 HK:+852 HU:+36 IS:+354 IN:+91 ID:+62 IR:+98 IQ:+964 IE:+353 IM:+44 IL:+972 IT:+39 JM:+1 JP:+81 JE:+44 JO:+962 KZ:+7 KE:+254 KI:+686 KP:+850 KR:+82 KW:+965 KG:+996 LA:+856 LV:+371 LB:+961 LS:+266 LR:+231 LY:+218 LI:+423 LT:+370 LU:+352 MO:+853 MG:+261 MW:+265 MY:+60 MV:+960 ML:+223 MT:+356 MH:+692 MQ:+596 MR:+222 MU:+230 YT:+262 MX:+52 FM:+691 MD:+373 MC:+377 MN:+976 ME:+382 MS:+1 MA:+212 MZ:+258 MM:+95 NA:+264 NR:+674 NP:+977 NL:+31 NC:+687 NZ:+64 NI:+505 NE:+227 NG:+234 NU:+683 NF:+672 MK:+389 MP:+1 NO:+47 OM:+968 PK:+92 PW:+680 PS:+970 PA:+507 PG:+675 PY:+595 PE:+51 PH:+63 PN:+64 PL:+48 PT:+351 PR:+1 QA:+974 RE:+262 RO:+40 RU:+7 RW:+250 BL:+590 SH:+290 KN:+1 LC:+1 MF:+590 PM:+508 VC:+1 WS:+685 SM:+378 ST:+239 SA:+966 SN:+221 RS:+381 SC:+248 SL:+232 SG:+65 SX:+1 SK:+421 SI:+386 SB:+677 SO:+252 ZA:+27 GS:+500 SS:+211 ES:+34 LK:+94 SD:+249 SR:+597 SJ:+47 SE:+46 CH:+41 SY:+963 TW:+886 TJ:+992 TZ:+255 TH:+66 TL:+670 TG:+228 TK:+690 TO:+676 TT:+1 TN:+216 TR:+90 TM:+993 TC:+1 TV:+688 UG:+256 UA:+380 AE:+971 GB:+44 US:+1 UM:+1 UY:+598 UZ:+998 VU:+678 VE:+58 VN:+84 VG:+1 VI:+1 WF:+681 EH:+212 YE:+967 ZM:+260 ZW:+263 XK:+383';
		$countries = array();
		foreach ( preg_split( '/\s+/', trim( $raw ) ) as $item ) {
			list( $iso, $dial ) = explode( ':', $item, 2 );
			$countries[] = array(
				'iso'  => $iso,
				'dial' => $dial,
				'name' => self::country_display_name( $iso ),
			);
		}
		usort( $countries, static function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );
		return $countries;
	}

	/**
	 * Shared searchable country calling-code picker used by member-facing forms.
	 *
	 * @param string $name          Hidden input name.
	 * @param string $id            Hidden input ID.
	 * @param string $selected_dial Selected dial code; defaults safely to +91.
	 * @param string $extra_class   Optional wrapper class.
	 * @return string
	 */
	public static function country_picker_html( $name, $id, $selected_dial = '+91', $extra_class = '' ) {
		$countries = self::country_calling_codes();
		$selected  = null;
		foreach ( $countries as $country ) {
			if ( (string) $selected_dial === (string) $country['dial'] ) {
				$selected = $country;
				break;
			}
		}
		if ( ! $selected ) {
			foreach ( $countries as $country ) {
				if ( 'IN' === $country['iso'] ) { $selected = $country; break; }
			}
		}
		$selected = $selected ?: array( 'iso'=>'IN', 'name'=>'India', 'dial'=>'+91' );

		ob_start();
		?>
		<div class="ggm-country-picker ggm-shared-country-picker <?php echo esc_attr( $extra_class ); ?>" data-country-picker>
			<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $selected['dial'] ); ?>">
			<button type="button" class="ggm-country-picker-button" aria-haspopup="listbox" aria-expanded="false">
				<?php echo self::country_flag_svg( $selected['iso'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from a validated ISO code. ?>
				<span><?php echo esc_html( $selected['dial'] ); ?></span>
			</button>
			<div class="ggm-country-options" role="listbox">
				<input type="search" class="ggm-country-search" placeholder="<?php esc_attr_e( 'Search country or code', 'ggm-member-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Search countries', 'ggm-member-dashboard' ); ?>">
				<?php foreach ( $countries as $country ) : ?>
					<button type="button" class="ggm-country-option" role="option" data-code="<?php echo esc_attr( $country['dial'] ); ?>" data-iso="<?php echo esc_attr( strtolower( $country['iso'] ) ); ?>" data-search="<?php echo esc_attr( strtolower( $country['name'] . ' ' . $country['iso'] . ' ' . $country['dial'] ) ); ?>" aria-selected="<?php echo $country['dial'] === $selected['dial'] ? 'true' : 'false'; ?>">
						<?php echo self::country_flag_svg( $country['iso'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from a validated ISO code. ?>
						<span><?php echo esc_html( $country['name'] . ' ' . $country['dial'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function country_display_name( $iso ) {
		$iso = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $iso ) );
		if ( 'XK' === $iso ) { return 'Kosovo'; }
		if ( class_exists( 'Locale' ) ) {
			$name = Locale::getDisplayRegion( 'und_' . $iso, function_exists( 'get_locale' ) ? get_locale() : 'en' );
			if ( is_string( $name ) && '' !== $name && strtoupper( $name ) !== $iso ) { return $name; }
		}
		return $iso;
	}

	public static function country_flag_svg( $iso ) {
		$iso = strtolower( preg_replace( '/[^A-Za-z]/', '', (string) $iso ) );
		if ( 2 !== strlen( $iso ) ) { return ''; }

		// Load the bundled, trusted flag definitions once per request, then place
		// the selected flag's real vector elements directly in the page markup.
		// The browser therefore makes no flag image/sprite request and the picker
		// never depends on an external <use href="..."> reference.
		static $symbols = null;
		if ( null === $symbols ) {
			$symbols = array();
			$flag_file = GGM_PLUGIN_DIR . 'assets/svg/country-flags.svg';
			$source = is_readable( $flag_file ) ? file_get_contents( $flag_file ) : '';
			if ( is_string( $source ) && preg_match_all( '/<symbol\b([^>]*)\bid="ggm-flag-([a-z]{2})"([^>]*)>(.*?)<\/symbol>/s', $source, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$attributes = $match[1] . $match[3];
					$view_box = preg_match( '/\bviewBox="([^"]+)"/', $attributes, $view_box_match ) ? $view_box_match[1] : '0 0 640 480';
					$symbols[ $match[2] ] = array( 'viewBox' => $view_box, 'markup' => $match[4] );
				}
			}
		}

		if ( empty( $symbols[ $iso ] ) ) { return ''; }
		return '<svg class="ggm-country-flag" viewBox="' . esc_attr( $symbols[ $iso ]['viewBox'] ) . '" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" data-country-flag="' . esc_attr( $iso ) . '" aria-hidden="true" focusable="false">' . $symbols[ $iso ]['markup'] . '</svg>';
	}

	private static function sanitize_field_width( $width ) {
		$width = preg_replace( '/[^0-9]/', '', (string) $width );
		return in_array( $width, array( '30','50','100' ), true ) ? $width : '100';
	}

	private static function sanitize_country_iso( $iso ) {
		$iso = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $iso ) );
		$valid = wp_list_pluck( self::country_calling_codes(), 'dial', 'iso' );
		return isset( $valid[ $iso ] ) ? $iso : 'IN';
	}

	private static function country_dial_for_iso( $iso ) {
		$iso = self::sanitize_country_iso( $iso );
		$valid = wp_list_pluck( self::country_calling_codes(), 'dial', 'iso' );
		return $valid[ $iso ] ?? '+91';
	}

	private static function sanitize_country_dial( $dial ) {
		$dial = '+' . preg_replace( '/\D/', '', (string) $dial );
		$valid = array_flip( wp_list_pluck( self::country_calling_codes(), 'dial' ) );
		return isset( $valid[ $dial ] ) ? $dial : '+91';
	}

	/** Public validation wrapper for shared member forms. */
	public static function sanitize_country_dial_value( $dial ) {
		return self::sanitize_country_dial( $dial );
	}

	private static function allowed_value( $value, array $allowed, $default ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Return the server-approved paid choices for a form. Older paid forms did
	 * not have choices, so their fixed amount remains a single stable fallback.
	 */
	public static function get_payment_options( array $settings ) {
		$options = array();
		$seen_ids = array();
		foreach ( (array) ( $settings['payment_options'] ?? array() ) as $option ) {
			$id     = sanitize_key( $option['id'] ?? '' );
			$label  = sanitize_text_field( $option['label'] ?? '' );
			$description = sanitize_text_field( $option['description'] ?? '' );
			$amount = round( (float) ( $option['amount'] ?? 0 ), 2 );
			if ( ! $id || isset( $seen_ids[ $id ] ) || '' === $label || $amount <= 0 ) { continue; }
			$seen_ids[ $id ] = true;
			$options[] = array(
				'id'     => $id,
				'label'  => $label,
				'description' => $description,
				'amount' => $amount,
				'width'  => self::sanitize_field_width( $option['width'] ?? '100' ),
			);
		}
		// A legacy form has no payment_options key at all. Do not silently fall
		// back to its old amount when a saved multi-price configuration is invalid.
		if ( ! $options && ! array_key_exists( 'payment_options', $settings ) ) {
			$legacy_amount = round( (float) ( $settings['payment_amount'] ?? 0 ), 2 );
			if ( $legacy_amount > 0 ) {
				$options[] = array(
					'id'     => 'legacy_fixed_price',
					'label'  => sanitize_text_field( $settings['payment_label'] ?? __( 'Payment', 'ggm-member-dashboard' ) ),
					'amount' => $legacy_amount,
					'width'  => '100',
				);
			}
		}
		return $options;
	}

	private function sanitize_payment_options( $raw_options ) {
		$options = array();
		$seen_labels = array();
		$seen_ids = array();
		foreach ( array_slice( (array) $raw_options, 0, 20 ) as $row ) {
			$label = sanitize_text_field( wp_unslash( $row['label'] ?? '' ) );
			$description = sanitize_text_field( wp_unslash( $row['description'] ?? '' ) );
			$amount = round( (float) wp_unslash( $row['amount'] ?? 0 ), 2 );
			$id = sanitize_key( wp_unslash( $row['id'] ?? '' ) );
			$label_key = strtolower( trim( $label ) );
			if ( '' === $label && $amount <= 0 ) { continue; }
			if ( '' === $label || $amount <= 0 || isset( $seen_labels[ $label_key ] ) ) {
				return new WP_Error( 'payment_options', __( 'Every payment price choice needs a unique heading and an amount greater than zero.', 'ggm-member-dashboard' ) );
			}
			if ( ! $id || isset( $seen_ids[ $id ] ) || 'legacy_fixed_price' === $id ) {
				$id = 'price_' . strtolower( wp_generate_password( 12, false, false ) );
			}
			$seen_ids[ $id ] = true;
			$seen_labels[ $label_key ] = true;
			$options[] = array(
				'id'     => $id,
				'label'  => $label,
				'description' => $description,
				'amount' => $amount,
				'width'  => self::sanitize_field_width( $row['width'] ?? '100' ),
			);
		}
		return $options;
	}

	private static function payment_option_by_id( array $settings, $option_id ) {
		$option_id = sanitize_key( $option_id );
		foreach ( self::get_payment_options( $settings ) as $option ) {
			if ( hash_equals( $option['id'], $option_id ) ) { return $option; }
		}
		return null;
	}

	/** Validate legacy free-text form currency settings before display/payment. */
	private static function payment_currency( $currency ) {
		if ( class_exists( 'GGM_Currency' ) ) {
			return GGM_Currency::normalize_code( $currency, GGM_Currency::base_currency() );
		}

		$currency = strtoupper( trim( sanitize_text_field( (string) $currency ) ) );
		return preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : 'INR';
	}

	private static function format_payment_option_amount( $amount, $currency ) {
		return self::payment_currency( $currency ) . ' ' . number_format_i18n( (float) $amount, 2 );
	}

	/** Reference checkout uses the Indian currency glyph, without changing other forms' currency output. */
	private static function format_health_checkout_amount( $amount, $currency ) {
		$currency = self::payment_currency( $currency );
		// Keep the screenshot's comma grouping and decimal point independent of
		// the WordPress locale; the underlying numeric amount is unchanged.
		return ( 'INR' === $currency ? '₹' : $currency ) . ' ' . number_format( (float) $amount, 2, '.', ',' );
	}

	/** Width for the one price-selection group, with per-price widths retained only as a migration fallback. */
	private static function payment_options_width( array $settings, array $options = array() ) {
		$fallback = ! empty( $options[0]['width'] ) ? $options[0]['width'] : '100';
		return self::sanitize_field_width( $settings['payment_options_width'] ?? $fallback );
	}

	private static function heading_size( $value ) {
		if ( '' === trim( (string) $value ) ) { return ''; }
		return max( 10, min( 96, absint( $value ) ) );
	}

	private static function guest_payment_token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function guest_payment_cookie_name( $form_id, $context, $context_id ) {
		return 'ggm_paid_form_' . absint( $form_id ) . '_' . sanitize_key( $context ) . '_' . absint( $context_id );
	}

	private static function guest_paid_submission( $form_id, $context, $context_id ) {
		$cookie_name = self::guest_payment_cookie_name( $form_id, $context, $context_id );
		$cookie = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ?? '' ) );
		if ( ! preg_match( '/^(\d+)\.([a-f0-9]{64})$/', $cookie, $parts ) ) { return null; }
		global $wpdb;
		$submission = $wpdb->get_row( $wpdb->prepare( 'SELECT id,answers_json FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE id=%d AND form_id=%d AND user_id=0 AND context_type=%s AND context_id=%d AND status='paid_awaiting_submission'", absint( $parts[1] ), absint( $form_id ), sanitize_key( $context ), absint( $context_id ) ) );
		if ( ! $submission ) { return null; }
		$answers = json_decode( (string) $submission->answers_json, true );
		$stored_hash = is_array( $answers ) ? (string) ( $answers['_ggm_guest_payment_token_hash'] ?? '' ) : '';
		return $stored_hash && hash_equals( $stored_hash, self::guest_payment_token_hash( $parts[2] ) ) ? $submission : null;
	}

	private static function set_guest_payment_cookie( $form_id, $context, $context_id, $submission_id, $token ) {
		$name = self::guest_payment_cookie_name( $form_id, $context, $context_id );
		$value = absint( $submission_id ) . '.' . $token;
		setcookie( $name, $value, array( 'expires'=>time() + DAY_IN_SECONDS, 'path'=>COOKIEPATH ?: '/', 'secure'=>is_ssl(), 'httponly'=>true, 'samesite'=>'Lax' ) );
		$_COOKIE[ $name ] = $value;
	}

	private static function clear_guest_payment_cookie( $form_id, $context, $context_id ) {
		$name = self::guest_payment_cookie_name( $form_id, $context, $context_id );
		setcookie( $name, '', array( 'expires'=>time() - HOUR_IN_SECONDS, 'path'=>COOKIEPATH ?: '/', 'secure'=>is_ssl(), 'httponly'=>true, 'samesite'=>'Lax' ) );
		unset( $_COOKIE[ $name ] );
	}

	private function sanitize_extensions( $value ) {
		$items = is_array( $value ) ? $value : preg_split( '/[,\s]+/', strtolower( (string) $value ) );
		$items = array_map( static function( $ext ) { return preg_replace( '/[^a-z0-9]/', '', (string) $ext ); }, $items );
		return array_values( array_unique( array_filter( $items ) ) );
	}

	private function sanitize_date_config( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	private function sanitize_duration_units( $value ) {
		$units = array_values( array_intersect( array( 'hours','minutes','seconds' ), array_map( 'sanitize_key', (array) $value ) ) );
		return $units ?: array( 'hours','minutes' );
	}

	public function save_form() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ) ); }
		check_admin_referer( 'ggm_save_form' );
		global $wpdb;
		$form_id = absint( $_POST['form_id'] ?? 0 );
		$title = sanitize_text_field( wp_unslash( $_POST['form_title'] ?? '' ) );
		$status = in_array( $_POST['form_status'] ?? '', array( 'draft','published' ), true ) ? $_POST['form_status'] : 'draft';
		$schema = $this->clean_schema( $_POST['schema_json'] ?? '' );
		if ( ! $title || is_wp_error( $schema ) ) {
			$redirect_args = array( 'page'=>'ggm-health-intakes', 'tab'=>'builder', 'error'=>'invalid_form' );
			if ( $form_id && self::get_form( $form_id ) ) { $redirect_args['edit_form'] = $form_id; }
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) ); exit;
		}
		$payment_options = $this->sanitize_payment_options( $_POST['payment_options'] ?? array() );
		if ( is_wp_error( $payment_options ) ) {
			$redirect_args = array( 'page'=>'ggm-health-intakes', 'tab'=>'builder', 'error'=>'invalid_payment_options' );
			if ( $form_id && self::get_form( $form_id ) ) { $redirect_args['edit_form'] = $form_id; }
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) ); exit;
		}
		$settings = array(
			'description' => sanitize_textarea_field( wp_unslash( $_POST['form_description'] ?? '' ) ),
			'form_heading_tag' => $this->allowed_value( $_POST['form_heading_tag'] ?? 'h2', array( 'h1','h2','h3','h4','h5','h6','p' ), 'h2' ),
			'form_heading_size' => $this->heading_size( $_POST['form_heading_size'] ?? '' ),
			'submit_label' => sanitize_text_field( wp_unslash( $_POST['submit_label'] ?? 'Submit' ) ),
			'next_label' => sanitize_text_field( wp_unslash( $_POST['next_label'] ?? 'Next' ) ),
			'back_label' => sanitize_text_field( wp_unslash( $_POST['back_label'] ?? 'Back' ) ),
			'confirmation' => wp_kses_post( wp_unslash( $_POST['confirmation'] ?? 'Thank you. Your response has been submitted.' ) ),
			'progress' => in_array( $_POST['progress'] ?? '', array( 'none','pages','bar' ), true ) ? $_POST['progress'] : 'bar',
			'allow_multiple' => ! empty( $_POST['allow_multiple'] ),
			'allow_additional_resubmission' => ! empty( $_POST['allow_additional_resubmission'] ),
			'allow_guests' => ! empty( $_POST['allow_guests'] ),
			// This is deliberately opt-in per form. Existing forms retain the
			// standard renderer unless their own settings enable this theme.
			'health_checkout_theme' => ! empty( $_POST['health_checkout_theme'] ),
			'whatsapp_group_url' => esc_url_raw( wp_unslash( $_POST['whatsapp_group_url'] ?? '' ) ),
			'payment_enabled' => ! empty( $_POST['payment_enabled'] ),
			'payment_flow' => $this->allowed_value( $_POST['payment_flow'] ?? 'submit', array( 'submit','before_form' ), 'submit' ),
			'payment_first_heading' => sanitize_text_field( wp_unslash( $_POST['payment_first_heading'] ?? 'Complete payment to continue' ) ),
			'payment_first_heading_tag' => $this->allowed_value( $_POST['payment_first_heading_tag'] ?? 'h3', array( 'h1','h2','h3','h4','h5','h6','p' ), 'h3' ),
			'payment_first_heading_size' => $this->heading_size( $_POST['payment_first_heading_size'] ?? '' ),
			'payment_first_text' => sanitize_textarea_field( wp_unslash( $_POST['payment_first_text'] ?? 'After payment is verified, the form fields will be available immediately.' ) ),
			'payment_label' => sanitize_text_field( wp_unslash( $_POST['payment_label'] ?? 'Pay & Submit' ) ),
			'payment_amount' => max( 0, round( (float) wp_unslash( $_POST['payment_amount'] ?? 0 ), 2 ) ), // Legacy fallback only.
			'payment_options' => $payment_options,
			'payment_options_width' => self::sanitize_field_width( $_POST['payment_options_width'] ?? '100' ),
			'payment_currency' => self::payment_currency( wp_unslash( $_POST['payment_currency'] ?? ggm_get_setting( 'ggm_currency', 'INR' ) ) ),
			'payment_description' => sanitize_text_field( wp_unslash( $_POST['payment_description'] ?? '' ) ),
			'payment_success' => wp_kses_post( wp_unslash( $_POST['payment_success'] ?? 'Payment received. Your response has been submitted.' ) ),
		);
		if ( ! empty( $settings['payment_enabled'] ) && ! $settings['payment_options'] ) {
			$redirect_args = array( 'page'=>'ggm-health-intakes', 'tab'=>'builder', 'error'=>'invalid_payment_options' );
			if ( $form_id && self::get_form( $form_id ) ) { $redirect_args['edit_form'] = $form_id; }
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) ); exit;
		}
		if ( empty( $settings['payment_enabled'] ) ) {
			$settings['payment_enabled'] = false;
			$settings['payment_amount']  = 0;
		}
		$now = current_time( 'mysql' );
		if ( $form_id && self::get_form( $form_id ) ) {
			$form = self::get_form( $form_id );
			$version = (int) $form->current_version + 1;
			$wpdb->update( self::table( 'ggm_forms' ), array( 'title'=>$title, 'status'=>$status, 'current_version'=>$version, 'settings_json'=>wp_json_encode( $settings ), 'updated_at'=>$now ), array( 'id'=>$form_id ) );
		} else {
			$version = 1;
			$slug = sanitize_title( $title ) . '-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 );
			$wpdb->insert( self::table( 'ggm_forms' ), array( 'title'=>$title, 'slug'=>$slug, 'status'=>$status, 'current_version'=>1, 'settings_json'=>wp_json_encode( $settings ), 'created_by'=>get_current_user_id(), 'created_at'=>$now, 'updated_at'=>$now ) );
			$form_id = (int) $wpdb->insert_id;
		}
		$wpdb->insert( self::table( 'ggm_form_versions' ), array( 'form_id'=>$form_id, 'version'=>$version, 'schema_json'=>wp_json_encode( $schema ), 'created_by'=>get_current_user_id(), 'created_at'=>$now ) );
		$wpdb->delete( self::table( 'ggm_form_assignments' ), array( 'form_id'=>$form_id ) );
		$locations = array_map( 'sanitize_key', (array) ( $_POST['locations'] ?? array() ) );
		foreach ( array_unique( $locations ) as $location ) {
			if ( in_array( $location, array( 'dashboard','workshop_all','course_all','elementor_popup','shortcode' ), true ) ) {
				$wpdb->insert( self::table( 'ggm_form_assignments' ), array( 'form_id'=>$form_id, 'location_type'=>$location, 'object_id'=>0, 'status'=>'active' ) );
			}
		}
		foreach ( array( 'workshop_specific'=>'workshop_ids', 'course_specific'=>'course_ids' ) as $location => $source ) {
			foreach ( array_unique( array_filter( array_map( 'absint', (array) ( $_POST[ $source ] ?? array() ) ) ) ) as $object_id ) {
				$wpdb->insert( self::table( 'ggm_form_assignments' ), array( 'form_id'=>$form_id, 'location_type'=>$location, 'object_id'=>$object_id, 'status'=>'active' ) );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page'=>'ggm-health-intakes','tab'=>'builder','saved'=>1,'edit_form'=>$form_id ), admin_url( 'admin.php' ) ) ); exit;
	}

	public function archive_form() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.' ); }
		$form_id = absint( $_GET['form_id'] ?? 0 );
		check_admin_referer( 'ggm_archive_form_' . $form_id );
		global $wpdb;
		$wpdb->update( self::table( 'ggm_forms' ), array( 'status'=>'archived', 'updated_at'=>current_time( 'mysql' ) ), array( 'id'=>$form_id ) );
		wp_safe_redirect( add_query_arg( array( 'page'=>'ggm-health-intakes','tab'=>'builder' ), admin_url( 'admin.php' ) ) ); exit;
	}

	/**
	 * AJAX: permanently delete a form and everything under it — versions,
	 * placements, member submissions, and any files those submissions
	 * uploaded. Unlike archive_form() (a soft status change only), this is
	 * irreversible, so the admin must have already typed the form's exact
	 * title client-side; that same title is re-checked here server-side
	 * rather than trusted from the browser.
	 */
	public function ajax_delete_form() {
		check_ajax_referer( 'ggm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'ggm-member-dashboard' ) ) );
		}
		$form_id = absint( $_POST['form_id'] ?? 0 );
		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form ID.', 'ggm-member-dashboard' ) ) );
		}
		global $wpdb;
		$form = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title FROM ' . self::table( 'ggm_forms' ) . ' WHERE id=%d', $form_id ) );
		if ( ! $form ) {
			wp_send_json_error( array( 'message' => __( 'Form not found.', 'ggm-member-dashboard' ) ) );
		}
		$confirm_title = trim( sanitize_text_field( wp_unslash( $_POST['confirm_title'] ?? '' ) ) );
		if ( '' === $confirm_title || $confirm_title !== trim( $form->title ) ) {
			wp_send_json_error( array( 'message' => __( 'The typed title did not match. Nothing was deleted.', 'ggm-member-dashboard' ) ) );
		}

		$submission_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'ggm_form_submissions' ) . ' WHERE form_id=%d', $form_id ) );
		if ( $submission_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );
			$paths = $wpdb->get_col( $wpdb->prepare( 'SELECT relative_path FROM ' . self::table( 'ggm_form_files' ) . " WHERE submission_id IN ($placeholders)", $submission_ids ) );
			foreach ( (array) $paths as $relative_path ) {
				$path = WP_CONTENT_DIR . '/ggm-private-forms/' . basename( $relative_path );
				if ( is_file( $path ) ) { wp_delete_file( $path ); }
			}
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'ggm_form_files' ) . " WHERE submission_id IN ($placeholders)", $submission_ids ) );
		}
		$wpdb->delete( self::table( 'ggm_form_submissions' ), array( 'form_id' => $form_id ) );
		$wpdb->delete( self::table( 'ggm_form_assignments' ), array( 'form_id' => $form_id ) );
		$wpdb->delete( self::table( 'ggm_form_payments' ), array( 'form_id' => $form_id ) );
		$wpdb->delete( self::table( 'ggm_form_versions' ), array( 'form_id' => $form_id ) );
		$wpdb->delete( self::table( 'ggm_forms' ), array( 'id' => $form_id ) );

		wp_send_json_success( array( 'message' => __( 'Form deleted.', 'ggm-member-dashboard' ) ) );
	}

	/**
	 * Render a saved or currently edited form in an administrator-only,
	 * non-submitting preview document.
	 */
	public function preview_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
		}

		$form_id = absint( $_REQUEST['form_id'] ?? 0 );
		$is_post = 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
		if ( $is_post ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['preview_nonce'] ?? '' ) );
			if ( ! wp_verify_nonce( $nonce, 'ggm_preview_form_builder' ) ) {
				wp_die( esc_html__( 'The preview link has expired.', 'ggm-member-dashboard' ), '', array( 'response' => 403 ) );
			}
			$schema = $this->clean_schema( $_POST['schema_json'] ?? '' );
			$title  = sanitize_text_field( wp_unslash( $_POST['form_title'] ?? __( 'Untitled Form', 'ggm-member-dashboard' ) ) );
			$description = sanitize_textarea_field( wp_unslash( $_POST['form_description'] ?? '' ) );
			$heading_tag = $this->allowed_value( $_POST['form_heading_tag'] ?? 'h2', array( 'h1','h2','h3','h4','h5','h6','p' ), 'h2' );
			$heading_size = $this->heading_size( $_POST['form_heading_size'] ?? '' );
		} else {
			check_admin_referer( 'ggm_preview_form_' . $form_id );
			$form = self::get_form( $form_id );
			if ( ! $form ) {
				wp_die( esc_html__( 'Form not found.', 'ggm-member-dashboard' ), '', array( 'response' => 404 ) );
			}
			$schema      = self::get_schema( $form );
			$title       = $form->title;
			$settings    = json_decode( (string) $form->settings_json, true );
			$description = is_array( $settings ) ? (string) ( $settings['description'] ?? '' ) : '';
			$heading_tag = $this->allowed_value( $settings['form_heading_tag'] ?? 'h2', array( 'h1','h2','h3','h4','h5','h6','p' ), 'h2' );
			$heading_size = $this->heading_size( $settings['form_heading_size'] ?? '' );
		}

		if ( is_wp_error( $schema ) || empty( $schema['sections'] ) ) {
			wp_die( esc_html__( 'The form structure is invalid or empty.', 'ggm-member-dashboard' ), '', array( 'response' => 400 ) );
		}
		$preview_sections = array_values( (array) $schema['sections'] );

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<title><?php echo esc_html( sprintf( __( 'Preview: %s', 'ggm-member-dashboard' ), $title ) ); ?></title>
			<style>
				*{box-sizing:border-box}body{margin:0;padding:36px 18px;background:#f4f6fb;color:#1f2937;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.ggm-preview-shell{max-width:850px;margin:auto}.ggm-preview-banner{position:sticky;top:0;z-index:2;margin-bottom:16px;padding:11px 15px;border:1px solid #f0c36d;border-radius:8px;background:#fff8df;color:#684d00;font-weight:600}.ggm-preview-form{padding:30px;border:1px solid #dfe4ec;border-radius:14px;background:#fff}h1{margin:0 0 8px}.ggm-preview-section{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));column-gap:16px;margin-top:24px;padding-top:20px;border-top:1px solid #e8ebf0}.ggm-preview-section>h2,.ggm-preview-section>p{grid-column:1/-1}.ggm-form-field{grid-column:1/-1;min-width:0;margin:18px 0}.ggm-field-width-50{grid-column:span 5}.ggm-field-width-30{grid-column:span 3}.ggm-field-width-100{grid-column:1/-1}.ggm-form-field>label>strong{display:block;margin-bottom:7px}.ggm-form-field input[type=text],.ggm-form-field input[type=email],.ggm-form-field input[type=number],.ggm-form-field input[type=tel],.ggm-form-field input[type=search],.ggm-form-field input[type=url],.ggm-form-field input[type=date],.ggm-form-field input[type=time],.ggm-form-field textarea,.ggm-form-field select{width:100%;max-width:100%;padding:10px;border:1px solid #cfd5df;border-radius:7px;background:#fafbfc}.ggm-phone-country-control{display:grid;grid-template-columns:minmax(104px,max-content) minmax(0,1fr);gap:8px;max-width:720px}.ggm-country-picker{position:relative;width:max-content;max-width:100%;min-width:0}.ggm-country-picker-button,.ggm-country-option{display:flex!important;align-items:center!important;gap:8px!important;width:auto!important;min-width:104px!important;min-height:40px!important;border:1px solid #cfd5df!important;border-radius:7px!important;background:#fafbfc!important;color:#1f2937!important;text-align:left!important;font:inherit!important}.ggm-country-picker-button{justify-content:space-between!important;padding:9px 10px!important;cursor:pointer}.ggm-country-picker-button span{flex:0 0 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ggm-country-flag{flex:0 0 auto;width:24px;height:18px;object-fit:cover;border:1px solid #d0d7e2;border-radius:2px;background:#fff}.ggm-country-picker-button:after{content:"";flex:0 0 auto;border:5px solid transparent;border-top-color:#64748b;margin-top:5px}.ggm-country-options{position:absolute;z-index:20;top:calc(100% + 4px);left:0;right:auto;display:none;min-width:100%;max-height:260px;overflow:auto;margin:0;padding:5px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.16)}.ggm-country-picker.is-open .ggm-country-options{display:flex;flex-direction:column;gap:5px}.ggm-country-option{justify-content:flex-start!important;margin:0!important;padding:8px!important;border:0!important;background:#fff!important;cursor:pointer}.ggm-country-option:hover,.ggm-country-option[aria-selected=true]{background:#eef2ff!important}.ggm-search-select{width:100%;max-width:720px}.ggm-search-selected{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}.ggm-search-selected-chip{display:inline-flex;align-items:center;gap:7px;padding:6px 9px;border:1px solid #cbd5e1;border-radius:999px;background:#f8fafc;color:#111827;font-weight:600}.ggm-search-selected-chip button{display:inline-grid;place-items:center;width:18px;height:18px;padding:0;border:0;border-radius:999px;background:#e2e8f0;color:#111827;cursor:pointer;line-height:1}.ggm-search-results{display:none;flex-direction:column;gap:5px;max-height:240px;overflow:auto;margin-top:6px;padding:5px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.12)}.ggm-search-select.has-results .ggm-search-results{display:flex}.ggm-search-result{width:100%;margin:0;padding:9px 10px;border:0;border-radius:6px;background:#fff;color:#111827;text-align:left;cursor:pointer;font:inherit}.ggm-search-result:hover,.ggm-search-result:focus{background:#eef2ff;outline:2px solid #c7d2fe;outline-offset:0}.ggm-search-empty,.ggm-search-select-error{display:block;min-height:18px;margin-top:5px;color:#b91c1c}.ggm-search-values{display:none}.ggm-form-field input[type=date]{cursor:pointer}.ggm-form-choice{display:block;margin:7px 0}.ggm-response-grid{width:100%;border-collapse:collapse}.ggm-response-grid th,.ggm-response-grid td{padding:8px;border:1px solid #dfe4ec;text-align:center}.ggm-grid-scroll{overflow:auto}.description{color:#667085}.ggm-chip-options{display:flex;flex-wrap:wrap;gap:8px}.ggm-chip-option{min-height:38px;padding:7px 13px;border:1px solid #cbd5e1;border-radius:999px;background:#fff;cursor:pointer}.ggm-chip-option.is-selected{border-color:#4f46e5;background:#eef2ff;color:#4338ca}.ggm-chip-option.is-selected:before{content:'✓ '}.ggm-chip-option:disabled{opacity:.45}.ggm-chip-option-other{border-style:dashed;border-color:#6366f1;color:#4338ca;font-weight:600;background:#eef2ff}.ggm-chip-other-input{display:block;box-sizing:border-box;width:100%;max-width:400px;margin-top:8px;padding:10px;border:1px solid #cbd5e1;border-radius:7px}.ggm-chip-error{display:block;min-height:18px;color:#b91c1c}.ggm-chip-values{display:none}@media(max-width:600px){body{padding:18px 10px}.ggm-preview-form{padding:20px}.ggm-phone-country-control{grid-template-columns:minmax(104px,max-content) minmax(0,1fr)}.ggm-form-field,.ggm-field-width-50,.ggm-field-width-30,.ggm-field-width-100{grid-column:1/-1}}
			</style>
			<style>
				.ggm-upload-control{display:flex;flex-direction:column;gap:9px;max-width:720px;padding:14px;border:1px dashed #9aa4b2;border-radius:9px;background:#f8fafc}.ggm-file-input,.ggm-file-camera-input{position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none}.ggm-upload-dropzone{display:grid;place-items:center;min-height:92px;padding:18px;border:1px dashed #94a3b8;border-radius:8px;background:#fff;text-align:center;cursor:pointer}.ggm-upload-dropzone span{font-weight:700;color:#111827}.ggm-upload-dropzone small{display:block;color:#64748b}.ggm-upload-control.is-dragging .ggm-upload-dropzone{border-color:#4f46e5;background:#eef2ff}.ggm-upload-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.ggm-upload-actions .ggm-upload-browse,.ggm-upload-actions .ggm-upload-camera{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:40px!important;margin:0!important;padding:9px 15px!important;border:0!important;border-radius:999px!important;background:#69bd2a!important;color:#fff!important;font:700 14px/1.2 inherit!important;text-decoration:none!important;cursor:pointer!important}.ggm-upload-actions .ggm-upload-browse:hover,.ggm-upload-actions .ggm-upload-camera:hover{background:#58a91f!important;color:#fff!important}.ggm-camera-capture{display:grid;gap:10px;padding:12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff}.ggm-camera-capture video{width:100%;max-height:360px;border-radius:8px;background:#111;object-fit:contain}.ggm-camera-actions{display:flex;flex-wrap:wrap;gap:8px}.ggm-camera-status{margin:0;color:#64748b;font-size:13px}.ggm-upload-list{display:flex;flex-direction:column;gap:5px;margin:0;padding:0;list-style:none}.ggm-upload-list li{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 9px;border:1px solid #e2e8f0;border-radius:7px;background:#fff;color:#111827}.ggm-upload-list button{display:inline-grid;place-items:center;width:20px;height:20px;padding:0;border:0;border-radius:999px;background:#e2e8f0;color:#111827;cursor:pointer}
				.ggm-phone-country-control{display:flex!important;gap:0!important;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.ggm-phone-country-control .ggm-country-picker-button{border:0!important;border-right:1px solid #cbd5e1!important;border-radius:7px 0 0 7px!important}.ggm-phone-country-control>input[type=tel]{flex:1;min-width:0;border:0!important;border-radius:0 7px 7px 0!important}.ggm-country-options{width:min(340px,calc(100vw - 40px));min-width:260px!important}.ggm-country-search{position:sticky;top:0;z-index:1;width:100%!important;margin-bottom:4px;padding:8px 10px!important;border:1px solid #cbd5e1!important;background:#fff}.ggm-country-option{width:100%!important}.ggm-country-option[hidden]{display:none!important}
			</style>
		</head>
		<body>
			<main class="ggm-preview-shell">
				<div class="ggm-preview-banner"><?php esc_html_e( 'Preview only — responses cannot be submitted or saved.', 'ggm-member-dashboard' ); ?></div>
				<article class="ggm-preview-form">
					<<?php echo esc_attr( $heading_tag ); ?> class="ggm-form-title"<?php echo $heading_size ? ' style="font-size:' . esc_attr( $heading_size ) . 'px"' : ''; ?>><?php echo esc_html( $title ); ?></<?php echo esc_attr( $heading_tag ); ?>>
					<?php if ( $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
					<div class="ggm-preview-fields">
						<?php foreach ( $preview_sections as $index => $section ) : ?>
							<?php $duplicate_preview_heading = 1 === count( $preview_sections ) && 0 === (int) $index && ! empty( $section['title'] ) && self::headings_are_equivalent( $title, $section['title'] ); ?>
							<?php if ( $duplicate_preview_heading ) : ?><div class="ggm-preview-section"><?php else : ?><section class="ggm-preview-section"><?php endif; ?>
								<?php if ( ! empty( $section['title'] ) && ! $duplicate_preview_heading ) : ?><h2><?php echo esc_html( $section['title'] ); ?></h2><?php endif; ?>
								<?php if ( ! empty( $section['description'] ) ) : ?><p><?php echo esc_html( $section['description'] ); ?></p><?php endif; ?>
								<?php foreach ( (array) ( $section['fields'] ?? array() ) as $field ) { self::render_field( $field, 'preview-' ); } ?>
							<?php if ( $duplicate_preview_heading ) : ?></div><?php else : ?></section><?php endif; ?>
						<?php endforeach; ?>
					</div>
				</article>
			</main>
			<script><?php echo self::country_picker_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal script. ?></script>
			<script><?php echo self::chip_selector_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal script. ?></script>
			<script><?php echo self::search_select_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal script. ?></script>
			<script><?php echo self::file_upload_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal script. ?></script>
		</body>
		</html>
		<?php
		exit;
	}

	private static function assignments_for_context( $type, $id = 0 ) {
		global $wpdb;
		if ( 'dashboard' === $type ) {
			return $wpdb->get_results( "SELECT f.* FROM " . self::table( 'ggm_forms' ) . ' f INNER JOIN ' . self::table( 'ggm_form_assignments' ) . " a ON a.form_id=f.id WHERE f.status='published' AND a.status='active' AND a.location_type='dashboard' ORDER BY a.sort_order,a.id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$all = $type . '_all';
		$specific = $type . '_specific';
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT DISTINCT f.* FROM ' . self::table( 'ggm_forms' ) . ' f INNER JOIN ' . self::table( 'ggm_form_assignments' ) . " a ON a.form_id=f.id WHERE f.status='published' AND a.status='active' AND ((a.location_type=%s AND a.object_id=0) OR (a.location_type=%s AND a.object_id=%d)) ORDER BY a.sort_order,a.id",
			$all, $specific, absint( $id )
		) );
	}

	public static function render_dashboard_forms() {
		$rendered = 0;
		foreach ( (array) self::assignments_for_context( 'dashboard' ) as $form ) {
			$guard = $form->id . ':dashboard:0';
			if ( isset( self::$rendered[ $guard ] ) ) {
				$rendered++;
				continue;
			}
			$html = self::render_form( $form, 'dashboard', 0 );
			if ( '' === $html ) { continue; }
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$rendered++;
		}
		return $rendered;
	}

	/**
	 * Render forms assigned to the current workshop/course independently of
	 * post content. This keeps Elementor's Post Content widget content-only.
	 */
	public function assigned_forms_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'type' => '' ), $atts, 'ggm_assigned_forms' );
		$post_id = absint( $atts['id'] ) ?: get_the_ID();
		$post_type = get_post_type( $post_id );
		$type = sanitize_key( $atts['type'] );
		if ( ! in_array( $type, array( 'workshop', 'course' ), true ) ) {
			$type = in_array( $post_type, array( 'workshop', 'ggm_workshop' ), true ) ? 'workshop' : ( 'course' === $post_type ? 'course' : '' );
		}
		if ( ! $type || ! $post_id ) { return ''; }
		$html = '';
		foreach ( (array) self::assignments_for_context( $type, $post_id ) as $form ) { $html .= self::render_form( $form, $type, $post_id ); }
		return $html;
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id'=>0, 'context'=>'shortcode', 'context_id'=>0 ), $atts );
		$form = self::get_form( absint( $atts['id'] ) );
		if ( ! $form || 'published' !== $form->status ) { return ''; }
		if ( ! $this->context_allowed( $form->id, sanitize_key( $atts['context'] ), absint( $atts['context_id'] ) ) ) { return ''; }
		return self::render_form( $form, sanitize_key( $atts['context'] ), absint( $atts['context_id'] ) );
	}

	/**
	 * Treat headings as equivalent when they match after normalization and the
	 * optional terminal word "form" is removed.
	 */
	private static function headings_are_equivalent( $form_title, $section_title ) {
		$normalize = static function ( $value ) {
			$key = sanitize_title( wp_strip_all_tags( (string) $value ) );
			$suffixes = array_unique( array_filter( array(
				'form',
				sanitize_title( __( 'Form', 'ggm-member-dashboard' ) ),
			) ) );
			foreach ( $suffixes as $suffix ) {
				if ( $key === $suffix ) {
					$key = '';
					break;
				}
				$needle = '-' . $suffix;
				if ( strlen( $key ) > strlen( $needle ) && substr( $key, -strlen( $needle ) ) === $needle ) {
					$key = substr( $key, 0, -strlen( $needle ) );
					break;
				}
			}
			return trim( $key, '-' );
		};

		$form_key = $normalize( $form_title );
		$section_key = $normalize( $section_title );
		return '' !== $form_key && '' !== $section_key && $form_key === $section_key;
	}

	private static function split_submit_fields( array $sections ) {
		$visible_sections = array();
		$post_submit_fields = array();
		foreach ( $sections as $section ) {
			$visible_fields = array();
			foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
				if ( ! empty( $field['show_after_submit'] ) ) {
					$post_submit_fields[] = $field;
				} else {
					$visible_fields[] = $field;
				}
			}
			if ( $visible_fields || trim( (string) ( $section['title'] ?? '' ) ) || trim( (string) ( $section['description'] ?? '' ) ) ) {
				$section['fields'] = $visible_fields;
				$visible_sections[] = $section;
			}
		}
		return array( $visible_sections, $post_submit_fields );
	}

	private static function post_submit_fields_html( array $fields, $id_prefix, $prefill_user_id = 0, $hidden = false ) {
		if ( ! $fields ) { return ''; }
		$fields_id = sanitize_html_class( $id_prefix . 'additional-fields' );
		ob_start();
		echo '<div class="ggm-post-submit-disclosure"' . ( $hidden ? ' hidden' : '' ) . '>';
		echo '<button type="button" class="button button-primary ggm-post-submit-open" aria-expanded="false" aria-controls="' . esc_attr( $fields_id ) . '">' . esc_html__( 'Submit additional details', 'ggm-member-dashboard' ) . '</button>';
		echo '<div id="' . esc_attr( $fields_id ) . '" class="ggm-post-submit-fields" hidden>';
		echo '<h3>' . esc_html__( 'Additional details', 'ggm-member-dashboard' ) . '</h3>';
		foreach ( $fields as $field ) {
			self::render_field( $field, $id_prefix, $prefill_user_id );
		}
		echo '<div class="ggm-post-submit-actions"><button type="button" class="button button-primary ggm-post-submit-save">' . esc_html__( 'Save additional details', 'ggm-member-dashboard' ) . '</button></div>';
		echo '<div class="ggm-post-submit-feedback" aria-live="polite"></div>';
		echo '</div></div>';
		return ob_get_clean();
	}

	private static function member_files_html( $submission_id ) {
		global $wpdb;
		$submission = $wpdb->get_row( $wpdb->prepare( 'SELECT form_id,schema_snapshot_json FROM ' . self::table( 'ggm_form_submissions' ) . ' WHERE id=%d', absint( $submission_id ) ) );
		$current_form = $submission ? self::get_form( (int) $submission->form_id ) : null;
		$schema = $current_form ? self::get_schema( $current_form ) : ( $submission ? json_decode( (string) $submission->schema_snapshot_json, true ) : array() );
		$visible_keys = array();
		foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) {
			foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
				if ( 'file_upload' === ( $field['type'] ?? '' ) && ! empty( $field['show_uploaded_files_to_user'] ) ) { $visible_keys[] = sanitize_key( $field['key'] ?? '' ); }
			}
		}
		$visible_keys = array_filter( array_unique( $visible_keys ) );
		if ( ! $visible_keys ) { return ''; }
		$files = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'ggm_form_files' ) . ' WHERE submission_id=%d ORDER BY created_at DESC,id DESC', absint( $submission_id ) ) );
		$files = array_values( array_filter( (array) $files, static function( $file ) use ( $visible_keys ) { return in_array( sanitize_key( $file->field_key ), $visible_keys, true ); } ) );
		if ( ! $files ) { return ''; }
		$html = '<div class="ggm-member-files"><h4>' . esc_html__( 'Your uploaded files', 'ggm-member-dashboard' ) . '</h4><ul>';
		foreach ( $files as $file ) {
			$download = wp_nonce_url( admin_url( 'admin-post.php?action=ggm_form_file&mode=download&file_id=' . (int) $file->id ), 'ggm_form_file_' . (int) $file->id . '_download' );
			$can_preview = in_array( $file->mime_type, array( 'application/pdf','image/jpeg','image/png' ), true );
			$preview = $can_preview ? wp_nonce_url( admin_url( 'admin-post.php?action=ggm_form_file&mode=preview&file_id=' . (int) $file->id ), 'ggm_form_file_' . (int) $file->id . '_preview' ) : '';
			$html .= '<li class="ggm-member-file-card">';
			if ( $preview && in_array( $file->mime_type, array( 'image/jpeg','image/png' ), true ) ) {
				$html .= '<a class="ggm-member-file-visual" target="_blank" rel="noopener noreferrer" href="' . esc_url( $preview ) . '"><img loading="lazy" src="' . esc_url( $preview ) . '" alt="' . esc_attr( $file->original_name ) . '"></a>';
			} else {
				$html .= '<div class="ggm-member-file-visual is-document"><span class="dashicons dashicons-media-document" aria-hidden="true"></span><span>' . esc_html( strtoupper( pathinfo( $file->original_name, PATHINFO_EXTENSION ) ?: __( 'File', 'ggm-member-dashboard' ) ) ) . '</span></div>';
			}
			$html .= '<div class="ggm-member-file-meta"><strong title="' . esc_attr( $file->original_name ) . '">' . esc_html( $file->original_name ) . '</strong><span>' . esc_html( size_format( (int) $file->file_size ) ) . '</span></div><div class="ggm-member-file-actions">';
			if ( $preview ) { $html .= '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url( $preview ) . '">' . esc_html__( 'Preview', 'ggm-member-dashboard' ) . '</a>'; }
			$html .= '<a class="button button-small" href="' . esc_url( $download ) . '">' . esc_html__( 'Download', 'ggm-member-dashboard' ) . '</a></div></li>';
		}
		return $html . '</ul></div>';
	}

	private static function render_form( $form, $context, $context_id ) {
		$guard = $form->id . ':' . $context . ':' . $context_id;
		if ( isset( self::$rendered[ $guard ] ) ) { return ''; }
		self::$rendered[ $guard ] = true;
		// Assignment queries can supply an earlier row during a long request.
		// Always render from the current canonical form settings and version.
		$form = self::get_form( (int) $form->id );
		if ( ! $form || 'published' !== $form->status ) { return ''; }
		$schema = self::get_schema( $form );
		$settings = json_decode( (string) $form->settings_json, true );
		$settings = is_array( $settings ) ? $settings : array();
		// The supplied design targets form 3. Existing installs therefore receive
		// it immediately, while an explicitly saved unchecked setting can disable it.
		$health_checkout_theme = ! empty( $settings['health_checkout_theme'] ) || ( 3 === (int) $form->id && ! array_key_exists( 'health_checkout_theme', $settings ) );
		$allow_guests = ! empty( $settings['allow_guests'] );
		if ( ! is_user_logged_in() && ! $allow_guests ) { return '<div class="ggm-built-form"><p>' . esc_html__( 'Please log in to complete this form.', 'ggm-member-dashboard' ) . '</p></div>'; }
		$user_id = get_current_user_id();
		if ( $user_id && 'workshop' === $context && ! ggm_user_has_workshop_access( $context_id, $user_id ) ) { return ''; }
		if ( $user_id && 'course' === $context && ! ggm_user_has_course_access( $context_id, $user_id ) ) { return ''; }
		$payment_options = self::get_payment_options( $settings );
		$payment_options_width = self::payment_options_width( $settings, $payment_options );
		$payment_enabled = ! empty( $settings['payment_enabled'] ) && ! empty( $payment_options );
		$sections = (array) ( $schema['sections'] ?? array() );
		if ( ! $sections ) { return ''; }
		list( $visible_sections, $post_submit_fields ) = self::split_submit_fields( $sections );
		$sections = $visible_sections ?: array( array( 'key' => 'default', 'title' => '', 'description' => '', 'fields' => array() ) );
		global $wpdb;
		$payment_first = $payment_enabled && 'before_form' === ( $settings['payment_flow'] ?? 'submit' );
		$paid_session = null;
		if ( $payment_first ) {
			$paid_session = $user_id
				? $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE form_id=%d AND user_id=%d AND context_type=%s AND context_id=%d AND status='paid_awaiting_submission' ORDER BY id DESC LIMIT 1", $form->id, $user_id, $context, $context_id ) )
				: self::guest_paid_submission( (int) $form->id, $context, $context_id );
		}
		$payment_first_locked = $payment_first && ! $paid_session;
		if ( $paid_session ) { $payment_enabled = false; }
		if ( $payment_first_locked ) {
			$sections = array( array(
				'key'         => 'payment',
				'title'       => (string) ( $settings['payment_first_heading'] ?? __( 'Complete payment to continue', 'ggm-member-dashboard' ) ),
				'description' => (string) ( $settings['payment_first_text'] ?? __( 'After payment is verified, the form fields will be available immediately.', 'ggm-member-dashboard' ) ),
				'heading_tag'  => self::allowed_value( $settings['payment_first_heading_tag'] ?? 'h3', array( 'h1','h2','h3','h4','h5','h6','p' ), 'h3' ),
				'heading_size' => self::heading_size( $settings['payment_first_heading_size'] ?? '' ),
				'fields'      => array(),
			) );
			$post_submit_fields = array();
		}
		$has_multiple_pages = count( $sections ) > 1;
		$submitted = null;
		if ( $user_id ) {
			$submitted = $wpdb->get_row( $wpdb->prepare(
				'SELECT id, submitted_at, additional_completed_at, reviewed_at, answers_json FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE form_id=%d AND user_id=%d AND context_type=%s AND context_id=%d AND status='submitted' ORDER BY id DESC LIMIT 1",
				$form->id,
				$user_id,
				$context,
				$context_id
			) );
		}
		$submitted_at = $submitted ? (string) $submitted->submitted_at : '';
		$editing_submission = $submitted && absint( $_GET['ggm_edit_submission'] ?? 0 ) === (int) $submitted->id && empty( $submitted->reviewed_at );
		self::$editing_answers = $editing_submission ? ( json_decode( (string) $submitted->answers_json, true ) ?: array() ) : array();
		if ( $submitted_at && empty( $settings['allow_multiple'] ) && ! $editing_submission ) {
			$submitted_date = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submitted_at );
			$additional_complete = ! empty( $submitted->additional_completed_at );
			$repeat_fields = array_values( array_filter( $post_submit_fields, static function( $field ) use ( $settings ) { return ! empty( $settings['allow_additional_resubmission'] ) || ! empty( $field['allow_multiple_entries'] ); } ) );
			$render_post_fields = $additional_complete ? $repeat_fields : $post_submit_fields;
			$show_additional = $render_post_fields && empty( $submitted->reviewed_at );
			$complete_form = $show_additional ? '<form class="ggm-builder-public-form ggm-post-submit-form" enctype="multipart/form-data">' . self::post_submit_fields_html( $render_post_fields, 'complete-' . $form->id . '-', $user_id ) . '</form>' : '';
			if ( $post_submit_fields && $additional_complete && ! $show_additional ) { $complete_form .= '<p class="ggm-additional-complete">' . esc_html__( 'Additional details saved.', 'ggm-member-dashboard' ) . '</p>'; }
			$complete_form .= self::member_files_html( (int) $submitted->id );
			$review_html = ! empty( $submitted->reviewed_at ) ? '<button class="button" disabled aria-disabled="true">' . esc_html__( 'Reviewed by GGM Team', 'ggm-member-dashboard' ) . '</button>' : '<a class="button button-primary" href="' . esc_url( add_query_arg( 'ggm_edit_submission', (int) $submitted->id ) . '#tab-health' ) . '">' . esc_html__( 'Edit Submitted Details', 'ggm-member-dashboard' ) . '</a>';
			$output = '<div class="ggm-built-form ggm-form-complete" data-submission-id="' . esc_attr( (int) $submitted->id ) . '" data-after-submit-token=""><h3>' . esc_html__( 'Registration Complete', 'ggm-member-dashboard' ) . '</h3><div class="ggm-form-success" role="status">' . esc_html__( 'Your registration has been submitted successfully. You can edit your details until they have been reviewed by our team.', 'ggm-member-dashboard' ) . '</div><p class="ggm-form-submitted-at">' . sprintf( esc_html__( 'Submitted on %s', 'ggm-member-dashboard' ), esc_html( $submitted_date ) ) . '</p>' . $review_html . $complete_form . self::whatsapp_button_html( $settings ) . '</div>';
			if ( $show_additional ) {
				$output .= '<script>' . self::country_picker_script() . '</script><script>' . self::chip_selector_script() . '</script><script>' . self::search_select_script() . '</script><script>' . self::file_upload_script() . '</script><script>' . self::post_submit_runtime_script() . '</script>';
			}
			return $output;
		}
		$dashboard_auto_popup = 'dashboard' === $context && ! $submitted_at;
		ob_start();
		?>
		<?php if ( ! self::$public_styles_printed ) : self::$public_styles_printed = true; ?>
			<style id="ggm-built-form-shadow-reset">
				.ggm-built-form,
				.ggm-built-form *,
				.ggm-built-form *::before,
				.ggm-built-form *::after {
					box-shadow: none !important;
					text-shadow: none !important;
				}
				.elementor-widget-shortcode:has(.ggm-built-form),
				.elementor-widget-shortcode:has(.ggm-built-form) > .elementor-widget-container {
					box-shadow: none !important;
					text-shadow: none !important;
					filter: none !important;
				}
				.ggm-built-form :is(a[href], button, input:not([type="hidden"]), select, textarea, [tabindex]):focus-visible {
					outline: 3px solid #4338ca !important;
					outline-offset: 2px;
				}
				.ggm-built-form,
				.ggm-builder-public-form,
				.ggm-form-page {
					scroll-behavior: smooth;
				}
				.ggm-date-picker {
					cursor: pointer;
					touch-action: manipulation;
					scroll-margin-block: 120px;
				}
				.ggm-date-picker::-webkit-calendar-picker-indicator {
					cursor: pointer;
					padding: 6px;
				}
				.ggm-form-feedback {
					color: #111827 !important;
				}
				.ggm-search-select {
					width: 100%;
					max-width: 720px;
				}
				.ggm-search-selected {
					display: flex;
					flex-wrap: wrap;
					gap: 6px;
					min-height: 0;
					margin-bottom: 8px;
				}
				.ggm-search-selected-chip {
					display: inline-flex;
					align-items: center;
					gap: 7px;
					padding: 6px 9px;
					border: 1px solid #cbd5e1;
					border-radius: 999px;
					background: #f8fafc;
					color: #111827;
					font-weight: 600;
				}
				.ggm-search-selected-chip button {
					display: inline-grid;
					place-items: center;
					width: 18px;
					height: 18px;
					padding: 0;
					border: 0;
					border-radius: 999px;
					background: #e2e8f0;
					color: #111827;
					cursor: pointer;
					line-height: 1;
				}
				.ggm-search-select input[type="search"] {
					box-sizing: border-box;
					width: 100%;
					max-width: 720px;
					padding: 10px;
					border: 1px solid #cbd5e1;
					border-radius: 7px;
				}
				.ggm-search-results {
					display: none;
					flex-direction: column;
					gap: 5px;
					max-height: 240px;
					overflow: auto;
					margin-top: 6px;
					padding: 5px;
					border: 1px solid #cbd5e1;
					border-radius: 8px;
					background: #fff;
					box-shadow: 0 12px 30px rgba(15,23,42,.12) !important;
				}
				.ggm-search-select.has-results .ggm-search-results {
					display: flex;
				}
				.ggm-search-result {
					width: 100%;
					margin: 0;
					padding: 9px 10px;
					border: 0;
					border-radius: 6px;
					background: #fff;
					color: #111827;
					text-align: left;
					cursor: pointer;
					font: inherit;
				}
				.ggm-search-result:hover,
				.ggm-search-result:focus {
					background: #eef2ff;
					outline: 2px solid #c7d2fe;
					outline-offset: 0;
				}
				.ggm-search-empty,
				.ggm-search-select-error {
					display: block;
					min-height: 18px;
					margin-top: 5px;
					color: #b91c1c;
				}
				.ggm-search-values {
					display: none;
				}
				.ggm-form-page {
					display: grid;
					grid-template-columns: repeat(10, minmax(0, 1fr));
					column-gap: 16px;
					align-items: flex-start;
				}
				.ggm-form-page[hidden] {
					display: none !important;
				}
				.ggm-form-page > :not(.ggm-form-field) {
					grid-column: 1 / -1;
				}
				.ggm-form-field {
					box-sizing: border-box;
					grid-column: 1 / -1;
					min-width: 0;
				}
				.ggm-field-width-50 {
					grid-column: span 5;
				}
				.ggm-field-width-30 {
					grid-column: span 3;
				}
				.ggm-field-width-100 {
					grid-column: 1 / -1;
				}
				.ggm-phone-country-control {
					display: grid;
					grid-template-columns: minmax(104px, max-content) minmax(0, 460px);
					gap: 8px;
					max-width: 720px;
				}
				.ggm-phone-country-control select,
				.ggm-phone-country-control input[type="tel"] {
					max-width: none !important;
				}
				.ggm-country-picker {
					position: relative;
					width: max-content;
					max-width: 100%;
					min-width: 0;
				}
				.ggm-country-picker-button,
				.ggm-country-option {
					display: flex !important;
					align-items: center !important;
					gap: 8px !important;
					width: auto !important;
					min-width: 104px !important;
					min-height: 40px !important;
					border: 1px solid #cbd5e1 !important;
					border-radius: 7px !important;
					background: #fff !important;
					color: #1f2937 !important;
					text-align: left !important;
					font: inherit !important;
				}
				.ggm-country-picker-button {
					justify-content: space-between !important;
					padding: 9px 10px !important;
					cursor: pointer;
				}
				.ggm-country-picker-button span {
					flex: 1;
					min-width: 0;
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
				}
				.ggm-country-flag {
					display: block;
					flex: 0 0 auto;
					width: 24px;
					height: 18px;
					overflow: hidden;
					border: 1px solid #d0d7e2;
					border-radius: 2px;
					background: #fff;
				}
				.ggm-country-picker-button::after {
					content: "";
					flex: 0 0 auto;
					margin-top: 5px;
					border: 5px solid transparent;
					border-top-color: #64748b;
				}
				.ggm-country-options {
					position: absolute;
					z-index: 20;
					top: calc(100% + 4px);
					right: 0;
					left: 0;
					display: none;
					flex-direction: column;
					gap: 5px;
					min-width: 100%;
					max-height: 260px;
					overflow: auto;
					margin: 0;
					padding: 5px;
					border: 1px solid #cbd5e1;
					border-radius: 8px;
					background: #fff;
					box-shadow: 0 12px 30px rgba(15,23,42,.16) !important;
				}
				.ggm-country-picker.is-open .ggm-country-options {
					display: flex;
				}
				.ggm-country-option {
					justify-content: flex-start !important;
					margin: 0 !important;
					padding: 8px !important;
					border: 0 !important;
					background: #fff !important;
					cursor: pointer;
				}
				.ggm-country-option:hover,
				.ggm-country-option[aria-selected="true"] {
					background: #eef2ff !important;
				}
				.ggm-post-submit-disclosure {
					margin-top: 14px;
				}
				.ggm-post-submit-disclosure[hidden] {
					display: none !important;
				}
				.ggm-post-submit-fields {
					display: grid;
					grid-template-columns: repeat(10, minmax(0, 1fr));
					column-gap: 16px;
					margin-top: 16px;
					padding-top: 16px;
					border-top: 1px solid #e2e8f0;
				}
				.ggm-post-submit-fields[hidden] {
					display: none !important;
				}
				.ggm-post-submit-fields > h3,
				.ggm-post-submit-actions,
				.ggm-post-submit-feedback {
					grid-column: 1 / -1;
				}
				.ggm-post-submit-fields > .ggm-form-success {
					grid-column: 1 / -1;
					box-sizing: border-box;
					width: 100%;
				}
				.ggm-post-submit-actions {
					display: flex;
					flex-wrap: nowrap;
					gap: 12px;
					align-items: center;
					margin-top: 14px;
				}
				.ggm-post-submit-actions .button {
					flex: 0 1 auto;
					margin: 0 !important;
					white-space: nowrap;
				}
				.ggm-post-submit-feedback {
					min-height: 20px;
					color: #111827 !important;
				}
				.ggm-post-submit-feedback.is-error {
					color: #b91c1c !important;
				}
				.ggm-upload-control {
					display: flex;
					flex-direction: column;
					gap: 9px;
					max-width: 720px;
					padding: 14px;
					border: 1px dashed #9aa4b2;
					border-radius: 9px;
					background: #f8fafc;
				}
				.ggm-file-input,
				.ggm-file-camera-input {
					position: absolute;
					width: 1px;
					height: 1px;
					overflow: hidden;
					opacity: 0;
					pointer-events: none;
				}
				.ggm-upload-dropzone {
					display: grid;
					place-items: center;
					min-height: 92px;
					padding: 18px;
					border: 1px dashed #94a3b8;
					border-radius: 8px;
					background: #fff;
					text-align: center;
					cursor: pointer;
				}
				.ggm-upload-dropzone span {
					font-weight: 700;
					color: #111827;
				}
				.ggm-upload-dropzone small {
					display: block;
					color: #64748b;
				}
				.ggm-upload-control.is-dragging .ggm-upload-dropzone {
					border-color: #4f46e5;
					background: #eef2ff;
				}
				.ggm-upload-actions {
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
					align-items: center;
				}
				.ggm-upload-actions .ggm-upload-browse,
				.ggm-upload-actions .ggm-upload-camera {
					display: inline-flex !important;
					align-items: center !important;
					justify-content: center !important;
					min-height: 40px !important;
					margin: 0 !important;
					padding: 9px 15px !important;
					border: 0 !important;
					border-radius: 999px !important;
					background: #69bd2a !important;
					color: #fff !important;
					font-family: inherit !important;
					font-size: 14px !important;
					font-weight: 700 !important;
					line-height: 1.2 !important;
					text-decoration: none !important;
					cursor: pointer !important;
				}
				.ggm-upload-actions .ggm-upload-browse:hover,
				.ggm-upload-actions .ggm-upload-camera:hover {
					background: #58a91f !important;
					color: #fff !important;
				}
				.ggm-camera-capture {
					display: grid;
					gap: 10px;
					padding: 12px;
					border: 1px solid #cbd5e1;
					border-radius: 10px;
					background: #fff;
				}
				.ggm-camera-capture video {
					width: 100%;
					max-height: 360px;
					border-radius: 8px;
					background: #111;
					object-fit: contain;
				}
				.ggm-camera-actions {
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
				}
				.ggm-camera-status {
					margin: 0;
					color: #64748b;
					font-size: 13px;
				}
				.ggm-upload-list {
					display: flex;
					flex-direction: column;
					gap: 5px;
					margin: 0;
					padding: 0;
					list-style: none;
				}
				.ggm-upload-list li {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 8px;
					padding: 7px 9px;
					border: 1px solid #e2e8f0;
					border-radius: 7px;
					background: #fff;
					color: #111827;
				}
				.ggm-upload-list button {
					display: inline-grid;
					place-items: center;
					width: 20px;
					height: 20px;
					padding: 0;
					border: 0;
					border-radius: 999px;
					background: #e2e8f0;
					color: #111827;
					cursor: pointer;
				}
				.ggm-form-payment-options { min-inline-size: 0; grid-column: 1 / -1; margin: 18px 0; padding: 0; border: 0; }
				.ggm-form-page > .ggm-form-payment-options--width-50 { grid-column: span 5; }
				.ggm-form-page > .ggm-form-payment-options--width-30 { grid-column: span 3; }
				.ggm-form-payment-options legend { margin: 0 0 8px; padding: 0; font-weight: 700; }
				.ggm-form-payment-options__choices { display: flex; flex-direction: column; gap: 10px; }
				.ggm-form-payment-option { display: grid; grid-template-columns: auto minmax(0,1fr) auto; gap: 10px; align-items: center; width: 100%; margin: 0; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; }
				.ggm-form-payment-option--single { cursor: default; }
				.ggm-form-payment-option:has(input:checked) { border-color: #4338ca; background: #eef2ff; }
				.ggm-form-payment-option__label { font-weight: 600; }
				.ggm-form-payment-option__amount { font-weight: 700; white-space: nowrap; }
				/* The checkout reference is an intentionally isolated, per-form theme. */
				.ggm-built-form.ggm-form-theme-health-checkout { box-sizing: border-box; width: 100%; max-width: 855px; height: auto !important; min-height: 0 !important; max-height: none; align-self: flex-start; margin: 0 auto; padding: 23px 32px 25px 37px; container-name: ggm-health-checkout; container-type: inline-size; color: #111827; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
				.ggm-form-theme-health-checkout *,
				.ggm-form-theme-health-checkout *::before,
				.ggm-form-theme-health-checkout *::after { box-sizing: border-box; }
				.ggm-form-theme-health-checkout > .ggm-form-title,
				.ggm-form-theme-health-checkout > p { display: none; }
				.ggm-form-theme-health-checkout .ggm-form-section-title,
				.ggm-form-theme-health-checkout .ggm-form-page > p,
				.ggm-form-theme-health-checkout .ggm-form-field .description { display: none; }
				.ggm-form-theme-health-checkout .ggm-builder-public-form { height: auto !important; min-height: 0 !important; max-height: none; margin: 0; }
				.ggm-form-theme-health-checkout .ggm-form-page { height: auto !important; min-height: 0 !important; max-height: none; grid-template-columns: minmax(0,1.043fr) minmax(0,1fr); column-gap: 30px; }
				.ggm-form-theme-health-checkout .ggm-field-width-50 { grid-column: auto; }
				.ggm-form-theme-health-checkout .ggm-field-width-100 { grid-column: 1 / -1; }
				.ggm-form-theme-health-checkout .ggm-form-field { position: relative; margin: 0 0 14px; }
				.ggm-form-theme-health-checkout .ggm-field-label strong { display: block; margin: 0 0 11px; color: #172033; font-size: 16px; font-weight: 600; line-height: 1.2; }
				.ggm-form-theme-health-checkout .ggm-form-field > input,
				.ggm-form-theme-health-checkout .ggm-form-field > textarea,
				.ggm-form-theme-health-checkout .ggm-form-field > select {     width: 100%;
    min-height: 52px;
    padding: 13px 16px 13px 54px;
    border: 1px solid #dfe3e8 !important;
    border-radius: 9px !important;
    background: #f8fafc !important;
    color: #667085 !important;
    font-size: 16px !important;
    line-height: 24px !important; }
				.ggm-form-theme-health-checkout .ggm-form-field > input:focus,
				.ggm-form-theme-health-checkout .ggm-form-field > textarea:focus,
				.ggm-form-theme-health-checkout .ggm-form-field > select:focus { border-color: #4e9a37; background: #fff; }
				.ggm-form-theme-health-checkout .ggm-field-short_answer::after { content: ""; position: absolute; left: 19px; bottom: 16px; width: 18px; height: 18px; opacity: .62; background: center / contain no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='%23475569' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpath d='M20 21a8 8 0 0 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'/%3E%3C/svg%3E"); pointer-events: none; }
				.ggm-form-theme-health-checkout .ggm-field-short_answer:has(.ggm-phone-country-control)::after { display: none; }
				.ggm-form-theme-health-checkout .ggm-field-short_answer[data-config*="email"]::after { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='%23475569' stroke-width='2' viewBox='0 0 24 24'%3E%3Crect x='3' y='5' width='18' height='14' rx='1'/%3E%3Cpath d='m3 7 9 6 9-6'/%3E%3C/svg%3E"); }
				.ggm-form-theme-health-checkout .ggm-phone-country-control { max-width: none; min-height: 52px; border: 1px solid #dfe3e8; border-radius: 9px; background: #f8fafc; }
				.ggm-form-theme-health-checkout .ggm-phone-country-control .ggm-country-picker-button { min-height: 50px !important; background: transparent !important; }
				.ggm-form-theme-health-checkout .ggm-phone-country-control > input[type="tel"] {    min-height: 50px;
    padding: 13px 16px;
    border: 0 !important;
    border-left: 1px solid #dfe3e8 !important;
    border-radius: 0 9px 9px 0 !important;
    outline: 0;
    background: transparent !important;
    color: #667085;
    font-size: 16px; }
				.ggm-form-theme-health-checkout .ggm-form-payment-options { width: 100%; max-width: 100%; margin: 1px 0 25px; }
				.ggm-form-theme-health-checkout .ggm-form-page > .ggm-form-payment-options { grid-column: 1 / -1; }
				.ggm-form-theme-health-checkout .ggm-form-payment-options legend { float: left; display: flex; width: 100%; flex-wrap: nowrap; align-items: center; justify-content: space-between; gap: 16px; margin: 8px 0 18px; padding: 0; color: #111827; font-size: 19px; font-weight: 800; line-height: 24px; }
				.ggm-form-theme-health-checkout .ggm-form-payment-security { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 8px; margin-left: auto; color: #77808d; font-size: 14px; font-weight: 500; white-space: nowrap; }
				.ggm-form-theme-health-checkout .ggm-form-payment-security::before { width: 15px; height: 17px; background: center / contain no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%2377808d' d='M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v9h14v-9a2 2 0 0 0-2-2Zm-7-2a2 2 0 1 1 4 0v2h-4V7Zm3 8.7V18h-2v-2.3a2 2 0 1 1 2 0Z'/%3E%3C/svg%3E"); content: ""; }
				.ggm-form-theme-health-checkout .ggm-form-payment-options__choices { clear: both; width: 100%; gap: 9px; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option { grid-template-columns: 25px 58px minmax(0,1fr) auto; min-height: 77px; gap: 23px; padding: 8px 26px; border: 1px solid #e0e4e9; border-radius: 11px; background: #fff; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option input { width: 25px; height: 25px; margin: 0; accent-color: #4b9d30; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option:has(input:checked) { border: 2px solid #5d9f4d; background: linear-gradient(90deg, #fbfffa 0%, #f8fcf7 100%); }
				.ggm-form-theme-health-checkout .ggm-form-payment-option__icon { position: relative; display: grid; width: 58px; height: 58px; place-items: center; border-radius: 999px; background: #e9f9e7; color: #3e9d38; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option__icon svg { display: block; width: 31px; height: 31px; fill: currentColor; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option:nth-child(2) .ggm-form-payment-option__icon { background: #fde8ed; color: #d8657a; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option:nth-child(3) .ggm-form-payment-option__icon { background: #fff3d3; color: #ad7900; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option:nth-child(3) .ggm-form-payment-option__icon svg { width: 35px; height: 35px; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option__details { display: flex; min-width: 0; flex-direction: column; gap: 3px; padding-left: 5px; overflow-wrap: anywhere; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option__label { min-width: 0; color: #111827; font-size: 18px; font-weight: 650; line-height: 1.2; text-transform: uppercase; overflow-wrap: anywhere; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option__description { min-width: 0; color: #707988; font-size: 15px; font-weight: 500; line-height: 1.25; overflow-wrap: anywhere; }
				.ggm-form-theme-health-checkout .ggm-form-payment-option__amount { grid-column: 4; grid-row: 1; align-self: center; color: #10172b; font-size: 19px; font-weight: 700; white-space: nowrap; }
				.ggm-form-theme-health-checkout .ggm-form-nav { display: block; margin-top: 0; }
				.ggm-form-theme-health-checkout .ggm-form-pay-btn { display: flex; width: 100%; height: 55px; min-height: 55px; align-items: center; justify-content: center; margin: 0 !important; padding: 0 24px; border: 0; border-radius: 20px; background: linear-gradient(90deg, #4c9d19 0%, #31920f 100%); color: #fff; font-size: 18px; font-weight: 800; line-height: 1; }
				.ggm-form-theme-health-checkout .ggm-form-pay-btn::after { width: 22px; height: 22px; margin-left: 18px; background: center / contain no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='white' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpath d='m5 12h14m-6-6 6 6-6 6'/%3E%3C/svg%3E"); content: ""; }
				.ggm-form-theme-health-checkout .ggm-form-feedback { min-height: 0; margin: 0; overflow-wrap: anywhere; }
				.ggm-form-theme-health-checkout .ggm-form-feedback:empty { display: none; }
				.ggm-form-theme-health-checkout .ggm-form-feedback:not(:empty) { margin-top: 8px; }
				.ggm-form-theme-health-checkout .ggm-form-security-note { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 8px; color: #77808d; font-size: 14px; font-weight: 500; line-height: 20px; }
				.ggm-form-theme-health-checkout .ggm-form-security-note::before { width: 17px; height: 17px; background: center / contain no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%234ca64a' d='m12 2 8 3v6c0 5.1-3.4 9.2-8 11-4.6-1.8-8-5.9-8-11V5l8-3Z'/%3E%3Cpath fill='none' stroke='white' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m8.5 12 2.2 2.2 4.8-5'/%3E%3C/svg%3E"); content: ""; }
				@container ggm-health-checkout (max-width: 700px) {
					.ggm-form-theme-health-checkout .ggm-form-payment-option { grid-template-columns: 25px 58px minmax(0,1fr) auto; min-height: 77px; gap: 16px; padding: 8px 18px; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option__details { padding-left: 0; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option__amount { grid-column: 4; grid-row: 1; }
				}
				@media (max-width: 600px) {
					.ggm-phone-country-control {
						grid-template-columns: minmax(104px, max-content) minmax(0, 1fr);
					}
					.ggm-form-field,
					.ggm-field-width-50,
					.ggm-field-width-30,
					.ggm-field-width-100 {
						grid-column: 1 / -1;
					}
					.ggm-post-submit-actions {
						flex-wrap: wrap;
					}
					.ggm-form-payment-option { grid-column: 1 / -1; grid-template-columns: auto minmax(0,1fr); }
					.ggm-form-payment-option__amount { grid-column: 2; }
					.ggm-built-form.ggm-form-theme-health-checkout { height: auto !important; min-height: 0 !important; max-height: none; padding: 18px; align-self: flex-start; }
					.ggm-form-theme-health-checkout .ggm-builder-public-form,
					.ggm-form-theme-health-checkout .ggm-form-page { height: auto !important; min-height: 0 !important; max-height: none; }
					.ggm-form-theme-health-checkout .ggm-form-page { grid-template-columns: minmax(0,1fr); column-gap: 0; }
					.ggm-form-theme-health-checkout .ggm-form-field,
					.ggm-form-theme-health-checkout .ggm-field-width-50,
					.ggm-form-theme-health-checkout .ggm-field-width-30,
					.ggm-form-theme-health-checkout .ggm-field-width-100 { grid-column: 1 / -1; }
					.ggm-form-theme-health-checkout .ggm-form-payment-options legend { flex-direction: column; align-items: flex-start; gap: 6px; }
					.ggm-form-theme-health-checkout .ggm-form-payment-security { width: 100%; margin-left: 0; white-space: nowrap; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option { grid-template-columns: 25px 48px minmax(0,1fr); grid-template-rows: auto auto; width: 100%; max-width: 100%; height: auto; min-height: 77px; column-gap: 10px; row-gap: 5px; padding: 9px 12px; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option > input { grid-column: 1; grid-row: 1 / span 2; align-self: center; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option__icon { grid-column: 2; grid-row: 1 / span 2; align-self: center; width: 48px; height: 48px; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option__details { grid-column: 3; grid-row: 1; align-self: end; overflow-wrap: anywhere; padding-left: 0; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option__label { font-size: 16px; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option__description { font-size: 14px; }
					.ggm-form-theme-health-checkout .ggm-form-payment-option__amount { grid-column: 3; grid-row: 2; align-self: start; justify-self: start; font-size: 17px; }
				}
				@media (max-width: 767px) {
					/* Applied only to ancestors of this opted-in form by the runtime
					   below. It releases mobile viewport/flex heights without changing
					   desktop layout or unrelated forms and sections. */
					.ggm-health-checkout-auto-height {
						height: auto !important;
						block-size: auto !important;
						min-height: 0 !important;
						min-block-size: 0 !important;
						max-height: none !important;
					}
					.elementor-widget-shortcode.ggm-health-checkout-auto-height,
					.elementor-shortcode.ggm-health-checkout-auto-height {
						width: 100% !important;
						max-width: 100% !important;
						height: fit-content !important;
						block-size: fit-content !important;
						align-self: auto !important;
						flex-grow: 0 !important;
						flex-basis: auto !important;
					}
					#ggm-dash.ggm-health-checkout-auto-height {
						align-items: flex-start;
					}
					#ggm-dash .ggm-dash-main-container.ggm-health-checkout-auto-height {
						flex: 0 0 auto !important;
						overflow: visible;
					}
					#ggm-dash .ggm-main.ggm-health-checkout-auto-height {
						flex: 0 0 auto;
						padding-bottom: calc(75px + env(safe-area-inset-bottom));
					}
				}
			</style>
		<?php endif; ?>
		<?php $form_heading_tag = self::allowed_value( $settings['form_heading_tag'] ?? 'h2', array( 'h1','h2','h3','h4','h5','h6','p' ), 'h2' ); $form_heading_size = self::heading_size( $settings['form_heading_size'] ?? '' ); ?>
		<div class="ggm-built-form<?php echo $health_checkout_theme ? ' ggm-form-theme-health-checkout' : ''; ?>" data-pages="<?php echo esc_attr( count( $sections ) ); ?>" data-progress="<?php echo esc_attr( $settings['progress'] ?? 'bar' ); ?>" data-dashboard-auto-popup="<?php echo $dashboard_auto_popup ? '1' : '0'; ?>">
			<<?php echo esc_attr( $form_heading_tag ); ?> class="ggm-form-title"<?php echo $form_heading_size ? ' style="font-size:' . esc_attr( $form_heading_size ) . 'px"' : ''; ?>><?php echo esc_html( $form->title ); ?></<?php echo esc_attr( $form_heading_tag ); ?>>
			<?php if ( ! empty( $settings['description'] ) ) : ?><p><?php echo esc_html( $settings['description'] ); ?></p><?php endif; ?>
			<form class="ggm-builder-public-form" enctype="multipart/form-data">
				<input type="hidden" name="action" value="ggm_submit_builder_form">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'ggm_submit_form_' . $form->id . '_' . $form->current_version ) ); ?>">
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $form->id ); ?>">
				<input type="hidden" name="form_version" value="<?php echo esc_attr( $form->current_version ); ?>">
				<input type="hidden" name="context_type" value="<?php echo esc_attr( $context ); ?>">
				<input type="hidden" name="context_id" value="<?php echo esc_attr( $context_id ); ?>">
				<?php if ( $editing_submission ) : ?><input type="hidden" name="edit_submission_id" value="<?php echo esc_attr( (int) $submitted->id ); ?>"><?php endif; ?>
				<?php foreach ( $sections as $index => $section ) : ?>
					<?php $duplicate_first_heading = 1 === count( $sections ) && 0 === (int) $index && ! empty( $section['title'] ) && self::headings_are_equivalent( $form->title, $section['title'] ); ?>
					<?php if ( $duplicate_first_heading ) : ?><div class="ggm-form-page" data-page="<?php echo esc_attr( $index ); ?>" <?php echo $index ? 'hidden' : ''; ?>><?php else : ?><section class="ggm-form-page" data-page="<?php echo esc_attr( $index ); ?>" <?php echo $index ? 'hidden' : ''; ?>><?php endif; ?>
						<?php if ( ! empty( $section['title'] ) && ! $duplicate_first_heading ) : ?><?php $section_heading_tag = self::allowed_value( $section['heading_tag'] ?? 'h3', array( 'h1','h2','h3','h4','h5','h6','p' ), 'h3' ); $section_heading_size = self::heading_size( $section['heading_size'] ?? '' ); ?><<?php echo esc_attr( $section_heading_tag ); ?> class="ggm-form-section-title"<?php echo $section_heading_size ? ' style="font-size:' . esc_attr( $section_heading_size ) . 'px"' : ''; ?>><?php echo esc_html( $section['title'] ); ?></<?php echo esc_attr( $section_heading_tag ); ?>><?php endif; ?>
						<?php if ( ! empty( $section['description'] ) ) : ?><p><?php echo esc_html( $section['description'] ); ?></p><?php endif; ?>
						<?php foreach ( (array) $section['fields'] as $field ) { self::render_field( $field, 'form-' . $form->id . '-' . $context . '-' . $context_id . '-', $user_id ); } ?>
						<?php if ( $payment_enabled && $index + 1 === count( $sections ) ) : ?>
							<fieldset class="ggm-form-payment-options ggm-form-payment-options--width-<?php echo esc_attr( $payment_options_width ); ?>">
								<?php if ( 1 === count( $payment_options ) ) : ?>
									<?php $option = $payment_options[0]; ?>
									<input type="hidden" name="payment_option_id" value="<?php echo esc_attr( $option['id'] ); ?>">
									<div class="ggm-form-payment-options__choices"><div class="ggm-form-payment-option ggm-form-payment-option--single"><span class="ggm-form-payment-option__label"><?php echo esc_html( $option['label'] ); ?></span><span class="ggm-form-payment-option__amount"><?php echo esc_html( $health_checkout_theme ? self::format_health_checkout_amount( $option['amount'], $settings['payment_currency'] ?? ggm_get_setting( 'ggm_currency', 'INR' ) ) : self::format_payment_option_amount( $option['amount'], $settings['payment_currency'] ?? ggm_get_setting( 'ggm_currency', 'INR' ) ) ); ?></span></div></div>
								<?php else : ?>
									<legend><?php echo $health_checkout_theme ? esc_html__( 'Choose a plan', 'ggm-member-dashboard' ) : esc_html__( 'Choose a price', 'ggm-member-dashboard' ); ?><?php if ( $health_checkout_theme ) : ?><span class="ggm-form-payment-security"><?php esc_html_e( 'Secure & encrypted payment', 'ggm-member-dashboard' ); ?></span><?php endif; ?></legend>
									<div class="ggm-form-payment-options__choices">
										<?php foreach ( $payment_options as $option_index => $option ) : ?>
											<?php
											$option_input_id = 'ggm-form-payment-' . $form->id . '-' . $context . '-' . $context_id . '-' . $option['id'];
											$option_description = (string) ( $option['description'] ?? '' );
											// Form 3 is the supplied three-tier health checkout. These are visual
											// defaults only; a saved Plan subtitle always takes precedence.
											if ( ! $option_description && $health_checkout_theme && 3 === (int) $form->id ) {
												$reference_subtitles = array( 'Basic health guidance', 'Chronic health concerns', 'Everything in ₹1000 plan + more' );
												$option_description = $reference_subtitles[ $option_index ] ?? '';
											}
											?>
											<label class="ggm-form-payment-option" for="<?php echo esc_attr( $option_input_id ); ?>">
											<input id="<?php echo esc_attr( $option_input_id ); ?>" type="radio" name="payment_option_id" value="<?php echo esc_attr( $option['id'] ); ?>"<?php echo 0 === $option_index ? ' required' : ''; ?><?php echo $health_checkout_theme && 0 === $option_index ? ' checked' : ''; ?>>
											<?php if ( $health_checkout_theme ) : ?>
												<span class="ggm-form-payment-option__icon" aria-hidden="true">
													<?php if ( 0 === $option_index ) : ?>
														<svg aria-hidden="true" class="e-font-icon-svg e-fas-leaf" viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg"><path d="M546.2 9.7c-5.6-12.5-21.6-13-28.3-1.2C486.9 62.4 431.4 96 368 96h-80C182 96 96 182 96 288c0 7 .8 13.7 1.5 20.5C161.3 262.8 253.4 224 384 224c8.8 0 16 7.2 16 16s-7.2 16-16 16C132.6 256 26 410.1 2.4 468c-6.6 16.3 1.2 34.9 17.5 41.6 16.4 6.8 35-1.1 41.8-17.3 1.5-3.6 20.9-47.9 71.9-90.6 32.4 43.9 94 85.8 174.9 77.2C465.5 467.5 576 326.7 576 154.3c0-50.2-10.8-102.2-29.8-144.6z"></path></svg>
													<?php elseif ( 1 === $option_index ) : ?>
														<svg aria-hidden="true" class="e-font-icon-svg e-fas-brain" viewBox="0 0 576 512" xmlns="http://www.w3.org/2000/svg"><path d="M208 0c-29.9 0-54.7 20.5-61.8 48.2-.8 0-1.4-.2-2.2-.2-35.3 0-64 28.7-64 64 0 4.8.6 9.5 1.7 14C52.5 138 32 166.6 32 200c0 12.6 3.2 24.3 8.3 34.9C16.3 248.7 0 274.3 0 304c0 33.3 20.4 61.9 49.4 73.9-.9 4.6-1.4 9.3-1.4 14.1 0 39.8 32.2 72 72 72 4.1 0 8.1-.5 12-1.2 9.6 28.5 36.2 49.2 68 49.2 39.8 0 72-32.2 72-72V64c0-35.3-28.7-64-64-64zm368 304c0-29.7-16.3-55.3-40.3-69.1 5.2-10.6 8.3-22.3 8.3-34.9 0-33.4-20.5-62-49.7-74 1-4.5 1.7-9.2 1.7-14 0-35.3-28.7-64-64-64-.8 0-1.5.2-2.2.2C422.7 20.5 397.9 0 368 0c-35.3 0-64 28.6-64 64v376c0 39.8 32.2 72 72 72 31.8 0 58.4-20.7 68-49.2 3.9.7 7.9 1.2 12 1.2 39.8 0 72-32.2 72-72 0-4.8-.5-9.5-1.4-14.1 29-12 49.4-40.6 49.4-73.9z"></path></svg>
													<?php else : ?>
														<svg aria-hidden="true" class="e-font-icon-svg e-fas-crown" viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg"><path d="M528 448H112c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h416c8.8 0 16-7.2 16-16v-32c0-8.8-7.2-16-16-16zm64-320c-26.5 0-48 21.5-48 48 0 7.1 1.6 13.7 4.4 19.8L476 239.2c-15.4 9.2-35.3 4-44.2-11.6L350.3 85C361 76.2 368 63 368 48c0-26.5-21.5-48-48-48s-48 21.5-48 48c0 15 7 28.2 17.7 37l-81.5 142.6c-8.9 15.6-28.9 20.8-44.2 11.6l-72.3-43.4c2.7-6 4.4-12.7 4.4-19.8 0-26.5-21.5-48-48-48S0 149.5 0 176s21.5 48 48 48c2.6 0 5.2-.4 7.7-.8L128 416h384l72.3-192.8c2.5.4 5.1.8 7.7.8 26.5 0 48-21.5 48-48s-21.5-48-48-48z"></path></svg>
													<?php endif; ?>
												</span>
											<?php endif; ?>
											<span class="ggm-form-payment-option__details"><span class="ggm-form-payment-option__label"><?php echo esc_html( $option['label'] ); ?></span><?php if ( $health_checkout_theme && $option_description ) : ?><span class="ggm-form-payment-option__description"><?php echo esc_html( $option_description ); ?></span><?php endif; ?></span>
											<span class="ggm-form-payment-option__amount"><?php echo esc_html( $health_checkout_theme ? self::format_health_checkout_amount( $option['amount'], $settings['payment_currency'] ?? ggm_get_setting( 'ggm_currency', 'INR' ) ) : self::format_payment_option_amount( $option['amount'], $settings['payment_currency'] ?? ggm_get_setting( 'ggm_currency', 'INR' ) ) ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</fieldset>
						<?php endif; ?>
						<div class="ggm-form-nav">
							<?php if ( $index ) : ?><button type="button" class="button ggm-form-back"><?php echo esc_html( $settings['back_label'] ?? __( 'Back', 'ggm-member-dashboard' ) ); ?></button><?php endif; ?>
							<?php if ( $index + 1 < count( $sections ) ) : ?><button type="button" class="button button-primary ggm-form-next"><?php echo esc_html( $settings['next_label'] ?? __( 'Next', 'ggm-member-dashboard' ) ); ?></button>
							<?php else : ?><?php if ( $payment_enabled ) : ?><button type="button" class="button button-primary ggm-form-pay-btn" data-payment-label="<?php echo esc_attr( $settings['payment_label'] ?? __( 'Pay & Submit', 'ggm-member-dashboard' ) ); ?>"><?php echo esc_html( $settings['payment_label'] ?? __( 'Pay & Submit', 'ggm-member-dashboard' ) ); ?></button><?php else : ?><button type="submit" class="button button-primary"><?php echo esc_html( $settings['submit_label'] ?? __( 'Submit', 'ggm-member-dashboard' ) ); ?></button><?php endif; ?><?php endif; ?>
						</div>
					<?php if ( $duplicate_first_heading ) : ?></div><?php else : ?></section><?php endif; ?>
				<?php endforeach; ?>
				<?php if ( $has_multiple_pages ) : ?><div class="ggm-form-progress" aria-live="polite"></div><?php endif; ?><div class="ggm-form-feedback" aria-live="polite"></div>
				<?php if ( $health_checkout_theme ) : ?><div class="ggm-form-security-note"><?php esc_html_e( 'Your information is safe with us', 'ggm-member-dashboard' ); ?></div><?php endif; ?>
			</form>
			<?php echo self::post_submit_fields_html( $post_submit_fields, 'post-submit-' . $form->id . '-' . $context . '-' . $context_id . '-', $user_id, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fields are rendered with escaping. ?>
		</div>
		<script>
		(function(){var root=document.currentScript.previousElementSibling,form=root.querySelector('form'),pages=[].slice.call(form.querySelectorAll('.ggm-form-page')),current=0,feedback=form.querySelector('.ggm-form-feedback'),progress=form.querySelector('.ggm-form-progress');
		function releaseMobileHostHeight(){if(!root.classList.contains('ggm-form-theme-health-checkout'))return;var selector='#ggm-dash,.ggm-dash-main-container,.ggm-main,.ggm-tab,.elementor-widget-shortcode,.elementor-widget-container,.elementor-shortcode,.e-con,.e-con-inner,.elementor-section,.elementor-container,.elementor-column,.elementor-widget-wrap',tab=root.closest('.ggm-tab'),enabled=!tab||tab.classList.contains('active'),node=root;while(node&&node!==document.body){if(node===root||(node.matches&&node.matches(selector)))node.classList.toggle('ggm-health-checkout-auto-height',enabled);node=node.parentElement;}}
		releaseMobileHostHeight();
		var formDashboardTab=root.closest('.ggm-tab');if(formDashboardTab&&window.MutationObserver)new MutationObserver(releaseMobileHostHeight).observe(formDashboardTab,{attributes:true,attributeFilter:['class']});
		function updateProgress(){if(!progress)return;var mode=root.dataset.progress;if(mode==='none'){progress.textContent='';progress.style.background='';return}if(mode==='bar'){progress.innerHTML='<span style="display:block;height:6px;background:#2271b1;width:'+(((current+1)/pages.length)*100)+'%"></span>';progress.style.background='#e5e7eb'}else{progress.textContent='Page '+(current+1)+' of '+pages.length;progress.style.background=''}}
		function show(n){pages[current].hidden=true;current=n;pages[current].hidden=false;updateProgress();pages[current].scrollIntoView({behavior:'smooth',block:'start'});}
		function customValid(){var ok=true;pages[current].querySelectorAll('.ggm-form-field').forEach(function(box){var cfg={};try{cfg=JSON.parse(box.dataset.config||'{}')}catch(e){}var inputs=[].slice.call(box.querySelectorAll('input,textarea,select')),anchor=inputs[0],phoneInput=box.querySelector('.ggm-phone-country-control input[type=tel]');if(phoneInput)anchor=phoneInput;if(!anchor)return;inputs.forEach(function(i){i.setCustomValidity('')});var message=cfg.validation_message||'Please check this response.';if(cfg.type==='checkboxes'){var checked=box.querySelectorAll('input[type=checkbox]:checked'),count=checked.length,target=Number(cfg.selection_count||0);if(cfg.required&&!count)anchor.setCustomValidity(message);if(target&&((cfg.selection_rule==='at_least'&&count<target)||(cfg.selection_rule==='at_most'&&count>target)||(cfg.selection_rule==='exactly'&&count!==target)))anchor.setCustomValidity(message)}var other=box.querySelector('input[value=\"__other__\"]:checked');if(other){var otherInput=box.querySelector('.ggm-other-input');if(otherInput&&!otherInput.value.trim())otherInput.setCustomValidity(message)}if((cfg.type==='short_answer'||cfg.type==='paragraph')&&anchor.value){if(cfg.regex_pattern&&!cfg.phone_country_enabled){try{if(!(new RegExp(cfg.regex_pattern)).test(anchor.value))anchor.setCustomValidity(message)}catch(e){anchor.setCustomValidity(message)}}if(cfg.validation_type==='number'&&cfg.phone_country_enabled){if(anchor.value.replace(/\D/g,'').length<7)anchor.setCustomValidity(message)}else if(cfg.validation_type==='number'&&cfg.number_rule&&cfg.number_rule!=='none'){var n=Number(anchor.value),a=Number(cfg.number_value),b=Number(cfg.number_value_to),pass=true;if(cfg.number_rule==='greater_than')pass=n>a;else if(cfg.number_rule==='greater_or_equal')pass=n>=a;else if(cfg.number_rule==='less_than')pass=n<a;else if(cfg.number_rule==='less_or_equal')pass=n<=a;else if(cfg.number_rule==='equal')pass=n===a;else if(cfg.number_rule==='not_equal')pass=n!==a;else if(cfg.number_rule==='between')pass=n>=Math.min(a,b)&&n<=Math.max(a,b);if(!pass)anchor.setCustomValidity(message)}}if(cfg.type==='file_upload'){var upload=box.querySelector('.ggm-upload-control');if(upload&&upload._validate&&!upload._validate())ok=false;if(anchor.files){if(anchor.files.length>Number(cfg.max_files||1))anchor.setCustomValidity(message);for(var f=0;f<anchor.files.length;f++){if(anchor.files[f].size>Number(cfg.max_size||10)*1024*1024)anchor.setCustomValidity(message)}}}if(cfg.type==='checkbox_grid'&&cfg.require_each_row){box.querySelectorAll('tbody tr').forEach(function(row){if(!row.querySelector('input:checked')){(row.querySelector('input')||anchor).setCustomValidity(message)}})}if(cfg.type==='multiple_choice_grid'&&cfg.limit_one_per_column){var used={};box.querySelectorAll('tbody input:checked').forEach(function(i){if(used[i.value])i.setCustomValidity(message);used[i.value]=true})}if(cfg.type==='time'&&cfg.time_type==='duration'&&cfg.required){var total=0;inputs.forEach(function(i){total+=Number(i.value||0)});if(!total)anchor.setCustomValidity(message)}if(cfg.type==='chip_selector'){var chips=box.querySelector('.ggm-chip-selector');if(chips&&chips._validate&&!chips._validate())ok=false}if(cfg.type==='search_select'){var search=box.querySelector('.ggm-search-select');if(search&&search._validate&&!search._validate())ok=false}inputs.forEach(function(i){if(!i.checkValidity())ok=false})});return ok}
		function valid(){var fields=pages[current].querySelectorAll('input,textarea,select');if(!customValid()){for(var c=0;c<fields.length;c++){if(!fields[c].checkValidity()){fields[c].reportValidity();return false}}}for(var i=0;i<fields.length;i++){if(!fields[i].checkValidity()){fields[i].reportValidity();return false;}}return true;}
		form.addEventListener('click',function(e){var date=e.target.closest&&e.target.closest('.ggm-date-picker');if(date&&date.showPicker){try{date.showPicker()}catch(err){}}if(e.target.classList.contains('ggm-form-next')){if(valid())show(current+1);}if(e.target.classList.contains('ggm-form-back'))show(current-1);if(e.target.classList.contains('ggm-form-pay-btn')){payAndSubmit(e.target);}if(e.target.classList.contains('ggm-post-submit-open')){showPostSubmit(e.target)}if(e.target.classList.contains('ggm-post-submit-save')){savePostSubmit(e.target)}});
		updateProgress();
		function setBusy(button,busy,text){button.disabled=busy;if(text)feedback.textContent=text;[].slice.call(form.querySelectorAll('button')).forEach(function(btn){if(btn!==button)btn.disabled=busy;});}
		function showSuccess(data,fallback){var success=document.createElement('div');success.className='ggm-form-success';success.setAttribute('role','status');success.setAttribute('tabindex','-1');if(data&&data.message_html){success.innerHTML=data.message_html;}else{success.textContent=(data&&data.message)||fallback||'Thank you. Your response has been submitted.';}var postSubmit=root.querySelector(':scope > .ggm-post-submit-disclosure');root.dataset.submissionId=(data&&data.submission_id)||'';root.dataset.afterSubmitToken=(data&&data.after_submit_token)||'';root.querySelectorAll(':scope > p').forEach(function(p){p.remove();});form.innerHTML='';form.appendChild(success);if(postSubmit&&data&&data.has_after_submit_fields){postSubmit.hidden=false;form.appendChild(postSubmit);}if(data&&data.whatsapp_url){var whatsappLink=document.createElement('a');whatsappLink.className='ggm-btn ggm-btn-primary ggm-form-whatsapp-btn';whatsappLink.href=data.whatsapp_url;whatsappLink.target='_blank';whatsappLink.rel='noopener noreferrer';whatsappLink.textContent=<?php echo wp_json_encode( __( 'Join WhatsApp Group', 'ggm-member-dashboard' ) ); ?>;form.appendChild(whatsappLink);}root.dataset.dashboardAutoPopup='0';success.focus();root.dispatchEvent(new CustomEvent('ggmFormSubmitted',{bubbles:true}));}
		function showPostSubmit(button){var disclosure=button.closest('.ggm-post-submit-disclosure'),box=disclosure&&disclosure.querySelector('.ggm-post-submit-fields');if(!box)return;box.hidden=false;button.setAttribute('aria-expanded','true');if(window.ggmInitFormFileUploads)window.ggmInitFormFileUploads(box);box.dispatchEvent(new CustomEvent('ggmFormAdditionalShown',{bubbles:true}));}
		function postSubmitFeedback(box,message,isError){var fb=box.querySelector('.ggm-post-submit-feedback');if(!fb)return;fb.textContent=message||'';fb.classList.toggle('is-error',!!isError)}
		function validatePostSubmit(box){var ok=true;box.querySelectorAll('input,textarea,select').forEach(function(i){i.setCustomValidity&&i.setCustomValidity('')});box.querySelectorAll('.ggm-upload-control').forEach(function(upload){if(upload._validate&&!upload._validate())ok=false});box.querySelectorAll('.ggm-chip-selector,.ggm-search-select').forEach(function(widget){if(widget._validate&&!widget._validate())ok=false});box.querySelectorAll('input,textarea,select').forEach(function(i){if(!i.checkValidity())ok=false});if(!ok){var first=[].slice.call(box.querySelectorAll('input,textarea,select')).find(function(i){return !i.checkValidity()});if(first&&first.reportValidity)first.reportValidity()}return ok}
		function finishPostSubmit(box,message){var disclosure=box.closest('.ggm-post-submit-disclosure'),openButton=disclosure&&disclosure.querySelector('.ggm-post-submit-open');if(openButton)openButton.remove();box.innerHTML='<div class="ggm-form-success" role="status">'+(message||'Additional details saved.')+'</div>'}
		function savePostSubmit(button){var box=button.closest('.ggm-post-submit-fields');if(!box||!validatePostSubmit(box))return;button.disabled=true;postSubmitFeedback(box,'Saving additional details...',false);var data=new FormData(form);data.set('action','ggm_update_builder_form_after_submit');data.set('submission_id',root.dataset.submissionId||'');data.set('after_submit_token',root.dataset.afterSubmitToken||'');fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',credentials:'same-origin',body:data}).then(function(r){return r.json();}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Could not save additional details.');finishPostSubmit(box,(r.data&&r.data.message)||'Additional details saved.');if(r.data&&r.data.files_html){var host=document.querySelector('.ggm-health-uploaded-files-host'),intro=document.querySelector('.ggm-health-uploaded-intro');if(host)host.innerHTML=r.data.files_html;if(intro)intro.hidden=true;}}).catch(function(err){button.disabled=false;postSubmitFeedback(box,err.message,true);})}
		function loadRazorpay(){if(typeof window.Razorpay==='function')return Promise.resolve();if(window.ggmRazorpayLoading)return window.ggmRazorpayLoading;window.ggmRazorpayLoading=new Promise(function(resolve,reject){var s=document.createElement('script');s.src='https://checkout.razorpay.com/v1/checkout.js';s.async=true;s.onload=function(){if(typeof window.Razorpay==='function')resolve();else reject(new Error('Razorpay checkout did not initialize. Please try again.'));};s.onerror=function(){reject(new Error('Could not load Razorpay. Please try again.'));};document.head.appendChild(s);});return window.ggmRazorpayLoading;}
		function postData(data){return fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',credentials:'same-origin',cache:'no-store',body:data}).then(function(response){return response.text().then(function(text){var payload;try{payload=JSON.parse(text);}catch(error){throw new Error(response.ok?'The payment server returned an invalid response. Please refresh and try again.':'The payment request failed (HTTP '+response.status+'). Please try again.');}return payload;});});}
		function postForm(action){var data=new FormData(form);data.set('action',action);return postData(data);}
		function refreshPaymentNonce(){var data=new FormData(),formId=form.querySelector('[name="form_id"]'),version=form.querySelector('[name="form_version"]'),nonce=form.querySelector('[name="nonce"]'),context=form.querySelector('[name="context_type"]'),contextId=form.querySelector('[name="context_id"]');data.set('action','ggm_refresh_form_payment_nonce');data.set('form_id',formId?formId.value:'');data.set('form_version',version?version.value:'');data.set('context_type',context?context.value:'shortcode');data.set('context_id',contextId?contextId.value:'0');return postData(data).then(function(result){if(!result.success||!result.data)throw new Error((result.data&&result.data.message)||'Could not refresh the payment session. Please reload and try again.');if(version)version.value=result.data.form_version;if(nonce)nonce.value=result.data.nonce;return result.data;});}
		function payAndSubmit(button){if(!valid())return;setBusy(button,true,'Creating payment order…');refreshPaymentNonce().then(function(){return postForm('ggm_create_form_payment');}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Payment order failed.');if(!r.data||!r.data.key_id||!r.data.order_id||!r.data.form_payment_id)throw new Error('The payment order is incomplete. Please try again.');return loadRazorpay().then(function(){return r.data;});}).then(function(order){var rzp=new Razorpay({key:order.key_id,amount:order.amount,currency:order.currency,name:order.name||<?php echo wp_json_encode( get_bloginfo( 'name' ) ); ?>,description:order.description||button.dataset.paymentLabel||'Form payment',order_id:order.order_id,handler:function(response){feedback.textContent='Verifying payment…';var data=new FormData();data.set('action','ggm_verify_form_payment');data.set('form_payment_id',order.form_payment_id);data.set('guest_payment_token',order.guest_payment_token||'');data.set('razorpay_order_id',response.razorpay_order_id);data.set('razorpay_payment_id',response.razorpay_payment_id);data.set('razorpay_signature',response.razorpay_signature);postData(data).then(function(v){if(!v.success)throw new Error((v.data&&v.data.message)||'Payment verification failed.');if(v.data&&v.data.payment_first_reload){window.location.reload();return;}showSuccess(v.data,'Payment received. Your response has been submitted.');}).catch(function(err){feedback.textContent=err.message;setBusy(button,false);});},modal:{ondismiss:function(){feedback.textContent='Payment was cancelled. You can try again.';setBusy(button,false);}}});rzp.on('payment.failed',function(response){feedback.textContent=(response.error&&response.error.description)||'Payment failed. Please try again.';setBusy(button,false);});rzp.open();}).catch(function(err){feedback.textContent=err.message;setBusy(button,false);});}
		form.addEventListener('submit',function(e){e.preventDefault();if(!valid())return;var button=form.querySelector('[type=submit]');setBusy(button,true,'Submitting…');postForm('ggm_submit_builder_form').then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Submission failed.');showSuccess(r.data,'Thank you. Your response has been submitted.');}).catch(function(err){feedback.textContent=err.message;setBusy(button,false);});});
		})();
		</script>
		<script><?php echo self::country_picker_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal script. ?></script>
		<script><?php echo self::chip_selector_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal script. ?></script>
		<script><?php echo self::search_select_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal script. ?></script>
		<script><?php echo self::file_upload_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal script. ?></script>
		<?php
		return ob_get_clean();
	}

	private static function whatsapp_button_html( array $settings ) {
		$url = $settings['whatsapp_group_url'] ?? '';
		if ( ! $url ) { return ''; }
		return '<a class="ggm-btn ggm-btn-primary ggm-form-whatsapp-btn" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Join WhatsApp Group', 'ggm-member-dashboard' ) . '</a>';
	}

	private static function country_picker_script() {
		return <<<'JS'
(function(){
	function closeAll(except){
		document.querySelectorAll('.ggm-country-picker.is-open').forEach(function(picker){
			if(picker!==except){
				picker.classList.remove('is-open');
				var toggle=picker.querySelector('.ggm-country-picker-button');
				if(toggle)toggle.setAttribute('aria-expanded','false');
			}
		});
	}
	document.querySelectorAll('.ggm-country-picker:not([data-country-ready])').forEach(function(picker){
		picker.dataset.countryReady='1';
		var hidden=picker.querySelector('input[type=hidden]');
		var toggle=picker.querySelector('.ggm-country-picker-button');
		var label=toggle?toggle.querySelector('span'):null;
		var flag=toggle?toggle.querySelector('.ggm-country-flag'):null;
		var search=picker.querySelector('.ggm-country-search');
		var options=[].slice.call(picker.querySelectorAll('.ggm-country-option'));
		if(!hidden||!toggle||!label||!flag)return;
		function choose(option){
			hidden.value=option.dataset.code||option.dataset.value||hidden.value;
			label.textContent=option.dataset.code||option.dataset.label||label.textContent;
			var selectedFlag=option.querySelector('.ggm-country-flag');if(selectedFlag){var replacement=selectedFlag.cloneNode(true);flag.replaceWith(replacement);flag=replacement;}
			options.forEach(function(item){item.setAttribute('aria-selected',item===option?'true':'false')});
			picker.classList.remove('is-open');
			toggle.setAttribute('aria-expanded','false');
			if(search){search.value='';options.forEach(function(item){item.hidden=false})}
			toggle.focus();
		}
		toggle.addEventListener('click',function(event){
			event.preventDefault();
			var open=!picker.classList.contains('is-open');
			closeAll(picker);
			picker.classList.toggle('is-open',open);
			toggle.setAttribute('aria-expanded',open?'true':'false');
			if(open&&search){search.value='';options.forEach(function(item){item.hidden=false});setTimeout(function(){search.focus()},0)}
		});
		if(search){
			search.addEventListener('input',function(){
				var query=search.value.trim().toLowerCase();
				options.forEach(function(option){option.hidden=!!query&&(option.dataset.search||'').indexOf(query)===-1});
			});
			search.addEventListener('click',function(event){event.stopPropagation()});
		}
		options.forEach(function(option){
			option.addEventListener('click',function(){choose(option)});
		});
		picker.addEventListener('keydown',function(event){
			if(event.key==='Escape'){
				picker.classList.remove('is-open');
				toggle.setAttribute('aria-expanded','false');
				toggle.focus();
			}
		});
	});
	document.addEventListener('click',function(event){
		if(!event.target.closest('.ggm-country-picker'))closeAll();
	});
})();
JS;
	}

	/**
	 * Reuse contact details already on file — from checkout, the member's
	 * profile, or a previous form-builder submission — so returning members
	 * do not have to retype their name, WhatsApp number, or email in a new
	 * form. Only called from render_form(), which already requires a
	 * logged-in member, so — unlike the guest-facing checkout page — there
	 * is no anonymous page-cache path that could leak one member's details
	 * to another.
	 */
	private static function prefill_value( $validation_type, $label, $user_id ) {
		$contact = self::known_contact( $user_id );
		if ( 'email' === $validation_type ) { return $contact['email']; }
		if ( 'phone' === $validation_type ) { return $contact['phone']; }
		if ( preg_match( '/\bname\b/i', (string) $label ) ) { return $contact['name']; }
		return '';
	}

	private static function known_contact( $user_id ) {
		if ( isset( self::$contact_cache[ $user_id ] ) ) { return self::$contact_cache[ $user_id ]; }

		$user = get_userdata( $user_id );
		$email = get_user_meta( $user_id, 'ggm_checkout_email', true ) ?: get_user_meta( $user_id, 'billing_email', true );
		if ( ! $email && $user && false === strpos( $user->user_email, '@guest.invalid' ) ) {
			$email = $user->user_email;
		}
		$name = '';
		if ( $user ) {
			$name = trim( $user->first_name . ' ' . $user->last_name );
			if ( ! $name && false === strpos( $user->display_name, '@' ) ) { $name = $user->display_name; }
		}
		$contact = array(
			'email' => is_email( $email ) ? $email : '',
			'phone' => (string) ( get_user_meta( $user_id, 'ggm_phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true ) ),
			'name'  => $name,
		);

		if ( ! $contact['email'] || ! $contact['phone'] || ! $contact['name'] ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT answers_json, schema_snapshot_json FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE user_id=%d AND status='submitted' ORDER BY submitted_at DESC LIMIT 20",
				$user_id
			) );
			foreach ( (array) $rows as $row ) {
				if ( $contact['email'] && $contact['phone'] && $contact['name'] ) { break; }
				$answers = json_decode( (string) $row->answers_json, true );
				$schema  = json_decode( (string) $row->schema_snapshot_json, true );
				if ( ! is_array( $answers ) || ! is_array( $schema ) ) { continue; }
				foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) {
					foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
						if ( 'short_answer' !== ( $field['type'] ?? '' ) ) { continue; }
						$value = $answers[ $field['key'] ?? '' ] ?? '';
						if ( ! is_string( $value ) || '' === $value ) { continue; }
						$field_validation = $field['validation_type'] ?? 'none';
						if ( ! $contact['email'] && 'email' === $field_validation && is_email( $value ) ) {
							$contact['email'] = $value;
						} elseif ( ! $contact['phone'] && ( 'phone' === $field_validation || ( 'number' === $field_validation && ! empty( $field['phone_country_enabled'] ) ) ) ) {
							$contact['phone'] = $value;
						} elseif ( ! $contact['name'] && preg_match( '/\bname\b/i', (string) ( $field['label'] ?? '' ) ) ) {
							$contact['name'] = $value;
						}
					}
				}
			}
		}

		self::$contact_cache[ $user_id ] = $contact;
		return $contact;
	}

	private static function chip_selector_script() {
		return <<<'JS'
(function(){
	document.querySelectorAll('.ggm-chip-selector:not([data-chip-ready])').forEach(function(root){
		root.dataset.chipReady='1';
		var valuesBox=root.querySelector('.ggm-chip-values'),error=root.querySelector('.ggm-chip-error');
		var optionButtons=[].slice.call(root.querySelectorAll('.ggm-chip-option:not(.ggm-chip-option-other)'));
		var otherButton=root.querySelector('.ggm-chip-option-other');
		var otherInput=root.querySelector('.ggm-chip-other-input');
		var selected=[],mode=root.dataset.mode||'multiple',minimum=Number(root.dataset.min||0),maximum=Number(root.dataset.max||0);
		var field=root.closest('.ggm-form-field'),cfg={};try{cfg=JSON.parse(field&&field.dataset.config||'{}')}catch(e){}
		if(cfg.required)minimum=Math.max(1,minimum);if(mode==='single')maximum=1;
		function has(value){return selected.indexOf(value)!==-1}
		function message(validate){
			var text='';
			if(validate&&selected.length<minimum)text=minimum>1?'Please select at least '+minimum+' options.':'Please select at least one option.';
			else if(maximum&&selected.length>maximum)text='You can select up to '+maximum+' options.';
			else if(validate&&otherInput&&has('__other__')&&!otherInput.value.trim())text='Please specify your answer for "Other".';
			error.textContent=text;
			if(otherInput)otherInput.setCustomValidity(text&&has('__other__')?text:'');
			return !text;
		}
		function draw(){
			valuesBox.innerHTML='';
			selected.forEach(function(value){
				var hidden=document.createElement('input');hidden.type='hidden';hidden.name=root.dataset.name;hidden.value=value;valuesBox.appendChild(hidden);
			});
			optionButtons.forEach(function(button){var active=has(button.dataset.value);button.classList.toggle('is-selected',active);button.setAttribute('aria-selected',active?'true':'false');button.disabled=!!(mode!=='single'&&maximum&&selected.length>=maximum&&!active)});
			if(otherButton){
				var otherActive=has('__other__');
				otherButton.classList.toggle('is-selected',otherActive);
				otherButton.setAttribute('aria-selected',otherActive?'true':'false');
				otherButton.disabled=!!(mode!=='single'&&maximum&&selected.length>=maximum&&!otherActive);
				if(otherInput){otherInput.hidden=!otherActive;if(!otherActive)otherInput.value=''}
			}
			message(false);
		}
		function toggle(value){
			if(has(value)){
				selected=selected.filter(function(v){return v!==value});
			}else{
				if(mode==='single')selected=[];
				else if(maximum&&selected.length>=maximum){error.textContent='You can select up to '+maximum+' options.';return}
				selected.push(value);
			}
			draw();
			if(value==='__other__'&&has('__other__')&&otherInput)otherInput.focus();
		}
		optionButtons.forEach(function(button){button.addEventListener('click',function(){toggle(button.dataset.value)})});
		if(otherButton)otherButton.addEventListener('click',function(){toggle('__other__')});
		if(otherInput)otherInput.addEventListener('input',draw);
		root._validate=function(){return message(true)};draw();
	});
})();
JS;
	}

	private static function search_select_script() {
		return <<<'JS'
(function(){
	document.querySelectorAll('.ggm-search-select:not([data-search-ready])').forEach(function(root){
		root.dataset.searchReady='1';
		var input=root.querySelector('.ggm-search-select-input'),results=root.querySelector('.ggm-search-results'),selectedBox=root.querySelector('.ggm-search-selected'),valuesBox=root.querySelector('.ggm-search-values'),error=root.querySelector('.ggm-search-select-error');
		if(!input||!results||!selectedBox||!valuesBox)return;
		var options=[];try{options=JSON.parse(root.dataset.options||'[]')}catch(e){options=[]}
		options=options.map(function(item){return typeof item==='string'?item:(item&&item.label)||''}).filter(Boolean);
		var selected=[],minimum=Number(root.dataset.min||0),maximum=Number(root.dataset.max||1);
		var field=root.closest('.ggm-form-field'),cfg={};try{cfg=JSON.parse(field&&field.dataset.config||'{}')}catch(e){}
		if(cfg.required)minimum=Math.max(1,minimum);
		function identity(value){return String(value||'').toLocaleLowerCase().trim()}
		function compact(value){return identity(value).replace(/[^a-z0-9]+/g,'')}
		function charsInOrder(haystack,needle){
			var pos=0;
			for(var i=0;i<needle.length;i++){
				pos=haystack.indexOf(needle.charAt(i),pos);
				if(pos===-1)return false;
				pos++;
			}
			return true;
		}
		function matchScore(label,needle){
			var text=identity(label),packed=compact(label),packedNeedle=compact(needle),index=text.indexOf(needle),packedIndex=packedNeedle?packed.indexOf(packedNeedle):-1,words=text.split(/[^a-z0-9]+/).filter(Boolean),wordPrefix=words.some(function(word){return word.indexOf(needle)===0});
			if(!needle)return null;
			if(text===needle||packed===packedNeedle)return 0;
			if(text.indexOf(needle)===0)return 10;
			if(wordPrefix)return 20;
			if(packedNeedle&&packed.indexOf(packedNeedle)===0)return 30;
			if(index!==-1)return 40+index;
			if(packedIndex!==-1)return 60+packedIndex;
			if(packedNeedle&&charsInOrder(packed,packedNeedle))return 90;
			return null;
		}
		function showError(text){error.textContent=text||'';input.setCustomValidity(text||'')}
		function drawSelected(){
			selectedBox.innerHTML='';
			valuesBox.innerHTML='';
			selected.forEach(function(value,index){
				var chip=document.createElement('span');
				chip.className='ggm-search-selected-chip';
				chip.appendChild(document.createTextNode(value));
				var remove=document.createElement('button');
				remove.type='button';
				remove.setAttribute('aria-label','Remove '+value);
				remove.textContent='x';
				remove.addEventListener('click',function(){selected.splice(index,1);drawSelected();drawResults()});
				chip.appendChild(remove);
				selectedBox.appendChild(chip);
				var hidden=document.createElement('input');
				hidden.type='hidden';
				hidden.name=root.dataset.name;
				hidden.value=value;
				valuesBox.appendChild(hidden);
			});
			showError('');
		}
		function drawResults(){
			var q=input.value.trim(),needle=identity(q);
			results.innerHTML='';
			root.classList.remove('has-results');
			if(!needle)return;
			var selectedMap={};
			selected.forEach(function(value){selectedMap[identity(value)]=true});
			var matches=options.map(function(label){return {label:label,score:matchScore(label,needle)}}).filter(function(item){return item.score!==null&&!selectedMap[identity(item.label)]}).sort(function(a,b){return a.score-b.score||a.label.length-b.label.length||a.label.localeCompare(b.label)}).slice(0,30).map(function(item){return item.label});
			if(!matches.length){
				var empty=document.createElement('span');
				empty.className='ggm-search-empty';
				empty.textContent='No matching options found.';
				results.appendChild(empty);
				root.classList.add('has-results');
				return;
			}
			matches.forEach(function(label){
				var button=document.createElement('button');
				button.type='button';
				button.className='ggm-search-result';
				button.textContent=label;
				button.addEventListener('click',function(){
					if(maximum&&selected.length>=maximum){showError('You can select up to '+maximum+' options.');return}
					selected.push(label);
					input.value='';
					results.innerHTML='';
					root.classList.remove('has-results');
					drawSelected();
					input.focus();
				});
				results.appendChild(button);
			});
			root.classList.add('has-results');
		}
		root._validate=function(){
			if(selected.length<minimum){showError(minimum>1?'Please select at least '+minimum+' options.':'Please select at least one option.');return false}
			if(maximum&&selected.length>maximum){showError('You can select up to '+maximum+' options.');return false}
			showError('');
			return true;
		};
		input.addEventListener('input',function(){showError('');drawResults()});
		input.addEventListener('focus',drawResults);
		document.addEventListener('click',function(event){if(!root.contains(event.target)){root.classList.remove('has-results')}});
		drawSelected();
	});
})();
JS;
	}

	private static function file_upload_script() {
		return <<<'JS'
(function(){
	function initFileUploads(scope){
		(scope||document).querySelectorAll('.ggm-upload-control:not([data-upload-ready])').forEach(function(root){
		root.dataset.uploadReady='1';
		var input=root.querySelector('.ggm-file-input'),camera=root.querySelector('.ggm-file-camera-input'),dropzone=root.querySelector('.ggm-upload-dropzone'),browse=root.querySelector('.ggm-upload-browse'),cameraButton=root.querySelector('.ggm-upload-camera'),clear=root.querySelector('.ggm-upload-clear'),list=root.querySelector('.ggm-upload-list');
		if(!input||!dropzone||!list)return;
		var max=Number(root.dataset.maxFiles||1),files=[];
		var field=root.closest('.ggm-form-field'),cfg={};try{cfg=JSON.parse(field&&field.dataset.config||'{}')}catch(e){}
		function fileKey(file){return [file.name,file.size,file.lastModified].join('|')}
		function currentCount(){return (input.files?input.files.length:0)+(camera&&camera.files?camera.files.length:0)}
		function validate(){
			var count=currentCount(),message='';
			if(cfg.required&&!count)message='Please upload at least one file.';
			else if(count>max)message='You can upload up to '+max+' file(s).';
			if(!message){
				var maxBytes=Number(cfg.max_size||10)*1024*1024;
				Array.prototype.slice.call(input.files||[]).concat(Array.prototype.slice.call(camera&&camera.files||[])).some(function(file){
					if(file.size>maxBytes){message='One or more files exceed the allowed size.';return true}
					return false;
				});
			}
			input.setCustomValidity(message);
			return !message;
		}
		function assign(){
			if(window.DataTransfer){
				var dt=new DataTransfer();
				files.slice(0,max).forEach(function(file){dt.items.add(file)});
				input.files=dt.files;
			}
		}
		function draw(){
			assign();
			list.innerHTML='';
			files.forEach(function(file,index){
				var item=document.createElement('li');
				item.textContent=file.name+' ('+Math.ceil(file.size/1024)+' KB)';
				var remove=document.createElement('button');
				remove.type='button';
				remove.textContent='x';
				remove.setAttribute('aria-label','Remove '+file.name);
				remove.addEventListener('click',function(){files.splice(index,1);draw()});
				item.appendChild(remove);
				list.appendChild(item);
			});
			if(clear)clear.hidden=!files.length;
			validate();
		}
		function add(fileList){
			var seen={};
			files.forEach(function(file){seen[fileKey(file)]=true});
			Array.prototype.slice.call(fileList||[]).forEach(function(file){
				if(files.length>=max)return;
				var key=fileKey(file);
				if(!seen[key]){seen[key]=true;files.push(file)}
			});
			draw();
		}
		function fallbackCameraInput(){
			if(camera)camera.click();
			else input.click();
		}
		function openCamera(event){
			if(event)event.preventDefault();
			if(files.length>=max){
				input.setCustomValidity('You can upload up to '+max+' file(s).');
				if(input.reportValidity)input.reportValidity();
				return;
			}
			if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia||!window.HTMLCanvasElement){
				fallbackCameraInput();
				return;
			}
			var existing=root.querySelector('.ggm-camera-capture');
			if(existing)return;
			var panel=document.createElement('div'),video=document.createElement('video'),actions=document.createElement('div'),captureButton=document.createElement('button'),cancelButton=document.createElement('button'),status=document.createElement('p'),stream=null;
			panel.className='ggm-camera-capture';
			video.autoplay=true;
			video.playsInline=true;
			video.muted=true;
			actions.className='ggm-camera-actions';
			captureButton.type='button';
			captureButton.className='button button-primary';
			captureButton.textContent='Capture photo';
			captureButton.disabled=true;
			cancelButton.type='button';
			cancelButton.className='button';
			cancelButton.textContent='Cancel';
			status.className='ggm-camera-status';
			status.textContent='Opening camera...';
			actions.appendChild(captureButton);
			actions.appendChild(cancelButton);
			panel.appendChild(video);
			panel.appendChild(actions);
			panel.appendChild(status);
			root.insertBefore(panel,list);
			function cleanup(){
				if(stream)stream.getTracks().forEach(function(track){track.stop()});
				if(panel.parentNode)panel.parentNode.removeChild(panel);
			}
			cancelButton.addEventListener('click',cleanup);
			navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false}).then(function(activeStream){
				stream=activeStream;
				video.srcObject=stream;
				status.textContent='Camera ready.';
				captureButton.disabled=false;
				return video.play();
			}).catch(function(){
				cleanup();
				fallbackCameraInput();
			});
			captureButton.addEventListener('click',function(){
				if(!video.videoWidth){
					status.textContent='Camera is still loading. Please try again.';
					return;
				}
				var canvas=document.createElement('canvas');
				canvas.width=video.videoWidth;
				canvas.height=video.videoHeight;
				canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);
				canvas.toBlob(function(blob){
					if(!blob){
						status.textContent='Could not capture photo. Please try again.';
						return;
					}
					var name='camera-'+Date.now()+'.jpg',file;
					try{file=new File([blob],name,{type:'image/jpeg',lastModified:Date.now()})}catch(err){blob.name=name;blob.lastModified=Date.now();file=blob}
					add([file]);
					cleanup();
				},'image/jpeg',0.92);
			});
		}
		input.addEventListener('change',function(){files=Array.prototype.slice.call(input.files||[]).slice(0,max);draw()});
		if(camera)camera.addEventListener('change',function(){add(camera.files);if(window.DataTransfer)camera.value=''});
		if(browse)browse.addEventListener('click',function(){if(browse.tagName==='LABEL')return;input.click()});
		if(cameraButton)cameraButton.addEventListener('click',openCamera);
		if(clear)clear.addEventListener('click',function(){files=[];input.value='';if(camera)camera.value='';draw()});
		['dragenter','dragover'].forEach(function(type){dropzone.addEventListener(type,function(event){event.preventDefault();root.classList.add('is-dragging')})});
		['dragleave','drop'].forEach(function(type){dropzone.addEventListener(type,function(event){event.preventDefault();root.classList.remove('is-dragging')})});
		dropzone.addEventListener('drop',function(event){add(event.dataTransfer&&event.dataTransfer.files)});
		dropzone.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();input.click()}});
		root._validate=validate;
		draw();
	});
	}
	window.ggmInitFormFileUploads=initFileUploads;
	document.addEventListener('ggmFormAdditionalShown',function(event){initFileUploads(event.target||document)});
	initFileUploads(document);
})();
JS;
	}

	private static function post_submit_runtime_script() {
		$ajax_url = wp_json_encode( admin_url( 'admin-ajax.php' ) );
		return <<<JS
(function(){
	var ajaxUrl={$ajax_url};
	document.querySelectorAll('.ggm-built-form.ggm-form-complete:not([data-post-submit-ready])').forEach(function(root){
		root.dataset.postSubmitReady='1';
		var form=root.querySelector('form.ggm-post-submit-form');
		if(!form)return;
		function showAdditional(button){var disclosure=button.closest('.ggm-post-submit-disclosure'),box=disclosure&&disclosure.querySelector('.ggm-post-submit-fields');if(!box)return;box.hidden=false;button.setAttribute('aria-expanded','true');if(window.ggmInitFormFileUploads)window.ggmInitFormFileUploads(box);box.dispatchEvent(new CustomEvent('ggmFormAdditionalShown',{bubbles:true}));}
		function feedback(box,message,isError){var fb=box.querySelector('.ggm-post-submit-feedback');if(!fb)return;fb.textContent=message||'';fb.classList.toggle('is-error',!!isError)}
		function validate(box){var ok=true;box.querySelectorAll('input,textarea,select').forEach(function(i){i.setCustomValidity&&i.setCustomValidity('')});box.querySelectorAll('.ggm-upload-control').forEach(function(upload){if(upload._validate&&!upload._validate())ok=false});box.querySelectorAll('.ggm-chip-selector,.ggm-search-select').forEach(function(widget){if(widget._validate&&!widget._validate())ok=false});box.querySelectorAll('input,textarea,select').forEach(function(i){if(!i.checkValidity())ok=false});if(!ok){var first=[].slice.call(box.querySelectorAll('input,textarea,select')).find(function(i){return !i.checkValidity()});if(first&&first.reportValidity)first.reportValidity()}return ok}
		function finish(box,message){var disclosure=box.closest('.ggm-post-submit-disclosure'),openButton=disclosure&&disclosure.querySelector('.ggm-post-submit-open');if(openButton)openButton.remove();box.innerHTML='<div class="ggm-form-success" role="status">'+(message||'Additional details saved.')+'</div>'}
		function save(button){var box=button.closest('.ggm-post-submit-fields');if(!box||!validate(box))return;button.disabled=true;feedback(box,'Saving additional details...',false);var data=new FormData(form);data.set('action','ggm_update_builder_form_after_submit');data.set('submission_id',root.dataset.submissionId||'');data.set('after_submit_token',root.dataset.afterSubmitToken||'');fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:data}).then(function(r){return r.json()}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Could not save additional details.');finish(box,(r.data&&r.data.message)||'Additional details saved.');if(r.data&&r.data.files_html){var host=document.querySelector('.ggm-health-uploaded-files-host'),intro=document.querySelector('.ggm-health-uploaded-intro');if(host)host.innerHTML=r.data.files_html;if(intro)intro.hidden=true;}}).catch(function(err){button.disabled=false;feedback(box,err.message,true)})}
		form.addEventListener('click',function(event){var openButton=event.target.closest('.ggm-post-submit-open'),saveButton=event.target.closest('.ggm-post-submit-save');if(openButton){showAdditional(openButton)}if(saveButton){save(saveButton)}});
	});
})();
JS;
	}

	private static function render_field( $field, $id_prefix = '', $prefill_user_id = 0 ) {
		$field = self::hydrate_dynamic_field( $field );
		$key = sanitize_key( $field['key'] );
		$edit_value = self::$editing_answers[ $key ] ?? null;
		$name = 'answers[' . $key . ']';
		$dom_key = sanitize_key( $id_prefix . $key );
		$label_id = 'ggm-field-label-' . $dom_key;
		$labelledby = ' aria-labelledby="' . esc_attr( $label_id ) . '"';
		$required = ! empty( $field['required'] );
		$field_width = self::sanitize_field_width( $field['field_width'] ?? '100' );
		echo '<div class="ggm-form-field ggm-field-' . esc_attr( $field['type'] ) . ' ggm-field-width-' . esc_attr( $field_width ) . '" role="group" aria-labelledby="' . esc_attr( $label_id ) . '" data-config="' . esc_attr( wp_json_encode( $field ) ) . '"><div class="ggm-field-label" id="' . esc_attr( $label_id ) . '"><strong>' . esc_html( $field['label'] ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</strong></div>';
		if ( ! empty( $field['description'] ) ) {
			echo 'checkboxes' === $field['type']
				? '<div class="description">' . wp_kses_post( $field['description'] ) . '</div>'
				: '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}
		$req = $required ? ' required' : '';
		$options = (array) ( $field['options'] ?? array() );
		$rows = (array) ( $field['rows'] ?? array() );
		if ( ! empty( $field['shuffle_options'] ) ) { shuffle( $options ); }
		if ( ! empty( $field['shuffle_rows'] ) ) { shuffle( $rows ); }
		switch ( $field['type'] ) {
			case 'short_answer':
				$validation = $field['validation_type'] ?? ( $field['subtype'] ?? 'none' );
				if ( 'phone' === $validation || ( 'number' === $validation && ! empty( $field['phone_country_enabled'] ) ) ) {
					$selected_iso = self::sanitize_country_iso( $field['phone_country_iso'] ?? 'IN' );
					$selected_dial = self::country_dial_for_iso( $selected_iso );
					$prefill = null !== $edit_value && ! is_array( $edit_value ) ? $edit_value : ( $prefill_user_id ? self::prefill_value( 'phone', $field['label'] ?? '', $prefill_user_id ) : '' );
					$phone_digits = preg_replace( '/\D/', '', (string) $prefill );
					$country_digits = preg_replace( '/\D/', '', $selected_dial );
					if ( $country_digits && 0 === strpos( $phone_digits, $country_digits ) ) {
						$phone_digits = substr( $phone_digits, strlen( $country_digits ) );
					}
					$lengths = ( ! empty( $field['min_length'] ) ? ' minlength="' . esc_attr( $field['min_length'] ) . '"' : '' ) . ' maxlength="' . esc_attr( ! empty( $field['max_length'] ) ? min( 15, (int) $field['max_length'] ) : 15 ) . '"';
					$countries = self::country_calling_codes();
					$selected_country = null;
					foreach ( $countries as $country ) {
						if ( $selected_iso === $country['iso'] ) { $selected_country = $country; break; }
					}
					if ( ! $selected_country ) {
						$selected_country = array( 'iso' => 'IN', 'dial' => '+91', 'name' => 'India' );
					}
					$selected_label = $selected_country['dial'];
					echo '<div class="ggm-phone-country-control"><div class="ggm-country-picker" data-country-picker><input type="hidden" name="answers_country_code[' . esc_attr( $key ) . ']" value="' . esc_attr( $selected_country['dial'] ) . '"><button type="button" class="ggm-country-picker-button" aria-haspopup="listbox" aria-expanded="false"' . $labelledby . '>' . self::country_flag_svg( $selected_country['iso'] ) . '<span>' . esc_html( $selected_label ) . '</span></button><div class="ggm-country-options" role="listbox"><input type="search" class="ggm-country-search" placeholder="' . esc_attr__( 'Search country or code', 'ggm-member-dashboard' ) . '" aria-label="' . esc_attr__( 'Search countries', 'ggm-member-dashboard' ) . '">';
					foreach ( self::country_calling_codes() as $country ) {
						$label = $country['dial'];
						$country_name = self::country_display_name( $country['iso'] );
						echo '<button type="button" class="ggm-country-option" role="option" data-value="' . esc_attr( $country['dial'] ) . '" data-label="' . esc_attr( $label ) . '" data-iso="' . esc_attr( strtolower( $country['iso'] ) ) . '" data-search="' . esc_attr( strtolower( $country_name . ' ' . $country['iso'] . ' ' . $country['dial'] ) ) . '" title="' . esc_attr( $country_name . ' ' . $country['dial'] ) . '" aria-selected="' . ( $selected_iso === $country['iso'] ? 'true' : 'false' ) . '">' . self::country_flag_svg( $country['iso'] ) . '<span>' . esc_html( $country_name . ' ' . $label ) . '</span></button>';
					}
					echo '</div></div><input type="tel" name="' . esc_attr( $name ) . '" value="' . esc_attr( $phone_digits ) . '" placeholder="' . esc_attr( $field['placeholder'] ?: __( 'Phone number', 'ggm-member-dashboard' ) ) . '" inputmode="tel" autocomplete="tel-national"' . $labelledby . $lengths . $req . '></div>';
					break;
				}
				$type = array( 'email'=>'email','number'=>'number','phone'=>'tel','url'=>'url' )[ $validation ] ?? 'text';
				$lengths = ( ! empty( $field['min_length'] ) ? ' minlength="' . esc_attr( $field['min_length'] ) . '"' : '' ) . ( ! empty( $field['max_length'] ) ? ' maxlength="' . esc_attr( $field['max_length'] ) . '"' : '' );
				$prefill = null !== $edit_value && ! is_array( $edit_value ) ? $edit_value : ( $prefill_user_id ? self::prefill_value( $validation, $field['label'] ?? '', $prefill_user_id ) : '' );
				echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $prefill ) . '" placeholder="' . esc_attr( $field['placeholder'] ?? '' ) . '"' . $labelledby . $lengths . $req . '>'; break;
			case 'paragraph':
				$lengths = ( ! empty( $field['min_length'] ) ? ' minlength="' . esc_attr( $field['min_length'] ) . '"' : '' ) . ( ! empty( $field['max_length'] ) ? ' maxlength="' . esc_attr( $field['max_length'] ) . '"' : '' );
				echo '<textarea name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $field['placeholder'] ?? '' ) . '"' . $labelledby . $lengths . $req . '>' . esc_textarea( is_scalar( $edit_value ) ? $edit_value : '' ) . '</textarea>'; break;
			case 'multiple_choice':
			case 'checkboxes':
				$type = 'checkboxes' === $field['type'] ? 'checkbox' : 'radio';
				foreach ( $options as $option ) {
					$n = 'checkbox' === $type ? $name . '[]' : $name;
					$option_label = 'checkboxes' === $field['type'] ? wp_kses( $option['label'], self::inline_html_tags() ) : esc_html( $option['label'] );
					// The label text is wrapped in its own <span> rather than
					// sitting as a bare child of the flex-laid-out <label> —
					// a <br> as a direct child of a flex container becomes its
					// own flex item and lays out along the row axis instead of
					// breaking the line, so a line break inside a checkboxes
					// option's rich text otherwise gets silently swallowed.
					$selected_values = is_array( $edit_value ) ? $edit_value : array( $edit_value );
					$checked = in_array( $option['key'], $selected_values, true ) ? ' checked' : '';
					echo '<label class="ggm-form-choice"><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $n ) . '" value="' . esc_attr( $option['key'] ) . '"' . $checked . ( 'radio' === $type ? $req : '' ) . '> <span class="ggm-form-choice-text">' . $option_label . '</span></label>';
				}
				if ( ! empty( $field['allow_other'] ) ) {
					$n = 'checkbox' === $type ? $name . '[]' : $name;
					echo '<label class="ggm-form-choice ggm-other-choice"><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $n ) . '" value="__other__"' . ( 'radio' === $type ? $req : '' ) . '> ' . esc_html__( 'Other:', 'ggm-member-dashboard' ) . ' <input type="text" name="answers_other[' . esc_attr( $key ) . ']" class="ggm-other-input" aria-label="' . esc_attr__( 'Other response', 'ggm-member-dashboard' ) . '"></label>';
				} break;
			case 'dropdown':
				echo '<select name="' . esc_attr( $name ) . '"' . $labelledby . $req . '><option value="">' . esc_html( $field['select_prompt'] ?? __( 'Choose an option', 'ggm-member-dashboard' ) ) . '</option>';
				foreach ( $options as $option ) { echo '<option value="' . esc_attr( $option['key'] ) . '"' . selected( $edit_value, $option['key'], false ) . '>' . esc_html( $option['label'] ) . '</option>'; }
				echo '</select>'; break;
			case 'file_upload':
				$accept = self::file_accept( $field );
				$max_files = max( 1, min( 50, (int) ( $field['max_files'] ?? 1 ) ) );
				$multiple = $max_files > 1;
				$upload_id = 'ggm-file-upload-' . $dom_key;
				$camera_id = 'ggm-file-camera-' . $dom_key;
				echo '<div class="ggm-upload-control" data-max-files="' . esc_attr( $max_files ) . '">';
				echo '<input id="' . esc_attr( $upload_id ) . '" class="ggm-file-input" type="file" name="files[' . esc_attr( $key ) . '][]" accept="' . esc_attr( $accept ) . '"' . $labelledby . ( $multiple ? ' multiple' : '' ) . '>';
				echo '<input id="' . esc_attr( $camera_id ) . '" class="ggm-file-camera-input" type="file" name="files[' . esc_attr( $key ) . '][]" accept="image/*" capture="environment" tabindex="-1" aria-hidden="true">';
				echo '<div class="ggm-upload-dropzone" tabindex="0"><span>' . esc_html__( 'Drag and drop files here', 'ggm-member-dashboard' ) . '</span><small>' . esc_html__( 'or choose a source below', 'ggm-member-dashboard' ) . '</small></div>';
				echo '<div class="ggm-upload-actions"><label class="button ggm-upload-browse" for="' . esc_attr( $upload_id ) . '">' . esc_html__( 'Upload from device', 'ggm-member-dashboard' ) . '</label><button type="button" class="button ggm-upload-camera">' . esc_html__( 'Capture from camera', 'ggm-member-dashboard' ) . '</button><button type="button" class="button-link ggm-upload-clear" hidden>' . esc_html__( 'Clear', 'ggm-member-dashboard' ) . '</button></div>';
				echo '<ul class="ggm-upload-list" aria-live="polite"></ul><small>' . sprintf( esc_html__( 'Up to %1$d file(s), %2$d MB each. Server limit: %3$s.', 'ggm-member-dashboard' ), $max_files, self::effective_file_mb( $field ), esc_html( size_format( wp_max_upload_size() ) ) ) . '</small></div>'; break;
			case 'linear_scale':
				$min = isset( $field['min'] ) ? max( 0, min( 1, (int) $field['min'] ) ) : 1; $max = isset( $field['max'] ) ? max( 2, min( 10, (int) $field['max'] ) ) : 5;
				echo '<div class="ggm-linear-scale"><span class="ggm-scale-end">' . esc_html( $field['min_label'] ?? '' ) . '</span><div class="ggm-scale-options">';
				for ( $i=$min; $i<=$max; $i++ ) { echo '<label><span>' . esc_html( $i ) . '</span><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $i ) . '"' . $req . '></label>'; }
				echo '</div><span class="ggm-scale-end">' . esc_html( $field['max_label'] ?? '' ) . '</span></div>'; break;
			case 'multiple_choice_grid':
			case 'checkbox_grid':
				echo '<div class="ggm-grid-scroll"><table class="ggm-response-grid"' . $labelledby . '><thead><tr><th></th>';
				foreach ( $options as $option ) { echo '<th scope="col">' . esc_html( $option['label'] ) . '</th>'; }
				echo '</tr></thead><tbody>';
				foreach ( $rows as $row ) {
					echo '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th>';
					foreach ( $options as $option ) {
						$type = 'checkbox_grid' === $field['type'] ? 'checkbox' : 'radio';
						$n = $name . '[' . $row['key'] . ']' . ( 'checkbox' === $type ? '[]' : '' );
						$row_required = ! empty( $field['require_each_row'] ) && 'radio' === $type ? ' required' : '';
						echo '<td><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $n ) . '" value="' . esc_attr( $option['key'] ) . '"' . $row_required . ' aria-label="' . esc_attr( $row['label'] . ': ' . $option['label'] ) . '"></td>';
					} echo '</tr>';
				} echo '</tbody></table></div>'; break;
			case 'chip_selector':
				$mode = 'single' === ( $field['selection_mode'] ?? 'multiple' ) ? 'single' : 'multiple';
				$allow_other = ! empty( $field['allow_custom_values'] );
				echo '<div class="ggm-chip-selector" data-name="' . esc_attr( $name . '[]' ) . '" data-mode="' . esc_attr( $mode ) . '" data-min="' . esc_attr( 'single' === $mode ? 0 : absint( $field['min_selections'] ?? 0 ) ) . '" data-max="' . esc_attr( 'single' === $mode ? 1 : absint( $field['max_selections'] ?? 0 ) ) . '">';
				echo '<div class="ggm-chip-options" role="listbox" aria-multiselectable="' . ( 'multiple' === $mode ? 'true' : 'false' ) . '" aria-label="' . esc_attr( $field['label'] ) . '">';
				foreach ( $options as $option ) {
					echo '<button type="button" class="ggm-chip-option" role="option" aria-selected="false" data-value="' . esc_attr( $option['label'] ) . '">' . esc_html( $option['label'] ) . '</button>';
				}
				if ( $allow_other ) {
					echo '<button type="button" class="ggm-chip-option ggm-chip-option-other" role="option" aria-selected="false" data-value="__other__">' . esc_html__( 'Other', 'ggm-member-dashboard' ) . '</button>';
				}
				echo '</div>';
				if ( $allow_other ) {
					echo '<input type="text" name="answers_other[' . esc_attr( $key ) . ']" class="ggm-other-input ggm-chip-other-input" hidden maxlength="150" placeholder="' . esc_attr__( 'Please specify…', 'ggm-member-dashboard' ) . '" aria-label="' . esc_attr__( 'Other response', 'ggm-member-dashboard' ) . '">';
				}
				echo '<div class="ggm-chip-values"></div><small class="ggm-chip-error" aria-live="polite"></small></div>';
				break;
			case 'search_select':
				$search_options = array();
				foreach ( $options as $option ) {
					$label = sanitize_text_field( wp_strip_all_tags( (string) ( $option['label'] ?? '' ) ) );
					if ( '' !== $label ) {
						$search_options[] = array( 'label' => $label );
					}
				}
				$max_selections = max( 1, absint( $field['max_selections'] ?? 1 ) );
				echo '<div class="ggm-search-select" data-name="' . esc_attr( $name . '[]' ) . '" data-min="' . esc_attr( $required ? 1 : 0 ) . '" data-max="' . esc_attr( $max_selections ) . '" data-options="' . esc_attr( wp_json_encode( $search_options ) ) . '">';
				echo '<div class="ggm-search-selected" aria-live="polite"></div>';
				echo '<input type="search" class="ggm-search-select-input" autocomplete="off" placeholder="' . esc_attr__( 'Start typing to search', 'ggm-member-dashboard' ) . '"' . $labelledby . '>';
				echo '<div class="ggm-search-results" role="listbox" aria-label="' . esc_attr( $field['label'] ) . '"></div>';
				echo '<div class="ggm-search-values"></div><small class="ggm-search-select-error" aria-live="polite"></small></div>';
				break;
			case 'date':
				echo '<input class="ggm-date-picker" type="date" name="' . esc_attr( $name ) . '"' . $labelledby . ( ! empty( $field['min_date'] ) ? ' min="' . esc_attr( $field['min_date'] ) . '"' : '' ) . ( ! empty( $field['max_date'] ) ? ' max="' . esc_attr( $field['max_date'] ) . '"' : '' ) . $req . '>';
				break;
			case 'time':
				if ( 'duration' === ( $field['time_type'] ?? 'time_of_day' ) ) {
					echo '<div class="ggm-duration-inputs">';
					foreach ( (array) ( $field['duration_units'] ?? array( 'hours','minutes' ) ) as $unit ) {
						$limit = in_array( $unit, array( 'minutes','seconds' ), true ) ? ' max="59"' : '';
						echo '<label>' . esc_html( ucfirst( $unit ) ) . '<input type="number" min="0"' . $limit . ' name="' . esc_attr( $name . '[' . $unit . ']' ) . '" value="0"></label>';
					}
					echo '</div>';
				} elseif ( '12' === (string) ( $field['time_format'] ?? '24' ) ) {
					echo '<div class="ggm-time-12"><select name="' . esc_attr( $name . '[hour]' ) . '" aria-label="' . esc_attr__( 'Hour', 'ggm-member-dashboard' ) . '"' . $req . '><option value="">' . esc_html__( 'Hour', 'ggm-member-dashboard' ) . '</option>';
					for ( $hour=1; $hour<=12; $hour++ ) { echo '<option value="' . esc_attr( $hour ) . '">' . esc_html( $hour ) . '</option>'; }
					echo '</select><select name="' . esc_attr( $name . '[minute]' ) . '" aria-label="' . esc_attr__( 'Minute', 'ggm-member-dashboard' ) . '"' . $req . '><option value="">' . esc_html__( 'Minute', 'ggm-member-dashboard' ) . '</option>';
					for ( $minute=0; $minute<60; $minute++ ) { echo '<option value="' . esc_attr( sprintf( '%02d', $minute ) ) . '">' . esc_html( sprintf( '%02d', $minute ) ) . '</option>'; }
					echo '</select><select name="' . esc_attr( $name . '[period]' ) . '" aria-label="' . esc_attr__( 'AM or PM', 'ggm-member-dashboard' ) . '"' . $req . '><option value="am">AM</option><option value="pm">PM</option></select></div>';
				} else {
					echo '<input type="time" name="' . esc_attr( $name ) . '"' . $labelledby . $req . '>';
				} break;
		}
		if ( ! empty( $field['validation_message'] ) ) { echo '<small class="ggm-validation-hint">' . esc_html( $field['validation_message'] ) . '</small>'; }
		echo '</div>';
	}

	private static function file_type_map() {
		return array(
			'image' => array( 'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp' ),
			'pdf' => array( 'pdf'=>'application/pdf' ),
			'document' => array( 'doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','txt'=>'text/plain','rtf'=>'application/rtf' ),
			'spreadsheet' => array( 'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','csv'=>'text/csv' ),
			'presentation' => array( 'ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation' ),
			'video' => array( 'mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime' ),
			'audio' => array( 'mp3'=>'audio/mpeg','wav'=>'audio/wav','m4a'=>'audio/mp4','ogg'=>'audio/ogg' ),
		);
	}

	private static function allowed_file_types( $field ) {
		$allowed = array();
		$map = self::file_type_map();
		foreach ( (array) ( $field['allowed_type_groups'] ?? array( 'image','pdf','document' ) ) as $group ) {
			if ( isset( $map[ $group ] ) ) { $allowed += $map[ $group ]; }
		}
		$allowed += $map['image'];
		$wp_types = get_allowed_mime_types();
		foreach ( (array) ( $field['custom_extensions'] ?? array() ) as $ext ) {
			foreach ( $wp_types as $pattern=>$mime ) {
				if ( preg_match( '/^(?:' . str_replace( '|', '|', $pattern ) . ')$/i', $ext ) ) { $allowed[ $ext ] = $mime; break; }
			}
		}
		return $allowed;
	}

	private static function file_accept( $field ) {
		return implode( ',', array_map( static function( $ext ) { return '.' . $ext; }, array_keys( self::allowed_file_types( $field ) ) ) );
	}

	private static function effective_file_mb( $field ) {
		return max( 1, min( (int) ( $field['max_size'] ?? 10 ), (int) floor( wp_max_upload_size() / MB_IN_BYTES ) ) );
	}

	private static function after_submit_fields( array $schema ) {
		$fields = array();
		foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) {
			foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
				if ( ! empty( $field['show_after_submit'] ) ) {
					$fields[] = $field;
				}
			}
		}
		return $fields;
	}

	/**
	 * Resolve post-submit fields using the same live schema shown to members.
	 *
	 * Submission snapshots remain the fallback for deleted/unavailable forms, but
	 * builder changes such as moving a report field after submit must also apply
	 * to registrations created on an older version.
	 */
	private static function effective_submission_schema( $submission ) {
		$form = $submission ? self::get_form( (int) $submission->form_id ) : null;
		$schema = $form ? self::get_schema( $form ) : array();
		if ( empty( $schema['sections'] ) ) {
			$schema = json_decode( (string) ( $submission->schema_snapshot_json ?? '' ), true );
			$schema = is_array( $schema ) ? self::normalize_runtime_schema( $schema ) : array();
		}
		return $schema;
	}

	private static function create_after_submit_token( $submission_id ) {
		$token = bin2hex( random_bytes( 24 ) );
		set_transient( 'ggm_after_submit_' . absint( $submission_id ), hash( 'sha256', $token ), 30 * MINUTE_IN_SECONDS );
		return $token;
	}

	private static function verify_after_submit_token( $submission_id, $token ) {
		$stored = get_transient( 'ggm_after_submit_' . absint( $submission_id ) );
		return is_string( $stored ) && hash_equals( $stored, hash( 'sha256', (string) $token ) );
	}

	private function post_submit_response_data( $submission_id, array $schema ) {
		$fields = self::after_submit_fields( $schema );
		$has_required = false;
		foreach ( $fields as $field ) {
			if ( ! empty( $field['required'] ) ) {
				$has_required = true;
				break;
			}
		}
		return array(
			'submission_id'             => (int) $submission_id,
			'after_submit_token'        => $fields ? self::create_after_submit_token( $submission_id ) : '',
			'has_after_submit_fields'   => ! empty( $fields ),
			'after_submit_required'     => $has_required,
		);
	}

	public function submit_form() {
		$result   = $this->create_submission_from_request( 'submitted' );
		$settings = $result['settings'];
		$response = array(
			'message' => wp_strip_all_tags( $settings['confirmation'] ?? __( 'Thank you. Your response has been submitted.', 'ggm-member-dashboard' ) ),
			'message_html' => wp_kses_post( $settings['confirmation'] ?? __( 'Thank you. Your response has been submitted.', 'ggm-member-dashboard' ) ),
			'whatsapp_url' => ! empty( $settings['whatsapp_group_url'] ) ? esc_url_raw( $settings['whatsapp_group_url'] ) : '',
		);
		wp_send_json_success( array_merge( $response, $this->post_submit_response_data( $result['submission_id'], $result['schema'] ) ) );
	}

	/**
	 * Return a current, short-lived authorization token for a payment form.
	 *
	 * Shortcode and page-builder output is commonly cached. Fetching the token
	 * immediately before order creation keeps a cached form usable after an
	 * administrator publishes a newer form version.
	 */
	public function refresh_form_payment_nonce() {
		$form_id = absint( $_POST['form_id'] ?? 0 );
		$form    = self::get_form( $form_id );
		if ( ! $form || 'published' !== $form->status ) {
			wp_send_json_error( array( 'message' => __( 'This form is unavailable.', 'ggm-member-dashboard' ) ), 404 );
		}

		$settings = json_decode( (string) $form->settings_json, true );
		$settings = is_array( $settings ) ? $settings : array();
		if ( empty( $settings['payment_enabled'] ) || ! self::get_payment_options( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Payment is not configured for this form.', 'ggm-member-dashboard' ) ), 400 );
		}

		$context    = sanitize_key( wp_unslash( $_POST['context_type'] ?? 'shortcode' ) );
		$context_id = absint( $_POST['context_id'] ?? 0 );
		if ( in_array( $context, array( 'dashboard', 'elementor_popup', 'shortcode' ), true ) ) {
			$context_id = 0;
		}
		if ( ! $this->context_allowed( $form_id, $context, $context_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This form is not assigned here.', 'ggm-member-dashboard' ) ), 403 );
		}

		$form_version = absint( $_POST['form_version'] ?? 0 );
		if ( ! $form_version || empty( self::get_schema( $form, $form_version )['sections'] ) ) {
			$form_version = (int) $form->current_version;
		}
		wp_send_json_success( array(
			'form_version' => $form_version,
			'nonce'        => wp_create_nonce( 'ggm_submit_form_' . $form_id . '_' . $form_version ),
		) );
	}

	public function create_form_payment() {
		$form_id = absint( $_POST['form_id'] ?? 0 );
		$preflight_form = self::get_form( $form_id );
		$preflight_settings = $preflight_form ? json_decode( (string) $preflight_form->settings_json, true ) : array();
		$preflight_settings = is_array( $preflight_settings ) ? $preflight_settings : array();
		$selected_option = self::payment_option_by_id( $preflight_settings, $_POST['payment_option_id'] ?? '' );
		if ( empty( $preflight_settings['payment_enabled'] ) || ! $selected_option ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid payment price.', 'ggm-member-dashboard' ) ), 400 );
		}
		$result   = $this->create_submission_from_request( 'pending_payment' );
		$form     = $result['form'];
		$settings = $result['settings'];
		$user_id  = $result['user_id'];
		$selected_option = self::payment_option_by_id( $settings, $_POST['payment_option_id'] ?? '' );
		$amount   = $selected_option ? (float) $selected_option['amount'] : 0.0;
		// Existing forms may still contain the former free-text value (for
		// example the rupee symbol). Repair it at runtime so users can pay
		// immediately without requiring an administrator to re-save the form.
		$currency = self::payment_currency( $settings['payment_currency'] ?? ggm_get_setting( 'ggm_currency', 'INR' ) );

		if ( $amount <= 0 ) {
			$this->delete_failed_submission( $result['submission_id'] );
			wp_send_json_error( array( 'message' => __( 'Payment is not configured for this form.', 'ggm-member-dashboard' ) ) );
		}

		if ( ! class_exists( 'GGM_Razorpay' ) ) {
			$this->delete_failed_submission( $result['submission_id'] );
			wp_send_json_error( array( 'message' => __( 'Payment gateway is unavailable.', 'ggm-member-dashboard' ) ), 500 );
		}

		$razorpay = new GGM_Razorpay();
		$receipt  = 'GGM-F' . (int) $form->id . '-S' . (int) $result['submission_id'] . '-' . time();
		$order    = $razorpay->create_order( $amount, $currency, $receipt );
		if ( is_wp_error( $order ) ) {
			$this->delete_failed_submission( $result['submission_id'] );
			wp_send_json_error( array( 'message' => $order->get_error_message() ) );
		}

		global $wpdb;
		$now      = current_time( 'mysql' );
		$inserted = $wpdb->insert( self::table( 'ggm_form_payments' ), array(
			'form_id'            => (int) $form->id,
			'submission_id'      => (int) $result['submission_id'],
			'user_id'            => $user_id,
			'payment_key'        => $selected_option['id'],
			'label'              => $selected_option['label'],
			'description'        => sanitize_text_field( $settings['payment_description'] ?? '' ),
			'razorpay_order_id'  => sanitize_text_field( $order['id'] ?? '' ),
			'amount'             => $amount,
			'currency'           => $currency,
			'status'             => 'pending',
			'created_at'         => $now,
			'updated_at'         => $now,
		), array( '%d','%d','%d','%s','%s','%s','%s','%f','%s','%s','%s','%s' ) );

		if ( ! $inserted ) {
			$this->delete_failed_submission( $result['submission_id'] );
			wp_send_json_error( array( 'message' => __( 'Could not create payment record.', 'ggm-member-dashboard' ) ), 500 );
		}

		wp_send_json_success( array(
			'order_id'        => sanitize_text_field( $order['id'] ?? '' ),
			'amount'          => absint( $order['amount'] ?? round( $amount * 100 ) ),
			'currency'        => sanitize_text_field( $order['currency'] ?? $currency ),
			'key_id'          => $razorpay->get_key_id(),
			'form_payment_id' => (int) $wpdb->insert_id,
			'form_id'         => (int) $form->id,
			'submission_id'   => (int) $result['submission_id'],
			'name'            => get_bloginfo( 'name' ),
			'description'     => sanitize_text_field( $settings['payment_description'] ?: $selected_option['label'] ?: $form->title ),
			'guest_payment_token' => (string) ( $result['guest_payment_token'] ?? '' ),
		) );
	}

	public function verify_form_payment() {
		$form_payment_id = absint( $_POST['form_payment_id'] ?? 0 );
		$rp_order_id     = sanitize_text_field( wp_unslash( $_POST['razorpay_order_id'] ?? '' ) );
		$rp_payment_id   = sanitize_text_field( wp_unslash( $_POST['razorpay_payment_id'] ?? '' ) );
		$rp_signature    = sanitize_text_field( wp_unslash( $_POST['razorpay_signature'] ?? '' ) );
		$guest_token     = sanitize_text_field( wp_unslash( $_POST['guest_payment_token'] ?? '' ) );
		if ( ! $form_payment_id || ! $rp_order_id || ! $rp_payment_id || ! $rp_signature ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment response.', 'ggm-member-dashboard' ) ) );
		}

		global $wpdb;
		$payment = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'ggm_form_payments' ) . ' WHERE id=%d', $form_payment_id ) );
		$payment_owned = $payment && (int) $payment->user_id === get_current_user_id();
		if ( $payment_owned && 0 === (int) $payment->user_id ) {
			$pending = $wpdb->get_row( $wpdb->prepare( 'SELECT answers_json FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE id=%d AND user_id=0 AND status='pending_payment'", (int) $payment->submission_id ) );
			$pending_answers = $pending ? json_decode( (string) $pending->answers_json, true ) : array();
			$stored_hash = is_array( $pending_answers ) ? (string) ( $pending_answers['_ggm_guest_payment_token_hash'] ?? '' ) : '';
			$payment_owned = $stored_hash && preg_match( '/^[a-f0-9]{64}$/', $guest_token ) && hash_equals( $stored_hash, $this->guest_payment_token_hash( $guest_token ) );
		}
		if ( ! $payment || ! $payment_owned || 'pending' !== $payment->status || $payment->razorpay_order_id !== $rp_order_id ) {
			wp_send_json_error( array( 'message' => __( 'Payment record not found.', 'ggm-member-dashboard' ) ), 404 );
		}

		$razorpay = new GGM_Razorpay();
		if ( ! $razorpay->verify_signature( $rp_order_id, $rp_payment_id, $rp_signature ) ) {
			$wpdb->update( self::table( 'ggm_form_payments' ), array( 'status'=>'failed', 'updated_at'=>current_time( 'mysql' ) ), array( 'id'=>$form_payment_id ), array( '%s','%s' ), array( '%d' ) );
			wp_send_json_error( array( 'message' => __( 'Payment verification failed.', 'ggm-member-dashboard' ) ) );
		}

		$now = current_time( 'mysql' );
		$finalized = $wpdb->update( self::table( 'ggm_form_payments' ), array(
			'status'              => 'success',
			'razorpay_payment_id' => $rp_payment_id,
			'razorpay_signature'  => $rp_signature,
			'updated_at'          => $now,
		), array( 'id'=>$form_payment_id, 'status'=>'pending' ), array( '%s','%s','%s','%s' ), array( '%d','%s' ) );
		if ( 1 !== $finalized ) {
			wp_send_json_error( array( 'message' => __( 'This payment has already been processed. Refresh the page to view the result.', 'ggm-member-dashboard' ) ), 409 );
		}
		$form = self::get_form( (int) $payment->form_id );
		$settings = $form ? json_decode( (string) $form->settings_json, true ) : array();
		$settings = is_array( $settings ) ? $settings : array();
		$payment_first = 'before_form' === ( $settings['payment_flow'] ?? 'submit' );
		$next_status = $payment_first ? 'paid_awaiting_submission' : 'submitted';
		$wpdb->update( self::table( 'ggm_form_submissions' ), array( 'status'=>$next_status, 'submitted_at'=>$now, 'updated_at'=>$now ), array( 'id'=>(int) $payment->submission_id, 'user_id'=>get_current_user_id(), 'status'=>'pending_payment' ), array( '%s','%s','%s' ), array( '%d','%d','%s' ) );
		if ( $payment_first && 0 === get_current_user_id() ) {
			$paid_submission = $wpdb->get_row( $wpdb->prepare( 'SELECT context_type,context_id FROM ' . self::table( 'ggm_form_submissions' ) . ' WHERE id=%d', (int) $payment->submission_id ) );
			if ( $paid_submission ) { $this->set_guest_payment_cookie( (int) $payment->form_id, $paid_submission->context_type, (int) $paid_submission->context_id, (int) $payment->submission_id, $guest_token ); }
		}

		$submission = $wpdb->get_row( $wpdb->prepare( 'SELECT id, form_version, schema_snapshot_json FROM ' . self::table( 'ggm_form_submissions' ) . ' WHERE id=%d', (int) $payment->submission_id ) );
		$schema = $submission ? json_decode( (string) $submission->schema_snapshot_json, true ) : array();
		$schema = is_array( $schema ) ? $schema : ( $form ? self::get_schema( $form, (int) ( $submission->form_version ?? 0 ) ) : array() );

		$response = array(
			'message' => wp_strip_all_tags( $settings['payment_success'] ?? __( 'Payment received. Your response has been submitted.', 'ggm-member-dashboard' ) ),
			'message_html' => wp_kses_post( $settings['payment_success'] ?? __( 'Payment received. Your response has been submitted.', 'ggm-member-dashboard' ) ),
			'whatsapp_url' => ! empty( $settings['whatsapp_group_url'] ) ? esc_url_raw( $settings['whatsapp_group_url'] ) : '',
			'payment_first_reload' => $payment_first,
		);
		wp_send_json_success( array_merge( $response, $this->post_submit_response_data( (int) $payment->submission_id, $schema ) ) );
	}

	public function ajax_render_popup_form() {
		$form_id = absint( $_POST['form_id'] ?? 0 );
		$form    = self::get_form( $form_id );
		if ( ! $form || 'published' !== $form->status || ! $this->context_allowed( $form_id, 'elementor_popup', 0 ) ) {
			wp_send_json_error( array( 'message' => __( 'This form is unavailable.', 'ggm-member-dashboard' ) ), 404 );
		}
		wp_send_json_success( array( 'html' => self::render_form( $form, 'elementor_popup', 0 ) ) );
	}

	public function update_after_submit_fields() {
		$submission_id = absint( $_POST['submission_id'] ?? 0 );
		$token = sanitize_text_field( wp_unslash( $_POST['after_submit_token'] ?? '' ) );
		if ( ! $submission_id ) {
			wp_send_json_error( array( 'message' => __( 'Submission not found.', 'ggm-member-dashboard' ) ), 404 );
		}

		global $wpdb;
		$submission = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE id=%d AND status='submitted'", $submission_id ) );
		if ( ! $submission ) {
			wp_send_json_error( array( 'message' => __( 'Submission not found.', 'ggm-member-dashboard' ) ), 404 );
		}
		if ( ! empty( $submission->reviewed_at ) ) {
			wp_send_json_error( array( 'message' => __( 'This registration has been reviewed and can no longer be changed.', 'ggm-member-dashboard' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$owns_submission = $user_id && (int) $submission->user_id === $user_id;
		if ( ! $owns_submission && ! self::verify_after_submit_token( $submission_id, $token ) ) {
			wp_send_json_error( array( 'message' => __( 'This update link has expired. Please refresh and try again.', 'ggm-member-dashboard' ) ), 403 );
		}

		$schema = self::effective_submission_schema( $submission );
		$fields = self::after_submit_fields( $schema );
		$form = self::get_form( (int) $submission->form_id );
		$settings = $form ? json_decode( (string) $form->settings_json, true ) : array();
		$settings = is_array( $settings ) ? $settings : array();
		if ( ! empty( $submission->additional_completed_at ) && empty( $settings['allow_additional_resubmission'] ) ) {
			$fields = array_values( array_filter( $fields, static function( $field ) { return ! empty( $field['allow_multiple_entries'] ); } ) );
		}
		if ( ! $fields ) {
			wp_send_json_error( array( 'message' => __( 'These additional details have already been saved and cannot be submitted again.', 'ggm-member-dashboard' ) ), 409 );
		}

		$posted = (array) ( $_POST['answers'] ?? array() );
		$new_answers = array();
		$file_fields = array();
		foreach ( $fields as $field ) {
			$key = $field['key'];
			if ( 'file_upload' === $field['type'] ) {
				$file_fields[ $key ] = $field;
				continue;
			}
			$clean = $this->validate_answer( $field, $posted[ $key ] ?? '' );
			if ( is_wp_error( $clean ) ) {
				wp_send_json_error( array( 'message' => $clean->get_error_message() ) );
			}
			$new_answers[ $key ] = $clean;
		}
		foreach ( $file_fields as $key => $field ) {
			$uploads = $this->uploaded_files( $key );
			$existing_file = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'ggm_form_files' ) . ' WHERE submission_id=%d AND field_key=%s', $submission_id, $key ) );
			if ( ! empty( $field['required'] ) && ! $uploads && ! $existing_file ) {
				wp_send_json_error( array( 'message' => sprintf( __( '%s is required.', 'ggm-member-dashboard' ), $field['label'] ) ) );
			}
			$file_error = $this->validate_files( $uploads, $field );
			if ( is_wp_error( $file_error ) ) {
				wp_send_json_error( array( 'message' => $file_error->get_error_message() ) );
			}
		}

		$answers = json_decode( (string) $submission->answers_json, true );
		$answers = is_array( $answers ) ? $answers : array();
		$history = isset( $answers['_ggm_additional_history'] ) && is_array( $answers['_ggm_additional_history'] ) ? $answers['_ggm_additional_history'] : array();
		if ( $new_answers ) {
			$history[] = array( 'saved_at' => current_time( 'mysql' ), 'answers' => $new_answers );
			$answers['_ggm_additional_history'] = $history;
		}
		$answers = array_merge( $answers, $new_answers );
		$updated = $wpdb->update(
			self::table( 'ggm_form_submissions' ),
			array( 'answers_json' => wp_json_encode( $answers ), 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $submission_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Additional details could not be saved.', 'ggm-member-dashboard' ) ), 500 );
		}

		foreach ( $file_fields as $key => $field ) {
			$file_result = $this->store_files( $submission_id, $key, $field );
			if ( is_wp_error( $file_result ) ) {
				wp_send_json_error( array( 'message' => $file_result->get_error_message() ), 500 );
			}
		}
		$completed = $wpdb->update( self::table( 'ggm_form_submissions' ), array( 'additional_completed_at'=>current_time( 'mysql' ), 'updated_at'=>current_time( 'mysql' ) ), array( 'id'=>$submission_id ), array( '%s','%s' ), array( '%d' ) );
		if ( false === $completed ) {
			wp_send_json_error( array( 'message' => __( 'The report was stored, but its completion status could not be saved. Please contact support.', 'ggm-member-dashboard' ) ), 500 );
		}

		delete_transient( 'ggm_after_submit_' . $submission_id );
		wp_send_json_success( array(
			'message'    => __( 'Medical reports saved.', 'ggm-member-dashboard' ),
			'files_html' => self::member_files_html( $submission_id ),
		) );
	}

	private function create_submission_from_request( $status ) {
		$form_id = absint( $_POST['form_id'] ?? 0 );
		$form = self::get_form( $form_id );
		if ( ! $form || 'published' !== $form->status ) { wp_send_json_error( array( 'message'=>__( 'This form is unavailable.', 'ggm-member-dashboard' ) ), 404 ); }
		$settings = json_decode( (string) $form->settings_json, true );
		$settings = is_array( $settings ) ? $settings : array();
		if ( ! is_user_logged_in() && empty( $settings['allow_guests'] ) ) { wp_send_json_error( array( 'message'=>__( 'Please log in again.', 'ggm-member-dashboard' ) ), 401 ); }
		$form_version = absint( $_POST['form_version'] ?? 0 );
		if ( ! $form_version ) { wp_send_json_error( array( 'message'=>__( 'This form has changed. Refresh the page and try again.', 'ggm-member-dashboard' ) ), 409 ); }
		if ( ! check_ajax_referer( 'ggm_submit_form_' . $form_id . '_' . $form_version, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your form session expired. Please refresh the page and try again.', 'ggm-member-dashboard' ) ), 403 );
		}
		$context = sanitize_key( $_POST['context_type'] ?? 'dashboard' );
		$context_id = absint( $_POST['context_id'] ?? 0 );
		if ( in_array( $context, array( 'dashboard','elementor_popup','shortcode' ), true ) ) { $context_id = 0; }
		if ( ! $this->context_allowed( $form_id, $context, $context_id ) ) { wp_send_json_error( array( 'message'=>__( 'This form is not assigned here.', 'ggm-member-dashboard' ) ), 403 ); }
		$user_id = get_current_user_id();
		$edit_submission_id = absint( $_POST['edit_submission_id'] ?? 0 );
		$rate_identity = $user_id ? 'user_' . $user_id : 'guest_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) . '|' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
		$rate_key = 'ggm_form_rate_' . $rate_identity . '_' . $form_id;
		if ( get_transient( $rate_key ) ) { wp_send_json_error( array( 'message'=>__( 'Please wait a few seconds before submitting again.', 'ggm-member-dashboard' ) ), 429 ); }
		set_transient( $rate_key, 1, 5 );
		if ( $user_id && 'workshop' === $context && ! ggm_user_has_workshop_access( $context_id, $user_id ) ) { wp_send_json_error( array( 'message'=>__( 'Workshop access is required.', 'ggm-member-dashboard' ) ), 403 ); }
		if ( $user_id && 'course' === $context && ! ggm_user_has_course_access( $context_id, $user_id ) ) { wp_send_json_error( array( 'message'=>__( 'Course access is required.', 'ggm-member-dashboard' ) ), 403 ); }
		global $wpdb;
		if ( $edit_submission_id ) {
			$editable = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE id=%d AND form_id=%d AND user_id=%d AND context_type=%s AND context_id=%d AND status='submitted'", $edit_submission_id, $form_id, $user_id, $context, $context_id ) );
			if ( ! $editable || ! empty( $editable->reviewed_at ) ) { wp_send_json_error( array( 'message'=>__( 'This registration can no longer be edited.', 'ggm-member-dashboard' ) ), 403 ); }
		}
		if ( $user_id && empty( $settings['allow_multiple'] ) && ! $edit_submission_id ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE form_id=%d AND user_id=%d AND context_type=%s AND context_id=%d AND status='submitted' LIMIT 1", $form_id, $user_id, $context, $context_id ) );
			if ( $exists ) { wp_send_json_error( array( 'message'=>__( 'You have already submitted this form.', 'ggm-member-dashboard' ) ) ); }
		}
		$schema = self::get_schema( $form, $form_version );
		if ( empty( $schema['sections'] ) ) { wp_send_json_error( array( 'message'=>__( 'This form has changed. Refresh the page and try again.', 'ggm-member-dashboard' ) ), 409 ); }
		$posted = (array) ( $_POST['answers'] ?? array() );
		$answers = array();
		$file_fields = array();
		$payment_first_order = 'pending_payment' === $status && 'before_form' === ( $settings['payment_flow'] ?? 'submit' );
		$guest_payment_token = '';
		if ( 'pending_payment' === $status && ! $user_id ) {
			$guest_payment_token = bin2hex( random_bytes( 32 ) );
			$answers['_ggm_guest_payment_token_hash'] = $this->guest_payment_token_hash( $guest_payment_token );
		}
		if ( ! $payment_first_order ) foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) foreach ( (array) $section['fields'] as $field ) {
			if ( ! empty( $field['show_after_submit'] ) ) { continue; }
			$key = $field['key']; $value = $posted[ $key ] ?? '';
			if ( 'file_upload' === $field['type'] ) { $file_fields[ $key ] = $field; continue; }
			$clean = $this->validate_answer( $field, $value );
			if ( is_wp_error( $clean ) ) { wp_send_json_error( array( 'message'=>$clean->get_error_message() ) ); }
			$answers[ $key ] = $clean;
		}
		foreach ( $file_fields as $key => $field ) {
			$uploads = $this->uploaded_files( $key );
			$existing_file = $edit_submission_id ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'ggm_form_files' ) . ' WHERE submission_id=%d AND field_key=%s', $edit_submission_id, $key ) ) : 0;
			if ( ! empty( $field['required'] ) && ! $uploads && ! $existing_file ) { wp_send_json_error( array( 'message'=>sprintf( __( '%s is required.', 'ggm-member-dashboard' ), $field['label'] ) ) ); }
			$file_error = $this->validate_files( $uploads, $field );
			if ( is_wp_error( $file_error ) ) { wp_send_json_error( array( 'message'=>$file_error->get_error_message() ) ); }
		}
		$now = current_time( 'mysql' );
		$submission_id = 0;
		if ( 'submitted' === $status && $edit_submission_id ) {
			$updated = $wpdb->update( self::table( 'ggm_form_submissions' ), array( 'answers_json'=>wp_json_encode( $answers ), 'schema_snapshot_json'=>wp_json_encode( $schema ), 'updated_at'=>$now ), array( 'id'=>$edit_submission_id, 'user_id'=>$user_id, 'status'=>'submitted' ) );
			if ( false === $updated ) { wp_send_json_error( array( 'message'=>__( 'The registration could not be updated.', 'ggm-member-dashboard' ) ), 500 ); }
			$submission_id = $edit_submission_id;
		}
		if ( 'submitted' === $status && ! $edit_submission_id && 'before_form' === ( $settings['payment_flow'] ?? 'submit' ) ) {
			$paid_submission = $user_id ? null : $this->guest_paid_submission( $form_id, $context, $context_id );
			$submission_id = $user_id
				? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE form_id=%d AND user_id=%d AND context_type=%s AND context_id=%d AND status='paid_awaiting_submission' ORDER BY id DESC LIMIT 1", $form_id, $user_id, $context, $context_id ) )
				: (int) ( $paid_submission->id ?? 0 );
			if ( $submission_id ) {
				$wpdb->update( self::table( 'ggm_form_submissions' ), array( 'answers_json'=>wp_json_encode( $answers ), 'schema_snapshot_json'=>wp_json_encode( $schema ), 'status'=>'submitted', 'submitted_at'=>$now, 'updated_at'=>$now ), array( 'id'=>$submission_id, 'status'=>'paid_awaiting_submission' ) );
				if ( ! $user_id ) { $this->clear_guest_payment_cookie( $form_id, $context, $context_id ); }
			}
			if ( ! $submission_id ) { wp_send_json_error( array( 'message'=>__( 'Your paid session could not be found. Please refresh the page or contact support before paying again.', 'ggm-member-dashboard' ) ), 403 ); }
		}
		if ( ! $submission_id ) {
			$wpdb->insert( self::table( 'ggm_form_submissions' ), array( 'form_id'=>$form_id, 'form_version'=>$form_version, 'user_id'=>$user_id, 'context_type'=>$context, 'context_id'=>$context_id, 'answers_json'=>wp_json_encode( $answers ), 'schema_snapshot_json'=>wp_json_encode( $schema ), 'status'=>sanitize_key( $status ), 'submitted_at'=>$now, 'updated_at'=>$now ) );
			$submission_id = (int) $wpdb->insert_id;
		}
		if ( ! $submission_id ) { wp_send_json_error( array( 'message'=>__( 'The response could not be saved.', 'ggm-member-dashboard' ) ) ); }
		foreach ( $file_fields as $key => $field ) {
			$file_result = $this->store_files( $submission_id, $key, $field );
			if ( is_wp_error( $file_result ) ) {
				if ( ! $edit_submission_id ) { $this->delete_failed_submission( $submission_id ); }
				wp_send_json_error( array( 'message'=>$file_result->get_error_message() ), 500 );
			}
		}

		return array(
			'form'          => $form,
			'settings'      => $settings,
			'schema'        => $schema,
			'submission_id' => $submission_id,
			'user_id'       => $user_id,
			'guest_payment_token' => $guest_payment_token,
		);
	}

	private function validate_answer( $field, $value ) {
		$field = self::hydrate_dynamic_field( $field );
		$empty = '' === $value || array() === $value;
		$message = ! empty( $field['validation_message'] ) ? $field['validation_message'] : '';
		if ( ! empty( $field['required'] ) && $empty ) { return new WP_Error( 'required', $message ?: sprintf( __( '%s is required.', 'ggm-member-dashboard' ), $field['label'] ) ); }
		if ( $empty && in_array( $field['type'], array( 'multiple_choice_grid','checkbox_grid' ), true ) && ! empty( $field['require_each_row'] ) ) { return new WP_Error( 'required', $message ?: sprintf( __( 'Complete every row in %s.', 'ggm-member-dashboard' ), $field['label'] ) ); }
		if ( $empty && 'checkboxes' === $field['type'] && absint( $field['selection_count'] ?? 0 ) && in_array( $field['selection_rule'] ?? '', array( 'at_least','exactly' ), true ) ) { return new WP_Error( 'selection_count', $message ?: __( 'The number of selected choices is not allowed.', 'ggm-member-dashboard' ) ); }
		if ( $empty && 'chip_selector' === $field['type'] && 'single' !== ( $field['selection_mode'] ?? 'multiple' ) && absint( $field['min_selections'] ?? 0 ) ) { return new WP_Error( 'chip_minimum', $message ?: __( 'Please select at least one option.', 'ggm-member-dashboard' ) ); }
		if ( $empty ) { return ''; }
		$array_value_allowed = in_array( $field['type'], array( 'checkboxes','chip_selector','search_select','multiple_choice_grid','checkbox_grid' ), true )
			|| ( 'time' === $field['type'] && ( 'duration' === ( $field['time_type'] ?? 'time_of_day' ) || '12' === (string) ( $field['time_format'] ?? '24' ) ) );
		if ( is_array( $value ) && ! $array_value_allowed ) { return new WP_Error( 'invalid', $message ?: __( 'An invalid response was submitted.', 'ggm-member-dashboard' ) ); }
		if ( 'time' === $field['type'] && ( 'duration' === ( $field['time_type'] ?? 'time_of_day' ) || '12' === (string) ( $field['time_format'] ?? '24' ) ) && ! is_array( $value ) ) {
			return new WP_Error( 'invalid', $message ?: __( 'An invalid time response was submitted.', 'ggm-member-dashboard' ) );
		}
		$valid_options = wp_list_pluck( (array) $field['options'], 'key' );
		if ( in_array( $field['type'], array( 'multiple_choice','dropdown' ), true ) ) {
			$value = sanitize_key( $value );
			if ( '__other__' === $value && ! empty( $field['allow_other'] ) ) {
				$other = sanitize_text_field( wp_unslash( $_POST['answers_other'][ $field['key'] ] ?? '' ) );
				return '' !== $other ? array( 'option'=>'__other__', 'other'=>$other ) : new WP_Error( 'invalid_other', $message ?: __( 'Enter the Other response.', 'ggm-member-dashboard' ) );
			}
			if ( ! in_array( $value, $valid_options, true ) ) { return new WP_Error( 'invalid', $message ?: __( 'An invalid option was submitted.', 'ggm-member-dashboard' ) ); }
			return $value;
		}
		if ( 'checkboxes' === $field['type'] ) {
			$values = array_map( 'sanitize_key', (array) $value );
			$other_text = '';
			if ( in_array( '__other__', $values, true ) ) {
				if ( empty( $field['allow_other'] ) ) { return new WP_Error( 'invalid', $message ?: __( 'An invalid checkbox option was submitted.', 'ggm-member-dashboard' ) ); }
				$other_text = sanitize_text_field( wp_unslash( $_POST['answers_other'][ $field['key'] ] ?? '' ) );
				if ( '' === $other_text ) { return new WP_Error( 'invalid_other', $message ?: __( 'Enter the Other response.', 'ggm-member-dashboard' ) ); }
			}
			if ( array_diff( $values, array_merge( $valid_options, array( '__other__' ) ) ) ) { return new WP_Error( 'invalid', $message ?: __( 'An invalid checkbox option was submitted.', 'ggm-member-dashboard' ) ); }
			$values = array_values( array_unique( $values ) );
			$count = count( $values ); $target = absint( $field['selection_count'] ?? 0 );
			if ( $target && ( ( 'at_least' === ( $field['selection_rule'] ?? '' ) && $count < $target ) || ( 'at_most' === ( $field['selection_rule'] ?? '' ) && $count > $target ) || ( 'exactly' === ( $field['selection_rule'] ?? '' ) && $count !== $target ) ) ) {
				return new WP_Error( 'selection_count', $message ?: __( 'The number of selected choices is not allowed.', 'ggm-member-dashboard' ) );
			}
			return $other_text ? array( 'options'=>$values, 'other'=>$other_text ) : $values;
		}
		if ( 'chip_selector' === $field['type'] ) {
			$values = array();
			$seen = array();
			foreach ( array_slice( (array) $value, 0, 101 ) as $item ) {
				$item = substr( sanitize_text_field( wp_unslash( $item ) ), 0, 150 );
				$item = trim( $item );
				$identity = strtolower( $item );
				if ( '' === $item || isset( $seen[ $identity ] ) ) { continue; }
				$seen[ $identity ] = true;
				$values[] = $item;
			}

			$allowed_labels = array();
			foreach ( (array) $field['options'] as $option ) {
				$label = trim( (string) ( $option['label'] ?? '' ) );
				if ( '' !== $label ) { $allowed_labels[ strtolower( $label ) ] = $label; }
			}

			// Only one "Other" chip is ever offered client-side (chip_selector
			// no longer supports free-typing unlimited new chips) — its
			// sentinel value arrives here as the literal string "__other__";
			// the real typed text travels in a separate answers_other[key]
			// field, exactly like checkboxes/multiple_choice already handle
			// their own Other option. Anything that's neither a known option
			// label nor that single Other sentinel is rejected outright, so a
			// hand-crafted request can't smuggle in more than one custom value.
			$other_used = false;
			foreach ( $values as $index => $item ) {
				$identity = strtolower( $item );
				if ( isset( $allowed_labels[ $identity ] ) ) {
					$values[ $index ] = $allowed_labels[ $identity ];
					continue;
				}
				if ( '__other__' === $item && ! empty( $field['allow_custom_values'] ) && ! $other_used ) {
					$other_text = sanitize_text_field( wp_unslash( $_POST['answers_other'][ $field['key'] ] ?? '' ) );
					$other_text = trim( substr( $other_text, 0, 150 ) );
					if ( '' === $other_text ) { return new WP_Error( 'invalid_other', $message ?: __( 'Enter the Other response.', 'ggm-member-dashboard' ) ); }
					$values[ $index ] = $other_text;
					$other_used = true;
					continue;
				}
				return new WP_Error( 'chip_invalid', $message ?: __( 'An invalid option was submitted.', 'ggm-member-dashboard' ) );
			}

			$count = count( $values );
			$mode = 'single' === ( $field['selection_mode'] ?? 'multiple' ) ? 'single' : 'multiple';
			$minimum = 'single' === $mode ? ( ! empty( $field['required'] ) ? 1 : 0 ) : max( ! empty( $field['required'] ) ? 1 : 0, absint( $field['min_selections'] ?? 0 ) );
			$maximum = 'single' === $mode ? 1 : absint( $field['max_selections'] ?? 0 );
			if ( $count < $minimum ) { return new WP_Error( 'chip_minimum', $message ?: __( 'Please select at least one option.', 'ggm-member-dashboard' ) ); }
			if ( $maximum && $count > $maximum ) { return new WP_Error( 'chip_maximum', $message ?: sprintf( __( 'You can select up to %d options.', 'ggm-member-dashboard' ), $maximum ) ); }
			return $values;
		}
		if ( 'search_select' === $field['type'] ) {
			$allowed_labels = array();
			foreach ( (array) $field['options'] as $option ) {
				$label = trim( sanitize_text_field( wp_strip_all_tags( (string) ( $option['label'] ?? '' ) ) ) );
				if ( '' !== $label ) {
					$allowed_labels[ strtolower( $label ) ] = $label;
				}
			}
			$values = array();
			$seen = array();
			foreach ( array_slice( (array) $value, 0, 101 ) as $item ) {
				$item = trim( substr( sanitize_text_field( wp_unslash( $item ) ), 0, 150 ) );
				$identity = strtolower( $item );
				if ( '' === $item || isset( $seen[ $identity ] ) ) { continue; }
				if ( ! isset( $allowed_labels[ $identity ] ) ) {
					return new WP_Error( 'search_select_invalid', $message ?: __( 'An invalid option was submitted.', 'ggm-member-dashboard' ) );
				}
				$seen[ $identity ] = true;
				$values[] = $allowed_labels[ $identity ];
			}
			$count = count( $values );
			$minimum = ! empty( $field['required'] ) ? 1 : 0;
			$maximum = max( 1, absint( $field['max_selections'] ?? 1 ) );
			if ( $count < $minimum ) { return new WP_Error( 'search_select_minimum', $message ?: __( 'Please select at least one option.', 'ggm-member-dashboard' ) ); }
			if ( $count > $maximum ) { return new WP_Error( 'search_select_maximum', $message ?: sprintf( __( 'You can select up to %d options.', 'ggm-member-dashboard' ), $maximum ) ); }
			return $values;
		}
		if ( in_array( $field['type'], array( 'multiple_choice_grid','checkbox_grid' ), true ) ) {
			$out = array(); $valid_rows = wp_list_pluck( (array) $field['rows'], 'key' );
			foreach ( (array) $value as $row => $chosen ) {
				$row = sanitize_key( $row ); if ( ! in_array( $row, $valid_rows, true ) ) { continue; }
				$choices = array_map( 'sanitize_key', (array) $chosen );
				if ( array_diff( $choices, $valid_options ) ) { return new WP_Error( 'invalid', $message ?: __( 'An invalid grid option was submitted.', 'ggm-member-dashboard' ) ); }
				$out[ $row ] = 'multiple_choice_grid' === $field['type'] ? reset( $choices ) : array_values( array_unique( $choices ) );
			}
			if ( ! empty( $field['require_each_row'] ) && count( $out ) < count( $valid_rows ) ) { return new WP_Error( 'required', $message ?: sprintf( __( 'Complete every row in %s.', 'ggm-member-dashboard' ), $field['label'] ) ); }
			if ( 'multiple_choice_grid' === $field['type'] && ! empty( $field['limit_one_per_column'] ) && count( array_unique( array_values( $out ) ) ) !== count( $out ) ) { return new WP_Error( 'grid_column', $message ?: __( 'Each column may be selected only once.', 'ggm-member-dashboard' ) ); }
			return $out;
		}
		if ( 'linear_scale' === $field['type'] ) {
			$validated = filter_var( $value, FILTER_VALIDATE_INT );
			if ( false === $validated ) { return new WP_Error( 'invalid', $message ?: __( 'An invalid scale value was submitted.', 'ggm-member-dashboard' ) ); }
			$n = (int) $validated; $min = isset( $field['min'] ) ? max( 0, min( 1, (int) $field['min'] ) ) : 1; $max = isset( $field['max'] ) ? max( 2, min( 10, (int) $field['max'] ) ) : 5;
			if ( $n < $min || $n > $max ) { return new WP_Error( 'invalid', $message ?: __( 'An invalid scale value was submitted.', 'ggm-member-dashboard' ) ); }
			return $n;
		}
		if ( 'date' === $field['type'] ) {
			$date = DateTime::createFromFormat( '!Y-m-d', (string) $value );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) { return new WP_Error( 'invalid', $message ?: __( 'Enter a valid date.', 'ggm-member-dashboard' ) ); }
			if ( ( ! empty( $field['min_date'] ) && $value < $field['min_date'] ) || ( ! empty( $field['max_date'] ) && $value > $field['max_date'] ) ) { return new WP_Error( 'date_range', $message ?: __( 'The date is outside the allowed range.', 'ggm-member-dashboard' ) ); }
			return $value;
		}
		if ( 'time' === $field['type'] ) {
			if ( 'duration' === ( $field['time_type'] ?? 'time_of_day' ) ) {
				$duration = array(); $total = 0;
				foreach ( (array) ( $field['duration_units'] ?? array( 'hours','minutes' ) ) as $unit ) {
					$n = absint( $value[ $unit ] ?? 0 );
					if ( in_array( $unit, array( 'minutes','seconds' ), true ) && $n > 59 ) { return new WP_Error( 'duration', $message ?: __( 'Minutes and seconds must be between 0 and 59.', 'ggm-member-dashboard' ) ); }
					$duration[ $unit ] = $n; $total += $n;
				}
				if ( ! empty( $field['required'] ) && 0 === $total ) { return new WP_Error( 'required', $message ?: sprintf( __( '%s is required.', 'ggm-member-dashboard' ), $field['label'] ) ); }
				return $duration;
			}
			if ( '12' === (string) ( $field['time_format'] ?? '24' ) && is_array( $value ) ) {
				$hour = absint( $value['hour'] ?? 0 ); $minute = absint( $value['minute'] ?? 99 ); $period = sanitize_key( $value['period'] ?? '' );
				if ( $hour < 1 || $hour > 12 || $minute > 59 || ! in_array( $period, array( 'am','pm' ), true ) ) { return new WP_Error( 'invalid', $message ?: __( 'Enter a valid time.', 'ggm-member-dashboard' ) ); }
				$hour24 = $hour % 12 + ( 'pm' === $period ? 12 : 0 );
				return sprintf( '%02d:%02d', $hour24, $minute );
			}
			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ) { return new WP_Error( 'invalid', $message ?: __( 'Enter a valid time.', 'ggm-member-dashboard' ) ); }
			return $value;
		}
		$validation = $field['validation_type'] ?? ( $field['subtype'] ?? 'none' );
		$text = 'paragraph' === $field['type'] ? sanitize_textarea_field( wp_unslash( $value ) ) : sanitize_text_field( wp_unslash( $value ) );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( ! empty( $field['min_length'] ) && $length < (int) $field['min_length'] ) { return new WP_Error( 'min_length', $message ?: __( 'The response is too short.', 'ggm-member-dashboard' ) ); }
		if ( ! empty( $field['max_length'] ) && $length > (int) $field['max_length'] ) { return new WP_Error( 'max_length', $message ?: __( 'The response is too long.', 'ggm-member-dashboard' ) ); }
		if ( 'email' === $validation ) {
			$email = sanitize_email( $text ); return is_email( $email ) ? $email : new WP_Error( 'invalid', $message ?: __( 'Enter a valid email address.', 'ggm-member-dashboard' ) );
		}
		if ( 'phone' === $validation || ( 'number' === $validation && ! empty( $field['phone_country_enabled'] ) ) ) {
			$dial = self::sanitize_country_dial( $_POST['answers_country_code'][ $field['key'] ] ?? ( $field['phone_country_code'] ?? '+91' ) );
			$digits = ggm_normalize_member_phone( wp_unslash( $value ), $dial );
			return '' !== $digits ? $dial . ' ' . $digits : new WP_Error( 'invalid', $message ?: __( 'Enter a valid phone number.', 'ggm-member-dashboard' ) );
		}
		if ( 'url' === $validation ) {
			$url = esc_url_raw( $text, array( 'http','https' ) ); return $url && wp_http_validate_url( $url ) ? $url : new WP_Error( 'invalid', $message ?: __( 'Enter a valid URL.', 'ggm-member-dashboard' ) );
		}
		if ( 'number' === $validation ) {
			if ( ! is_numeric( $value ) ) { return new WP_Error( 'invalid', $message ?: __( 'Enter a valid number.', 'ggm-member-dashboard' ) ); }
			$n = (float) $value; $a = $field['number_value'] ?? null; $b = $field['number_value_to'] ?? null; $rule = $field['number_rule'] ?? 'none'; $valid = true;
			if ( 'greater_than' === $rule ) { $valid = $n > $a; } elseif ( 'greater_or_equal' === $rule ) { $valid = $n >= $a; } elseif ( 'less_than' === $rule ) { $valid = $n < $a; } elseif ( 'less_or_equal' === $rule ) { $valid = $n <= $a; } elseif ( 'equal' === $rule ) { $valid = $n == $a; } elseif ( 'not_equal' === $rule ) { $valid = $n != $a; } elseif ( 'between' === $rule ) { $valid = $n >= min( $a, $b ) && $n <= max( $a, $b ); }
			return $valid ? $n : new WP_Error( 'number_rule', $message ?: __( 'The number does not meet the required condition.', 'ggm-member-dashboard' ) );
		}
		if ( 'regex' === $validation || ( 'paragraph' === $field['type'] && ! empty( $field['regex_pattern'] ) ) ) {
			$pattern = (string) ( $field['regex_pattern'] ?? '' );
			if ( ! $this->safe_regex_match( $pattern, $text ) ) { return new WP_Error( 'regex', $message ?: __( 'The response format is invalid.', 'ggm-member-dashboard' ) ); }
		}
		return $text;
	}

	private function safe_regex_match( $pattern, $value ) {
		if ( '' === $pattern || strlen( $pattern ) > 500 || false !== strpos( $pattern, '~' ) ) { return false; }
		return 1 === @preg_match( '~' . $pattern . '~u', $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	private function context_allowed( $form_id, $context, $context_id ) {
		foreach ( self::get_assignments( $form_id ) as $a ) {
			if ( 'dashboard' === $context && 0 === (int) $context_id && 'dashboard' === $a->location_type ) { return true; }
			if ( 'elementor_popup' === $context && 0 === (int) $context_id && 'elementor_popup' === $a->location_type ) { return true; }
			if ( 'shortcode' === $context && 0 === (int) $context_id && 'shortcode' === $a->location_type ) { return true; }
			if ( in_array( $context, array( 'workshop','course' ), true ) && ( $context . '_all' === $a->location_type || ( $context . '_specific' === $a->location_type && (int) $a->object_id === $context_id ) ) ) { return true; }
		}
		return false;
	}

	public static function format_answer( $field, $value ) {
		$options = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $index=>$option ) {
			$key = is_array( $option ) ? ( $option['key'] ?? $index ) : $option;
			$options[ $key ] = is_array( $option ) ? ( $option['label'] ?? $key ) : $option;
		}
		$rows = array();
		foreach ( (array) ( $field['rows'] ?? array() ) as $index=>$row ) {
			$key = is_array( $row ) ? ( $row['key'] ?? $index ) : $row;
			$rows[ $key ] = is_array( $row ) ? ( $row['label'] ?? $key ) : $row;
		}
		$type = $field['type'] ?? '';
		if ( in_array( $type, array( 'multiple_choice','dropdown' ), true ) ) {
			if ( is_array( $value ) && '__other__' === ( $value['option'] ?? '' ) ) { return __( 'Other: ', 'ggm-member-dashboard' ) . ( $value['other'] ?? '' ); }
			return $options[ $value ] ?? (string) $value;
		}
		if ( 'checkboxes' === $type ) {
			$other = is_array( $value ) && isset( $value['options'] ) ? ( $value['other'] ?? '' ) : '';
			$keys = is_array( $value ) && isset( $value['options'] ) ? $value['options'] : (array) $value;
			$labels = array();
			foreach ( $keys as $item ) { if ( '__other__' !== $item ) { $labels[] = $options[ $item ] ?? $item; } }
			if ( $other ) { $labels[] = __( 'Other: ', 'ggm-member-dashboard' ) . $other; }
			return implode( ', ', $labels );
		}
		if ( in_array( $type, array( 'chip_selector','search_select' ), true ) ) {
			return implode( ', ', array_map( 'sanitize_text_field', (array) $value ) );
		}
		if ( in_array( $type, array( 'multiple_choice_grid','checkbox_grid' ), true ) ) {
			$lines = array();
			foreach ( (array) $value as $row_key=>$selected ) {
				$labels = array();
				foreach ( (array) $selected as $option_key ) { $labels[] = $options[ $option_key ] ?? $option_key; }
				$lines[] = ( $rows[ $row_key ] ?? $row_key ) . ': ' . implode( ', ', $labels );
			}
			return implode( '; ', $lines );
		}
		if ( 'time' === $type && is_array( $value ) ) {
			$parts = array();
			foreach ( array( 'hours','minutes','seconds' ) as $unit ) { if ( isset( $value[ $unit ] ) ) { $parts[] = absint( $value[ $unit ] ) . ' ' . $unit; } }
			return implode( ' ', $parts );
		}
		if ( 'date' === $type && is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return mysql2date( get_option( 'date_format' ), $value . ' 00:00:00' );
		}
		if ( 'time' === $type && is_string( $value ) && preg_match( '/^\d{2}:\d{2}$/', $value ) ) {
			$timestamp = strtotime( '1970-01-01 ' . $value . ':00 UTC' );
			return $timestamp && '12' === (string) ( $field['time_format'] ?? '24' ) ? gmdate( 'g:i A', $timestamp ) : $value;
		}
		if ( 'linear_scale' === $type ) {
			$context = array_filter( array( $field['min_label'] ?? '', $field['max_label'] ?? '' ) );
			return (string) $value . ( $context ? ' (' . implode( ' — ', $context ) . ')' : '' );
		}
		return is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
	}

	private function uploaded_files( $key ) {
		$bucket = $_FILES['files'] ?? array();
		$names = $bucket['name'][ $key ] ?? array();
		if ( ! is_array( $names ) ) { $names = array( $names ); }
		$files = array();
		foreach ( $names as $i=>$name ) {
			if ( '' === (string) $name || UPLOAD_ERR_NO_FILE === (int) ( $bucket['error'][ $key ][ $i ] ?? UPLOAD_ERR_NO_FILE ) ) { continue; }
			$files[] = array(
				'name'=>sanitize_file_name( $name ),
				'type'=>sanitize_mime_type( $bucket['type'][ $key ][ $i ] ?? '' ),
				'tmp_name'=>$bucket['tmp_name'][ $key ][ $i ] ?? '',
				'error'=>(int) ( $bucket['error'][ $key ][ $i ] ?? UPLOAD_ERR_NO_FILE ),
				'size'=>(int) ( $bucket['size'][ $key ][ $i ] ?? 0 ),
			);
		}
		return $files;
	}

	private function validate_files( array $files, $field ) {
		if ( count( $files ) > max( 1, (int) ( $field['max_files'] ?? 1 ) ) ) { return new WP_Error( 'upload_count', sprintf( __( '%s has too many files.', 'ggm-member-dashboard' ), $field['label'] ) ); }
		$allowed = self::allowed_file_types( $field );
		if ( ! $allowed && $files ) { return new WP_Error( 'upload_type', __( 'No file types are enabled for this upload field.', 'ggm-member-dashboard' ) ); }
		$max_bytes = self::effective_file_mb( $field ) * MB_IN_BYTES;
		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== $file['error'] ) { return new WP_Error( 'upload', sprintf( __( '%s could not be uploaded.', 'ggm-member-dashboard' ), $field['label'] ) ); }
			if ( $file['size'] > $max_bytes ) { return new WP_Error( 'upload_size', sprintf( __( '%s exceeds the allowed file size.', 'ggm-member-dashboard' ), $field['label'] ) ); }
			$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
			if ( empty( $checked['ext'] ) || ! in_array( $checked['type'], array_values( $allowed ), true ) ) { return new WP_Error( 'upload_type', sprintf( __( '%s has an unsupported file type.', 'ggm-member-dashboard' ), $field['label'] ) ); }
		}
		return true;
	}

	private function store_files( $submission_id, $key, $field ) {
		$files = $this->uploaded_files( $key );
		if ( ! $files ) { return true; }
		$dir = WP_CONTENT_DIR . '/ggm-private-forms';
		if ( ! wp_mkdir_p( $dir ) ) { return new WP_Error( 'upload_storage', __( 'The private upload directory is unavailable. Please try again.', 'ggm-member-dashboard' ) ); }
		if ( ! file_exists( $dir . '/index.php' ) && false === file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'upload_storage', __( 'The private upload directory could not be protected.', 'ggm-member-dashboard' ) );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) && false === file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'upload_storage', __( 'The private upload directory could not be protected.', 'ggm-member-dashboard' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$allowed = self::allowed_file_types( $field );
		add_filter( 'upload_dir', array( $this, 'private_upload_dir' ) );
		global $wpdb;
		$error = null;
		foreach ( $files as $file ) {
			$original = $file['name'];
			$file['name'] = $submission_id . '-' . wp_generate_uuid4() . '-' . $original;
			$result = wp_handle_sideload( $file, array( 'test_form'=>false, 'mimes'=>$allowed ) );
			if ( ! empty( $result['error'] ) || empty( $result['file'] ) ) {
				$error = new WP_Error( 'upload_storage', sprintf( __( '%s could not be stored. Please try again.', 'ggm-member-dashboard' ), $field['label'] ) );
				break;
			}
			$stored = basename( $result['file'] );
			$inserted = $wpdb->insert( self::table( 'ggm_form_files' ), array( 'submission_id'=>$submission_id, 'field_key'=>$key, 'original_name'=>$original, 'stored_name'=>$stored, 'relative_path'=>$stored, 'mime_type'=>sanitize_mime_type( $result['type'] ), 'file_size'=>(int)$file['size'], 'created_at'=>current_time( 'mysql' ) ) );
			if ( false === $inserted ) {
				wp_delete_file( $result['file'] );
				$error = new WP_Error( 'upload_storage', sprintf( __( '%s could not be recorded. Please try again.', 'ggm-member-dashboard' ), $field['label'] ) );
				break;
			}
		}
		remove_filter( 'upload_dir', array( $this, 'private_upload_dir' ) );
		return $error ?: true;
	}

	private function delete_failed_submission( $submission_id ) {
		$this->delete_submission_record( $submission_id );
	}

	private function delete_submission_record( $submission_id ) {
		$submission_id = absint( $submission_id );
		if ( ! $submission_id ) { return false; }

		global $wpdb;
		$paths = $wpdb->get_col( $wpdb->prepare( 'SELECT relative_path FROM ' . self::table( 'ggm_form_files' ) . ' WHERE submission_id=%d', $submission_id ) );
		foreach ( (array) $paths as $relative_path ) {
			$path = WP_CONTENT_DIR . '/ggm-private-forms/' . basename( $relative_path );
			if ( is_file( $path ) ) { wp_delete_file( $path ); }
		}
		$wpdb->delete( self::table( 'ggm_form_files' ), array( 'submission_id'=>$submission_id ) );
		$wpdb->delete( self::table( 'ggm_form_payments' ), array( 'submission_id'=>$submission_id ) );
		return false !== $wpdb->delete( self::table( 'ggm_form_submissions' ), array( 'id'=>$submission_id ) );
	}

	public function delete_form_submission() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response'=>403 ) ); }

		$submission_id = absint( $_GET['submission_id'] ?? 0 );
		check_admin_referer( 'ggm_delete_form_submission_' . $submission_id );
		$redirect_args = array(
			'page' => 'ggm-health-intakes',
			'tab'  => 'submissions',
		);
		$filter_form_id = absint( $_GET['filter_form_id'] ?? 0 );
		if ( $filter_form_id ) { $redirect_args['form_id'] = $filter_form_id; }
		$submission_search = sanitize_text_field( wp_unslash( $_GET['submission_search'] ?? '' ) );
		if ( '' !== $submission_search ) { $redirect_args['submission_search'] = $submission_search; }
		$paged = max( 1, absint( $_GET['submissions_page'] ?? 1 ) );
		if ( $paged > 1 ) { $redirect_args['submissions_page'] = $paged; }

		global $wpdb;
		$submission = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE id=%d AND status='submitted'", $submission_id ) );
		if ( ! $submission ) {
			$redirect_args['submission_deleted'] = 'missing';
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) ); exit;
		}

		$delete_ids = array( $submission_id );
		if ( (int) $submission->user_id > 0 ) {
			$form = self::get_form( (int) $submission->form_id );
			$settings = $form ? json_decode( (string) $form->settings_json, true ) : array();
			$settings = is_array( $settings ) ? $settings : array();
			if ( empty( $settings['allow_multiple'] ) ) {
				$delete_ids = $wpdb->get_col(
					$wpdb->prepare(
						'SELECT id FROM ' . self::table( 'ggm_form_submissions' ) . " WHERE form_id=%d AND user_id=%d AND context_type=%s AND context_id=%d AND status='submitted'",
						(int) $submission->form_id,
						(int) $submission->user_id,
						(string) $submission->context_type,
						(int) $submission->context_id
					)
				);
				$delete_ids = $delete_ids ?: array( $submission_id );
			}
		}

		$deleted = true;
		foreach ( array_unique( array_map( 'absint', $delete_ids ) ) as $delete_id ) {
			$deleted = $this->delete_submission_record( $delete_id ) && $deleted;
		}

		$redirect_args['submission_deleted'] = $deleted ? '1' : '0';
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) ); exit;
	}

	public function private_upload_dir( $dirs ) {
		$dirs['path'] = WP_CONTENT_DIR . '/ggm-private-forms';
		$dirs['url'] = content_url( 'ggm-private-forms' );
		$dirs['subdir'] = '';
		$dirs['basedir'] = $dirs['path'];
		$dirs['baseurl'] = $dirs['url'];
		return $dirs;
	}

	public function review_form_submission() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response'=>403 ) ); }
		$submission_id = absint( $_GET['submission_id'] ?? 0 );
		$mode = 'reopen' === sanitize_key( $_GET['mode'] ?? '' ) ? 'reopen' : 'review';
		check_admin_referer( 'ggm_review_form_submission_' . $submission_id . '_' . $mode );
		global $wpdb;
		$data = 'review' === $mode ? array( 'reviewed_at'=>current_time( 'mysql' ), 'reviewed_by'=>get_current_user_id() ) : array( 'reviewed_at'=>null, 'reviewed_by'=>null );
		$wpdb->update( self::table( 'ggm_form_submissions' ), $data, array( 'id'=>$submission_id, 'status'=>'submitted' ) );
		wp_safe_redirect( add_query_arg( array( 'page'=>'ggm-health-intakes', 'tab'=>'submissions', 'review_updated'=>'1' ), admin_url( 'admin.php' ) ) ); exit;
	}

	public function download_file() {
		$file_id = absint( $_GET['file_id'] ?? 0 );
		$mode    = 'preview' === sanitize_key( $_GET['mode'] ?? '' ) ? 'preview' : 'download';
		$nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		$valid_nonce = wp_verify_nonce( $nonce, 'ggm_form_file_' . $file_id . '_' . $mode );
		if ( ! $valid_nonce && 'download' === $mode ) {
			$valid_nonce = wp_verify_nonce( $nonce, 'ggm_form_file_' . $file_id );
		}
		if ( ! $valid_nonce ) {
			wp_die( esc_html__( 'The file link has expired.', 'ggm-member-dashboard' ), '', array( 'response'=>403 ) );
		}
		global $wpdb; $file = $wpdb->get_row( $wpdb->prepare( 'SELECT f.*,s.user_id,s.form_id,s.schema_snapshot_json FROM ' . self::table( 'ggm_form_files' ) . ' f INNER JOIN ' . self::table( 'ggm_form_submissions' ) . ' s ON s.id=f.submission_id WHERE f.id=%d', $file_id ) );
		$field_visible = false;
		if ( $file ) {
			$current_form = self::get_form( (int) $file->form_id );
			$schema = $current_form ? self::get_schema( $current_form ) : json_decode( (string) $file->schema_snapshot_json, true );
			foreach ( (array) ( $schema['sections'] ?? array() ) as $section ) {
				foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
					if ( sanitize_key( $field['key'] ?? '' ) === sanitize_key( $file->field_key ) && 'file_upload' === ( $field['type'] ?? '' ) ) { $field_visible = ! empty( $field['show_uploaded_files_to_user'] ); break 2; }
				}
			}
		}
		$is_owner = $file && $field_visible && is_user_logged_in() && (int) $file->user_id === get_current_user_id();
		if ( ! current_user_can( 'manage_options' ) && ! $is_owner ) { wp_die( esc_html__( 'Access denied.', 'ggm-member-dashboard' ), '', array( 'response'=>403 ) ); }
		$path = $file ? WP_CONTENT_DIR . '/ggm-private-forms/' . basename( $file->relative_path ) : '';
		if ( ! $file || ! is_file( $path ) ) { wp_die( 'File not found.' ); }
		$mime = sanitize_mime_type( $file->mime_type );
		$inline_mimes = array( 'application/pdf', 'image/jpeg', 'image/png' );
		if ( 'preview' === $mode && ! in_array( $mime, $inline_mimes, true ) ) { wp_die( esc_html__( 'This file type cannot be previewed safely. Please download it instead.', 'ggm-member-dashboard' ), '', array( 'response'=>415 ) ); }
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox" );
		header( 'Content-Type: ' . ( $mime ?: 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . ( 'preview' === $mode ? 'inline' : 'attachment' ) . '; filename="' . sanitize_file_name( $file->original_name ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); exit; // phpcs:ignore
	}
}
