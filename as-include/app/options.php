<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Getting and setting admin options (application level)


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

require_once AS_INCLUDE_DIR . 'db/options.php';

define('AS_PERMIT_ALL', 150);
define('AS_PERMIT_USERS', 120);
define('AS_PERMIT_CONFIRMED', 110);
define('AS_PERMIT_POINTS', 106);
define('AS_PERMIT_POINTS_CONFIRMED', 104);
define('AS_PERMIT_APPROVED', 103);
define('AS_PERMIT_APPROVED_POINTS', 102);
define('AS_PERMIT_EXPERTS', 100);
define('AS_PERMIT_EDITORS', 70);
define('AS_PERMIT_MODERATORS', 40);
define('AS_PERMIT_ADMINS', 20);
define('AS_PERMIT_SUPERS', 0);


/**
 * Return an array [name] => [value] of settings for each option in $names.
 * If any options are missing from the database, set them to their defaults
 * @param $names
 * @return array
 */
function as_get_options($names)
{
	global $as_options_cache, $as_options_loaded;

	// If any options not cached, retrieve them from database via standard pending mechanism

	if (!$as_options_loaded)
		as_preload_options();

	if (!$as_options_loaded) {
		require_once AS_INCLUDE_DIR . 'db/selects.php';

		as_load_options_results(array(
			as_db_get_pending_result('options'),
			as_db_get_pending_result('time'),
		));
	}

	// Pull out the options specifically requested here, and assign defaults

	$options = array();
	foreach ($names as $name) {
		if (!isset($as_options_cache[$name])) {
			$todatabase = true;

			switch ($name) { // don't write default to database if option was deprecated, or depends on site language (which could be changed)
				case 'custom_sidebar':
				case 'site_title':
				case 'email_privacy':
				case 'review_needs_signin':
				case 'post_needs_signin':
				case 'comment_needs_signin':
				case 'db_time':
					$todatabase = false;
					break;
			}

			as_set_option($name, as_default_option($name), $todatabase);
		}

		$options[$name] = $as_options_cache[$name];
	}

	return $options;
}


/**
 * Return the value of option $name if it has already been loaded, otherwise return null
 * (used to prevent a database query if it's not essential for us to know the option value)
 * @param $name
 * @return
 */
function as_opt_if_loaded($name)
{
	global $as_options_cache;

	return @$as_options_cache[$name];
}


/**
 * Load all of the APS options from the database.
 * From APS 1.8 we always load the options in a separate query regardless of AS_OPTIMIZE_DISTANT_DB.
 */
function as_preload_options()
{
	global $as_options_loaded;

	if (!@$as_options_loaded) {
		$selectspecs = array(
			'options' => array(
				'columns' => array('title', 'content'),
				'source' => '^options',
				'arraykey' => 'title',
				'arrayvalue' => 'content',
			),

			'time' => array(
				'columns' => array('title' => "'db_time'", 'content' => 'UNIX_TIMESTAMP(NOW())'),
				'arraykey' => 'title',
				'arrayvalue' => 'content',
			),
		);

		// fetch options in a separate query before everything else
		as_load_options_results(as_db_multi_select($selectspecs));
	}
}


/**
 * Load the options from the $results of the database selectspecs defined in as_preload_options()
 * @param $results
 * @return mixed
 */
function as_load_options_results($results)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	global $as_options_cache, $as_options_loaded;

	foreach ($results as $result) {
		foreach ($result as $name => $value) {
			$as_options_cache[$name] = $value;
		}
	}

	$as_options_loaded = true;
}


/**
 * Set an option $name to $value (application level) in both cache and database, unless
 * $todatabase=false, in which case set it in the cache only
 * @param $name
 * @param $value
 * @param bool $todatabase
 * @return mixed
 */
function as_set_option($name, $value, $todatabase = true)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	global $as_options_cache;

	if ($todatabase && isset($value))
		as_db_set_option($name, $value);

	$as_options_cache[$name] = $value;
}


/**
 * Reset the options in $names to their defaults
 * @param $names
 * @return mixed
 */
function as_reset_options($names)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	foreach ($names as $name) {
		as_set_option($name, as_default_option($name));
	}
}


/**
 * Return the default value for option $name
 * @param $name
 * @return bool|mixed|string
 */
