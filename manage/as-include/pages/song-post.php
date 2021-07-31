<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: More control for song page if it's submitted by HTTP POST


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

require_once AS_INCLUDE_DIR . 'app/limits.php';
require_once AS_INCLUDE_DIR . 'pages/song-submit.php';


$code = as_post_text('code');


// Process general cancel button

if (as_clicked('docancel'))
	as_page_q_refresh($pagestart);


// Process incoming review (or button)

if ($song['reviewbutton']) {
	if (as_clicked('q_doreview'))
		as_page_q_refresh($pagestart, 'review');

	// The 'approve', 'signin', 'confirm', 'limit', 'userblock', 'ipblock' permission errors are reported to the user here
	// The other option ('level') prevents the review button being shown, in as_page_q_post_rules(...)

	if (as_clicked('a_doadd') || $pagestate == 'review') {
		switch (as_user_post_permit_error('permit_post_a', $song, AS_LIMIT_REVIEWS)) {
			case 'signin':
				$pageerror = as_insert_signin_links(as_lang_html('song/review_must_signin'), as_request());
				break;

			case 'confirm':
				$pageerror = as_insert_signin_links(as_lang_html('song/review_must_confirm'), as_request());
				break;

			case 'approve':
				$pageerror = strtr(as_lang_html('song/review_must_be_approved'), array(
					'^1' => '<a href="' . as_path_html('account') . '">',
					'^2' => '</a>',
				));
				break;

			case 'limit':
				$pageerror = as_lang_html('song/review_limit');
				break;

			default:
				$pageerror = as_lang_html('users/no_permission');
				break;

			case false:
				if (as_clicked('a_doadd')) {
					$reviewid = as_page_q_add_a_submit($song, $reviews, $usecaptcha, $anewin, $anewerrors);

					if (isset($reviewid))
						as_page_q_refresh(0, null, 'R', $reviewid);
					else
						$formtype = 'a_add'; // show form again

				} else
					$formtype = 'a_add'; // show form as if first time
				break;
		}
	}
}


// Process close buttons for song

if ($song['closeable']) {
	if (as_clicked('q_doclose'))
		as_page_q_refresh($pagestart, 'close');

	elseif (as_clicked('doclose') && as_page_q_permit_edit($song, 'permit_close_q', $pageerror)) {
		if (as_page_q_close_q_submit($song, $closepost, $closein, $closeerrors))
			as_page_q_refresh($pagestart);
		else
			$formtype = 'q_close'; // keep editing if an error

	} elseif ($pagestate == 'close' && as_page_q_permit_edit($song, 'permit_close_q', $pageerror))
		$formtype = 'q_close';
}


// Process any single click operations or delete button for song

if (as_page_q_single_click_q($song, $reviews, $commentsfollows, $closepost, $pageerror))
	as_page_q_refresh($pagestart);

if (as_clicked('q_dodelete') && $song['deleteable'] && as_page_q_click_check_form_code($song, $pageerror)) {
	as_song_delete($song, $userid, as_get_logged_in_handle(), $cookieid, $closepost);
	as_redirect(''); // redirect since song has gone
}


// Process edit or save button for song

if ($song['editbutton'] || $song['retagcatbutton']) {
	if (as_clicked('q_doedit'))
		as_page_q_refresh($pagestart, 'edit-' . $songid);

	elseif (as_clicked('q_dosave') && as_page_q_permit_edit($song, 'permit_edit_q', $pageerror, 'permit_retag_cat')) {
		if (as_page_q_edit_q_submit($song, $reviews, $commentsfollows, $closepost, $qin, $qerrors))
			as_redirect(as_q_request($songid, $qin['title'])); // don't use refresh since URL may have changed
		else {
			$formtype = 'q_edit'; // keep editing if an error
			$pageerror = @$qerrors['page']; // for security code failure
		}

	} elseif ($pagestate == ('edit-' . $songid) && as_page_q_permit_edit($song, 'permit_edit_q', $pageerror, 'permit_retag_cat'))
		$formtype = 'q_edit';

	if ($formtype == 'q_edit') { // get tags for auto-completion
		if (as_opt('do_complete_tags'))
			$completetags = array_keys(as_db_select_with_pending(as_db_popular_tags_selectspec(0, AS_DB_RETRIEVE_COMPLETE_TAGS)));
		else
			$completetags = array();
	}
}


// Process adding a comment to song (shows form or processes it)

