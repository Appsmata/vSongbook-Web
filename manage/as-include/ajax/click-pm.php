<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax single clicks on private messages


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


$signinUserId = as_get_logged_in_userid();
$signinUserHandle = as_get_logged_in_handle();

$fromhandle = as_post_text('handle');
$start = (int)as_post_text('start');
$box = as_post_text('box');
$pagesize = as_opt('page_size_pms');

if (!isset($signinUserId) || $signinUserHandle !== $fromhandle || !in_array($box, array('inbox', 'outbox'))) {
	echo "AS_AJAX_RESPONSE\n0\n";
	return;
}


$func = 'as_db_messages_' . $box . '_selectspec';
$pmSpec = $func('private', $signinUserId, true, $start, $pagesize);
$userMessages = as_db_select_with_pending($pmSpec);

foreach ($userMessages as $message) {
	if (as_clicked('m' . $message['messageid'] . '_dodelete')) {
		if (as_check_form_security_code('pm-' . $fromhandle, as_post_text('code'))) {
			as_pm_delete($signinUserId, as_get_logged_in_handle(), as_cookie_get(), $message, $box);
			echo "AS_AJAX_RESPONSE\n1\n";
			return;
		}
	}
}

echo "AS_AJAX_RESPONSE\n0\n";