function as_default_option($name)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$fixed_defaults = array(
		'allow_anonymous_naming' => 1,
		'allow_change_usernames' => 1,
		'allow_close_songs' => 1,
		'allow_close_own_songs' => 1,
		'allow_multi_reviews' => 1,
		'allow_private_messages' => 1,
		'allow_user_walls' => 1,
		'allow_self_review' => 1,
		'allow_view_q_bots' => 1,
		'avatar_allow_gravatar' => 1,
		'avatar_allow_upload' => 1,
		'avatar_message_list_size' => 20,
		'avatar_profile_size' => 200,
		'avatar_s_list_size' => 0,
		'avatar_q_page_a_size' => 40,
		'avatar_q_page_c_size' => 20,
		'avatar_q_page_q_size' => 50,
		'avatar_store_size' => 400,
		'avatar_users_size' => 30,
		'caching_catwidget_time' => 30,
		'caching_driver' => 'filesystem',
		'caching_enabled' => 0,
		'caching_q_start' => 7,
		'caching_q_time' => 30,
		'caching_qlist_time' => 5,
		'captcha_on_anon_post' => 1,
		'captcha_on_feedback' => 1,
		'captcha_on_signup' => 1,
		'captcha_on_reset_password' => 1,
		'captcha_on_unconfirmed' => 0,
		'columns_tags' => 3,
		'columns_users' => 2,
		'comment_on_as' => 1,
		'comment_on_qs' => 0,
		'confirm_user_emails' => 1,
		'do_post_check_qs' => 0,
		'do_complete_tags' => 1,
		'do_count_q_views' => 1,
		'do_example_tags' => 1,
		'feed_for_activity' => 1,
		'feed_for_qa' => 1,
		'feed_for_songs' => 1,
		'feed_for_unreviewed' => 1,
		'feed_full_text' => 1,
		'feed_number_items' => 50,
		'feed_per_category' => 1,
		'feedback_enabled' => 1,
		'flagging_hide_after' => 5,
		'flagging_notify_every' => 2,
		'flagging_notify_first' => 1,
		'flagging_of_posts' => 1,
		'follow_on_as' => 1,
		'hot_weight_a_age' => 100,
		'hot_weight_reviews' => 100,
		'hot_weight_q_age' => 100,
		'hot_weight_views' => 100,
		'hot_weight_thumbs' => 100,
		'mailing_per_minute' => 500,
		'match_post_check_qs' => 3,
		'match_example_tags' => 3,
		'match_related_qs' => 3,
		'max_copy_user_updates' => 10,
		'max_len_q_title' => 120,
		'max_num_q_tags' => 5,
		'max_rate_ip_as' => 50,
		'max_rate_ip_cs' => 40,
		'max_rate_ip_flags' => 10,
		'max_rate_ip_signins' => 20,
		'max_rate_ip_messages' => 10,
		'max_rate_ip_qs' => 20,
		'max_rate_ip_signups' => 5,
		'max_rate_ip_uploads' => 20,
		'max_rate_ip_thumbs' => 600,
		'max_rate_user_as' => 25,
		'max_rate_user_cs' => 20,
		'max_rate_user_flags' => 5,
		'max_rate_user_messages' => 5,
		'max_rate_user_qs' => 10,
		'max_rate_user_uploads' => 10,
		'max_rate_user_thumbs' => 300,
		'max_store_user_updates' => 50,
		'min_len_a_content' => 12,
		'min_len_c_content' => 12,
		'min_len_q_content' => 0,
		'min_len_q_title' => 12,
		'min_num_q_tags' => 0,
		'minify_html' => 1,
		'moderate_notify_admin' => 1,
		'moderate_points_limit' => 150,
		'moderate_update_time' => 1,
		'nav_post' => 1,
		'nav_as_not_home' => 1,
		'nav_songs' => 1,
		'nav_tags' => 1,
		'nav_unreviewed' => 1,
		'nav_users' => 1,
		'neat_urls' => AS_URL_FORMAT_NEAT,
		'notify_users_default' => 1,
		'page_size_activity' => 20,
		'page_size_post_check_qs' => 5,
		'page_size_post_tags' => 5,
		'page_size_home' => 20,
		'page_size_hot_qs' => 20,
		'page_size_pms' => 10,
		'page_size_q_as' => 10,
		'page_size_qs' => 20,
		'page_size_related_qs' => 5,
		'page_size_search' => 10,
		'page_size_tag_qs' => 20,
		'page_size_tags' => 30,
		'page_size_una_qs' => 20,
		'page_size_users' => 30,
		'page_size_wall' => 10,
		'pages_prev_next' => 3,
		'permit_anon_view_ips' => AS_PERMIT_EDITORS,
		'permit_close_q' => AS_PERMIT_EDITORS,
		'permit_delete_hidden' => AS_PERMIT_MODERATORS,
		'permit_edit_a' => AS_PERMIT_EXPERTS,
		'permit_edit_c' => AS_PERMIT_EDITORS,
		'permit_edit_q' => AS_PERMIT_EDITORS,
		'permit_edit_silent' => AS_PERMIT_MODERATORS,
		'permit_flag' => AS_PERMIT_CONFIRMED,
		'permit_hide_show' => AS_PERMIT_EDITORS,
		'permit_moderate' => AS_PERMIT_EXPERTS,
		'permit_post_wall' => AS_PERMIT_CONFIRMED,
		'permit_select_a' => AS_PERMIT_EXPERTS,
		'permit_view_q_page' => AS_PERMIT_ALL,
		'permit_view_new_users_page' => AS_PERMIT_EDITORS,
		'permit_view_special_users_page' => AS_PERMIT_MODERATORS,
		'permit_view_thumbers_flaggers' => AS_PERMIT_ADMINS,
		'permit_thumb_a' => AS_PERMIT_USERS,
		'permit_thumb_c' => AS_PERMIT_USERS,
		'permit_thumb_down' => AS_PERMIT_USERS,
		'permit_thumb_q' => AS_PERMIT_USERS,
		'points_a_selected' => 30,
		'points_a_thumbd_max_gain' => 20,
		'points_a_thumbd_max_loss' => 5,
		'points_base' => 100,
		'points_c_thumbd_max_gain' => 10,
		'points_c_thumbd_max_loss' => 3,
		'points_multiple' => 10,
		'points_per_c_thumbd_down' => 0,
		'points_per_c_thumbd_up' => 0,
		'points_post_a' => 4,
		'points_post_q' => 2,
		'points_q_thumbd_max_gain' => 10,
		'points_q_thumbd_max_loss' => 3,
		'points_select_a' => 3,
		'q_urls_title_length' => 50,
		'recalc_hotness_q_view' => 1,
		'show_a_c_links' => 1,
		'show_a_form_immediate' => 'if_no_as',
		'show_c_reply_buttons' => 1,
		'show_compact_numbers' => 1,
		'show_custom_welcome' => 0,
		'show_fewer_cs_count' => 5,
		'show_fewer_cs_from' => 10,
		'show_full_date_days' => 7,
		'show_message_history' => 1,
		'show_post_update_meta' => 1,
		'show_signup_terms' => 0,
		'show_selected_first' => 1,
		'show_url_links' => 1,
		'show_user_points' => 1,
		'show_user_titles' => 1,
		'show_view_count_q_page' => 0,
		'show_view_counts' => 0,
		'show_when_created' => 1,
		'site_text_direction' => 'ltr',
		'site_theme' => 'Material',
		'site_title' => 'vSongBook Online',
		'smtp_port' => 25,
		'sort_reviews_by' => 'created',
		'tags_or_categories' => 'tc',
		'use_microdata' => 1,
		'thumbing_on_as' => 1,
		'thumbing_on_cs' => 0,
		'thumbing_on_qs' => 1,
	);

	if (isset($fixed_defaults[$name])) {
		return $fixed_defaults[$name];
	}

	switch ($name) {
		case 'site_url':
			$protocol =
				(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
				(!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
				(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
					? 'https'
					: 'http';
			$value = $protocol . '://' . @$_SERVER['HTTP_HOST'] . strtr(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'), '\\', '/') . '/';
			break;

		case 'site_title':
			$value = as_default_site_title();
			break;

		case 'site_theme_mobile':
			$value = as_opt('site_theme');
			break;

		case 'from_email': // heuristic to remove short prefix (e.g. www. or as.)
			$parts = explode('.', @$_SERVER['HTTP_HOST']);

			if (count($parts) > 2 && strlen($parts[0]) < 5 && !is_numeric($parts[0]))
				unset($parts[0]);

			$value = 'no-reply@' . ((count($parts) > 1) ? implode('.', $parts) : 'example.com');
			break;

		case 'email_privacy':
			$value = as_lang_html('options/default_privacy');
			break;

		case 'show_custom_sidebar':
			$value = strlen(as_opt('custom_sidebar')) > 0;
			break;

		case 'show_custom_header':
			$value = strlen(as_opt('custom_header')) > 0;
			break;

		case 'show_custom_footer':
			$value = strlen(as_opt('custom_footer')) > 0;
			break;

		case 'show_custom_in_head':
			$value = strlen(as_opt('custom_in_head')) > 0;
			break;

		case 'signup_terms':
			$value = as_lang_html_sub('options/default_terms', as_html(as_opt('site_title')));
			break;

		case 'block_bad_usernames':
			$value = as_lang_html('main/anonymous');
			break;

		case 'custom_sidebar':
			$value = as_lang_html_sub('options/default_sidebar', as_html(as_opt('site_title')));
			break;

		case 'editor_for_qs':
		case 'editor_for_as':
			require_once AS_INCLUDE_DIR . 'app/format.php';

			$value = '-'; // to match none by default, i.e. choose based on who is best at editing HTML
			as_load_editor('', 'html', $value);
			break;

		case 'permit_post_q': // convert from deprecated option if available
			$value = as_opt('post_needs_signin') ? AS_PERMIT_USERS : AS_PERMIT_ALL;
			break;

		case 'permit_post_a': // convert from deprecated option if available
			$value = as_opt('review_needs_signin') ? AS_PERMIT_USERS : AS_PERMIT_ALL;
			break;

		case 'permit_post_c': // convert from deprecated option if available
			$value = as_opt('comment_needs_signin') ? AS_PERMIT_USERS : AS_PERMIT_ALL;
			break;

		case 'permit_retag_cat': // convert from previous option that used to contain it too
			$value = as_opt('permit_edit_q');
			break;

		case 'points_thumb_up_q':
		case 'points_thumb_down_q':
			$oldvalue = as_opt('points_thumb_on_q');
			$value = is_numeric($oldvalue) ? $oldvalue : 1;
			break;

		case 'points_thumb_up_a':
		case 'points_thumb_down_a':
			$oldvalue = as_opt('points_thumb_on_a');
			$value = is_numeric($oldvalue) ? $oldvalue : 1;
			break;

		case 'points_per_q_thumbd_up':
		case 'points_per_q_thumbd_down':
			$oldvalue = as_opt('points_per_q_thumbd');
			$value = is_numeric($oldvalue) ? $oldvalue : 1;
			break;

		case 'points_per_a_thumbd_up':
		case 'points_per_a_thumbd_down':
			$oldvalue = as_opt('points_per_a_thumbd');
			$value = is_numeric($oldvalue) ? $oldvalue : 2;
			break;

		case 'captcha_module':
			$captchamodules = as_list_modules('captcha');
			if (count($captchamodules))
				$value = reset($captchamodules);
			else
				$value = '';
			break;

		case 'mailing_from_name':
			$value = as_opt('site_title');
			break;

		case 'mailing_from_email':
			$value = as_opt('from_email');
			break;

		case 'mailing_subject':
			$value = as_lang_sub('options/default_subject', as_opt('site_title'));
			break;

		case 'mailing_body':
			$value = "\n\n\n--\n" . as_opt('site_title') . "\n" . as_opt('site_url');
			break;

		case 'form_security_salt':
			require_once AS_INCLUDE_DIR . 'util/string.php';
			$value = as_random_alphanum(32);
			break;

		default: // call option_default method in any signuped modules
			$modules = as_load_all_modules_with('option_default');  // Loads all modules with the 'option_default' method

			foreach ($modules as $module) {
				$value = $module->option_default($name);
				if (strlen($value))
					return $value;
			}

			$value = '';
			break;
	}

	return $value;
}


/**
 * Return a heuristic guess at the name of the site from the HTTP HOST
 */
function as_default_site_title()
{
	$parts = explode('.', @$_SERVER['HTTP_HOST']);

	$longestpart = '';
	foreach ($parts as $part) {
		if (strlen($part) > strlen($longestpart))
			$longestpart = $part;
	}

	return ((strlen($longestpart) > 3) ? (ucfirst($longestpart) . ' ') : '') . as_lang('options/default_suffix');
}


/**
 * Return an array of defaults for the $options parameter passed to as_post_html_fields() and its ilk for posts of $basetype='S'/'R'/'C'
 * Set $full to true if these posts will be viewed in full, i.e. on a song page rather than a song listing
 * @param $basetype
 * @param bool $full
 * @return array|mixed
 */
function as_post_html_defaults($basetype, $full = false)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'app/users.php';

	return array(
		'tagsview' => $basetype == 'S' && as_using_tags(),
		'categoryview' => $basetype == 'S' && as_using_categories(),
		'contentview' => $full,
		'thumbview' => as_get_thumb_view($basetype, $full),
		'flagsview' => as_opt('flagging_of_posts') && $full,
		'favoritedview' => true,
		'reviewsview' => $basetype == 'S',
		'viewsview' => $basetype == 'S' && as_opt('do_count_q_views') && ($full ? as_opt('show_view_count_q_page') : as_opt('show_view_counts')),
		'whatview' => true,
		'whatlink' => as_opt('show_a_c_links'),
		'whenview' => as_opt('show_when_created'),
		'ipview' => !as_user_permit_error('permit_anon_view_ips'),
		'whoview' => true,
		'avatarsize' => as_opt('avatar_s_list_size'),
		'pointsview' => as_opt('show_user_points'),
		'pointstitle' => as_opt('show_user_titles') ? as_get_points_to_titles() : array(),
		'updateview' => as_opt('show_post_update_meta'),
		'blockwordspreg' => as_get_block_words_preg(),
		'showurllinks' => as_opt('show_url_links'),
		'linksnewwindow' => as_opt('links_in_new_window'),
		'fulldatedays' => as_opt('show_full_date_days'),
	);
}


/**
 * Return an array of options for post $post to pass in the $options parameter to as_post_html_fields() and its ilk. Preferably,
 * call as_post_html_defaults() previously and pass its output in $defaults, to save excessive recalculation for each item in a
 * list. Set $full to true if these posts will be viewed in full, i.e. on a song page rather than a song listing.
 * @param $post
 * @param $defaults
 * @param bool $full
 * @return array|mixed|null
 */
function as_post_html_options($post, $defaults = null, $full = false)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	if (!isset($defaults))
		$defaults = as_post_html_defaults($post['basetype'], $full);

	$defaults['thumbview'] = as_get_thumb_view($post, $full);
	$defaults['ipview'] = !as_user_post_permit_error('permit_anon_view_ips', $post);

	return $defaults;
}


/**
 * Return an array of defaults for the $options parameter passed to as_message_html_fields()
 */
function as_message_html_defaults()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	return array(
		'whenview' => as_opt('show_when_created'),
		'whoview' => true,
		'avatarsize' => as_opt('avatar_message_list_size'),
		'blockwordspreg' => as_get_block_words_preg(),
		'showurllinks' => as_opt('show_url_links'),
		'linksnewwindow' => as_opt('links_in_new_window'),
		'fulldatedays' => as_opt('show_full_date_days'),
	);
}


