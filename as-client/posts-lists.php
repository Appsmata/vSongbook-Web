<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';
	require_once '../as-include/app/cookies.php';

	$sort = as_get('sort');
	$start = min(max(0, (int)as_get('start')), AS_MAX_LIMIT_START);
	$userid = as_get_logged_in_userid();
	$cookieid = as_cookie_get();

	switch ($sort) {
		case 'hot':
			$selectsort = 'hotness';
			break;

		case 'thumbs':
			$selectsort = 'netthumbs';
			break;

		case 'reviews':
			$selectsort = 'acount';
			break;

		case 'views':
			$selectsort = 'views';
			break;

		default:
			$selectsort = 'created';
			break;
	}

	$songs = as_db_select_with_pending( as_db_qs_selectspec($userid, $selectsort, $start, null, null, false, false, as_opt_if_loaded('page_size_qs')));
	
	$total = count($songs);
	
	$data = array();
	$usershtml = as_userids_handles_html($songs, true);
	foreach( $songs as $song ){
		$songid = $song['postid'];
		$htmloptions = as_post_html_options($song, null, true);
		$htmloptions['reviewsview'] = false; // review count is displayed separately so don't show it here
		$htmloptions['avatarsize'] = as_opt('avatar_q_page_q_size');
		$htmloptions['q_request'] = as_q_request($songid, $song['title']);
		
		$as_content = as_post_html_fields($song, $userid, $cookieid, $usershtml, null, $htmloptions);
		
		if (array_key_exists('when', $as_content)) {
			$when = @array_key_exists('data', $as_content['when']) ? '<b>'.$as_content['when']['data'] : null;
			$when .= @array_key_exists('suffix', $as_content['when']) ? ' '.@$as_content['when']['suffix'].'</b>' : null;
		} else $when = '';
		
		if (array_key_exists('where', $as_content)) {
			$where = @array_key_exists('prefix', $as_content['where']) ? $as_content['where']['prefix'] : null;
			$where .= @array_key_exists('data', $as_content['where']) ? ' <b>'.@$as_content['where']['data'].'</b>' : null;
		} else $where = '';
		
		if (array_key_exists('who', $as_content)) {
			$who = @array_key_exists('prefix', $as_content['who']) ? $as_content['who']['prefix'] : null;
			$who .= @array_key_exists('data', $as_content['who']) ? ' <b>'.@$as_content['who']['data'].'</b>' : null;
			$who .= @array_key_exists('points', $as_content['who']) ? ' ('. @$as_content['who']['points']['data'].' '. 
			$as_content['who']['points']['suffix'].')' : null;
		} else $who = '';
		
		array_push($data, array(
			'postid' 			=> $songid,
			'basetype' 			=> $song['basetype'],
			'title' 			=> $song['title'],
			'tags' 				=> array_key_exists('tags', $song) ? $song['tags'] : null,
			'created' 			=> $song['created'],
			'categoryid' 		=> $song['categoryid'],
			'meta_order' 		=> $as_content['meta_order'],
			'what' 				=> $as_content['what'],
			'when' 				=> trim($when),
			'where' 			=> trim($where),
			'who' 				=> trim($who),
			'netthumbs' 		=> $song['netthumbs'],
			'views' 			=> $song['views'],
			'hotness' 			=> $song['hotness'],
			'acount' 			=> $song['acount'],
			'userid' 			=> $song['userid'],
			'level' 			=> $song['level'],
			'avatar' 			=> $as_content['avatar'],
			'thumb_state' 		=> $as_content['thumb_state'])
		);
	}
	$output = json_encode(array('total' => $total, 'data' => $data));
	
	echo $output;