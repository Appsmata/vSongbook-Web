<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/wysiwyg-editor/as-wysiwyg-upload.php
	Description: Page module class for WYSIWYG editor (CKEditor) file upload receiver


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


class as_wysiwyg_upload
{
	public function match_request($request)
	{
		return $request === 'wysiwyg-editor-upload';
	}

	public function process_request($request)
	{
		$message = '';
		$url = '';

		if (is_array($_FILES) && count($_FILES)) {
			if (as_opt('wysiwyg_editor_upload_images')) {
				require_once AS_INCLUDE_DIR . 'app/upload.php';

				$onlyImage = as_get('as_only_image');
				$upload = as_upload_file_one(
					as_opt('wysiwyg_editor_upload_max_size'),
					$onlyImage || !as_opt('wysiwyg_editor_upload_all'),
					$onlyImage ? 600 : null, // max width if it's an image upload
					null // no max height
				);

				if (isset($upload['error'])) {
					$message = $upload['error'];
				} else {
					$url = $upload['bloburl'];
				}
			} else {
				$message = as_lang('users/no_permission');
			}
		}

		echo sprintf(
			'<script>window.parent.CKEDITOR.tools.callFunction(%s, %s, %s);</script>',
			as_js(as_get('CKEditorFuncNum')),
			as_js($url),
			as_js($message)
		);

		return null;
	}
}
