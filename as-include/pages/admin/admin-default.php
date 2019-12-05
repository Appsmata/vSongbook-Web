<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for most admin pages which just contain options


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
	header('Location: ../../../');
	exit;
}

require_once AS_INCLUDE_DIR . 'db/admin.php';
require_once AS_INCLUDE_DIR . 'db/maxima.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'app/options.php';
require_once AS_INCLUDE_DIR . 'app/admin.php';


// Pages handled by this controller: general, emails, users, layout, viewing, lists, posting, permissions, feeds, spam, caching, mailing

$adminsection = strtolower(as_request_part(1));


// Get list of categories and all options

$categories = as_db_select_with_pending(as_db_category_nav_selectspec(null, true));


// See if we need to redirect

if (empty($adminsection)) {
	$subnav = as_admin_sub_navigation();

	if (isset($subnav[@$_COOKIE['as_admin_last']]))
		as_redirect($_COOKIE['as_admin_last']);
	elseif (count($subnav)) {
		reset($subnav);
		as_redirect(key($subnav));
	}
}


// Check admin privileges (do late to allow one DB query)

if (!as_admin_check_privileges($as_content))
	return $as_content;


// For non-text options, lists of option types, minima and maxima

$optiontype = array(
	'avatar_message_list_size' => 'number',
	'avatar_profile_size' => 'number',
	'avatar_s_list_size' => 'number',
	'avatar_q_page_a_size' => 'number',
	'avatar_q_page_c_size' => 'number',
	'avatar_q_page_q_size' => 'number',
	'avatar_store_size' => 'number',
	'avatar_users_size' => 'number',
	'caching_catwidget_time' => 'number',
	'caching_q_start' => 'number',
	'caching_q_time' => 'number',
	'caching_qlist_time' => 'number',
	'columns_tags' => 'number',
	'columns_users' => 'number',
	'feed_number_items' => 'number',
	'flagging_hide_after' => 'number',
	'flagging_notify_every' => 'number',
	'flagging_notify_first' => 'number',
	'hot_weight_a_age' => 'number',
	'hot_weight_reviews' => 'number',
	'hot_weight_q_age' => 'number',
	'hot_weight_views' => 'number',
	'hot_weight_thumbs' => 'number',
	'logo_height' => 'number-blank',
	'logo_width' => 'number-blank',
	'mailing_per_minute' => 'number',
	'max_len_q_title' => 'number',
	'max_num_q_tags' => 'number',
	'max_rate_ip_as' => 'number',
	'max_rate_ip_cs' => 'number',
	'max_rate_ip_flags' => 'number',
	'max_rate_ip_signins' => 'number',
	'max_rate_ip_messages' => 'number',
	'max_rate_ip_qs' => 'number',
	'max_rate_ip_signups' => 'number',
	'max_rate_ip_uploads' => 'number',
	'max_rate_ip_thumbs' => 'number',
	'max_rate_user_as' => 'number',
	'max_rate_user_cs' => 'number',
	'max_rate_user_flags' => 'number',
	'max_rate_user_messages' => 'number',
	'max_rate_user_qs' => 'number',
	'max_rate_user_uploads' => 'number',
	'max_rate_user_thumbs' => 'number',
	'min_len_a_content' => 'number',
	'min_len_c_content' => 'number',
	'min_len_q_content' => 'number',
	'min_len_q_title' => 'number',
	'min_num_q_tags' => 'number',
	'moderate_points_limit' => 'number',
	'page_size_activity' => 'number',
	'page_size_post_check_qs' => 'number',
	'page_size_post_tags' => 'number',
	'page_size_home' => 'number',
	'page_size_hot_qs' => 'number',
	'page_size_pms' => 'number',
	'page_size_q_as' => 'number',
	'page_size_qs' => 'number',
	'page_size_related_qs' => 'number',
	'page_size_search' => 'number',
	'page_size_tag_qs' => 'number',
	'page_size_tags' => 'number',
	'page_size_una_qs' => 'number',
	'page_size_users' => 'number',
	'page_size_wall' => 'number',
	'pages_prev_next' => 'number',
	'q_urls_title_length' => 'number',
	'show_fewer_cs_count' => 'number',
	'show_fewer_cs_from' => 'number',
	'show_full_date_days' => 'number',
	'smtp_port' => 'number',

	'allow_anonymous_naming' => 'checkbox',
	'allow_change_usernames' => 'checkbox',
	'allow_close_songs' => 'checkbox',
	'allow_close_own_songs' => 'checkbox',
	'allow_signin_email_only' => 'checkbox',
	'allow_multi_reviews' => 'checkbox',
	'allow_private_messages' => 'checkbox',
	'allow_user_walls' => 'checkbox',
	'allow_self_review' => 'checkbox',
	'allow_view_q_bots' => 'checkbox',
	'avatar_allow_gravatar' => 'checkbox',
	'avatar_allow_upload' => 'checkbox',
	'avatar_default_show' => 'checkbox',
	'caching_enabled' => 'checkbox',
	'captcha_on_anon_post' => 'checkbox',
	'captcha_on_feedback' => 'checkbox',
	'captcha_on_signup' => 'checkbox',
	'captcha_on_reset_password' => 'checkbox',
	'captcha_on_unapproved' => 'checkbox',
	'captcha_on_unconfirmed' => 'checkbox',
	'comment_on_as' => 'checkbox',
	'comment_on_qs' => 'checkbox',
	'confirm_user_emails' => 'checkbox',
	'confirm_user_required' => 'checkbox',
	'do_post_check_qs' => 'checkbox',
	'do_close_on_select' => 'checkbox',
	'do_complete_tags' => 'checkbox',
	'do_count_q_views' => 'checkbox',
	'do_example_tags' => 'checkbox',
	'extra_field_active' => 'checkbox',
	'extra_field_display' => 'checkbox',
	'feed_for_activity' => 'checkbox',
	'feed_for_hot' => 'checkbox',
	'feed_for_qa' => 'checkbox',
	'feed_for_songs' => 'checkbox',
	'feed_for_search' => 'checkbox',
	'feed_for_tag_qs' => 'checkbox',
	'feed_for_unreviewed' => 'checkbox',
	'feed_full_text' => 'checkbox',
	'feed_per_category' => 'checkbox',
	'feedback_enabled' => 'checkbox',
	'flagging_of_posts' => 'checkbox',
	'follow_on_as' => 'checkbox',
	'links_in_new_window' => 'checkbox',
	'logo_show' => 'checkbox',
	'mailing_enabled' => 'checkbox',
	'moderate_anon_post' => 'checkbox',
	'moderate_by_points' => 'checkbox',
	'moderate_edited_again' => 'checkbox',
	'moderate_notify_admin' => 'checkbox',
	'moderate_unapproved' => 'checkbox',
	'moderate_unconfirmed' => 'checkbox',
	'moderate_users' => 'checkbox',
	'neat_urls' => 'checkbox',
	'notify_admin_q_post' => 'checkbox',
	'notify_users_default' => 'checkbox',
	'q_urls_remove_accents' => 'checkbox',
	'recalc_hotness_q_view' => 'checkbox',
	'signup_notify_admin' => 'checkbox',
	'show_c_reply_buttons' => 'checkbox',
	'show_compact_numbers' => 'checkbox',
	'show_custom_review' => 'checkbox',
	'show_custom_post' => 'checkbox',
	'show_custom_comment' => 'checkbox',
	'show_custom_footer' => 'checkbox',
	'show_custom_header' => 'checkbox',
	'show_custom_home' => 'checkbox',
	'show_custom_in_head' => 'checkbox',
	'show_custom_signup' => 'checkbox',
	'show_custom_sidebar' => 'checkbox',
	'show_custom_sidepanel' => 'checkbox',
	'show_custom_welcome' => 'checkbox',
	'show_home_description' => 'checkbox',
	'show_message_history' => 'checkbox',
	'show_notice_visitor' => 'checkbox',
	'show_notice_welcome' => 'checkbox',
	'show_post_update_meta' => 'checkbox',
	'show_signup_terms' => 'checkbox',
	'show_selected_first' => 'checkbox',
	'show_url_links' => 'checkbox',
	'show_user_points' => 'checkbox',
	'show_user_titles' => 'checkbox',
	'show_view_counts' => 'checkbox',
	'show_view_count_q_page' => 'checkbox',
	'show_when_created' => 'checkbox',
	'site_maintenance' => 'checkbox',
	'smtp_active' => 'checkbox',
	'smtp_authenticate' => 'checkbox',
	'suspend_signup_users' => 'checkbox',
	'tag_separator_comma' => 'checkbox',
	'use_microdata' => 'checkbox',
	'minify_html' => 'checkbox',
	'thumbs_separated' => 'checkbox',
	'thumbing_on_as' => 'checkbox',
	'thumbing_on_cs' => 'checkbox',
	'thumbing_on_q_page_only' => 'checkbox',
	'thumbing_on_qs' => 'checkbox',

	'smtp_password' => 'password',
);

