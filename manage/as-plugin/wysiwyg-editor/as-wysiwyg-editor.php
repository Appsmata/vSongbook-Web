<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/wysiwyg-editor/as-wysiwyg-editor.php
	Description: Editor module class for WYSIWYG editor plugin


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this online
*/


class as_wysiwyg_editor
{
	private $urltoroot;

	public function load_module($directory, $urltoroot)
	{
		$this->urltoroot = $urltoroot;
	}

	public function option_default($option)
	{
		if ($option == 'wysiwyg_editor_upload_max_size') {
			require_once AS_INCLUDE_DIR.'app/upload.php';

			return min(as_get_max_upload_size(), 1048576);
		}
	}

	public function admin_form(&$as_content)
	{
		require_once AS_INCLUDE_DIR.'app/upload.php';

		$saved = false;

		if (as_clicked('wysiwyg_editor_save_button')) {
			as_opt('wysiwyg_editor_upload_images', (int)as_post_text('wysiwyg_editor_upload_images_field'));
			as_opt('wysiwyg_editor_upload_all', (int)as_post_text('wysiwyg_editor_upload_all_field'));
			as_opt('wysiwyg_editor_upload_max_size', min(as_get_max_upload_size(), 1048576*(float)as_post_text('wysiwyg_editor_upload_max_size_field')));
			$saved = true;
		}

		as_set_display_rules($as_content, array(
			'wysiwyg_editor_upload_all_display' => 'wysiwyg_editor_upload_images_field',
			'wysiwyg_editor_upload_max_size_display' => 'wysiwyg_editor_upload_images_field',
		));

		// handle AJAX requests to 'wysiwyg-editor-ajax'
		$js = array(
			'function wysiwyg_editor_ajax(totalEdited) {',
			'	$.ajax({',
			'		url: ' . as_js(as_path('wysiwyg-editor-ajax')) . ',',
			'		success: function(response) {',
			'			var postsEdited = parseInt(response, 10);',
			'			var $btn = $("#wysiwyg_editor_ajax");',
			'			if (isNaN(postsEdited)) {',
			'				$btn.text("ERROR");',
			'			}',
			'			else if (postsEdited < 5) {',
			'				$btn.text("All posts converted.");',
			'			}',
			'			else {',
			'				totalEdited += postsEdited;',
			'				$btn.text("Updating posts... " + totalEdited)',
			'				window.setTimeout(function() {',
			'					wysiwyg_editor_ajax(totalEdited);',
			'				}, 1000);',
			'			}',
			'		}',
			'	});',
			'}',

			'$("#wysiwyg_editor_ajax").click(function() {',
			'	wysiwyg_editor_ajax(0);',
			'	return false;',
			'});',
		);
		$ajaxHtml = 'Update broken images from old CKeditor Smiley plugin: ' .
			'<button id="wysiwyg_editor_ajax">click here</button> ' .
			'<script>' . implode("\n", $js) . '</script>';

		return array(
			'ok' => $saved ? 'WYSIWYG editor settings saved' : null,

			'fields' => array(
				array(
					'label' => 'Allow images to be uploaded',
					'type' => 'checkbox',
					'value' => (int)as_opt('wysiwyg_editor_upload_images'),
					'tags' => 'name="wysiwyg_editor_upload_images_field" id="wysiwyg_editor_upload_images_field"',
				),

				array(
					'id' => 'wysiwyg_editor_upload_all_display',
					'label' => 'Allow other content to be uploaded, e.g. Flash, PDF',
					'type' => 'checkbox',
					'value' => (int)as_opt('wysiwyg_editor_upload_all'),
					'tags' => 'name="wysiwyg_editor_upload_all_field"',
				),

				array(
					'id' => 'wysiwyg_editor_upload_max_size_display',
					'label' => 'Maximum size of uploads:',
					'suffix' => 'MB (max '.as_html(number_format($this->bytes_to_mega(as_get_max_upload_size()), 1)).')',
					'type' => 'number',
					'value' => as_html(number_format($this->bytes_to_mega(as_opt('wysiwyg_editor_upload_max_size')), 1)),
					'tags' => 'name="wysiwyg_editor_upload_max_size_field"',
				),

				array(
					'type' => 'custom',
					'html' => $ajaxHtml,
				),
			),

			'buttons' => array(
				array(
					'label' => 'Save Changes',
					'tags' => 'name="wysiwyg_editor_save_button"',
				),
			),
		);
	}

