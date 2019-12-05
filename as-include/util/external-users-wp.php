<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: External user functions for WordPress integration


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


function as_get_mysql_user_column_type()
{
	return 'BIGINT UNSIGNED';
}


function as_get_signin_links($relative_url_prefix, $redirect_back_to_url)
{
	return array(
		'signin' => wp_signin_url(as_opt('site_url') . $redirect_back_to_url),
		'signup' => function_exists('wp_registration_url') ? wp_registration_url() : site_url('wp-signin.php?action=signup'),
		'signout' => strtr(wp_signout_url(), array('&amp;' => '&')),
	);
}


function as_get_logged_in_user()
{
	$wordpressuser = wp_get_current_user();

	if ($wordpressuser->ID == 0)
		return null;

	else {
		if (current_user_can('administrator'))
			$level = AS_USER_LEVEL_ADMIN;
		elseif (current_user_can('editor'))
			$level = AS_USER_LEVEL_EDITOR;
		elseif (current_user_can('contributor'))
			$level = AS_USER_LEVEL_EXPERT;
		else
			$level = AS_USER_LEVEL_BASIC;

		return array(
			'userid' => $wordpressuser->ID,
			'publicusername' => $wordpressuser->user_nicename,
			'email' => $wordpressuser->user_email,
			'level' => $level,
		);
	}
}


function as_get_user_email($userid)
{
	$user = get_userdata($userid);

	return @$user->user_email;
}


function as_get_userids_from_public($publicusernames)
{
	global $wpdb;

	if (count($publicusernames))
		return as_db_read_all_assoc(as_db_query_sub(
			'SELECT user_nicename, ID FROM ' . $wpdb->base_prefix . 'users WHERE user_nicename IN ($)',
			$publicusernames
		), 'user_nicename', 'ID');
	else
		return array();
}


function as_get_public_from_userids($userids)
{
	global $wpdb, $as_cache_wp_user_emails;

	if (count($userids)) {
		$useridtopublic = array();
		$as_cache_wp_user_emails = array();

		$userfields = as_db_read_all_assoc(as_db_query_sub(
			'SELECT ID, user_nicename, user_email FROM ' . $wpdb->base_prefix . 'users WHERE ID IN (#)',
			$userids
		), 'ID');

		foreach ($userfields as $id => $fields) {
			$useridtopublic[$id] = $fields['user_nicename'];
			$as_cache_wp_user_emails[$id] = $fields['user_email'];
		}

		return $useridtopublic;

	} else
		return array();
}


function as_get_logged_in_user_html($logged_in_user, $relative_url_prefix)
{
	$publicusername = $logged_in_user['publicusername'];

	return '<a href="' . as_path_html('user/' . $publicusername) . '" class="as-user-link">' . htmlspecialchars($publicusername) . '</a>';
}


function as_get_users_html($userids, $should_include_link, $relative_url_prefix)
{
	$useridtopublic = as_get_public_from_userids($userids);

	$usershtml = array();

	foreach ($userids as $userid) {
		$publicusername = $useridtopublic[$userid];

		$usershtml[$userid] = htmlspecialchars($publicusername);

		if ($should_include_link)
			$usershtml[$userid] = '<a href="' . as_path_html('user/' . $publicusername) . '" class="as-user-link">' . $usershtml[$userid] . '</a>';
	}

	return $usershtml;
}


function as_avatar_html_from_userid($userid, $size, $padding)
{
	require_once AS_INCLUDE_DIR . 'app/format.php';

	global $as_cache_wp_user_emails;

	if (isset($as_cache_wp_user_emails[$userid]))
		return as_get_gravatar_html($as_cache_wp_user_emails[$userid], $size);

	return null;
}


function as_user_report_action($userid, $action)
{
}
