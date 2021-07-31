<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for signup page


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this online
*/

if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

require_once AS_INCLUDE_DIR . 'app/captcha.php';
require_once AS_INCLUDE_DIR . 'db/users.php';


if (as_is_logged_in()) {
	as_redirect('');
}

// Check we're not using single-sign on integration, that we're not logged in, and we're not blocked
if (AS_FINAL_EXTERNAL_USERS) {
	$request = as_request();
	$topath = as_get('to'); // lets user switch between signin and signup without losing destination page
	$userlinks = as_get_signin_links(as_path_to_root(), isset($topath) ? $topath : as_path($request, $_GET, ''));

	if (!empty($userlinks['signup'])) {
		as_redirect_raw($userlinks['signup']);
	}
	as_fatal_error('User registration should be handled by external code');
}


// Get information about possible additional fields

$show_terms = as_opt('show_signup_terms');

$userfields = as_db_select_with_pending(
	as_db_userfields_selectspec()
);

foreach ($userfields as $index => $userfield) {
	if (!($userfield['flags'] & AS_FIELD_FLAGS_ON_REGISTER))
		unset($userfields[$index]);
}

$genderoptions = array("1" => "Brother", "2" => "Sister");

$countrylist = array(
	"AF" => "Afghanistan (93)",
	"AL" => "Albania (355)",
	"DZ" => "Algeria (213)",
	"AS" => "American Samoa (1-684)",
	"AD" => "Andorra (376)",
	"AO" => "Angola (244)",
	"AI" => "Anguilla (1-264)",
	"AQ" => "Antarctica (672)",
	"AG" => "Antigua and Barbuda (1-268)",
	"AR" => "Argentina (54)",
	"AM" => "Armenia (374)",
	"AW" => "Aruba (297)",
	"AU" => "Australia (61)",
	"AT" => "Austria (43)",
	"AZ" => "Azerbaijan (994)",
	"BS" => "Bahamas (1-242)",
	"BH" => "Bahrain (973)",
	"BD" => "Bangladesh (880)",
	"BB" => "Barbados (1-246)",
	"BY" => "Belarus (375)",
	"BE" => "Belgium (32)",
	"BZ" => "Belize (501)",
	"BJ" => "Benin (229)",
	"BM" => "Bermuda (1-441)",
	"BT" => "Bhutan (975)",
	"BO" => "Bolivia (591)",
	"BA" => "Bosnia and Herzegovina (387)",
	"BW" => "Botswana (267)",
	"BR" => "Brazil (55)",
	"IO" => "British Indian Ocean Territory (246)",
	"VG" => "British Virgin Islands (1-284)",
	"BN" => "Brunei (673)",
	"BG" => "Bulgaria (359)",
	"BF" => "Burkina Faso (226)",
	"BI" => "Burundi (257)",
	"KH" => "Cambodia (855)",
	"CM" => "Cameroon (237)",
	"CA" => "Canada (1)",
	"CV" => "Cape Verde (238)",
	"KY" => "Cayman Islands (1-345)",
	"CF" => "Central African Republic (236)",
	"TD" => "Chad (235)",
	"CL" => "Chile (56)",
	"CN" => "China (86)",
	"CX" => "Christmas Island (61)",
	"CC" => "Cocos Islands (61)",
	"CO" => "Colombia (57)",
	"KM" => "Comoros (269)",
	"CK" => "Cook Islands (682)",
	"CR" => "Costa Rica (506)",
	"HR" => "Croatia (385)",
	"CU" => "Cuba (53)",
	"CW" => "Curacao (599)",
	"CY" => "Cyprus (357)",
	"CZ" => "Czech Republic (420)",
	"CD" => "Democratic Republic of the Congo (243)",
	"DK" => "Denmark (45)",
	"DJ" => "Djibouti (253)",
	"DM" => "Dominica (1-767)",
	"DO" => "Dominican Republic (1-809, 1-829, 1-849)",
	"TL" => "East Timor (670)",
	"EC" => "Ecuador (593)",
	"EG" => "Egypt (20)",
	"SV" => "El Salvador (503)",
	"GQ" => "Equatorial Guinea (240)",
	"ER" => "Eritrea (291)",
	"EE" => "Estonia (372)",
	"ET" => "Ethiopia (251)",
	"FK" => "Falkland Islands (500)",
	"FO" => "Faroe Islands (298)",
	"FJ" => "Fiji (679)",
	"FI" => "Finland (358)",
	"FR" => "France (33)",
	"PF" => "French Polynesia (689)",
	"GA" => "Gabon (241)",
	"GM" => "Gambia (220)",
	"GE" => "Georgia (995)",
	"DE" => "Germany (49)",
	"GH" => "Ghana (233)",
	"GI" => "Gibraltar (350)",
	"GR" => "Greece (30)",
	"GL" => "Greenland (299)",
	"GD" => "Grenada (1-473)",
	"GU" => "Guam (1-671)",
	"GT" => "Guatemala (502)",
	"GG" => "Guernsey (44-1481)",
	"GN" => "Guinea (224)",
	"GW" => "Guinea-Bissau (245)",
	"GY" => "Guyana (592)",
	"HT" => "Haiti (509)",
	"HN" => "Honduras (504)",
	"HK" => "Hong Kong (852)",
	"HU" => "Hungary (36)",
	"IS" => "Iceland (354)",
	"IN" => "India (91)",
	"ID" => "Indonesia (62)",
	"IR" => "Iran (98)",
	"IQ" => "Iraq (964)",
	"IE" => "Ireland (353)",
	"IM" => "Isle of Man (44-1624)",
	"IL" => "Israel (972)",
	"IT" => "Italy (39)",
	"CI" => "Ivory Coast (225)",
	"JM" => "Jamaica (1-876)",
	"JP" => "Japan (81)",
	"JE" => "Jersey (44-1534)",
	"JO" => "Jordan (962)",
	"KZ" => "Kazakhstan (7)",
	"KE" => "Kenya (254)",
	"KI" => "Kiribati (686)",
	"XK" => "Kosovo (383)",
	"KW" => "Kuwait (965)",
	"KG" => "Kyrgyzstan (996)",
	"LA" => "Laos (856)",
	"LV" => "Latvia (371)",
	"LB" => "Lebanon (961)",
	"LS" => "Lesotho (266)",
	"LR" => "Liberia (231)",
	"LY" => "Libya (218)",
	"LI" => "Liechtenstein (423)",
	"LT" => "Lithuania (370)",
	"LU" => "Luxembourg (352)",
	"MO" => "Macau (853)",
	"MK" => "Macedonia (389)",
	"MG" => "Madagascar (261)",
	"MW" => "Malawi (265)",
	"MY" => "Malaysia (60)",
	"MV" => "Maldives (960)",
	"ML" => "Mali (223)",
	"MT" => "Malta (356)",
	"MH" => "Marshall Islands (692)",
	"MR" => "Mauritania (222)",
	"MU" => "Mauritius (230)",
	"YT" => "Mayotte (262)",
	"MX" => "Mexico (52)",
	"FM" => "Micronesia (691)",
	"MD" => "Moldova (373)",
	"MC" => "Monaco (377)",
	"MN" => "Mongolia (976)",
	"ME" => "Montenegro (382)",
	"MS" => "Montserrat (1-664)",
	"MA" => "Morocco (212)",
	"MZ" => "Mozambique (258)",
	"MM" => "Myanmar (95)",
	"NA" => "Namibia (264)",
	"NR" => "Nauru (674)",
	"NP" => "Nepal (977)",
	"NL" => "Netherlands (31)",
	"AN" => "Netherlands Antilles (599)",
	"NC" => "New Caledonia (687)",
	"NZ" => "New Zealand (64)",
	"NI" => "Nicaragua (505)",
	"NE" => "Niger (227)",
	"NG" => "Nigeria (234)",
	"NU" => "Niue (683)",
	"KP" => "North Korea (850)",
	"MP" => "Northern Mariana Islands (1-670)",
	"NO" => "Norway (47)",
	"OM" => "Oman (968)",
	"PK" => "Pakistan (92)",
	"PW" => "Palau (680)",
	"PS" => "Palestine (970)",
	"PA" => "Panama (507)",
	"PG" => "Papua New Guinea (675)",
	"PY" => "Paraguay (595)",
	"PE" => "Peru (51)",
	"PH" => "Philippines (63)",
	"PN" => "Pitcairn (64)",
	"PL" => "Poland (48)",
	"PT" => "Portugal (351)",
	"PR" => "Puerto Rico (1-787, 1-939)",
	"QA" => "Qatar (974)",
	"CG" => "Republic of the Congo (242)",
	"RE" => "Reunion (262)",
	"RO" => "Romania (40)",
	"RU" => "Russia (7)",
	"RW" => "Rwanda (250)",
	"BL" => "Saint Barthelemy (590)",
	"SH" => "Saint Helena (290)",
	"KN" => "Saint Kitts and Nevis (1-869)",
	"LC" => "Saint Lucia (1-758)",
	"MF" => "Saint Martin (590)",
	"PM" => "Saint Pierre and Miquelon (508)",
	"VC" => "Saint Vincent and the Grenadines (1-784)",
	"WS" => "Samoa (685)",
	"SM" => "San Marino (378)",
	"ST" => "Sao Tome and Principe (239)",
	"SA" => "Saudi Arabia (966)",
	"SN" => "Senegal (221)",
	"RS" => "Serbia (381)",
	"SC" => "Seychelles (248)",
	"SL" => "Sierra Leone (232)",
	"SG" => "Singapore (65)",
	"SX" => "Sint Maarten (1-721)",
	"SK" => "Slovakia (421)",
	"SI" => "Slovenia (386)",
	"SB" => "Solomon Islands (677)",
	"SO" => "Somalia (252)",
	"ZA" => "South Africa (27)",
	"KR" => "South Korea (82)",
	"SS" => "South Sudan (211)",
	"ES" => "Spain (34)",
	"LK" => "Sri Lanka (94)",
	"SD" => "Sudan (249)",
	"SR" => "Suriname (597)",
	"SJ" => "Svalbard and Jan Mayen (47)",
	"SZ" => "Swaziland (268)",
	"SE" => "Sweden (46)",
	"CH" => "Switzerland (41)",
	"SY" => "Syria (963)",
	"TW" => "Taiwan (886)",
	"TJ" => "Tajikistan (992)",
	"TZ" => "Tanzania (255)",
	"TH" => "Thailand (66)",
	"TG" => "Togo (228)",
	"TK" => "Tokelau (690)",
	"TO" => "Tonga (676)",
	"TT" => "Trinidad and Tobago (1-868)",
	"TN" => "Tunisia (216)",
	"TR" => "Turkey (90)",
	"TM" => "Turkmenistan (993)",
	"TC" => "Turks and Caicos Islands (1-649)",
	"TV" => "Tuvalu (688)",
	"VI" => "U.S. Virgin Islands (1-340)",
	"UG" => "Uganda (256)",
	"UA" => "Ukraine (380)",
	"AE" => "United Arab Emirates (971)",
	"GB" => "United Kingdom (44)",
	"US" => "United States (1)",
	"UY" => "Uruguay (598)",
	"UZ" => "Uzbekistan (998)",
	"VU" => "Vanuatu (678)",
	"VA" => "Vatican (379)",
	"VE" => "Venezuela (58)",
	"VN" => "Vietnam (84)",
	"WF" => "Wallis and Futuna (681)",
	"EH" => "Western Sahara (212)",
	"YE" => "Yemen (967)",
	"ZM" => "Zambia (260)",
	"ZW" => "Zimbabwe (263)",
);

