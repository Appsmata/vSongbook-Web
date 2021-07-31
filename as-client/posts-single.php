<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';
	require_once '../as-include/app/cookies.php';
	require_once '../as-include/as-page-song-view.php';

	$categoryslugs = as_request_parts(1);
	$countslugs = count($categoryslugs);

	$songid = as_get('postid') ? as_get('postid') : '';
	$userid = as_get_logged_in_userid();
	$cookieid = as_cookie_get();
	
	$success = 0;
	$message = '';
	$response = array();
	
	$cacheDriver = APS_Storage_CacheFactory::getCacheDriver();
	$cacheKey = "song:$songid";
	$useCache = $userid === null && $cacheDriver->isEnabled() && !as_is_http_post() && empty($pagestate);
	$saveCache = false;

	if ($useCache) {
		$songData = $cacheDriver->get($cacheKey);
	}

	if (!isset($songData)) {
		$songData = as_db_select_with_pending(
			as_db_full_post_selectspec($userid, $songid),
			as_db_full_child_posts_selectspec($userid, $songid),
			as_db_full_a_child_posts_selectspec($userid, $songid),
			as_db_post_parent_q_selectspec($songid),
			as_db_post_close_post_selectspec($songid),
			as_db_post_duplicates_selectspec($songid),
			as_db_post_meta_selectspec($songid, 'as_q_extra'),
			as_db_category_nav_selectspec($songid, true, true, true),
			isset($userid) ? as_db_is_favorite_selectspec($userid, AS_ENTITY_SONG, $songid) : null
		);

		// whether to save the cache (actioned below, after basic checks)
		$saveCache = $useCache;
	}

	list($song, $childposts, $achildposts, $parentsong, $closepost, $duplicateposts, $extravalue, $categories, $favorite) = $songData;


	if (isset($song)) {		
		$song['extra'] = $extravalue;

		$reviews = as_page_q_load_as($song, $childposts);
		$commentsfollows = as_page_q_load_c_follows($song, $childposts, $achildposts, $duplicateposts);

		$song = $song + as_page_q_post_rules($song, null, null, $childposts + $duplicateposts); // array union

		if ($song['selchildid'] && (@$reviews[$song['selchildid']]['type'] != 'R'))
			$song['selchildid'] = null; // if selected review is hidden or somehow not there, consider it not selected

		foreach ($reviews as $key => $review) {
			$reviews[$key] = $review + as_page_q_post_rules($review, $song, $reviews, $achildposts);
			$reviews[$key]['isselected'] = ($review['postid'] == $song['selchildid']);
		}

		foreach ($commentsfollows as $key => $commentfollow) {
			$parent = ($commentfollow['parentid'] == $songid) ? $song : @$reviews[$commentfollow['parentid']];
			$commentsfollows[$key] = $commentfollow + as_page_q_post_rules($commentfollow, $parent, $commentsfollows, null);
		}
		
		$usershtml = as_userids_handles_html(array_merge(array($song), $reviews, $commentsfollows), true);

		$as_content = as_page_q_song_view($song, $parentsong, $closepost, $usershtml, null);
				
		$when = '<b>'.@$as_content['when']['data'].' '.@$as_content['when']['suffix'].'</b>';
		$where = @$as_content['where']['prefix'].' <b>'.@$as_content['where']['data'].'</b>';
		$who = @$as_content['who']['prefix'].' <b>'.@$as_content['who']['data'].'</b> ('. @$as_content['who']['points']['data'].' '. 
			$as_content['who']['points']['suffix'].')';
			
		$response['postid'] 		= $songid;
		$response['basetype'] 		= $song['basetype'];
		$response['title'] 			= $song['title'];
		$response['content'] 		= $song['content'];
		$response['tags']			= $song['tags'];
		$response['created'] 		= $song['created'];
		$response['categoryid'] 	= $song['categoryid'];
		$response['category'] 		= $song['categoryname'];
		$response['meta_order'] 	= $as_content['meta_order'];
		$response['what'] 			= $as_content['what'];
		$response['when'] 			= trim($when);
		$response['where'] 			= trim($where);
		$response['who'] 			= trim($who);
		$response['netthumbs'] 		= $song['netthumbs'];
		$response['views'] 			= $song['views'];
		$response['hotness'] 		= $song['hotness'];
		$response['acount'] 		= $song['acount'];
		$response['userid'] 		= $song['userid'];
		$response['level'] 			= $song['level'];
		$response['avatar'] 		= $as_content['avatar'];
		$response['thumb_state'] 	= $as_content['thumb_state'];
		
		$success = 1;
	} else {
		$success = 0;
		$message = 'the song was either deleted or hidden.';
	}
	
	$output = json_encode(array('success' => $success, 'message' => $message, 'data' => $response));	
	
	$response["status"] = 1;
	$response["message"] = "Request successful";
	$response["results"] = $result;

	echo json_encode($response);