$optionmaximum = array(
	'feed_number_items' => AS_DB_RETRIEVE_QS_AS,
	'max_len_q_title' => AS_DB_MAX_TITLE_LENGTH,
	'page_size_activity' => AS_DB_RETRIEVE_QS_AS,
	'page_size_post_check_qs' => AS_DB_RETRIEVE_QS_AS,
	'page_size_post_tags' => AS_DB_RETRIEVE_QS_AS,
	'page_size_home' => AS_DB_RETRIEVE_QS_AS,
	'page_size_hot_qs' => AS_DB_RETRIEVE_QS_AS,
	'page_size_pms' => AS_DB_RETRIEVE_MESSAGES,
	'page_size_qs' => AS_DB_RETRIEVE_QS_AS,
	'page_size_related_qs' => AS_DB_RETRIEVE_QS_AS,
	'page_size_search' => AS_DB_RETRIEVE_QS_AS,
	'page_size_tag_qs' => AS_DB_RETRIEVE_QS_AS,
	'page_size_tags' => AS_DB_RETRIEVE_TAGS,
	'page_size_una_qs' => AS_DB_RETRIEVE_QS_AS,
	'page_size_users' => AS_DB_RETRIEVE_USERS,
	'page_size_wall' => AS_DB_RETRIEVE_MESSAGES,
);

$optionminimum = array(
	'flagging_hide_after' => 2,
	'flagging_notify_every' => 1,
	'flagging_notify_first' => 1,
	'max_num_q_tags' => 2,
	'max_rate_ip_signins' => 1,
	'min_len_a_content' => 1,
	'min_len_c_content' => 1,
	'min_len_q_title' => 1,
	'page_size_activity' => 1,
	'page_size_post_check_qs' => 3,
	'page_size_post_tags' => 3,
	'page_size_home' => 1,
	'page_size_hot_qs' => 1,
	'page_size_pms' => 1,
	'page_size_q_as' => 1,
	'page_size_qs' => 1,
	'page_size_search' => 1,
	'page_size_tag_qs' => 1,
	'page_size_tags' => 1,
	'page_size_users' => 1,
	'page_size_wall' => 1,
);


// Define the options to show (and some other visual stuff) based on request

$formstyle = 'tall';
$checkboxtodisplay = null;

$maxpermitpost = max(as_opt('permit_post_q'), as_opt('permit_post_a'));
if (as_opt('comment_on_qs') || as_opt('comment_on_as'))
	$maxpermitpost = max($maxpermitpost, as_opt('permit_post_c'));

