<?php
/** Global, admin-only Workshop editor configuration. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GGM_Workshop_Admin_Config {
	const SETTING_KEY = 'ggm_workshop_admin_configuration';
	public static function sections() {
		return array(
			'workshop_details'=>__( 'Workshop Details', 'ggm-member-dashboard' ), 'booking_card'=>__( 'Booking Card', 'ggm-member-dashboard' ), 'form_text'=>__( 'Form Text', 'ggm-member-dashboard' ), 'time_slots'=>__( 'Workshop Time Slots', 'ggm-member-dashboard' ), 'additional_layout'=>__( 'Additional Block Layout', 'ggm-member-dashboard' ), 'discover'=>__( 'You Will Discover', 'ggm-member-dashboard' ), 'why_different'=>__( 'Why This Webinar Is Different', 'ggm-member-dashboard' ), 'why_workshop_different'=>__( 'Why this Workshop is Different', 'ggm-member-dashboard' ), 'journey'=>__( 'Workshop Journey', 'ggm-member-dashboard' ), 'perfect_for'=>__( 'Perfect For You', 'ggm-member-dashboard' ), 'faq'=>__( 'FAQ', 'ggm-member-dashboard' ),
		);
	}
	public static function fields() {
		return array(
			'workshop_details'=>array('mentors'=>__( 'Mentors', 'ggm-member-dashboard' ),'linked_course'=>__( 'Linked Course', 'ggm-member-dashboard' ),'countdown'=>__( 'Counter Start Date & Time', 'ggm-member-dashboard' ),'preparatory_date'=>__( 'Preparatory Date', 'ggm-member-dashboard' ),'date_range'=>__( 'Workshop Date Range', 'ggm-member-dashboard' ),'pricing'=>__( 'Pricing & Contribution', 'ggm-member-dashboard' ),'mode'=>__( 'Workshop Mode', 'ggm-member-dashboard' ),'header_pill'=>__( 'Header Pill Text', 'ggm-member-dashboard' ),'whatsapp'=>__( 'WhatsApp Group Link', 'ggm-member-dashboard' ),'language'=>__( 'Workshop Language', 'ggm-member-dashboard' ),'duration'=>__( 'Label Duration', 'ggm-member-dashboard' ),'is_free'=>__( 'Is Free', 'ggm-member-dashboard' ),'featured_video'=>__( 'Featured YouTube Video URL', 'ggm-member-dashboard' ),'bottom_image'=>__( 'Bottom Image', 'ggm-member-dashboard' )),
			'booking_card'=>array('hero'=>__( 'Booking Card Hero', 'ggm-member-dashboard' ),'cta'=>__( 'Booking Card CTA', 'ggm-member-dashboard' ),'social_proof'=>__( 'Booking Card Social Proof', 'ggm-member-dashboard' )),
			'form_text'=>array('heading'=>__( 'Heading', 'ggm-member-dashboard' ),'content'=>__( 'Content', 'ggm-member-dashboard' )),
			'time_slots'=>array('slots'=>__( 'Time Slot Editor', 'ggm-member-dashboard' )), 'additional_layout'=>array('layout'=>__( 'Columns and Rows', 'ggm-member-dashboard' )), 'discover'=>array('content'=>__( 'Discover Content', 'ggm-member-dashboard' )), 'why_different'=>array('content'=>__( 'Why Different Content', 'ggm-member-dashboard' )), 'why_workshop_different'=>array('content'=>__( 'Why Workshop Content', 'ggm-member-dashboard' )), 'journey'=>array('content'=>__( 'Journey Content', 'ggm-member-dashboard' )), 'perfect_for'=>array('content'=>__( 'Perfect For Content', 'ggm-member-dashboard' )), 'faq'=>array('content'=>__( 'FAQ Content', 'ggm-member-dashboard' )),
		);
	}
	private static function rows($items){$out=array();foreach(array_keys($items) as $id){$out[]=array('id'=>$id,'enabled'=>true);}return $out;}
	public static function defaults(){ $fields=array(); foreach(self::fields() as $section=>$items){$fields[$section]=self::rows($items);} return array('version'=>1,'sections'=>self::rows(self::sections()),'fields'=>$fields); }
	public static function normalise($raw){$raw=is_array($raw)?$raw:array();$default=self::defaults();$out=array('version'=>1,'sections'=>self::normalise_rows($raw['sections']??array(),self::sections(),$default['sections']),'fields'=>array());foreach(self::fields() as $section=>$items){$out['fields'][$section]=self::normalise_rows($raw['fields'][$section]??array(),$items,$default['fields'][$section]);}return $out;}
	private static function normalise_rows($rows,$allowed,$defaults){$out=array();$seen=array();foreach(is_array($rows)?$rows:array() as $row){$id=is_array($row)?sanitize_key($row['id']??''):sanitize_key($row);if(!isset($allowed[$id])||isset($seen[$id]))continue;$seen[$id]=true;$out[]=array('id'=>$id,'enabled'=>is_array($row)?!empty($row['enabled']):true);}foreach($defaults as $row){if(!isset($seen[$row['id']]))$out[]=$row;}return $out;}
	public static function sanitise_submitted($raw){return self::normalise($raw);}
	public static function get(){static $config=null;if(null===$config){$config=self::normalise(ggm_get_setting(self::SETTING_KEY,array()));}return $config;}
}
