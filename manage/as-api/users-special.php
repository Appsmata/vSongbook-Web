<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';

	$users = as_db_select_with_pending(as_db_users_from_level_selectspec(AS_USER_LEVEL_EXPERT));

	$total = count($users);
	$data = array();

	foreach( $users as $user ){
		array_push($data, array(
			'userid' 		=> $user['userid'],
			'handle' 		=> $user['handle'],
			'level' 		=> $user['level'],
			'_order_' 		=> $user['_order_'],
			)
		);	
	}
	
	$output = json_encode(array('total' => $total, 'data' => $data));
	
	echo $output;