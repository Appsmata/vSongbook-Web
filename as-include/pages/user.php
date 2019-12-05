<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for user profile page


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


// Determine the identify of the user

$handle = as_request_part(1);

if (!strlen($handle)) {
	$handle = as_get_logged_in_handle();
	as_redirect(!empty($handle) ? 'user/' . $handle : 'users');
}


// Get the HTML to display for the handle, and if we're using external users, determine the userid

if (AS_FINAL_EXTERNAL_USERS) {
	$userid = as_handle_to_userid($handle);
	if (!isset($userid))
		return include AS_INCLUDE_DIR . 'as-page-not-found.php';

	$usershtml = as_get_users_html(array($userid), false, as_path_to_root(), true);
	$userhtml = @$usershtml[$userid];

} else
	$userhtml = as_html($handle);


// Display the appropriate page based on the request

switch (as_request_part(2)) {
	case 'wall':
		as_set_template('user-wall');
		$as_content = include AS_INCLUDE_DIR . 'pages/user-wall.php';
		break;

	case 'activity':
		as_set_template('user-activity');
		$as_content = include AS_INCLUDE_DIR . 'pages/user-activity.php';
		break;

	case 'songs':
		as_set_template('user-songs');
		$as_content = include AS_INCLUDE_DIR . 'pages/user-songs.php';
		break;

	case 'reviews':
		as_set_template('user-reviews');
		$as_content = include AS_INCLUDE_DIR . 'pages/user-reviews.php';
		break;

	case null:
		$as_content = include AS_INCLUDE_DIR . 'pages/user-profile.php';
		break;

	default:
		$as_content = include AS_INCLUDE_DIR . 'as-page-not-found.php';
		break;
}

return $as_content;
