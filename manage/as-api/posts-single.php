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
	$data = array();
	
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

		/*$as_content = as_content_prepare(true, array_keys(as_category_path($categories, $song['categoryid'])));

		if (isset($userid) && !$formrequested)
			$as_content['favorite'] = as_favorite_form(AS_ENTITY_SONG, $songid, $favorite,
				as_lang($favorite ? 'song/remove_q_favorites' : 'song/add_q_favorites'));

		if (isset($pageerror))
			$as_content['error'] = $pageerror; // might also show thumbing error set in as-index.php

		elseif ($song['queued'])
			$as_content['error'] = $song['isbyuser'] ? as_lang_html('song/q_your_waiting_approval') : as_lang_html('song/q_waiting_your_approval');

		if ($song['hidden'])
			$as_content['hidden'] = true;

		as_sort_by($commentsfollows, 'created');*/

		$as_content = as_page_q_song_view($song, $parentsong, $closepost, $usershtml, null);
				
		$when = '<b>'.@$as_content['when']['data'].' '.@$as_content['when']['suffix'].'</b>';
		$where = @$as_content['where']['prefix'].' <b>'.@$as_content['where']['data'].'</b>';
		$who = @$as_content['who']['prefix'].' <b>'.@$as_content['who']['data'].'</b> ('. @$as_content['who']['points']['data'].' '. 
			$as_content['who']['points']['suffix'].')';
			
		$data['postid'] 		= $songid;
		$data['basetype'] 		= $song['basetype'];
		$data['title'] 			= $song['title'];
		$data['content'] 		= $song['content'];
		$data['tags']			= $song['tags'];
		$data['created'] 		= $song['created'];
		$data['categoryid'] 	= $song['categoryid'];
		$data['category'] 		= $song['categoryname'];
		$data['meta_order'] 	= $as_content['meta_order'];
		$data['what'] 			= $as_content['what'];
		$data['when'] 			= trim($when);
		$data['where'] 			= trim($where);
		$data['who'] 			= trim($who);
		$data['netthumbs'] 		= $song['netthumbs'];
		$data['views'] 			= $song['views'];
		$data['hotness'] 		= $song['hotness'];
		$data['acount'] 		= $song['acount'];
		$data['userid'] 		= $song['userid'];
		$data['level'] 			= $song['level'];
		$data['avatar'] 		= $as_content['avatar'];
		$data['thumb_state'] 	= $as_content['thumb_state'];
		
		$success = 1;
	} else {
		$success = 0;
		$message = 'the song was either deleted or hidden.';
	}
	
	$output = json_encode(array('success' => $success, 'message' => $message, 'data' => $data));	
	
	echo $output;