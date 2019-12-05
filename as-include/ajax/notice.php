<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax requests to close a notice


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

require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'db/notices.php';
require_once AS_INCLUDE_DIR . 'db/users.php';


$noticeid = as_post_text('noticeid');

if (!as_check_form_security_code('notice-' . $noticeid, as_post_text('code')))
	echo "AS_AJAX_RESPONSE\n0\n" . as_lang('misc/form_security_reload');

else {
	if ($noticeid == 'visitor')
		setcookie('as_noticed', 1, time() + 86400 * 3650, '/', AS_COOKIE_DOMAIN, (bool)ini_get('session.cookie_secure'), true);

	else {
		$userid = as_get_logged_in_userid();

		if ($noticeid == 'welcome')
			as_db_user_set_flag($userid, AS_USER_FLAGS_WELCOME_NOTICE, false);
		else
			as_db_usernotice_delete($userid, $noticeid);
	}


	echo "AS_AJAX_RESPONSE\n1";
}
