<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax category information requests


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

require_once AS_INCLUDE_DIR . 'db/selects.php';


$categoryid = as_post_text('categoryid');
if (!strlen($categoryid))
	$categoryid = null;

list($fullcategory, $categories) = as_db_select_with_pending(
	as_db_full_category_selectspec($categoryid, true),
	as_db_category_sub_selectspec($categoryid)
);

echo "AS_AJAX_RESPONSE\n1\n";

echo as_html(strtr(@$fullcategory['content'], "\r\n", '  ')); // category description

foreach ($categories as $category) {
	// subcategory information
	echo "\n" . $category['categoryid'] . '/' . $category['title'];
}