/**
 * Return $thumbview parameter to pass to as_post_html_fields() in /as-include/app/format.php.
 * @param array|string $postorbasetype The post, or for compatibility just a basetype, i.e. 'S', 'R' or 'C'
 * @param bool $full Whether full post is shown
 * @param bool $enabledif Whether to do checks for thumbing buttons (i.e. will always disable thumbing if false)
 * @return bool|string Possible values:
 *   updown, updown-disabled-page, updown-disabled-level, updown-uponly-level, updown-disabled-approve, updown-uponly-approve
 *   net, net-disabled-page, net-disabled-level, net-uponly-level, net-disabled-approve, net-uponly-approve
 */
function as_get_thumb_view($postorbasetype, $full = false, $enabledif = true)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	// The 'level' and 'approve' permission errors are taken care of by disabling the thumbing buttons.
	// Others are reported to the user after they click, in as_thumb_error_html(...)

	// deal with dual-use parameter
	if (is_array($postorbasetype)) {
		$basetype = $postorbasetype['basetype'];
		$post = $postorbasetype;
	} else {
		$basetype = $postorbasetype;
		$post = null;
	}

	$disabledsuffix = '';

	switch($basetype)
	{
		case 'S':
			$view = as_opt('thumbing_on_qs');
			$permitOpt = 'permit_thumb_q';
			break;
		case 'R':
			$view = as_opt('thumbing_on_as');
			$permitOpt = 'permit_thumb_a';
			break;
		case 'C':
			$view = as_opt('thumbing_on_cs');
			$permitOpt = 'permit_thumb_c';
			break;
		default:
			$view = false;
			break;
	}

	if (!$view) {
		return false;
	}

	if (!$enabledif || ($basetype == 'S' && !$full && as_opt('thumbing_on_q_page_only'))) {
		$disabledsuffix = '-disabled-page';
	}
	else {
		$permiterror = isset($post) ? as_user_post_permit_error($permitOpt, $post) : as_user_permit_error($permitOpt);

		if ($permiterror == 'level')
			$disabledsuffix = '-disabled-level';
		elseif ($permiterror == 'approve')
			$disabledsuffix = '-disabled-approve';
		else {
			$permiterrordown = isset($post) ? as_user_post_permit_error('permit_thumb_down', $post) : as_user_permit_error('permit_thumb_down');

			if ($permiterrordown == 'level')
				$disabledsuffix = '-uponly-level';
			elseif ($permiterrordown == 'approve')
				$disabledsuffix = '-uponly-approve';
		}
	}

	return (as_opt('thumbs_separated') ? 'updown' : 'net') . $disabledsuffix;
}


