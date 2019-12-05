<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Handling private or public messages (wall posts)


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
 * Returns an HTML string describing the reason why user $fromuserid cannot post on the wall of $touserid who has
 * user flags $touserflags. If there is no such reason the function returns false.
 * @param $fromuserid
 * @param $touserid
 * @param $touserflags
 * @return bool|mixed|string
 */
function as_wall_error_html($fromuserid, $touserid, $touserflags)
{
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	if (!AS_FINAL_EXTERNAL_USERS && as_opt('allow_user_walls')) {
		if (($touserflags & AS_USER_FLAGS_NO_WALL_POSTS) && !(isset($fromuserid) && $fromuserid == $touserid))
			return as_lang_html('profile/post_wall_blocked');

		else {
			switch (as_user_permit_error('permit_post_wall', AS_LIMIT_WALL_POSTS)) {
				case 'limit':
					return as_lang_html('profile/post_wall_limit');
					break;

				case 'signin':
					return as_insert_signin_links(as_lang_html('profile/post_wall_must_signin'), as_request());
					break;

				case 'confirm':
					return as_insert_signin_links(as_lang_html('profile/post_wall_must_confirm'), as_request());
					break;

				case 'approve':
					return strtr(as_lang_html('profile/post_wall_must_be_approved'), array(
						'^1' => '<a href="' . as_path_html('account') . '">',
						'^2' => '</a>',
					));
					break;

				case false:
					return false;
					break;
			}
		}
	}

	return as_lang_html('users/no_permission');
}


/**
 * Adds a post to the wall of user $touserid with handle $tohandle, containing $content in $format (e.g. '' for text or 'html')
 * The post is by user $userid with handle $handle, and $cookieid is the user's current cookie (used for reporting the event).
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @param $touserid
 * @param $tohandle
 * @param $content
 * @param $format
 * @return mixed
 */
function as_wall_add_post($userid, $handle, $cookieid, $touserid, $tohandle, $content, $format)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'app/format.php';
	require_once AS_INCLUDE_DIR . 'db/messages.php';

	$messageid = as_db_message_create($userid, $touserid, $content, $format, true);
	as_db_user_recount_posts($touserid);

	as_report_event('u_wall_post', $userid, $handle, $cookieid, array(
		'userid' => $touserid,
		'handle' => $tohandle,
		'messageid' => $messageid,
		'content' => $content,
		'format' => $format,
		'text' => as_viewer_text($content, $format),
	));

	return $messageid;
}


/**
 * Deletes the wall post described in $message (as obtained via as_db_recent_messages_selectspec()). The deletion was performed
 * by user $userid with handle $handle, and $cookieid is the user's current cookie (all used for reporting the event).
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @param $message
 */
function as_wall_delete_post($userid, $handle, $cookieid, $message)
{
	require_once AS_INCLUDE_DIR . 'db/messages.php';

	as_db_message_delete($message['messageid']);
	as_db_user_recount_posts($message['touserid']);

	as_report_event('u_wall_delete', $userid, $handle, $cookieid, array(
		'messageid' => $message['messageid'],
		'oldmessage' => $message,
	));
}


/**
 * Return the list of messages in $usermessages (as obtained via as_db_recent_messages_selectspec()) with additional
 * fields indicating what actions can be performed on them by the current user. The messages were retrieved beginning
 * at offset $start in the database. Currently only 'deleteable' is relevant.
 * @param $usermessages
 * @param $start
 * @return mixed
 */
function as_wall_posts_add_rules($usermessages, $start)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$userid = as_get_logged_in_userid();
	// reuse "Hiding or showing any post" and "Deleting hidden posts" permissions
	$userdeleteall = !(as_user_permit_error('permit_hide_show') || as_user_permit_error('permit_delete_hidden'));
	$userrecent = $start == 0 && isset($userid); // User can delete all of the recent messages they wrote on someone's wall...

	foreach ($usermessages as $key => $message) {
		if ($message['fromuserid'] != $userid)
			$userrecent = false; // ... until we come across one that they didn't write (which could be a reply)

		$usermessages[$key]['deleteable'] =
			$message['touserid'] == $userid || // if it's this user's wall
			($userrecent && $message['fromuserid'] == $userid) || // if it's one the user wrote that no one replied to yet
			$userdeleteall; // if the user has enough permissions  to delete from any wall
	}

	return $usermessages;
}


/**
 * Returns an element to add to $as_content['message_list']['messages'] for $message (as obtained via
 * as_db_recent_messages_selectspec() and then as_wall_posts_add_rules()).
 * @param $message
 * @return array
 */
function as_wall_post_view($message)
{
	require_once AS_INCLUDE_DIR . 'app/format.php';

	$options = as_message_html_defaults();

	$htmlfields = as_message_html_fields($message, $options);

	if ($message['deleteable']) {
		$htmlfields['form'] = array(
			'style' => 'light',

			'buttons' => array(
				'delete' => array(
					'tags' => 'name="m' . as_html($message['messageid']) . '_dodelete" onclick="return as_wall_post_click(' . as_js($message['messageid']) . ', this);"',
					'label' => as_lang_html('song/delete_button'),
					'popup' => as_lang_html('profile/delete_wall_post_popup'),
				),
			),
		);
	}

	return $htmlfields;
}


/**
 * Returns an element to add to $as_content['message_list']['messages'] with a link to view all wall posts
 * @param $handle
 * @param $start
 * @return array
 */
function as_wall_view_more_link($handle, $start)
{
	$url = as_path_html('user/' . $handle . '/wall', array('start' => $start));
	return array(
		'content' => '<a href="' . $url . '">' . as_lang_html('profile/wall_view_more') . '</a>',
	);
}


/**
 * Hides the private message described in $message (as obtained via as_db_messages_inbox_selectspec() or as_db_messages_outbox_selectspec()).
 * If both sender and receiver have hidden the message, it gets deleted from the database.
 * Note: currently no event is reported here, so $handle/$cookieid are unused.
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @param $message
 * @param $box
 */
function as_pm_delete($userid, $handle, $cookieid, $message, $box)
{
	require_once AS_INCLUDE_DIR . 'db/messages.php';

	as_db_message_user_hide($message['messageid'], $box);
	as_db_message_delete($message['messageid'], false);
}