if ($song['commentbutton']) {
	if (as_clicked('q_docomment'))
		as_page_q_refresh($pagestart, 'comment-' . $songid, 'C', $songid);

	if (as_clicked('c' . $songid . '_doadd') || $pagestate == ('comment-' . $songid))
		as_page_q_do_comment($song, $song, $commentsfollows, $pagestart, $usecaptcha, $cnewin, $cnewerrors, $formtype, $formpostid, $pageerror);
}


// Process clicked buttons for reviews

foreach ($reviews as $reviewid => $review) {
	$prefix = 'a' . $reviewid . '_';

	if (as_page_q_single_click_a($review, $song, $reviews, $commentsfollows, true, $pageerror))
		as_page_q_refresh($pagestart, null, 'R', $reviewid);

	if ($review['editbutton']) {
		if (as_clicked($prefix . 'doedit'))
			as_page_q_refresh($pagestart, 'edit-' . $reviewid);

		elseif (as_clicked($prefix . 'dosave') && as_page_q_permit_edit($review, 'permit_edit_a', $pageerror)) {
			$editedtype = as_page_q_edit_a_submit($review, $song, $reviews, $commentsfollows, $aeditin[$reviewid], $aediterrors[$reviewid]);

			if (isset($editedtype))
				as_page_q_refresh($pagestart, null, $editedtype, $reviewid);

			else {
				$formtype = 'a_edit';
				$formpostid = $reviewid; // keep editing if an error
			}

		} elseif ($pagestate == ('edit-' . $reviewid) && as_page_q_permit_edit($review, 'permit_edit_a', $pageerror)) {
			$formtype = 'a_edit';
			$formpostid = $reviewid;
		}
	}

	if ($review['commentbutton']) {
		if (as_clicked($prefix . 'docomment'))
			as_page_q_refresh($pagestart, 'comment-' . $reviewid, 'C', $reviewid);

		if (as_clicked('c' . $reviewid . '_doadd') || $pagestate == ('comment-' . $reviewid))
			as_page_q_do_comment($song, $review, $commentsfollows, $pagestart, $usecaptcha, $cnewin, $cnewerrors, $formtype, $formpostid, $pageerror);
	}

	if (as_clicked($prefix . 'dofollow')) {
		$params = array('follow' => $reviewid);
		if (isset($song['categoryid']))
			$params['cat'] = $song['categoryid'];

		as_redirect('post', $params);
	}
}


// Process hide, show, delete, flag, unflag, edit or save button for comments

foreach ($commentsfollows as $commentid => $comment) {
	if ($comment['basetype'] == 'C') {
		$cparentid = $comment['parentid'];
		$commentparent = isset($reviews[$cparentid]) ? $reviews[$cparentid] : $song;
		$prefix = 'c' . $commentid . '_';

		if (as_page_q_single_click_c($comment, $song, $commentparent, $pageerror))
			as_page_q_refresh($pagestart, 'showcomments-' . $cparentid, $commentparent['basetype'], $cparentid);

		if ($comment['editbutton']) {
			if (as_clicked($prefix . 'doedit')) {
				if (as_page_q_permit_edit($comment, 'permit_edit_c', $pageerror)) // extra check here ensures error message is visible
					as_page_q_refresh($pagestart, 'edit-' . $commentid, 'C', $commentid);
			} elseif (as_clicked($prefix . 'dosave') && as_page_q_permit_edit($comment, 'permit_edit_c', $pageerror)) {
				if (as_page_q_edit_c_submit($comment, $song, $commentparent, $ceditin[$commentid], $cediterrors[$commentid]))
					as_page_q_refresh($pagestart, null, 'C', $commentid);
				else {
					$formtype = 'c_edit';
					$formpostid = $commentid; // keep editing if an error
				}
			} elseif ($pagestate == ('edit-' . $commentid) && as_page_q_permit_edit($comment, 'permit_edit_c', $pageerror)) {
				$formtype = 'c_edit';
				$formpostid = $commentid;
			}
		}
	}
}


// Functions used above - also see functions in /as-include/pages/song-submit.php (which are shared with Ajax)

/*
	Redirects back to the song page, with the specified parameters
*/
function as_page_q_refresh($start = 0, $state = null, $showtype = null, $showid = null)
{
	$params = array();

	if ($start > 0)
		$params['start'] = $start;
	if (isset($state))
		$params['state'] = $state;

	if (isset($showtype) && isset($showid)) {
		$anchor = as_anchor($showtype, $showid);
		$params['show'] = $showid;
	} else
		$anchor = null;

	as_redirect(as_request(), $params, null, null, $anchor);
}