// Check we haven't suspended registration, and this IP isn't blocked

if (as_opt('suspend_signup_users')) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/signup_suspended');
	return $as_content;
}

if (as_user_permit_error()) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}


// Process submitted form

if (as_clicked('dosignup')) {
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	if (as_user_limits_remaining(AS_LIMIT_REGISTRATIONS)) {
		require_once AS_INCLUDE_DIR . 'app/users-edit.php';

		$infirstname = as_post_text('firstname');
		$inlastname = as_post_text('lastname');
		$inremember = as_post_text('remember');
		$ingender = as_post_text('gender');
		$incountry = as_post_text('country');
		$inmobile = as_post_text('mobile');
		$inchurch = as_post_text('church');
		$inemail = as_post_text('email');
		$inhandle = as_post_text('handle');
		$inpassword = as_post_text('password');
		$interms = (int)as_post_text('terms');

		$inprofile = array();
		foreach ($userfields as $userfield)
			$inprofile[$userfield['fieldid']] = as_post_text('field_' . $userfield['fieldid']);

		if (!as_check_form_security_code('signup', as_post_text('code'))) {
			$pageerror = as_lang_html('misc/form_security_again');
		} else {
			// core validation
			$errors = array_merge(
				as_handle_email_filter($inhandle, $inemail),
				as_password_validate($inpassword)
			);

			// T&Cs validation
			if ($show_terms && !$interms)
				$errors['terms'] = as_lang_html('users/terms_not_accepted');

			// filter module validation
			if (count($inprofile)) {
				$filtermodules = as_load_modules_with('filter', 'filter_profile');
				foreach ($filtermodules as $filtermodule)
					$filtermodule->filter_profile($inprofile, $errors, null, null);
			}

			if (as_opt('captcha_on_signup'))
				as_captcha_validate_post($errors);

			if (empty($errors)) {
				// signup and redirect
				as_limits_increment(null, AS_LIMIT_REGISTRATIONS);

				$userid = as_create_new_user($infirstname, $inlastname, $incountry, $inmobile, $ingender, $incity, $inchurch, $inhandle, $inemail, $inpassword);

				foreach ($userfields as $userfield)
					as_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);

				as_set_logged_in_user($userid, $inhandle);

				$topath = as_get('to');

				if (isset($topath))
					as_redirect_raw(as_path_to_root() . $topath); // path already provided as URL fragment
				else
					as_redirect('');
			}
		}

	} else
		$pageerror = as_lang('users/signup_limit');
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('users/signup_title');

