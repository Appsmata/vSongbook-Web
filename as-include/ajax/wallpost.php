<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax wall post requests


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

require_once AS_INCLUDE_DIR . 'app/messages.php';
require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'app/cookies.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';


$message = as_post_text('message');
$tohandle = as_post_text('handle');
$morelink = as_post_text('morelink');

$touseraccount = as_db_select_with_pending(as_db_user_account_selectspec($tohandle, false));
$signinuserid = as_get_logged_in_userid();

$errorhtml = as_wall_error_html($signinuserid, $touseraccount['userid'], $touseraccount['flags']);

if ($errorhtml || !strlen($message) || !as_check_form_security_code('wall-' . $tohandle, as_post_text('code'))) {
	echo "AS_AJAX_RESPONSE\n0"; // if there's an error, process in non-Ajax way
} else {
	$messageid = as_wall_add_post($signinuserid, as_get_logged_in_handle(), as_cookie_get(),
		$touseraccount['userid'], $touseraccount['handle'], $message, '');
	$touseraccount['wallposts']++; // won't have been updated

	$usermessages = as_db_select_with_pending(as_db_recent_messages_selectspec(null, null, $touseraccount['userid'], true, as_opt('page_size_wall')));
	$usermessages = as_wall_posts_add_rules($usermessages, 0);

	$themeclass = as_load_theme_class(as_get_site_theme(), 'wall', null, null);
	$themeclass->initialize();

	echo "AS_AJAX_RESPONSE\n1\n";

	echo 'm' . $messageid . "\n"; // element in list to be revealed

	foreach ($usermessages as $message) {
		$themeclass->message_item(as_wall_post_view($message));
	}

	if ($morelink && ($touseraccount['wallposts'] > count($usermessages)))
		$themeclass->message_item(as_wall_view_more_link($tohandle, count($usermessages)));
}
