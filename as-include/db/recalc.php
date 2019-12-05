<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database functions for recalculations (clean-up operations)


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

require_once AS_INCLUDE_DIR . 'db/post-create.php';


// For reindexing pages...

/**
 * Return the number of custom pages currently in the database
 */
function as_db_count_pages()
{
	return as_db_read_one_value(as_db_query_sub(
		'SELECT COUNT(*) FROM ^pages'
	));
}


/**
 * Return the information to reindex up to $count pages starting from $startpageid in the database
 * @param $startpageid
 * @param $count
 * @return array
 */
function as_db_pages_get_for_reindexing($startpageid, $count)
{
	return as_db_read_all_assoc(as_db_query_sub(
		'SELECT pageid, flags, tags, heading, content FROM ^pages WHERE pageid>=# ORDER BY pageid LIMIT #',
		$startpageid, $count
	), 'pageid');
}


// For reindexing posts...

/**
 * Return the information required to reindex up to $count posts starting from $startpostid in the database
 * @param $startpostid
 * @param $count
 * @return array
 */
function as_db_posts_get_for_reindexing($startpostid, $count)
{
	return as_db_read_all_assoc(as_db_query_sub(
		"SELECT ^posts.postid, ^posts.title, ^posts.content, ^posts.format, ^posts.tags, ^posts.categoryid, ^posts.type, IF (^posts.type='S', ^posts.postid, IF(parent.type='S', parent.postid, grandparent.postid)) AS songid, ^posts.parentid FROM ^posts LEFT JOIN ^posts AS parent ON ^posts.parentid=parent.postid LEFT JOIN ^posts as grandparent ON parent.parentid=grandparent.postid WHERE ^posts.postid>=# AND ( (^posts.type='S') OR (^posts.type='R' AND parent.type<=>'S') OR (^posts.type='C' AND parent.type<=>'S') OR (^posts.type='C' AND parent.type<=>'R' AND grandparent.type<=>'S') ) ORDER BY postid LIMIT #",
		$startpostid, $count
	), 'postid');
}


/**
 * Prepare posts $firstpostid to $lastpostid for reindexing in the database by removing their prior index entries
 * @param $firstpostid
 * @param $lastpostid
 */
function as_db_prepare_for_reindexing($firstpostid, $lastpostid)
{
	as_db_query_sub(
		'DELETE FROM ^titlewords WHERE postid>=# AND postid<=#',
		$firstpostid, $lastpostid
	);

	as_db_query_sub(
		'DELETE FROM ^contentwords WHERE postid>=# AND postid<=#',
		$firstpostid, $lastpostid
	);

	as_db_query_sub(
		'DELETE FROM ^tagwords WHERE postid>=# AND postid<=#',
		$firstpostid, $lastpostid
	);

	as_db_query_sub(
		'DELETE FROM ^posttags WHERE postid>=# AND postid<=#',
		$firstpostid, $lastpostid
	);
}


/**
 * Remove any rows in the database word indexes with postid from $firstpostid upwards
 * @param $firstpostid
 */
function as_db_truncate_indexes($firstpostid)
{
	as_db_query_sub(
		'DELETE FROM ^titlewords WHERE postid>=#',
		$firstpostid
	);

	as_db_query_sub(
		'DELETE FROM ^contentwords WHERE postid>=#',
		$firstpostid
	);

	as_db_query_sub(
		'DELETE FROM ^tagwords WHERE postid>=#',
		$firstpostid
	);

	as_db_query_sub(
		'DELETE FROM ^posttags WHERE postid>=#',
		$firstpostid
	);
}


/**
 * Return the number of words currently referenced in the database
 */
function as_db_count_words()
{
	return as_db_read_one_value(as_db_query_sub(
		'SELECT COUNT(*) FROM ^words'
	));
}


/**
 * Return the ids of up to $count words in the database starting from $startwordid
 * @param $startwordid
 * @param $count
 * @return array
 */
function as_db_words_prepare_for_recounting($startwordid, $count)
{
	return as_db_read_all_values(as_db_query_sub(
		'SELECT wordid FROM ^words WHERE wordid>=# ORDER BY wordid LIMIT #',
		$startwordid, $count
	));
}


/**
 * Recalculate the cached counts for words $firstwordid to $lastwordid in the database
 * @param $firstwordid
 * @param $lastwordid
 */