$as_content['error'] = @$pageerror;

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '"',

	'style' => 'tall',

	'fields' => array(
		'firstname' => array(
			'label' => as_lang_html('users/firstname_label'),
			'tags' => 'name="firstname" id="firstname" dir="auto"',
			'value' => as_html(@$infirstname),
			'error' => as_html(@$errors['firstname']),
		),

		'lastname' => array(
			'label' => as_lang_html('users/lastname_label'),
			'tags' => 'name="lastname" id="lastname" dir="auto"',
			'value' => as_html(@$inlastname),
			'error' => as_html(@$errors['lastname']),
		),

		'gender' => array(
			'type' => 'radio',
			'label' => 'You are a:',
			'options' => $genderoptions,
			'tags' => 'name="gender" id="gender" dir="auto"',
			'value' => $genderoptions["1"],
			'error' => as_html(@$errors['gender']),
		),

		'country' => array(
			'type' => 'select',
			'label' => 'Country:',
			'options' => $countrylist,
			'tags' => 'name="country" id="country" dir="auto"',
			'value' => $countrylist["KE"],
			'error' => as_html(@$errors['country']),
		),

		'mobile' => array(
			'type' => 'phone',
			'label' => as_lang_html('users/mobile_label'),
			'tags' => 'name="mobile" id="mobile" dir="auto"',
			'value' => as_html(@$inmobile),
			'error' => as_html(@$errors['mobile']),
		),

		'church' => array(
			'label' => as_lang_html('users/church_label'),
			'tags' => 'name="church" id="church" dir="auto"',
			'value' => as_html(@$inchurch),
			'error' => as_html(@$errors['church']),
		),

		'city' => array(
			'label' => as_lang_html('users/city_label'),
			'tags' => 'name="city" id="city" dir="auto"',
			'value' => as_html(@$incity),
			'error' => as_html(@$errors['city']),
		),

		'email' => array(
			'type' => 'email',
			'label' => as_lang_html('users/email_label'),
			'tags' => 'name="email" id="email" dir="auto"',
			'value' => as_html(@$inemail),
			'note' => as_opt('email_privacy'),
			'error' => as_html(@$errors['email']),
		),
		
		'handle' => array(
			'label' => as_lang_html('users/handle_label'),
			'tags' => 'name="handle" id="handle" dir="auto"',
			'value' => as_html(@$inhandle),
			'error' => as_html(@$errors['handle']),
		),

		'password' => array(
			'type' => 'password',
			'label' => as_lang_html('users/password_label'),
			'tags' => 'name="password" id="password" dir="auto"',
			'value' => as_html(@$inpassword),
			'error' => as_html(@$errors['password']),
		),
	),

	'buttons' => array(
		'signup' => array(
			'tags' => 'onclick="as_show_waiting_after(this, false);"',
			'label' => as_lang_html('users/signup_button'),
		),
	),

	'hidden' => array(
		'dosignup' => '1',
		'code' => as_get_form_security_code('signup'),
	),

	'links' => array(		
		'signin' => array(
			'url' => 'signin',
			'label' => as_lang_html('users/signin_title'),
		),
	)
);