switch ($adminsection) {
	case 'general':
		$subtitle = 'admin/general_title';
		$showoptions = array('site_title', 'site_url', 'neat_urls', 'site_language', 'site_theme', 'site_theme_mobile', 'site_text_direction', 'tags_or_categories', 'site_maintenance');
		break;

	case 'emails':
		$subtitle = 'admin/emails_title';
		$showoptions = array(
			'from_email', 'feedback_email', 'notify_admin_q_post', 'feedback_enabled', 'email_privacy',
			'smtp_active', 'smtp_address', 'smtp_port', 'smtp_secure', 'smtp_authenticate', 'smtp_username', 'smtp_password'
		);

		$checkboxtodisplay = array(
			'smtp_address' => 'option_smtp_active',
			'smtp_port' => 'option_smtp_active',
			'smtp_secure' => 'option_smtp_active',
			'smtp_authenticate' => 'option_smtp_active',
			'smtp_username' => 'option_smtp_active && option_smtp_authenticate',
			'smtp_password' => 'option_smtp_active && option_smtp_authenticate',
		);
		break;

	case 'users':
		$subtitle = 'admin/users_title';

		$showoptions = array('show_notice_visitor', 'notice_visitor');

		if (!AS_FINAL_EXTERNAL_USERS) {
			require_once AS_INCLUDE_DIR . 'util/image.php';

			array_push($showoptions, 'show_custom_signup', 'custom_signup', 'show_signup_terms', 'signup_terms', 'show_notice_welcome', 'notice_welcome', 'show_custom_welcome', 'custom_welcome',
				'', 'allow_signin_email_only', 'allow_change_usernames', 'signup_notify_admin', 'suspend_signup_users', '', 'block_bad_usernames',
				'', 'allow_private_messages', 'show_message_history', 'page_size_pms', 'allow_user_walls', 'page_size_wall',
				'', 'avatar_allow_gravatar');

			if (as_has_gd_image())
				array_push($showoptions, 'avatar_allow_upload', 'avatar_store_size', 'avatar_default_show');
		}

		$showoptions[] = '';

		if (!AS_FINAL_EXTERNAL_USERS)
			$showoptions[] = 'avatar_profile_size';

		array_push($showoptions, 'avatar_users_size', 'avatar_q_page_q_size', 'avatar_q_page_a_size', 'avatar_q_page_c_size',
			'avatar_s_list_size', 'avatar_message_list_size');

		$checkboxtodisplay = array(
			'custom_signup' => 'option_show_custom_signup',
			'signup_terms' => 'option_show_signup_terms',
			'custom_welcome' => 'option_show_custom_welcome',
			'notice_welcome' => 'option_show_notice_welcome',
			'notice_visitor' => 'option_show_notice_visitor',
			'show_message_history' => 'option_allow_private_messages',
			'avatar_store_size' => 'option_avatar_allow_upload',
			'avatar_default_show' => 'option_avatar_allow_gravatar || option_avatar_allow_upload',
		);

		if (!AS_FINAL_EXTERNAL_USERS) {
			$checkboxtodisplay = array_merge($checkboxtodisplay, array(
				'page_size_pms' => 'option_allow_private_messages && option_show_message_history',
				'page_size_wall' => 'option_allow_user_walls',
				'avatar_profile_size' => 'option_avatar_allow_gravatar || option_avatar_allow_upload',
				'avatar_users_size' => 'option_avatar_allow_gravatar || option_avatar_allow_upload',
				'avatar_q_page_q_size' => 'option_avatar_allow_gravatar || option_avatar_allow_upload',
				'avatar_q_page_a_size' => 'option_avatar_allow_gravatar || option_avatar_allow_upload',
				'avatar_q_page_c_size' => 'option_avatar_allow_gravatar || option_avatar_allow_upload',
				'avatar_s_list_size' => 'option_avatar_allow_gravatar || option_avatar_allow_upload',
				'avatar_message_list_size' => '(option_avatar_allow_gravatar || option_avatar_allow_upload) && (option_allow_private_messages || option_allow_user_walls)',
			));
		}

		$formstyle = 'wide';
		break;

	case 'layout':
		$subtitle = 'admin/layout_title';
		$showoptions = array('logo_show', 'logo_url', 'logo_width', 'logo_height', '', 'show_custom_sidebar', 'custom_sidebar', 'show_custom_sidepanel', 'custom_sidepanel', 'show_custom_header', 'custom_header', 'show_custom_footer', 'custom_footer', 'show_custom_in_head', 'custom_in_head', 'show_custom_home', 'custom_home_heading', 'custom_home_content', 'show_home_description', 'home_description', '');

		$checkboxtodisplay = array(
			'logo_url' => 'option_logo_show',
			'logo_width' => 'option_logo_show',
			'logo_height' => 'option_logo_show',
			'custom_sidebar' => 'option_show_custom_sidebar',
			'custom_sidepanel' => 'option_show_custom_sidepanel',
			'custom_header' => 'option_show_custom_header',
			'custom_footer' => 'option_show_custom_footer',
			'custom_in_head' => 'option_show_custom_in_head',
			'custom_home_heading' => 'option_show_custom_home',
			'custom_home_content' => 'option_show_custom_home',
			'home_description' => 'option_show_home_description',
		);
		break;

	case 'viewing':
		$subtitle = 'admin/viewing_title';
		$showoptions = array(
			'q_urls_title_length', 'q_urls_remove_accents', 'do_count_q_views', 'show_view_counts', 'show_view_count_q_page', 'recalc_hotness_q_view', '',
			'thumbing_on_qs', 'thumbing_on_q_page_only', 'thumbing_on_as', 'thumbing_on_cs', 'thumbs_separated', '',
			'show_url_links', 'links_in_new_window', 'show_when_created', 'show_full_date_days'
		);

		if (count(as_get_points_to_titles())) {
			$showoptions[] = 'show_user_titles';
		}

		array_push($showoptions,
			'show_user_points', 'show_post_update_meta', 'show_compact_numbers', 'use_microdata', 'minify_html', '',
			'sort_reviews_by', 'show_selected_first', 'page_size_q_as', 'show_a_form_immediate'
		);

		if (as_opt('comment_on_qs') || as_opt('comment_on_as')) {
			array_push($showoptions, 'show_fewer_cs_from', 'show_fewer_cs_count', 'show_c_reply_buttons');
		}

		$showoptions[] = '';

		$widgets = as_db_single_select(as_db_widgets_selectspec());

		foreach ($widgets as $widget) {
			if ($widget['title'] == 'Related Songs') {
				array_push($showoptions, 'match_related_qs', 'page_size_related_qs', '');
				break;
			}
		}

		$showoptions[] = 'pages_prev_next';

		$formstyle = 'wide';

		$checkboxtodisplay = array(
			'show_view_counts' => 'option_do_count_q_views',
			'show_view_count_q_page' => 'option_do_count_q_views',
			'recalc_hotness_q_view' => 'option_do_count_q_views',
			'thumbs_separated' => 'option_thumbing_on_qs || option_thumbing_on_as',
			'thumbing_on_q_page_only' => 'option_thumbing_on_qs',
			'show_full_date_days' => 'option_show_when_created',
		);
		break;

	case 'lists':
		$subtitle = 'admin/lists_title';

		$showoptions = array('page_size_home', 'page_size_activity', 'page_size_qs', 'page_size_hot_qs', 'page_size_una_qs');

		if (as_using_tags())
			$showoptions[] = 'page_size_tag_qs';

		$showoptions[] = '';

		if (as_using_tags())
			array_push($showoptions, 'page_size_tags', 'columns_tags');

		array_push($showoptions, 'page_size_users', 'columns_users', '');

		$searchmodules = as_load_modules_with('search', 'process_search');

		if (count($searchmodules))
			$showoptions[] = 'search_module';

		$showoptions[] = 'page_size_search';

		array_push($showoptions, '', 'admin/hotness_factors', 'hot_weight_q_age', 'hot_weight_a_age', 'hot_weight_reviews', 'hot_weight_thumbs');

		if (as_opt('do_count_q_views'))
			$showoptions[] = 'hot_weight_views';

		$formstyle = 'wide';

		break;

	case 'posting':
		$getoptions = as_get_options(array('tags_or_categories'));

		$subtitle = 'admin/posting_title';

		$showoptions = array('do_close_on_select', 'allow_close_songs', 'allow_close_own_songs', 'allow_self_review', 'allow_multi_reviews', 'follow_on_as', 'comment_on_qs', 'comment_on_as', 'allow_anonymous_naming', '');

		if (count(as_list_modules('editor')) > 1)
			array_push($showoptions, 'editor_for_qs', 'editor_for_as', 'editor_for_cs', '');

		array_push($showoptions, 'show_custom_post', 'custom_post', 'extra_field_active', 'extra_field_prompt', 'extra_field_display', 'extra_field_label', 'show_custom_review', 'custom_review', 'show_custom_comment', 'custom_comment', '');

		array_push($showoptions, 'min_len_q_title', 'max_len_q_title', 'min_len_q_content');

		if (as_using_tags())
			array_push($showoptions, 'min_num_q_tags', 'max_num_q_tags', 'tag_separator_comma');

		array_push($showoptions, 'min_len_a_content', 'min_len_c_content', 'notify_users_default');

		array_push($showoptions, '', 'block_bad_words', '', 'do_post_check_qs', 'match_post_check_qs', 'page_size_post_check_qs', '');

		if (as_using_tags())
			array_push($showoptions, 'do_example_tags', 'match_example_tags', 'do_complete_tags', 'page_size_post_tags');

		$formstyle = 'wide';

		$checkboxtodisplay = array(
			'allow_close_own_songs' => 'option_allow_close_songs',
			'editor_for_cs' => 'option_comment_on_qs || option_comment_on_as',
			'custom_post' => 'option_show_custom_post',
			'extra_field_prompt' => 'option_extra_field_active',
			'extra_field_display' => 'option_extra_field_active',
			'extra_field_label' => 'option_extra_field_active && option_extra_field_display',
			'extra_field_label_hidden' => '!option_extra_field_display',
			'extra_field_label_shown' => 'option_extra_field_display',
			'custom_review' => 'option_show_custom_review',
			'show_custom_comment' => 'option_comment_on_qs || option_comment_on_as',
			'custom_comment' => 'option_show_custom_comment && (option_comment_on_qs || option_comment_on_as)',
			'min_len_c_content' => 'option_comment_on_qs || option_comment_on_as',
			'match_post_check_qs' => 'option_do_post_check_qs',
			'page_size_post_check_qs' => 'option_do_post_check_qs',
			'match_example_tags' => 'option_do_example_tags',
			'page_size_post_tags' => 'option_do_example_tags || option_do_complete_tags',
		);
		break;

	case 'permissions':
		$subtitle = 'admin/permissions_title';

		$permitoptions = as_get_permit_options();

		$showoptions = array();
		$checkboxtodisplay = array();

		foreach ($permitoptions as $permitoption) {
			$showoptions[] = $permitoption;

			if ($permitoption == 'permit_view_q_page') {
				$showoptions[] = 'allow_view_q_bots';
				$checkboxtodisplay['allow_view_q_bots'] = 'option_permit_view_q_page<' . as_js(AS_PERMIT_ALL);

			} else {
				$showoptions[] = $permitoption . '_points';
				$checkboxtodisplay[$permitoption . '_points'] = '(option_' . $permitoption . '==' . as_js(AS_PERMIT_POINTS) .
					')||(option_' . $permitoption . '==' . as_js(AS_PERMIT_POINTS_CONFIRMED) . ')||(option_' . $permitoption . '==' . as_js(AS_PERMIT_APPROVED_POINTS) . ')';
			}
		}

		$formstyle = 'wide';
		break;

	case 'feeds':
		$subtitle = 'admin/feeds_title';

		$showoptions = array('feed_for_songs', 'feed_for_qa', 'feed_for_activity');

		array_push($showoptions, 'feed_for_hot', 'feed_for_unreviewed');

		if (as_using_tags())
			$showoptions[] = 'feed_for_tag_qs';

		if (as_using_categories())
			$showoptions[] = 'feed_per_category';

		array_push($showoptions, 'feed_for_search', '', 'feed_number_items', 'feed_full_text');

		$formstyle = 'wide';

		$checkboxtodisplay = array(
			'feed_per_category' => 'option_feed_for_qa || option_feed_for_songs || option_feed_for_unreviewed || option_feed_for_activity',
		);
		break;

	case 'spam':
		$subtitle = 'admin/spam_title';

		$showoptions = array();

		$getoptions = as_get_options(array('feedback_enabled', 'permit_post_q', 'permit_post_a', 'permit_post_c'));

		if (!AS_FINAL_EXTERNAL_USERS)
			array_push($showoptions, 'confirm_user_emails', 'confirm_user_required', 'moderate_users', '');

		$captchamodules = as_list_modules('captcha');

		if (count($captchamodules)) {
			if (!AS_FINAL_EXTERNAL_USERS)
				array_push($showoptions, 'captcha_on_signup', 'captcha_on_reset_password');

			if ($maxpermitpost > AS_PERMIT_USERS)
				$showoptions[] = 'captcha_on_anon_post';

			if ($maxpermitpost > AS_PERMIT_APPROVED)
				$showoptions[] = 'captcha_on_unapproved';

			if ($maxpermitpost > AS_PERMIT_CONFIRMED)
				$showoptions[] = 'captcha_on_unconfirmed';

			if ($getoptions['feedback_enabled'])
				$showoptions[] = 'captcha_on_feedback';

			$showoptions[] = 'captcha_module';
		}

		$showoptions[] = '';

		if ($maxpermitpost > AS_PERMIT_USERS)
			$showoptions[] = 'moderate_anon_post';

		if ($maxpermitpost > AS_PERMIT_APPROVED)
			$showoptions[] = 'moderate_unapproved';

		if ($maxpermitpost > AS_PERMIT_CONFIRMED)
			$showoptions[] = 'moderate_unconfirmed';

		if ($maxpermitpost > AS_PERMIT_EXPERTS)
			array_push($showoptions, 'moderate_by_points', 'moderate_points_limit', 'moderate_edited_again', 'moderate_notify_admin', 'moderate_update_time', '');

		array_push($showoptions, 'flagging_of_posts', 'flagging_notify_first', 'flagging_notify_every', 'flagging_hide_after', '');

		array_push($showoptions, 'block_ips_write', '');

		if (!AS_FINAL_EXTERNAL_USERS)
			array_push($showoptions, 'max_rate_ip_signups', 'max_rate_ip_signins', '');

		array_push($showoptions, 'max_rate_ip_qs', 'max_rate_user_qs', 'max_rate_ip_as', 'max_rate_user_as');

		if (as_opt('comment_on_qs') || as_opt('comment_on_as'))
			array_push($showoptions, 'max_rate_ip_cs', 'max_rate_user_cs');

		$showoptions[] = '';

		if (as_opt('thumbing_on_qs') || as_opt('thumbing_on_as') || as_opt('thumbing_on_cs'))
			array_push($showoptions, 'max_rate_ip_thumbs', 'max_rate_user_thumbs');

		array_push($showoptions, 'max_rate_ip_flags', 'max_rate_user_flags', 'max_rate_ip_uploads', 'max_rate_user_uploads');

		if (as_opt('allow_private_messages') || as_opt('allow_user_walls'))
			array_push($showoptions, 'max_rate_ip_messages', 'max_rate_user_messages');

		$formstyle = 'wide';

		$checkboxtodisplay = array(
			'confirm_user_required' => 'option_confirm_user_emails',
			'captcha_on_unapproved' => 'option_moderate_users',
			'captcha_on_unconfirmed' => 'option_confirm_user_emails && !(option_moderate_users && option_captcha_on_unapproved)',
			'captcha_module' => 'option_captcha_on_signup || option_captcha_on_anon_post || (option_confirm_user_emails && option_captcha_on_unconfirmed) || (option_moderate_users && option_captcha_on_unapproved) || option_captcha_on_reset_password || option_captcha_on_feedback',
			'moderate_unapproved' => 'option_moderate_users',
			'moderate_unconfirmed' => 'option_confirm_user_emails && !(option_moderate_users && option_moderate_unapproved)',
			'moderate_points_limit' => 'option_moderate_by_points',
			'moderate_points_label_off' => '!option_moderate_by_points',
			'moderate_points_label_on' => 'option_moderate_by_points',
			'moderate_edited_again' => 'option_moderate_anon_post || (option_confirm_user_emails && option_moderate_unconfirmed) || (option_moderate_users && option_moderate_unapproved) || option_moderate_by_points',
			'flagging_hide_after' => 'option_flagging_of_posts',
			'flagging_notify_every' => 'option_flagging_of_posts',
			'flagging_notify_first' => 'option_flagging_of_posts',
			'max_rate_ip_flags' => 'option_flagging_of_posts',
			'max_rate_user_flags' => 'option_flagging_of_posts',
		);

		$checkboxtodisplay['moderate_notify_admin'] = $checkboxtodisplay['moderate_edited_again'];
		$checkboxtodisplay['moderate_update_time'] = $checkboxtodisplay['moderate_edited_again'];
		break;

	case 'caching':
		$subtitle = 'admin/caching_title';
		$formstyle = 'wide';

		$showoptions = array('caching_enabled', 'caching_driver', 'caching_q_start', 'caching_q_time', 'caching_catwidget_time');

		break;

	case 'mailing':
		require_once AS_INCLUDE_DIR . 'app/mailing.php';

		$subtitle = 'admin/mailing_title';

		$showoptions = array('mailing_enabled', 'mailing_from_name', 'mailing_from_email', 'mailing_subject', 'mailing_body', 'mailing_per_minute');
		break;

	default:
		$pagemodules = as_load_modules_with('page', 'match_request');
		$request = as_request();

		foreach ($pagemodules as $pagemodule) {
			if ($pagemodule->match_request($request))
				return $pagemodule->process_request($request);
		}

		return include AS_INCLUDE_DIR . 'as-page-not-found.php';
		break;
}