function as_db_words_recount($firstwordid, $lastwordid)
{
	as_db_query_sub(
		'UPDATE ^words AS x, (SELECT ^words.wordid, COUNT(^titlewords.wordid) AS titlecount FROM ^words LEFT JOIN ^titlewords ON ^titlewords.wordid=^words.wordid WHERE ^words.wordid>=# AND ^words.wordid<=# GROUP BY wordid) AS a SET x.titlecount=a.titlecount WHERE x.wordid=a.wordid',
		$firstwordid, $lastwordid
	);

	as_db_query_sub(
		'UPDATE ^words AS x, (SELECT ^words.wordid, COUNT(^contentwords.wordid) AS contentcount FROM ^words LEFT JOIN ^contentwords ON ^contentwords.wordid=^words.wordid WHERE ^words.wordid>=# AND ^words.wordid<=# GROUP BY wordid) AS a SET x.contentcount=a.contentcount WHERE x.wordid=a.wordid',
		$firstwordid, $lastwordid
	);

	as_db_query_sub(
		'UPDATE ^words AS x, (SELECT ^words.wordid, COUNT(^tagwords.wordid) AS tagwordcount FROM ^words LEFT JOIN ^tagwords ON ^tagwords.wordid=^words.wordid WHERE ^words.wordid>=# AND ^words.wordid<=# GROUP BY wordid) AS a SET x.tagwordcount=a.tagwordcount WHERE x.wordid=a.wordid',
		$firstwordid, $lastwordid
	);

	as_db_query_sub(
		'UPDATE ^words AS x, (SELECT ^words.wordid, COUNT(^posttags.wordid) AS tagcount FROM ^words LEFT JOIN ^posttags ON ^posttags.wordid=^words.wordid WHERE ^words.wordid>=# AND ^words.wordid<=# GROUP BY wordid) AS a SET x.tagcount=a.tagcount WHERE x.wordid=a.wordid',
		$firstwordid, $lastwordid
	);

	as_db_query_sub(
		'DELETE FROM ^words WHERE wordid>=# AND wordid<=# AND titlecount=0 AND contentcount=0 AND tagwordcount=0 AND tagcount=0',
		$firstwordid, $lastwordid
	);
}


// For recalculating numbers of thumbs and reviews for songs...

/**
 * Return the ids of up to $count posts in the database starting from $startpostid
 * @param $startpostid
 * @param $count
 * @return array
 */
function as_db_posts_get_for_recounting($startpostid, $count)
{
	return as_db_read_all_values(as_db_query_sub(
		'SELECT postid FROM ^posts WHERE postid>=# ORDER BY postid LIMIT #',
		$startpostid, $count
	));
}


/**
 * Recalculate the cached thumb counts for posts $firstpostid to $lastpostid in the database
 * @param $firstpostid
 * @param $lastpostid
 */
function as_db_posts_thumbs_recount($firstpostid, $lastpostid)
{
	as_db_query_sub(
		'UPDATE ^posts AS x, (SELECT ^posts.postid, COALESCE(SUM(GREATEST(0,^userthumbs.thumb)),0) AS thumbsup, -COALESCE(SUM(LEAST(0,^userthumbs.thumb)),0) AS thumbsdown, COALESCE(SUM(IF(^userthumbs.flag, 1, 0)),0) AS flagcount FROM ^posts LEFT JOIN ^userthumbs ON ^userthumbs.postid=^posts.postid WHERE ^posts.postid>=# AND ^posts.postid<=# GROUP BY postid) AS a SET x.thumbsup=a.thumbsup, x.thumbsdown=a.thumbsdown, x.netthumbs=a.thumbsup-a.thumbsdown, x.flagcount=a.flagcount WHERE x.postid=a.postid',
		$firstpostid, $lastpostid
	);

	as_db_hotness_update($firstpostid, $lastpostid);
}


/**
 * Recalculate the cached review counts for posts $firstpostid to $lastpostid in the database, along with the highest netthumbs of any of their reviews
 * @param $firstpostid
 * @param $lastpostid
 */
function as_db_posts_reviews_recount($firstpostid, $lastpostid)
{
	require_once AS_INCLUDE_DIR . 'db/hotness.php';

	as_db_query_sub(
		'UPDATE ^posts AS x, (SELECT parents.postid, COUNT(children.postid) AS acount, COALESCE(GREATEST(MAX(children.netthumbs), 0), 0) AS amaxthumb FROM ^posts AS parents LEFT JOIN ^posts AS children ON parents.postid=children.parentid AND children.type=\'A\' WHERE parents.postid>=# AND parents.postid<=# GROUP BY postid) AS a SET x.acount=a.acount, x.amaxthumb=a.amaxthumb WHERE x.postid=a.postid',
		$firstpostid, $lastpostid
	);

	as_db_hotness_update($firstpostid, $lastpostid);
}