// prepend custom message
$custom = as_opt('show_custom_signup') ? trim(as_opt('custom_signup')) : '';
if (strlen($custom)) {
	array_unshift($as_content['form']['fields'], array(
		'type' => 'custom',
		'note' => $custom,
	));
}

foreach ($userfields as $userfield) {
	$value = @$inprofile[$userfield['fieldid']];

	$label = trim(as_user_userfield_label($userfield), ':');
	if (strlen($label))
		$label .= ':';

	$as_content['form']['fields'][$userfield['title']] = array(
		'label' => as_html($label),
		'tags' => 'name="field_' . $userfield['fieldid'] . '"',
		'value' => as_html($value),
		'error' => as_html(@$errors[$userfield['fieldid']]),
		'rows' => ($userfield['flags'] & AS_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
	);
}

if (as_opt('captcha_on_signup'))
	as_set_up_captcha_field($as_content, $as_content['form']['fields'], @$errors);

// show T&Cs checkbox
if ($show_terms) {
	$as_content['form']['fields']['terms'] = array(
		'type' => 'checkbox',
		'label' => trim(as_opt('signup_terms')),
		'tags' => 'name="terms" id="terms"',
		'value' => as_html(@$interms),
		'error' => as_html(@$errors['terms']),
	);
}

$signinmodules = as_load_modules_with('signin', 'signin_html');

foreach ($signinmodules as $module) {
	ob_start();
	$module->signin_html(as_opt('site_url') . as_get('to'), 'signup');
	$html = ob_get_clean();

	if (strlen($html))
		@$as_content['custom'] .= '<br>' . $html . '<br>';
}

// prioritize 'handle' for keyboard focus
$as_content['focusid'] = 'firstname';


return $as_content;
