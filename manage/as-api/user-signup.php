<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';

	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';
	
	$infirstname = as_post_text('firstname');
	$inlastname = as_post_text('lastname');
	$incountry = as_post_text('country');
	$inmobile = as_post_text('mobile');
	$ingender = as_post_text('gender');
	$incity = as_post_text('city');
	$inchurch = as_post_text('church');
	
	$data = array();
	
	if (as_opt('suspend_signup_users')) {
		$data['success'] = 0;
		$data['message'] = as_lang_html('users/signup_suspended');
	}

	else if (as_user_permit_error()) {
		$data['success'] = 0;
		$data['message'] = as_lang_html('users/no_permission');
	}

	else if (empty($infirstname)) {
		$data['success'] = 3;
		$data['message'] = 'Your first name appears invalid';
	}
	
	else if (empty($inlastname)) {
		$data['success'] = 3;
		$data['message'] = 'Your last name appears invalid';
	}
	
	else if (empty($incountry)) {
		$data['success'] = 3;
		$data['message'] = 'Your last name appears invalid';
	}
	
	else if (empty($inmobile)) {
		$data['success'] = 3;
		$data['message'] = 'Your mobile number appears invalid';
	}
	
	else if (empty($incity)) {
		$data['success'] = 3;
		$data['message'] = 'Your city name appears invalid';
	}
	
	else if (empty($inchurch)) {
		$data['success'] = 3;
		$data['message'] = 'Your church name appears invalid';
	}
	
	else {
		require_once AS_INCLUDE_DIR . 'app/limits.php';

		if (as_user_limits_remaining(AS_LIMIT_REGISTRATIONS)) {
			require_once AS_INCLUDE_DIR . 'app/users-edit.php';
			$inhandle = $infirstname.$inlastname;
			$inemail = strtolower($infirstname.$inlastname).'@vsongbook.com';
			// core validation
			$errors = array_merge(
				as_mobile_handle_filter($inmobile, $inhandle)
			);

			$inprofile = array();
			
			if (count($inprofile)) {
				$filtermodules = as_load_modules_with('filter', 'filter_profile');
				foreach ($filtermodules as $filtermodule)
					$filtermodule->filter_profile($inprofile, $errors, null, null);
			}

			if (empty($errors)) {
				as_limits_increment(null, AS_LIMIT_REGISTRATIONS);
				$incityid = as_db_city_find_by_title($incity, $incountry);
				$inchurchid = as_db_church_find_by_title($inchurch, $incityid);
								
				$userid = as_create_new_user($infirstname, $inlastname, $incountry, $inmobile, $ingender, $incityid, $inchurchid, $inhandle, $inemail, null);
				$userinfo = as_db_select_with_pending(as_db_user_account_selectspec($userid, true));
				
				as_set_logged_in_user($userid, $userinfo['handle']);
				
				$data['success'] = 1;
				$data['message'] = 'Registered and Logged in successfully';
				$data['userid'] = $userid;
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
			}

		} else {
			$data['success'] = 2;
			$data['message'] = as_lang('users/signup_limit');
		}
	} 
	
	$output = json_encode(array('data' => $data));	
	echo $output;