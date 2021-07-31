<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-external-example/as-external-users.php
	Description: Example of how to integrate with your own user database


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


/*
	=========================================================================
	THIS FILE ALLOWS YOU TO INTEGRATE WITH AN EXISTING USER MANAGEMENT SYSTEM
	=========================================================================

	It is used if AS_EXTERNAL_USERS is set to true in as-config.php.
*/

if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../');
	exit;
}


/**
 * ==========================================================================
 * YOU MUST MODIFY THIS FUNCTION *BEFORE* APS CREATES ITS DATABASE
 * ==========================================================================
 *
 * You should return the appropriate MySQL column type to use for the userid,
 * for smooth integration with your existing users. Allowed options are:
 *
 * SMALLINT, SMALLINT UNSIGNED, MEDIUMINT, MEDIUMINT UNSIGNED, INT, INT UNSIGNED,
 * BIGINT, BIGINT UNSIGNED or VARCHAR(x) where x is the maximum length.
 */
function as_get_mysql_user_column_type()
{
	// Set this before anything else

	return null;

	/*
		Example 1 - suitable if:

		* You use textual user identifiers with a maximum length of 32

		return 'VARCHAR(32)';
	*/

	/*
		Example 2 - suitable if:

		* You use unsigned numerical user identifiers in an INT UNSIGNED column

		return 'INT UNSIGNED';
	*/
}


/**
 * ===========================================================================
 * YOU MUST MODIFY THIS FUNCTION, BUT CAN DO SO AFTER APS CREATES ITS DATABASE
 * ===========================================================================
 *
 * You should return an array containing URLs for the signin, signup and signout pages on
 * your site. These URLs will be used as appropriate within the APS site.
 *
 * You may return absolute or relative URLs for each page. If you do not want one of the links
 * to show, omit it from the array, or use null or an empty string.
 *
 * If you use absolute URLs, then return an array with the URLs in full (see example 1 below).
 *
 * If you use relative URLs, the URLs should start with $relative_url_prefix, followed by the
 * relative path from the root of the APS site to your signin page. Like in example 2 below, if
 * the APS site is in a subdirectory, $relative_url_prefix.'../' refers to your site root.
 *
 * Now, about $redirect_back_to_url. Let's say a user is viewing a page on the APS site, and
 * clicks a link to the signin URL that you returned from this function. After they log in using
 * the form on your main site, they want to automatically go back to the page on the APS site
 * where they came from. This can be done with an HTTP redirect, but how does your signin page
 * know where to redirect the user to? The solution is $redirect_back_to_url, which is the URL
 * of the page on the APS site where you should send the user once they've successfully logged
 * in. To implement this, you can add $redirect_back_to_url as a parameter to the signin URL
 * that you return from this function. Your signin page can then read it in from this parameter,
 * and redirect the user back to the page after they've logged in. The same applies for your
 * signup and signout pages. Note that the URL you are given in $redirect_back_to_url is
 * relative to the root of the APS site, so you may need to add something.
 */
function as_get_signin_links($relative_url_prefix, $redirect_back_to_url)
{
	// Until you edit this function, don't show signin, signup or signout links

	return array(
		'signin' => null,
		'signup' => null,
		'signout' => null
	);

	/*
		Example 1 - using absolute URLs, suitable if:

		* Your APS site:       http://as.mysite.com/
		* Your signin page:     http://www.mysite.com/signin
		* Your signup page:  http://www.mysite.com/signup
		* Your signout page:    http://www.mysite.com/signout

		return array(
			'signin' => 'http://www.mysite.com/signin',
			'signup' => 'http://www.mysite.com/signup',
			'signout' => 'http://www.mysite.com/signout',
		);
	*/

	/*
		Example 2 - using relative URLs, suitable if:

		* Your APS site:       http://www.mysite.com/as/
		* Your signin page:     http://www.mysite.com/signin.php
		* Your signup page:  http://www.mysite.com/signup.php
		* Your signout page:    http://www.mysite.com/signout.php

		return array(
			'signin' => $relative_url_prefix.'../signin.php',
			'signup' => $relative_url_prefix.'../signup.php',
			'signout' => $relative_url_prefix.'../signout.php',
		);
	*/

	/*
		Example 3 - using relative URLs, and implementing $redirect_back_to_url

		In this example, your pages signin.php, signup.php and signout.php should read in the
		parameter $_GET['redirect'], and redirect the user to the page specified by that
		parameter once they have successfully logged in, signuped or logged out.

		return array(
			'signin' => $relative_url_prefix.'../signin.php?redirect='.urlencode('as/'.$redirect_back_to_url),
			'signup' => $relative_url_prefix.'../signup.php?redirect='.urlencode('as/'.$redirect_back_to_url),
			'signout' => $relative_url_prefix.'../signout.php?redirect='.urlencode('as/'.$redirect_back_to_url),
		);
	*/
}


