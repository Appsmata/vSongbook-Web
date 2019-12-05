<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax thumbing requests


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
require_once AS_INCLUDE_DIR . 'app/cookies.php';
require_once AS_INCLUDE_DIR . 'app/thumbs.php';
require_once AS_INCLUDE_DIR . 'app/format.php';
require_once AS_INCLUDE_DIR . 'app/options.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';


$postid = as_post_text('postid');
$thumb = as_post_text('thumb');
$code = as_post_text('code');

$userid = as_get_logged_in_userid();
$cookieid = as_cookie_get();

if (!as_check_form_security_code('thumb', $code)) {
	$thumberror = as_lang_html('misc/form_security_reload');
} else {
	$post = as_db_select_with_pending(as_db_full_post_selectspec($userid, $postid));
	$thumberror = as_thumb_error_html($post, $thumb, $userid, as_request());
}

if ($thumberror === false) {
	as_thumb_set($post, $userid, as_get_logged_in_handle(), $cookieid, $thumb);

	$post = as_db_select_with_pending(as_db_full_post_selectspec($userid, $postid));

	$fields = as_post_html_fields($post, $userid, $cookieid, array(), null, array(
		'thumbview' => as_get_thumb_view($post, true), // behave as if on song page since the thumb succeeded
	));

	$themeclass = as_load_theme_class(as_get_site_theme(), 'thumbing', null, null);
	$themeclass->initialize();

	echo "AS_AJAX_RESPONSE\n1\n";
	$themeclass->thumbing_inner_html($fields);

	return;

}

echo "AS_AJAX_RESPONSE\n0\n" . $thumberror;
