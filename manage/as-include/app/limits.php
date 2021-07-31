<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Monitoring and rate-limiting user actions (application level)


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


define('AS_LIMIT_SONGS', 'S');
define('AS_LIMIT_REVIEWS', 'R');
define('AS_LIMIT_COMMENTS', 'C');
define('AS_LIMIT_VOTES', 'V');
define('AS_LIMIT_REGISTRATIONS', 'R');
define('AS_LIMIT_LOGINS', 'L');
define('AS_LIMIT_UPLOADS', 'U');
define('AS_LIMIT_FLAGS', 'F');
define('AS_LIMIT_MESSAGES', 'M'); // i.e. private messages
define('AS_LIMIT_WALL_POSTS', 'W');


/**
 * How many more times the logged in user (and requesting IP address) can perform an action this hour.
 * @param string $action One of the AS_LIMIT_* constants defined above.
 * @return int
 */
function as_user_limits_remaining($action)
{
	$userlimits = as_db_get_pending_result('userlimits', as_db_user_limits_selectspec(as_get_logged_in_userid()));
	$iplimits = as_db_get_pending_result('iplimits', as_db_ip_limits_selectspec(as_remote_ip_address()));

	return as_limits_calc_remaining($action, @$userlimits[$action], @$iplimits[$action]);
}

/**
 * Return how many more times user $userid and/or the requesting IP can perform $action (a AS_LIMIT_* constant) this hour.
 * @deprecated Deprecated from 1.6.0; use `as_user_limits_remaining($action)` instead.
 * @param int $userid
 * @param string $action
 * @return mixed
 */
function as_limits_remaining($userid, $action)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'db/limits.php';

	$dblimits = as_db_limits_get($userid, as_remote_ip_address(), $action);

	return as_limits_calc_remaining($action, @$dblimits['user'], @$dblimits['ip']);
}

/**
 * Calculate how many more times an action can be performed this hour by the user/IP.
 * @param string $action One of the AS_LIMIT_* constants defined above.
 * @param array $userlimits Limits for the user.
 * @param array $iplimits Limits for the requesting IP.
 * @return mixed
 */
function as_limits_calc_remaining($action, $userlimits, $iplimits)
{
	switch ($action) {
		case AS_LIMIT_SONGS:
			$usermax = as_opt('max_rate_user_qs');
			$ipmax = as_opt('max_rate_ip_qs');
			break;

		case AS_LIMIT_REVIEWS:
			$usermax = as_opt('max_rate_user_as');
			$ipmax = as_opt('max_rate_ip_as');
			break;

		case AS_LIMIT_COMMENTS:
			$usermax = as_opt('max_rate_user_cs');
			$ipmax = as_opt('max_rate_ip_cs');
			break;

		case AS_LIMIT_VOTES:
			$usermax = as_opt('max_rate_user_thumbs');
			$ipmax = as_opt('max_rate_ip_thumbs');
			break;

		case AS_LIMIT_REGISTRATIONS:
			$usermax = 1; // not really relevant
			$ipmax = as_opt('max_rate_ip_signups');
			break;

		case AS_LIMIT_LOGINS:
			$usermax = 1; // not really relevant
			$ipmax = as_opt('max_rate_ip_signins');
			break;

		case AS_LIMIT_UPLOADS:
			$usermax = as_opt('max_rate_user_uploads');
			$ipmax = as_opt('max_rate_ip_uploads');
			break;

		case AS_LIMIT_FLAGS:
			$usermax = as_opt('max_rate_user_flags');
			$ipmax = as_opt('max_rate_ip_flags');
			break;

		case AS_LIMIT_MESSAGES:
		case AS_LIMIT_WALL_POSTS:
			$usermax = as_opt('max_rate_user_messages');
			$ipmax = as_opt('max_rate_ip_messages');
			break;

		default:
			as_fatal_error('Unknown limit code in as_limits_calc_remaining: ' . $action);
			break;
	}

	$period = (int)(as_opt('db_time') / 3600);

	return max(0, min(
		$usermax - (@$userlimits['period'] == $period ? $userlimits['count'] : 0),
		$ipmax - (@$iplimits['period'] == $period ? $iplimits['count'] : 0)
	));
}

/**
 * Determine whether the requesting IP address has been blocked from write operations.
 * @return bool
 */
function as_is_ip_blocked()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	global $as_curr_ip_blocked;

	// return cached value early
	if (isset($as_curr_ip_blocked))
		return $as_curr_ip_blocked;

	$as_curr_ip_blocked = false;
	$blockipclauses = as_block_ips_explode(as_opt('block_ips_write'));
	$ip = as_remote_ip_address();

	foreach ($blockipclauses as $blockipclause) {
		if (as_block_ip_match($ip, $blockipclause)) {
			$as_curr_ip_blocked = true;
			break;
		}
	}

	return $as_curr_ip_blocked;
}