/**
 * ===========================================================================
 * YOU MUST MODIFY THIS FUNCTION, BUT CAN DO SO AFTER APS CREATES ITS DATABASE
 * ===========================================================================
 *
 * as_get_logged_in_user()
 *
 * You should check (using $_COOKIE, $_SESSION or whatever is appropriate) whether a user is
 * currently logged in. If not, return null. If so, return an array with the following elements:
 *
 * - userid: a user id appropriate for your response to as_get_mysql_user_column_type()
 * - publicusername: a user description you are willing to show publicly, e.g. the username
 * - email: the logged in user's email address
 * - passsalt: (optional) password salt specific to this user, used for form security codes
 * - level: one of the AS_USER_LEVEL_* values below to denote the user's privileges:
 *
 * AS_USER_LEVEL_BASIC, AS_USER_LEVEL_EDITOR, AS_USER_LEVEL_ADMIN, AS_USER_LEVEL_SUPER
 *
 * To indicate that the user is blocked you can also add an element 'blocked' with the value true.
 * Blocked users are not allowed to perform any write actions such as thumbing or posting.
 *
 * The result of this function will be passed to your other function as_get_logged_in_user_html()
 * so you may add any other elements to the returned array if they will be useful to you.
 *
 * Call as_db_connection() to get the connection to the APS database. If your database is shared with
 * APS, you can also use the various as_db_* functions to run queries.
 *
 * In order to access the admin interface of your APS site, ensure that the array element 'level'
 * contains AS_USER_LEVEL_ADMIN or AS_USER_LEVEL_SUPER when you are logged in.
 */
function as_get_logged_in_user()
{
	// Until you edit this function, nobody is ever logged in

	return null;

	/*
		Example 1 - suitable if:

		* You store the signin state and user in a PHP session
		* You use textual user identifiers that also serve as public usernames
		* Your database is shared with the APS site
		* Your database has a users table that contains emails
		* The administrator has the user identifier 'admin'

		session_start();

		if (isset($_SESSION['is_logged_in'])) {
			$userid = $_SESSION['logged_in_userid'];

			$result = as_db_read_one_assoc(as_db_query_sub(
				'SELECT email FROM users WHERE id=$',
				$userid
			));

			if (is_array($result)) {
				return array(
					'userid' => $userid,
					'publicusername' => $userid,
					'email' => $result['email'],
					'level' => ($userid == 'admin') ? AS_USER_LEVEL_ADMIN : AS_USER_LEVEL_BASIC
				);
			}
		}

		return null;
	*/

	/*
		Example 2 - suitable if:

		* You store a session ID inside a cookie
		* You use numerical user identifiers
		* Your database is shared with the APS site
		* Your database has a sessions table that maps session IDs to users
		* Your database has a users table that contains usernames, emails and a flag for admin privileges

		if (isset($_COOKIE['sessionid'])) {
			$result = as_db_read_one_assoc(as_db_query_sub(
				'SELECT userid, username, email, admin_flag FROM users WHERE userid=(SELECT userid FROM sessions WHERE sessionid=#)',
				$_COOKIE['sessionid']
			));

			if (is_array($result)) {
				return array(
					'userid' => $result['userid'],
					'publicusername' => $result['username'],
					'email' => $result['email'],
					'level' => $result['admin_flag'] ? AS_USER_LEVEL_ADMIN : AS_USER_LEVEL_BASIC
				);
			}
		}

		return null;
	*/
}


/**
 * ===========================================================================
 * YOU MUST MODIFY THIS FUNCTION, BUT CAN DO SO AFTER APS CREATES ITS DATABASE
 * ===========================================================================
 *
 * as_get_user_email($userid)
 *
 * Return the email address for user $userid, or null if you don't know it.
 *
 * Call as_db_connection() to get the connection to the APS database. If your database is shared with
 * APS, you can also use the various as_db_* functions to run queries.
 */
