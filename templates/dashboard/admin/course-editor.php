<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="ggm-management-editor ggm-course-editor" data-editor="course">
	<button class="ggm-btn" data-ggm-back="courses"><?php esc_html_e( 'Back to Courses', 'ggm-member-dashboard' ); ?></button>
	<h2 data-ggm-editor-title><?php esc_html_e( 'Create Course', 'ggm-member-dashboard' ); ?></h2>
	<div class="ggm-management-notice" aria-live="polite"></div>
	<form data-ggm-editor-form>
		<input type="hidden" name="id">
		<label><?php esc_html_e( 'Title', 'ggm-member-dashboard' ); ?><input required name="title"></label>
		<label for="ggm-dashboard-course-content"><?php esc_html_e( 'Content', 'ggm-member-dashboard' ); ?></label><?php wp_editor( '', 'ggm-dashboard-course-content', array( 'textarea_name' => 'content', 'textarea_rows' => 10, 'media_buttons' => true, 'teeny' => false, 'quicktags' => true ) ); ?>
		<details open><summary><?php esc_html_e( 'Course details', 'ggm-member-dashboard' ); ?></summary>
			<label><?php esc_html_e( 'Price', 'ggm-member-dashboard' ); ?><input name="course_price" inputmode="decimal"></label>
			<label><?php esc_html_e( 'Short description', 'ggm-member-dashboard' ); ?><textarea name="course_short_description" rows="3"></textarea></label>
			<label><?php esc_html_e( 'Language', 'ggm-member-dashboard' ); ?><select name="course_language"><option value="english"><?php esc_html_e( 'English', 'ggm-member-dashboard' ); ?></option><option value="hindi"><?php esc_html_e( 'Hindi', 'ggm-member-dashboard' ); ?></option></select></label>
			<label><?php esc_html_e( 'Course duration', 'ggm-member-dashboard' ); ?><input name="course_duration"></label>
			<label><?php esc_html_e( 'Course instructor', 'ggm-member-dashboard' ); ?><input name="course_instructor"></label>
		</details>
		<details open><summary><?php esc_html_e( 'Featured image', 'ggm-member-dashboard' ); ?></summary>
			<input type="hidden" name="featured_image_id">
			<div class="ggm-course-image-preview" data-ggm-course-image-preview></div>
			<p><button type="button" class="ggm-btn" data-ggm-course-image-select><?php esc_html_e( 'Choose image', 'ggm-member-dashboard' ); ?></button> <button type="button" class="ggm-btn" data-ggm-course-image-remove><?php esc_html_e( 'Remove image', 'ggm-member-dashboard' ); ?></button></p>
		</details>
		<details open><summary><?php esc_html_e( 'Lessons', 'ggm-member-dashboard' ); ?></summary>
			<p class="description"><?php esc_html_e( 'Lessons are saved as the existing lesson posts attached to this course. Removing a lesson here detaches it; it does not delete the lesson or learner progress.', 'ggm-member-dashboard' ); ?></p>
			<div data-ggm-course-lessons></div>
			<p><button type="button" class="ggm-btn" data-ggm-add-lesson><?php esc_html_e( 'Add lesson', 'ggm-member-dashboard' ); ?></button></p>
		</details>
		<div class="ggm-management-actions"><button class="ggm-btn" name="status" value="draft"><?php esc_html_e( 'Save Draft', 'ggm-member-dashboard' ); ?></button><button class="ggm-btn ggm-btn-primary" name="status" value="publish"><?php esc_html_e( 'Publish', 'ggm-member-dashboard' ); ?></button></div>
	</form>
</div>
