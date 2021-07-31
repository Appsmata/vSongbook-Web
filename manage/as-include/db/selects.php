<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Builders of selectspec arrays (see as-db.php) used to specify database SELECTs


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

require_once AS_INCLUDE_DIR.'db/maxima.php';


/**
 * Return the results of all the SELECT operations specified by the supplied selectspec parameters, while also
 * performing all pending selects that have not yet been executed. If only one parameter is supplied, return its
 * result, otherwise return an array of results indexed as per the parameters.
 */
function as_db_select_with_pending() // any number of parameters read via func_get_args()
{
	require_once AS_INCLUDE_DIR . 'app/options.php';

	global $as_db_pending_selectspecs, $as_db_pending_results;

	$selectspecs = func_get_args();
	$singleresult = (count($selectspecs) == 1);
	$outresults = array();

	foreach ($selectspecs as $key => $selectspec) { // can pass null parameters
		if (empty($selectspec)) {
			unset($selectspecs[$key]);
			$outresults[$key] = null;
		}
	}

	if (is_array($as_db_pending_selectspecs)) {
		foreach ($as_db_pending_selectspecs as $pendingid => $selectspec) {
			if (!isset($as_db_pending_results[$pendingid])) {
				$selectspecs['pending_' . $pendingid] = $selectspec;
			}
		}
	}

	$outresults = $outresults + as_db_multi_select($selectspecs);

	if (is_array($as_db_pending_selectspecs)) {
		foreach ($as_db_pending_selectspecs as $pendingid => $selectspec) {
			if (!isset($as_db_pending_results[$pendingid])) {
				$as_db_pending_results[$pendingid] = $outresults['pending_' . $pendingid];
				unset($outresults['pending_' . $pendingid]);
			}
		}
	}

	return $singleresult ? $outresults[0] : $outresults;
}


/**
 * Queue a $selectspec for running later, with $pendingid (used for retrieval)
 * @param $pendingid
 * @param $selectspec
 */
function as_db_queue_pending_select($pendingid, $selectspec)
{
	global $as_db_pending_selectspecs;

	$as_db_pending_selectspecs[$pendingid] = $selectspec;
}


/**
 * Get the result of the queued SELECT query identified by $pendingid. Run the query if it hasn't run already. If
 * $selectspec is supplied, it doesn't matter if this hasn't been queued before - it will be queued and run now.
 * @param $pendingid
 * @param $selectspec
 * @return
 */
function as_db_get_pending_result($pendingid, $selectspec = null)
{
	global $as_db_pending_selectspecs, $as_db_pending_results;

	if (isset($selectspec)) {
		as_db_queue_pending_select($pendingid, $selectspec);
	} elseif (!isset($as_db_pending_selectspecs[$pendingid])) {
		as_fatal_error('Pending query was never set up: ' . $pendingid);
	}

	if (!isset($as_db_pending_results[$pendingid])) {
		as_db_select_with_pending();
	}

	return $as_db_pending_results[$pendingid];
}


/**
 * Remove the results of queued SELECT query identified by $pendingid if it has already been run. This means it will
 * run again if its results are requested via as_db_get_pending_result()
 * @param $pendingid
 */
function as_db_flush_pending_result($pendingid)
{
	global $as_db_pending_results;
	unset($as_db_pending_results[$pendingid]);
}


/**
 * Modify a selectspec to count the number of items. This assumes the original selectspec does not have a LIMIT clause.
 * Currently works with message inbox/outbox functions and user-flags function.
 * @param $selectSpec
 * @return mixed
 */
function as_db_selectspec_count($selectSpec)
{
	$selectSpec['columns'] = array('count' => 'COUNT(*)');
	$selectSpec['single'] = true;
	unset($selectSpec['arraykey']);

	return $selectSpec;
}


/**
 * Return the common selectspec used to build any selectspecs which retrieve posts from the database.
 * If $thumbuserid is set, retrieve the thumb made by a particular that user on each post.
 * If $full is true, get full information on the posts, instead of just information for listing pages.
 * If $user is true, get information about the user who wrote the post (or cookie if anonymous).
 * @param $thumbuserid
 * @param bool $full
 * @param bool $user
 * @return array
 */
function as_db_posts_basic_selectspec($thumbuserid = null, $full = false, $user = true)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$selectspec = array(
		'columns' => array(
			'^posts.postid', '^posts.categoryid', '^posts.type', 'basetype' => 'LEFT(^posts.type, 1)',
			'hidden' => "INSTR(^posts.type, '_HIDDEN')>0", 'queued' => "INSTR(^posts.type, '_QUEUED')>0",
			'^posts.acount', '^posts.selchildid', '^posts.closedbyid', '^posts.thumbsup', '^posts.thumbsdown', '^posts.netthumbs', '^posts.views', '^posts.hotness',
			'^posts.flagcount', '^posts.number', '^posts.songkey', '^posts.title', '^posts.alias', '^posts.tags', 'created' => 'UNIX_TIMESTAMP(^posts.created)', '^posts.name',
			'categoryname' => '^categories.title', 'categorybackpath' => "^categories.backpath",
			'categoryids' => "CONCAT_WS(',', ^posts.catidpath1, ^posts.catidpath2, ^posts.catidpath3, ^posts.categoryid)",
		),

		'arraykey' => 'postid',
		'source' => '^posts LEFT JOIN ^categories ON ^categories.categoryid=^posts.categoryid',
		'arguments' => array(),
	);

	if (isset($thumbuserid)) {
		require_once AS_INCLUDE_DIR . 'app/updates.php';

		$selectspec['columns']['userthumb'] = '^userthumbs.thumb';
		$selectspec['columns']['userflag'] = '^userthumbs.flag';
		$selectspec['columns']['userfavoriteq'] = '^userfavorites.entityid<=>^posts.postid';
		$selectspec['source'] .= ' LEFT JOIN ^userthumbs ON ^posts.postid=^userthumbs.postid AND ^userthumbs.userid=$';
		$selectspec['source'] .= ' LEFT JOIN ^userfavorites ON ^posts.postid=^userfavorites.entityid AND ^userfavorites.userid=$ AND ^userfavorites.entitytype=$';
		array_push($selectspec['arguments'], $thumbuserid, $thumbuserid, AS_ENTITY_SONG);
	}

	if ($full) {
		$selectspec['columns']['content'] = '^posts.content';
		$selectspec['columns']['notify'] = '^posts.notify';
		$selectspec['columns']['updated'] = 'UNIX_TIMESTAMP(^posts.updated)';
		$selectspec['columns']['updatetype'] = '^posts.updatetype';
		$selectspec['columns'][] = '^posts.format';
		$selectspec['columns'][] = '^posts.lastuserid';
		$selectspec['columns']['lastip'] = '^posts.lastip';
		$selectspec['columns'][] = '^posts.parentid';
		$selectspec['columns']['lastviewip'] = '^posts.lastviewip';
	}

	if ($user) {
		$selectspec['columns'][] = '^posts.userid';
		$selectspec['columns'][] = '^posts.cookieid';
		$selectspec['columns']['createip'] = '^posts.createip';
		$selectspec['columns'][] = '^userpoints.points';

		if (!AS_FINAL_EXTERNAL_USERS) {
			$selectspec['columns'][] = '^users.flags';
			$selectspec['columns'][] = '^users.level';
			$selectspec['columns']['email'] = '^users.email';
			$selectspec['columns']['handle'] = '^users.handle';
			$selectspec['columns']['avatarblobid'] = 'BINARY ^users.avatarblobid';
			$selectspec['columns'][] = '^users.avatarwidth';
			$selectspec['columns'][] = '^users.avatarheight';
			$selectspec['source'] .= ' LEFT JOIN ^users ON ^posts.userid=^users.userid';

			if ($full) {
				$selectspec['columns']['lasthandle'] = 'lastusers.handle';
				$selectspec['source'] .= ' LEFT JOIN ^users AS lastusers ON ^posts.lastuserid=lastusers.userid';
			}
		}

		$selectspec['source'] .= ' LEFT JOIN ^userpoints ON ^posts.userid=^userpoints.userid';
	}

	return $selectspec;
}


/**
 * Supplement a selectspec returned by as_db_posts_basic_selectspec() to get information about another post (review or
 * comment) which is related to the main post (song) retrieved. Pass the name of table which will contain the other
 * post in $poststable. Set $fromupdated to true to get information about when this other post was edited, rather than
 * created. If $full is true, get full information on this other post.
 * @param $selectspec
 * @param $poststable
 * @param bool $fromupdated
 * @param bool $full
 */
function as_db_add_selectspec_opost(&$selectspec, $poststable, $fromupdated = false, $full = false)
{
	$selectspec['arraykey'] = 'opostid';

	$selectspec['columns']['obasetype'] = 'LEFT(' . $poststable . '.type, 1)';
	$selectspec['columns']['ohidden'] = "INSTR(" . $poststable . ".type, '_HIDDEN')>0";
	$selectspec['columns']['opostid'] = $poststable . '.postid';
	$selectspec['columns']['ouserid'] = $poststable . ($fromupdated ? '.lastuserid' : '.userid');
	$selectspec['columns']['ocookieid'] = $poststable . '.cookieid';
	$selectspec['columns']['oname'] = $poststable . '.name';
	$selectspec['columns']['oip'] = $poststable . ($fromupdated ? '.lastip' : '.createip');
	$selectspec['columns']['otime'] = 'UNIX_TIMESTAMP(' . $poststable . ($fromupdated ? '.updated' : '.created') . ')';
	$selectspec['columns']['oflagcount'] = $poststable . '.flagcount';

	if ($fromupdated) {
		$selectspec['columns']['oupdatetype'] = $poststable . '.updatetype';
	}

	if ($full) {
		$selectspec['columns']['ocontent'] = $poststable . '.content';
		$selectspec['columns']['oformat'] = $poststable . '.format';
	}

	if ($fromupdated || $full) {
		$selectspec['columns']['oupdated'] = 'UNIX_TIMESTAMP(' . $poststable . '.updated)';
	}
}


/**
 * Supplement a selectspec returned by as_db_posts_basic_selectspec() to get information about the author of another
 * post (review or comment) which is related to the main post (song) retrieved. Pass the name of table which will
 * contain the other user's details in $userstable and the name of the table which will contain the other user's points
 * in $pointstable.
 * @param $selectspec
 * @param $userstable
 * @param $pointstable
 */
