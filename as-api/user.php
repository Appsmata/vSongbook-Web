<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';

	$handle = isset( $_GET['handle'] ) ? $_GET['handle'] : "";
	$handle = isset( $_GET['handle'] ) ? $_GET['handle'] : "";
	
	/*if (isset($handle)) {
		$userid = as_handle_to_userid($handle);
	} else {
		die('handle is required.');
	}*/
	$userid = as_handle_to_userid($handle);
	//echo $output;