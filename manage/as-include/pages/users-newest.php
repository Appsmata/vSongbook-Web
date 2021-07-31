<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-include/pages/users-newest.php
	Description: Controller for newest users page


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

// Check we're not using single-sign on integration

if (AS_FINAL_EXTERNAL_USERS) {
	as_fatal_error('User accounts are handled by external code');
}


// Check we have permission to view this page (moderator or above)

if (as_user_permit_error('permit_view_new_users_page')) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}


// Get list of all users

$start = as_get_start();
$users = as_db_select_with_pending(as_db_newest_users_selectspec($start, as_opt_if_loaded('page_size_users')));

$userCount = as_opt('cache_userpointscount');
$pageSize = as_opt('page_size_users');
$users = array_slice($users, 0, $pageSize);
$usersHtml = as_userids_handles_html($users);

// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('main/newest_users');

$as_content['ranking'] = array(
	'items' => array(),
	'rows' => ceil($pageSize / as_opt('columns_users')),
	'type' => 'users',
	'sort' => 'date',
);

if (!empty($users)) {
	foreach ($users as $user) {
		$avatarHtml = as_get_user_avatar_html($user['flags'], $user['email'], $user['handle'],
			$user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], as_opt('avatar_users_size'), true);

		$when = as_when_to_html($user['created'], 7);
		$as_content['ranking']['items'][] = array(
			'avatar' => $avatarHtml,
			'label' => $usersHtml[$user['userid']],
			'score' => $when['data'],
			'raw' => $user,
		);
	}
} else {
	$as_content['title'] = as_lang_html('main/no_active_users');
}

$as_content['canonical'] = as_get_canonical();

$as_content['page_links'] = as_html_page_links(as_request(), $start, $pageSize, $userCount, as_opt('pages_prev_next'));

$as_content['navigation']['sub'] = as_users_sub_navigation();


return $as_content;
