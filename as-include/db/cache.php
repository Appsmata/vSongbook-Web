<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database-level access to cache table


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


/**
 * Create (or replace) the item ($type, $cacheid) in the database cache table with $content
 * @param $type
 * @param $cacheid
 * @param $content
 * @return mixed
 */
function as_db_cache_set($type, $cacheid, $content)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	as_db_query_sub(
		'DELETE FROM ^cache WHERE lastread<NOW()-INTERVAL # SECOND',
		AS_DB_MAX_CACHE_AGE
	);

	as_db_query_sub(
		'INSERT INTO ^cache (type, cacheid, content, created, lastread) VALUES ($, #, $, NOW(), NOW()) ' .
		'ON DUPLICATE KEY UPDATE content = VALUES(content), created = VALUES(created), lastread = VALUES(lastread)',
		$type, $cacheid, $content
	);
}


/**
 * Retrieve the item ($type, $cacheid) from the database cache table
 * @param $type
 * @param $cacheid
 * @return mixed|null
 */
function as_db_cache_get($type, $cacheid)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$content = as_db_read_one_value(as_db_query_sub(
		'SELECT content FROM ^cache WHERE type=$ AND cacheid=#',
		$type, $cacheid
	), true);

	if (isset($content))
		as_db_query_sub(
			'UPDATE ^cache SET lastread=NOW() WHERE type=$ AND cacheid=#',
			$type, $cacheid
		);

	return $content;
}
