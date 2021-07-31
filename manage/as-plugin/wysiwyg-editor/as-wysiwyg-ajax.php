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


class as_wysiwyg_ajax
{
	public function match_request($request)
	{
		return $request == 'wysiwyg-editor-ajax';
	}

	// Fix path to WYSIWYG editor smileys
	public function process_request($request)
	{
		require_once AS_INCLUDE_DIR.'as-app-posts.php';

		// smiley replacement regexes
		$rxSearch = '<(img|a)([^>]+)(src|href)="([^"]+)/wysiwyg-editor/plugins/smiley/images/([^"]+)"';
		$rxReplace = '<$1$2$3="$4/wysiwyg-editor/ckeditor/plugins/smiley/images/$5"';

		as_suspend_event_reports(true); // avoid infinite loop

		// prevent race conditions
		$locks = array('posts', 'categories', 'users', 'users AS lastusers', 'userpoints', 'words', 'titlewords', 'contentwords', 'tagwords', 'words AS x', 'posttags', 'options');
		foreach ($locks as &$tbl)
			$tbl = '^'.$tbl.' WRITE';
		as_db_query_sub('LOCK TABLES ' . implode(',', $locks));

		$sql =
			'SELECT postid, title, content FROM ^posts WHERE format="html" ' .
			'AND content LIKE "%/wysiwyg-editor/plugins/smiley/images/%" ' .
			'AND content RLIKE \'' . $rxSearch . '\' ' .
			'LIMIT 5';
		$result = as_db_query_sub($sql);

		$numPosts = 0;
		while (($post=as_db_read_one_assoc($result, true)) !== null) {
			$newcontent = preg_replace("#$rxSearch#", $rxReplace, $post['content']);
			as_post_set_content($post['postid'], $post['title'], $newcontent);
			$numPosts++;
		}

		as_db_query_raw('UNLOCK TABLES');
		as_suspend_event_reports(false);

		echo $numPosts;
	}
}