/*
	Returns whether the editing operation (as specified by $permitoption or $permitoption2) on $post is permitted.
	If not, sets the $error variable appropriately
*/
function as_page_q_permit_edit($post, $permitoption, &$error, $permitoption2 = null)
{
	// The 'signin', 'confirm', 'userblock', 'ipblock' permission errors are reported to the user here
	// The other options ('approve', 'level') prevent the edit button being shown, in as_page_q_post_rules(...)

	$permiterror = as_user_post_permit_error($post['isbyuser'] ? null : $permitoption, $post);
	// if it's by the user, this will only check whether they are blocked

	if ($permiterror && isset($permitoption2)) {
		$permiterror2 = as_user_post_permit_error($post['isbyuser'] ? null : $permitoption2, $post);

		if ($permiterror == 'level' || $permiterror == 'approve' || !$permiterror2) // if it's a less strict error
			$permiterror = $permiterror2;
	}

	switch ($permiterror) {
		case 'signin':
			$error = as_insert_signin_links(as_lang_html('song/edit_must_signin'), as_request());
			break;

		case 'confirm':
			$error = as_insert_signin_links(as_lang_html('song/edit_must_confirm'), as_request());
			break;

		default:
			$error = as_lang_html('users/no_permission');
			break;

		case false:
			break;
	}

	return !$permiterror;
}


/*
	Returns a $as_content form for editing the song and sets up other parts of $as_content accordingly
*/
function as_page_q_edit_q_form(&$as_content, $song, $in, $errors, $completetags, $categories)
{
	$form = array(
		'tags' => 'method="post" action="' . as_self_html() . '"',

		'style' => 'tall',

		'fields' => array(
			
			'number' => array(
				'type' => 'custom',
				'html' => '<table><tr>'.
				'<td><label class="as-form-wide-label">' . as_lang_html('song/q_number_label') .' </label></td>'.
				'<td><input name="number" id="number" autocomplete="off" type="text" value="'.
				as_html(($song['editable'] && isset($in['number'])) ? $in['number'] : $song['number'])
				.'" class="as-form-wide-number"></td>'.
				'<td><label class="as-form-wide-label">' . as_lang_html('song/q_key_label') .' </label></td>'.
				'<td><input name="songkey" id="songkey" autocomplete="off" type="text" value="' .
					as_html(($song['editable'] && isset($in['songkey'])) ? $in['songkey'] : $song['songkey'])
				.'" class="as-form-wide-number"></td>'.
				'</tr></table>',
			),

			'title' => array(
				'type' => $song['editable'] ? 'text' : 'static',
				'label' => as_lang_html('song/q_title_label'),
				'tags' => 'name="q_title"',
				'value' => as_html(($song['editable'] && isset($in['title'])) ? $in['title'] : $song['title']),
				'error' => as_html(@$errors['title']),
			),

			'category' => array(
				'label' => as_lang_html('song/q_category_label'),
				'error' => as_html(@$errors['categoryid']),
			),

			'content' => array(
				'label' => as_lang_html('song/q_content_label'),
				'error' => as_html(@$errors['content']),
			),
			
			/*
			'extra' => array(
				'label' => as_html(as_opt('extra_field_prompt')),
				'tags' => 'name="q_extra"',
				'value' => as_html(isset($in['extra']) ? $in['extra'] : $song['extra']),
				'error' => as_html(@$errors['extra']),
			),

			'tags' => array(
				'error' => as_html(@$errors['tags']),
			),*/

		),

		'buttons' => array(
			'save' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false);"',
				'label' => as_lang_html('main/save_button'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => as_lang_html('main/cancel_button'),
			),
		),

		'hidden' => array(
			'q_dosave' => '1',
			'code' => as_get_form_security_code('edit-' . $song['postid']),
		),
	);

	if ($song['editable']) {
		$content = isset($in['content']) ? $in['content'] : $song['content'];
		$format = isset($in['format']) ? $in['format'] : $song['format'];

		$editorname = isset($in['editor']) ? $in['editor'] : as_opt('editor_for_qs');
		$editor = as_load_editor($content, $format, $editorname);

		$form['fields']['content'] = array_merge($form['fields']['content'],
			as_editor_load_field($editor, $as_content, $content, $format, 'q_content', 12, true));

		if (method_exists($editor, 'update_script'))
			$form['buttons']['save']['tags'] = 'onclick="as_show_waiting_after(this, false); ' . $editor->update_script('q_content') . '"';

		$form['hidden']['q_editor'] = as_html($editorname);

	} else
		unset($form['fields']['content']);

	if (as_using_categories() && count($categories) && $song['retagcatable']) {
		as_set_up_category_field($as_content, $form['fields']['category'], 'q_category', $categories,
			isset($in['categoryid']) ? $in['categoryid'] : $song['categoryid'],
			as_opt('allow_no_category') || !isset($song['categoryid']), as_opt('allow_no_sub_category'));
	} else {
		unset($form['fields']['category']);
	}

	if (!($song['editable'] && as_opt('extra_field_active')))
		unset($form['fields']['extra']);

	if (as_using_tags() && $song['retagcatable']) {
		as_set_up_tag_field($as_content, $form['fields']['tags'], 'q_tags', isset($in['tags']) ? $in['tags'] : as_tagstring_to_tags($song['tags']),
			array(), $completetags, as_opt('page_size_post_tags'));
	} else {
		unset($form['fields']['tags']);
	}

	if ($song['isbyuser']) {
		if (!as_is_logged_in() && as_opt('allow_anonymous_naming'))
			as_set_up_name_field($as_content, $form['fields'], isset($in['name']) ? $in['name'] : @$song['name'], 'q_');

		as_set_up_notify_fields($as_content, $form['fields'], 'S', as_get_logged_in_email(),
			isset($in['notify']) ? $in['notify'] : !empty($song['notify']),
			isset($in['email']) ? $in['email'] : @$song['notify'], @$errors['email'], 'q_');
	}

	if (!as_user_post_permit_error('permit_edit_silent', $song)) {
		$form['fields']['silent'] = array(
			'type' => 'checkbox',
			'label' => as_lang_html('song/save_silent_label'),
			'tags' => 'name="q_silent"',
			'value' => as_html(@$in['silent']),
		);
	}

	return $form;
}


