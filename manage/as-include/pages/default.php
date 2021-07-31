<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for home page, Q&A listing page, custom pages and plugin pages


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

if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}


require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'app/format.php';
require_once AS_INCLUDE_DIR . 'app/s-list.php';

$categoryslugs = as_request_parts(1);
$countslugs = count($categoryslugs);
$userid = as_get_logged_in_userid();


// Get lists of recent activity in all its forms, plus category information

list($songs1, $songs2, $songs3, $songs4, $categories, $categoryid) = as_db_select_with_pending(
	as_db_qs_selectspec($userid, 'created', 0, $categoryslugs, null, false, false, as_opt_if_loaded('page_size_activity')),
	as_db_recent_a_qs_selectspec($userid, 0, $categoryslugs),
	as_db_recent_c_qs_selectspec($userid, 0, $categoryslugs),
	as_db_recent_edit_qs_selectspec($userid, 0, $categoryslugs),
	as_db_category_nav_selectspec($categoryslugs, false, false, true),
	$countslugs ? as_db_slugs_to_category_id_selectspec($categoryslugs) : null
);

// Prepare and return content for theme

return as_s_list_page_content(
	as_any_sort_and_dedupe(array_merge($songs1, $songs2, $songs3, $songs4)), // songs
	as_opt('page_size_activity'), // songs per page
	0, // start offset
	null, // total count (null to hide page links)
	null, // title if some songs
	null, // title if no songs
	$categories, // categories for navigation
	$categoryid, // selected category id
	true, // show song counts in category navigation
	'activity/', // prefix for links in category navigation
	as_opt('feed_for_activity') ? 'activity' : null, // prefix for RSS feed paths (null to hide)
	as_html_suggest_qs_tags(as_using_tags(), as_category_path_request($categories, $categoryid)), // suggest what to do next
	null, // page link params
	null // category nav params
);