/**
 * Returns true if the home page has been customized, either due to admin setting, or $AS_CONST_PATH_MAP
 */
function as_has_custom_home()
{
	return as_opt('show_custom_home') || (array_search('', as_get_request_map()) !== false);
}


/**
 * Return whether the option is set to classify songs by tags
 */
function as_using_tags()
{
	return strpos(as_opt('tags_or_categories'), 't') !== false;
}


/**
 * Return whether the option is set to classify songs by categories
 */
function as_using_categories()
{
	return strpos(as_opt('tags_or_categories'), 'c') !== false;
}


/**
 * Return the regular expression fragment to match the blocked words options set in the database
 */
function as_get_block_words_preg()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	global $as_blockwordspreg, $as_blockwordspreg_set;

	if (!@$as_blockwordspreg_set) {
		$blockwordstring = as_opt('block_bad_words');

		if (strlen($blockwordstring)) {
			require_once AS_INCLUDE_DIR . 'util/string.php';
			$as_blockwordspreg = as_block_words_to_preg($blockwordstring);

		} else
			$as_blockwordspreg = null;

		$as_blockwordspreg_set = true;
	}

	return $as_blockwordspreg;
}


/**
 * Return an array of [points] => [user title] from the 'points_to_titles' option, to pass to as_get_points_title_html()
 */