/*
	Processes a POSTed form for editing the song and returns true if successful
*/
function as_page_q_edit_q_submit($song, $reviews, $commentsfollows, $closepost, &$in, &$errors)
{
	$in = array();

	if ($song['editable']) {
		$in['title'] = as_get_post_title('q_title');
		$in['number'] = as_post_text('number');
		$in['songkey'] = as_post_text('songkey');
		as_get_post_content('q_editor', 'q_content', $in['editor'], $in['content'], $in['format'], $in['text']);
		$in['extra'] = as_opt('extra_field_active') ? as_post_text('q_extra') : null;
	}

	if ($song['retagcatable']) {
		if (as_using_tags())
			$in['tags'] = as_get_tags_field_value('q_tags');

		if (as_using_categories())
			$in['categoryid'] = as_get_category_field_value('q_category');
	}

	if (array_key_exists('categoryid', $in)) { // need to check if we can move it to that category, and if we need moderation
		$categories = as_db_select_with_pending(as_db_category_nav_selectspec($in['categoryid'], true));
		$categoryids = array_keys(as_category_path($categories, $in['categoryid']));
		$userlevel = as_user_level_for_categories($categoryids);

	} else
		$userlevel = null;

	if ($song['isbyuser']) {
		$in['name'] = as_opt('allow_anonymous_naming') ? as_post_text('q_name') : null;
		$in['notify'] = as_post_text('q_notify') !== null;
		$in['email'] = as_post_text('q_email');
	}

	if (!as_user_post_permit_error('permit_edit_silent', $song))
		$in['silent'] = as_post_text('q_silent');

	// here the $in array only contains values for parts of the form that were displayed, so those are only ones checked by filters

	$errors = array();

	if (!as_check_form_security_code('edit-' . $song['postid'], as_post_text('code')))
		$errors['page'] = as_lang_html('misc/form_security_again');

	else {
		$in['queued'] = as_opt('moderate_edited_again') && as_user_moderation_reason($userlevel);

		$filtermodules = as_load_modules_with('filter', 'filter_song');
		foreach ($filtermodules as $filtermodule) {
			$oldin = $in;
			$filtermodule->filter_song($in, $errors, $song);

			if ($song['editable'])
				as_update_post_text($in, $oldin);
		}

		if (array_key_exists('categoryid', $in) && strcmp($in['categoryid'], $song['categoryid'])) {
			if (as_user_permit_error('permit_post_q', null, $userlevel))
				$errors['categoryid'] = as_lang_html('song/category_post_not_allowed');
		}

		if (empty($errors)) {
			$userid = as_get_logged_in_userid();
			$handle = as_get_logged_in_handle();
			$cookieid = as_cookie_get();

			// now we fill in the missing values in the $in array, so that we have everything we need for as_song_set_content()
			// we do things in this way to avoid any risk of a validation failure on elements the user can't see (e.g. due to admin setting changes)

			if (!$song['editable']) {
				$in['title'] = $song['title'];
				$in['number'] = as_post_text('number');
				$in['songkey'] = as_post_text('songkey');
				$in['content'] = $song['content'];
				$in['format'] = $song['format'];
				$in['text'] = as_viewer_text($in['content'], $in['format']);
				$in['extra'] = $song['extra'];
			}

			if (!isset($in['tags']))
				$in['tags'] = as_tagstring_to_tags($song['tags']);

			if (!array_key_exists('categoryid', $in))
				$in['categoryid'] = $song['categoryid'];

			if (!isset($in['silent']))
				$in['silent'] = false;

			$setnotify = $song['isbyuser'] ? as_combine_notify_email($song['userid'], $in['notify'], $in['email']) : $song['notify'];

			as_song_set_content($song, $in['number'], $in['songkey'], $in['title'], $in['content'], $in['format'], $in['text'], as_tags_to_tagstring($in['tags']),
				$setnotify, $userid, $handle, $cookieid, $in['extra'], @$in['name'], $in['queued'], $in['silent']);

			if (as_using_categories() && strcmp($in['categoryid'], $song['categoryid'])) {
				as_song_set_category($song, $in['categoryid'], $userid, $handle, $cookieid,
					$reviews, $commentsfollows, $closepost, $in['silent']);
			}

			return true;
		}
	}

	return false;
}


