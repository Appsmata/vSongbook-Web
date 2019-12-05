<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: User cookie management (application level) for tracking anonymous posts


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


/**
 * Return the user identification cookie sent by the browser for this page request, or null if none
 */
function as_cookie_get()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	return isset($_COOKIE['as_id']) ? as_gpc_to_string($_COOKIE['as_id']) : null;
}


/**
 * Return user identification cookie sent by browser if valid, or create a new one if not.
 * Either way, extend for another year (this is used when an anonymous post is created)
 */
function as_cookie_get_create()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'db/cookies.php';

	$cookieid = as_cookie_get();

	if (!isset($cookieid) || !as_db_cookie_exists($cookieid)) {
		// cookie is invalid
		$cookieid = as_db_cookie_create(as_remote_ip_address());
	}

	setcookie('as_id', $cookieid, time() + 86400 * 365, '/', AS_COOKIE_DOMAIN, (bool)ini_get('session.cookie_secure'), true);
	$_COOKIE['as_id'] = $cookieid;

	return $cookieid;
}


/**
 * Called after a database write $action performed by a user identified by $cookieid,
 * relating to $songid, $reviewid and/or $commentid
 * @param $cookieid
 * @param $action
 */
function as_cookie_report_action($cookieid, $action)
{
	require_once AS_INCLUDE_DIR . 'db/cookies.php';

	as_db_cookie_written($cookieid, as_remote_ip_address());
}
