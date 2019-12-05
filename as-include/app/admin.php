<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Functions used in the admin center pages


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


/**
 * Return true if user is logged in with admin privileges. If not, return false
 * and set up $as_content with the appropriate title and error message
 * @param $as_content
 * @return bool
 */
function as_admin_check_privileges(&$as_content)
{
	if (!as_is_logged_in()) {
		require_once AS_INCLUDE_DIR . 'app/format.php';

		$as_content = as_content_prepare();

		$as_content['title'] = as_lang_html('admin/admin_title');
		$as_content['error'] = as_insert_signin_links(as_lang_html('admin/not_logged_in'), as_request());

		return false;

	} elseif (as_get_logged_in_level() < AS_USER_LEVEL_ADMIN) {
		$as_content = as_content_prepare();

		$as_content['title'] = as_lang_html('admin/admin_title');
		$as_content['error'] = as_lang_html('admin/no_privileges');

		return false;
	}

	return true;
}


/**
 *	Return a sorted array of available languages, [short code] => [long name]
 */
function as_admin_language_options()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	/**
	 * @deprecated The hardcoded language ids will be removed in favor of language metadata files.
	 * See as-lang/en-GB directory for a clear example of how to use them.
	 */
	$codetolanguage = array( // 2-letter language codes as per ISO 639-1
		'ar' => 'Arabic - العربية',
		'az' => 'Azerbaijani - Azərbaycanca',
		'bg' => 'Bulgarian - Български',
		'bn' => 'Bengali - বাংলা',
		'ca' => 'Catalan - Català',
		'cs' => 'Czech - Čeština',
		'cy' => 'Welsh - Cymraeg',
		'da' => 'Danish - Dansk',
		'de' => 'German - Deutsch',
		'el' => 'Greek - Ελληνικά',
		'en-GB' => 'English (UK)',
		'es' => 'Spanish - Español',
		'et' => 'Estonian - Eesti',
		'fa' => 'Persian - فارسی',
		'fi' => 'Finnish - Suomi',
		'fr' => 'French - Français',
		'he' => 'Hebrew - עברית',
		'hr' => 'Croatian - Hrvatski',
		'hu' => 'Hungarian - Magyar',
		'id' => 'Indonesian - Bahasa Indonesia',
		'is' => 'Icelandic - Íslenska',
		'it' => 'Italian - Italiano',
		'ja' => 'Japanese - 日本語',
		'ka' => 'Georgian - ქართული ენა',
		'kh' => 'Khmer - ភាសាខ្មែរ',
		'ko' => 'Korean - 한국어',
		'ku-CKB' => 'Kurdish Central - کورد',
		'lt' => 'Lithuanian - Lietuvių',
		'lv' => 'Latvian - Latviešu',
		'nl' => 'Dutch - Nederlands',
		'no' => 'Norwegian - Norsk',
		'pl' => 'Polish - Polski',
		'pt' => 'Portuguese - Português',
		'ro' => 'Romanian - Română',
		'ru' => 'Russian - Русский',
		'sk' => 'Slovak - Slovenčina',
		'sl' => 'Slovenian - Slovenščina',
		'sq' => 'Albanian - Shqip',
		'sr' => 'Serbian - Српски',
		'sv' => 'Swedish - Svenska',
		'th' => 'Thai - ไทย',
		'tr' => 'Turkish - Türkçe',
		'ug' => 'Uyghur - ئۇيغۇرچە',
		'uk' => 'Ukrainian - Українська',
		'uz' => 'Uzbek - ўзбек',
		'vi' => 'Vietnamese - Tiếng Việt',
		'zh-TW' => 'Chinese Traditional - 繁體中文',
		'zh' => 'Chinese Simplified - 简体中文',
	);

	$options = array('' => 'English (US)');

	// find all language folders
	$metadataUtil = new APS_Util_Metadata();
	foreach (glob(AS_LANG_DIR . '*', GLOB_ONLYDIR) as $directory) {
		$code = basename($directory);
		$metadata = $metadataUtil->fetchFromAddonPath($directory);
		if (isset($metadata['name'])) {
			$options[$code] = $metadata['name'];
		} elseif (isset($codetolanguage[$code])) {
			// otherwise use an entry from above
			$options[$code] = $codetolanguage[$code];
		}
	}

	asort($options, SORT_STRING);
	return $options;
}


