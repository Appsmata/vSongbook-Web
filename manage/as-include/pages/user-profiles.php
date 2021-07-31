<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for user profile page, including wall


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

require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'db/users.php';
require_once AS_INCLUDE_DIR . 'app/format.php';
require_once AS_INCLUDE_DIR . 'app/limits.php';
require_once AS_INCLUDE_DIR . 'app/updates.php';


// $handle, $userhtml are already set by /as-include/page/user.php - also $userid if using external user integration


// Redirect to 'My Account' page if button clicked

if (as_clicked('doaccount'))
	as_redirect('account');


// Find the user profile and songs and reviews for this handle


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


// Check the user exists and work out what can and can't be set (if not using single sign-on)

$errors = array();

$signinlevel = as_get_logged_in_level();

if (!AS_FINAL_EXTERNAL_USERS) { // if we're using integrated user management, we can know and show more
	require_once AS_INCLUDE_DIR . 'app/messages.php';

	if (!is_array($userpoints) && !is_array($useraccount))
		return include AS_INCLUDE_DIR . 'as-page-not-found.php';

	$userid = $useraccount['userid'];
	$fieldseditable = false;
	$maxlevelassign = null;

	$maxuserlevel = $useraccount['level'];
	foreach ($userlevels as $userlevel)
		$maxuserlevel = max($maxuserlevel, $userlevel['level']);

	if (isset($signinuserid) && $signinuserid != $userid &&
		($signinlevel >= AS_USER_LEVEL_SUPER || $signinlevel > $maxuserlevel) &&
		!as_user_permit_error()
	) { // can't change self - or someone on your level (or higher, obviously) unless you're a super admin
		if ($signinlevel >= AS_USER_LEVEL_SUPER)
			$maxlevelassign = AS_USER_LEVEL_SUPER;
		elseif ($signinlevel >= AS_USER_LEVEL_ADMIN)
			$maxlevelassign = AS_USER_LEVEL_MODERATOR;
		elseif ($signinlevel >= AS_USER_LEVEL_MODERATOR)
			$maxlevelassign = AS_USER_LEVEL_EXPERT;

		if ($signinlevel >= AS_USER_LEVEL_ADMIN)
			$fieldseditable = true;

		if (isset($maxlevelassign) && ($useraccount['flags'] & AS_USER_FLAGS_USER_BLOCKED))
			$maxlevelassign = min($maxlevelassign, AS_USER_LEVEL_EDITOR); // if blocked, can't promote too high
	}

	$approvebutton = isset($maxlevelassign)
		&& $useraccount['level'] < AS_USER_LEVEL_APPROVED
		&& $maxlevelassign >= AS_USER_LEVEL_APPROVED
		&& !($useraccount['flags'] & AS_USER_FLAGS_USER_BLOCKED)
		&& as_opt('moderate_users');
	$usereditbutton = $fieldseditable || isset($maxlevelassign);
	$userediting = $usereditbutton && (as_get_state() == 'edit');

	$wallposterrorhtml = as_wall_error_html($signinuserid, $useraccount['userid'], $useraccount['flags']);

	$fullname = $useraccount['fullname'] = as_db_name_find_by_handle($handle);
	// This code is similar but not identical to that in to qq-page-user-wall.php

	$usermessages = array_slice($usermessages, 0, as_opt('page_size_wall'));
	$usermessages = as_wall_posts_add_rules($usermessages, 0);

	foreach ($usermessages as $message) {
		if ($message['deleteable'] && as_clicked('m' . $message['messageid'] . '_dodelete')) {
			if (!as_check_form_security_code('wall-' . $useraccount['handle'], as_post_text('code')))
				$errors['page'] = as_lang_html('misc/form_security_again');
			else {
				as_wall_delete_post($signinuserid, as_get_logged_in_handle(), as_cookie_get(), $message);
				as_redirect(as_request(), null, null, null, 'wall');
			}
		}
	}
}

// Process edit or save button for user, and other actions

