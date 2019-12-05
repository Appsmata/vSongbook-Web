<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Creating songs, reviews and comments (application level)


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

require_once AS_INCLUDE_DIR . 'db/maxima.php';
require_once AS_INCLUDE_DIR . 'db/post-create.php';
require_once AS_INCLUDE_DIR . 'db/points.php';
require_once AS_INCLUDE_DIR . 'db/hotness.php';
require_once AS_INCLUDE_DIR . 'util/string.php';


/**
 * Return value to store in database combining $notify and $email values entered by user $userid (or null for anonymous)
 * @param $userid
 * @param $notify
 * @param $email
 * @return null|string
 */
function as_combine_notify_email($userid, $notify, $email)
{
	return $notify ? (empty($email) ? (isset($userid) ? '@' : null) : $email) : null;
}


/**
 * Add a song (application level) - create record, update appropriate counts, index it, send notifications.
 * If song is follow-on from an review, $followreview should contain review database record, otherwise null.
 * See /as-include/app/posts.php for a higher-level function which is easier to use.
 * @param $followreview
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @param $title
 * @param $content
 * @param $format
 * @param $text
 * @param $tagstring
 * @param $notify
 * @param $email
 * @param $categoryid
 * @param $extravalue
 * @param bool $queued
 * @param $name
 * @return mixed
 */
function as_song_create($followreview, $userid, $handle, $cookieid, $title, $content, $format, $text, $tagstring, $notify, $email,
	$categoryid = null, $extravalue = null, $queued = false, $name = null)
{
	require_once AS_INCLUDE_DIR . 'db/selects.php';

	$postid = as_db_post_create($queued ? 'S_QUEUED' : 'S', @$followreview['postid'], $userid, isset($userid) ? null : $cookieid,
		as_remote_ip_address(), $title, $content, $format, $tagstring, as_combine_notify_email($userid, $notify, $email),
		$categoryid, isset($userid) ? null : $name);

	if (isset($extravalue)) {
		require_once AS_INCLUDE_DIR . 'db/metas.php';
		as_db_postmeta_set($postid, 'as_q_extra', $extravalue);
	}

	as_db_posts_calc_category_path($postid);
	as_db_hotness_update($postid);

	if ($queued) {
		as_db_queuedcount_update();

	} else {
		as_post_index($postid, 'S', $postid, @$followreview['postid'], $title, $content, $format, $text, $tagstring, $categoryid);
		as_update_counts_for_q($postid);
		as_db_points_update_ifuser($userid, 'qposts');
	}

	as_report_event($queued ? 'q_queue' : 'q_post', $userid, $handle, $cookieid, array(
		'postid' => $postid,
		'parentid' => @$followreview['postid'],
		'parent' => $followreview,
		'title' => $title,
		'content' => $content,
		'format' => $format,
		'text' => $text,
		'tags' => $tagstring,
		'categoryid' => $categoryid,
		'extra' => $extravalue,
		'name' => $name,
		'notify' => $notify,
		'email' => $email,
	));

	return $postid;
}


/**
 * Perform various common cached count updating operations to reflect changes in the song whose id is $postid
 * @param $postid
 */
function as_update_counts_for_q($postid)
{
	if (isset($postid)) // post might no longer exist
		as_db_category_path_qcount_update(as_db_post_get_category_path($postid));

	as_db_qcount_update();
	as_db_unaqcount_update();
	as_db_unselqcount_update();
	as_db_unupaqcount_update();
	as_db_tagcount_update();
}


/**
 * Return an array containing the elements of $inarray whose key is in $keys
 * @param $inarray
 * @param $keys
 * @return array
 */
function as_array_filter_by_keys($inarray, $keys)
{
	$outarray = array();

	foreach ($keys as $key) {
		if (isset($inarray[$key]))
			$outarray[$key] = $inarray[$key];
	}

	return $outarray;
}


/**
 * Suspend the indexing (and unindexing) of posts via as_post_index(...) and as_post_unindex(...)
 * if $suspend is true, otherwise reinstate it. A counter is kept to allow multiple calls.
 * @param bool $suspend
 */
function as_suspend_post_indexing($suspend = true)
{
	global $as_post_indexing_suspended;

	$as_post_indexing_suspended += ($suspend ? 1 : -1);
}


/**
 * Add post $postid (which comes under $songid) of $type (Q/A/C) to the database index, with $title, $text,
 * $tagstring and $categoryid. Calls through to all installed search modules.
 * @param $postid
 * @param $type
 * @param $songid
 * @param $parentid
 * @param $title
 * @param $content
 * @param $format
 * @param $text
 * @param $tagstring
 * @param $categoryid
 */
function as_post_index($postid, $type, $songid, $parentid, $title, $content, $format, $text, $tagstring, $categoryid)
{
	global $as_post_indexing_suspended;

	if ($as_post_indexing_suspended > 0)
		return;

	// Send through to any search modules for indexing

	$searches = as_load_modules_with('search', 'index_post');
	foreach ($searches as $search)
		$search->index_post($postid, $type, $songid, $parentid, $title, $content, $format, $text, $tagstring, $categoryid);
}


