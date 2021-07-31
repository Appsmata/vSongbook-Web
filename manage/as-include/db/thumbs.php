<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database-level access to thumbs tables


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
 * Set the thumb for $userid on $postid to $thumb in the database
 * @param $postid
 * @param $userid
 * @param $thumb
 */
function as_db_userthumb_set($postid, $userid, $thumb)
{
	$thumb = max(min(($thumb), 1), -1);

	as_db_query_sub(
		'INSERT INTO ^userthumbs (postid, userid, thumb, flag, thumbcreated) VALUES (#, #, #, 0, NOW()) ON DUPLICATE KEY UPDATE thumb=#, thumbupdated=NOW()',
		$postid, $userid, $thumb, $thumb
	);
}


/**
 * Get the thumb for $userid on $postid from the database (or NULL if none)
 * @param $postid
 * @param $userid
 * @return mixed|null
 */
function as_db_userthumb_get($postid, $userid)
{
	return as_db_read_one_value(as_db_query_sub(
		'SELECT thumb FROM ^userthumbs WHERE postid=# AND userid=#',
		$postid, $userid
	), true);
}


/**
 * Set the flag for $userid on $postid to $flag (true or false) in the database
 * @param $postid
 * @param $userid
 * @param $flag
 */
function as_db_userflag_set($postid, $userid, $flag)
{
	$flag = $flag ? 1 : 0;

	as_db_query_sub(
		'INSERT INTO ^userthumbs (postid, userid, thumb, flag) VALUES (#, #, 0, #) ON DUPLICATE KEY UPDATE flag=#',
		$postid, $userid, $flag, $flag
	);
}


/**
 * Clear all flags for $postid in the database
 * @param $postid
 */
function as_db_userflags_clear_all($postid)
{
	as_db_query_sub(
		'UPDATE ^userthumbs SET flag=0 WHERE postid=#',
		$postid
	);
}


/**
 * Recalculate the cached count of thumbsup, thumbsdown and netthumbs for $postid in the database
 * @param $postid
 */
function as_db_post_recount_thumbs($postid)
{
	if (as_should_update_counts()) {
		as_db_query_sub(
			'UPDATE ^posts AS x, (SELECT COALESCE(SUM(GREATEST(0,thumb)),0) AS thumbsup, -COALESCE(SUM(LEAST(0,thumb)),0) AS thumbsdown FROM ^userthumbs WHERE postid=#) AS a SET x.thumbsup=a.thumbsup, x.thumbsdown=a.thumbsdown, x.netthumbs=a.thumbsup-a.thumbsdown WHERE x.postid=#',
			$postid, $postid
		);
	}
}


/**
 * Recalculate the cached count of flags for $postid in the database
 * @param $postid
 */
function as_db_post_recount_flags($postid)
{
	if (as_should_update_counts()) {
		as_db_query_sub(
			'UPDATE ^posts AS x, (SELECT COALESCE(SUM(IF(flag, 1, 0)),0) AS flagcount FROM ^userthumbs WHERE postid=#) AS a SET x.flagcount=a.flagcount WHERE x.postid=#',
			$postid, $postid
		);
	}
}


/**
 * Returns all non-zero thumbs on post $postid from the database as an array of [userid] => [thumb]
 * @param $postid
 * @return array
 */
function as_db_userthumb_post_get($postid)
{
	return as_db_read_all_assoc(as_db_query_sub(
		'SELECT userid, thumb FROM ^userthumbs WHERE postid=# AND thumb!=0',
		$postid
	), 'userid', 'thumb');
}


/**
 * Returns all the postids from the database for posts that $userid has thumbd on or flagged
 * @param $userid
 * @return array
 */
function as_db_userthumbflag_user_get($userid)
{
	return as_db_read_all_values(as_db_query_sub(
		'SELECT postid FROM ^userthumbs WHERE userid=# AND (thumb!=0 OR flag!=0)',
		$userid
	));
}


/**
 * Return information about all the non-zero thumbs and/or flags on the posts in postids, including user handles for internal user management
 * @param $postids
 * @return array
 */
function as_db_userthumbflag_posts_get($postids)
{
	if (AS_FINAL_EXTERNAL_USERS) {
		return as_db_read_all_assoc(as_db_query_sub(
			'SELECT postid, userid, thumb, flag, thumbcreated, thumbupdated FROM ^userthumbs WHERE postid IN (#) AND (thumb!=0 OR flag!=0)',
			$postids
		));
	} else {
		return as_db_read_all_assoc(as_db_query_sub(
			'SELECT postid, handle, thumb, flag, thumbcreated, thumbupdated FROM ^userthumbs LEFT JOIN ^users ON ^userthumbs.userid=^users.userid WHERE postid IN (#) AND (thumb!=0 OR flag!=0)',
			$postids
		));
	}
}


/**
 * Remove all thumbs assigned to a post that had been cast by the owner of the post.
 *
 * @param int $postid The post ID from which the owner's thumbs will be removed.
 */
function as_db_userthumb_remove_own($postid)
{
	as_db_query_sub(
		'DELETE uv FROM ^userthumbs uv JOIN ^posts p ON uv.postid=p.postid AND uv.userid=p.userid WHERE uv.postid=#', $postid
	);
}
