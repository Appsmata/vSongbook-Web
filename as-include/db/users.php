<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database-level access to user management tables (if not using single sign-on)


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
 * Return the expected value for the passcheck column given the $password and password $salt
 * @param $password
 * @param $salt
 * @return mixed|string
 */
function as_db_calc_passcheck($password, $salt)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	return sha1(substr($salt, 0, 8) . $password . substr($salt, 8));
}


/**
 * Create a new user in the database with $email, $password, $handle, privilege $level, and $ip address
 * @param $email
 * @param $password
 * @param $handle
 * @param $level
 * @param $ip
 * @return mixed
 */
function as_db_user_create($firstname, $lastname, $country, $mobile, $gender, $city, $church, $handle, $email, $password, $level, $ip)
{
	require_once AS_INCLUDE_DIR . 'util/string.php';

	$ipHex = bin2hex(@inet_pton($ip));

	if (AS_PASSWORD_HASH) {
		as_db_query_sub(
			'INSERT INTO ^users (created, createip, firstname, lastname, country, mobile, gender, city, church, email, passhash, level, handle, signedin, signinip) ' .
			'VALUES (NOW(), UNHEX($), $, $, $, $, $, $, $, $, $, #, $, NOW(), UNHEX($))',
			$ipHex, $firstname, $lastname, $country, $mobile, $gender, $city, $church, $email, isset($password) ? password_hash($password, PASSWORD_BCRYPT) : null, (int)$level, $handle, $ipHex
		);
	} else {
		$salt = isset($password) ? as_random_alphanum(16) : null;

		as_db_query_sub(
			'INSERT INTO ^users (created, createip, firstname, lastname, country, mobile, gender, city, church, email, passsalt, passcheck, level, handle, signedin, signinip) ' .
			'VALUES (NOW(), UNHEX($), $, $, $, $, $, $, $, $, $, UNHEX($), #, $, NOW(), UNHEX($))',
			$ipHex, $firstname, $lastname, $country, $mobile, $gender, $city, $church, $email, $salt, isset($password) ? as_db_calc_passcheck($password, $salt) : null, (int)$level, $handle, $ipHex
		);
	}


	return as_db_last_insert_id();
}


/**
 * Delete user $userid from the database, along with everything they have ever done (to the extent that it's possible)
 * @param $userid
 */
function as_db_user_delete($userid)
{
	as_db_query_sub('UPDATE ^posts SET lastuserid=NULL WHERE lastuserid=$', $userid);
	as_db_query_sub('DELETE FROM ^userpoints WHERE userid=$', $userid);
	as_db_query_sub('DELETE FROM ^blobs WHERE blobid=(SELECT avatarblobid FROM ^users WHERE userid=$)', $userid);
	as_db_query_sub('DELETE FROM ^users WHERE userid=$', $userid);

	// All the queries below should be superfluous due to foreign key constraints, but just in case the user switched to MyISAM.
	// Note also that private messages to/from that user are kept since we don't have all the keys we need to delete efficiently.

	as_db_query_sub('UPDATE ^posts SET userid=NULL WHERE userid=$', $userid);
	as_db_query_sub('DELETE FROM ^usersignins WHERE userid=$', $userid);
	as_db_query_sub('DELETE FROM ^userprofile WHERE userid=$', $userid);
	as_db_query_sub('DELETE FROM ^userfavorites WHERE userid=$', $userid);
	as_db_query_sub('DELETE FROM ^userevents WHERE userid=$', $userid);
	as_db_query_sub('DELETE FROM ^userthumbs WHERE userid=$', $userid);
	as_db_query_sub('DELETE FROM ^userlimits WHERE userid=$', $userid);
}


/**
 * Return the ids of all users in the database which match $email (should be one or none)
 * @param $email
 * @return array
 */
function as_db_user_find_by_email($email)
{
	return as_db_read_all_values(as_db_query_sub(
		'SELECT userid FROM ^users WHERE email=$',
		$email
	));
}

/**
 * Return the ids of all users in the database which match $handle (=username), should be one or none
 * @param $handle
 * @return array
 */
function as_db_name_find_by_handle($handle)
{
	$username = as_db_read_all_values(as_db_query_sub(
		'SELECT CONCAT(firstname, " ", lastname) AS fullname FROM ^users WHERE handle=$',
		$handle
	));
	return $username[0];
}

/**
 * Return the ids of all users in the database which match $mobile (should be one or none)
 * @param $email
 * @return array
 */
function as_db_user_find_by_mobile($mobile)
{
	return as_db_read_all_values(as_db_query_sub(
		'SELECT userid FROM ^users WHERE mobile=$',
		$mobile
	));
}

