<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	
	$inuserid = as_post_text('userid');
	
	if (strlen($inuserid)) {
		as_db_user_written($inuserid, as_remote_ip_address());
	}