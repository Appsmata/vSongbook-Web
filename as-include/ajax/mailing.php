<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax mailing loop requests


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
require_once AS_INCLUDE_DIR . 'app/mailing.php';


$continue = false;

if (as_get_logged_in_level() >= AS_USER_LEVEL_ADMIN) {
	$starttime = time();

	as_mailing_perform_step();

	if ($starttime == time())
		sleep(1); // make sure at least one second has passed

	$message = as_mailing_progress_message();

	if (isset($message))
		$continue = true;
	else
		$message = as_lang('admin/mailing_complete');

} else
	$message = as_lang('admin/no_privileges');


echo "AS_AJAX_RESPONSE\n" . (int)$continue . "\n" . as_html($message);