/**
 * Return a sorted array of available themes, [theme name] => [theme name]
 */
function as_admin_theme_options()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$metadataUtil = new APS_Util_Metadata();
	foreach (glob(AS_THEME_DIR . '*', GLOB_ONLYDIR) as $directory) {
		$theme = basename($directory);
		$metadata = $metadataUtil->fetchFromAddonPath($directory);
		if (empty($metadata)) {
			// limit theme parsing to first 8kB
			$contents = @file_get_contents($directory . '/as-styles.css', false, null, 0, 8192);
			$metadata = as_addon_metadata($contents, 'Theme');
		}
		$options[$theme] = isset($metadata['name']) ? $metadata['name'] : $theme;
	}

	asort($options, SORT_STRING);
	return $options;
}


/**
 * Return an array of widget placement options, with keys matching the database value
 */
function as_admin_place_options()
{
	return array(
		'FT' => as_lang_html('options/place_full_top'),
		'FH' => as_lang_html('options/place_full_below_nav'),
		'FL' => as_lang_html('options/place_full_below_content'),
		'FB' => as_lang_html('options/place_full_below_footer'),
		'MT' => as_lang_html('options/place_main_top'),
		'MH' => as_lang_html('options/place_main_below_title'),
		'ML' => as_lang_html('options/place_main_below_lists'),
		'MB' => as_lang_html('options/place_main_bottom'),
		'ST' => as_lang_html('options/place_side_top'),
		'SH' => as_lang_html('options/place_side_below_sidebar'),
		'SL' => as_lang_html('options/place_side_low'),
		'SB' => as_lang_html('options/place_side_last'),
	);
}


/**
 * Return an array of page size options up to $maximum, [page size] => [page size]
 * @param $maximum
 * @return array
 */
function as_admin_page_size_options($maximum)
{
	$rawoptions = array(5, 10, 15, 20, 25, 30, 40, 50, 60, 80, 100, 120, 150, 200, 250, 300, 400, 500, 600, 800, 1000);

	$options = array();
	foreach ($rawoptions as $rawoption) {
		if ($rawoption > $maximum)
			break;

		$options[$rawoption] = $rawoption;
	}

	return $options;
}


/**
 * Return an array of options representing matching precision, [value] => [label]
 */
function as_admin_match_options()
{
	return array(
		5 => as_lang_html('options/match_5'),
		4 => as_lang_html('options/match_4'),
		3 => as_lang_html('options/match_3'),
		2 => as_lang_html('options/match_2'),
		1 => as_lang_html('options/match_1'),
	);
}


/**
 * Return an array of options representing permission restrictions, [value] => [label]
 * ranging from $widest to $narrowest. Set $doconfirms to whether email confirmations are on
 * @param $widest
 * @param $narrowest
 * @param bool $doconfirms
 * @param bool $dopoints
 * @return array
 */
function as_admin_permit_options($widest, $narrowest, $doconfirms = true, $dopoints = true)
{
	require_once AS_INCLUDE_DIR . 'app/options.php';

	$options = array(
		AS_PERMIT_ALL => as_lang_html('options/permit_all'),
		AS_PERMIT_USERS => as_lang_html('options/permit_users'),
		AS_PERMIT_CONFIRMED => as_lang_html('options/permit_confirmed'),
		AS_PERMIT_POINTS => as_lang_html('options/permit_points'),
		AS_PERMIT_POINTS_CONFIRMED => as_lang_html('options/permit_points_confirmed'),
		AS_PERMIT_APPROVED => as_lang_html('options/permit_approved'),
		AS_PERMIT_APPROVED_POINTS => as_lang_html('options/permit_approved_points'),
		AS_PERMIT_EXPERTS => as_lang_html('options/permit_experts'),
		AS_PERMIT_EDITORS => as_lang_html('options/permit_editors'),
		AS_PERMIT_MODERATORS => as_lang_html('options/permit_moderators'),
		AS_PERMIT_ADMINS => as_lang_html('options/permit_admins'),
		AS_PERMIT_SUPERS => as_lang_html('options/permit_supers'),
	);

	foreach ($options as $key => $label) {
		if ($key < $narrowest || $key > $widest)
			unset($options[$key]);
	}

	if (!$doconfirms) {
		unset($options[AS_PERMIT_CONFIRMED]);
		unset($options[AS_PERMIT_POINTS_CONFIRMED]);
	}

	if (!$dopoints) {
		unset($options[AS_PERMIT_POINTS]);
		unset($options[AS_PERMIT_POINTS_CONFIRMED]);
		unset($options[AS_PERMIT_APPROVED_POINTS]);
	}

	if (AS_FINAL_EXTERNAL_USERS || !as_opt('moderate_users')) {
		unset($options[AS_PERMIT_APPROVED]);
		unset($options[AS_PERMIT_APPROVED_POINTS]);
	}

	return $options;
}


