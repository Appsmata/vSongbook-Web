<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for page listing user's favorites


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


// Check that we're logged in

$userid = as_get_logged_in_userid();

if (!isset($userid))
	as_redirect('signin');


// Get lists of favorites for this user

$pagesize_qs = as_opt('page_size_qs');
$pagesize_users = as_opt('page_size_users');
$pagesize_tags = as_opt('page_size_tags');

list($numQs, $songs, $numUsers, $users, $numTags, $tags, $categories) = as_db_select_with_pending(
	as_db_selectspec_count(as_db_user_favorite_qs_selectspec($userid)),
	as_db_user_favorite_qs_selectspec($userid, $pagesize_qs),

	AS_FINAL_EXTERNAL_USERS ? null : as_db_selectspec_count(as_db_user_favorite_users_selectspec($userid)),
	AS_FINAL_EXTERNAL_USERS ? null : as_db_user_favorite_users_selectspec($userid, $pagesize_users),

	as_db_selectspec_count(as_db_user_favorite_tags_selectspec($userid)),
	as_db_user_favorite_tags_selectspec($userid, $pagesize_tags),

	as_db_user_favorite_categories_selectspec($userid)
);

$usershtml = as_userids_handles_html(AS_FINAL_EXTERNAL_USERS ? $songs : array_merge($songs, $users));


// Prepare and return content for theme

$as_content = as_content_prepare(true);

$as_content['title'] = as_lang_html('misc/my_favorites_title');


// Favorite songs

$as_content['s_list'] = as_favorite_s_list_view($songs, $usershtml);
$as_content['s_list']['title'] = count($songs) ? as_lang_html('main/nav_qs') : as_lang_html('misc/no_favorite_qs');
if ($numQs['count'] > count($songs)) {
	$url = as_path_html('favorites/songs', array('start' => $pagesize_qs));
	$as_content['s_list']['footer'] = '<p class="as-link-next"><a href="' . $url . '">' . as_lang_html('misc/more_favorite_qs') . '</a></p>';
}


// Favorite users

if (!AS_FINAL_EXTERNAL_USERS) {
	$as_content['ranking_users'] = as_favorite_users_view($users, $usershtml);
	$as_content['ranking_users']['title'] = count($users) ? as_lang_html('main/nav_users') : as_lang_html('misc/no_favorite_users');
	if ($numUsers['count'] > count($users)) {
		$url = as_path_html('favorites/users', array('start' => $pagesize_users));
		$as_content['ranking_users']['footer'] = '<p class="as-link-next"><a href="' . $url . '">' . as_lang_html('misc/more_favorite_users') . '</a></p>';
	}
}


// Favorite tags

if (as_using_tags()) {
	$as_content['ranking_tags'] = as_favorite_tags_view($tags);
	$as_content['ranking_tags']['title'] = count($tags) ? as_lang_html('main/nav_tags') : as_lang_html('misc/no_favorite_tags');
	if ($numTags['count'] > count($tags)) {
		$url = as_path_html('favorites/tags', array('start' => $pagesize_tags));
		$as_content['ranking_tags']['footer'] = '<p class="as-link-next"><a href="' . $url . '">' . as_lang_html('misc/more_favorite_tags') . '</a></p>';
	}
}


// Favorite categories (no pagination)

if (as_using_categories()) {
	$as_content['nav_list_categories'] = as_favorite_categories_view($categories);
	$as_content['nav_list_categories']['title'] = count($categories) ? as_lang_html('main/nav_categories') : as_lang_html('misc/no_favorite_categories');
}


// Sub navigation for account pages and suggestion

$as_content['suggest_next'] = as_lang_html_sub('misc/suggest_favorites_add', '<span class="as-favorite-image">&nbsp;</span>');

$handle = as_get_logged_in_handle();
$fullname = as_db_name_find_by_handle($handle);

$as_content['navigation']['sub'] = as_user_sub_navigation($fullname, $handle, 'favorites', true);


return $as_content;