function as_get_points_to_titles()
{
	global $as_points_title_cache;

	if (!is_array($as_points_title_cache)) {
		$as_points_title_cache = array();

		$pairs = explode(',', as_opt('points_to_titles'));
		foreach ($pairs as $pair) {
			$spacepos = strpos($pair, ' ');
			if (is_numeric($spacepos)) {
				$points = trim(substr($pair, 0, $spacepos));
				$title = trim(substr($pair, $spacepos));

				if (is_numeric($points) && strlen($title))
					$as_points_title_cache[(int)$points] = $title;
			}
		}

		krsort($as_points_title_cache, SORT_NUMERIC);
	}

	return $as_points_title_cache;
}


/**
 * Return an array of relevant permissions settings, based on other options
 */
function as_get_permit_options()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$permits = array('permit_view_q_page', 'permit_post_q', 'permit_post_a');

	if (as_opt('comment_on_qs') || as_opt('comment_on_as'))
		$permits[] = 'permit_post_c';

	if (as_opt('thumbing_on_qs'))
		$permits[] = 'permit_thumb_q';

	if (as_opt('thumbing_on_as'))
		$permits[] = 'permit_thumb_a';

	if (as_opt('thumbing_on_cs'))
		$permits[] = 'permit_thumb_c';

	if (as_opt('thumbing_on_qs') || as_opt('thumbing_on_as') || as_opt('thumbing_on_cs'))
		$permits[] = 'permit_thumb_down';

	if (as_using_tags() || as_using_categories())
		$permits[] = 'permit_retag_cat';

	array_push($permits, 'permit_edit_q', 'permit_edit_a');

	if (as_opt('comment_on_qs') || as_opt('comment_on_as'))
		$permits[] = 'permit_edit_c';

	$permits[] = 'permit_edit_silent';

	if (as_opt('allow_close_songs'))
		$permits[] = 'permit_close_q';

	array_push($permits, 'permit_select_a', 'permit_anon_view_ips');

	if (as_opt('thumbing_on_qs') || as_opt('thumbing_on_as') || as_opt('thumbing_on_cs') || as_opt('flagging_of_posts'))
		$permits[] = 'permit_view_thumbers_flaggers';

	if (as_opt('flagging_of_posts'))
		$permits[] = 'permit_flag';

	$permits[] = 'permit_moderate';

	array_push($permits, 'permit_hide_show', 'permit_delete_hidden');

	if (as_opt('allow_user_walls'))
		$permits[] = 'permit_post_wall';

	array_push($permits, 'permit_view_new_users_page', 'permit_view_special_users_page');

	return $permits;
}