/**
 * Return the sub navigation structure common to admin pages
 */
function as_admin_sub_navigation()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$navigation = array();
	$level = as_get_logged_in_level();

	if ($level >= AS_USER_LEVEL_ADMIN) {
		$navigation['admin/general'] = array(
			'label' => as_lang_html('admin/general_title'),
			'url' => as_path_html('admin/general'),
		);

		$navigation['admin/emails'] = array(
			'label' => as_lang_html('admin/emails_title'),
			'url' => as_path_html('admin/emails'),
		);

		$navigation['admin/users'] = array(
			'label' => as_lang_html('admin/users_title'),
			'url' => as_path_html('admin/users'),
			'selected_on' => array('admin/users$', 'admin/userfields$', 'admin/usertitles$'),
		);

		$navigation['admin/layout'] = array(
			'label' => as_lang_html('admin/layout_title'),
			'url' => as_path_html('admin/layout'),
		);

		$navigation['admin/posting'] = array(
			'label' => as_lang_html('admin/posting_title'),
			'url' => as_path_html('admin/posting'),
		);

		$navigation['admin/viewing'] = array(
			'label' => as_lang_html('admin/viewing_title'),
			'url' => as_path_html('admin/viewing'),
		);

		$navigation['admin/lists'] = array(
			'label' => as_lang_html('admin/lists_title'),
			'url' => as_path_html('admin/lists'),
		);

		if (as_using_categories())
			$navigation['admin/categories'] = array(
				'label' => as_lang_html('admin/categories_title'),
				'url' => as_path_html('admin/categories'),
			);

		$navigation['admin/permissions'] = array(
			'label' => as_lang_html('admin/permissions_title'),
			'url' => as_path_html('admin/permissions'),
		);

		$navigation['admin/pages'] = array(
			'label' => as_lang_html('admin/pages_title'),
			'url' => as_path_html('admin/pages'),
		);

		$navigation['admin/feeds'] = array(
			'label' => as_lang_html('admin/feeds_title'),
			'url' => as_path_html('admin/feeds'),
		);

		$navigation['admin/points'] = array(
			'label' => as_lang_html('admin/points_title'),
			'url' => as_path_html('admin/points'),
		);

		$navigation['admin/spam'] = array(
			'label' => as_lang_html('admin/spam_title'),
			'url' => as_path_html('admin/spam'),
		);

		$navigation['admin/caching'] = array(
			'label' => as_lang_html('admin/caching_title'),
			'url' => as_path_html('admin/caching'),
		);

		$navigation['admin/stats'] = array(
			'label' => as_lang_html('admin/stats_title'),
			'url' => as_path_html('admin/stats'),
		);

		if (!AS_FINAL_EXTERNAL_USERS)
			$navigation['admin/mailing'] = array(
				'label' => as_lang_html('admin/mailing_title'),
				'url' => as_path_html('admin/mailing'),
			);

		$navigation['admin/plugins'] = array(
			'label' => as_lang_html('admin/plugins_title'),
			'url' => as_path_html('admin/plugins'),
		);
	}

	if (!as_user_maximum_permit_error('permit_moderate')) {
		$count = as_user_permit_error('permit_moderate') ? null : as_opt('cache_queuedcount'); // if only in some categories don't show cached count

		$navigation['admin/moderate'] = array(
			'label' => as_lang_html('admin/moderate_title') . ($count ? (' (' . $count . ')') : ''),
			'url' => as_path_html('admin/moderate'),
		);
	}

	if (as_opt('flagging_of_posts') && !as_user_maximum_permit_error('permit_hide_show')) {
		$count = as_user_permit_error('permit_hide_show') ? null : as_opt('cache_flaggedcount'); // if only in some categories don't show cached count

		$navigation['admin/flagged'] = array(
			'label' => as_lang_html('admin/flagged_title') . ($count ? (' (' . $count . ')') : ''),
			'url' => as_path_html('admin/flagged'),
		);
	}

	if (!as_user_maximum_permit_error('permit_hide_show') || !as_user_maximum_permit_error('permit_delete_hidden')) {
		$navigation['admin/hidden'] = array(
			'label' => as_lang_html('admin/hidden_title'),
			'url' => as_path_html('admin/hidden'),
		);
	}

	if (!AS_FINAL_EXTERNAL_USERS && as_opt('moderate_users') && $level >= AS_USER_LEVEL_MODERATOR) {
		$count = as_opt('cache_uapprovecount');

		$navigation['admin/approve'] = array(
			'label' => as_lang_html('admin/approve_users_title') . ($count ? (' (' . $count . ')') : ''),
			'url' => as_path_html('admin/approve'),
		);
	}

	return $navigation;
}