// For recalculating user points...

/**
 * Return the ids of up to $count users in the database starting from $startuserid
 * If using single sign-on integration, base this on user activity rather than the users table which we don't have
 * @param $startuserid
 * @param $count
 * @return array
 */
function as_db_users_get_for_recalc_points($startuserid, $count)
{
	if (AS_FINAL_EXTERNAL_USERS) {
		return as_db_read_all_values(as_db_query_sub(
			'SELECT userid FROM ((SELECT DISTINCT userid FROM ^posts WHERE userid>=# ORDER BY userid LIMIT #) UNION (SELECT DISTINCT userid FROM ^userthumbs WHERE userid>=# ORDER BY userid LIMIT #)) x ORDER BY userid LIMIT #',
			$startuserid, $count, $startuserid, $count, $count
		));
	} else {
		return as_db_read_all_values(as_db_query_sub(
			'SELECT DISTINCT userid FROM ^users WHERE userid>=# ORDER BY userid LIMIT #',
			$startuserid, $count
		));
	}
}


/**
 * Recalculate all userpoints columns for users $firstuserid to $lastuserid in the database
 * @param $firstuserid
 * @param $lastuserid
 */
function as_db_users_recalc_points($firstuserid, $lastuserid)
{
	require_once AS_INCLUDE_DIR . 'db/points.php';

	$as_userpoints_calculations = as_db_points_calculations();

	as_db_query_sub(
		'DELETE FROM ^userpoints WHERE userid>=# AND userid<=# AND bonus=0', // delete those with no bonus
		$firstuserid, $lastuserid
	);

	$zeropoints = 'points=0';
	foreach ($as_userpoints_calculations as $field => $calculation) {
		$zeropoints .= ', ' . $field . '=0';
	}

	as_db_query_sub(
		'UPDATE ^userpoints SET ' . $zeropoints . ' WHERE userid>=# AND userid<=#', // zero out the rest
		$firstuserid, $lastuserid
	);

	if (AS_FINAL_EXTERNAL_USERS) {
		as_db_query_sub(
			'INSERT IGNORE INTO ^userpoints (userid) SELECT DISTINCT userid FROM ^posts WHERE userid>=# AND userid<=# UNION SELECT DISTINCT userid FROM ^userthumbs WHERE userid>=# AND userid<=#',
			$firstuserid, $lastuserid, $firstuserid, $lastuserid
		);
	} else {
		as_db_query_sub(
			'INSERT IGNORE INTO ^userpoints (userid) SELECT DISTINCT userid FROM ^users WHERE userid>=# AND userid<=#',
			$firstuserid, $lastuserid
		);
	}

	$updatepoints = (int)as_opt('points_base');

	foreach ($as_userpoints_calculations as $field => $calculation) {
		as_db_query_sub(
			'UPDATE ^userpoints, (SELECT userid_src.userid, ' . str_replace('~', ' BETWEEN # AND #', $calculation['formula']) . ' GROUP BY userid) AS results ' .
			'SET ^userpoints.' . $field . '=results.' . $field . ' WHERE ^userpoints.userid=results.userid',
			$firstuserid, $lastuserid
		);

		$updatepoints .= '+(' . ((int)$calculation['multiple']) . '*' . $field . ')';
	}

	as_db_query_sub(
		'UPDATE ^userpoints SET points=' . $updatepoints . '+bonus WHERE userid>=# AND userid<=#',
		$firstuserid, $lastuserid
	);
}


/**
 * Remove any rows in the userpoints table where userid is greater than $lastuserid
 * @param $lastuserid
 */
function as_db_truncate_userpoints($lastuserid)
{
	as_db_query_sub(
		'DELETE FROM ^userpoints WHERE userid>#',
		$lastuserid
	);
}


// For refilling event streams...

/**
 * Return the ids of up to $count songs in the database starting from $startpostid
 * @param $startpostid
 * @param $count
 * @return array
 */
