<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/users.php';

	$categoryslugs = as_request_parts(1);
	$countslugs = count($categoryslugs);

	$userid = as_get_logged_in_userid();

	$categories = as_db_select_with_pending(as_db_category_nav_selectspec($categoryslugs, false, false, true));
	
	$total = count($categories);
	$data = array();

	foreach( $categories as $category ){
		array_push($data, array(
			'categoryid' 	=> $category['categoryid'],
			'enabled' 		=> $category['enabled'],
			'title' 		=> $category['title'],
			'tags' 			=> $category['tags'],
			'qcount' 		=> $category['qcount'],
			'content' 		=> $category['content'],
			'backpath' 		=> $category['backpath'])
		);	
	}
	
	$output = json_encode(array('total' => $total, 'data' => $data));
	
	echo $output;