/**
 * Return the error that needs to displayed on all admin pages, or null if none
 */
function as_admin_page_error()
{
	if (file_exists(AS_INCLUDE_DIR . 'db/install.php')) // file can be removed for extra security
		include_once AS_INCLUDE_DIR . 'db/install.php';

	if (defined('AS_DB_VERSION_CURRENT') && as_opt('db_version') < AS_DB_VERSION_CURRENT && as_get_logged_in_level() >= AS_USER_LEVEL_ADMIN) {
		return strtr(
			as_lang_html('admin/upgrade_db'),

			array(
				'^1' => '<a href="' . as_path_html('install') . '">',
				'^2' => '</a>',
			)
		);

	} elseif (defined('AS_BLOBS_DIRECTORY') && !is_writable(AS_BLOBS_DIRECTORY)) {
		return as_lang_html_sub('admin/blobs_directory_error', as_html(AS_BLOBS_DIRECTORY));
	}

	return null;
}


/**
 * Return an HTML fragment to display for a URL test which has passed
 */
function as_admin_url_test_html()
{
	return '; font-size:9px; color:#060; font-weight:bold; font-family:arial,sans-serif; border-color:#060;">OK<';
}


/**
 * Returns whether a URL path beginning with $requestpart is reserved by the engine or a plugin page module
 * @param $requestpart
 * @return bool
 */
function as_admin_is_slug_reserved($requestpart)
{
	$requestpart = trim(strtolower($requestpart));
	$routing = as_page_routing();

	if (isset($routing[$requestpart]) || isset($routing[$requestpart . '/']) || is_numeric($requestpart))
		return true;

	$pathmap = as_get_request_map();

	foreach ($pathmap as $mappedrequest) {
		if (trim(strtolower($mappedrequest)) == $requestpart)
			return true;
	}

	switch ($requestpart) {
		case '':
		case 'as':
		case 'feed':
		case 'install':
		case 'url':
		case 'image':
		case 'ajax':
			return true;
	}

	$pagemodules = as_load_modules_with('page', 'match_request');
	foreach ($pagemodules as $pagemodule) {
		if ($pagemodule->match_request($requestpart))
			return true;
	}

	return false;
}


/**
 * Returns true if admin (hidden/flagged/approve/moderate) page $action performed on $entityid is permitted by the
 * logged in user and was processed successfully
 * @param $entityid
 * @param $action
 * @return bool
 */
