<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax single clicks on posts in admin section


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

require_once AS_INCLUDE_DIR . 'app/admin.php';
require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'app/cookies.php';


$entityid = as_post_text('entityid');
$action = as_post_text('action');

if (!as_check_form_security_code('admin/click', as_post_text('code')))
	echo "AS_AJAX_RESPONSE\n0\n" . as_lang('misc/form_security_reload');
elseif (as_admin_single_click($entityid, $action)) // permission check happens in here
	echo "AS_AJAX_RESPONSE\n1\n";
else
	echo "AS_AJAX_RESPONSE\n0\n" . as_lang('main/general_error');