// Filter out blanks to get list of valid options

$getoptions = array();
foreach ($showoptions as $optionname) {
	if (strlen($optionname) && (strpos($optionname, '/') === false)) // empties represent spacers in forms
		$getoptions[] = $optionname;
}


// Process user actions

$errors = array();

$recalchotness = false;
$startmailing = false;
$securityexpired = false;

$formokhtml = null;

// If the post_max_size is exceeded then the $_POST array is empty so no field processing can be done
if (as_post_limit_exceeded())
	$errors['avatar_default_show'] = as_lang('main/file_upload_limit_exceeded');
else {
	if (as_clicked('doresetoptions')) {
		if (!as_check_form_security_code('admin/' . $adminsection, as_post_text('code')))
			$securityexpired = true;

		else {
			as_reset_options($getoptions);
			$formokhtml = as_lang_html('admin/options_reset');
		}
	} elseif (as_clicked('dosaveoptions')) {
		if (!as_check_form_security_code('admin/' . $adminsection, as_post_text('code')))
			$securityexpired = true;

		else {
			foreach ($getoptions as $optionname) {
				$optionvalue = as_post_text('option_' . $optionname);

				if (@$optiontype[$optionname] == 'number' || @$optiontype[$optionname] == 'checkbox' ||
					(@$optiontype[$optionname] == 'number-blank' && strlen($optionvalue))
				)
					$optionvalue = (int)$optionvalue;

				if (isset($optionmaximum[$optionname]))
					$optionvalue = min($optionmaximum[$optionname], $optionvalue);

				if (isset($optionminimum[$optionname]))
					$optionvalue = max($optionminimum[$optionname], $optionvalue);

				switch ($optionname) {
					case 'site_url':
						if (substr($optionvalue, -1) != '/') // seems to be a very common mistake and will mess up URLs
							$optionvalue .= '/';
						break;

					case 'hot_weight_views':
					case 'hot_weight_reviews':
					case 'hot_weight_thumbs':
					case 'hot_weight_q_age':
					case 'hot_weight_a_age':
						if (as_opt($optionname) != $optionvalue)
							$recalchotness = true;
						break;

					case 'block_ips_write':
						require_once AS_INCLUDE_DIR . 'app/limits.php';
						$optionvalue = implode(' , ', as_block_ips_explode($optionvalue));
						break;

					case 'block_bad_words':
					case 'block_bad_usernames':
						require_once AS_INCLUDE_DIR . 'util/string.php';
						$optionvalue = implode(' , ', as_block_words_explode($optionvalue));
						break;
				}

				as_set_option($optionname, $optionvalue);
			}

			$formokhtml = as_lang_html('admin/options_saved');

			// Uploading default avatar
			if (is_array(@$_FILES['avatar_default_file'])) {
				$avatarfileerror = $_FILES['avatar_default_file']['error'];

				// Note if $_FILES['avatar_default_file']['error'] === 1 then upload_max_filesize has been exceeded
				if ($avatarfileerror === 1) {
					$errors['avatar_default_show'] = as_lang('main/file_upload_limit_exceeded');
				} elseif ($avatarfileerror === 0 && $_FILES['avatar_default_file']['size'] > 0) {
					require_once AS_INCLUDE_DIR . 'util/image.php';

					$oldblobid = as_opt('avatar_default_blobid');

					$toobig = as_image_file_too_big($_FILES['avatar_default_file']['tmp_name'], as_opt('avatar_store_size'));

					if ($toobig) {
						$errors['avatar_default_show'] = as_lang_sub('main/image_too_big_x_pc', (int)($toobig * 100));
					} else {
						$imagedata = as_image_constrain_data(file_get_contents($_FILES['avatar_default_file']['tmp_name']), $width, $height, as_opt('avatar_store_size'));

						if (isset($imagedata)) {
							require_once AS_INCLUDE_DIR . 'app/blobs.php';

							$newblobid = as_create_blob($imagedata, 'jpeg');

							if (isset($newblobid)) {
								as_set_option('avatar_default_blobid', $newblobid);
								as_set_option('avatar_default_width', $width);
								as_set_option('avatar_default_height', $height);
								as_set_option('avatar_default_show', 1);
							}

							if (strlen($oldblobid))
								as_delete_blob($oldblobid);
						} else {
							$errors['avatar_default_show'] = as_lang_sub('main/image_not_read', implode(', ', as_gd_image_formats()));
						}
					}
				}
			}
		}
	}
}


// Mailings management

if ($adminsection == 'mailing') {
	if (as_clicked('domailingtest') || as_clicked('domailingstart') || as_clicked('domailingresume') || as_clicked('domailingcancel')) {
		if (!as_check_form_security_code('admin/' . $adminsection, as_post_text('code'))) {
			$securityexpired = true;
		} else {
			if (as_clicked('domailingtest')) {
				$email = as_get_logged_in_email();

				if (as_mailing_send_one(as_get_logged_in_userid(), as_get_logged_in_handle(), $email, as_get_logged_in_user_field('emailcode')))
					$formokhtml = as_lang_html_sub('admin/test_sent_to_x', as_html($email));
				else
					$formokhtml = as_lang_html('main/general_error');
			}

			if (as_clicked('domailingstart')) {
				as_mailing_start();
				$startmailing = true;
			}

			if (as_clicked('domailingresume'))
				$startmailing = true;

			if (as_clicked('domailingcancel'))
				as_mailing_stop();
		}
	}

	$mailingprogress = as_mailing_progress_message();

	if (isset($mailingprogress)) {
		$formokhtml = as_html($mailingprogress);

		$checkboxtodisplay = array(
			'mailing_enabled' => '0',
		);

	} else {
		$checkboxtodisplay = array(
			'mailing_from_name' => 'option_mailing_enabled',
			'mailing_from_email' => 'option_mailing_enabled',
			'mailing_subject' => 'option_mailing_enabled',
			'mailing_body' => 'option_mailing_enabled',
			'mailing_per_minute' => 'option_mailing_enabled',
			'domailingtest' => 'option_mailing_enabled',
			'domailingstart' => 'option_mailing_enabled',
		);
	}
}


