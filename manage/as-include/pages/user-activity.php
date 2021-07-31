<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for user page showing recent activity


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


// Find the recent activity for this user

$signinuserid = as_get_logged_in_userid();
$identifier = AS_FINAL_EXTERNAL_USERS ? $userid : $handle;

list($useraccount, $songs, $reviewqs, $commentqs, $editqs) = as_db_select_with_pending(
	AS_FINAL_EXTERNAL_USERS ? null : as_db_user_account_selectspec($handle, false),
	as_db_user_recent_qs_selectspec($signinuserid, $identifier, as_opt_if_loaded('page_size_activity')),
	as_db_user_recent_a_qs_selectspec($signinuserid, $identifier),
	as_db_user_recent_c_qs_selectspec($signinuserid, $identifier),
	as_db_user_recent_edit_qs_selectspec($signinuserid, $identifier)
);

if (!AS_FINAL_EXTERNAL_USERS && !is_array($useraccount)) // check the user exists
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';


// Get information on user references

$songs = as_any_sort_and_dedupe(array_merge($songs, $reviewqs, $commentqs, $editqs));
$songs = array_slice($songs, 0, as_opt('page_size_activity'));
$usershtml = as_userids_handles_html(as_any_get_userids_handles($songs), false);


// Prepare content for theme

$as_content = as_content_prepare(true);

if (count($songs))
	$as_content['title'] = as_lang_html_sub('profile/recent_activity_by_x', $userhtml);
else
	$as_content['title'] = as_lang_html_sub('profile/no_posts_by_x', $userhtml);


// Recent activity by this user

$as_content['s_list']['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '"',

	'hidden' => array(
		'code' => as_get_form_security_code('thumb'),
	),
);

$as_content['s_list']['qs'] = array();

$htmldefaults = as_post_html_defaults('S');
$htmldefaults['whoview'] = false;
$htmldefaults['thumbview'] = false;
$htmldefaults['avatarsize'] = 0;

foreach ($songs as $song) {
	$as_content['s_list']['qs'][] = as_any_to_q_html_fields($song, $signinuserid, as_cookie_get(),
		$usershtml, null, array('thumbview' => false) + as_post_html_options($song, $htmldefaults));
}


// Sub menu for navigation in user pages

$ismyuser = isset($signinuserid) && $signinuserid == (AS_FINAL_EXTERNAL_USERS ? $userid : $useraccount['userid']);
$fullname = as_db_name_find_by_handle($handle);
$as_content['navigation']['sub'] = as_user_sub_navigation($fullname, $handle, 'activity', $ismyuser);


return $as_content;