/**
 * Add an review (application level) - create record, update appropriate counts, index it, send notifications.
 * $song should contain database record for the song this is an review to.
 * See /as-include/app/posts.php for a higher-level function which is easier to use.
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @param $content
 * @param $format
 * @param $text
 * @param $notify
 * @param $email
 * @param $song
 * @param bool $queued
 * @param $name
 * @return mixed
 */
function as_review_create($userid, $handle, $cookieid, $content, $format, $text, $notify, $email, $song, $queued = false, $name = null)
{
	$postid = as_db_post_create($queued ? 'R_QUEUED' : 'R', $song['postid'], $userid, isset($userid) ? null : $cookieid,
		as_remote_ip_address(), null, $content, $format, null, as_combine_notify_email($userid, $notify, $email),
		$song['categoryid'], isset($userid) ? null : $name);

	as_db_posts_calc_category_path($postid);

	if ($queued) {
		as_db_queuedcount_update();

	} else {
		if ($song['type'] == 'S') // don't index review if parent song is hidden or queued
			as_post_index($postid, 'R', $song['postid'], $song['postid'], null, $content, $format, $text, null, $song['categoryid']);

		as_update_q_counts_for_a($song['postid']);
		as_db_points_update_ifuser($userid, 'aposts');
	}

	as_report_event($queued ? 'a_queue' : 'a_post', $userid, $handle, $cookieid, array(
		'postid' => $postid,
		'parentid' => $song['postid'],
		'parent' => $song,
		'content' => $content,
		'format' => $format,
		'text' => $text,
		'categoryid' => $song['categoryid'],
		'name' => $name,
		'notify' => $notify,
		'email' => $email,
	));

	return $postid;
}


/**
 * Perform various common cached count updating operations to reflect changes in an review of song $songid
 * @param $songid
 */
function as_update_q_counts_for_a($songid)
{
	as_db_post_acount_update($songid);
	as_db_hotness_update($songid);
	as_db_acount_update();
	as_db_unaqcount_update();
	as_db_unupaqcount_update();
}


/**
 * Add a comment (application level) - create record, update appropriate counts, index it, send notifications.
 * $song should contain database record for the song this is part of (as direct or comment on Q's review).
 * If this is a comment on an review, $review should contain database record for the review, otherwise null.
 * $commentsfollows should contain database records for all previous comments on the same song or review,
 * but it can also contain other records that are ignored.
 * See /as-include/app/posts.php for a higher-level function which is easier to use.
 * @param $userid
 * @param $handle
 * @param $cookieid
 * @param $content
 * @param $format
 * @param $text
 * @param $notify
 * @param $email
 * @param $song
 * @param $parent
 * @param $commentsfollows
 * @param bool $queued
 * @param $name
 * @return mixed
 */
function as_comment_create($userid, $handle, $cookieid, $content, $format, $text, $notify, $email, $song, $parent, $commentsfollows, $queued = false, $name = null)
{
	require_once AS_INCLUDE_DIR . 'app/emails.php';
	require_once AS_INCLUDE_DIR . 'app/options.php';
	require_once AS_INCLUDE_DIR . 'app/format.php';
	require_once AS_INCLUDE_DIR . 'util/string.php';

	if (!isset($parent))
		$parent = $song; // for backwards compatibility with old review parameter

	$postid = as_db_post_create($queued ? 'C_QUEUED' : 'C', $parent['postid'], $userid, isset($userid) ? null : $cookieid,
		as_remote_ip_address(), null, $content, $format, null, as_combine_notify_email($userid, $notify, $email),
		$song['categoryid'], isset($userid) ? null : $name);

	as_db_posts_calc_category_path($postid);

	if ($queued) {
		as_db_queuedcount_update();

	} else {
		if ($song['type'] == 'S' && ($parent['type'] == 'S' || $parent['type'] == 'R')) { // only index if antecedents fully visible
			as_post_index($postid, 'C', $song['postid'], $parent['postid'], null, $content, $format, $text, null, $song['categoryid']);
		}

		as_db_points_update_ifuser($userid, 'cposts');
		as_db_ccount_update();
	}

	$thread = array();

	foreach ($commentsfollows as $comment) {
		if ($comment['type'] == 'C' && $comment['parentid'] == $parent['postid']) // find just those for this parent, fully visible
			$thread[] = $comment;
	}

	as_report_event($queued ? 'c_queue' : 'c_post', $userid, $handle, $cookieid, array(
		'postid' => $postid,
		'parentid' => $parent['postid'],
		'parenttype' => $parent['basetype'],
		'parent' => $parent,
		'songid' => $song['postid'],
		'song' => $song,
		'thread' => $thread,
		'content' => $content,
		'format' => $format,
		'text' => $text,
		'categoryid' => $song['categoryid'],
		'name' => $name,
		'notify' => $notify,
		'email' => $email,
	));

	return $postid;
}