function as_admin_single_click($entityid, $action)
{
	$userid = as_get_logged_in_userid();

	if (!AS_FINAL_EXTERNAL_USERS && ($action == 'userapprove' || $action == 'userblock')) { // approve/block moderated users
		require_once AS_INCLUDE_DIR . 'db/selects.php';

		$useraccount = as_db_select_with_pending(as_db_user_account_selectspec($entityid, true));

		if (isset($useraccount) && as_get_logged_in_level() >= AS_USER_LEVEL_MODERATOR) {
			switch ($action) {
				case 'userapprove':
					if ($useraccount['level'] <= AS_USER_LEVEL_APPROVED) { // don't demote higher level users
						require_once AS_INCLUDE_DIR . 'app/users-edit.php';
						as_set_user_level($useraccount['userid'], $useraccount['handle'], AS_USER_LEVEL_APPROVED, $useraccount['level']);
						return true;
					}
					break;

				case 'userblock':
					require_once AS_INCLUDE_DIR . 'app/users-edit.php';
					as_set_user_blocked($useraccount['userid'], $useraccount['handle'], true);
					return true;
					break;
			}
		}

	} else { // something to do with a post
		require_once AS_INCLUDE_DIR . 'app/posts.php';

		$post = as_post_get_full($entityid);

		if (isset($post)) {
			$queued = (substr($post['type'], 1) == '_QUEUED');

			switch ($action) {
				case 'approve':
					if ($queued && !as_user_post_permit_error('permit_moderate', $post)) {
						as_post_set_status($entityid, AS_POST_STATUS_NORMAL, $userid);
						return true;
					}
					break;

				case 'reject':
					if ($queued && !as_user_post_permit_error('permit_moderate', $post)) {
						as_post_set_status($entityid, AS_POST_STATUS_HIDDEN, $userid);
						return true;
					}
					break;

				case 'hide':
					if (!$queued && !as_user_post_permit_error('permit_hide_show', $post)) {
						as_post_set_status($entityid, AS_POST_STATUS_HIDDEN, $userid);
						return true;
					}
					break;

				case 'reshow':
					if ($post['hidden'] && !as_user_post_permit_error('permit_hide_show', $post)) {
						as_post_set_status($entityid, AS_POST_STATUS_NORMAL, $userid);
						return true;
					}
					break;

				case 'delete':
					if ($post['hidden'] && !as_user_post_permit_error('permit_delete_hidden', $post)) {
						as_post_delete($entityid);
						return true;
					}
					break;

				case 'clearflags':
					require_once AS_INCLUDE_DIR . 'app/thumbs.php';

					if (!as_user_post_permit_error('permit_hide_show', $post)) {
						as_flags_clear_all($post, $userid, as_get_logged_in_handle(), null);
						return true;
					}
					break;
			}
		}
	}

	return false;
}


/**
 * Checks for a POSTed click on an admin (hidden/flagged/approve/moderate) page, and refresh the page if processed successfully (non Ajax)
 */
function as_admin_check_clicks()
{
	if (!as_is_http_post()) {
		return null;
	}

	foreach ($_POST as $field => $value) {
		if (strpos($field, 'admin_') !== 0) {
			continue;
		}

		@list($dummy, $entityid, $action) = explode('_', $field);

		if (strlen($entityid) && strlen($action)) {
			if (!as_check_form_security_code('admin/click', as_post_text('code')))
				return as_lang_html('misc/form_security_again');
			elseif (as_admin_single_click($entityid, $action))
				as_redirect(as_request());
		}
	}

	return null;
}


/**
 * Retrieve metadata information from the $contents of a as-theme.php or as-plugin.php file, mapping via $fields.
 *
 * @deprecated Deprecated from 1.7; use `as_addon_metadata($contents, $type)` instead.
 * @param $contents
 * @param $fields
 * @return array
 */
function as_admin_addon_metadata($contents, $fields)
{
	$metadata = array();

	foreach ($fields as $key => $field) {
		if (preg_match('/' . str_replace(' ', '[ \t]*', preg_quote($field, '/')) . ':[ \t]*([^\n\f]*)[\n\f]/i', $contents, $matches))
			$metadata[$key] = trim($matches[1]);
	}

	return $metadata;
}


/**
 * Return the hash code for the plugin in $directory (without trailing slash), used for in-page navigation on admin/plugins page
 * @param $directory
 * @return mixed
 */
function as_admin_plugin_directory_hash($directory)
{
	$pluginManager = new APS_Plugin_PluginManager();
	$hashes = $pluginManager->getHashesForPlugins(array($directory));

	return reset($hashes);
}


/**
 * Return the URL (relative to the current page) to navigate to the options panel for the plugin in $directory (without trailing slash)
 * @param $directory
 * @return mixed|string
 */
function as_admin_plugin_options_path($directory)
{
	$hash = as_admin_plugin_directory_hash($directory);
	return as_path_html('admin/plugins', array('show' => $hash), null, null, $hash);
}


/**
 * Return the URL (relative to the current page) to navigate to the options panel for plugin module $name of $type
 * @param $type
 * @param $name
 * @return mixed|string
 */
function as_admin_module_options_path($type, $name)
{
	$info = as_get_module_info($type, $name);
	$dir = basename($info['directory']);

	return as_admin_plugin_options_path($dir);
}
