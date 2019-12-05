<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database-level access to usernotices table


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
 * Create a notice for $userid with $content in $format and optional $tags (not displayed) and return its noticeid
 * @param $userid
 * @param $content
 * @param string $format
 * @param $tags
 * @return mixed
 */
function as_db_usernotice_create($userid, $content, $format = '', $tags = null)
{
	as_db_query_sub(
		'INSERT INTO ^usernotices (userid, content, format, tags, created) VALUES ($, $, $, $, NOW())',
		$userid, $content, $format, $tags
	);

	return as_db_last_insert_id();
}


/**
 * Delete the notice $notice which belongs to $userid
 * @param $userid
 * @param $noticeid
 */
function as_db_usernotice_delete($userid, $noticeid)
{
	as_db_query_sub(
		'DELETE FROM ^usernotices WHERE userid=$ AND noticeid=#',
		$userid, $noticeid
	);
}


/**
 * Return an array summarizing the notices to be displayed for $userid, including the tags (not displayed)
 * @param $userid
 * @return array
 */
function as_db_usernotices_list($userid)
{
	return as_db_read_all_assoc(as_db_query_sub(
		'SELECT noticeid, tags, UNIX_TIMESTAMP(created) AS created FROM ^usernotices WHERE userid=$ ORDER BY created',
		$userid
	));
}
