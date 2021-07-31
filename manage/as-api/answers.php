<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/users.php';

	$categoryslugs = as_request_parts(1);
	$countslugs = count($categoryslugs);
	$userid = as_get_logged_in_userid();

	list($songs, $categories, $categoryid) = as_db_select_with_pending(
		as_db_recent_a_qs_selectspec($userid, 0, $categoryslugs),
		as_db_category_nav_selectspec($categoryslugs, false, false, true),
		$countslugs ? as_db_slugs_to_category_id_selectspec($categoryslugs) : null
	);
	
	$total = count($songs);
	$data = array();
	foreach( $songs as $song ){
		array_push($data, array(
			'postid' 			=> $song['postid'],
			'categoryid' 		=> $song['categoryid'],
			'type' 				=> $song['type'],
			'basetype' 			=> $song['basetype'],
			'hidden' 			=> $song['hidden'],
			'queued' 			=> $song['queued'],
			'acount' 			=> $song['acount'],
			'selchildid' 		=> $song['selchildid'],
			'closedbyid' 		=> $song['closedbyid'],
			'thumbsup' 			=> $song['thumbsup'],
			'thumbsdown' 		=> $song['thumbsdown'],
			'netthumbs' 			=> $song['netthumbs'],
			'views' 			=> $song['views'],
			'hotness' 			=> $song['hotness'],
			'flagcount' 		=> $song['flagcount'],
			'title' 			=> $song['title'],
			'tags' 				=> $song['tags'],
			'created' 			=> $song['created'],
			'name' 				=> $song['name'],
			'categoryname' 		=> $song['categoryname'],
			'categorybackpath' 	=> $song['categorybackpath'],
			'categoryids' 		=> $song['categoryids'],
			'userthumb' 			=> $song['userthumb'],
			'userflag' 			=> $song['userflag'],
			'userfavoriteq' 	=> $song['userfavoriteq'],
			'userid' 			=> $song['userid'],
			'cookieid' 			=> $song['cookieid'],
			'createip' 			=> $song['createip'],
			'points' 			=> $song['points'],
			'flags' 			=> $song['flags'],
			'level' 			=> $song['level'],
			'email' 			=> $song['email'],
			'handle' 			=> $song['handle'],
			'avatarblobid' 		=> $song['avatarblobid'],
			'avatarwidth' 		=> $song['avatarwidth'],
			'avatarheight' 		=> $song['avatarheight'],
			'itemorder' 		=> $song['_order_'])
		);	
	}
	$output = json_encode(array('total' => $total, 'data' => $data));
	
	echo $output;