<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for user page showing all user wall posts


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

require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'app/messages.php';


// Check we're not using single-sign on integration, which doesn't allow walls

if (AS_FINAL_EXTERNAL_USERS)
	as_fatal_error('User accounts are handled by external code');


// $handle, $userhtml are already set by /as-include/page/user.php

$start = as_get_start();


// Find the songs for this user

list($useraccount, $usermessages) = as_db_select_with_pending(
	as_db_user_account_selectspec($handle, false),
	as_db_recent_messages_selectspec(null, null, $handle, false, as_opt_if_loaded('page_size_wall'), $start)
);

if (!is_array($useraccount)) // check the user exists
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';


// Perform pagination

$pagesize = as_opt('page_size_wall');
$count = $useraccount['wallposts'];
$signinuserid = as_get_logged_in_userid();

$usermessages = array_slice($usermessages, 0, $pagesize);
$usermessages = as_wall_posts_add_rules($usermessages, $start);


// Process deleting or adding a wall post (similar but not identical code to qq-page-user-profile.php)

$errors = array();

$wallposterrorhtml = as_wall_error_html($signinuserid, $useraccount['userid'], $useraccount['flags']);

foreach ($usermessages as $message) {
	if ($message['deleteable'] && as_clicked('m' . $message['messageid'] . '_dodelete')) {
		if (!as_check_form_security_code('wall-' . $useraccount['handle'], as_post_text('code'))) {
			$errors['page'] = as_lang_html('misc/form_security_again');
		} else {
			as_wall_delete_post($signinuserid, as_get_logged_in_handle(), as_cookie_get(), $message);
			as_redirect(as_request(), $_GET);
		}
	}
}

if (as_clicked('dowallpost')) {
	$inmessage = as_post_text('message');

	if (!strlen($inmessage)) {
		$errors['message'] = as_lang('profile/post_wall_empty');
	} elseif (!as_check_form_security_code('wall-' . $useraccount['handle'], as_post_text('code'))) {
		$errors['message'] = as_lang_html('misc/form_security_again');
	} elseif (!$wallposterrorhtml) {
		as_wall_add_post($signinuserid, as_get_logged_in_handle(), as_cookie_get(), $useraccount['userid'], $useraccount['handle'], $inmessage, '');
		as_redirect(as_request());
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html_sub('profile/wall_for_x', $userhtml);
$as_content['error'] = @$errors['page'];

$as_content['message_list'] = array(
	'tags' => 'id="wallmessages"',

	'form' => array(
		'tags' => 'name="wallpost" method="post" action="' . as_self_html() . '"',
		'style' => 'tall',
		'hidden' => array(
			'as_click' => '', // for simulating clicks in Javascript
			'handle' => as_html($useraccount['handle']),
			'start' => as_html($start),
			'code' => as_get_form_security_code('wall-' . $useraccount['handle']),
		),
	),

	'messages' => array(),
);

if ($start == 0) { // only allow posting on first page
	if ($wallposterrorhtml) {
		$as_content['message_list']['error'] = $wallposterrorhtml; // an error that means we are not allowed to post
	} else {
		$as_content['message_list']['form']['fields'] = array(
			'message' => array(
				'tags' => 'name="message" id="message"',
				'value' => as_html(@$inmessage, false),
				'rows' => 2,
				'error' => as_html(@$errors['message']),
			),
		);

		$as_content['message_list']['form']['buttons'] = array(
			'post' => array(
				'tags' => 'name="dowallpost" onclick="return as_submit_wall_post(this, false);"',
				'label' => as_lang_html('profile/post_wall_button'),
			),
		);
	}
}

foreach ($usermessages as $message) {
	$as_content['message_list']['messages'][] = as_wall_post_view($message);
}

$as_content['page_links'] = as_html_page_links(as_request(), $start, $pagesize, $count, as_opt('pages_prev_next'));


// Sub menu for navigation in user pages

$ismyuser = isset($signinuserid) && $signinuserid == (AS_FINAL_EXTERNAL_USERS ? $userid : $useraccount['userid']);
$fullname = as_db_name_find_by_handle($handle);
$as_content['navigation']['sub'] = as_user_sub_navigation($fullname, $handle, 'wall', $ismyuser);


return $as_content;
