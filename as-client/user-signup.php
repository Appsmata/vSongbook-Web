<?php
	require_once '../as-include/as-base.php';
	require_once '../as-include/db/users.php';
	require_once '../as-include/db/selects.php';

	require_once '../as-include/app/format.php';
	require_once '../as-include/app/users.php';
	require_once '../as-include/app/users-edit.php';
	
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		$data = json_decode(file_get_contents("php://input"));
		if(isset($data -> user ) && !empty($data -> user) && isset($data -> user -> firstname) && isset($data -> user -> lastname) && isset($data -> user -> country) && isset($data -> user -> mobile) && isset($data -> user -> gender) && isset($data -> user -> city) && isset($data -> user -> church)) {

			$user = $data -> user;
			$infirstname = $user -> firstname;
			$inlastname = $user -> lastname;
			$incountry = $user -> country;
			$inmobile = $user -> mobile;
			$ingender = $user -> gender;
			$incity = $user -> city;
			$inchurch = $user -> church;
			
			$inhandle = $infirstname.$inlastname;
			$inemail = strtolower($infirstname.$inlastname).'@vsongbook.com';

			// core validation
			$errors = array_merge(
				as_mobile_handle_filter($inmobile, $inhandle),
			);

			if (empty($errors)) {
				$incityid = as_db_city_find_by_title($incity, $incountry);
				$inchurchid = as_db_church_find_by_title($inchurch, $incityid);
								
				$userid = as_create_new_user($infirstname, $inlastname, $incountry, $inmobile, $ingender, $incityid, $inchurchid, $inhandle, $inemail, null);
				$userinfo = as_db_select_with_pending(as_db_user_account_selectspec($userid, true));
				
				as_set_logged_in_user($userid, $userinfo['handle']);

				$result['userid'] = $userid; 
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
				$response["message"] = "Signup successful";
				$response["user"] = $result;
				echo json_encode($response);
			} else {
				$response["result"] = "already";
				$response["message"] = "User already registered!";
				echo json_encode($response);
			}
		} else {
			$response["result"] = "failure";
			$response["message"] = "Parameters should not be empty!";
			echo json_encode($response);
		}
	}
