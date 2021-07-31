<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Handling incoming thumbs (application level)


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
 * Check if $userid can thumb on $post, on the page $topage.
 * Return an HTML error to display if there was a problem, or false if it's OK.
 * @param $post
 * @param $thumb
 * @param $userid
 * @param $topage
 * @return bool|mixed|string
 */
function as_thumb_error_html($post, $thumb, $userid, $topage)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	// The 'signin', 'confirm', 'limit', 'userblock' and 'ipblock' permission errors are reported to the user here.
	// Others ('approve', 'level') prevent the buttons being clickable in the first place, in as_get_thumb_view(...)

	require_once AS_INCLUDE_DIR . 'app/users.php';
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	if ($post['hidden']) {
		return as_lang_html('main/thumb_disabled_hidden');
	}
	if ($post['queued']) {
		return as_lang_html('main/thumb_disabled_queued');
	}

	switch($post['basetype'])
	{
		case 'S':
			$allowVoting = as_opt('thumbing_on_qs');
			break;
		case 'R':
			$allowVoting = as_opt('thumbing_on_as');
			break;
		case 'C':
			$allowVoting = as_opt('thumbing_on_cs');
			break;
		default:
			$allowVoting = false;
			break;
	}

	if (!$allowVoting || (isset($post['userid']) && isset($userid) && $post['userid'] == $userid)) {
		// thumbing option should not have been presented (but could happen due to options change)
		return as_lang_html('main/thumb_not_allowed');
	}

	$permiterror = as_user_post_permit_error(($post['basetype'] == 'S') ? 'permit_thumb_q' : 'permit_thumb_a', $post, AS_LIMIT_VOTES);

	$errordownonly = !$permiterror && $thumb < 0;
	if ($errordownonly) {
		$permiterror = as_user_post_permit_error('permit_thumb_down', $post);
	}

	switch ($permiterror) {
		case false:
			return false;
			break;

		case 'signin':
			return as_insert_signin_links(as_lang_html('main/thumb_must_signin'), $topage);
			break;

		case 'confirm':
			return as_insert_signin_links(as_lang_html($errordownonly ? 'main/thumb_down_must_confirm' : 'main/thumb_must_confirm'), $topage);
			break;

		case 'limit':
			return as_lang_html('main/thumb_limit');
			break;

		default:
			return as_lang_html('users/no_permission');
			break;
	}
}


/**
 * Actually set (application level) the $thumb (-1/0/1) by $userid (with $handle and $cookieid) on $postid.
 * Handles user points, recounting and event reports as appropriate.
 * @param $post
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @param $thumb
 * @return void
 */
function as_thumb_set($post, $userid, $handle, $cookieid, $thumb)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'db/points.php';
	require_once AS_INCLUDE_DIR . 'db/hotness.php';
	require_once AS_INCLUDE_DIR . 'db/thumbs.php';
	require_once AS_INCLUDE_DIR . 'db/post-create.php';
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	$thumb = (int)min(1, max(-1, $thumb));
	$oldthumb = (int)as_db_userthumb_get($post['postid'], $userid);

	as_db_userthumb_set($post['postid'], $userid, $thumb);
	as_db_post_recount_thumbs($post['postid']);

	if (!in_array($post['basetype'], array('S', 'R', 'C'))) {
		return;
	}

	$prefix = strtolower($post['basetype']);

	if ($prefix === 'a') {
		as_db_post_acount_update($post['parentid']);
		as_db_unupaqcount_update();
	}

	$columns = array();

	if ($thumb > 0 || $oldthumb > 0) {
		$columns[] = $prefix . 'thumbsup';
	}

	if ($thumb < 0 || $oldthumb < 0) {
		$columns[] = $prefix . 'thumbsdown';
	}

	as_db_points_update_ifuser($userid, $columns);

	as_db_points_update_ifuser($post['userid'], array($prefix . 'thumbds', 'upthumbds', 'downthumbds'));

	if ($prefix === 'q') {
		as_db_hotness_update($post['postid']);
	}

	if ($thumb < 0) {
		$event = $prefix . '_thumb_down';
	} elseif ($thumb > 0) {
		$event = $prefix . '_thumb_up';
	} else {
		$event = $prefix . '_thumb_nil';
	}

	as_report_event($event, $userid, $handle, $cookieid, array(
		'postid' => $post['postid'],
		'userid' => $post['userid'],
		'thumb' => $thumb,
		'oldthumb' => $oldthumb,
	));
}


/**
 * Check if $userid can flag $post, on the page $topage.
 * Return an HTML error to display if there was a problem, or false if it's OK.
 * @param $post
 * @param $userid
 * @param $topage
 * @return bool|mixed|string
 */