if (!AS_FINAL_EXTERNAL_USERS) {
	$reloaduser = false;

	if ($usereditbutton) {
		if (as_clicked('docancel')) {
			as_redirect(as_request());
		} elseif (as_clicked('doedit')) {
			as_redirect(as_request(), array('state' => 'edit'));
		} elseif (as_clicked('dosave')) {
			require_once AS_INCLUDE_DIR . 'app/users-edit.php';
			require_once AS_INCLUDE_DIR . 'db/users.php';

			$inemail = as_post_text('email');

			$inprofile = array();
			foreach ($userfields as $userfield)
				$inprofile[$userfield['fieldid']] = as_post_text('field_' . $userfield['fieldid']);

			if (!as_check_form_security_code('user-edit-' . $handle, as_post_text('code'))) {
				$errors['page'] = as_lang_html('misc/form_security_again');
				$userediting = true;
			} else {
				if (as_post_text('removeavatar')) {
					as_db_user_set_flag($userid, AS_USER_FLAGS_SHOW_AVATAR, false);
					as_db_user_set_flag($userid, AS_USER_FLAGS_SHOW_GRAVATAR, false);

					if (isset($useraccount['avatarblobid'])) {
						require_once AS_INCLUDE_DIR . 'app/blobs.php';

						as_db_user_set($userid, array(
							'avatarblobid' => null,
							'avatarwidth' => null,
							'avatarheight' => null,
						));

						as_delete_blob($useraccount['avatarblobid']);
					}
				}

				if ($fieldseditable) {
					$filterhandle = $handle; // we're not filtering the handle...
					$errors = as_handle_email_filter($filterhandle, $inemail, $useraccount);
					unset($errors['handle']); // ...and we don't care about any errors in it

					if (!isset($errors['email'])) {
						if ($inemail != $useraccount['email']) {
							as_db_user_set($userid, 'email', $inemail);
							as_db_user_set_flag($userid, AS_USER_FLAGS_EMAIL_CONFIRMED, false);
						}
					}

					if (count($inprofile)) {
						$filtermodules = as_load_modules_with('filter', 'filter_profile');
						foreach ($filtermodules as $filtermodule)
							$filtermodule->filter_profile($inprofile, $errors, $useraccount, $userprofile);
					}

					foreach ($userfields as $userfield) {
						if (!isset($errors[$userfield['fieldid']]))
							as_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);
					}

					if (count($errors))
						$userediting = true;

					as_report_event('u_edit', $signinuserid, as_get_logged_in_handle(), as_cookie_get(), array(
						'userid' => $userid,
						'handle' => $useraccount['handle'],
					));
				}

				if (isset($maxlevelassign)) {
					$inlevel = min($maxlevelassign, (int)as_post_text('level')); // constrain based on maximum permitted to prevent simple browser-based attack
					if ($inlevel != $useraccount['level'])
						as_set_user_level($userid, $useraccount['handle'], $inlevel, $useraccount['level']);

					if (as_using_categories()) {
						$inuserlevels = array();

						for ($index = 1; $index <= 999; $index++) {
							$inlevel = as_post_text('uc_' . $index . '_level');
							if (!isset($inlevel))
								break;

							$categoryid = as_get_category_field_value('uc_' . $index . '_cat');

							if (strlen($categoryid) && strlen($inlevel)) {
								$inuserlevels[] = array(
									'entitytype' => AS_ENTITY_CATEGORY,
									'entityid' => $categoryid,
									'level' => min($maxlevelassign, (int)$inlevel),
								);
							}
						}

						as_db_user_levels_set($userid, $inuserlevels);
					}
				}

				if (empty($errors))
					as_redirect(as_request());

				list($useraccount, $userprofile, $userlevels) = as_db_select_with_pending(
					as_db_user_account_selectspec($userid, true),
					as_db_user_profile_selectspec($userid, true),
					as_db_user_levels_selectspec($userid, true, true)
				);
			}
		}
	}

	if (as_clicked('doapprove') || as_clicked('doblock') || as_clicked('dounblock') || as_clicked('dohideall') || as_clicked('dodelete')) {
		if (!as_check_form_security_code('user-' . $handle, as_post_text('code')))
			$errors['page'] = as_lang_html('misc/form_security_again');

		else {
			if ($approvebutton && as_clicked('doapprove')) {
				require_once AS_INCLUDE_DIR . 'app/users-edit.php';
				as_set_user_level($userid, $useraccount['handle'], AS_USER_LEVEL_APPROVED, $useraccount['level']);
				as_redirect(as_request());
			}

			if (isset($maxlevelassign) && ($maxuserlevel < AS_USER_LEVEL_MODERATOR)) {
				if (as_clicked('doblock')) {
					require_once AS_INCLUDE_DIR . 'app/users-edit.php';

					as_set_user_blocked($userid, $useraccount['handle'], true);
					as_redirect(as_request());
				}

				if (as_clicked('dounblock')) {
					require_once AS_INCLUDE_DIR . 'app/users-edit.php';

					as_set_user_blocked($userid, $useraccount['handle'], false);
					as_redirect(as_request());
				}

				if (as_clicked('dohideall') && !as_user_permit_error('permit_hide_show')) {
					require_once AS_INCLUDE_DIR . 'db/admin.php';
					require_once AS_INCLUDE_DIR . 'app/posts.php';

					$postids = as_db_get_user_visible_postids($userid);

					foreach ($postids as $postid)
						as_post_set_status($postid, AS_POST_STATUS_HIDDEN, $signinuserid);

					as_redirect(as_request());
				}

				if (as_clicked('dodelete') && ($signinlevel >= AS_USER_LEVEL_ADMIN)) {
					require_once AS_INCLUDE_DIR . 'app/users-edit.php';

					as_delete_user($userid);

					as_report_event('u_delete', $signinuserid, as_get_logged_in_handle(), as_cookie_get(), array(
						'userid' => $userid,
						'handle' => $useraccount['handle'],
					));

					as_redirect('users');
				}
			}
		}
	}


	if (as_clicked('dowallpost')) {
		$inmessage = as_post_text('message');

		if (!strlen($inmessage)) {
			$errors['message'] = as_lang('profile/post_wall_empty');
		} elseif (!as_check_form_security_code('wall-' . $useraccount['handle'], as_post_text('code'))) {
			$errors['message'] = as_lang_html('misc/form_security_again');
		} elseif (!$wallposterrorhtml) {
			as_wall_add_post($signinuserid, as_get_logged_in_handle(), as_cookie_get(), $userid, $useraccount['handle'], $inmessage, '');
			as_redirect(as_request(), null, null, null, 'wall');
		}
	}
}


