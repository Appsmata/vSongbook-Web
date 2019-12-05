<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database-level access to tables which monitor rate limits


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
 * Get rate limit information for $action from the database for user $userid and/or IP address $ip, if they're set.
 * Return as an array with the limit type in the key, and a labelled array of the period and count.
 * @param $userid
 * @param $ip
 * @param $action
 * @return array
 */
function as_db_limits_get($userid, $ip, $action)
{
	$selects = array();
	$arguments = array();

	if (isset($userid)) {
		$selects[] = "(SELECT 'user' AS limitkey, period, count FROM ^userlimits WHERE userid=$ AND action=$)";
		$arguments[] = $userid;
		$arguments[] = $action;
	}

	if (isset($ip)) {
		$selects[] = "(SELECT 'ip' AS limitkey, period, count FROM ^iplimits WHERE ip=UNHEX($) AND action=$)";
		$arguments[] = bin2hex(@inet_pton($ip));
		$arguments[] = $action;
	}

	if (count($selects)) {
		$query = as_db_apply_sub(implode(' UNION ALL ', $selects), $arguments);
		return as_db_read_all_assoc(as_db_query_raw($query), 'limitkey');

	} else
		return array();
}


/**
 * Increment the database rate limit count for user $userid and $action by $count within $period
 * @param $userid
 * @param $action
 * @param $period
 * @param $count
 */
function as_db_limits_user_add($userid, $action, $period, $count)
{
	as_db_query_sub(
		'INSERT INTO ^userlimits (userid, action, period, count) VALUES ($, $, #, #) ' .
		'ON DUPLICATE KEY UPDATE count=IF(period=#, count+#, #), period=#',
		$userid, $action, $period, $count, $period, $count, $count, $period
	);
}


/**
 * Increment the database rate limit count for IP address $ip and $action by $count within $period
 * @param $ip
 * @param $action
 * @param $period
 * @param $count
 */
function as_db_limits_ip_add($ip, $action, $period, $count)
{
	as_db_query_sub(
		'INSERT INTO ^iplimits (ip, action, period, count) VALUES (UNHEX($), $, #, #) ' .
		'ON DUPLICATE KEY UPDATE count=IF(period=#, count+#, #), period=#',
		bin2hex(@inet_pton($ip)), $action, $period, $count, $period, $count, $count, $period
	);
}
