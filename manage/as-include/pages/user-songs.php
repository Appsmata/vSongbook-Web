<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for user page showing all user's songs


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


// $handle, $userhtml are already set by /as-include/page/user.php - also $userid if using external user integration

$start = as_get_start();


// Find the songs for this user

$signinuserid = as_get_logged_in_userid();
$identifier = AS_FINAL_EXTERNAL_USERS ? $userid : $handle;

list($useraccount, $userpoints, $songs) = as_db_select_with_pending(
	AS_FINAL_EXTERNAL_USERS ? null : as_db_user_account_selectspec($handle, false),
	as_db_user_points_selectspec($identifier),
	as_db_user_recent_qs_selectspec($signinuserid, $identifier, as_opt_if_loaded('page_size_qs'), $start)
);

if (!AS_FINAL_EXTERNAL_USERS && !is_array($useraccount)) // check the user exists
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';


// Get information on user songs

$pagesize = as_opt('page_size_qs');
$count = (int)@$userpoints['qposts'];
$songs = array_slice($songs, 0, $pagesize);
$usershtml = as_userids_handles_html($songs, false);


// Prepare content for theme

$as_content = as_content_prepare(true);

if (count($songs))
	$as_content['title'] = as_lang_html_sub('profile/songs_by_x', $userhtml);
else
	$as_content['title'] = as_lang_html_sub('profile/no_songs_by_x', $userhtml);


// Recent songs by this user

$as_content['s_list']['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '"',

	'hidden' => array(
		'code' => as_get_form_security_code('thumb'),
	),
);

$as_content['s_list']['qs'] = array();

$htmldefaults = as_post_html_defaults('S');
$htmldefaults['whoview'] = false;
$htmldefaults['avatarsize'] = 0;

foreach ($songs as $song) {
	$as_content['s_list']['qs'][] = as_post_html_fields($song, $signinuserid, as_cookie_get(),
		$usershtml, null, as_post_html_options($song, $htmldefaults));
}

$as_content['page_links'] = as_html_page_links(as_request(), $start, $pagesize, $count, as_opt('pages_prev_next'));


// Sub menu for navigation in user pages

$ismyuser = isset($signinuserid) && $signinuserid == (AS_FINAL_EXTERNAL_USERS ? $userid : $useraccount['userid']);
$fullname = as_db_name_find_by_handle($handle);
$as_content['navigation']['sub'] = as_user_sub_navigation($fullname, $handle, 'songs', $ismyuser);


return $as_content;