function as_get_user_email($userid)
{
	// Until you edit this function, always return null

	return null;

	/*
		Example 1 - suitable if:

		* Your database is shared with the APS site
		* Your database has a users table that contains emails

		$result = as_db_read_one_assoc(as_db_query_sub(
			'SELECT email FROM users WHERE userid=#',
			$userid
		));

		if (is_array($result))
			return $result['email'];

		return null;
	*/
}


/**
 * ===========================================================================
 * YOU MUST MODIFY THIS FUNCTION, BUT CAN DO SO AFTER APS CREATES ITS DATABASE
 * ===========================================================================
 *
 * as_get_userids_from_public($publicusernames)
 *
 * You should take the array of public usernames in $publicusernames, and return an array which
 * maps valid usernames to internal user ids. For each element of this array, the username should be
 * in the key, with the corresponding user id in the value. If your usernames are case- or accent-
 * insensitive, keys should contain the usernames as stored, not necessarily as in $publicusernames.
 *
 * Call as_db_connection() to get the connection to the APS database. If your database is shared with
 * APS, you can also use the various as_db_* functions to run queries. If you access this database or
 * any other, try to use a single query instead of one per user.
 */
function as_get_userids_from_public($publicusernames)
{
	// Until you edit this function, always return null

	return null;

	/*
		Example 1 - suitable if:

		* You use textual user identifiers that are also shown publicly

		$publictouserid = array();

		foreach ($publicusernames as $publicusername)
			$publictouserid[$publicusername] = $publicusername;

		return $publictouserid;
	*/

	/*
		Example 2 - suitable if:

		* You use numerical user identifiers
		* Your database is shared with the APS site
		* Your database has a users table that contains usernames

		$publictouserid = array();

		if (count($publicusernames)) {
			$escapedusernames = array();
			foreach ($publicusernames as $publicusername)
				$escapedusernames[] = "'" . as_db_escape_string($publicusername) . "'";

			$results = as_db_read_all_assoc(as_db_query_raw(
				'SELECT username, userid FROM users WHERE username IN (' . implode(',', $escapedusernames) . ')'
			));

			foreach ($results as $result)
				$publictouserid[$result['username']] = $result['userid'];
		}

		return $publictouserid;
	*/
}


/**
 * ===========================================================================
 * YOU MUST MODIFY THIS FUNCTION, BUT CAN DO SO AFTER APS CREATES ITS DATABASE
 * ===========================================================================
 *
 * as_get_public_from_userids($userids)
 *
 * This is exactly like as_get_userids_from_public(), but works in the other direction.
 *
 * You should take the array of user identifiers in $userids, and return an array which maps valid
 * userids to public usernames. For each element of this array, the userid you were given should
 * be in the key, with the corresponding username in the value.
 *
 * Call as_db_connection() to get the connection to the APS database. If your database is shared with
 * APS, you can also use the various as_db_* functions to run queries. If you access this database or
 * any other, try to use a single query instead of one per user.
 */
function as_get_public_from_userids($userids)
{
	// Until you edit this function, always return null

	return null;

	/*
		Example 1 - suitable if:

		* You use textual user identifiers that are also shown publicly

		$useridtopublic = array();

		foreach ($userids as $userid)
			$useridtopublic[$userid] = $userid;

		return $useridtopublic;
	*/

	/*
		Example 2 - suitable if:

		* You use numerical user identifiers
		* Your database is shared with the APS site
		* Your database has a users table that contains usernames

		$useridtopublic = array();

		if (count($userids)) {
			$escapeduserids = array();
			foreach ($userids as $userid)
				$escapeduserids[] = "'" . as_db_escape_string($userid) . "'";

			$results = as_db_read_all_assoc(as_db_query_raw(
				'SELECT username, userid FROM users WHERE userid IN (' . implode(',', $escapeduserids) . ')'
			));

			foreach ($results as $result)
				$useridtopublic[$result['userid']] = $result['username'];
		}

		return $useridtopublic;
	*/
}


