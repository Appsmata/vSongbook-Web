<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for signout page (not much to do)


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


if (AS_FINAL_EXTERNAL_USERS) {
	$request = as_request();
	$topath = as_get('to'); // lets user switch between signin and signup without losing destination page
	$userlinks = as_get_signin_links(as_path_to_root(), isset($topath) ? $topath : as_path($request, $_GET, ''));

	if (!empty($userlinks['signout'])) {
		as_redirect_raw($userlinks['signout']);
	}
	as_fatal_error('User signout should be handled by external code');
}

if (as_is_logged_in()) {
	as_set_logged_in_user(null);
}

as_redirect(''); // back to home page
