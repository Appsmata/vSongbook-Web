<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';

	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';
	
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$response = json_decode(file_get_contents("php://input"));
		if(isset($response -> user ) && !empty($response -> user) && isset($response -> user -> mobile)){

			$user = $response -> user;
			$mobile = $user -> mobile;

			$matchusers = as_db_user_find_by_mobile($mobile);

			if (count($matchusers) == 1) { // if matches more than one (should be impossible), don't log in
				$inuserid = $matchusers[0];
				$userinfo = as_db_select_with_pending(as_db_user_account_selectspec($inuserid, true));

				as_set_logged_in_user($inuserid, $userinfo['handle'], !empty($inremember));
				
				$result['userid'] = $inuserid; 
				$result['firstname'] = $userinfo['firstname'];
				$result['lastname'] = $userinfo['lastname'];
				$result['country'] = $userinfo['country'];
				$result['mobile'] = $userinfo['mobile'];
				$result['gender'] = $userinfo['gender'];
				$result['city'] = $userinfo['cityname'];
				$result['church'] = $userinfo['churchname'];
				$result['email'] = $userinfo['email'];
				$result['level'] = $userinfo['level'];
				$result['handle'] = $userinfo['handle'];
				$result['created'] = $userinfo['created'];
				$result['signedin'] = $userinfo['signedin'];
				$result['avatarblobid'] = $userinfo['avatarblobid'];
				$result['points'] = $userinfo['points'];
				$result['wallposts'] = $userinfo['wallposts'];

				$response["result"] = "success";
				$response["message"] = "Signin successful";
				$response["user"] = $result;
				echo json_encode($response);
			} else {
				$response["result"] = "missing";
				$response["message"] = "User not found!";
				echo json_encode($response);
			}
		} else {
			$response["result"] = "failure";
			$response["message"] = "Parameters should not be empty!";
			echo json_encode($response);
		}
	}

	$response["status"] = 1;
	$response["message"] = "Request successful";
	$response["results"] = $result;

	echo json_encode($response);