/**
 * Return the ids of all cities in the database which match $title (should be one or none)
 * @param $email
 * @return array
 */
function as_db_city_find_by_title($title, $country)
{
	$matchrows = as_db_read_all_values(as_db_query_sub( 'SELECT cityid FROM ^cities WHERE title=$', $title ));
	if (count($matchrows) == 1) $inrowid = $matchrows[0];
	else {
		as_db_query_sub('INSERT INTO ^cities (title, country) VALUES ($, $)', $title, $country);
		$inrowid = as_db_last_insert_id();
	}
	return $inrowid;
}

/**
 * Return the ids of all churches in the database which match $title (should be one or none)
 * @param $email
 * @return array
 */
function as_db_church_find_by_title($title, $city)
{
	$matchrows = as_db_read_all_values(as_db_query_sub( 'SELECT churchid FROM ^churches WHERE title=$', $title ));
	if (count($matchrows) == 1) $inrowid = $matchrows[0];
	else {
		as_db_query_sub('INSERT INTO ^churches (title, city) VALUES ($, #)', $title, $city);
		$inrowid = as_db_last_insert_id();
	}
	return $inrowid;
}

function as_db_user_find_by_handle($handle)
{
	return as_db_read_all_values(as_db_query_sub(
		'SELECT userid FROM ^users WHERE handle=$',
		$handle
	));
}


/**
 * Return an array mapping each userid in $userids that can be found to that user's handle
 * @param $userids
 * @return array
 */
function as_db_user_get_userid_handles($userids)
{
	if (count($userids)) {
		return as_db_read_all_assoc(as_db_query_sub(
			'SELECT userid, handle FROM ^users WHERE userid IN (#)',
			$userids
		), 'userid', 'handle');
	}

	return array();
}


/**
 * Return an array mapping mapping each handle in $handle that can be found to that user's userid
 * @param $handles
 * @return array
 */
function as_db_user_get_handle_userids($handles)
{
	if (count($handles)) {
		return as_db_read_all_assoc(as_db_query_sub(
			'SELECT handle, userid FROM ^users WHERE handle IN ($)',
			$handles
		), 'handle', 'userid');
	}

	return array();
}


/**
 * Set $field of $userid to $value in the database users table. If the $fields parameter is an array, the $value
 * parameter is ignored and each element of the array is treated as a key-value pair of user fields and values.
 * @param mixed $userid
 * @param string|array $fields
 * @param string|null $value
 */
function as_db_user_set($userid, $fields, $value = null)
{
	if (!is_array($fields)) {
		$fields = array(
			$fields => $value,
		);
	}

	$sql = 'UPDATE ^users SET ';
	foreach ($fields as $field => $fieldValue) {
		$sql .= as_db_escape_string($field) . ' = $, ';
	}
	$sql = substr($sql, 0, -2) . ' WHERE userid = $';

	$params = array_values($fields);
	$params[] = $userid;

	as_db_query_sub_params($sql, $params);
}


/**
 * Set the password of $userid to $password, and reset their salt at the same time
 * @param $userid
 * @param $password
 * @return mixed
 */
function as_db_user_set_password($userid, $password)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'util/string.php';

	if (AS_PASSWORD_HASH) {
		as_db_query_sub(
			'UPDATE ^users SET passhash=$, passsalt=NULL, passcheck=NULL WHERE userid=$',
			password_hash($password, PASSWORD_BCRYPT), $userid
		);
	} else {
		$salt = as_random_alphanum(16);

		as_db_query_sub(
			'UPDATE ^users SET passsalt=$, passcheck=UNHEX($) WHERE userid=$',
			$salt, as_db_calc_passcheck($password, $salt), $userid
		);
	}
}


/**
 * Switch on the $flag bit of the flags column for $userid if $set is true, or switch off otherwise
 * @param $userid
 * @param $flag
 * @param $set
 */
function as_db_user_set_flag($userid, $flag, $set)
{
	as_db_query_sub(
		'UPDATE ^users SET flags=flags' . ($set ? '|' : '&~') . '# WHERE userid=$',
		$flag, $userid
	);
}


/**
 * Return a random string to be used for a user's emailcode column
 */
function as_db_user_rand_emailcode()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'util/string.php';

	return as_random_alphanum(8);
}


/**
 * Return a random string to be used for a user's sessioncode column (for browser session cookies)
 */
function as_db_user_rand_sessioncode()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'util/string.php';

	return as_random_alphanum(8);
}