// Process bonus setting button

if ($signinlevel >= AS_USER_LEVEL_ADMIN && as_clicked('dosetbonus')) {
	require_once AS_INCLUDE_DIR . 'db/points.php';

	$inbonus = (int)as_post_text('bonus');

	if (!as_check_form_security_code('user-activity-' . $handle, as_post_text('code'))) {
		$errors['page'] = as_lang_html('misc/form_security_again');
	} else {
		as_db_points_set_bonus($userid, $inbonus);
		as_db_points_update_ifuser($userid, null);
		as_redirect(as_request(), null, null, null, 'activity');
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = $fullname;
$as_content['error'] = @$errors['page'];

if (isset($signinuserid) && $signinuserid != $useraccount['userid'] && !AS_FINAL_EXTERNAL_USERS) {
	$favoritemap = as_get_favorite_non_qs_map();
	$favorite = @$favoritemap['user'][$useraccount['userid']];

	$as_content['favorite'] = as_favorite_form(AS_ENTITY_USER, $useraccount['userid'], $favorite,
		as_lang_sub($favorite ? 'main/remove_x_favorites' : 'users/add_user_x_favorites', $handle));
}


// General information about the user, only available if we're using internal user management

if (!AS_FINAL_EXTERNAL_USERS) {
	$membertime = as_time_to_string(as_opt('db_time') - $useraccount['created']);
	$joindate = as_when_to_html($useraccount['created'], 0);

	$as_content['xform_profile'] = array(
		'tags' => 'method="post" action="' . as_self_html() . '"',

		'style' => 'wide',

		'fields' => array(
			'avatar' => array(
				'type' => 'image',
				'style' => 'tall',
				'label' => '',
				'html' => as_get_user_avatar_html($useraccount['flags'], $useraccount['email'], $useraccount['handle'],
					$useraccount['avatarblobid'], $useraccount['avatarwidth'], $useraccount['avatarheight'], as_opt('avatar_profile_size')),
				'id' => 'avatar',
			),

			'removeavatar' => null,

			'duration' => array(
				'type' => 'static',
				'label' => as_lang_html('users/member_for'),
				'value' => as_html($membertime . ' (' . as_lang_sub('main/since_x', $joindate['data']) . ')'),
				'id' => 'duration',
			),

			'level' => array(
				'type' => 'static',
				'label' => as_lang_html('users/member_type'),
				'tags' => 'name="level"',
				'value' => as_html(as_user_level_string($useraccount['level'])),
				'note' => (($useraccount['flags'] & AS_USER_FLAGS_USER_BLOCKED) && isset($maxlevelassign)) ? as_lang_html('users/user_blocked') : '',
				'id' => 'level',
			),
		),
	);

	if (empty($as_content['form_profile']['fields']['avatar']['html']))
		unset($as_content['form_profile']['fields']['avatar']);


	// Private message link

	if (as_opt('allow_private_messages') && isset($signinuserid) && $signinuserid != $userid && !($useraccount['flags'] & AS_USER_FLAGS_NO_MESSAGES) && !$userediting) {
		$as_content['form_profile']['fields']['level']['value'] .= strtr(as_lang_html('profile/send_private_message'), array(
			'^1' => '<a href="' . as_path_html('message/' . $handle) . '">',
			'^2' => '</a>',
		));
	}


	// Levels editing or viewing (add category-specific levels)

	if ($userediting) {
		if (isset($maxlevelassign)) {
			$as_content['form_profile']['fields']['level']['type'] = 'select';

			$showlevels = array(AS_USER_LEVEL_BASIC);
			if (as_opt('moderate_users'))
				$showlevels[] = AS_USER_LEVEL_APPROVED;

			array_push($showlevels, AS_USER_LEVEL_EXPERT, AS_USER_LEVEL_EDITOR, AS_USER_LEVEL_MODERATOR, AS_USER_LEVEL_ADMIN, AS_USER_LEVEL_SUPER);

			$leveloptions = array();
			$catleveloptions = array('' => as_lang_html('users/category_level_none'));

			foreach ($showlevels as $showlevel) {
				if ($showlevel <= $maxlevelassign) {
					$leveloptions[$showlevel] = as_html(as_user_level_string($showlevel));
					if ($showlevel > AS_USER_LEVEL_BASIC)
						$catleveloptions[$showlevel] = $leveloptions[$showlevel];
				}
			}

			$as_content['form_profile']['fields']['level']['options'] = $leveloptions;


			// Category-specific levels

			if (as_using_categories()) {
				$catleveladd = strlen(as_get('catleveladd')) > 0;

				if (!$catleveladd && !count($userlevels)) {
					$as_content['form_profile']['fields']['level']['suffix'] = strtr(as_lang_html('users/category_level_add'), array(
						'^1' => '<a href="' . as_path_html(as_request(), array('state' => 'edit', 'catleveladd' => 1)) . '">',
						'^2' => '</a>',
					));
				} else {
					$as_content['form_profile']['fields']['level']['suffix'] = as_lang_html('users/level_in_general');
				}

				if ($catleveladd || count($userlevels))
					$userlevels[] = array('entitytype' => AS_ENTITY_CATEGORY);

				$index = 0;
				foreach ($userlevels as $userlevel) {
					if ($userlevel['entitytype'] == AS_ENTITY_CATEGORY) {
						$index++;
						$id = 'ls_' . +$index;

						$as_content['form_profile']['fields']['uc_' . $index . '_level'] = array(
							'label' => as_lang_html('users/category_level_label'),
							'type' => 'select',
							'tags' => 'name="uc_' . $index . '_level" id="' . as_html($id) . '" onchange="this.as_prev=this.options[this.selectedIndex].value;"',
							'options' => $catleveloptions,
							'value' => isset($userlevel['level']) ? as_html(as_user_level_string($userlevel['level'])) : '',
							'suffix' => as_lang_html('users/category_level_in'),
						);

						$as_content['form_profile']['fields']['uc_' . $index . '_cat'] = array();

						if (isset($userlevel['entityid']))
							$fieldnavcategories = as_db_select_with_pending(as_db_category_nav_selectspec($userlevel['entityid'], true));
						else
							$fieldnavcategories = $navcategories;

						as_set_up_category_field($as_content, $as_content['form_profile']['fields']['uc_' . $index . '_cat'],
							'uc_' . $index . '_cat', $fieldnavcategories, @$userlevel['entityid'], true, true);

						unset($as_content['form_profile']['fields']['uc_' . $index . '_cat']['note']);
					}
				}

				$as_content['script_lines'][] = array(
					"function as_update_category_levels()",
					"{",
					"\tglob=document.getElementById('level_select');",
					"\tif (!glob)",
					"\t\treturn;",
					"\tvar opts=glob.options;",
					"\tvar lev=parseInt(opts[glob.selectedIndex].value);",
					"\tfor (var i=1; i<9999; i++) {",
					"\t\tvar sel=document.getElementById('ls_'+i);",
					"\t\tif (!sel)",
					"\t\t\tbreak;",
					"\t\tsel.as_prev=sel.as_prev || sel.options[sel.selectedIndex].value;",
					"\t\tsel.options.length=1;", // just leaves "no upgrade" element
					"\t\tfor (var j=0; j<opts.length; j++)",
					"\t\t\tif (parseInt(opts[j].value)>lev)",
					"\t\t\t\tsel.options[sel.options.length]=new Option(opts[j].text, opts[j].value, false, (opts[j].value==sel.as_prev));",
					"\t}",
					"}",
				);

				$as_content['script_onloads'][] = array(
					"as_update_category_levels();",
				);

				$as_content['form_profile']['fields']['level']['tags'] .= ' id="level_select" onchange="as_update_category_levels();"';
			}
		}

	} else {
		foreach ($userlevels as $userlevel) {
			if ($userlevel['entitytype'] == AS_ENTITY_CATEGORY && $userlevel['level'] > $useraccount['level']) {
				$as_content['form_profile']['fields']['level']['value'] .= '<br/>' .
					strtr(as_lang_html('users/level_for_category'), array(
						'^1' => as_html(as_user_level_string($userlevel['level'])),
						'^2' => '<a href="' . as_path_html(implode('/', array_reverse(explode('/', $userlevel['backpath'])))) . '">' . as_html($userlevel['title']) . '</a>',
					));
			}
		}
	}


	// Show any extra privileges due to user's level or their points

	$showpermits = array();
	$permitoptions = as_get_permit_options();

	foreach ($permitoptions as $permitoption) {
		// if not available to approved and email confirmed users with no points, but yes available to the user, it's something special
		if (as_permit_error($permitoption, $userid, AS_USER_LEVEL_APPROVED, AS_USER_FLAGS_EMAIL_CONFIRMED, 0) &&
			!as_permit_error($permitoption, $userid, $useraccount['level'], $useraccount['flags'], $userpoints['points'])
		) {
			if ($permitoption == 'permit_retag_cat')
				$showpermits[] = as_lang(as_using_categories() ? 'profile/permit_recat' : 'profile/permit_retag');
			else
				$showpermits[] = as_lang('profile/' . $permitoption); // then show it as an extra priviliege
		}
	}

	if (count($showpermits)) {
		$as_content['form_profile']['fields']['permits'] = array(
			'type' => 'static',
			'label' => as_lang_html('profile/extra_privileges'),
			'value' => as_html(implode("\n", $showpermits), true),
			'rows' => count($showpermits),
			'id' => 'permits',
		);
	}


	// Show email address only if we're an administrator

	if ($signinlevel >= AS_USER_LEVEL_ADMIN && !as_user_permit_error()) {
		$doconfirms = as_opt('confirm_user_emails') && $useraccount['level'] < AS_USER_LEVEL_EXPERT;
		$isconfirmed = ($useraccount['flags'] & AS_USER_FLAGS_EMAIL_CONFIRMED) > 0;
		$htmlemail = as_html(isset($inemail) ? $inemail : $useraccount['email']);

		$as_content['form_profile']['fields']['email'] = array(
			'type' => $userediting ? 'text' : 'static',
			'label' => as_lang_html('users/email_label'),
			'tags' => 'name="email"',
			'value' => $userediting ? $htmlemail : ('<a href="mailto:' . $htmlemail . '">' . $htmlemail . '</a>'),
			'error' => as_html(@$errors['email']),
			'note' => ($doconfirms ? (as_lang_html($isconfirmed ? 'users/email_confirmed' : 'users/email_not_confirmed') . ' ') : '') .
				($userediting ? '' : as_lang_html('users/only_shown_admins')),
			'id' => 'email',
		);
	}


	// Show IP addresses and times for last signin or write - only if we're a moderator or higher

	if ($signinlevel >= AS_USER_LEVEL_MODERATOR && !as_user_permit_error()) {
		$as_content['form_profile']['fields']['lastsignin'] = array(
			'type' => 'static',
			'label' => as_lang_html('users/last_signin_label'),
			'value' =>
				strtr(as_lang_html('users/x_ago_from_y'), array(
					'^1' => as_time_to_string(as_opt('db_time') - $useraccount['signedin']),
					'^2' => as_ip_anchor_html(@inet_ntop($useraccount['signinip'])),
				)),
			'note' => $userediting ? null : as_lang_html('users/only_shown_moderators'),
			'id' => 'lastsignin',
		);

		if (isset($useraccount['written'])) {
			$as_content['form_profile']['fields']['lastwrite'] = array(
				'type' => 'static',
				'label' => as_lang_html('users/last_write_label'),
				'value' =>
					strtr(as_lang_html('users/x_ago_from_y'), array(
						'^1' => as_time_to_string(as_opt('db_time') - $useraccount['written']),
						'^2' => as_ip_anchor_html(@inet_ntop($useraccount['writeip'])),
					)),
				'note' => $userediting ? null : as_lang_html('users/only_shown_moderators'),
				'id' => 'lastwrite',
			);
		} else {
			unset($as_content['form_profile']['fields']['lastwrite']);
		}
	}


	// Show other profile fields

	$fieldsediting = $fieldseditable && $userediting;

	foreach ($userfields as $userfield) {
		if (($userfield['flags'] & AS_FIELD_FLAGS_LINK_URL) && !$fieldsediting) {
			$valuehtml = as_url_to_html_link(@$userprofile[$userfield['title']], as_opt('links_in_new_window'));
		} else {
			$value = @$inprofile[$userfield['fieldid']];
			if (!isset($value))
				$value = @$userprofile[$userfield['title']];

			$valuehtml = as_html($value, (($userfield['flags'] & AS_FIELD_FLAGS_MULTI_LINE) && !$fieldsediting));
		}

		$label = trim(as_user_userfield_label($userfield), ':');
		if (strlen($label))
			$label .= ':';

		$notehtml = null;
		if (isset($userfield['permit']) && !$userediting) {
			if ($userfield['permit'] <= AS_PERMIT_ADMINS)
				$notehtml = as_lang_html('users/only_shown_admins');
			elseif ($userfield['permit'] <= AS_PERMIT_MODERATORS)
				$notehtml = as_lang_html('users/only_shown_moderators');
			elseif ($userfield['permit'] <= AS_PERMIT_EDITORS)
				$notehtml = as_lang_html('users/only_shown_editors');
			elseif ($userfield['permit'] <= AS_PERMIT_EXPERTS)
				$notehtml = as_lang_html('users/only_shown_experts');
		}

		$as_content['form_profile']['fields'][$userfield['title']] = array(
			'type' => $fieldsediting ? 'text' : 'static',
			'label' => as_html($label),
			'tags' => 'name="field_' . $userfield['fieldid'] . '"',
			'value' => $valuehtml,
			'error' => as_html(@$errors[$userfield['fieldid']]),
			'note' => $notehtml,
			'rows' => ($userfield['flags'] & AS_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
			'id' => 'userfield-' . $userfield['fieldid'],
		);
	}


	// Edit form or button, if appropriate

	if ($userediting) {
		if ((as_opt('avatar_allow_gravatar') && ($useraccount['flags'] & AS_USER_FLAGS_SHOW_GRAVATAR)) ||
			(as_opt('avatar_allow_upload') && ($useraccount['flags'] & AS_USER_FLAGS_SHOW_AVATAR) && isset($useraccount['avatarblobid']))
		) {
			$as_content['form_profile']['fields']['removeavatar'] = array(
				'type' => 'checkbox',
				'label' => as_lang_html('users/remove_avatar'),
				'tags' => 'name="removeavatar"',
			);
		}

		$as_content['form_profile']['buttons'] = array(
			'save' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false);"',
				'label' => as_lang_html('users/save_user'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => as_lang_html('main/cancel_button'),
			),
		);

		$as_content['form_profile']['hidden'] = array(
			'dosave' => '1',
			'code' => as_get_form_security_code('user-edit-' . $handle),
		);

	} elseif ($usereditbutton) {
		$as_content['form_profile']['buttons'] = array();

		if ($approvebutton) {
			$as_content['form_profile']['buttons']['approve'] = array(
				'tags' => 'name="doapprove"',
				'label' => as_lang_html('users/approve_user_button'),
			);
		}

		$as_content['form_profile']['buttons']['edit'] = array(
			'tags' => 'name="doedit"',
			'label' => as_lang_html('users/edit_user_button'),
		);

		if (isset($maxlevelassign) && $useraccount['level'] < AS_USER_LEVEL_MODERATOR) {
			if ($useraccount['flags'] & AS_USER_FLAGS_USER_BLOCKED) {
				$as_content['form_profile']['buttons']['unblock'] = array(
					'tags' => 'name="dounblock"',
					'label' => as_lang_html('users/unblock_user_button'),
				);

				if (!as_user_permit_error('permit_hide_show')) {
					$as_content['form_profile']['buttons']['hideall'] = array(
						'tags' => 'name="dohideall" onclick="as_show_waiting_after(this, false);"',
						'label' => as_lang_html('users/hide_all_user_button'),
					);
				}

				if ($signinlevel >= AS_USER_LEVEL_ADMIN) {
					$as_content['form_profile']['buttons']['delete'] = array(
						'tags' => 'name="dodelete" onclick="as_show_waiting_after(this, false);"',
						'label' => as_lang_html('users/delete_user_button'),
					);
				}

			} else {
				$as_content['form_profile']['buttons']['block'] = array(
					'tags' => 'name="doblock"',
					'label' => as_lang_html('users/block_user_button'),
				);
			}

			$as_content['form_profile']['hidden'] = array(
				'code' => as_get_form_security_code('user-' . $handle),
			);
		}

	} elseif (isset($signinuserid) && ($signinuserid == $userid)) {
		$as_content['form_profile']['buttons'] = array(
			'account' => array(
				'tags' => 'name="doaccount"',
				'label' => as_lang_html('users/edit_profile'),
			),
		);
	}


	if (!is_array($as_content['form_profile']['fields']['removeavatar']))
		unset($as_content['form_profile']['fields']['removeavatar']);

	$as_content['raw']['account'] = $useraccount; // for plugin layers to access
	$as_content['raw']['profile'] = $userprofile;
}


// Information about user activity, available also with single sign-on integration

$as_content['form_activity'] = array(
	'title' => '<span id="activity">' . as_lang_html_sub('profile/activity_by_x', $userhtml) . '</span>',

	'style' => 'wide',

	'fields' => array(
		'bonus' => array(
			'label' => as_lang_html('profile/bonus_points'),
			'tags' => 'name="bonus"',
			'value' => as_html(isset($inbonus) ? $inbonus : $userpoints['bonus']),
			'type' => 'number',
			'note' => as_lang_html('users/only_shown_admins'),
			'id' => 'bonus',
		),

		'points' => array(
			'type' => 'static',
			'label' => as_lang_html('profile/score'),
			'value' => (@$userpoints['points'] == 1)
				? as_lang_html_sub('main/1_point', '<span class="as-uf-user-points">1</span>', '1')
				: as_lang_html_sub('main/x_points', '<span class="as-uf-user-points">' . as_html(as_format_number(@$userpoints['points'])) . '</span>'),
			'id' => 'points',
		),

		'title' => array(
			'type' => 'static',
			'label' => as_lang_html('profile/title'),
			'value' => as_get_points_title_html(@$userpoints['points'], as_get_points_to_titles()),
			'id' => 'title',
		),

		'songs' => array(
			'type' => 'static',
			'label' => as_lang_html('profile/songs'),
			'value' => '<span class="as-uf-user-q-posts">' . as_html(as_format_number(@$userpoints['qposts'])) . '</span>',
			'id' => 'songs',
		),

		'reviews' => array(
			'type' => 'static',
			'label' => as_lang_html('profile/reviews'),
			'value' => '<span class="as-uf-user-a-posts">' . as_html(as_format_number(@$userpoints['aposts'])) . '</span>',
			'id' => 'reviews',
		),
	),
);

if ($signinlevel >= AS_USER_LEVEL_ADMIN) {
	$as_content['form_activity']['tags'] = 'method="post" action="' . as_self_html() . '"';

	$as_content['form_activity']['buttons'] = array(
		'setbonus' => array(
			'tags' => 'name="dosetbonus"',
			'label' => as_lang_html('profile/set_bonus_button'),
		),
	);

	$as_content['form_activity']['hidden'] = array(
		'code' => as_get_form_security_code('user-activity-' . $handle),
	);

} else {
	unset($as_content['form_activity']['fields']['bonus']);
}

if (!isset($as_content['form_activity']['fields']['title']['value']))
	unset($as_content['form_activity']['fields']['title']);

if (as_opt('comment_on_qs') || as_opt('comment_on_as')) { // only show comment count if comments are enabled
	$as_content['form_activity']['fields']['comments'] = array(
		'type' => 'static',
		'label' => as_lang_html('profile/comments'),
		'value' => '<span class="as-uf-user-c-posts">' . as_html(as_format_number(@$userpoints['cposts'])) . '</span>',
		'id' => 'comments',
	);
}

if (as_opt('thumbing_on_qs') || as_opt('thumbing_on_as')) { // only show thumb record if thumbing is enabled
	$thumbdonvalue = '';

	if (as_opt('thumbing_on_qs')) {
		$qthumbs = @$userpoints['qthumbsup'] + @$userpoints['qthumbsdown'];

		$innervalue = '<span class="as-uf-user-q-thumbs">' . as_format_number($qthumbs) . '</span>';
		$thumbdonvalue .= ($qthumbs == 1) ? as_lang_html_sub('main/1_song', $innervalue, '1')
			: as_lang_html_sub('main/x_songs', $innervalue);

		if (as_opt('thumbing_on_as'))
			$thumbdonvalue .= ', ';
	}

	if (as_opt('thumbing_on_as')) {
		$athumbs = @$userpoints['athumbsup'] + @$userpoints['athumbsdown'];

		$innervalue = '<span class="as-uf-user-a-thumbs">' . as_format_number($athumbs) . '</span>';
		$thumbdonvalue .= ($athumbs == 1) ? as_lang_html_sub('main/1_review', $innervalue, '1')
			: as_lang_html_sub('main/x_reviews', $innervalue);
	}

	$as_content['form_activity']['fields']['thumbdon'] = array(
		'type' => 'static',
		'label' => as_lang_html('profile/thumbd_on'),
		'value' => $thumbdonvalue,
		'id' => 'thumbdon',
	);

	$thumbsup = @$userpoints['qthumbsup'] + @$userpoints['athumbsup'];
	$innervalue = '<span class="as-uf-user-thumbsup">' . as_format_number($thumbsup) . '</span>';
	$thumbgavevalue = (($thumbsup == 1) ? as_lang_html_sub('profile/1_up_thumb', $innervalue, '1') : as_lang_html_sub('profile/x_up_thumbs', $innervalue)) . ', ';

	$thumbsdown = @$userpoints['qthumbsdown'] + @$userpoints['athumbsdown'];
	$innervalue = '<span class="as-uf-user-thumbsdown">' . as_format_number($thumbsdown) . '</span>';
	$thumbgavevalue .= ($thumbsdown == 1) ? as_lang_html_sub('profile/1_down_thumb', $innervalue, '1') : as_lang_html_sub('profile/x_down_thumbs', $innervalue);

	$as_content['form_activity']['fields']['thumbgave'] = array(
		'type' => 'static',
		'label' => as_lang_html('profile/gave_out'),
		'value' => $thumbgavevalue,
		'id' => 'thumbgave',
	);

	$innervalue = '<span class="as-uf-user-upthumbds">' . as_format_number(@$userpoints['upthumbds']) . '</span>';
	$thumbgotvalue = ((@$userpoints['upthumbds'] == 1) ? as_lang_html_sub('profile/1_up_thumb', $innervalue, '1')
			: as_lang_html_sub('profile/x_up_thumbs', $innervalue)) . ', ';

	$innervalue = '<span class="as-uf-user-downthumbds">' . as_format_number(@$userpoints['downthumbds']) . '</span>';
	$thumbgotvalue .= (@$userpoints['downthumbds'] == 1) ? as_lang_html_sub('profile/1_down_thumb', $innervalue, '1')
		: as_lang_html_sub('profile/x_down_thumbs', $innervalue);

	$as_content['form_activity']['fields']['thumbgot'] = array(
		'type' => 'static',
		'label' => as_lang_html('profile/received'),
		'value' => $thumbgotvalue,
		'id' => 'thumbgot',
	);
}

if (@$userpoints['points']) {
	$as_content['form_activity']['fields']['points']['value'] .=
		as_lang_html_sub('profile/ranked_x', '<span class="as-uf-user-rank">' . as_format_number($userrank) . '</span>');
}

if (@$userpoints['aselects']) {
	$as_content['form_activity']['fields']['songs']['value'] .= ($userpoints['aselects'] == 1)
		? as_lang_html_sub('profile/1_with_best_chosen', '<span class="as-uf-user-q-selects">1</span>', '1')
		: as_lang_html_sub('profile/x_with_best_chosen', '<span class="as-uf-user-q-selects">' . as_format_number($userpoints['aselects']) . '</span>');
}

if (@$userpoints['aselecteds']) {
	$as_content['form_activity']['fields']['reviews']['value'] .= ($userpoints['aselecteds'] == 1)
		? as_lang_html_sub('profile/1_chosen_as_best', '<span class="as-uf-user-a-selecteds">1</span>', '1')
		: as_lang_html_sub('profile/x_chosen_as_best', '<span class="as-uf-user-a-selecteds">' . as_format_number($userpoints['aselecteds']) . '</span>');
}


// For plugin layers to access

$as_content['raw']['userid'] = $userid;
$as_content['raw']['points'] = $userpoints;
$as_content['raw']['rank'] = $userrank;


// Wall posts

if (!AS_FINAL_EXTERNAL_USERS && as_opt('allow_user_walls')) {
	$as_content['message_list'] = array(
		'title' => '<span id="wall">' . as_lang_html_sub('profile/wall_for_x', $userhtml) . '</span>',

		'tags' => 'id="wallmessages"',

		'form' => array(
			'tags' => 'name="wallpost" method="post" action="' . as_self_html() . '#wall"',
			'style' => 'tall',
			'hidden' => array(
				'as_click' => '', // for simulating clicks in Javascript
				'handle' => as_html($useraccount['handle']),
				'start' => 0,
				'code' => as_get_form_security_code('wall-' . $useraccount['handle']),
			),
		),

		'messages' => array(),
	);

	if ($wallposterrorhtml) {
		$as_content['message_list']['error'] = $wallposterrorhtml; // an error that means we are not allowed to post
	} else {
		$as_content['message_list']['form']['fields'] = array(
			'message' => array(
				'tags' => 'name="message" id="message"',
				'value' => as_html(@$inmessage, false),
				'rows' => 2,
				'error' => as_html(@$errors['message']),
			),
		);

		$as_content['message_list']['form']['buttons'] = array(
			'post' => array(
				'tags' => 'name="dowallpost" onclick="return as_submit_wall_post(this, true);"',
				'label' => as_lang_html('profile/post_wall_button'),
			),
		);
	}

	foreach ($usermessages as $message)
		$as_content['message_list']['messages'][] = as_wall_post_view($message);

	if ($useraccount['wallposts'] > count($usermessages))
		$as_content['message_list']['messages'][] = as_wall_view_more_link($handle, count($usermessages));
}


// Sub menu for navigation in user pages

$ismyuser = isset($signinuserid) && $signinuserid == (AS_FINAL_EXTERNAL_USERS ? $userid : $useraccount['userid']);
$as_content['navigation']['sub'] = as_user_sub_navigation($fullname, $handle, 'profile', $ismyuser);


return $as_content;