/*
	Returns a $as_content form for closing the song and sets up other parts of $as_content accordingly
*/
function as_page_q_close_q_form(&$as_content, $song, $id, $in, $errors)
{
	$form = array(
		'tags' => 'method="post" action="' . as_self_html() . '"',

		'id' => $id,

		'style' => 'tall',

		'title' => as_lang_html('song/close_form_title'),

		'fields' => array(
			'details' => array(
				'tags' => 'name="q_close_details" id="q_close_details"',
				'label' =>
					'<span id="close_label_other">' . as_lang_html('song/close_reason_title') . '</span>',
				'value' => @$in['details'],
				'error' => as_html(@$errors['details']),
			),
		),

		'buttons' => array(
			'close' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false);"',
				'label' => as_lang_html('song/close_form_button'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => as_lang_html('main/cancel_button'),
			),
		),

		'hidden' => array(
			'doclose' => '1',
			'code' => as_get_form_security_code('close-' . $song['postid']),
		),
	);

	$as_content['focusid'] = 'q_close_details';

	return $form;
}


/*
	Processes a POSTed form for closing the song and returns true if successful
*/
function as_page_q_close_q_submit($song, $closepost, &$in, &$errors)
{
	$in = array(
		'details' => trim(as_post_text('q_close_details')),
	);

	$userid = as_get_logged_in_userid();
	$handle = as_get_logged_in_handle();
	$cookieid = as_cookie_get();

	$sanitizedUrl = filter_var($in['details'], FILTER_SANITIZE_URL);
	$isduplicateurl = filter_var($sanitizedUrl, FILTER_VALIDATE_URL);

	if (!as_check_form_security_code('close-' . $song['postid'], as_post_text('code'))) {
		$errors['details'] = as_lang_html('misc/form_security_again');
	} elseif ($isduplicateurl) {
		// be liberal in what we accept, but there are two potential unlikely pitfalls here:
		// a) URLs could have a fixed numerical path, e.g. http://as.mysite.com/1/478/...
		// b) There could be a song title which is just a number, e.g. http://as.mysite.com/478/12345/...
		// so we check if more than one song could match, and if so, show an error

		$parts = preg_split('|[=/&]|', $sanitizedUrl, -1, PREG_SPLIT_NO_EMPTY);
		$keypostids = array();

		foreach ($parts as $part) {
			if (preg_match('/^[0-9]+$/', $part))
				$keypostids[$part] = true;
		}

		$songids = as_db_posts_filter_q_postids(array_keys($keypostids));

		if (count($songids) == 1 && $songids[0] != $song['postid']) {
			as_song_close_duplicate($song, $closepost, $songids[0], $userid, $handle, $cookieid);
			return true;

		} else
			$errors['details'] = as_lang('song/close_duplicate_error');

	} else {
		if (strlen($in['details']) > 0) {
			as_song_close_other($song, $closepost, $in['details'], $userid, $handle, $cookieid);
			return true;

		} else
			$errors['details'] = as_lang('main/field_required');
	}

	return false;
}


