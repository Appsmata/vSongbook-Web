<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';

	$start = min(max(0, (int)as_get('start')), AS_MAX_LIMIT_START);
	
	$usercount = as_opt('cache_userpointscount');
	$pagesize = as_opt('page_size_users');
	
	$userSpecCount = as_db_selectspec_count(as_db_users_with_flag_selectspec(AS_USER_FLAGS_USER_BLOCKED));
	$userSpec = as_db_users_with_flag_selectspec(AS_USER_FLAGS_USER_BLOCKED, $start, $pagesize);

	list($numUsers, $users) = as_db_select_with_pending($userSpecCount, $userSpec);
	$count = $numUsers['count'];

	$usershtml = as_userids_handles_html($users);

	$categoryslugs = as_request_parts(1);
	$countslugs = count($categoryslugs);

	$userid = as_get_logged_in_userid();
	
	$total = count($users);
	$data = array();
	
	if (AS_FINAL_EXTERNAL_USERS) {
		die('User accounts are handled by external code');
	} else {
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
				'_order_' 		=> $user['_order_'],
				)
			);	
		}
		$output = json_encode(array('total' => $total, 'data' => $data));
	}
	
	echo $output;