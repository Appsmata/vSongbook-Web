<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';

	$start = min(max(0, (int)as_get('start')), AS_MAX_LIMIT_START);
	$users = as_db_select_with_pending(as_db_top_users_selectspec($start, as_opt_if_loaded('page_size_users')));

	$usercount = as_opt('cache_userpointscount');
	$pagesize = as_opt('page_size_users');
	$users = array_slice($users, 0, $pagesize);
	$usershtml = as_userids_handles_html($users);

	$total = count($users);
	$data = array();

	foreach( $users as $user ){
		array_push($data, array(
			'userid' 		=> $user['userid'],
			'handle' 		=> $user['handle'],
			'points' 		=> $user['points'],
			'flags' 		=> $user['flags'],
			'email' 		=> $user['email'],
			'avatarblobid' 	=> $user['avatarblobid'],
			'avatarwidth' 	=> $user['avatarwidth'],
			'avatarheight' 	=> $user['avatarheight'],
			'_order_' 		=> $user['_order_'])
		);	
	}
	
	$output = json_encode(array('total' => $total, 'data' => $data));
	
	echo $output;