// Get the actual options

$options = as_get_options($getoptions);


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/admin_title') . ' - ' . as_lang_html($subtitle);
$as_content['error'] = $securityexpired ? as_lang_html('admin/form_security_expired') : as_admin_page_error();

$as_content['script_rel'][] = 'as-content/as-admin.js?' . AS_VERSION;

$as_content['form'] = array(
	'ok' => $formokhtml,

	'tags' => 'method="post" action="' . as_self_html() . '" name="admin_form" onsubmit="document.forms.admin_form.has_js.value=1; return true;"',

	'style' => $formstyle,

	'fields' => array(),

	'buttons' => array(
		'save' => array(
			'tags' => 'id="dosaveoptions"',
			'label' => as_lang_html('admin/save_options_button'),
		),

		'reset' => array(
			'tags' => 'name="doresetoptions" onclick="return confirm(' . as_js(as_lang_html('admin/reset_options_confirm')) . ');"',
			'label' => as_lang_html('admin/reset_options_button'),
		),
	),

	'hidden' => array(
		'dosaveoptions' => '1', // for IE
		'has_js' => '0',
		'code' => as_get_form_security_code('admin/' . $adminsection),
	),
);

if ($recalchotness) {
	$as_content['form']['ok'] = '<span id="recalc_ok"></span>';
	$as_content['form']['hidden']['code_recalc'] = as_get_form_security_code('admin/recalc');

	$as_content['script_var']['as_warning_recalc'] = as_lang('admin/stop_recalc_warning');

	$as_content['script_onloads'][] = array(
		"as_recalc_click('dorecountposts', document.getElementById('dosaveoptions'), null, 'recalc_ok');"
	);

} elseif ($startmailing) {
	if (as_post_text('has_js')) {
		$as_content['form']['ok'] = '<span id="mailing_ok">' . as_html($mailingprogress) . '</span>';

		$as_content['script_onloads'][] = array(
			"as_mailing_start('mailing_ok', 'domailingpause');"
		);

	} else { // rudimentary non-Javascript version of mass mailing loop
		echo '<code>';

		while (true) {
			as_mailing_perform_step();

			$message = as_mailing_progress_message();

			if (!isset($message))
				break;

			echo as_html($message) . str_repeat('    ', 1024) . "<br>\n";

			flush();
			sleep(1);
		}

		echo as_lang_html('admin/mailing_complete').'</code><p><a href="'.as_path_html('admin/mailing').'">'.as_lang_html('admin/admin_title').' - '.as_lang_html('admin/mailing_title').'</a>';

		as_exit();
	}
}


function as_optionfield_make_select(&$optionfield, $options, $value, $default)
{
	$optionfield['type'] = 'select';
	$optionfield['options'] = $options;
	$optionfield['value'] = isset($options[as_html($value)]) ? $options[as_html($value)] : @$options[$default];
}

$indented = false;

