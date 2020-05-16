<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/users.php';

	$userid = as_get_logged_in_userid();
	$categories = as_db_select_with_pending(as_db_category_enabled());
	
	$result = array();

	foreach( $categories as $category ){
		array_push($result, array(
			'categoryid' 	=> $category['categoryid'],
			'title' 		=> $category['title'],
			'tags' 			=> $category['tags'],
			'qcount' 		=> $category['qcount'],
			'content' 		=> $category['content'],
			'backpath' 		=> $category['backpath'])
		);	
	}
	
	$response["result"] = "success";
	$response["message"] = "Request successful";
	$response["list"] = $result;
	echo json_encode($result);