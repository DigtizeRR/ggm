/* global jQuery, ggmDashboardManagement, wp, tinymce */
(function ($) {
	'use strict';
	if (!window.ggmDashboardManagement) return;

	var api = window.ggmDashboardManagement;
	var editorId = 'ggm-dashboard-disease-description';
	var currentPage = 1;

	function request(action, data) {
		data = data || {};
		data.action = 'ggm_dashboard_management_' + action;
		data.nonce = api.nonce;
		return $.post(api.ajaxUrl, data);
	}

	function manager() { return $('[data-ggm-disease-manager]').first(); }
	function editor() { return $('[data-ggm-disease-editor]').first(); }
	function notice($target, message, error) {
		$target.text(message || '').toggleClass('ggm-management-error', !!error);
	}
	function activate(tab) {
		$('#tab-admin-' + tab).addClass('active').siblings('.ggm-tab').removeClass('active');
	}
	function removeEditor() {
		try { if (window.wp && wp.editor && document.getElementById(editorId)) wp.editor.remove(editorId); } catch (ignore) {}
		try { if (window.tinymce && tinymce.get(editorId)) tinymce.remove(tinymce.get(editorId)); } catch (ignoreTwo) {}
	}
	function setEditorValue(value) {
		var $area = $('#' + editorId);
		$area.val(value || '');
		if (window.tinymce && tinymce.get(editorId)) tinymce.get(editorId).setContent(value || '');
	}
	function ensureEditor(value) {
		var $area = $('#' + editorId);
		if (!$area.length) return;
		setEditorValue(value);
		if (window.tinymce && tinymce.get(editorId)) return;
		if (!(window.wp && wp.editor && wp.editor.initialize)) return;
		wp.editor.initialize(editorId, { tinymce: true, quicktags: true });
		window.setTimeout(function () { setEditorValue(value); }, 0);
	}
	function editorValue() {
		try { if (window.tinymce && tinymce.get(editorId)) { tinymce.triggerSave(); return tinymce.get(editorId).getContent(); } } catch (ignore) {}
		return $('#' + editorId).val() || '';
	}
	function button(text, attrs) {
		return $('<button>', $.extend({ type: 'button', 'class': 'ggm-btn' }, attrs || {})).text(text);
	}
	function render(data) {
		var $out = manager().find('[data-ggm-disease-results]').empty();
		if (!data.items || !data.items.length) {
			$out.append($('<p>').text(data.empty_message || 'No diseases found.'));
			return;
		}
		var $table = $('<table>');
		var $head = $('<thead>').append($('<tr>')
			.append($('<th>', { scope: 'col' }).text('Title'))
			.append($('<th>', { scope: 'col' }).text('Description'))
			.append($('<th>', { scope: 'col' }).text('Updated'))
			.append($('<th>', { scope: 'col' }).text('')));
		var $body = $('<tbody>');
		$.each(data.items, function (_, item) {
			var $edit = button('Edit', { 'data-ggm-disease-edit': item.id }).data('disease', item);
			$body.append($('<tr>')
				.append($('<td>').text(item.title || ''))
				.append($('<td>').text(item.excerpt || ''))
				.append($('<td>').text(item.updated || ''))
				.append($('<td>').append($edit)));
		});
		$out.append($table.append($head).append($body));
		if (data.pages > 1) {
			var $pages = $('<div>', { 'class': 'ggm-management-pagination' });
			if (data.page > 1) $pages.append(button('Previous', { 'data-ggm-disease-page': data.page - 1 }));
			$pages.append($('<span>').text('Page ' + data.page + ' of ' + data.pages));
			if (data.page < data.pages) $pages.append(button('Next', { 'data-ggm-disease-page': data.page + 1 }));
			$out.append($pages);
		}
	}
	function load(page) {
		var $manager = manager();
		if (!$manager.length) return;
		currentPage = page || 1;
		var $out = $manager.find('[data-ggm-disease-results]');
		notice($manager.find('[data-ggm-disease-list-notice]'), '', false);
		$out.empty().append($('<p>').text('Loading…'));
		request('diseases', { page: currentPage, search: $manager.find('[data-ggm-disease-search]').val() || '' })
			.done(function (response) {
				if (!response.success) { $out.empty().append($('<p>', { 'class': 'ggm-management-error' }).text((response.data && response.data.message) || 'Could not load diseases.')); return; }
				render(response.data);
			})
			.fail(function (xhr) { $out.empty().append($('<p>', { 'class': 'ggm-management-error' }).text((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Could not load diseases.')); });
	}
	function openEditor(item) {
		var $editor = editor();
		var form = $editor.find('form')[0];
		if (!form) return;
		form.reset();
		$editor.find('[name=id]').val(item && item.id ? item.id : '');
		$editor.find('[name=title]').val(item && item.title ? item.title : '');
		$editor.find('[data-ggm-disease-editor-title]').text(item && item.id ? 'Edit Disease' : 'Add Disease');
		$editor.find('[data-ggm-disease-submit]').text(item && item.id ? 'Save Disease' : 'Create Disease').prop('disabled', false);
		notice($editor.find('[data-ggm-disease-editor-notice]'), '', false);
		activate('disease-editor');
		ensureEditor(item && item.description ? item.description : '');
	}
	function backToList(message, clearSearch) {
		removeEditor();
		activate('diseases');
		if (clearSearch) manager().find('[data-ggm-disease-search]').val('');
		if (message) notice(manager().find('[data-ggm-disease-list-notice]'), message, false);
		load(1);
	}

	$(document)
		.on('click', '.ggm-dash-nav-item[data-tab="admin-diseases"]', function () { load(1); })
		.on('click', '[data-ggm-disease-create]', function () { openEditor(null); })
		.on('click', '[data-ggm-disease-search-button]', function () { load(1); })
		.on('keydown', '[data-ggm-disease-search]', function (event) { if (event.key === 'Enter') { event.preventDefault(); load(1); } })
		.on('click', '[data-ggm-disease-page]', function () { load(parseInt($(this).attr('data-ggm-disease-page'), 10) || 1); })
		.on('click', '[data-ggm-disease-edit]', function () { openEditor($(this).data('disease') || null); })
		.on('click', '[data-ggm-disease-back], [data-ggm-disease-cancel]', function () { backToList(); })
		.on('submit', '[data-ggm-disease-form]', function (event) {
			event.preventDefault();
			var $form = $(this), form = this, $editor = editor(), $submit = $editor.find('[data-ggm-disease-submit]');
			if (form.reportValidity && !form.reportValidity()) return;
			$submit.prop('disabled', true);
			notice($editor.find('[data-ggm-disease-editor-notice]'), '', false);
			request('disease_save', { id: $form.find('[name=id]').val() || 0, title: $form.find('[name=title]').val() || '', description: editorValue() })
				.done(function (response) {
					if (!response.success) { notice($editor.find('[data-ggm-disease-editor-notice]'), (response.data && response.data.message) || 'Could not save Disease.', true); return; }
					backToList(response.data.message || 'Disease saved successfully.', !$form.find('[name=id]').val());
				})
				.fail(function (xhr) { notice($editor.find('[data-ggm-disease-editor-notice]'), (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Could not save Disease.', true); })
				.always(function () { $submit.prop('disabled', false); });
		});
})(jQuery);
