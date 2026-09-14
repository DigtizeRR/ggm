<?php
/** Static contract tests for the structured Workshop Dashboard editor. */
$root=dirname(__DIR__);$assert=static function($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}echo "PASS: {$message}\n";};
$template=file_get_contents($root.'/templates/dashboard/admin/workshop-editor.php');$schema=file_get_contents($root.'/templates/dashboard/admin/partials/workshop-editor-schema.php');$js=file_get_contents($root.'/assets/js/ggm-dashboard-workshop-editor.js');$controller=file_get_contents($root.'/modules/dashboard/class-ggm-dashboard-management.php');$service=file_get_contents($root.'/modules/workshop/class-ggm-workshop-data-service.php');
$assert(false===stripos($template,'excerpt')&&false===stripos($schema,'excerpt'),'Excerpt is absent from the Workshop dashboard markup.');
$assert(false===stripos($template,'JSON')&&false===stripos($schema,'JSON'),'Workshop dashboard markup exposes no raw JSON editor.');
$assert(false===strpos($controller,"'post_excerpt'")&&false===strpos($controller,'post_excerpt'),'Dashboard Workshop save does not update post_excerpt.');
$assert(false===strpos($service,'canonical_meta'),'Canonical-meta escape hatch is absent from the Workshop service.');
foreach(array('Basic Information','Workshop Details','Pricing','Booking Card','Form Text','Workshop Time Slots','Additional Block Layout','You Will Discover','Why Different','Why This Workshop Is Different','Workshop Journey','Perfect For You','FAQ')as$section)$assert(false!==strpos($schema,$section),"Section present: {$section}.");
foreach(array('GGM_Workshop_Slot','mentor_ids','linked_course_id','ggm_contribution_options','ggm_workshop_booking_card_hero_items','ggm_workshop_journey_footer_items','ggm_faq')as$key)$assert(false!==strpos($service,$key),"Canonical field/service coverage: {$key}.");
$assert(false!==strpos($js,'data-ggm-add-slot')||false!==strpos($js,'data-ggm-add-slot'.''),'Slot editor action is present.');
echo "Workshop dashboard editor static parity contracts passed.\n";
