<?php
/** Portable single-workshop package with locally hosted media. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class GGM_Workshop_Package {
	const TYPE = 'ggm_workshop_package';
	public static function title( $file ) {
		if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'ggm_package_zip', __( 'ZIP support is unavailable.', 'ggm-member-dashboard' ) ); }
		$zip = new ZipArchive(); if ( true !== $zip->open( $file ) ) { return new WP_Error( 'ggm_package_open', __( 'The package could not be opened.', 'ggm-member-dashboard' ) ); }
		$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true ); $zip->close();
		$title = is_array( $manifest ) && self::TYPE === ( $manifest['type'] ?? '' ) ? sanitize_text_field( $manifest['post']['title'] ?? '' ) : '';
		return $title ?: new WP_Error( 'ggm_package_invalid', __( 'This is not a valid Workshop package.', 'ggm-member-dashboard' ) );
	}
	public static function export( $id, $file ) {
		$post = get_post( $id ); if ( ! $post || ! in_array( $post->post_type, array( 'workshop','ggm_workshop' ), true ) || ! class_exists( 'ZipArchive' ) ) return new WP_Error( 'ggm_package_export', __( 'Workshop or ZIP support is unavailable.', 'ggm-member-dashboard' ) );
		$zip = new ZipArchive(); if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) return new WP_Error( 'ggm_package_zip', __( 'The package could not be created.', 'ggm-member-dashboard' ) );
		$ids = array_filter( array_unique( array_merge( array( get_post_thumbnail_id( $id ), absint( get_post_meta( $id, 'ggm_workshop_bottom_image_id', true ) ) ), wp_list_pluck( get_attached_media( '', $id ), 'ID' ), self::media_ids( array( $post->post_content, $post->post_excerpt, get_post_meta( $id ) ) ) ) ) );
		$media = array(); foreach ( $ids as $aid ) { $path = get_attached_file( $aid ); $url = wp_get_attachment_url( $aid ); if ( $path && is_readable( $path ) && $url ) { $entry = 'media/' . $aid . '-' . sanitize_file_name( basename( $path ) ); $zip->addFile( $path, $entry ); $media[] = array( 'id'=>(int)$aid,'url'=>$url,'file'=>$entry,'title'=>get_the_title($aid),'caption'=>get_post_field('post_excerpt',$aid),'alt'=>get_post_meta($aid,'_wp_attachment_image_alt',true) ); } }
		$terms=array(); foreach(get_object_taxonomies($post->post_type) as $tax){$terms[$tax]=wp_get_object_terms($id,$tax,array('fields'=>'names'));}
		$slots=array(); if(class_exists('GGM_Workshop_Slot')) foreach(GGM_Workshop_Slot::get_for_workshop($id,'all') as $s){$slots[]=array('slot_type'=>$s->slot_type,'start_time'=>$s->start_time,'end_time'=>$s->end_time,'meeting_link'=>$s->meeting_link,'sort_order'=>$s->sort_order,'status'=>$s->status);}
		$manifest=array('type'=>self::TYPE,'version'=>1,'post'=>array('post_type'=>$post->post_type,'title'=>$post->post_title,'slug'=>$post->post_name,'status'=>$post->post_status,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'menu_order'=>$post->menu_order),'meta'=>get_post_meta($id),'terms'=>$terms,'slots'=>$slots,'featured_id'=>(int)get_post_thumbnail_id($id),'media'=>$media);
		$zip->addFromString('manifest.json',wp_json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); $zip->close(); return true;
	}
	private static function media_ids($value){$ids=array(); if(is_array($value)){foreach($value as $v)$ids=array_merge($ids,self::media_ids($v));}elseif(is_string($value)){if(preg_match_all('~https?://[^\\s"\'<>]+~',$value,$m))foreach($m[0] as $u){$id=attachment_url_to_postid($u);if($id)$ids[]=$id;}} return $ids;}
	public static function import( $file, $target_post_id = 0 ) {
		if(!class_exists('ZipArchive'))return new WP_Error('ggm_package_zip',__('ZIP support is unavailable.','ggm-member-dashboard')); $zip=new ZipArchive();if(true!==$zip->open($file))return new WP_Error('ggm_package_open',__('The package could not be opened.','ggm-member-dashboard'));$m=json_decode((string)$zip->getFromName('manifest.json'),true);if(!is_array($m)||self::TYPE!==($m['type']??'')){ $zip->close();return new WP_Error('ggm_package_invalid',__('This is not a valid Workshop package.','ggm-member-dashboard'));}
		$p=$m['post']??array();$postarr=array('post_type'=>in_array($p['post_type']??'',array('workshop','ggm_workshop'),true)?$p['post_type']:'workshop','post_title'=>sanitize_text_field($p['title']??''),'post_name'=>sanitize_title($p['slug']??''),'post_status'=>in_array($p['status']??'',array('publish','draft','pending','private'),true)?$p['status']:'draft','post_content'=>wp_kses_post($p['content']??''),'post_excerpt'=>sanitize_textarea_field($p['excerpt']??''),'menu_order'=>absint($p['menu_order']??0));$target=get_post($target_post_id);$id=($target&&in_array($target->post_type,array('workshop','ggm_workshop'),true))?wp_update_post(array_merge($postarr,array('ID'=>$target->ID)),true):wp_insert_post($postarr,true);if(is_wp_error($id)){ $zip->close();return $id;}if($target){foreach(array_keys(get_post_meta($id))as$key){if(!in_array($key,array('_edit_lock','_edit_last'),true))delete_post_meta($id,$key);}if(class_exists('GGM_Workshop_Slot'))GGM_Workshop_Slot::delete_missing($id,array());}
		require_once ABSPATH.'wp-admin/includes/image.php';$map=array();foreach((array)($m['media']??array()) as $a){$entry=(string)($a['file']??'');if(0!==strpos($entry,'media/')||!($s=$zip->getStream($entry)))continue;$bits=stream_get_contents($s);fclose($s);$up=wp_upload_bits(sanitize_file_name(basename($entry)),null,$bits);if($up['error'])continue;$aid=wp_insert_attachment(array('post_mime_type'=>wp_check_filetype($up['file'])['type'],'post_title'=>sanitize_text_field($a['title']??''),'post_excerpt'=>sanitize_textarea_field($a['caption']??''),'post_status'=>'inherit'),$up['file'],$id);if(!is_wp_error($aid)){wp_update_attachment_metadata($aid,wp_generate_attachment_metadata($aid,$up['file']));update_post_meta($aid,'_wp_attachment_image_alt',sanitize_text_field($a['alt']??''));$map[$a['url']]=$up['url'];$map['id:'.(int)$a['id']]=$aid;}}
		foreach ( (array) ( $m['meta'] ?? array() ) as $k => $values ) {
			if ( in_array( $k, array( '_edit_lock', '_edit_last' ), true ) ) {
				continue;
			}
			if ( 'ggm_workshop_bottom_image_id' === $k ) {
				$bottom_image_values    = (array) $values;
				$source_bottom_image_id = absint( maybe_unserialize( reset( $bottom_image_values ) ) );
				$mapped_bottom_image_id = absint( $map[ 'id:' . $source_bottom_image_id ] ?? 0 );
				if ( $mapped_bottom_image_id && wp_attachment_is_image( $mapped_bottom_image_id ) ) {
					update_post_meta( $id, $k, $mapped_bottom_image_id );
				}
				continue;
			}
			foreach ( (array) $values as $v ) {
				add_post_meta( $id, $k, self::replace( $v, $map ) );
			}
		}
		if(!empty($map['id:'.(int)($m['featured_id']??0)]))set_post_thumbnail($id,$map['id:'.(int)$m['featured_id']]);foreach((array)($m['terms']??array()) as $tax=>$names)if(taxonomy_exists($tax))wp_set_object_terms($id,array_map('sanitize_text_field',(array)$names),$tax);if(class_exists('GGM_Workshop_Slot'))foreach((array)($m['slots']??array())as$s)GGM_Workshop_Slot::create(array_merge($s,array('workshop_id'=>$id)));$zip->close();return $id;
	}
	private static function replace($v,$map){if(is_array($v)){foreach($v as $k=>$x)$v[$k]=self::replace($x,$map);return $v;}return is_string($v)?strtr($v,$map):$v;}
}