/*
	Returns a $as_content form for editing an review and sets up other parts of $as_content accordingly
*/
function as_page_q_edit_a_form(&$as_content, $id, $review, $song, $reviews, $commentsfollows, $in, $errors)
{
	require_once AS_INCLUDE_DIR . 'util/string.php';

	$reviewid = $review['postid'];
	$prefix = 'a' . $reviewid . '_';

	$content = isset($in['content']) ? $in['content'] : $review['content'];
	$format = isset($in['format']) ? $in['format'] : $review['format'];

	$editorname = isset($in['editor']) ? $in['editor'] : as_opt('editor_for_as');
	$editor = as_load_editor($content, $format, $editorname);

	$hascomments = false;
	foreach ($commentsfollows as $commentfollow) {
		if ($commentfollow['parentid'] == $reviewid)
			$hascomments = true;
	}

	$form = array(
		'tags' => 'method="post" action="' . as_self_html() . '"',

		'id' => $id,

		'title' => as_lang_html('song/edit_a_title'),

		'style' => 'tall',

		'fields' => array(
			'content' => array_merge(
				as_editor_load_field($editor, $as_content, $content, $format, $prefix . 'content', 12),
				array(
					'error' => as_html(@$errors['content']),
				)
			),
		),

		'buttons' => array(
			'save' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false); ' .
					(method_exists($editor, 'update_script') ? $editor->update_script($prefix . 'content') : '') . '"',
				'label' => as_lang_html('main/save_button'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => as_lang_html('main/cancel_button'),
			),
		),

		'hidden' => array(
			$prefix . 'editor' => as_html($editorname),
			$prefix . 'dosave' => '1',
			$prefix . 'code' => as_get_form_security_code('edit-' . $reviewid),
		),
	);

	// Show option to convert this review to a comment, if appropriate

	$commentonoptions = array();

	$lastbeforeid = $song['postid']; // used to find last post created before this review - this is default given
	$lastbeforetime = $song['created'];

	if ($song['commentable']) {
		$commentonoptions[$song['postid']] =
			as_lang_html('song/comment_on_q') . as_html(as_shorten_string_line($song['title'], 80));
	}

	foreach ($reviews as $otherreview) {
		if ($otherreview['postid'] != $reviewid && $otherreview['created'] < $review['created'] && $otherreview['commentable'] && !$otherreview['hidden']) {
			$commentonoptions[$otherreview['postid']] =
				as_lang_html('song/comment_on_a') . as_html(as_shorten_string_line(as_viewer_text($otherreview['content'], $otherreview['format']), 80));

			if ($otherreview['created'] > $lastbeforetime) {
				$lastbeforeid = $otherreview['postid'];
				$lastbeforetime = $otherreview['created'];
			}
		}
	}

	if (count($commentonoptions)) {
		$form['fields']['tocomment'] = array(
			'tags' => 'name="' . $prefix . 'dotoc" id="' . $prefix . 'dotoc"',
			'label' => '<span id="' . $prefix . 'toshown">' . as_lang_html('song/a_convert_to_c_on') . '</span>' .
				'<span id="' . $prefix . 'tohidden" style="display:none;">' . as_lang_html('song/a_convert_to_c') . '</span>',
			'type' => 'checkbox',
			'tight' => true,
		);

		$form['fields']['commenton'] = array(
			'tags' => 'name="' . $prefix . 'commenton"',
			'id' => $prefix . 'commenton',
			'type' => 'select',
			'note' => as_lang_html($hascomments ? 'song/a_convert_warn_cs' : 'song/a_convert_warn'),
			'options' => $commentonoptions,
			'value' => @$commentonoptions[$lastbeforeid],
		);

		as_set_display_rules($as_content, array(
			$prefix . 'commenton' => $prefix . 'dotoc',
			$prefix . 'toshown' => $prefix . 'dotoc',
			$prefix . 'tohidden' => '!' . $prefix . 'dotoc',
		));
	}

	// Show name and notification field if appropriate

	if ($review['isbyuser']) {
		if (!as_is_logged_in() && as_opt('allow_anonymous_naming'))
			as_set_up_name_field($as_content, $form['fields'], isset($in['name']) ? $in['name'] : @$review['name'], $prefix);

		as_set_up_notify_fields($as_content, $form['fields'], 'R', as_get_logged_in_email(),
			isset($in['notify']) ? $in['notify'] : !empty($review['notify']),
			isset($in['email']) ? $in['email'] : @$review['notify'], @$errors['email'], $prefix);
	}

	if (!as_user_post_permit_error('permit_edit_silent', $review)) {
		$form['fields']['silent'] = array(
			'type' => 'checkbox',
			'label' => as_lang_html('song/save_silent_label'),
			'tags' => 'name="' . $prefix . 'silent"',
			'value' => as_html(@$in['silent']),
		);
	}

	return $form;
}


