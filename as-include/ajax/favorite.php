<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax favorite requests


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
require_once AS_INCLUDE_DIR . 'app/favorites.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


$entitytype = as_post_text('entitytype');
$entityid = as_post_text('entityid');
$setfavorite = as_post_text('favorite');

$userid = as_get_logged_in_userid();

if (!as_check_form_security_code('favorite-' . $entitytype . '-' . $entityid, as_post_text('code'))) {
	echo "AS_AJAX_RESPONSE\n0\n" . as_lang('misc/form_security_reload');
} elseif (isset($userid)) {
	$cookieid = as_cookie_get();

	as_user_favorite_set($userid, as_get_logged_in_handle(), $cookieid, $entitytype, $entityid, $setfavorite);

	$favoriteform = as_favorite_form($entitytype, $entityid, $setfavorite, as_lang($setfavorite ? 'main/remove_favorites' : 'main/add_favorites'));

	$themeclass = as_load_theme_class(as_get_site_theme(), 'ajax-favorite', null, null);
	$themeclass->initialize();

	echo "AS_AJAX_RESPONSE\n1\n";

	$themeclass->favorite_inner_html($favoriteform);
}
