<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';

	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';
	
	$inmobile = as_post_text('mobile');
	
	$data = array();
	if (strlen($inmobile)) {
		require_once AS_INCLUDE_DIR . 'app/limits.php';

		if (as_user_limits_remaining(AS_LIMIT_LOGINS)) {
			as_limits_increment(null, AS_LIMIT_LOGINS);

			$errors = array();

			$matchusers = as_db_user_find_by_mobile($inmobile);

			if (count($matchusers) == 1) { // if matches more than one (should be impossible), don't log in
				$inuserid = $matchusers[0];
				$userinfo = as_db_select_with_pending(as_db_user_account_selectspec($inuserid, true));

				as_set_logged_in_user($inuserid, $userinfo['handle'], !empty($inremember));
				$data['success'] = 1;
				$data['message'] = 'Logged in successfully';
				$data['userid'] = $inuserid; 
				$data['firstname'] = $userinfo['firstname'];
				$data['lastname'] = $userinfo['lastname'];
				$data['country'] = $userinfo['country'];
				$data['mobile'] = $userinfo['mobile'];
				$data['gender'] = $userinfo['gender'];
				$data['city'] = $userinfo['cityname'];
				$data['church'] = $userinfo['churchname'];
				$data['email'] = $userinfo['email'];
				$data['level'] = $userinfo['level'];
				$data['handle'] = $userinfo['handle'];
				$data['created'] = $userinfo['created'];
				$data['signedin'] = $userinfo['signedin'];
				$data['avatarblobid'] = $userinfo['avatarblobid'];
				$data['points'] = $userinfo['points'];
				$data['wallposts'] = $userinfo['wallposts'];

			} else {
				$data['success'] = 2;
				$data['message'] = as_lang('users/user_not_found');
			}
		} else {
			$data['success'] = 3;
			$data['message'] = as_lang('users/signin_limit');
		}

	} else {
		$data['success'] = 4;
		$data['message'] = 'You need to enter a valid mobile number';
	}
	
	$output = json_encode(array('data' => $data));	
	echo $output;