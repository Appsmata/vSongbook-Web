<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';

	$start = min(max(0, (int)as_get('start')), AS_MAX_LIMIT_START);
	$users = as_db_select_with_pending(as_db_newest_users_selectspec($start, as_opt_if_loaded('page_size_users')));

	$userCount = as_opt('cache_userpointscount');
	$pageSize = as_opt('page_size_users');
	$users = array_slice($users, 0, $pageSize);
	$usersHtml = as_userids_handles_html($users);

	$total = count($users);
	$data = array();
	
	foreach( $users as $user ){
		array_push($data, array(
			'userid' 		=> $user['userid'],
			'handle' 		=> $user['handle'],
			'flags' 		=> $user['flags'],
			'email' 		=> $user['email'],
			'avatarblobid' 	=> $user['avatarblobid'],
			'avatarwidth' 	=> $user['avatarwidth'],
			'avatarheight' 	=> $user['avatarheight'],
			)
		);	
	}
	
	$output = json_encode(array('total' => $total, 'data' => $data));
	
	echo $output;