	public function calc_quality($content, $format)
	{
		if ($format == 'html')
			return 1.0;
		elseif ($format == '')
			return 0.8;
		else
			return 0;
	}

	public function get_field(&$as_content, $content, $format, $fieldname, $rows)
	{
		$scriptsrc = $this->urltoroot.'ckeditor/ckeditor.js?'.AS_VERSION;
		$alreadyadded = false;

		if (isset($as_content['script_src'])) {
			foreach ($as_content['script_src'] as $testscriptsrc) {
				if ($testscriptsrc == $scriptsrc)
					$alreadyadded = true;
			}
		}

		if (!$alreadyadded) {
			$uploadimages = as_opt('wysiwyg_editor_upload_images');
			$uploadall = $uploadimages && as_opt('wysiwyg_editor_upload_all');
			$imageUploadUrl = as_js( as_path('wysiwyg-editor-upload', array('as_only_image' => true)) );
			$fileUploadUrl = as_js( as_path('wysiwyg-editor-upload') );

			$as_content['script_src'][] = $scriptsrc;
			$as_content['script_lines'][] = array(
				// Most CKeditor config occurs in ckeditor/config.js
				"var as_wysiwyg_editor_config = {",

				// File uploads
				($uploadimages ? "	filebrowserImageUploadUrl: $imageUploadUrl," : ""),
				($uploadall ? "	filebrowserUploadUrl: $fileUploadUrl," : ""),
				"	filebrowserUploadMethod: 'form',", // Use form upload instead of XHR

				// Set language to APS site language, falling back to English if not available.
				"	defaultLanguage: 'en',",
				"	language: " . as_js(as_opt('site_language')) . "",

				"};",
			);
		}

		if ($format == 'html') {
			$html = $content;
			$text = $this->html_to_text($content);
		}
		else {
			$text = $content;
			$html = as_html($content, true);
		}

		return array(
			'tags' => 'name="'.$fieldname.'"',
			'value' => as_html($text),
			'rows' => $rows,
			'html_prefix' => '<input name="'.$fieldname.'_ckeditor_ok" id="'.$fieldname.'_ckeditor_ok" type="hidden" value="0"><input name="'.$fieldname.'_ckeditor_data" id="'.$fieldname.'_ckeditor_data" type="hidden" value="'.as_html($html).'">',
		);
	}

	public function load_script($fieldname)
	{
		return
			"if (as_ckeditor_".$fieldname." = CKEDITOR.replace(".as_js($fieldname).", as_wysiwyg_editor_config)) { " .
				"as_ckeditor_".$fieldname.".setData(document.getElementById(".as_js($fieldname.'_ckeditor_data').").value); " .
				"document.getElementById(".as_js($fieldname.'_ckeditor_ok').").value = 1; " .
			"}";
	}

	public function focus_script($fieldname)
	{
		return "if (as_ckeditor_".$fieldname.") as_ckeditor_".$fieldname.".focus();";
	}

	public function update_script($fieldname)
	{
		return "if (as_ckeditor_".$fieldname.") as_ckeditor_".$fieldname.".updateElement();";
	}

	public function read_post($fieldname)
	{
		if (as_post_text($fieldname.'_ckeditor_ok')) {
			// CKEditor was loaded successfully
			$html = as_post_text($fieldname);

			// remove <p>, <br>, etc... since those are OK in text
			$htmlformatting = preg_replace('/<\s*\/?\s*(br|p)\s*\/?\s*>/i', '', $html);

			if (preg_match('/<.+>/', $htmlformatting)) {
				// if still some other tags, it's worth keeping in HTML
				// as_sanitize_html() is ESSENTIAL for security
				return array(
					'format' => 'html',
					'content' => as_sanitize_html($html, false, true),
				);
			}
			else {
				// convert to text
				as_load_module('viewer', '');

				return array(
					'format' => '',
					'content' => $this->html_to_text($html),
				);
			}
		} else {
			// CKEditor was not loaded so treat it as plain text
			return array(
				'format' => '',
				'content' => as_post_text($fieldname),
			);
		}
	}


	private function html_to_text($html)
	{
		$viewer = as_load_module('viewer', '');
		return $viewer->get_text($html, 'html', array());
	}

	private function bytes_to_mega($bytes)
	{
		return $bytes / 1048576;
	}
}
