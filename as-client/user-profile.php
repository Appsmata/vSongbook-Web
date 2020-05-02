<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';

	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';
	
	$handle = as_get('handle');
	
	if (isset($handle)) {
		$userid = as_handle_to_userid($handle);
		$signinuserid = as_get_logged_in_userid();
		$identifier = AS_FINAL_EXTERNAL_USERS ? $userid : $handle;

		list($useraccount, $userprofile, $userfields, $usermessages, $userpoints, $userlevels, $navcategories, $userrank) =
			as_db_select_with_pending(
				AS_FINAL_EXTERNAL_USERS ? null : as_db_user_account_selectspec($handle, false),
				AS_FINAL_EXTERNAL_USERS ? null : as_db_user_profile_selectspec($handle, false),
				AS_FINAL_EXTERNAL_USERS ? null : as_db_userfields_selectspec(),
				AS_FINAL_EXTERNAL_USERS ? null : as_db_recent_messages_selectspec(null, null, $handle, false, as_opt_if_loaded('page_size_wall')),
				as_db_user_points_selectspec($identifier),
				as_db_user_levels_selectspec($identifier, AS_FINAL_EXTERNAL_USERS, true),
				as_db_category_nav_selectspec(null, true),
				as_db_user_rank_selectspec($identifier)
			);

		if (!AS_FINAL_EXTERNAL_USERS && $handle !== as_get_logged_in_handle()) {
			foreach ($userfields as $index => $userfield) {
				if (isset($userfield['permit']) && as_permit_value_error($userfield['permit'], $signinuserid, as_get_logged_in_level(), as_get_logged_in_flags()))
					unset($userfields[$index]); // don't pay attention to user fields we're not allowed to view
			}
		}
		
		$data = array();
		
		array_push($data, array(
			'userid' 				=> $useraccount['userid'],
			'passsalt' 				=> $useraccount['passsalt'],
			'passcheck' 			=> $useraccount['passcheck'],
			'passhash' 				=> $useraccount['passhash'],
			'email' 				=> $useraccount['email'],
			'level' 				=> $useraccount['level'],
			'emailcode' 			=> $useraccount['emailcode'],
			'handle' 				=> $useraccount['handle'],
			'created' 				=> $useraccount['created'],
			'sessioncode' 			=> $useraccount['sessioncode'],
			'sessionsource' 		=> $useraccount['sessionsource'],
			'flags' 				=> $useraccount['flags'],
			'loggedin' 				=> $useraccount['loggedin'],
			'signinip' 				=> $useraccount['signinip'],
			'written' 				=> $useraccount['written'],
			'writeip' 				=> $useraccount['writeip'],
			'avatarblobid' 			=> $useraccount['avatarblobid'],
			'avatarwidth' 			=> $useraccount['avatarwidth'],
			'avatarheight' 			=> $useraccount['avatarheight'],
			'points' 				=> $useraccount['points'],
			'wallposts' 			=> $useraccount['wallposts'],
			)
		);	
		
		$output = json_encode(array('data' => $data));
	} else {
		die('handle is required.');
	}
	
	
	echo $output;