foreach ($showoptions as $optionname) {
	if (empty($optionname)) {
		$indented = false;

		$as_content['form']['fields'][] = array(
			'type' => 'blank'
		);

	} elseif (strpos($optionname, '/') !== false) {
		$as_content['form']['fields'][] = array(
			'type' => 'static',
			'label' => as_lang_html($optionname),
		);

		$indented = true;

	} else {
		$type = @$optiontype[$optionname];
		if ($type == 'number-blank')
			$type = 'number';

		$value = $options[$optionname];

		$optionfield = array(
			'id' => $optionname,
			'label' => ($indented ? '&ndash; ' : '') . as_lang_html('options/' . $optionname),
			'tags' => 'name="option_' . $optionname . '" id="option_' . $optionname . '"',
			'value' => as_html($value),
			'type' => $type,
			'error' => as_html(@$errors[$optionname]),
		);

		if (isset($optionmaximum[$optionname]))
			$optionfield['note'] = as_lang_html_sub('admin/maximum_x', $optionmaximum[$optionname]);

		$feedrequest = null;
		$feedisexample = false;

		switch ($optionname) { // special treatment for certain options
			case 'site_language':
				require_once AS_INCLUDE_DIR . 'util/string.php';

				as_optionfield_make_select($optionfield, as_admin_language_options(), $value, '');

				$optionfield['suffix'] = strtr(as_lang_html('admin/check_language_suffix'), array(
					'^1' => '<a href="' . as_html(as_path_to_root() . 'as-include/as-check-lang.php') . '">',
					'^2' => '</a>',
				));

				if (!as_has_multibyte())
					$optionfield['error'] = as_lang_html('admin/no_multibyte');
				break;

			case 'neat_urls':
				$neatoptions = array();

				$rawoptions = array(
					AS_URL_FORMAT_NEAT,
					AS_URL_FORMAT_INDEX,
					AS_URL_FORMAT_PARAM,
					AS_URL_FORMAT_PARAMS,
					AS_URL_FORMAT_SAFEST,
				);

				foreach ($rawoptions as $rawoption) {
					$neatoptions[$rawoption] =
						'<iframe src="' . as_path_html('url/test/' . AS_URL_TEST_STRING, array('dummy' => '', 'param' => AS_URL_TEST_STRING), null, $rawoption) . '" width="20" height="16" style="vertical-align:middle; border:0" scrolling="no"></iframe>&nbsp;' .
						'<small>' .
						as_html(urldecode(as_path('123/why-do-birds-sing', null, '/', $rawoption))) .
						(($rawoption == AS_URL_FORMAT_NEAT) ? strtr(as_lang_html('admin/neat_urls_note'), array(
							'^1' => '<a href="http://github.com/vsongbookhtaccess.php" target="_blank">',
							'^2' => '</a>',
						)) : '') .
						'</small>';
				}

				as_optionfield_make_select($optionfield, $neatoptions, $value, AS_URL_FORMAT_SAFEST);

				$optionfield['type'] = 'select-radio';
				$optionfield['note'] = as_lang_html_sub('admin/url_format_note', '<span style=" ' . as_admin_url_test_html() . '/span>');
				break;

			case 'site_theme':
			case 'site_theme_mobile':
				$themeoptions = as_admin_theme_options();
				if (!isset($themeoptions[$value]))
					$value = 'Classic'; // check here because we also need $value for as_addon_metadata()

				as_optionfield_make_select($optionfield, $themeoptions, $value, 'Classic');

				$metadataUtil = new APS_Util_Metadata();
				$themedirectory = AS_THEME_DIR . $value;
				$metadata = $metadataUtil->fetchFromAddonPath($themedirectory);
				if (empty($metadata)) {
					// limit theme parsing to first 8kB
					$contents = @file_get_contents($themedirectory . '/as-styles.css', false, null, 0, 8192);
					$metadata = as_addon_metadata($contents, 'Theme');
				}

				if (strlen(@$metadata['version']))
					$namehtml = 'v' . as_html($metadata['version']);
				else
					$namehtml = '';

				if (strlen(@$metadata['uri'])) {
					if (!strlen($namehtml))
						$namehtml = as_html($value);

					$namehtml = '<a href="' . as_html($metadata['uri']) . '">' . $namehtml . '</a>';
				}

				$authorhtml = '';
				if (strlen(@$metadata['author'])) {
					$authorhtml = as_html($metadata['author']);

					if (strlen(@$metadata['author_uri']))
						$authorhtml = '<a href="' . as_html($metadata['author_uri']) . '">' . $authorhtml . '</a>';

					$authorhtml = as_lang_html_sub('main/by_x', $authorhtml);

				}

				$updatehtml = '';
				if (strlen(@$metadata['version']) && strlen(@$metadata['update_uri'])) {
					$elementid = 'version_check_' . $optionname;

					$updatehtml = '(<span id="' . $elementid . '">...</span>)';

					$as_content['script_onloads'][] = array(
						"as_version_check(" . as_js($metadata['update_uri']) . ", " . as_js($metadata['version'], true) . ", " . as_js($elementid) . ", false);"
					);

				}

				$optionfield['suffix'] = $namehtml . ' ' . $authorhtml . ' ' . $updatehtml;
				break;

			case 'site_text_direction':
				$directions = array('ltr' => 'LTR', 'rtl' => 'RTL');
				as_optionfield_make_select($optionfield, $directions, $value, 'ltr');
				break;

			case 'tags_or_categories':
				as_optionfield_make_select($optionfield, array(
					'' => as_lang_html('admin/no_classification'),
					't' => as_lang_html('admin/tags'),
					'c' => as_lang_html('admin/categories'),
					'tc' => as_lang_html('admin/tags_and_categories'),
				), $value, 'tc');

				$optionfield['error'] = '';

				if (as_opt('cache_tagcount') && !as_using_tags())
					$optionfield['error'] .= as_lang_html('admin/tags_not_shown') . ' ';

				if (!as_using_categories()) {
					foreach ($categories as $category) {
						if ($category['qcount']) {
							$optionfield['error'] .= as_lang_html('admin/categories_not_shown');
							break;
						}
					}
				}
				break;

			case 'smtp_secure':
				as_optionfield_make_select($optionfield, array(
					'' => as_lang_html('options/smtp_secure_none'),
					'ssl' => 'SSL',
					'tls' => 'TLS',
				), $value, '');
				break;

			case 'custom_sidebar':
			case 'custom_sidepanel':
			case 'custom_header':
			case 'custom_footer':
			case 'custom_in_head':
			case 'home_description':
				unset($optionfield['label']);
				$optionfield['rows'] = 6;
				break;

			case 'custom_home_content':
				$optionfield['rows'] = 16;
				break;

			case 'show_custom_signup':
			case 'show_signup_terms':
			case 'show_custom_welcome':
			case 'show_notice_welcome':
			case 'show_notice_visitor':
				$optionfield['style'] = 'tall';
				break;

			case 'custom_signup':
			case 'signup_terms':
			case 'custom_welcome':
			case 'notice_welcome':
			case 'notice_visitor':
				unset($optionfield['label']);
				$optionfield['style'] = 'tall';
				$optionfield['rows'] = 3;
				break;

			case 'avatar_allow_gravatar':
				$optionfield['label'] = strtr($optionfield['label'], array(
					'^1' => '<a href="http://www.gravatar.com/" target="_blank">',
					'^2' => '</a>',
				));

				if (!as_has_gd_image()) {
					$optionfield['style'] = 'tall';
					$optionfield['error'] = as_lang_html('admin/no_image_gd');
				}
				break;

			case 'avatar_store_size':
			case 'avatar_profile_size':
			case 'avatar_users_size':
			case 'avatar_q_page_q_size':
			case 'avatar_q_page_a_size':
			case 'avatar_q_page_c_size':
			case 'avatar_s_list_size':
			case 'avatar_message_list_size':
				$optionfield['note'] = as_lang_html('admin/pixels');
				break;

			case 'avatar_default_show':
				$as_content['form']['tags'] .= 'enctype="multipart/form-data"';
				$optionfield['label'] .= ' <span style="margin:2px 0; display:inline-block;">' .
					as_get_avatar_blob_html(as_opt('avatar_default_blobid'), as_opt('avatar_default_width'), as_opt('avatar_default_height'), 32) .
					'</span> <input name="avatar_default_file" type="file" style="width:16em;">';
				break;

			case 'logo_width':
			case 'logo_height':
				$optionfield['suffix'] = as_lang_html('admin/pixels');
				break;

			case 'pages_prev_next':
				as_optionfield_make_select($optionfield, array(0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5), $value, 3);
				break;

			case 'columns_tags':
			case 'columns_users':
				as_optionfield_make_select($optionfield, array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5), $value, 2);
				break;

			case 'min_len_q_title':
			case 'q_urls_title_length':
			case 'min_len_q_content':
			case 'min_len_a_content':
			case 'min_len_c_content':
				$optionfield['note'] = as_lang_html('admin/characters');
				break;

			case 'recalc_hotness_q_view':
				$optionfield['note'] = '<span class="as-form-wide-help" title="' . as_lang_html('admin/recalc_hotness_q_view_note') . '">?</span>';
				break;

			case 'min_num_q_tags':
			case 'max_num_q_tags':
				$optionfield['note'] = as_lang_html_sub('main/x_tags', ''); // this to avoid language checking error: a_lang('main/1_tag')
				break;

			case 'show_full_date_days':
				$optionfield['note'] = as_lang_html_sub('main/x_days', '');
				break;

			case 'sort_reviews_by':
				as_optionfield_make_select($optionfield, array(
					'created' => as_lang_html('options/sort_time'),
					'thumbs' => as_lang_html('options/sort_thumbs'),
				), $value, 'created');
				break;

			case 'page_size_q_as':
				$optionfield['note'] = as_lang_html_sub('main/x_reviews', '');
				break;

			case 'show_a_form_immediate':
				as_optionfield_make_select($optionfield, array(
					'always' => as_lang_html('options/show_always'),
					'if_no_as' => as_lang_html('options/show_if_no_as'),
					'never' => as_lang_html('options/show_never'),
				), $value, 'if_no_as');
				break;

			case 'show_fewer_cs_from':
			case 'show_fewer_cs_count':
				$optionfield['note'] = as_lang_html_sub('main/x_comments', '');
				break;

			case 'match_related_qs':
			case 'match_post_check_qs':
			case 'match_example_tags':
				as_optionfield_make_select($optionfield, as_admin_match_options(), $value, 3);
				break;

			case 'block_bad_words':
			case 'block_bad_usernames':
				$optionfield['style'] = 'tall';
				$optionfield['rows'] = 4;
				$optionfield['note'] = as_lang_html('admin/block_words_note');
				break;

			case 'editor_for_qs':
			case 'editor_for_as':
			case 'editor_for_cs':
				$editors = as_list_modules('editor');

				$selectoptions = array();

				foreach ($editors as $editor) {
					$selectoptions[as_html($editor)] = strlen($editor) ? as_html($editor) : as_lang_html('admin/basic_editor');

					if ($editor == $value) {
						$module = as_load_module('editor', $editor);

						if (method_exists($module, 'admin_form')) {
							$optionfield['note'] = '<a href="' . as_admin_module_options_path('editor', $editor) . '">' . as_lang_html('admin/options') . '</a>';
						}
					}
				}

				as_optionfield_make_select($optionfield, $selectoptions, $value, '');
				break;

			case 'show_custom_post':
			case 'extra_field_active':
			case 'show_custom_review':
			case 'show_custom_comment':
				$optionfield['style'] = 'tall';
				break;

			case 'custom_post':
			case 'custom_review':
			case 'custom_comment':
				$optionfield['style'] = 'tall';
				unset($optionfield['label']);
				$optionfield['rows'] = 3;
				break;

			case 'extra_field_display':
				$optionfield['style'] = 'tall';
				$optionfield['label'] = '<span id="extra_field_label_hidden" style="display:none;">' . $optionfield['label'] . '</span><span id="extra_field_label_shown">' . as_lang_html('options/extra_field_display_label') . '</span>';
				break;

			case 'extra_field_prompt':
			case 'extra_field_label':
				$optionfield['style'] = 'tall';
				unset($optionfield['label']);
				break;

			case 'search_module':
				foreach ($searchmodules as $modulename => $module) {
					$selectoptions[as_html($modulename)] = strlen($modulename) ? as_html($modulename) : as_lang_html('options/option_default');

					if ($modulename == $value && method_exists($module, 'admin_form')) {
						$optionfield['note'] = '<a href="' . as_admin_module_options_path('search', $modulename) . '">' . as_lang_html('admin/options') . '</a>';
					}
				}

				as_optionfield_make_select($optionfield, $selectoptions, $value, '');
				break;

			case 'hot_weight_q_age':
			case 'hot_weight_a_age':
			case 'hot_weight_reviews':
			case 'hot_weight_thumbs':
			case 'hot_weight_views':
				$optionfield['note'] = '/ 100';
				break;

			case 'moderate_by_points':
				$optionfield['label'] = '<span id="moderate_points_label_off" style="display:none;">' . $optionfield['label'] . '</span><span id="moderate_points_label_on">' . as_lang_html('options/moderate_points_limit') . '</span>';
				break;

			case 'moderate_points_limit':
				unset($optionfield['label']);
				$optionfield['note'] = as_lang_html('admin/points');
				break;

			case 'flagging_hide_after':
			case 'flagging_notify_every':
			case 'flagging_notify_first':
				$optionfield['note'] = as_lang_html_sub('main/x_flags', '');
				break;

			case 'block_ips_write':
				$optionfield['style'] = 'tall';
				$optionfield['rows'] = 4;
				$optionfield['note'] = as_lang_html('admin/block_ips_note');
				break;

			case 'allow_view_q_bots':
				$optionfield['note'] = $optionfield['label'];
				unset($optionfield['label']);
				break;

			case 'permit_view_q_page':
			case 'permit_view_new_users_page':
			case 'permit_view_special_users_page':
			case 'permit_post_q':
			case 'permit_post_a':
			case 'permit_post_c':
			case 'permit_thumb_q':
			case 'permit_thumb_a':
			case 'permit_thumb_c':
			case 'permit_thumb_down':
			case 'permit_edit_q':
			case 'permit_retag_cat':
			case 'permit_edit_a':
			case 'permit_edit_c':
			case 'permit_edit_silent':
			case 'permit_flag':
			case 'permit_close_q':
			case 'permit_select_a':
			case 'permit_hide_show':
			case 'permit_moderate':
			case 'permit_delete_hidden':
			case 'permit_anon_view_ips':
			case 'permit_view_thumbers_flaggers':
			case 'permit_post_wall':
				$dopoints = true;

				if ($optionname == 'permit_retag_cat')
					$optionfield['label'] = as_lang_html(as_using_categories() ? 'profile/permit_recat' : 'profile/permit_retag') . ':';
				else
					$optionfield['label'] = as_lang_html('profile/' . $optionname) . ':';

				if (in_array($optionname, array('permit_view_q_page', 'permit_view_new_users_page', 'permit_view_special_users_page', 'permit_post_q', 'permit_post_a', 'permit_post_c', 'permit_anon_view_ips')))
					$widest = AS_PERMIT_ALL;
				elseif ($optionname == 'permit_close_q' || $optionname == 'permit_select_a' || $optionname == 'permit_moderate' || $optionname == 'permit_hide_show')
					$widest = AS_PERMIT_POINTS;
				elseif ($optionname == 'permit_delete_hidden')
					$widest = AS_PERMIT_EDITORS;
				elseif ($optionname == 'permit_view_thumbers_flaggers' || $optionname == 'permit_edit_silent')
					$widest = AS_PERMIT_EXPERTS;
				else
					$widest = AS_PERMIT_USERS;

				if ($optionname == 'permit_view_q_page') {
					$narrowest = AS_PERMIT_APPROVED;
					$dopoints = false;
				} elseif ($optionname == 'permit_view_special_users_page' || $optionname == 'permit_view_new_users_page') {
					$narrowest = AS_PERMIT_SUPERS;
					$dopoints = false;
				} elseif ($optionname == 'permit_edit_c' || $optionname == 'permit_close_q' || $optionname == 'permit_select_a' || $optionname == 'permit_moderate' || $optionname == 'permit_hide_show' || $optionname == 'permit_anon_view_ips')
					$narrowest = AS_PERMIT_MODERATORS;
				elseif ($optionname == 'permit_post_c' || $optionname == 'permit_edit_q' || $optionname == 'permit_retag_cat' || $optionname == 'permit_edit_a' || $optionname == 'permit_flag')
					$narrowest = AS_PERMIT_EDITORS;
				elseif ($optionname == 'permit_thumb_q' || $optionname == 'permit_thumb_a' || $optionname == 'permit_thumb_c' || $optionname == 'permit_post_wall')
					$narrowest = AS_PERMIT_APPROVED_POINTS;
				elseif ($optionname == 'permit_delete_hidden' || $optionname == 'permit_edit_silent')
					$narrowest = AS_PERMIT_ADMINS;
				elseif ($optionname == 'permit_view_thumbers_flaggers')
					$narrowest = AS_PERMIT_SUPERS;
				else
					$narrowest = AS_PERMIT_EXPERTS;

				$permitoptions = as_admin_permit_options($widest, $narrowest, (!AS_FINAL_EXTERNAL_USERS) && as_opt('confirm_user_emails'), $dopoints);

				if (count($permitoptions) > 1) {
					as_optionfield_make_select($optionfield, $permitoptions, $value,
						($value == AS_PERMIT_CONFIRMED) ? AS_PERMIT_USERS : min(array_keys($permitoptions)));
				} else {
					$optionfield['type'] = 'static';
					$optionfield['value'] = reset($permitoptions);
				}
				break;

			case 'permit_post_q_points':
			case 'permit_post_a_points':
			case 'permit_post_c_points':
			case 'permit_thumb_q_points':
			case 'permit_thumb_a_points':
			case 'permit_thumb_c_points':
			case 'permit_thumb_down_points':
			case 'permit_flag_points':
			case 'permit_edit_q_points':
			case 'permit_retag_cat_points':
			case 'permit_edit_a_points':
			case 'permit_edit_c_points':
			case 'permit_close_q_points':
			case 'permit_select_a_points':
			case 'permit_hide_show_points':
			case 'permit_moderate_points':
			case 'permit_delete_hidden_points':
			case 'permit_anon_view_ips_points':
			case 'permit_post_wall_points':
				unset($optionfield['label']);
				$optionfield['type'] = 'number';
				$optionfield['prefix'] = as_lang_html('admin/users_must_have') . '&nbsp;';
				$optionfield['note'] = as_lang_html('admin/points');
				break;

			case 'feed_for_qa':
				$feedrequest = 'as';
				break;

			case 'feed_for_songs':
				$feedrequest = 'songs';
				break;

			case 'feed_for_hot':
				$feedrequest = 'hot';
				break;

			case 'feed_for_unreviewed':
				$feedrequest = 'unreviewed';
				break;

			case 'feed_for_activity':
				$feedrequest = 'activity';
				break;

			case 'feed_per_category':
				if (count($categories)) {
					$category = reset($categories);
					$categoryslug = $category['tags'];

				} else
					$categoryslug = 'example-category';

				if (as_opt('feed_for_qa'))
					$feedrequest = 'as';
				elseif (as_opt('feed_for_songs'))
					$feedrequest = 'songs';
				else
					$feedrequest = 'activity';

				$feedrequest .= '/' . $categoryslug;
				$feedisexample = true;
				break;

			case 'feed_for_tag_qs':
				$populartags = as_db_select_with_pending(as_db_popular_tags_selectspec(0, 1));

				if (count($populartags)) {
					reset($populartags);
					$feedrequest = 'tag/' . key($populartags);
				} else
					$feedrequest = 'tag/singing';

				$feedisexample = true;
				break;

			case 'feed_for_search':
				$feedrequest = 'search/why do birds sing';
				$feedisexample = true;
				break;

			case 'moderate_users':
				$optionfield['note'] = '<a href="' . as_path_html('admin/users', null, null, null, 'profile_fields') . '">' . as_lang_html('admin/registration_fields') . '</a>';
				break;

			case 'captcha_module':
				$captchaoptions = array();

				foreach ($captchamodules as $modulename) {
					$captchaoptions[as_html($modulename)] = as_html($modulename);

					if ($modulename == $value) {
						$module = as_load_module('captcha', $modulename);

						if (method_exists($module, 'admin_form')) {
							$optionfield['note'] = '<a href="' . as_admin_module_options_path('captcha', $modulename) . '">' . as_lang_html('admin/options') . '</a>';
						}
					}
				}

				as_optionfield_make_select($optionfield, $captchaoptions, $value, '');
				break;

			case 'moderate_update_time':
				as_optionfield_make_select($optionfield, array(
					'0' => as_lang_html('options/time_written'),
					'1' => as_lang_html('options/time_approved'),
				), $value, '0');
				break;

			case 'max_rate_ip_as':
			case 'max_rate_ip_cs':
			case 'max_rate_ip_flags':
			case 'max_rate_ip_signins':
			case 'max_rate_ip_messages':
			case 'max_rate_ip_qs':
			case 'max_rate_ip_signups':
			case 'max_rate_ip_uploads':
			case 'max_rate_ip_thumbs':
				$optionfield['note'] = as_lang_html('admin/per_ip_hour');
				break;

			case 'max_rate_user_as':
			case 'max_rate_user_cs':
			case 'max_rate_user_flags':
			case 'max_rate_user_messages':
			case 'max_rate_user_qs':
			case 'max_rate_user_uploads':
			case 'max_rate_user_thumbs':
				unset($optionfield['label']);
				$optionfield['note'] = as_lang_html('admin/per_user_hour');
				break;

			case 'mailing_per_minute':
				$optionfield['suffix'] = as_lang_html('admin/emails_per_minute');
				break;

			case 'caching_driver':
				as_optionfield_make_select($optionfield, array(
					'filesystem' => as_lang_html('options/caching_filesystem'),
					'memcached' => as_lang_html('options/caching_memcached'),
				), $value, 'filesystem');
				break;

			case 'caching_q_time':
			case 'caching_qlist_time':
			case 'caching_catwidget_time':
				$optionfield['note'] = as_lang_html_sub('main/x_minutes', '');
				break;
			case 'caching_q_start':
				$optionfield['note'] = as_lang_html_sub('main/x_days', '');
				break;
		}

		if (isset($feedrequest) && $value) {
			$optionfield['note'] = '<a href="' . as_path_html(as_feed_request($feedrequest)) . '">' . as_lang_html($feedisexample ? 'admin/feed_link_example' : 'admin/feed_link') . '</a>';
		}

		$as_content['form']['fields'][$optionname] = $optionfield;
	}
}


