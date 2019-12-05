<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for page showing users who have been blocked


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
require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


// Check we're not using single-sign on integration

if (AS_FINAL_EXTERNAL_USERS) {
	as_fatal_error('User accounts are handled by external code');
}


// Get list of blocked users

$start = as_get_start();
$pagesize = as_opt('page_size_users');

$userSpecCount = as_db_selectspec_count(as_db_users_with_flag_selectspec(AS_USER_FLAGS_USER_BLOCKED));
$userSpec = as_db_users_with_flag_selectspec(AS_USER_FLAGS_USER_BLOCKED, $start, $pagesize);

list($numUsers, $users) = as_db_select_with_pending($userSpecCount, $userSpec);
$count = $numUsers['count'];


// Check we have permission to view this page (moderator or above)

if (as_get_logged_in_level() < AS_USER_LEVEL_MODERATOR) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}


// Get userids and handles of retrieved users

$usershtml = as_userids_handles_html($users);


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = $count > 0 ? as_lang_html('users/blocked_users') : as_lang_html('users/no_blocked_users');

$as_content['ranking'] = array(
	'items' => array(),
	'rows' => ceil(count($users) / as_opt('columns_users')),
	'type' => 'users',
	'sort' => 'level',
);

foreach ($users as $user) {
	$as_content['ranking']['items'][] = array(
		'label' => $usershtml[$user['userid']],
		'score' => as_html(as_user_level_string($user['level'])),
		'raw' => $user,
	);
}

$as_content['page_links'] = as_html_page_links(as_request(), $start, $pagesize, $count, as_opt('pages_prev_next'));

$as_content['navigation']['sub'] = as_users_sub_navigation();


return $as_content;