function as_flag_error_html($post, $userid, $topage)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	// The 'signin', 'confirm', 'limit', 'userblock' and 'ipblock' permission errors are reported to the user here.
	// Others ('approve', 'level') prevent the flag button being shown, in as_page_q_post_rules(...)

	require_once AS_INCLUDE_DIR . 'db/selects.php';
	require_once AS_INCLUDE_DIR . 'app/options.php';
	require_once AS_INCLUDE_DIR . 'app/users.php';
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	if (is_array($post) && as_opt('flagging_of_posts') &&
		(!isset($post['userid']) || !isset($userid) || $post['userid'] != $userid)
	) {
		switch (as_user_post_permit_error('permit_flag', $post, AS_LIMIT_FLAGS)) {
			case 'signin':
				return as_insert_signin_links(as_lang_html('song/flag_must_signin'), $topage);
				break;

			case 'confirm':
				return as_insert_signin_links(as_lang_html('song/flag_must_confirm'), $topage);
				break;

			case 'limit':
				return as_lang_html('song/flag_limit');
				break;

			default:
				return as_lang_html('users/no_permission');
				break;

			case false:
				return false;
		}
	} else {
		return as_lang_html('song/flag_not_allowed'); // flagging option should not have been presented
	}
}


/**
 * Set (application level) a flag by $userid (with $handle and $cookieid) on $oldpost which belongs to $song.
 * Handles recounting, admin notifications and event reports as appropriate.
 * Returns true if the post should now be hidden because it has accumulated enough flags.
 * @param $oldpost
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @param $song
 * @return bool
 */
function as_flag_set_tohide($oldpost, $userid, $handle, $cookieid, $song)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'db/thumbs.php';
	require_once AS_INCLUDE_DIR . 'app/limits.php';
	require_once AS_INCLUDE_DIR . 'db/post-update.php';

	as_db_userflag_set($oldpost['postid'], $userid, true);
	as_db_post_recount_flags($oldpost['postid']);
	as_db_flaggedcount_update();

	switch ($oldpost['basetype']) {
		case 'S':
			$event = 'q_flag';
			break;

		case 'R':
			$event = 'a_flag';
			break;

		case 'C':
			$event = 'c_flag';
			break;
	}

	$post = as_db_select_with_pending(as_db_full_post_selectspec(null, $oldpost['postid']));

	as_report_event($event, $userid, $handle, $cookieid, array(
		'postid' => $oldpost['postid'],
		'oldpost' => $oldpost,
		'flagcount' => $post['flagcount'],
		'songid' => $song['postid'],
		'song' => $song,
	));

	return $post['flagcount'] >= as_opt('flagging_hide_after') && !$post['hidden'];
}


/**
 * Clear (application level) a flag on $oldpost by $userid (with $handle and $cookieid).
 * Handles recounting and event reports as appropriate.
 * @param $oldpost
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @return mixed
 */
function as_flag_clear($oldpost, $userid, $handle, $cookieid)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'db/thumbs.php';
	require_once AS_INCLUDE_DIR . 'app/limits.php';
	require_once AS_INCLUDE_DIR . 'db/post-update.php';

	as_db_userflag_set($oldpost['postid'], $userid, false);
	as_db_post_recount_flags($oldpost['postid']);
	as_db_flaggedcount_update();

	switch ($oldpost['basetype']) {
		case 'S':
			$event = 'q_unflag';
			break;

		case 'R':
			$event = 'a_unflag';
			break;

		case 'C':
			$event = 'c_unflag';
			break;
	}

	as_report_event($event, $userid, $handle, $cookieid, array(
		'postid' => $oldpost['postid'],
		'oldpost' => $oldpost,
	));
}


/**
 * Clear (application level) all flags on $oldpost by $userid (with $handle and $cookieid).
 * Handles recounting and event reports as appropriate.
 * @param $oldpost
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @return mixed
 */
function as_flags_clear_all($oldpost, $userid, $handle, $cookieid)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'db/thumbs.php';
	require_once AS_INCLUDE_DIR . 'app/limits.php';
	require_once AS_INCLUDE_DIR . 'db/post-update.php';

	as_db_userflags_clear_all($oldpost['postid']);
	as_db_post_recount_flags($oldpost['postid']);
	as_db_flaggedcount_update();

	switch ($oldpost['basetype']) {
		case 'S':
			$event = 'q_clearflags';
			break;

		case 'R':
			$event = 'a_clearflags';
			break;

		case 'C':
			$event = 'c_clearflags';
			break;
	}

	as_report_event($event, $userid, $handle, $cookieid, array(
		'postid' => $oldpost['postid'],
		'oldpost' => $oldpost,
	));
}