/**
 * Return an array of the clauses within $blockipstring, each of which can contain hyphens or asterisks
 * @param $blockipstring
 * @return array
 */
function as_block_ips_explode($blockipstring)
{
	$blockipstring = preg_replace('/\s*\-\s*/', '-', $blockipstring); // special case for 'x.x.x.x - x.x.x.x'

	return preg_split('/[^0-9A-Fa-f\.:\-\*]/', $blockipstring, -1, PREG_SPLIT_NO_EMPTY);
}

/**
 * Checks if the IP address is matched by the individual block clause, which can contain a hyphen or asterisk
 * @param string $ip The IP address
 * @param string $blockipclause The IP/clause to check against, e.g. 127.0.0.*
 * @return bool
 */
function as_block_ip_match($ip, $blockipclause)
{
	$ipv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
	$blockipv4 = filter_var($blockipclause, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

	// allow faster return if IP and blocked IP are plain IPv4 strings (IPv6 requires expanding)
	if ($ipv4 && $blockipv4) {
		return $ip === $blockipclause;
	}

	if (filter_var($ip, FILTER_VALIDATE_IP)) {
		if (preg_match('/^(.*)\-(.*)$/', $blockipclause, $matches)) {
			// match IP range
			if (filter_var($matches[1], FILTER_VALIDATE_IP) && filter_var($matches[2], FILTER_VALIDATE_IP)) {
				return as_ip_between($ip, $matches[1], $matches[2]);
			}
		} elseif (strlen($blockipclause)) {
			// normalize IPv6 addresses
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				$ip = as_ipv6_expand($ip);
				$blockipclause = as_ipv6_expand($blockipclause);
			}

			// expand wildcards; preg_quote misses hyphens but that is OK here
			return preg_match('/^' . str_replace('\\*', '([0-9A-Fa-f]+)', preg_quote($blockipclause, '/')) . '$/', $ip) > 0;
		}
	}

	return false;
}

/**
 * Check if IP falls between two others.
 * @param $ip
 * @param $startip
 * @param $endip
 * @return bool
 */
function as_ip_between($ip, $startip, $endip)
{
	$uip = unpack('C*', @inet_pton($ip));
	$ustartip = unpack('C*', @inet_pton($startip));
	$uendip = unpack('C*', @inet_pton($endip));

	if (count($uip) != count($ustartip) || count($uip) != count($uendip))
		return false;

	foreach ($uip as $i => $byte) {
		if ($byte < $ustartip[$i] || $byte > $uendip[$i]) {
			return false;
		}
	}

	return true;
}

/**
 * Expands an IPv6 address (possibly containing wildcards), e.g. ::ffff:1 to 0000:0000:0000:0000:0000:0000:ffff:0001.
 * Based on http://stackoverflow.com/a/12095836/753676
 * @param string $ip The IP address to expand.
 * @return string
 */
function as_ipv6_expand($ip)
{
	$ipv6_wildcard = false;
	$wildcards = '';
	$wildcards_matched = array();
	if (strpos($ip, "*") !== false) {
		$ipv6_wildcard = true;
	}
	if ($ipv6_wildcard) {
		$wildcards = explode(":", $ip);
		foreach ($wildcards as $index => $value) {
			if ($value == "*") {
				$wildcards_matched[] = count($wildcards) - 1 - $index;
				$wildcards[$index] = "0";
			}
		}
		$ip = implode($wildcards, ":");
	}

	$hex = unpack("H*hex", @inet_pton($ip));
	$ip = substr(preg_replace("/([0-9A-Fa-f]{4})/", "$1:", $hex['hex']), 0, -1);

	if ($ipv6_wildcard) {
		$wildcards = explode(":", $ip);
		foreach ($wildcards_matched as $value) {
			$i = count($wildcards) - 1 - $value;
			$wildcards[$i] = "*";
		}
		$ip = implode($wildcards, ":");
	}

	return $ip;
}

/**
 * Called after a database write $action performed by a user identified by $userid and/or $cookieid.
 * @param int $userid
 * @param string $cookieid
 * @param string $action
 * @param int $songid
 * @param int $reviewid
 * @param int $commentid
 */
function as_report_write_action($userid, $cookieid, $action, $songid, $reviewid, $commentid)
{
}

/**
 * Take note for rate limits that a user and/or the requesting IP just performed an action.
 * @param int $userid User performing the action.
 * @param string $action One of the AS_LIMIT_* constants defined above.
 * @return mixed
 */
function as_limits_increment($userid, $action)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'db/limits.php';

	$period = (int)(as_opt('db_time') / 3600);

	if (isset($userid))
		as_db_limits_user_add($userid, $action, $period, 1);

	as_db_limits_ip_add(as_remote_ip_address(), $action, $period, 1);
}
