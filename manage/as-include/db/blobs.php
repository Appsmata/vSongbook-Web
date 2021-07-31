<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database-level access to blobs table for large chunks of data (e.g. images)


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
 * Create a new blob in the database with $content and $format, other fields as provided
 * @param $content
 * @param $format
 * @param $sourcefilename
 * @param $userid
 * @param $cookieid
 * @param $ip
 * @return mixed|null|string
 */
function as_db_blob_create($content, $format, $sourcefilename = null, $userid = null, $cookieid = null, $ip = null)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	for ($attempt = 0; $attempt < 10; $attempt++) {
		$blobid = as_db_random_bigint();

		if (as_db_blob_exists($blobid))
			continue;

		as_db_query_sub(
			'INSERT INTO ^blobs (blobid, format, content, filename, userid, cookieid, createip, created) VALUES (#, $, $, $, $, #, UNHEX($), NOW())',
			$blobid, $format, $content, $sourcefilename, $userid, $cookieid, bin2hex(@inet_pton($ip))
		);

		return $blobid;
	}

	return null;
}


/**
 * Get the information about blob $blobid from the database
 * @param $blobid
 * @return array|mixed|null
 */
function as_db_blob_read($blobid)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	return as_db_read_one_assoc(as_db_query_sub(
		'SELECT content, format, filename FROM ^blobs WHERE blobid=#',
		$blobid
	), true);
}


/**
 * Change the content of blob $blobid in the database to $content (can also be null)
 * @param $blobid
 * @param $content
 */
function as_db_blob_set_content($blobid, $content)
{
	as_db_query_sub(
		'UPDATE ^blobs SET content=$ WHERE blobid=#',
		$content, $blobid
	);
}


/**
 * Delete blob $blobid in the database
 * @param $blobid
 * @return mixed
 */
function as_db_blob_delete($blobid)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	as_db_query_sub(
		'DELETE FROM ^blobs WHERE blobid=#',
		$blobid
	);
}


/**
 * Check if blob $blobid exists in the database
 * @param $blobid
 * @return bool|mixed
 */
function as_db_blob_exists($blobid)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$blob = as_db_read_one_value(as_db_query_sub(
		'SELECT COUNT(*) FROM ^blobs WHERE blobid=#',
		$blobid
	));

	return $blob > 0;
}
