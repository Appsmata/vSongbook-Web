<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';
	require_once '../as-include/app/cookies.php';

	$books = as_get('books');
	$userid = as_get_logged_in_userid();
	$cookieid = as_cookie_get();
	
	$total = 0;
	$response = array();
	
	if (strlen($books)) {
		$songs = as_db_select_with_pending( as_db_posts_select($userid, $books) );
		
		$total = count($songs);
		
		$usershtml = as_userids_handles_html($songs, true);
		foreach( $songs as $song ){
			$songid = $song['postid'];
			$htmloptions = as_post_html_options($song, null, true);
			$htmloptions['reviewsview'] = false; // review count is displayed separately so don't show it here
			$htmloptions['avatarsize'] = as_opt('avatar_q_page_q_size');
			$htmloptions['q_request'] = as_q_request($songid, $song['title']);
			
			$as_content = as_post_html_fields($song, $userid, $cookieid, $usershtml, null, $htmloptions);
			
			if (array_key_exists('who', $as_content)) {
				$who = @array_key_exists('data', $as_content['who']) ? ' '.@$as_content['who']['data'] : null;
			} else $who = '';
			
			array_push($result, array(
				'postid' 			=> $songid,
				'number' 			=> $song['number'],
				'title' 			=> $song['title'],
				'alias' 			=> $song['alias'] == null ? "" : $song['alias'],
				'content' 			=> $song['content'],
				'tags' 				=> array_key_exists('tags', $song) ? $song['tags'] : null,
				'created' 			=> $song['created'],
				'categoryid' 		=> $song['categoryid'],
				'who' 				=> trim($who),
				'netthumbs' 		=> $song['netthumbs'],
				'acount' 			=> $song['acount'],
				'userid' 			=> $song['userid'])
			);
		}
	}

	$response["status"] = 1;
	$response["message"] = "Request successful";
	$response["results"] = $result;

	echo json_encode($response);