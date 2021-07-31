<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for sub-page listing user's favorites of a certain type


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
require_once AS_INCLUDE_DIR . 'app/favorites.php';


// Data for functions to run

$favswitch = array(
	'songs' => array(
		'page_opt' => 'page_size_qs',
		'fn_spec' => 'as_db_user_favorite_qs_selectspec',
		'fn_view' => 'as_favorite_s_list_view',
		'key' => 's_list',
	),
	'users' => array(
		'page_opt' => 'page_size_users',
		'fn_spec' => 'as_db_user_favorite_users_selectspec',
		'fn_view' => 'as_favorite_users_view',
		'key' => 'ranking_users',
	),
	'tags' => array(
		'page_opt' => 'page_size_tags',
		'fn_spec' => 'as_db_user_favorite_tags_selectspec',
		'fn_view' => 'as_favorite_tags_view',
		'key' => 'ranking_tags',
	),
);


// Check that we're logged in

$userid = as_get_logged_in_userid();

if (!isset($userid))
	as_redirect('signin');


// Get lists of favorites of this type

$favtype = as_request_part(1);
$start = as_get_start();

if (!array_key_exists($favtype, $favswitch) || ($favtype === 'users' && AS_FINAL_EXTERNAL_USERS))
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';

extract($favswitch[$favtype]); // get switch variables

$pagesize = as_opt($page_opt);
list($totalItems, $items) = as_db_select_with_pending(
	as_db_selectspec_count($fn_spec($userid)),
	$fn_spec($userid, $pagesize, $start)
);

$count = $totalItems['count'];
$usershtml = as_userids_handles_html($items);


// Prepare and return content for theme

$as_content = as_content_prepare(true);

$as_content['title'] = as_lang_html('misc/my_favorites_title');

$as_content[$key] = $fn_view($items, $usershtml);


// Sub navigation for account pages and suggestion

$as_content['suggest_next'] = as_lang_html_sub('misc/suggest_favorites_add', '<span class="as-favorite-image">&nbsp;</span>');

$as_content['page_links'] = as_html_page_links(as_request(), $start, $pagesize, $count, as_opt('pages_prev_next'));

$handle = as_get_logged_in_handle();
$fullname = as_db_name_find_by_handle($handle);

$as_content['navigation']['sub'] = as_user_sub_navigation($fullname, $handle, 'favorites', true);


return $as_content;