/**
 * ==========================================================================
 * YOU MAY MODIFY THIS FUNCTION, BUT THE DEFAULT BELOW WILL WORK OK
 * ==========================================================================
 *
 * as_get_logged_in_user_html($logged_in_user, $relative_url_prefix)
 *
 * You should return HTML code which identifies the logged in user, to be displayed next to the
 * signout link on the APS pages. This HTML will only be shown to the logged in user themselves.
 * Note: the username MUST be escaped with htmlspecialchars() for general output, or urlencode()
 * for link URLs.
 *
 * $logged_in_user is the array that you returned from as_get_logged_in_user(). Hopefully this
 * contains enough information to generate the HTML without another database query, but if not,
 * call as_db_connection() to get the connection to the APS database.
 *
 * $relative_url_prefix is a relative URL to the root of the APS site, which may be useful if
 * you want to include a link that uses relative URLs. If the APS site is in a subdirectory of
 * your site, $relative_url_prefix.'../' refers to your site root (see example 1).
 *
 * If you don't know what to display for a user, you can leave the default below. This will
 * show the public username, linked to the APS profile page for the user.
 */
function as_get_logged_in_user_html($logged_in_user, $relative_url_prefix)
{
	// By default, show the public username linked to the APS profile page for the user

	$publicusername = $logged_in_user['publicusername'];

	return '<a href="' . as_path_html('user/' . $publicusername) . '" class="as-user-link">' . htmlspecialchars($publicusername) . '</a>';

	/*
		Example 1 - suitable if:

		* Your APS site:       http://www.mysite.com/as/
		* Your user pages:     http://www.mysite.com/user/[username]

		$publicusername = $logged_in_user['publicusername'];

		return '<a href="' . htmlspecialchars($relative_url_prefix . '../user/' . urlencode($publicusername)) .
			'" class="as-user-link">' . htmlspecialchars($publicusername) . '</a>';
	*/

	/*
		Example 2 - suitable if:

		* Your APS site:       http://as.mysite.com/
		* Your user pages:     http://www.mysite.com/[username]/
		* 16x16 user photos:   http://www.mysite.com/[username]/photo-small.jpg

		$publicusername = $logged_in_user['publicusername'];

		return '<a href="http://www.mysite.com/' . htmlspecialchars(urlencode($publicusername)) . '/" class="as-user-link">' .
			'<img src="http://www.mysite.com/' . htmlspecialchars(urlencode($publicusername)) . '/photo-small.jpg" ' .
			'style="width:16px; height:16px; border:none; margin-right:4px;">' . htmlspecialchars($publicusername) . '</a>';
	*/
}


/**
 * ==========================================================================
 * YOU MAY MODIFY THIS FUNCTION, BUT THE DEFAULT BELOW WILL WORK OK
 * ==========================================================================
 *
 * as_get_users_html($userids, $should_include_link, $relative_url_prefix)
 *
 * You should return an array of HTML to display for each user in $userids. For each element of
 * this array, the userid should be in the key, with the corresponding HTML in the value.
 * Note: the username MUST be escaped with htmlspecialchars() for general output, or urlencode()
 * for link URLs.
 *
 * Call as_db_connection() to get the connection to the APS database. If your database is shared with
 * APS, you can also use the various as_db_* functions to run queries. If you access this database or
 * any other, try to use a single query instead of one per user.
 *
 * If $should_include_link is true, the HTML may include links to user profile pages.
 * If $should_include_link is false, links should not be included in the HTML.
 *
 * $relative_url_prefix is a relative URL to the root of the APS site, which may be useful if
 * you want to include links that uses relative URLs. If the APS site is in a subdirectory of
 * your site, $relative_url_prefix.'../' refers to your site root (see example 1).
 *
 * If you don't know what to display for a user, you can leave the default below. This will
 * show the public username, linked to the APS profile page for each user.
 */