/*
	Processes a POSTed form for editing an review and returns the new type of the post if successful
*/
function as_page_q_edit_a_submit($review, $song, $reviews, $commentsfollows, &$in, &$errors)
{
	$reviewid = $review['postid'];
	$prefix = 'a' . $reviewid . '_';

	$in = array(
		'dotoc' => as_post_text($prefix . 'dotoc'),
		'commenton' => as_post_text($prefix . 'commenton'),
	);

	if ($review['isbyuser']) {
		$in['name'] = as_opt('allow_anonymous_naming') ? as_post_text($prefix . 'name') : null;
		$in['notify'] = as_post_text($prefix . 'notify') !== null;
		$in['email'] = as_post_text($prefix . 'email');
	}

	if (!as_user_post_permit_error('permit_edit_silent', $review))
		$in['silent'] = as_post_text($prefix . 'silent');

	as_get_post_content($prefix . 'editor', $prefix . 'content', $in['editor'], $in['content'], $in['format'], $in['text']);

	// here the $in array only contains values for parts of the form that were displayed, so those are only ones checked by filters

	$errors = array();

	if (!as_check_form_security_code('edit-' . $reviewid, as_post_text($prefix . 'code')))
		$errors['content'] = as_lang_html('misc/form_security_again');

	else {
		$in['queued'] = as_opt('moderate_edited_again') && as_user_moderation_reason(as_user_level_for_post($review));

		$filtermodules = as_load_modules_with('filter', 'filter_review');
		foreach ($filtermodules as $filtermodule) {
			$oldin = $in;
			$filtermodule->filter_review($in, $errors, $song, $review);
			as_update_post_text($in, $oldin);
		}

		if (empty($errors)) {
			$userid = as_get_logged_in_userid();
			$handle = as_get_logged_in_handle();
			$cookieid = as_cookie_get();

			if (!isset($in['silent']))
				$in['silent'] = false;

			$setnotify = $review['isbyuser'] ? as_combine_notify_email($review['userid'], $in['notify'], $in['email']) : $review['notify'];

			if ($in['dotoc'] && (
					(($in['commenton'] == $song['postid']) && $song['commentable']) ||
					(($in['commenton'] != $reviewid) && @$reviews[$in['commenton']]['commentable'])
				)
			) { // convert to a comment
				if (as_user_limits_remaining(AS_LIMIT_COMMENTS)) { // already checked 'permit_post_c'
					as_review_to_comment($review, $in['commenton'], $in['content'], $in['format'], $in['text'], $setnotify,
						$userid, $handle, $cookieid, $song, $reviews, $commentsfollows, @$in['name'], $in['queued'], $in['silent']);

					return 'C'; // to signify that redirect should be to the comment

				} else
					$errors['content'] = as_lang_html('song/comment_limit'); // not really best place for error, but it will do

			} else {
				as_review_set_content($review, $in['content'], $in['format'], $in['text'], $setnotify,
					$userid, $handle, $cookieid, $song, @$in['name'], $in['queued'], $in['silent']);

				return 'R';
			}
		}
	}

	return null;
}


/*
	Processes a request to add a comment to $parent, with antecedent $song, checking for permissions errors
*/
function as_page_q_do_comment($song, $parent, $commentsfollows, $pagestart, $usecaptcha, &$cnewin, &$cnewerrors, &$formtype, &$formpostid, &$error)
{
	// The 'approve', 'signin', 'confirm', 'userblock', 'ipblock' permission errors are reported to the user here
	// The other option ('level') prevents the comment button being shown, in as_page_q_post_rules(...)

	$parentid = $parent['postid'];

	switch (as_user_post_permit_error('permit_post_c', $parent, AS_LIMIT_COMMENTS)) {
		case 'signin':
			$error = as_insert_signin_links(as_lang_html('song/comment_must_signin'), as_request());
			break;

		case 'confirm':
			$error = as_insert_signin_links(as_lang_html('song/comment_must_confirm'), as_request());
			break;

		case 'approve':
			$error = strtr(as_lang_html('song/comment_must_be_approved'), array(
				'^1' => '<a href="' . as_path_html('account') . '">',
				'^2' => '</a>',
			));
			break;

		case 'limit':
			$error = as_lang_html('song/comment_limit');
			break;

		default:
			$error = as_lang_html('users/no_permission');
			break;

		case false:
			if (as_clicked('c' . $parentid . '_doadd')) {
				$commentid = as_page_q_add_c_submit($song, $parent, $commentsfollows, $usecaptcha, $cnewin[$parentid], $cnewerrors[$parentid]);

				if (isset($commentid))
					as_page_q_refresh($pagestart, null, 'C', $commentid);

				else {
					$formtype = 'c_add';
					$formpostid = $parentid; // show form again
				}

			} else {
				$formtype = 'c_add';
				$formpostid = $parentid; // show form first time
			}
			break;
	}
}


