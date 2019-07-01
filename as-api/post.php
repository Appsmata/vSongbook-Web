<?php

	require_once '../as-include/as-base.php';
	require_once '../as-include/app/format.php';
	require_once '../as-include/app/limits.php';
	require_once '../as-include/app/cookies.php';
	require_once '../as-include/app/post-create.php';
	require_once '../as-include/db/selects.php';
	
	require_once '../as-include/util/sort.php';
	require_once '../as-include/util/image.php';
	require_once '../as-include/util/string.php';

	require_once '../as-include/db/users.php';
	require_once '../as-include/app/users.php';
	
	$inuserid = as_post_text('userid');
	$inhandle = as_post_text('handle');
	$inemail = as_post_text('email');
	$intitle = as_post_text('title');
	$incategoryid = as_post_text('categoryid');
	$incontent = as_post_text('content');
	$intags = as_get_tags_field_value('tags');
	
	$followanswer = null;
	$innotify = null;
		
	$data = array();
	$permiterror = as_user_maximum_permit_error('permit_post_q', AS_LIMIT_QUESTIONS);
	
	if ($permiterror) {
		$data['success'] = 0;
		switch ($permiterror) {
			case 'signin':
				$data['message'] = as_lang_html('question/write_must_signin');
				break;

			case 'confirm':
				$data['message'] = as_lang_html('question/write_must_confirm');
				break;

			case 'limit':
				$data['message'] = as_lang_html('question/write_limit');
				break;

			case 'approve':
				$data['message'] = as_lang_html('question/write_must_be_approved');
				break;

			default:
				$data['message'] =  as_lang_html('users/no_permission');
				break;
		}
	}

	if (empty($intitle)) {
		$data['success'] = 3;
		$data['message'] = 'The title of the question appears to be invalid';
	}
	
	else if (empty($incategoryid)) {
		$data['success'] = 3;
		$data['message'] = 'The category of the question appears to be invalid';
	}
	
	else if (empty($incontent)) {
		$data['success'] = 3;
		$data['message'] = 'The content of the question appears to be invalid';
	}
	
	else if (empty($intags)) {
		$data['success'] = 3;
		$data['message'] = 'The tags of the question appears to be invalid';
	}
	
	else {
		$errors = array();

		$posticon = '';
		if (empty($errors)) {
			$cookieid = isset($userid) ? as_cookie_get() : as_cookie_get_create();
			$format = 'html';
			$text = as_remove_utf8mb4(as_viewer_text($incontent, $format));
			
			$questionid = as_question_create($followanswer, $inuserid, $inhandle, $cookieid, $intitle, $incontent, $format, $text, isset($intags) ? as_tags_to_tagstring($intags) : '', $innotify, $inemail, $incategoryid, null, null, null);
			
			$data['success'] = 1;
			$data['message'] = 'Asked in successfully';
		}
	} 
	
	$output = json_encode(array('data' => $data));	
	echo $output;