function as_db_add_selectspec_ousers(&$selectspec, $userstable, $pointstable)
{
	if (!AS_FINAL_EXTERNAL_USERS) {
		$selectspec['columns']['oflags'] = $userstable . '.flags';
		$selectspec['columns']['olevel'] = $userstable . '.level';
		$selectspec['columns']['oemail'] = $userstable . '.email';
		$selectspec['columns']['ohandle'] = $userstable . '.handle';
		$selectspec['columns']['oavatarblobid'] = 'BINARY ' . $userstable . '.avatarblobid'; // cast to BINARY due to MySQL bug which renders it signed in a union
		$selectspec['columns']['oavatarwidth'] = $userstable . '.avatarwidth';
		$selectspec['columns']['oavatarheight'] = $userstable . '.avatarheight';
	}

	$selectspec['columns']['opoints'] = $pointstable . '.points';
}


/**
 * Given $categoryslugs in order of the hierarchiy, return the equivalent value for the backpath column in the categories table
 * @param $categoryslugs
 * @return string
 */
function as_db_slugs_to_backpath($categoryslugs)
{
	if (!is_array($categoryslugs)) {
		// accept old-style string arguments for one category deep
		$categoryslugs = array($categoryslugs);
	}

	return implode('/', array_reverse($categoryslugs));
}


/**
 * Return SQL code that represents the constraint of a post being in the category with $categoryslugs, or any of its subcategories
 * @param $categoryslugs
 * @param $arguments
 * @return string
 */
function as_db_categoryslugs_sql_args($categoryslugs, &$arguments)
{
	if (!is_array($categoryslugs)) {
		// accept old-style string arguments for one category deep
		$categoryslugs = strlen($categoryslugs) ? array($categoryslugs) : array();
	}

	$levels = count($categoryslugs);

	if ($levels > 0 && $levels <= AS_CATEGORY_DEPTH) {
		$arguments[] = as_db_slugs_to_backpath($categoryslugs);
		return (($levels == AS_CATEGORY_DEPTH) ? 'categoryid' : ('catidpath' . $levels)) . '=(SELECT categoryid FROM ^categories WHERE backpath=$ LIMIT 1) AND ';
	}

	return '';
}


/**
 * Return the selectspec to retrieve songs (of type $specialtype if provided, or 'S' by default) sorted by $sort,
 * restricted to $createip (if not null) and the category for $categoryslugs (if not null), with the corresponding thumb
 * made by $thumbuserid (if not null) and including $full content or not. Return $count (if null, a default is used)
 * songs starting from offset $start.
 * @param $thumbuserid
 * @param $sort
 * @param $start
 * @param $categoryslugs
 * @param $createip
 * @param bool $specialtype
 * @param bool $full
 * @param $count
 * @return array
 */
function as_db_posts_select($userid, $bookids)
{
	//$sortsql = 'ORDER BY ^posts.' . $sort . ' DESC, ^posts.created DESC';

	$selectspec = as_db_posts_basic_selectspec($userid, true);

	$selectspec['source'] .=
		" JOIN (SELECT postid FROM ^posts WHERE categoryid IN (" . $bookids . ") AND type='S') y ON ^posts.postid=y.postid";

	return $selectspec;
}


/**
 * Return the selectspec to retrieve songs (of type $specialtype if provided, or 'S' by default) sorted by $sort,
 * restricted to $createip (if not null) and the category for $categoryslugs (if not null), with the corresponding thumb
 * made by $thumbuserid (if not null) and including $full content or not. Return $count (if null, a default is used)
 * songs starting from offset $start.
 * @param $thumbuserid
 * @param $sort
 * @param $start
 * @param $categoryslugs
 * @param $createip
 * @param bool $specialtype
 * @param bool $full
 * @param $count
 * @return array
 */
function as_db_posts_search($userid, $search, $categoryslugs = null)
{
	$selectspec = as_db_posts_basic_selectspec($userid, true);
	$selectspec['source'] .= ' LEFT JOIN ^categories AS childcat ON ^posts.categoryid=childcat.categoryid';
	$selectspec['source'] .= ' LEFT JOIN ^categories AS parent ON childcat.parentid=parent.categoryid';
	
	$selectspec['source'] .= ' WHERE ^posts.title LIKE "%' . $search . '%"';
	$selectspec['source'] .= ' OR parent.title LIKE "%' . $search . '%"';
	$selectspec['source'] .= ' OR ^posts.content LIKE "%' . $search . '%"';

	return $selectspec;
}

/**
 * Return the selectspec to retrieve songs (of type $specialtype if provided, or 'S' by default) sorted by $sort,
 * restricted to $createip (if not null) and the category for $categoryslugs (if not null), with the corresponding thumb
 * made by $thumbuserid (if not null) and including $full content or not. Return $count (if null, a default is used)
 * songs starting from offset $start.
 * @param $thumbuserid
 * @param $sort
 * @param $start
 * @param $categoryslugs
 * @param $createip
 * @param bool $specialtype
 * @param bool $full
 * @param $count
 * @return array
 */
function as_db_qs_selectspec($thumbuserid, $sort, $start, $categoryslugs = null, $createip = null, $specialtype = false, $full = false, $count = null)
{
	if ($specialtype == 'S' || $specialtype == 'S_QUEUED') {
		$type = $specialtype;
	} else {
		$type = $specialtype ? 'S_HIDDEN' : 'S'; // for backwards compatibility
	}

	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	switch ($sort) {
		case 'acount':
		case 'flagcount':
		case 'netthumbs':
		case 'views':
			$sortsql = 'ORDER BY ^posts.' . $sort . ' DESC, ^posts.created DESC';
			break;

		case 'created':
		case 'hotness':
			$sortsql = 'ORDER BY ^posts.' . $sort . ' DESC';
			break;

		default:
			as_fatal_error('as_db_qs_selectspec() called with illegal sort value');
			break;
	}

	$selectspec = as_db_posts_basic_selectspec($thumbuserid, $full);

	$selectspec['source'] .=
		" JOIN (SELECT postid FROM ^posts WHERE " .
		as_db_categoryslugs_sql_args($categoryslugs, $selectspec['arguments']) .
		(isset($createip) ? "createip=UNHEX($) AND " : "") .
		"type=$ " . $sortsql . " LIMIT #,#) y ON ^posts.postid=y.postid";

	if (isset($createip)) {
		$selectspec['arguments'][] = bin2hex(@inet_pton($createip));
	}

	array_push($selectspec['arguments'], $type, $start, $count);

	$selectspec['sortdesc'] = $sort;

	return $selectspec;
}


/**
 * Return the selectspec to retrieve recent songs (of type $specialtype if provided, or 'S' by default) which,
 * depending on $by, either (a) have no reviews, (b) have on selected reviews, or (c) have no upthumbd reviews. The
 * songs are restricted to the category for $categoryslugs (if not null), and will have the corresponding thumb made
 * by $thumbuserid (if not null) and will include $full content or not. Return $count (if null, a default is used)
 * songs starting from offset $start.
 * @param $thumbuserid
 * @param $by
 * @param $start
 * @param $categoryslugs
 * @param bool $specialtype
 * @param bool $full
 * @param $count
 * @return array
 */
