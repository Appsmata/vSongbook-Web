<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/users.php';

	$userid = as_get_logged_in_userid();

	$categories = as_db_select_with_pending(as_db_category_enabled());
	
	$total = count($categories);
	$data = array();

	foreach( $categories as $category ){
		array_push($data, array(
			'categoryid' 	=> $category['categoryid'],
			'title' 		=> $category['title'],
			'tags' 			=> $category['tags'],
			'qcount' 		=> $category['qcount'],
			'content' 		=> $category['content'],
			'backpath' 		=> $category['backpath'])
		);	
	}
	
	$output = json_encode(array('total' => $total, 'data' => $data));
	
	echo $output;