function as_get_users_html($userids, $should_include_link, $relative_url_prefix)
{
	// By default, show the public username linked to the APS profile page for each user

	$useridtopublic = as_get_public_from_userids($userids);

	$usershtml = array();

	foreach ($userids as $userid) {
		$publicusername = $useridtopublic[$userid];

		$usershtml[$userid] = htmlspecialchars($publicusername);

		if ($should_include_link)
			$usershtml[$userid] = '<a href="' . as_path_html('user/' . $publicusername) . '" class="as-user-link">' . $usershtml[$userid] . '</a>';
	}

	return $usershtml;

	/*
		Example 1 - suitable if:

		* Your APS site:       http://www.mysite.com/as/
		* Your user pages:     http://www.mysite.com/user/[username]

		$useridtopublic = as_get_public_from_userids($userids);

		foreach ($userids as $userid) {
			$publicusername = $useridtopublic[$userid];

			$usershtml[$userid] = htmlspecialchars($publicusername);

			if ($should_include_link) {
				$usershtml[$userid] = '<a href="' . htmlspecialchars($relative_url_prefix . '../user/' . urlencode($publicusername)) .
					'" class="as-user-link">' . $usershtml[$userid] . '</a>';
			}
		}

		return $usershtml;
	*/

	/*
		Example 2 - suitable if:

		* Your APS site:       http://as.mysite.com/
		* Your user pages:     http://www.mysite.com/[username]/
		* User photos (16x16): http://www.mysite.com/[username]/photo-small.jpg

		$useridtopublic = as_get_public_from_userids($userids);

		foreach ($userids as $userid) {
			$publicusername = $useridtopublic[$userid];

			$usershtml[$userid] = '<img src="http://www.mysite.com/' . htmlspecialchars(urlencode($publicusername)) . '/photo-small.jpg" ' .
				'style="width:16px; height:16px; border:0; margin-right:4px;">' . htmlspecialchars($publicusername);

			if ($should_include_link) {
				$usershtml[$userid] = '<a href="http://www.mysite.com/' . htmlspecialchars(urlencode($publicusername)) .
					'/" class="as-user-link">' . $usershtml[$userid] . '</a>';
			}
		}

		return $usershtml;
	*/
}


/**
 * ==========================================================================
 * YOU MAY MODIFY THIS FUNCTION, BUT THE DEFAULT BELOW WILL WORK OK
 * ==========================================================================
 *
 * as_avatar_html_from_userid($userid, $size, $padding)
 *
 * You should return some HTML for displaying the avatar of $userid on the page.
 * If you do not wish to show an avatar for this user, return null.
 *
 * $size contains the maximum width and height of the avatar to be displayed, in pixels.
 *
 * If $padding is true, the HTML you return should render to a square of $size x $size pixels,
 * even if the avatar is not square. This can be achieved using CSS padding - see function
 * as_get_avatar_blob_html(...) in as-app-format.php for an example. If $padding is false,
 * the HTML can render to anything which would fit inside a square of $size x $size pixels.
 *
 * Note that this function may be called many times to render an individual page, so it is not
 * a good idea to perform a database query each time it is called. Instead, you can use the fact
 * that before as_avatar_html_from_userid(...) is called, as_get_users_html(...) will have been
 * called with all the relevant users in the array $userids. So you can pull out the information
 * you need in as_get_users_html(...) and cache it in a global variable, for use in this function.
 */
function as_avatar_html_from_userid($userid, $size, $padding)
{
	// Show no avatars by default

	return null;

	/*
		Example 1 - suitable if:

		* All your avatars are square
		* Your APS site:       http://www.mysite.com/as/
		* Your avatar images:  http://www.mysite.com/avatar/[userid]-[size]x[size].jpg

		$htmlsize = (int)$size;

		return '<img src="http://www.mysite.com/avatar/' . htmlspecialchars($userid) . '-' . $htmlsize . 'x' . $htmlsize . '.jpg" ' .
			'width="' . $htmlsize . '" height="' . $htmlsize . '" class="as-avatar-image" alt=""/>';
	*/
}


/**
 * ==========================================================================
 * YOU MAY MODIFY THIS FUNCTION, BUT THE DEFAULT BELOW WILL WORK OK
 * ==========================================================================
 *
 * as_user_report_action($userid, $action)
 *
 * Informs you about an action by user $userid that modified the database, such as posting,
 * thumbing, etc... If you wish, you may use this to log user activity or monitor for abuse.
 *
 * Call as_db_connection() to get the connection to the APS database. If your database is shared with
 * APS, you can also use the various as_db_* functions to run queries.
 *
 * $action will be a string (such as 'q_edit') describing the action. These strings will match the
 * first $event parameter passed to the process_event(...) function in event modules. In fact, you might
 * be better off just using a plugin with an event module instead, since you'll get more information.
 *
 * FYI, you can get the IP address of the user from as_remote_ip_address().
 */
function as_user_report_action($userid, $action)
{
	// Do nothing by default
}
