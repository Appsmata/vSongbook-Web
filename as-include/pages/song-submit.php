<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Common functions for song page form submission, either regular or via Ajax


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


require_once AS_INCLUDE_DIR . 'app/post-create.php';
require_once AS_INCLUDE_DIR . 'app/post-update.php';


/**
 * Checks for a POSTed click on $song by the current user and returns true if it was permitted and processed. Pass
 * in the song's $reviews, all $commentsfollows from it or its reviews, and its closing $closepost (or null if
 * none). If there is an error to display, it will be passed out in $error.
 * @param $song
 * @param $reviews
 * @param $commentsfollows
 * @param $closepost
 * @param $error
 * @return bool
 */
function as_page_q_single_click_q($song, $reviews, $commentsfollows, $closepost, &$error)
{
	require_once AS_INCLUDE_DIR . 'app/post-update.php';
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	$userid = as_get_logged_in_userid();
	$handle = as_get_logged_in_handle();
	$cookieid = as_cookie_get();

	if (as_clicked('q_doreopen') && $song['reopenable'] && as_page_q_click_check_form_code($song, $error)) {
		as_song_close_clear($song, $closepost, $userid, $handle, $cookieid);
		return true;
	}

	if ((as_clicked('q_dohide') && $song['hideable']) || (as_clicked('q_doreject') && $song['moderatable'])) {
		if (as_page_q_click_check_form_code($song, $error)) {
			as_song_set_status($song, AS_POST_STATUS_HIDDEN, $userid, $handle, $cookieid, $reviews, $commentsfollows, $closepost);
			return true;
		}
	}

	if ((as_clicked('q_doreshow') && $song['reshowable']) || (as_clicked('q_doapprove') && $song['moderatable'])) {
		if (as_page_q_click_check_form_code($song, $error)) {
			if ($song['moderatable'] || $song['reshowimmed']) {
				$status = AS_POST_STATUS_NORMAL;

			} else {
				$in = as_page_q_prepare_post_for_filters($song);
				$filtermodules = as_load_modules_with('filter', 'filter_song'); // run through filters but only for queued status

				foreach ($filtermodules as $filtermodule) {
					$tempin = $in; // always pass original song in because we aren't modifying anything else
					$filtermodule->filter_song($tempin, $temperrors, $song);
					$in['queued'] = $tempin['queued']; // only preserve queued status in loop
				}

				$status = $in['queued'] ? AS_POST_STATUS_QUEUED : AS_POST_STATUS_NORMAL;
			}

			as_song_set_status($song, $status, $userid, $handle, $cookieid, $reviews, $commentsfollows, $closepost);
			return true;
		}
	}

	if (as_clicked('q_doclaim') && $song['claimable'] && as_page_q_click_check_form_code($song, $error)) {
		if (as_user_limits_remaining(AS_LIMIT_SONGS)) { // already checked 'permit_post_q'
			as_song_set_userid($song, $userid, $handle, $cookieid);
			return true;

		} else
			$error = as_lang_html('song/post_limit');
	}

	if (as_clicked('q_doflag') && $song['flagbutton'] && as_page_q_click_check_form_code($song, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		$error = as_flag_error_html($song, $userid, as_request());
		if (!$error) {
			if (as_flag_set_tohide($song, $userid, $handle, $cookieid, $song))
				as_song_set_status($song, AS_POST_STATUS_HIDDEN, null, null, null, $reviews, $commentsfollows, $closepost); // hiding not really by this user so pass nulls
			return true;
		}
	}

	if (as_clicked('q_dounflag') && $song['unflaggable'] && as_page_q_click_check_form_code($song, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		as_flag_clear($song, $userid, $handle, $cookieid);
		return true;
	}

	if (as_clicked('q_doclearflags') && $song['clearflaggable'] && as_page_q_click_check_form_code($song, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		as_flags_clear_all($song, $userid, $handle, $cookieid);
		return true;
	}

	return false;
}


/**
 * Checks for a POSTed click on $review by the current user and returns true if it was permitted and processed. Pass in
 * the $song, all of its $reviews, and all $commentsfollows from it or its reviews. Set $allowselectmove to whether
 * it is legitimate to change the selected review for the song from one to another (this can't be done via Ajax).
 * If there is an error to display, it will be passed out in $error.
 * @param $review
 * @param $song
 * @param $reviews
 * @param $commentsfollows
 * @param $allowselectmove
 * @param $error
 * @return bool
 */
function as_page_q_single_click_a($review, $song, $reviews, $commentsfollows, $allowselectmove, &$error)
{
	$userid = as_get_logged_in_userid();
	$handle = as_get_logged_in_handle();
	$cookieid = as_cookie_get();

	$prefix = 'a' . $review['postid'] . '_';

	if (as_clicked($prefix . 'doselect') && $song['aselectable'] && ($allowselectmove || ((!isset($song['selchildid'])) && !as_opt('do_close_on_select'))) && as_page_q_click_check_form_code($review, $error)) {
		as_song_set_selchildid($userid, $handle, $cookieid, $song, $review['postid'], $reviews);
		return true;
	}

	if (as_clicked($prefix . 'dounselect') && $song['aselectable'] && ($song['selchildid'] == $review['postid']) && ($allowselectmove || !as_opt('do_close_on_select')) && as_page_q_click_check_form_code($review, $error)) {
		as_song_set_selchildid($userid, $handle, $cookieid, $song, null, $reviews);
		return true;
	}

	if ((as_clicked($prefix . 'dohide') && $review['hideable']) || (as_clicked($prefix . 'doreject') && $review['moderatable'])) {
		if (as_page_q_click_check_form_code($review, $error)) {
			as_review_set_status($review, AS_POST_STATUS_HIDDEN, $userid, $handle, $cookieid, $song, $commentsfollows);
			return true;
		}
	}

	if ((as_clicked($prefix . 'doreshow') && $review['reshowable']) || (as_clicked($prefix . 'doapprove') && $review['moderatable'])) {
		if (as_page_q_click_check_form_code($review, $error)) {
			if ($review['moderatable'] || $review['reshowimmed']) {
				$status = AS_POST_STATUS_NORMAL;

			} else {
				$in = as_page_q_prepare_post_for_filters($review);
				$filtermodules = as_load_modules_with('filter', 'filter_review'); // run through filters but only for queued status

				foreach ($filtermodules as $filtermodule) {
					$tempin = $in; // always pass original review in because we aren't modifying anything else
					$filtermodule->filter_review($tempin, $temperrors, $song, $review);
					$in['queued'] = $tempin['queued']; // only preserve queued status in loop
				}

				$status = $in['queued'] ? AS_POST_STATUS_QUEUED : AS_POST_STATUS_NORMAL;
			}

			as_review_set_status($review, $status, $userid, $handle, $cookieid, $song, $commentsfollows);
			return true;
		}
	}

	if (as_clicked($prefix . 'dodelete') && $review['deleteable'] && as_page_q_click_check_form_code($review, $error)) {
		as_review_delete($review, $song, $userid, $handle, $cookieid);
		return true;
	}

	if (as_clicked($prefix . 'doclaim') && $review['claimable'] && as_page_q_click_check_form_code($review, $error)) {
		if (as_user_limits_remaining(AS_LIMIT_REVIEWS)) { // already checked 'permit_post_a'
			as_review_set_userid($review, $userid, $handle, $cookieid);
			return true;

		} else
			$error = as_lang_html('song/review_limit');
	}

	if (as_clicked($prefix . 'doflag') && $review['flagbutton'] && as_page_q_click_check_form_code($review, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		$error = as_flag_error_html($review, $userid, as_request());
		if (!$error) {
			if (as_flag_set_tohide($review, $userid, $handle, $cookieid, $song))
				as_review_set_status($review, AS_POST_STATUS_HIDDEN, null, null, null, $song, $commentsfollows); // hiding not really by this user so pass nulls

			return true;
		}
	}

	if (as_clicked($prefix . 'dounflag') && $review['unflaggable'] && as_page_q_click_check_form_code($review, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		as_flag_clear($review, $userid, $handle, $cookieid);
		return true;
	}

	if (as_clicked($prefix . 'doclearflags') && $review['clearflaggable'] && as_page_q_click_check_form_code($review, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		as_flags_clear_all($review, $userid, $handle, $cookieid);
		return true;
	}

	return false;
}


/**
 * Checks for a POSTed click on $comment by the current user and returns true if it was permitted and processed. Pass
 * in the antecedent $song and the comment's $parent post. If there is an error to display, it will be passed out
 * in $error.
 * @param $comment
 * @param $song
 * @param $parent
 * @param $error
 * @return bool
 */
function as_page_q_single_click_c($comment, $song, $parent, &$error)
{
	$userid = as_get_logged_in_userid();
	$handle = as_get_logged_in_handle();
	$cookieid = as_cookie_get();

	$prefix = 'c' . $comment['postid'] . '_';

	if ((as_clicked($prefix . 'dohide') && $comment['hideable']) || (as_clicked($prefix . 'doreject') && $comment['moderatable'])) {
		if (as_page_q_click_check_form_code($parent, $error)) {
			as_comment_set_status($comment, AS_POST_STATUS_HIDDEN, $userid, $handle, $cookieid, $song, $parent);
			return true;
		}
	}

	if ((as_clicked($prefix . 'doreshow') && $comment['reshowable']) || (as_clicked($prefix . 'doapprove') && $comment['moderatable'])) {
		if (as_page_q_click_check_form_code($parent, $error)) {
			if ($comment['moderatable'] || $comment['reshowimmed']) {
				$status = AS_POST_STATUS_NORMAL;

			} else {
				$in = as_page_q_prepare_post_for_filters($comment);
				$filtermodules = as_load_modules_with('filter', 'filter_comment'); // run through filters but only for queued status

				foreach ($filtermodules as $filtermodule) {
					$tempin = $in; // always pass original comment in because we aren't modifying anything else
					$filtermodule->filter_comment($tempin, $temperrors, $song, $parent, $comment);
					$in['queued'] = $tempin['queued']; // only preserve queued status in loop
				}

				$status = $in['queued'] ? AS_POST_STATUS_QUEUED : AS_POST_STATUS_NORMAL;
			}

			as_comment_set_status($comment, $status, $userid, $handle, $cookieid, $song, $parent);
			return true;
		}
	}

	if (as_clicked($prefix . 'dodelete') && $comment['deleteable'] && as_page_q_click_check_form_code($parent, $error)) {
		as_comment_delete($comment, $song, $parent, $userid, $handle, $cookieid);
		return true;
	}

	if (as_clicked($prefix . 'doclaim') && $comment['claimable'] && as_page_q_click_check_form_code($parent, $error)) {
		if (as_user_limits_remaining(AS_LIMIT_COMMENTS)) {
			as_comment_set_userid($comment, $userid, $handle, $cookieid);
			return true;

		} else
			$error = as_lang_html('song/comment_limit');
	}

	if (as_clicked($prefix . 'doflag') && $comment['flagbutton'] && as_page_q_click_check_form_code($parent, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		$error = as_flag_error_html($comment, $userid, as_request());
		if (!$error) {
			if (as_flag_set_tohide($comment, $userid, $handle, $cookieid, $song))
				as_comment_set_status($comment, AS_POST_STATUS_HIDDEN, null, null, null, $song, $parent); // hiding not really by this user so pass nulls

			return true;
		}
	}

	if (as_clicked($prefix . 'dounflag') && $comment['unflaggable'] && as_page_q_click_check_form_code($parent, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		as_flag_clear($comment, $userid, $handle, $cookieid);
		return true;
	}

	if (as_clicked($prefix . 'doclearflags') && $comment['clearflaggable'] && as_page_q_click_check_form_code($parent, $error)) {
		require_once AS_INCLUDE_DIR . 'app/thumbs.php';

		as_flags_clear_all($comment, $userid, $handle, $cookieid);
		return true;
	}

	return false;
}


/**
 * Check the form security (anti-CSRF protection) for one of the buttons shown for post $post. Return true if the
 * security passed, otherwise return false and set an error message in $error
 * @param $post
 * @param $error
 * @return bool
 */
function as_page_q_click_check_form_code($post, &$error)
{
	$result = as_check_form_security_code('buttons-' . $post['postid'], as_post_text('code'));

	if (!$result)
		$error = as_lang_html('misc/form_security_again');

	return $result;
}


/**
 * Processes a POSTed form to add an review to $song, returning the postid if successful, otherwise null. Pass in
 * other $reviews to the song and whether a $usecaptcha is required. The form fields submitted will be passed out
 * as an array in $in, as well as any $errors on those fields.
 * @param $song
 * @param $reviews
 * @param $usecaptcha
 * @param $in
 * @param $errors
 * @return mixed|null
 */
function as_page_q_add_a_submit($song, $reviews, $usecaptcha, &$in, &$errors)
{
	$in = array(
		'name' => as_opt('allow_anonymous_naming') ? as_post_text('a_name') : null,
		'notify' => as_post_text('a_notify') !== null,
		'email' => as_post_text('a_email'),
		'queued' => as_user_moderation_reason(as_user_level_for_post($song)) !== false,
	);

	as_get_post_content('a_editor', 'a_content', $in['editor'], $in['content'], $in['format'], $in['text']);

	$errors = array();

	if (!as_check_form_security_code('review-' . $song['postid'], as_post_text('code')))
		$errors['content'] = as_lang_html('misc/form_security_again');

	else {
		// call any filter plugins
		$filtermodules = as_load_modules_with('filter', 'filter_review');
		foreach ($filtermodules as $filtermodule) {
			$oldin = $in;
			$filtermodule->filter_review($in, $errors, $song, null);
			as_update_post_text($in, $oldin);
		}

		// check CAPTCHA
		if ($usecaptcha)
			as_captcha_validate_post($errors);

		// check for duplicate posts
		if (empty($errors)) {
			$testwords = implode(' ', as_string_to_words($in['content']));

			foreach ($reviews as $review) {
				if (!$review['hidden']) {
					if (implode(' ', as_string_to_words($review['content'])) == $testwords) {
						$errors['content'] = as_lang_html('song/duplicate_content');
						break;
					}
				}
			}
		}

		$userid = as_get_logged_in_userid();

		// if this is an additional review, check we can add it
		if (empty($errors) && !as_opt('allow_multi_reviews')) {
			foreach ($reviews as $review) {
				if (as_post_is_by_user($review, $userid, as_cookie_get())) {
					$errors[] = '';
					break;
				}
			}
		}

		// create the review
		if (empty($errors)) {
			$handle = as_get_logged_in_handle();
			$cookieid = isset($userid) ? as_cookie_get() : as_cookie_get_create(); // create a new cookie if necessary

			$reviewid = as_review_create($userid, $handle, $cookieid, $in['content'], $in['format'], $in['text'], $in['notify'], $in['email'],
				$song, $in['queued'], $in['name']);

			return $reviewid;
		}
	}

	return null;
}


/**
 * Processes a POSTed form to add a comment, returning the postid if successful, otherwise null. Pass in the antecedent
 * $song and the comment's $parent post. Set $usecaptcha to whether a captcha is required. Pass an array which
 * includes the other comments with the same parent in $commentsfollows (it can contain other posts which are ignored).
 * The form fields submitted will be passed out as an array in $in, as well as any $errors on those fields.
 * @param $song
 * @param $parent
 * @param $commentsfollows
 * @param $usecaptcha
 * @param $in
 * @param $errors
 * @return mixed|null
 */
function as_page_q_add_c_submit($song, $parent, $commentsfollows, $usecaptcha, &$in, &$errors)
{
	$parentid = $parent['postid'];

	$prefix = 'c' . $parentid . '_';

	$in = array(
		'name' => as_opt('allow_anonymous_naming') ? as_post_text($prefix . 'name') : null,
		'notify' => as_post_text($prefix . 'notify') !== null,
		'email' => as_post_text($prefix . 'email'),
		'queued' => as_user_moderation_reason(as_user_level_for_post($parent)) !== false,
	);

	as_get_post_content($prefix . 'editor', $prefix . 'content', $in['editor'], $in['content'], $in['format'], $in['text']);

	$errors = array();

	if (!as_check_form_security_code('comment-' . $parent['postid'], as_post_text($prefix . 'code')))
		$errors['content'] = as_lang_html('misc/form_security_again');

	else {
		$filtermodules = as_load_modules_with('filter', 'filter_comment');
		foreach ($filtermodules as $filtermodule) {
			$oldin = $in;
			$filtermodule->filter_comment($in, $errors, $song, $parent, null);
			as_update_post_text($in, $oldin);
		}

		if ($usecaptcha)
			as_captcha_validate_post($errors);

		if (empty($errors)) {
			$testwords = implode(' ', as_string_to_words($in['content']));

			foreach ($commentsfollows as $comment) {
				if ($comment['basetype'] == 'C' && $comment['parentid'] == $parentid && !$comment['hidden']) {
					if (implode(' ', as_string_to_words($comment['content'])) == $testwords) {
						$errors['content'] = as_lang_html('song/duplicate_content');
						break;
					}
				}
			}
		}

		if (empty($errors)) {
			$userid = as_get_logged_in_userid();
			$handle = as_get_logged_in_handle();
			$cookieid = isset($userid) ? as_cookie_get() : as_cookie_get_create(); // create a new cookie if necessary

			$commentid = as_comment_create($userid, $handle, $cookieid, $in['content'], $in['format'], $in['text'], $in['notify'], $in['email'],
				$song, $parent, $commentsfollows, $in['queued'], $in['name']);

			return $commentid;
		}
	}

	return null;
}


/**
 * Return the array of information to be passed to filter modules for the post in $post (from the database)
 * @param $post
 * @return array
 */
function as_page_q_prepare_post_for_filters($post)
{
	$in = array(
		'content' => $post['content'],
		'format' => $post['format'],
		'text' => as_viewer_text($post['content'], $post['format']),
		'notify' => isset($post['notify']),
		'email' => as_email_validate($post['notify']) ? $post['notify'] : null,
		'queued' => as_user_moderation_reason(as_user_level_for_post($post)) !== false,
	);

	if ($post['basetype'] == 'S') {
		$in['title'] = $post['title'];
		$in['tags'] = as_tagstring_to_tags($post['tags']);
		$in['categoryid'] = $post['categoryid'];
		$in['extra'] = $post['extra'];
	}

	return $in;
}