/**
 * Set a row in the database user profile table to store $value for $field for $userid
 * @param $userid
 * @param $field
 * @param $value
 */
function as_db_user_profile_set($userid, $field, $value)
{
	as_db_query_sub(
		'INSERT INTO ^userprofile (userid, title, content) VALUES ($, $, $) ' .
		'ON DUPLICATE KEY UPDATE content = VALUES(content)',
		$userid, $field, $value
	);
}


/**
 * Note in the database that $userid just logged in from $ip address
 * @param $userid
 * @param $ip
 */
function as_db_user_logged_in($userid, $ip)
{
	as_db_query_sub(
		'UPDATE ^users SET signedin=NOW(), signinip=UNHEX($) WHERE userid=$',
		bin2hex(@inet_pton($ip)), $userid
	);
}


/**
 * Note in the database that $userid just performed a write operation from $ip address
 * @param $userid
 * @param $ip
 */
function as_db_user_written($userid, $ip)
{
	as_db_query_sub(
		'UPDATE ^users SET written=NOW(), writeip=UNHEX($) WHERE userid=$',
		bin2hex(@inet_pton($ip)), $userid
	);
}


/**
 * Add an external signin in the database for $source and $identifier for user $userid
 * @param $userid
 * @param $source
 * @param $identifier
 */
function as_db_user_signin_add($userid, $source, $identifier)
{
	as_db_query_sub(
		'INSERT INTO ^usersignins (userid, source, identifier, identifiermd5) ' .
		'VALUES ($, $, $, UNHEX($))',
		$userid, $source, $identifier, md5($identifier)
	);
}


/**
 * Return some information about the user with external signin $source and $identifier in the database, if a match is found
 * @param $source
 * @param $identifier
 * @return array
 */
function as_db_user_signin_find($source, $identifier)
{
	return as_db_read_all_assoc(as_db_query_sub(
		'SELECT ^usersignins.userid, handle, email FROM ^usersignins LEFT JOIN ^users ON ^usersignins.userid=^users.userid ' .
		'WHERE source=$ AND identifiermd5=UNHEX($) AND identifier=$',
		$source, md5($identifier), $identifier
	));
}


/**
 * Lock all tables if $sync is true, otherwise unlock them. Used to synchronize creation of external signin mappings.
 * @param $sync
 */
function as_db_user_signin_sync($sync)
{
	if ($sync) { // need to lock all tables since any could be used by a plugin's event module
		$tables = as_db_list_tables();

		$locks = array();
		foreach ($tables as $table)
			$locks[] = $table . ' WRITE';

		as_db_query_sub('LOCK TABLES ' . implode(', ', $locks));

	} else {
		as_db_query_sub('UNLOCK TABLES');
	}
}


/**
 * Reset the full set of context-specific (currently, per category) user levels for user $userid to $userlevels, where
 * $userlevels is an array of arrays, the inner arrays containing items 'entitytype', 'entityid' and 'level'.
 * @param $userid
 * @param $userlevels
 */
function as_db_user_levels_set($userid, $userlevels)
{
	as_db_query_sub(
		'DELETE FROM ^userlevels WHERE userid=$',
		$userid
	);

	foreach ($userlevels as $userlevel) {
		as_db_query_sub(
			'INSERT INTO ^userlevels (userid, entitytype, entityid, level) VALUES ($, $, #, #) ' .
			'ON DUPLICATE KEY UPDATE level = VALUES(level)',
			$userid, $userlevel['entitytype'], $userlevel['entityid'], $userlevel['level']
		);
	}
}


/**
 * Get the information required for sending a mailing to the next $count users with userids greater than $lastuserid
 * @param $lastuserid
 * @param $count
 * @return array
 */
function as_db_users_get_mailing_next($lastuserid, $count)
{
	return as_db_read_all_assoc(as_db_query_sub(
		'SELECT userid, email, handle, emailcode, flags, level FROM ^users WHERE userid># ORDER BY userid LIMIT #',
		$lastuserid, $count
	));
}


/**
 * Update the cached count of the number of users who are awaiting approval after registration
 */
function as_db_uapprovecount_update()
{
	if (as_should_update_counts() && !AS_FINAL_EXTERNAL_USERS) {
		as_db_query_sub(
			"INSERT INTO ^options (title, content) " .
			"SELECT 'cache_uapprovecount', COUNT(*) FROM ^users " .
			"WHERE level < # AND NOT (flags & #) " .
			"ON DUPLICATE KEY UPDATE content = VALUES(content)",
			AS_USER_LEVEL_APPROVED, AS_USER_FLAGS_USER_BLOCKED
		);
	}
}
