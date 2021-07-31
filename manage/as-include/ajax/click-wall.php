<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax single clicks on wall posts


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


$tohandle = as_post_text('handle');
$start = (int)as_post_text('start');

$usermessages = as_db_select_with_pending(as_db_recent_messages_selectspec(null, null, $tohandle, false, null, $start));
$usermessages = as_wall_posts_add_rules($usermessages, $start);

foreach ($usermessages as $message) {
	if (as_clicked('m' . $message['messageid'] . '_dodelete') && $message['deleteable']) {
		if (as_check_form_security_code('wall-' . $tohandle, as_post_text('code'))) {
			as_wall_delete_post(as_get_logged_in_userid(), as_get_logged_in_handle(), as_cookie_get(), $message);
			echo "AS_AJAX_RESPONSE\n1\n";
			return;
		}
	}
}

echo "AS_AJAX_RESPONSE\n0\n";
