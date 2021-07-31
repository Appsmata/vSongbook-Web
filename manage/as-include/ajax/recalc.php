<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax admin recalculation requests


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
require_once AS_INCLUDE_DIR . 'app/recalc.php';


if (as_get_logged_in_level() >= AS_USER_LEVEL_ADMIN) {
	if (!as_check_form_security_code('admin/recalc', as_post_text('code'))) {
		$state = '';
		$message = as_lang('misc/form_security_reload');

	} else {
		$state = as_post_text('state');
		$stoptime = time() + 3;

		while (as_recalc_perform_step($state) && time() < $stoptime) {
			// wait
		}

		$message = as_recalc_get_message($state);
	}

} else {
	$state = '';
	$message = as_lang('admin/no_privileges');
}


echo "AS_AJAX_RESPONSE\n1\n" . $state . "\n" . as_html($message);