function as_db_qs_get_for_event_refilling($startpostid, $count)
{
	return as_db_read_all_values(as_db_query_sub(
		"SELECT postid FROM ^posts WHERE postid>=# AND LEFT(type, 1)='S' ORDER BY postid LIMIT #",
		$startpostid, $count
	));
}


// For recalculating categories...

/**
 * Return the ids of up to $count posts (including queued/hidden) in the database starting from $startpostid
 * @param $startpostid
 * @param $count
 * @return array
 */
function as_db_posts_get_for_recategorizing($startpostid, $count)
{
	return as_db_read_all_values(as_db_query_sub(
		"SELECT postid FROM ^posts WHERE postid>=# ORDER BY postid LIMIT #",
		$startpostid, $count
	));
}


/**
 * Recalculate the (exact) categoryid for the posts (including queued/hidden) between $firstpostid and $lastpostid
 * in the database, where the category of comments and reviews is set by the category of the antecedent song
 * @param $firstpostid
 * @param $lastpostid
 */
function as_db_posts_recalc_categoryid($firstpostid, $lastpostid)
{
	as_db_query_sub(
		"UPDATE ^posts AS x, (SELECT ^posts.postid, IF(LEFT(parent.type, 1)='S', parent.categoryid, grandparent.categoryid) AS categoryid FROM ^posts LEFT JOIN ^posts AS parent ON ^posts.parentid=parent.postid LEFT JOIN ^posts AS grandparent ON parent.parentid=grandparent.postid WHERE ^posts.postid BETWEEN # AND # AND LEFT(^posts.type, 1)!='S') AS a SET x.categoryid=a.categoryid WHERE x.postid=a.postid",
		$firstpostid, $lastpostid
	);
}


/**
 * Return the ids of up to $count categories in the database starting from $startcategoryid
 * @param $startcategoryid
 * @param $count
 * @return array
 */
function as_db_categories_get_for_recalcs($startcategoryid, $count)
{
	return as_db_read_all_values(as_db_query_sub(
		"SELECT categoryid FROM ^categories WHERE categoryid>=# ORDER BY categoryid LIMIT #",
		$startcategoryid, $count
	));
}


// For deleting hidden posts...

/**
 * Return the ids of up to $limit posts of $type that can be deleted from the database (i.e. have no dependents)
 * @param $type
 * @param int $startpostid
 * @param $limit
 * @return array
 */
function as_db_posts_get_for_deleting($type, $startpostid = 0, $limit = null)
{
	$limitsql = isset($limit) ? (' ORDER BY ^posts.postid LIMIT ' . (int)$limit) : '';

	return as_db_read_all_values(as_db_query_sub(
		"SELECT ^posts.postid FROM ^posts LEFT JOIN ^posts AS child ON child.parentid=^posts.postid LEFT JOIN ^posts AS dupe ON dupe.closedbyid=^posts.postid WHERE ^posts.type=$ AND ^posts.postid>=# AND child.postid IS NULL AND dupe.postid IS NULL" . $limitsql,
		$type . '_HIDDEN', $startpostid
	));
}


// For moving blobs between database and disk...

/**
 * Return the number of blobs whose content is stored in the database, rather than on disk
 */
function as_db_count_blobs_in_db()
{
	return as_db_read_one_value(as_db_query_sub('SELECT COUNT(*) FROM ^blobs WHERE content IS NOT NULL'));
}


/**
 * Return the id, content and format of the first blob whose content is stored in the database starting from $startblobid
 * @param $startblobid
 * @return array|null
 */
function as_db_get_next_blob_in_db($startblobid)
{
	return as_db_read_one_assoc(as_db_query_sub(
		'SELECT blobid, content, format FROM ^blobs WHERE blobid>=# AND content IS NOT NULL LIMIT 1',
		$startblobid
	), true);
}


/**
 * Return the number of blobs whose content is stored on disk, rather than in the database
 */
function as_db_count_blobs_on_disk()
{
	return as_db_read_one_value(as_db_query_sub('SELECT COUNT(*) FROM ^blobs WHERE content IS NULL'));
}


/**
 * Return the id and format of the first blob whose content is stored on disk starting from $startblobid
 * @param $startblobid
 * @return array|null
 */
function as_db_get_next_blob_on_disk($startblobid)
{
	return as_db_read_one_assoc(as_db_query_sub(
		'SELECT blobid, format FROM ^blobs WHERE blobid>=# AND content IS NULL LIMIT 1',
		$startblobid
	), true);
}