// Extra items for specific pages

switch ($adminsection) {
	case 'users':
		require_once AS_INCLUDE_DIR . 'app/format.php';

		if (!AS_FINAL_EXTERNAL_USERS) {
			$userfields = as_db_single_select(as_db_userfields_selectspec());

			$listhtml = '';

			foreach ($userfields as $userfield) {
				$listhtml .= '<li><b>' . as_html(as_user_userfield_label($userfield)) . '</b>';

				$listhtml .= strtr(as_lang_html('admin/edit_field'), array(
					'^1' => '<a href="' . as_path_html('admin/userfields', array('edit' => $userfield['fieldid'])) . '">',
					'^2' => '</a>',
				));

				$listhtml .= '</li>';
			}

			$listhtml .= '<li><b><a href="' . as_path_html('admin/userfields') . '">' . as_lang_html('admin/add_new_field') . '</a></b></li>';

			$as_content['form']['fields'][] = array('type' => 'blank');

			$as_content['form']['fields']['userfields'] = array(
				'label' => as_lang_html('admin/profile_fields'),
				'id' => 'profile_fields',
				'style' => 'tall',
				'type' => 'custom',
				'html' => strlen($listhtml) ? '<ul style="margin-bottom:0;">' . $listhtml . '</ul>' : null,
			);
		}

		$as_content['form']['fields'][] = array('type' => 'blank');

		$pointstitle = as_get_points_to_titles();

		$listhtml = '';

		foreach ($pointstitle as $points => $title) {
			$listhtml .= '<li><b>' . $title . '</b> - ' . (($points == 1) ? as_lang_html_sub('main/1_point', '1', '1')
					: as_lang_html_sub('main/x_points', as_html(as_format_number($points))));

			$listhtml .= strtr(as_lang_html('admin/edit_title'), array(
				'^1' => '<a href="' . as_path_html('admin/usertitles', array('edit' => $points)) . '">',
				'^2' => '</a>',
			));

			$listhtml .= '</li>';
		}

		$listhtml .= '<li><b><a href="' . as_path_html('admin/usertitles') . '">' . as_lang_html('admin/add_new_title') . '</a></b></li>';

		$as_content['form']['fields']['usertitles'] = array(
			'label' => as_lang_html('admin/user_titles'),
			'style' => 'tall',
			'type' => 'custom',
			'html' => strlen($listhtml) ? '<ul style="margin-bottom:0;">' . $listhtml . '</ul>' : null,
		);
		break;

	case 'layout':
		$listhtml = '';

		$widgetmodules = as_load_modules_with('widget', 'allow_template');

		foreach ($widgetmodules as $tryname => $trywidget) {
			if (method_exists($trywidget, 'allow_region')) {
				$listhtml .= '<li><b>' . as_html($tryname) . '</b>';

				$listhtml .= strtr(as_lang_html('admin/add_widget_link'), array(
					'^1' => '<a href="' . as_path_html('admin/layoutwidgets', array('title' => $tryname)) . '">',
					'^2' => '</a>',
				));

				if (method_exists($trywidget, 'admin_form'))
					$listhtml .= strtr(as_lang_html('admin/widget_global_options'), array(
						'^1' => '<a href="' . as_admin_module_options_path('widget', $tryname) . '">',
						'^2' => '</a>',
					));

				$listhtml .= '</li>';
			}
		}

		if (strlen($listhtml)) {
			$as_content['form']['fields']['plugins'] = array(
				'label' => as_lang_html('admin/widgets_explanation'),
				'style' => 'tall',
				'type' => 'custom',
				'html' => '<ul style="margin-bottom:0;">' . $listhtml . '</ul>',
			);
		}

		$widgets = as_db_single_select(as_db_widgets_selectspec());

		$listhtml = '';

		$placeoptions = as_admin_place_options();

		foreach ($widgets as $widget) {
			$listhtml .= '<li><b>' . as_html($widget['title']) . '</b> - ' .
				'<a href="' . as_path_html('admin/layoutwidgets', array('edit' => $widget['widgetid'])) . '">' .
				@$placeoptions[$widget['place']] . '</a>';

			$listhtml .= '</li>';
		}

		if (strlen($listhtml)) {
			$as_content['form']['fields']['widgets'] = array(
				'label' => as_lang_html('admin/active_widgets_explanation'),
				'type' => 'custom',
				'html' => '<ul style="margin-bottom:0;">' . $listhtml . '</ul>',
			);
		}

		break;

	case 'permissions':
		$as_content['form']['fields']['permit_block'] = array(
			'type' => 'static',
			'label' => as_lang_html('options/permit_block'),
			'value' => as_lang_html('options/permit_moderators'),
		);

		if (!AS_FINAL_EXTERNAL_USERS) {
			$as_content['form']['fields']['permit_approve_users'] = array(
				'type' => 'static',
				'label' => as_lang_html('options/permit_approve_users'),
				'value' => as_lang_html('options/permit_moderators'),
			);

			$as_content['form']['fields']['permit_create_experts'] = array(
				'type' => 'static',
				'label' => as_lang_html('options/permit_create_experts'),
				'value' => as_lang_html('options/permit_moderators'),
			);

			$as_content['form']['fields']['permit_see_emails'] = array(
				'type' => 'static',
				'label' => as_lang_html('options/permit_see_emails'),
				'value' => as_lang_html('options/permit_admins'),
			);

			$as_content['form']['fields']['permit_delete_users'] = array(
				'type' => 'static',
				'label' => as_lang_html('options/permit_delete_users'),
				'value' => as_lang_html('options/permit_admins'),
			);

			$as_content['form']['fields']['permit_create_eds_mods'] = array(
				'type' => 'static',
				'label' => as_lang_html('options/permit_create_eds_mods'),
				'value' => as_lang_html('options/permit_admins'),
			);

			$as_content['form']['fields']['permit_create_admins'] = array(
				'type' => 'static',
				'label' => as_lang_html('options/permit_create_admins'),
				'value' => as_lang_html('options/permit_supers'),
			);
		}

		break;

	case 'mailing':
		require_once AS_INCLUDE_DIR . 'util/sort.php';

		if (isset($mailingprogress)) {
			unset($as_content['form']['buttons']['save']);
			unset($as_content['form']['buttons']['reset']);

			if ($startmailing) {
				unset($as_content['form']['hidden']['dosaveoptions']);

				foreach ($showoptions as $optionname)
					$as_content['form']['fields'][$optionname]['type'] = 'static';

				$as_content['form']['fields']['mailing_body']['value'] = as_html(as_opt('mailing_body'), true);

				$as_content['form']['buttons']['stop'] = array(
					'tags' => 'name="domailingpause" id="domailingpause"',
					'label' => as_lang_html('admin/pause_mailing_button'),
				);

			} else {
				$as_content['form']['buttons']['resume'] = array(
					'tags' => 'name="domailingresume"',
					'label' => as_lang_html('admin/resume_mailing_button'),
				);

				$as_content['form']['buttons']['cancel'] = array(
					'tags' => 'name="domailingcancel"',
					'label' => as_lang_html('admin/cancel_mailing_button'),
				);
			}
		} else {
			$as_content['form']['buttons']['spacer'] = array();

			$as_content['form']['buttons']['test'] = array(
				'tags' => 'name="domailingtest" id="domailingtest"',
				'label' => as_lang_html('admin/send_test_button'),
			);

			$as_content['form']['buttons']['start'] = array(
				'tags' => 'name="domailingstart" id="domailingstart"',
				'label' => as_lang_html('admin/start_mailing_button'),
			);
		}

		if (!$startmailing) {
			$as_content['form']['fields']['mailing_enabled']['note'] = as_lang_html('admin/mailing_explanation');
			$as_content['form']['fields']['mailing_body']['rows'] = 12;
			$as_content['form']['fields']['mailing_body']['note'] = as_lang_html('admin/mailing_unsubscribe');
		}
		break;

	case 'caching':
		$cacheDriver = APS_Storage_CacheFactory::getCacheDriver();
		$as_content['error'] = $cacheDriver->getError();
		$cacheStats = $cacheDriver->getStats();

		$as_content['form_2'] = array(
			'tags' => 'method="post" action="' . as_path_html('admin/recalc') . '"',

			'title' => as_lang_html('admin/caching_cleanup'),

			'style' => 'wide',

			'fields' => array(
				'cache_files' => array(
					'type' => 'static',
					'label' => as_lang_html('admin/caching_num_items'),
					'value' => as_html(as_format_number($cacheStats['files'])),
				),
				'cache_size' => array(
					'type' => 'static',
					'label' => as_lang_html('admin/caching_space_used'),
					'value' => as_html(as_format_number($cacheStats['size'] / 1048576, 1) . ' MB'),
				),
			),

			'buttons' => array(
				'delete_expired' => array(
					'label' => as_lang_html('admin/caching_delete_expired'),
					'tags' => 'name="docachetrim" onclick="return as_recalc_click(this.name, this, ' . as_js(as_lang_html('admin/delete_stop')) . ', \'cachetrim_note\');"',
					'note' => '<span id="cachetrim_note"></span>',
				),
				'delete_all' => array(
					'label' => as_lang_html('admin/caching_delete_all'),
					'tags' => 'name="docacheclear" onclick="return as_recalc_click(this.name, this, ' . as_js(as_lang_html('admin/delete_stop')) . ', \'cacheclear_note\');"',
					'note' => '<span id="cacheclear_note"></span>',
				),
			),

			'hidden' => array(
				'code' => as_get_form_security_code('admin/recalc'),
			),
		);
		break;
}


if (isset($checkboxtodisplay))
	as_set_display_rules($as_content, $checkboxtodisplay);

$as_content['navigation']['sub'] = as_admin_sub_navigation();


return $as_content;