/*
	Returns a $as_content form for editing a comment and sets up other parts of $as_content accordingly
*/
function as_page_q_edit_c_form(&$as_content, $id, $comment, $in, $errors)
{
	$commentid = $comment['postid'];
	$prefix = 'c' . $commentid . '_';

	$content = isset($in['content']) ? $in['content'] : $comment['content'];
	$format = isset($in['format']) ? $in['format'] : $comment['format'];

	$editorname = isset($in['editor']) ? $in['editor'] : as_opt('editor_for_cs');
	$editor = as_load_editor($content, $format, $editorname);

	$form = array(
		'tags' => 'method="post" action="' . as_self_html() . '"',

		'id' => $id,

		'title' => as_lang_html('song/edit_c_title'),

		'style' => 'tall',

		'fields' => array(
			'content' => array_merge(
				as_editor_load_field($editor, $as_content, $content, $format, $prefix . 'content', 4, true),
				array(
					'error' => as_html(@$errors['content']),
				)
			),
		),

		'buttons' => array(
			'save' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false); ' .
					(method_exists($editor, 'update_script') ? $editor->update_script($prefix . 'content') : '') . '"',
				'label' => as_lang_html('main/save_button'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => as_lang_html('main/cancel_button'),
			),
		),

		'hidden' => array(
			$prefix . 'editor' => as_html($editorname),
			$prefix . 'dosave' => '1',
			$prefix . 'code' => as_get_form_security_code('edit-' . $commentid),
		),
	);

	if ($comment['isbyuser']) {
		if (!as_is_logged_in() && as_opt('allow_anonymous_naming'))
			as_set_up_name_field($as_content, $form['fields'], isset($in['name']) ? $in['name'] : @$comment['name'], $prefix);

		as_set_up_notify_fields($as_content, $form['fields'], 'C', as_get_logged_in_email(),
			isset($in['notify']) ? $in['notify'] : !empty($comment['notify']),
			isset($in['email']) ? $in['email'] : @$comment['notify'], @$errors['email'], $prefix);
	}

	if (!as_user_post_permit_error('permit_edit_silent', $comment)) {
		$form['fields']['silent'] = array(
			'type' => 'checkbox',
			'label' => as_lang_html('song/save_silent_label'),
			'tags' => 'name="' . $prefix . 'silent"',
			'value' => as_html(@$in['silent']),
		);
	}

	return $form;
}


/*
	Processes a POSTed form for editing a comment and returns true if successful
*/
function as_page_q_edit_c_submit($comment, $song, $parent, &$in, &$errors)
{
	$commentid = $comment['postid'];
	$prefix = 'c' . $commentid . '_';

	$in = array();

	if ($comment['isbyuser']) {
		$in['name'] = as_opt('allow_anonymous_naming') ? as_post_text($prefix . 'name') : null;
		$in['notify'] = as_post_text($prefix . 'notify') !== null;
		$in['email'] = as_post_text($prefix . 'email');
	}

	if (!as_user_post_permit_error('permit_edit_silent', $comment))
		$in['silent'] = as_post_text($prefix . 'silent');

	as_get_post_content($prefix . 'editor', $prefix . 'content', $in['editor'], $in['content'], $in['format'], $in['text']);

	// here the $in array only contains values for parts of the form that were displayed, so those are only ones checked by filters

	$errors = array();

	if (!as_check_form_security_code('edit-' . $commentid, as_post_text($prefix . 'code')))
		$errors['content'] = as_lang_html('misc/form_security_again');

	else {
		$in['queued'] = as_opt('moderate_edited_again') && as_user_moderation_reason(as_user_level_for_post($comment));

		$filtermodules = as_load_modules_with('filter', 'filter_comment');
		foreach ($filtermodules as $filtermodule) {
			$oldin = $in;
			$filtermodule->filter_comment($in, $errors, $song, $parent, $comment);
			as_update_post_text($in, $oldin);
		}

		if (empty($errors)) {
			$userid = as_get_logged_in_userid();
			$handle = as_get_logged_in_handle();
			$cookieid = as_cookie_get();

			if (!isset($in['silent']))
				$in['silent'] = false;

			$setnotify = $comment['isbyuser'] ? as_combine_notify_email($comment['userid'], $in['notify'], $in['email']) : $comment['notify'];

			as_comment_set_content($comment, $in['content'], $in['format'], $in['text'], $setnotify,
				$userid, $handle, $cookieid, $song, $parent, @$in['name'], $in['queued'], $in['silent']);

			return true;
		}
	}

	return false;
}