function as_db_unreviewed_qs_selectspec($thumbuserid, $by, $start, $categoryslugs = null, $specialtype = false, $full = false, $count = null)
{
	if ($specialtype == 'S' || $specialtype == 'S_QUEUED') {
		$type = $specialtype;
	} else {
		$type = $specialtype ? 'S_HIDDEN' : 'S'; // for backwards compatibility
	}

	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	switch ($by) {
		case 'selchildid':
			$bysql = 'selchildid IS NULL';
			break;

		case 'amaxthumb':
			$bysql = 'amaxthumb=0';
			break;

		default:
			$bysql = 'acount=0';
			break;
	}

	$selectspec = as_db_posts_basic_selectspec($thumbuserid, $full);

	$selectspec['source'] .= " JOIN (SELECT postid FROM ^posts WHERE " . as_db_categoryslugs_sql_args($categoryslugs, $selectspec['arguments']) . "type=$ AND " . $bysql . " AND closedbyid IS NULL ORDER BY ^posts.created DESC LIMIT #,#) y ON ^posts.postid=y.postid";

	array_push($selectspec['arguments'], $type, $start, $count);

	$selectspec['sortdesc'] = 'created';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the antecedent songs for recent reviews (of type $specialtype if provided, or
 * 'R' by default), restricted to $createip (if not null) and the category for $categoryslugs (if not null), with the
 * corresponding thumb on those songs made by $thumbuserid (if not null). Return $count (if null, a default is used)
 * songs starting from offset $start. The selectspec will also retrieve some information about the reviews
 * themselves (including the content if $fullreviews is true), in columns named with the prefix 'o'.
 * @param $thumbuserid
 * @param $start
 * @param $categoryslugs
 * @param $createip
 * @param bool $specialtype
 * @param bool $fullreviews
 * @param $count
 * @return array
 */
function as_db_recent_a_qs_selectspec($thumbuserid, $start, $categoryslugs = null, $createip = null, $specialtype = false, $fullreviews = false, $count = null)
{
	if ($specialtype == 'R' || $specialtype == 'R_QUEUED') {
		$type = $specialtype;
	} else {
		$type = $specialtype ? 'R_HIDDEN' : 'R'; // for backwards compatibility
	}

	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	as_db_add_selectspec_opost($selectspec, 'aposts', false, $fullreviews);
	as_db_add_selectspec_ousers($selectspec, 'ausers', 'auserpoints');

	$selectspec['source'] .=
		" JOIN ^posts AS aposts ON ^posts.postid=aposts.parentid" .
		(AS_FINAL_EXTERNAL_USERS ? "" : " LEFT JOIN ^users AS ausers ON aposts.userid=ausers.userid") .
		" LEFT JOIN ^userpoints AS auserpoints ON aposts.userid=auserpoints.userid" .
		" JOIN (SELECT postid FROM ^posts WHERE " .
		as_db_categoryslugs_sql_args($categoryslugs, $selectspec['arguments']) .
		(isset($createip) ? "createip=UNHEX($) AND " : "") .
		"type=$ ORDER BY ^posts.created DESC LIMIT #,#) y ON aposts.postid=y.postid" .
		($specialtype ? '' : " WHERE ^posts.type='S'");

	if (isset($createip)) {
		$selectspec['arguments'][] = bin2hex(@inet_pton($createip));
	}

	array_push($selectspec['arguments'], $type, $start, $count);

	$selectspec['sortdesc'] = 'otime';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the antecedent songs for recent comments (of type $specialtype if provided, or
 * 'C' by default), restricted to $createip (if not null) and the category for $categoryslugs (if not null), with the
 * corresponding thumb on those songs made by $thumbuserid (if not null). Return $count (if null, a default is used)
 * songs starting from offset $start. The selectspec will also retrieve some information about the comments
 * themselves (including the content if $fullcomments is true), in columns named with the prefix 'o'.
 * @param $thumbuserid
 * @param $start
 * @param $categoryslugs
 * @param $createip
 * @param bool $specialtype
 * @param bool $fullcomments
 * @param $count
 * @return array
 */
function as_db_recent_c_qs_selectspec($thumbuserid, $start, $categoryslugs = null, $createip = null, $specialtype = false, $fullcomments = false, $count = null)
{
	if ($specialtype == 'C' || $specialtype == 'C_QUEUED') {
		$type = $specialtype;
	} else {
		$type = $specialtype ? 'C_HIDDEN' : 'C'; // for backwards compatibility
	}

	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	as_db_add_selectspec_opost($selectspec, 'cposts', false, $fullcomments);
	as_db_add_selectspec_ousers($selectspec, 'cusers', 'cuserpoints');

	$selectspec['source'] .=
		" JOIN ^posts AS parentposts ON" .
		" ^posts.postid=(CASE LEFT(parentposts.type, 1) WHEN 'R' THEN parentposts.parentid ELSE parentposts.postid END)" .
		" JOIN ^posts AS cposts ON parentposts.postid=cposts.parentid" .
		(AS_FINAL_EXTERNAL_USERS ? "" : " LEFT JOIN ^users AS cusers ON cposts.userid=cusers.userid") .
		" LEFT JOIN ^userpoints AS cuserpoints ON cposts.userid=cuserpoints.userid" .
		" JOIN (SELECT postid FROM ^posts WHERE " .
		as_db_categoryslugs_sql_args($categoryslugs, $selectspec['arguments']) .
		(isset($createip) ? "createip=UNHEX($) AND " : "") .
		"type=$ ORDER BY ^posts.created DESC LIMIT #,#) y ON cposts.postid=y.postid" .
		($specialtype ? '' : " WHERE ^posts.type='S' AND ((parentposts.type='S') OR (parentposts.type='R'))");

	if (isset($createip)) {
		$selectspec['arguments'][] = bin2hex(@inet_pton($createip));
	}

	array_push($selectspec['arguments'], $type, $start, $count);

	$selectspec['sortdesc'] = 'otime';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the antecedent songs for recently edited posts, restricted to edits by $lastip
 * (if not null), the category for $categoryslugs (if not null) and only visible posts (if $onlyvisible), with the
 * corresponding thumb on those songs made by $thumbuserid (if not null). Return $count (if null, a default is used)
 * songs starting from offset $start. The selectspec will also retrieve some information about the edited posts
 * themselves (including the content if $fulledited is true), in columns named with the prefix 'o'.
 * @param $thumbuserid
 * @param $start
 * @param $categoryslugs
 * @param $lastip
 * @param bool $onlyvisible
 * @param bool $fulledited
 * @param $count
 * @return array
 */
function as_db_recent_edit_qs_selectspec($thumbuserid, $start, $categoryslugs = null, $lastip = null, $onlyvisible = true, $fulledited = false, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	as_db_add_selectspec_opost($selectspec, 'editposts', true, $fulledited);
	as_db_add_selectspec_ousers($selectspec, 'editusers', 'edituserpoints');

	$selectspec['source'] .=
		" JOIN ^posts AS parentposts ON" .
		" ^posts.postid=IF(LEFT(parentposts.type, 1)='S', parentposts.postid, parentposts.parentid)" .
		" JOIN ^posts AS editposts ON parentposts.postid=IF(LEFT(editposts.type, 1)='S', editposts.postid, editposts.parentid)" .
		(AS_FINAL_EXTERNAL_USERS ? "" : " LEFT JOIN ^users AS editusers ON editposts.lastuserid=editusers.userid") .
		" LEFT JOIN ^userpoints AS edituserpoints ON editposts.lastuserid=edituserpoints.userid" .
		" JOIN (SELECT postid FROM ^posts WHERE " .
		as_db_categoryslugs_sql_args($categoryslugs, $selectspec['arguments']) .
		(isset($lastip) ? "lastip=UNHEX($) AND " : "") .
		($onlyvisible ? "type IN ('S', 'R', 'C')" : "1") .
		" ORDER BY ^posts.updated DESC LIMIT #,#) y ON editposts.postid=y.postid" .
		($onlyvisible ? " WHERE parentposts.type IN ('S', 'R', 'C') AND ^posts.type IN ('S', 'R', 'C')" : "");

	if (isset($lastip)) {
		$selectspec['arguments'][] = bin2hex(@inet_pton($lastip));
	}

	array_push($selectspec['arguments'], $start, $count);

	$selectspec['sortdesc'] = 'otime';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the antecedent songs for the most flagged posts, with the corresponding thumb
 * on those songs made by $thumbuserid (if not null). Return $count (if null, a default is used) songs starting
 * from offset $start. The selectspec will also retrieve some information about the flagged posts themselves (including
 * the content if $fullflagged is true).
 * @param $thumbuserid
 * @param $start
 * @param bool $fullflagged
 * @param $count
 * @return array
 */
function as_db_flagged_post_qs_selectspec($thumbuserid, $start, $fullflagged = false, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	as_db_add_selectspec_opost($selectspec, 'flagposts', false, $fullflagged);
	as_db_add_selectspec_ousers($selectspec, 'flagusers', 'flaguserpoints');

	$selectspec['source'] .=
		" JOIN ^posts AS parentposts ON" .
		" ^posts.postid=IF(LEFT(parentposts.type, 1)='S', parentposts.postid, parentposts.parentid)" .
		" JOIN ^posts AS flagposts ON parentposts.postid=IF(LEFT(flagposts.type, 1)='S', flagposts.postid, flagposts.parentid)" .
		(AS_FINAL_EXTERNAL_USERS ? "" : " LEFT JOIN ^users AS flagusers ON flagposts.userid=flagusers.userid") .
		" LEFT JOIN ^userpoints AS flaguserpoints ON flagposts.userid=flaguserpoints.userid" .
		" JOIN (SELECT postid FROM ^posts WHERE flagcount>0 AND type IN ('S', 'R', 'C') ORDER BY ^posts.flagcount DESC, ^posts.created DESC LIMIT #,#) y ON flagposts.postid=y.postid";

	array_push($selectspec['arguments'], $start, $count);

	$selectspec['sortdesc'] = 'oflagcount';
	$selectspec['sortdesc_2'] = 'otime';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the posts in $postids, with the corresponding thumb on those posts made by
 * $thumbuserid (if not null). Returns full information if $full is true.
 * @param $thumbuserid
 * @param $postids
 * @param bool $full
 * @return array
 */
function as_db_posts_selectspec($thumbuserid, $postids, $full = false)
{
	$selectspec = as_db_posts_basic_selectspec($thumbuserid, $full);

	$selectspec['source'] .= " WHERE ^posts.postid IN (#)";
	$selectspec['arguments'][] = $postids;

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the basetype for the posts in $postids, as an array mapping postid => basetype
 * @param $postids
 * @return array
 */
function as_db_posts_basetype_selectspec($postids)
{
	return array(
		'columns' => array('postid', 'basetype' => 'LEFT(type, 1)'),
		'source' => "^posts WHERE postid IN (#)",
		'arguments' => array($postids),
		'arraykey' => 'postid',
		'arrayvalue' => 'basetype',
	);
}


/**
 * Return the selectspec to retrieve the basetype for the posts in $postids, as an array mapping postid => basetype
 * @param $thumbuserid
 * @param $postids
 * @param bool $full
 * @return array
 */
function as_db_posts_to_qs_selectspec($thumbuserid, $postids, $full = false)
{
	$selectspec = as_db_posts_basic_selectspec($thumbuserid, $full);

	$selectspec['columns']['obasetype'] = 'LEFT(childposts.type, 1)';
	$selectspec['columns']['opostid'] = 'childposts.postid';

	$selectspec['source'] .=
		" JOIN ^posts AS parentposts ON" .
		" ^posts.postid=IF(LEFT(parentposts.type, 1)='S', parentposts.postid, parentposts.parentid)" .
		" JOIN ^posts AS childposts ON parentposts.postid=IF(LEFT(childposts.type, 1)='S', childposts.postid, childposts.parentid)" .
		" WHERE childposts.postid IN (#)";

	$selectspec['arraykey'] = 'opostid';
	$selectspec['arguments'][] = $postids;

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the full information for $postid, with the corresponding thumb made by $thumbuserid (if not null)
 * @param $thumbuserid
 * @param $postid
 * @return array
 */
function as_db_full_post_selectspec($thumbuserid, $postid)
{
	$selectspec = as_db_posts_basic_selectspec($thumbuserid, true);

	$selectspec['source'] .= " WHERE ^posts.postid=#";
	$selectspec['arguments'][] = $postid;
	$selectspec['single'] = true;

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the full information for all posts whose parent is $parentid, with the
 * corresponding thumb made by $thumbuserid (if not null)
 * @param $thumbuserid
 * @param $parentid
 * @return array
 */
function as_db_full_child_posts_selectspec($thumbuserid, $parentid)
{
	$selectspec = as_db_posts_basic_selectspec($thumbuserid, true);

	$selectspec['source'] .= " WHERE ^posts.parentid=#";
	$selectspec['arguments'][] = $parentid;

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the full information for all posts whose parent is an review which
 * has $songid as its parent, with the corresponding thumb made by $thumbuserid (if not null)
 * @param $thumbuserid
 * @param $songid
 * @return array
 */
function as_db_full_a_child_posts_selectspec($thumbuserid, $songid)
{
	$selectspec = as_db_posts_basic_selectspec($thumbuserid, true);

	$selectspec['source'] .= " JOIN ^posts AS parents ON ^posts.parentid=parents.postid WHERE parents.parentid=# AND LEFT(parents.type, 1)='R'";
	$selectspec['arguments'][] = $songid;

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the song for the parent of $postid (where $postid is of a follow-on song or comment),
 * i.e. the parent of $songid's parent if $songid's parent is an review, otherwise $songid's parent itself.
 * @param $postid
 * @return array
 */
function as_db_post_parent_q_selectspec($postid)
{
	$selectspec = as_db_posts_basic_selectspec();

	$selectspec['source'] .= " WHERE ^posts.postid=(SELECT IF(LEFT(parent.type, 1)='R', parent.parentid, parent.postid) FROM ^posts AS child LEFT JOIN ^posts AS parent ON parent.postid=child.parentid WHERE child.postid=# AND parent.type IN('S','R'))";
	$selectspec['arguments'] = array($postid);
	$selectspec['single'] = true;

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the post (either duplicate song or explanatory note) which has closed $songid, if any
 * @param $songid
 * @return array
 */
function as_db_post_close_post_selectspec($songid)
{
	$selectspec = as_db_posts_basic_selectspec(null, true);

	$selectspec['source'] .= " WHERE ^posts.postid=(SELECT closedbyid FROM ^posts WHERE postid=#)";
	$selectspec['arguments'] = array($songid);
	$selectspec['single'] = true;

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the posts that have been closed as a duplicate of this song, if any
 * @param $songid int The canonical song.
 * @return array
 */
function as_db_post_duplicates_selectspec($songid)
{
	$selectspec = as_db_posts_basic_selectspec(null, true);

	$selectspec['source'] .= " WHERE ^posts.closedbyid=#";
	$selectspec['arguments'] = array($songid);

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the metadata value for $postid with key $title
 * @param $postid
 * @param $title
 * @return array
 */
function as_db_post_meta_selectspec($postid, $title)
{
	$selectspec = array(
		'columns' => array('title', 'content'),
		'source' => "^postmetas WHERE postid=# AND " . (is_array($title) ? "title IN ($)" : "title=$"),
		'arguments' => array($postid, $title),
		'arrayvalue' => 'content',
	);

	if (is_array($title)) {
		$selectspec['arraykey'] = 'title';
	} else {
		$selectspec['single'] = true;
	}

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the most closely related songs to $songid, with the corresponding thumb
 * made by $thumbuserid (if not null). Return $count (if null, a default is used) songs. This works by looking for
 * other songs which have title words, tag words or an (exact) category in common.
 * @param $thumbuserid
 * @param $songid
 * @param $count
 * @return array
 */
function as_db_related_qs_selectspec($thumbuserid, $songid, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	$selectspec['columns'][] = 'score';

	// added LOG(postid)/1000000 here to ensure ordering is deterministic even if several posts have same score

	$selectspec['source'] .= " JOIN (SELECT postid, SUM(score)+LOG(postid)/1000000 AS score FROM ((SELECT ^titlewords.postid, LOG(#/titlecount) AS score FROM ^titlewords JOIN ^words ON ^titlewords.wordid=^words.wordid JOIN ^titlewords AS source ON ^titlewords.wordid=source.wordid WHERE source.postid=# AND titlecount<#) UNION ALL (SELECT ^posttags.postid, 2*LOG(#/tagcount) AS score FROM ^posttags JOIN ^words ON ^posttags.wordid=^words.wordid JOIN ^posttags AS source ON ^posttags.wordid=source.wordid WHERE source.postid=# AND tagcount<#) UNION ALL (SELECT ^posts.postid, LOG(#/^categories.qcount) FROM ^posts JOIN ^categories ON ^posts.categoryid=^categories.categoryid AND ^posts.type='S' WHERE ^categories.categoryid=(SELECT categoryid FROM ^posts WHERE postid=#) AND ^categories.qcount<#)) x WHERE postid!=# GROUP BY postid ORDER BY score DESC LIMIT #) y ON ^posts.postid=y.postid";

	array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ, $songid, AS_IGNORED_WORDS_FREQ, AS_IGNORED_WORDS_FREQ,
		$songid, AS_IGNORED_WORDS_FREQ, AS_IGNORED_WORDS_FREQ, $songid, AS_IGNORED_WORDS_FREQ, $songid, $count);

	$selectspec['sortdesc'] = 'score';

	if (!isset($thumbuserid)) {
		$selectspec['caching'] = array(
			'key' => __FUNCTION__ . ":$songid:$count",
			'ttl' => as_opt('caching_q_time'),
		);
	}

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the top song matches for a search, with the corresponding thumb made by
 * $thumbuserid (if not null) and including $full content or not. Return $count (if null, a default is used) songs
 * starting from offset $start. The search is performed for any of $titlewords in the title, $contentwords in the
 * content (of the song or an review or comment for whom that is the antecedent song), $tagwords in tags, for
 * song author usernames which match a word in $handlewords or which match $handle as a whole. The results also
 * include a 'score' column based on the matching strength and post hotness, and a 'matchparts' column that tells us
 * where the score came from (since a song could get weight from a match in the song itself, and/or weight from
 * a match in its reviews, comments, or comments on reviews). The 'matchparts' is a comma-separated list of tuples
 * matchtype:matchpostid:matchscore to be used with as_search_set_max_match().
 * @param $thumbuserid
 * @param $titlewords
 * @param $contentwords
 * @param $tagwords
 * @param $handlewords
 * @param $handle
 * @param $start
 * @param bool $full
 * @param $count
 * @return array
 */
function as_db_search_posts_selectspec($thumbuserid, $titlewords, $contentwords, $tagwords, $handlewords, $handle, $start, $full = false, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	// add LOG(postid)/1000000 here to ensure ordering is deterministic even if several posts have same score
	// The score also gives a bonus for hot songs, where the bonus scales linearly with hotness. The hottest
	// song gets a bonus equivalent to a matching unique tag, and the least hot song gets zero bonus.

	$selectspec = as_db_posts_basic_selectspec($thumbuserid, $full);

	$selectspec['columns'][] = 'score';
	$selectspec['columns'][] = 'matchparts';
	$selectspec['source'] .= " JOIN (SELECT songid, SUM(score)+2*(LOG(#)*(MAX(^posts.hotness)-(SELECT MIN(hotness) FROM ^posts WHERE type='S'))/((SELECT MAX(hotness) FROM ^posts WHERE type='S')-(SELECT MIN(hotness) FROM ^posts WHERE type='S')))+LOG(songid)/1000000 AS score, GROUP_CONCAT(CONCAT_WS(':', matchposttype, matchpostid, ROUND(score,3))) AS matchparts FROM (";
	$selectspec['sortdesc'] = 'score';
	array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ);

	$selectparts = 0;

	if (!empty($titlewords)) {
		// At the indexing stage, duplicate words in title are ignored, so this doesn't count multiple appearances.

		$selectspec['source'] .= ($selectparts++ ? " UNION ALL " : "") .
			"(SELECT postid AS songid, LOG(#/titlecount) AS score, 'S' AS matchposttype, postid AS matchpostid FROM ^titlewords JOIN ^words ON ^titlewords.wordid=^words.wordid WHERE word IN ($) AND titlecount<#)";

		array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ, $titlewords, AS_IGNORED_WORDS_FREQ);
	}

	if (!empty($contentwords)) {
		// (1-1/(1+count)) weights words in content based on their frequency: If a word appears once in content
		// it's equivalent to 1/2 an appearance in the title (ignoring the contentcount/titlecount factor).
		// If it appears an infinite number of times, it's equivalent to one appearance in the title.
		// This will discourage keyword stuffing while still giving some weight to multiple appearances.
		// On top of that, review matches are worth half a song match, and comment/note matches half again.

		$selectspec['source'] .= ($selectparts++ ? " UNION ALL " : "") .
			"(SELECT songid, (1-1/(1+count))*LOG(#/contentcount)*(CASE ^contentwords.type WHEN 'S' THEN 1.0 WHEN 'R' THEN 0.5 ELSE 0.25 END) AS score, ^contentwords.type AS matchposttype, ^contentwords.postid AS matchpostid FROM ^contentwords JOIN ^words ON ^contentwords.wordid=^words.wordid WHERE word IN ($) AND contentcount<#)";

		array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ, $contentwords, AS_IGNORED_WORDS_FREQ);
	}

	if (!empty($tagwords)) {
		// Appearances in the tag words count like 2 appearances in the title (ignoring the tagcount/titlecount factor).
		// This is because tags express explicit semantic intent, whereas titles do not necessarily.

		$selectspec['source'] .= ($selectparts++ ? " UNION ALL " : "") .
			"(SELECT postid AS songid, 2*LOG(#/tagwordcount) AS score, 'S' AS matchposttype, postid AS matchpostid FROM ^tagwords JOIN ^words ON ^tagwords.wordid=^words.wordid WHERE word IN ($) AND tagwordcount<#)";

		array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ, $tagwords, AS_IGNORED_WORDS_FREQ);
	}

	if (!empty($handlewords)) {
		if (AS_FINAL_EXTERNAL_USERS) {
			require_once AS_INCLUDE_DIR . 'app/users.php';

			$userids = as_get_userids_from_public($handlewords);

			if (count($userids)) {
				$selectspec['source'] .= ($selectparts++ ? " UNION ALL " : "") .
					"(SELECT postid AS songid, LOG(#/qposts) AS score, 'S' AS matchposttype, postid AS matchpostid FROM ^posts JOIN ^userpoints ON ^posts.userid=^userpoints.userid WHERE ^posts.userid IN ($) AND type='S')";

				array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ, $userids);
			}

		} else {
			$selectspec['source'] .= ($selectparts++ ? " UNION ALL " : "") .
				"(SELECT postid AS songid, LOG(#/qposts) AS score, 'S' AS matchposttype, postid AS matchpostid FROM ^posts JOIN ^users ON ^posts.userid=^users.userid JOIN ^userpoints ON ^userpoints.userid=^users.userid WHERE handle IN ($) AND type='S')";

			array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ, $handlewords);
		}
	}

	if (strlen($handle)) { // to allow searching for multi-word usernames (only works if search query contains full username and nothing else)
		if (AS_FINAL_EXTERNAL_USERS) {
			$userids = as_get_userids_from_public(array($handle));

			if (count($userids)) {
				$selectspec['source'] .= ($selectparts++ ? " UNION ALL " : "") .
					"(SELECT postid AS songid, LOG(#/qposts) AS score, 'S' AS matchposttype, postid AS matchpostid FROM ^posts JOIN ^userpoints ON ^posts.userid=^userpoints.userid WHERE ^posts.userid=$ AND type='S')";

				array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ, reset($userids));
			}

		} else {
			$selectspec['source'] .= ($selectparts++ ? " UNION ALL " : "") .
				"(SELECT postid AS songid, LOG(#/qposts) AS score, 'S' AS matchposttype, postid AS matchpostid FROM ^posts JOIN ^users ON ^posts.userid=^users.userid JOIN ^userpoints ON ^userpoints.userid=^users.userid WHERE handle=$ AND type='S')";

			array_push($selectspec['arguments'], AS_IGNORED_WORDS_FREQ, $handle);
		}
	}

	if ($selectparts == 0) {
		$selectspec['source'] .= '(SELECT NULL as songid, 0 AS score, NULL AS matchposttype, NULL AS matchpostid FROM ^posts WHERE postid IS NULL)';
	}

	$selectspec['source'] .= ") x LEFT JOIN ^posts ON ^posts.postid=songid GROUP BY songid ORDER BY score DESC LIMIT #,#) y ON ^posts.postid=y.songid";

	array_push($selectspec['arguments'], $start, $count);

	return $selectspec;
}


/**
 * Processes the matchparts column in $song which was returned from a search performed via as_db_search_posts_selectspec()
 * Returns the id of the strongest matching review or comment, or null if the song itself was the strongest match
 * @param $song
 * @param $type
 * @param $postid
 * @return null
 */
function as_search_set_max_match($song, &$type, &$postid)
{
	$type = 'S';
	$postid = $song['postid'];
	$bestscore = null;

	$matchparts = explode(',', $song['matchparts']);
	foreach ($matchparts as $matchpart) {
		if (sscanf($matchpart, '%1s:%f:%f', $matchposttype, $matchpostid, $matchscore) == 3) {
			if (!isset($bestscore) || $matchscore > $bestscore) {
				$bestscore = $matchscore;
				$type = $matchposttype;
				$postid = $matchpostid;
			}
		}
	}

	return null;
}


/**
 * Return a selectspec to retrieve the full information on the category whose id is $slugsorid (if $isid is true),
 * otherwise whose backpath matches $slugsorid
 * @param $slugsorid
 * @param $isid
 * @return array
 */
function as_db_full_category_selectspec($slugsorid, $isid)
{
	if ($isid) {
		$identifiersql = 'categoryid=#';
	} else {
		$identifiersql = 'backpath=$';
		$slugsorid = as_db_slugs_to_backpath($slugsorid);
	}

	return array(
		'columns' => array('categoryid', 'parentid', 'title', 'tags', 'qcount', 'enabled', 'content', 'backpath'),
		'source' => '^categories WHERE ' . $identifiersql,
		'arguments' => array($slugsorid),
		'single' => 'true',
	);
}


/**
 * Return the selectspec to retrieve ($full or not) info on the categories which "surround" the central category specified
 * by $slugsorid, $isid and $ispostid. The "surrounding" categories include all categories (even unrelated) at the
 * top level, any ancestors (at any level) of the category, the category's siblings and sub-categories (to one level).
 * The central category is specified as follows. If $isid AND $ispostid then $slugsorid is the ID of a post with the category.
 * Otherwise if $isid then $slugsorid is the category's own id. Otherwise $slugsorid is the full backpath of the category.
 * @param $slugsorid
 * @param $isid
 * @param bool $ispostid
 * @param bool $full
 * @return array
 */
function as_db_category_nav_selectspec($slugsorid, $isid, $ispostid = false, $full = false, $enabled = false)
{
	if ($isid) {
		if ($ispostid) {
			$identifiersql = 'categoryid=(SELECT categoryid FROM ^posts WHERE postid=#)';
		} else {
			$identifiersql = 'categoryid=#';
		}
	} else {
		$identifiersql = 'backpath=$';
		$slugsorid = as_db_slugs_to_backpath($slugsorid);
	}

	$parentselects = array( // requires AS_CATEGORY_DEPTH=4
		'SELECT NULL AS parentkey', // top level
		'SELECT grandparent.parentid FROM ^categories JOIN ^categories AS parent ON ^categories.parentid=parent.categoryid JOIN ^categories AS grandparent ON parent.parentid=grandparent.categoryid WHERE ^categories.' . $identifiersql, // 2 gens up
		'SELECT parent.parentid FROM ^categories JOIN ^categories AS parent ON ^categories.parentid=parent.categoryid WHERE ^categories.' . $identifiersql,
		// 1 gen up
		'SELECT parentid FROM ^categories WHERE ' . $identifiersql, // same gen
		'SELECT categoryid FROM ^categories WHERE ' . $identifiersql, // gen below
	);

	$columns = array(
		'parentid' => '^categories.parentid',
		'title' => '^categories.title',
		'enabled' => '^categories.enabled',
		'tags' => '^categories.tags',
		'qcount' => '^categories.qcount',
		'position' => '^categories.position',
	);

	if ($full) {
		foreach ($columns as $alias => $column) {
			$columns[$alias] = 'MAX(' . $column . ')';
		}

		$columns['childcount'] = 'COUNT(child.categoryid)';
		$columns['content'] = 'MAX(^categories.content)';
		$columns['backpath'] = 'MAX(^categories.backpath)';
	}

	array_unshift($columns, '^categories.categoryid');

	$selectspec = array(
		'columns' => $columns,
		'source' => '^categories JOIN (' . implode(' UNION ', $parentselects) . ') y ON ^categories.parentid<=>parentkey' .
			($full ? ' LEFT JOIN ^categories AS child ON child.parentid=^categories.categoryid GROUP BY ^categories.categoryid' : '') .
			' ORDER BY ^categories.position',
		'arguments' => array($slugsorid, $slugsorid, $slugsorid, $slugsorid),
		'arraykey' => 'categoryid',
		'sortasc' => 'position',
	);

	return $selectspec;
}


/**
 * Return the selectspec to retrieve information on all subcategories of $categoryid (used for Ajax navigation of hierarchy)
 * @param $categoryid
 * @return array
 */
function as_db_category_sub_selectspec($categoryid)
{
	return array(
		'columns' => array('categoryid', 'title', 'tags', 'qcount', 'position'),
		'source' => '^categories WHERE parentid<=># ORDER BY position',
		'arguments' => array($categoryid),
		'arraykey' => 'categoryid',
		'sortasc' => 'position',
	);
}

/**
 * Return the selectspec to retrieve information on all subcategories of $categoryid (used for Ajax navigation of hierarchy)
 * @param $categoryid
 * @return array
 */
function as_db_category_enabled()
{
	return array(//categoryid, enabled,title, tags, qcount, content, backpath
		'columns' => array('categoryid', 'title', 'tags', 'qcount', 'content', 'backpath', 'position'),
		'source' => '^categories WHERE enabled=1 ORDER BY position',
		'arraykey' => 'categoryid',
		'sortasc' => 'position',
	);
}


/**
 * Return the selectspec to retrieve a single category as specified by its $slugs (in order of hierarchy)
 * @param $slugs
 * @return array
 */
function as_db_slugs_to_category_id_selectspec($slugs)
{
	return array(
		'columns' => array('categoryid'),
		'source' => '^categories WHERE backpath=$',
		'arguments' => array(as_db_slugs_to_backpath($slugs)),
		'arrayvalue' => 'categoryid',
		'single' => true,
	);
}


/**
 * Return the selectspec to retrieve the list of custom pages or links, ordered for display
 * @param $onlynavin
 * @param $onlypageids
 * @return array
 */
function as_db_pages_selectspec($onlynavin = null, $onlypageids = null)
{
	$selectspec = array(
		// +0 required to work around MySQL bug where by permit value is mis-read as signed, e.g. -106 instead of 150
		'columns' => array('pageid', 'title', 'flags', 'permit' => 'permit+0', 'nav', 'tags', 'position', 'heading'),
		'arraykey' => 'pageid',
		'sortasc' => 'position',
	);

	if (isset($onlypageids)) {
		$selectspec['source'] = '^pages WHERE pageid IN (#)';
		$selectspec['arguments'] = array($onlypageids);
	} elseif (isset($onlynavin)) {
		$selectspec['source'] = '^pages WHERE nav IN ($) ORDER BY position';
		$selectspec['arguments'] = array($onlynavin);
	} else {
		$selectspec['source'] = '^pages ORDER BY position';
	}

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the list of widgets, ordered for display
 */
function as_db_widgets_selectspec()
{
	return array(
		'columns' => array('widgetid', 'place', 'position', 'tags', 'title'),
		'source' => '^widgets ORDER BY position',
		'sortasc' => 'position',
	);
}


/**
 * Return the selectspec to retrieve the full information about a custom page
 * @param $slugorpageid
 * @param $ispageid
 * @return array
 */
function as_db_page_full_selectspec($slugorpageid, $ispageid)
{
	return array(
		'columns' => array('pageid', 'title', 'flags', 'permit', 'nav', 'tags', 'position', 'heading', 'content'),
		'source' => '^pages WHERE ' . ($ispageid ? 'pageid' : 'tags') . '=$',
		'arguments' => array($slugorpageid),
		'single' => true,
	);
}


/**
 * Return the selectspec to retrieve the most recent songs with $tag, with the corresponding thumb on those
 * songs made by $thumbuserid (if not null) and including $full content or not. Return $count (if null, a default is
 * used) songs starting from $start.
 * @param $thumbuserid
 * @param $tag
 * @param $start
 * @param bool $full
 * @param $count
 * @return array
 */
function as_db_tag_recent_qs_selectspec($thumbuserid, $tag, $start, $full = false, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	require_once AS_INCLUDE_DIR . 'util/string.php';

	$selectspec = as_db_posts_basic_selectspec($thumbuserid, $full);

	// use two tests here - one which can use the index, and the other which narrows it down exactly - then limit to 1 just in case
	$selectspec['source'] .= " JOIN (SELECT postid FROM ^posttags WHERE wordid=(SELECT wordid FROM ^words WHERE word=$ AND word=$ COLLATE utf8_bin LIMIT 1) ORDER BY postcreated DESC LIMIT #,#) y ON ^posts.postid=y.postid";
	array_push($selectspec['arguments'], $tag, as_strtolower($tag), $start, $count);
	$selectspec['sortdesc'] = 'created';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the number of songs tagged with $tag (single value)
 * @param $tag
 * @return array
 */
function as_db_tag_word_selectspec($tag)
{
	return array(
		'columns' => array('wordid', 'word', 'tagcount'),
		'source' => '^words WHERE word=$ AND word=$ COLLATE utf8_bin',
		'arguments' => array($tag, as_strtolower($tag)),
		'single' => true,
	);
}


/**
 * Return the selectspec to retrieve recent songs by the user identified by $identifier, where $identifier is a
 * handle if we're using internal user management, or a userid if we're using external users. Also include the
 * corresponding thumb on those songs made by $thumbuserid (if not null). Return $count (if null, a default is used)
 * songs.
 * @param $thumbuserid
 * @param $identifier
 * @param $count
 * @param int $start
 * @return array
 */
function as_db_user_recent_qs_selectspec($thumbuserid, $identifier, $count = null, $start = 0)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	$selectspec['source'] .= " WHERE ^posts.userid=" . (AS_FINAL_EXTERNAL_USERS ? "$" : "(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)") . " AND type='S' ORDER BY ^posts.created DESC LIMIT #,#";
	array_push($selectspec['arguments'], $identifier, $start, $count);
	$selectspec['sortdesc'] = 'created';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the antecedent songs for recent reviews by the user identified by $identifier
 * (see as_db_user_recent_qs_selectspec() comment), with the corresponding thumb on those songs made by $thumbuserid
 * (if not null). Return $count (if null, a default is used) songs. The selectspec will also retrieve some
 * information about the reviews themselves, in columns named with the prefix 'o'.
 * @param $thumbuserid
 * @param $identifier
 * @param $count
 * @param int $start
 * @return array
 */
function as_db_user_recent_a_qs_selectspec($thumbuserid, $identifier, $count = null, $start = 0)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	as_db_add_selectspec_opost($selectspec, 'aposts');

	$selectspec['columns']['othumbsup'] = 'aposts.thumbsup';
	$selectspec['columns']['othumbsdown'] = 'aposts.thumbsdown';
	$selectspec['columns']['onetthumbs'] = 'aposts.netthumbs';

	$selectspec['source'] .=
		" JOIN ^posts AS aposts ON ^posts.postid=aposts.parentid" .
		" JOIN (SELECT postid FROM ^posts WHERE " .
		" userid=" . (AS_FINAL_EXTERNAL_USERS ? "$" : "(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)") .
		" AND type='R' ORDER BY created DESC LIMIT #,#) y ON aposts.postid=y.postid WHERE ^posts.type='S'";

	array_push($selectspec['arguments'], $identifier, $start, $count);
	$selectspec['sortdesc'] = 'otime';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the antecedent songs for recent comments by the user identified by $identifier
 * (see as_db_user_recent_qs_selectspec() comment), with the corresponding thumb on those songs made by $thumbuserid
 * (if not null). Return $count (if null, a default is used) songs. The selectspec will also retrieve some
 * information about the comments themselves, in columns named with the prefix 'o'.
 * @param $thumbuserid
 * @param $identifier
 * @param $count
 * @return array
 */
function as_db_user_recent_c_qs_selectspec($thumbuserid, $identifier, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	as_db_add_selectspec_opost($selectspec, 'cposts');

	$selectspec['source'] .=
		" JOIN ^posts AS parentposts ON" .
		" ^posts.postid=(CASE parentposts.type WHEN 'R' THEN parentposts.parentid ELSE parentposts.postid END)" .
		" JOIN ^posts AS cposts ON parentposts.postid=cposts.parentid" .
		" JOIN (SELECT postid FROM ^posts WHERE " .
		" userid=" . (AS_FINAL_EXTERNAL_USERS ? "$" : "(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)") .
		" AND type='C' ORDER BY created DESC LIMIT #) y ON cposts.postid=y.postid WHERE ^posts.type='S' AND parentposts.type IN ('S', 'R')";

	array_push($selectspec['arguments'], $identifier, $count);
	$selectspec['sortdesc'] = 'otime';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the antecedent songs for recently edited posts by the user identified by
 * $identifier (see as_db_user_recent_qs_selectspec() comment), with the corresponding thumb on those songs made by
 * $thumbuserid (if not null). Return $count (if null, a default is used) songs. The selectspec will also retrieve
 * some information about the edited posts themselves, in columns named with the prefix 'o'.
 * @param $thumbuserid
 * @param $identifier
 * @param $count
 * @return array
 */
function as_db_user_recent_edit_qs_selectspec($thumbuserid, $identifier, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_QS_AS) : AS_DB_RETRIEVE_QS_AS;

	$selectspec = as_db_posts_basic_selectspec($thumbuserid);

	as_db_add_selectspec_opost($selectspec, 'editposts', true);

	$selectspec['source'] .=
		" JOIN ^posts AS parentposts ON" .
		" ^posts.postid=IF(LEFT(parentposts.type, 1)='S', parentposts.postid, parentposts.parentid)" .
		" JOIN ^posts AS editposts ON parentposts.postid=IF(LEFT(editposts.type, 1)='S', editposts.postid, editposts.parentid)" .
		" JOIN (SELECT postid FROM ^posts WHERE " .
		" lastuserid=" . (AS_FINAL_EXTERNAL_USERS ? "$" : "(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)") .
		" AND type IN ('S', 'R', 'C') ORDER BY updated DESC LIMIT #) y ON editposts.postid=y.postid " .
		" WHERE parentposts.type IN ('S', 'R', 'C') AND ^posts.type IN ('S', 'R', 'C')";

	array_push($selectspec['arguments'], $identifier, $count);
	$selectspec['sortdesc'] = 'otime';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve the most popular tags. Return $count (if null, a default is used) tags, starting
 * from offset $start. The selectspec will produce a sorted array with tags in the key, and counts in the values.
 * @param $start
 * @param $count
 * @return array
 */
function as_db_popular_tags_selectspec($start, $count = null)
{
	$count = isset($count) ? $count : AS_DB_RETRIEVE_TAGS;

	return array(
		'columns' => array('word', 'tagcount'),
		'source' => '^words JOIN (SELECT wordid FROM ^words WHERE tagcount>0 ORDER BY tagcount DESC LIMIT #,#) y ON ^words.wordid=y.wordid',
		'arguments' => array($start, $count),
		'arraykey' => 'word',
		'arrayvalue' => 'tagcount',
		'sortdesc' => 'tagcount',
	);
}


/**
 * Return the selectspec to retrieve the list of user profile fields, ordered for display
 */
function as_db_userfields_selectspec()
{
	return array(
		'columns' => array('fieldid', 'title', 'content', 'flags', 'permit', 'position'),
		'source' => '^userfields',
		'arraykey' => 'title',
		'sortasc' => 'position',
	);
}


/**
 * Return the selecspec to retrieve a single array with details of the account of the user identified by
 * $useridhandle, which should be a userid if $isuserid is true, otherwise $useridhandle should be a handle.
 * @param $useridhandle
 * @param $isuserid
 * @return array
 */
function as_db_user_account_selectspec($useridhandle, $isuserid)
{
	return array(
		'columns' => array(
			'^users.userid', 'passsalt', 'passcheck' => 'HEX(passcheck)', 'passhash', 'firstname', 'lastname', '^users.country', 'mobile', 'gender', 'cityname' => '^cities.title', 'churchname' => '^churches.title', 'email', 'level', 'emailcode', 'handle',
			'created' => 'UNIX_TIMESTAMP(created)', 'sessioncode', 'sessionsource', 'flags', 'signedin' => 'UNIX_TIMESTAMP(signedin)',
			'signinip', 'written' => 'UNIX_TIMESTAMP(written)', 'writeip',
			'avatarblobid' => 'BINARY avatarblobid', // cast to BINARY due to MySQL bug which renders it signed in a union
			'avatarwidth', 'avatarheight', 'points', 'wallposts',
		),

		'source' => '^users LEFT JOIN ^userpoints ON ^userpoints.userid=^users.userid' . 
			' LEFT JOIN ^cities ON ^cities.cityid=^users.city LEFT JOIN ^churches ON ^churches.churchid=^users.church' .
			' WHERE ^users.' . ($isuserid ? 'userid' : 'handle') . '=$',
		'arguments' => array($useridhandle),
		'single' => true,
	);
}


/**
 * Return the selectspec to retrieve all user profile information of the user identified by
 * $useridhandle (see as_db_user_account_selectspec() comment), as an array of [field] => [value]
 * @param $useridhandle
 * @param $isuserid
 * @return array
 */
function as_db_user_profile_selectspec($useridhandle, $isuserid)
{
	return array(
		'columns' => array('title', 'content'),
		'source' => '^userprofile WHERE userid=' . ($isuserid ? '$' : '(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)'),
		'arguments' => array($useridhandle),
		'arraykey' => 'title',
		'arrayvalue' => 'content',
	);
}


/**
 * Return the selectspec to retrieve all notices for the user $userid
 * @param $userid
 * @return array
 */
function as_db_user_notices_selectspec($userid)
{
	return array(
		'columns' => array('noticeid', 'content', 'format', 'tags', 'created' => 'UNIX_TIMESTAMP(created)'),
		'source' => '^usernotices WHERE userid=$ ORDER BY created',
		'arguments' => array($userid),
		'sortasc' => 'created',
	);
}


/**
 * Return the selectspec to retrieve all columns from the userpoints table for the user identified by $identifier
 * (see as_db_user_recent_qs_selectspec() comment), as a single array
 * @param $identifier
 * @param bool $isuserid
 * @return array
 */
function as_db_user_points_selectspec($identifier, $isuserid = AS_FINAL_EXTERNAL_USERS)
{
	return array(
		'columns' => array('points', 'qposts', 'aposts', 'cposts', 'aselects', 'aselecteds', 'qthumbsup', 'qthumbsdown', 'athumbsup', 'athumbsdown', 'qthumbds', 'athumbds', 'upthumbds', 'downthumbds', 'bonus'),
		'source' => '^userpoints WHERE userid=' . ($isuserid ? '$' : '(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)'),
		'arguments' => array($identifier),
		'single' => true,
	);
}


/**
 * Return the selectspec to calculate the rank in points of the user identified by $identifier
 * (see as_db_user_recent_qs_selectspec() comment), as a single value
 * @param $identifier
 * @param bool $isuserid
 * @return array
 */
function as_db_user_rank_selectspec($identifier, $isuserid = AS_FINAL_EXTERNAL_USERS)
{
	return array(
		'columns' => array('rank' => '1+COUNT(*)'),
		'source' => '^userpoints WHERE points>COALESCE((SELECT points FROM ^userpoints WHERE userid=' . ($isuserid ? '$' : '(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)') . '), 0)',
		'arguments' => array($identifier),
		'arrayvalue' => 'rank',
		'single' => true,
	);
}


/**
 * Return the selectspec to get the top scoring users, with handles if we're using internal user management. Return
 * $count (if null, a default is used) users starting from the offset $start.
 * @param $start
 * @param $count
 * @return array
 */
function as_db_top_users_selectspec($start, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_USERS) : AS_DB_RETRIEVE_USERS;

	if (AS_FINAL_EXTERNAL_USERS) {
		return array(
			'columns' => array('userid', 'points'),
			'source' => '^userpoints ORDER BY points DESC LIMIT #,#',
			'arguments' => array($start, $count),
			'arraykey' => 'userid',
			'sortdesc' => 'points',
		);
	}

	// If the site is configured to share the ^users table then there might not be a record in the ^userpoints table
	if (defined('AS_MYSQL_USERS_PREFIX')) {
		$basePoints = (int)as_opt('points_base');
		$source = '^users JOIN (SELECT ^users.userid, COALESCE(points,' . $basePoints . ') AS points FROM ^users LEFT JOIN ^userpoints ON ^users.userid=^userpoints.userid ORDER BY points DESC LIMIT #,#) y ON ^users.userid=y.userid';
	} else {
		$source = '^users JOIN (SELECT userid FROM ^userpoints ORDER BY points DESC LIMIT #,#) y ON ^users.userid=y.userid JOIN ^userpoints ON ^users.userid=^userpoints.userid';;
	}

	return array(
		'columns' => array('^users.userid', 'handle', 'points', 'flags', '^users.email', 'avatarblobid' => 'BINARY avatarblobid', 'avatarwidth', 'avatarheight'),
		'source' => $source,
		'arguments' => array($start, $count),
		'arraykey' => 'userid',
		'sortdesc' => 'points',
	);
}


/**
 * Return the selectspec to get the newest users. Return $count (if null, a default is used) users starting from the
 * offset $start. This query must not be run when using external users
 * @param $start
 * @param $count
 * @return array
 */
function as_db_newest_users_selectspec($start, $count = null)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_USERS) : AS_DB_RETRIEVE_USERS;

	return array(
		'columns' => array('userid', 'handle', 'flags', 'email', 'created' => 'UNIX_TIMESTAMP(created)', 'avatarblobid' => 'BINARY avatarblobid', 'avatarwidth', 'avatarheight'),
		'source' => '^users ORDER BY created DESC, userid DESC LIMIT #,#',
		'arguments' => array($start, $count),
		'sortdesc' => 'created',
		'sortdesc_2' => 'userid',
	);
}


/**
 * Return the selectspec to get information about users at a certain privilege level or higher
 * @param $level
 * @return array
 */
function as_db_users_from_level_selectspec($level)
{
	return array(
		'columns' => array('^users.userid', 'handle', 'level'),
		'source' => '^users WHERE level>=# ORDER BY level DESC',
		'arguments' => array($level),
		'sortdesc' => 'level',
	);
}


/**
 * Return the selectspec to get information about users with the $flag bit set (unindexed query)
 * @param $flag
 * @param int $start
 * @param $limit
 * @return array
 */
function as_db_users_with_flag_selectspec($flag, $start = 0, $limit = null)
{
	$source = '^users WHERE (flags & #)';
	$arguments = array($flag);

	if (isset($limit)) {
		$limit = min($limit, AS_DB_RETRIEVE_USERS);
		$source .= ' LIMIT #,#';
		array_push($arguments, $start, $limit);
	}

	return array(
		'columns' => array('^users.userid', 'handle', 'flags', 'level'),
		'source' => $source,
		'arguments' => $arguments,
	);
}


/**
 * Return columns for standard messages selectspec
 */
function as_db_messages_columns()
{
	return array(
		'messageid', 'fromuserid', 'touserid', 'content', 'format',
		'created' => 'UNIX_TIMESTAMP(^messages.created)',

		'fromflags' => 'ufrom.flags', 'fromlevel' => 'ufrom.level',
		'fromemail' => 'ufrom.email', 'fromhandle' => 'ufrom.handle',
		'fromavatarblobid' => 'BINARY ufrom.avatarblobid', // cast to BINARY due to MySQL bug which renders it signed in a union
		'fromavatarwidth' => 'ufrom.avatarwidth', 'fromavatarheight' => 'ufrom.avatarheight',

		'toflags' => 'uto.flags', 'tolevel' => 'uto.level',
		'toemail' => 'uto.email', 'tohandle' => 'uto.handle',
		'toavatarblobid' => 'BINARY uto.avatarblobid', // cast to BINARY due to MySQL bug which renders it signed in a union
		'toavatarwidth' => 'uto.avatarwidth', 'toavatarheight' => 'uto.avatarheight',
	);
}


/**
 * If $fromidentifier is not null, return the selectspec to get recent private messages which have been sent from
 * the user identified by $fromidentifier+$fromisuserid to the user identified by $toidentifier+$toisuserid (see
 * as_db_user_recent_qs_selectspec() comment). If $fromidentifier is null, then get recent wall posts
 * for the user identified by $toidentifier+$toisuserid. Return $count (if null, a default is used) messages.
 * @param $fromidentifier
 * @param $fromisuserid
 * @param $toidentifier
 * @param $toisuserid
 * @param $count
 * @param int $start
 * @return array
 */
function as_db_recent_messages_selectspec($fromidentifier, $fromisuserid, $toidentifier, $toisuserid, $count = null, $start = 0)
{
	$count = isset($count) ? min($count, AS_DB_RETRIEVE_MESSAGES) : AS_DB_RETRIEVE_MESSAGES;

	if (isset($fromidentifier)) {
		$fromsub = $fromisuserid ? '$' : '(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)';
		$where = 'fromuserid=' . $fromsub . " AND type='PRIVATE'";
	} else {
		$where = "type='PUBLIC'";
	}
	$tosub = $toisuserid ? '$' : '(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)';

	$source = '^messages LEFT JOIN ^users ufrom ON fromuserid=ufrom.userid LEFT JOIN ^users uto ON touserid=uto.userid WHERE ' . $where . ' AND touserid=' . $tosub . ' ORDER BY ^messages.created DESC LIMIT #,#';

	$arguments = isset($fromidentifier) ? array($fromidentifier, $toidentifier, $start, $count) : array($toidentifier, $start, $count);

	return array(
		'columns' => as_db_messages_columns(),
		'source' => $source,
		'arguments' => $arguments,
		'arraykey' => 'messageid',
		'sortdesc' => 'created',
	);
}


/**
 * Get selectspec for messages *to* specified user. $type is either 'public' or 'private'.
 * $toidentifier is a handle or userid depending on the value of $toisuserid.
 * Returns $limit messages, or all of them if $limit is null (used in as_db_selectspec_count).
 * @param $type
 * @param $toidentifier
 * @param $toisuserid
 * @param int $start
 * @param $limit
 * @return array
 */
function as_db_messages_inbox_selectspec($type, $toidentifier, $toisuserid, $start = 0, $limit = null)
{
	$type = strtoupper($type);

	$where = 'touserid=' . ($toisuserid ? '$' : '(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)') . ' AND type=$ AND tohidden=0';
	$source = '^messages LEFT JOIN ^users ufrom ON fromuserid=ufrom.userid LEFT JOIN ^users uto ON touserid=uto.userid WHERE ' . $where . ' ORDER BY ^messages.created DESC';
	$arguments = array($toidentifier, $type);

	if (isset($limit)) {
		$limit = min($limit, AS_DB_RETRIEVE_MESSAGES);
		$source .= ' LIMIT #,#';
		$arguments[] = $start;
		$arguments[] = $limit;
	}

	return array(
		'columns' => as_db_messages_columns(),
		'source' => $source,
		'arguments' => $arguments,
		'arraykey' => 'messageid',
		'sortdesc' => 'created',
	);
}


/**
 * Get selectspec for messages *from* specified user. $type is either 'public' or 'private'.
 * $fromidentifier is a handle or userid depending on the value of $fromisuserid.
 * Returns $limit messages, or all of them if $limit is null (used in as_db_selectspec_count).
 * @param $type
 * @param $fromidentifier
 * @param $fromisuserid
 * @param int $start
 * @param $limit
 * @return array
 */
function as_db_messages_outbox_selectspec($type, $fromidentifier, $fromisuserid, $start = 0, $limit = null)
{
	$type = strtoupper($type);

	$where = 'fromuserid=' . ($fromisuserid ? '$' : '(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)') . ' AND type=$ AND fromhidden=0';
	$source = '^messages LEFT JOIN ^users ufrom ON fromuserid=ufrom.userid LEFT JOIN ^users uto ON touserid=uto.userid WHERE ' . $where . ' ORDER BY ^messages.created DESC';
	$arguments = array($fromidentifier, $type);

	if (isset($limit)) {
		$limit = min($limit, AS_DB_RETRIEVE_MESSAGES);
		$source .= ' LIMIT #,#';
		$arguments[] = $start;
		$arguments[] = $limit;
	}

	return array(
		'columns' => as_db_messages_columns(),
		'source' => $source,
		'arguments' => $arguments,
		'arraykey' => 'messageid',
		'sortdesc' => 'created',
	);
}


/**
 * Return the selectspec to retrieve whether or not $userid has favorited entity $entitytype identifier by $identifier.
 * The $identifier should be a handle, word, backpath or postid for users, tags, categories and songs respectively.
 * @param $userid
 * @param $entitytype
 * @param $identifier
 * @return array
 */
function as_db_is_favorite_selectspec($userid, $entitytype, $identifier)
{
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	$selectspec = array(
		'columns' => array('flags' => 'COUNT(*)'),
		'source' => '^userfavorites WHERE userid=$ AND entitytype=$',
		'arrayvalue' => 'flags',
		'single' => true,
	);

	switch ($entitytype) {
		case AS_ENTITY_USER:
			$selectspec['source'] .= ' AND entityid=(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)';
			break;

		case AS_ENTITY_TAG:
			$selectspec['source'] .= ' AND entityid=(SELECT wordid FROM ^words WHERE word=$ LIMIT 1)';
			break;

		case AS_ENTITY_CATEGORY:
			$selectspec['source'] .= ' AND entityid=(SELECT categoryid FROM ^categories WHERE backpath=$ LIMIT 1)';
			$identifier = as_db_slugs_to_backpath($identifier);
			break;

		default:
			$selectspec['source'] .= ' AND entityid=$';
			break;
	}

	$selectspec['arguments'] = array($userid, $entitytype, $identifier);

	return $selectspec;
}


/**
 * Return the selectspec to retrieve an array of $userid's favorited songs, with the usual information.
 * Returns $limit songs, or all of them if $limit is null (used in as_db_selectspec_count).
 * @param $userid
 * @param $limit
 * @param int $start
 * @return array
 */
function as_db_user_favorite_qs_selectspec($userid, $limit = null, $start = 0)
{
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	$selectspec = as_db_posts_basic_selectspec($userid);

	$selectspec['source'] .= ' JOIN ^userfavorites AS selectfave ON ^posts.postid=selectfave.entityid WHERE selectfave.userid=$ AND selectfave.entitytype=$ AND ^posts.type="Q" ORDER BY ^posts.created DESC';
	$selectspec['arguments'][] = $userid;
	$selectspec['arguments'][] = AS_ENTITY_SONG;

	if (isset($limit)) {
		$limit = min($limit, AS_DB_RETRIEVE_QS_AS);
		$selectspec['source'] .= ' LIMIT #,#';
		$selectspec['arguments'][] = $start;
		$selectspec['arguments'][] = $limit;
	}

	$selectspec['sortdesc'] = 'created';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve an array of $userid's favorited users, with information about those users' accounts.
 * Returns $limit users, or all of them if $limit is null (used in as_db_selectspec_count).
 * @param $userid
 * @param $limit
 * @param int $start
 * @return array
 */
function as_db_user_favorite_users_selectspec($userid, $limit = null, $start = 0)
{
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	$source = '^users JOIN ^userpoints ON ^users.userid=^userpoints.userid JOIN ^userfavorites ON ^users.userid=^userfavorites.entityid WHERE ^userfavorites.userid=$ AND ^userfavorites.entitytype=$ ORDER BY ^users.handle';
	$arguments = array($userid, AS_ENTITY_USER);

	if (isset($limit)) {
		$limit = min($limit, AS_DB_RETRIEVE_USERS);
		$source .= ' LIMIT #,#';
		$arguments[] = $start;
		$arguments[] = $limit;
	}

	return array(
		'columns' => array('^users.userid', 'handle', 'points', 'flags', '^users.email', 'avatarblobid' => 'BINARY avatarblobid', 'avatarwidth', 'avatarheight'),
		'source' => $source,
		'arguments' => $arguments,
		'sortasc' => 'handle',
	);
}


/**
 * Return the selectspec to retrieve an array of $userid's favorited tags, with information about those tags.
 * Returns $limit tags, or all of them if $limit is null (used in as_db_selectspec_count).
 * @param $userid
 * @param $limit
 * @param int $start
 * @return array
 */
function as_db_user_favorite_tags_selectspec($userid, $limit = null, $start = 0)
{
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	$source = '^words JOIN ^userfavorites ON ^words.wordid=^userfavorites.entityid WHERE ^userfavorites.userid=$ AND ^userfavorites.entitytype=$ ORDER BY ^words.tagcount DESC';
	$arguments = array($userid, AS_ENTITY_TAG);

	if (isset($limit)) {
		$limit = min($limit, AS_DB_RETRIEVE_TAGS);
		$source .= ' LIMIT #,#';
		$arguments[] = $start;
		$arguments[] = $limit;
	}

	return array(
		'columns' => array('word', 'tagcount'),
		'source' => $source,
		'arguments' => $arguments,
		'sortdesc' => 'tagcount',
	);
}


/**
 * Return the selectspec to retrieve an array of $userid's favorited categories, with information about those categories.
 * @param $userid
 * @return array
 */
function as_db_user_favorite_categories_selectspec($userid)
{
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	return array(
		'columns' => array('categoryid', 'title', 'tags', 'qcount', 'backpath', 'content'),
		'source' => "^categories JOIN ^userfavorites ON ^categories.categoryid=^userfavorites.entityid WHERE ^userfavorites.userid=$ AND ^userfavorites.entitytype=$",
		'arguments' => array($userid, AS_ENTITY_CATEGORY),
		'sortasc' => 'title',
	);
}


/**
 * Return the selectspec to retrieve information about all a user's favorited items except the songs. Depending on
 * the type of item, the array for each item will contain a userid, category backpath or tag word.
 * @param $userid
 * @return array
 */
function as_db_user_favorite_non_qs_selectspec($userid)
{
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	return array(
		'columns' => array('type' => 'entitytype', 'userid' => 'IF (entitytype=$, entityid, NULL)', 'categorybackpath' => '^categories.backpath', 'tags' => '^words.word'),
		'source' => '^userfavorites LEFT JOIN ^words ON entitytype=$ AND wordid=entityid LEFT JOIN ^categories ON entitytype=$ AND categoryid=entityid WHERE userid=$ AND entitytype!=$',
		'arguments' => array(AS_ENTITY_USER, AS_ENTITY_TAG, AS_ENTITY_CATEGORY, $userid, AS_ENTITY_SONG),
	);
}


/**
 * Return the selectspec to retrieve the list of recent updates for $userid. Set $forfavorites to whether this should
 * include updates on the user's favorites and $forcontent to whether it should include responses to user's content.
 * This combines events from both the user's stream and the the shared stream for any entities which the user has
 * favorited and which no longer post to user streams (see long comment in /as-include/db/favorites.php).
 * @param $userid
 * @param bool $forfavorites
 * @param bool $forcontent
 * @return array
 */
function as_db_user_updates_selectspec($userid, $forfavorites = true, $forcontent = true)
{
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	$selectspec = as_db_posts_basic_selectspec($userid);

	$nonesql = as_db_argument_to_mysql(AS_ENTITY_NONE, true);

	$selectspec['columns']['obasetype'] = 'LEFT(updateposts.type, 1)';
	$selectspec['columns']['oupdatetype'] = 'fullevents.updatetype';
	$selectspec['columns']['ohidden'] = "INSTR(updateposts.type, '_HIDDEN')>0";
	$selectspec['columns']['opostid'] = 'fullevents.lastpostid';
	$selectspec['columns']['ouserid'] = 'fullevents.lastuserid';
	$selectspec['columns']['otime'] = 'UNIX_TIMESTAMP(fullevents.updated)';
	$selectspec['columns']['opersonal'] = 'fullevents.entitytype=' . $nonesql;
	$selectspec['columns']['oparentid'] = 'updateposts.parentid';

	as_db_add_selectspec_ousers($selectspec, 'eventusers', 'eventuserpoints');

	if ($forfavorites) { // life is hard
		$selectspec['source'] .= ' JOIN ' .
			"(SELECT entitytype, songid, lastpostid, updatetype, lastuserid, updated FROM ^userevents WHERE userid=$" .
			($forcontent ? '' : " AND entitytype!=" . $nonesql) .
			" UNION SELECT ^sharedevents.entitytype, songid, lastpostid, updatetype, lastuserid, updated FROM ^sharedevents JOIN ^userfavorites ON ^sharedevents.entitytype=^userfavorites.entitytype AND ^sharedevents.entityid=^userfavorites.entityid AND ^userfavorites.nouserevents=1 WHERE userid=$) fullevents ON ^posts.postid=fullevents.songid";

		array_push($selectspec['arguments'], $userid, $userid);

	} else { // life is easy
		$selectspec['source'] .= " JOIN ^userevents AS fullevents ON ^posts.postid=fullevents.songid AND fullevents.userid=$ AND fullevents.entitytype=" . $nonesql;
		$selectspec['arguments'][] = $userid;
	}

	$selectspec['source'] .=
		" JOIN ^posts AS updateposts ON updateposts.postid=fullevents.lastpostid" .
		" AND (updateposts.type IN ('S', 'R', 'C') OR fullevents.entitytype=" . $nonesql . ")" .
		" AND (^posts.selchildid=fullevents.lastpostid OR NOT fullevents.updatetype<=>$) AND ^posts.type IN ('S', 'S_HIDDEN')" .
		(AS_FINAL_EXTERNAL_USERS ? '' : ' LEFT JOIN ^users AS eventusers ON fullevents.lastuserid=eventusers.userid') .
		' LEFT JOIN ^userpoints AS eventuserpoints ON fullevents.lastuserid=eventuserpoints.userid';
	$selectspec['arguments'][] = AS_UPDATE_SELECTED;

	unset($selectspec['arraykey']); // allow same song to be retrieved multiple times

	$selectspec['sortdesc'] = 'otime';

	return $selectspec;
}


/**
 * Return the selectspec to retrieve all of the per-hour activity limits for user $userid
 * @param $userid
 * @return array
 */
function as_db_user_limits_selectspec($userid)
{
	return array(
		'columns' => array('action', 'period', 'count'),
		'source' => '^userlimits WHERE userid=$',
		'arguments' => array($userid),
		'arraykey' => 'action',
	);
}


/**
 * Return the selectspec to retrieve all of the per-hour activity limits for ip address $ip
 * @param $ip
 * @return array
 */
function as_db_ip_limits_selectspec($ip)
{
	return array(
		'columns' => array('action', 'period', 'count'),
		'source' => '^iplimits WHERE ip=UNHEX($)',
		'arguments' => array(bin2hex(@inet_pton($ip))),
		'arraykey' => 'action',
	);
}


/**
 * Return the selectspec to retrieve all of the context specific (currently per-categpry) levels for the user identified by
 * $identifier, which is treated as a userid if $isuserid is true, otherwise as a handle. Set $full to true to obtain extra
 * information about these contexts (currently, categories).
 * @param $identifier
 * @param bool $isuserid
 * @param bool $full
 * @return array
 */
function as_db_user_levels_selectspec($identifier, $isuserid = AS_FINAL_EXTERNAL_USERS, $full = false)
{
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	$selectspec = array(
		'columns' => array('entityid', 'entitytype', 'level'),
		'source' => '^userlevels' . ($full ? ' LEFT JOIN ^categories ON ^userlevels.entitytype=$ AND ^userlevels.entityid=^categories.categoryid' : '') . ' WHERE userid=' . ($isuserid ? '$' : '(SELECT userid FROM ^users WHERE handle=$ LIMIT 1)'),
		'arguments' => array($identifier),
	);

	if ($full) {
		array_push($selectspec['columns'], 'title', 'backpath');
		array_unshift($selectspec['arguments'], AS_ENTITY_CATEGORY);
	}

	return $selectspec;
}
