<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Common functions for song page viewing, either regular or via Ajax


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
 * Given a $song and its $childposts from the database, return a list of that song's reviews
 * @param $song
 * @param $childposts
 * @return array
 */
function as_page_q_load_as($song, $childposts)
{
	$reviews = array();

	foreach ($childposts as $postid => $post) {
		switch ($post['type']) {
			case 'R':
			case 'R_HIDDEN':
			case 'R_QUEUED':
				$reviews[$postid] = $post;
				break;
		}
	}

	return $reviews;
}


/**
 * Given a $song, its $childposts and its reviews $achildposts from the database,
 * return a list of comments or follow-on songs for that song or its reviews.
 * Follow-on and duplicate songs are now returned, with their visibility determined in as_page_q_comment_follow_list()
 * @param $song
 * @param $childposts
 * @param $achildposts
 * @param array $duplicateposts
 * @return array
 */
function as_page_q_load_c_follows($song, $childposts, $achildposts, $duplicateposts = array())
{
	$commentsfollows = array();

	foreach ($childposts as $postid => $post) {
		switch ($post['basetype']) {
			case 'S':
			case 'C':
				$commentsfollows[$postid] = $post;
				break;
		}
	}

	foreach ($achildposts as $postid => $post) {
		switch ($post['basetype']) {
			case 'S':
			case 'C':
				$commentsfollows[$postid] = $post;
				break;
		}
	}

	foreach ($duplicateposts as $postid => $post) {
		$commentsfollows[$postid] = $post;
	}

	return $commentsfollows;
}


/**
 * Calculates which operations the current user may perform on a post. This function is a key part of APS's logic
 * and is ripe for overriding by plugins. The latter two arrays can contain additional posts retrieved from the
 * database, and these will be ignored.
 *
 * @param array $post The song/review/comment to check.
 * @param array $parentpost The post's parent if there is one.
 * @param array $siblingposts The post's siblings (i.e. those with the same type and parent as the post).
 * @param array $childposts The post's children (e.g. comments on reviews).
 * @return array List of elements that can be added to the post.
 */
function as_page_q_post_rules($post, $parentpost = null, $siblingposts = null, $childposts = null)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	$userid = as_get_logged_in_userid();
	$cookieid = as_cookie_get();
	$userlevel = as_user_level_for_post($post);

	$userfields = as_get_logged_in_user_cache();
	if (!isset($userfields)) {
		$userfields = array(
			'userid' => null,
			'level' => null,
			'flags' => null,
		);
	}

	$rules['isbyuser'] = as_post_is_by_user($post, $userid, $cookieid);
	$rules['closed'] = $post['basetype'] == 'S' && (isset($post['closedbyid']) || (isset($post['selchildid']) && as_opt('do_close_on_select')));

	// Cache some responses to the user permission checks

	$permiterror_post_q = as_user_permit_error('permit_post_q', null, $userlevel, true, $userfields); // don't check limits here, so we can show error message
	$permiterror_post_a = as_user_permit_error('permit_post_a', null, $userlevel, true, $userfields);
	$permiterror_post_c = as_user_permit_error('permit_post_c', null, $userlevel, true, $userfields);

	$edit_option = $post['basetype'] == 'S' ? 'permit_edit_q' : ($post['basetype'] == 'R' ? 'permit_edit_a' : 'permit_edit_c');
	$permiterror_edit = as_user_permit_error($edit_option, null, $userlevel, true, $userfields);
	$permiterror_retagcat = as_user_permit_error('permit_retag_cat', null, $userlevel, true, $userfields);
	$permiterror_flag = as_user_permit_error('permit_flag', null, $userlevel, true, $userfields);
	$permiterror_hide_show = as_user_permit_error('permit_hide_show', null, $userlevel, true, $userfields);
	$permiterror_hide_show_self = $rules['isbyuser'] ? as_user_permit_error(null, null, $userlevel, true, $userfields) : $permiterror_hide_show;

	$close_option = $rules['isbyuser'] && as_opt('allow_close_own_songs') ? null : 'permit_close_q';
	$permiterror_close_open = as_user_permit_error($close_option, null, $userlevel, true, $userfields);
	$permiterror_moderate = as_user_permit_error('permit_moderate', null, $userlevel, true, $userfields);

	// General permissions

	$rules['authorlast'] = !isset($post['lastuserid']) || $post['lastuserid'] === $post['userid'];
	$rules['viewable'] = $post['hidden'] ? !$permiterror_hide_show_self : ($post['queued'] ? ($rules['isbyuser'] || !$permiterror_moderate) : true);

	// Review, comment and edit might show the button even if the user still needs to do something (e.g. log in)

	$rules['reviewbutton'] = $post['type'] == 'S' && $permiterror_post_a != 'level' && !$rules['closed']
		&& (as_opt('allow_self_review') || !$rules['isbyuser']);

	$rules['commentbutton'] = ($post['type'] == 'S' || $post['type'] == 'R') && $permiterror_post_c != 'level'
		&& as_opt($post['type'] == 'S' ? 'comment_on_qs' : 'comment_on_as');
	$rules['commentable'] = $rules['commentbutton'] && !$permiterror_post_c;

	$button_errors = array('signin', 'level', 'approve');

	$rules['editbutton'] = !$post['hidden'] && !$rules['closed']
		&& ($rules['isbyuser'] || (!in_array($permiterror_edit, $button_errors) && (!$post['queued'])));
	$rules['editable'] = $rules['editbutton'] && ($rules['isbyuser'] || !$permiterror_edit);

	$rules['retagcatbutton'] = $post['basetype'] == 'S' && (as_using_tags() || as_using_categories())
		&& !$post['hidden'] && ($rules['isbyuser'] || !in_array($permiterror_retagcat, $button_errors));
	$rules['retagcatable'] = $rules['retagcatbutton'] && ($rules['isbyuser'] || !$permiterror_retagcat);

	if ($rules['editbutton'] && $rules['retagcatbutton']) {
		// only show one button since they lead to the same form
		if ($rules['retagcatable'] && !$rules['editable'])
			$rules['editbutton'] = false; // if we can do this without getting an error, show that as the title
		else
			$rules['retagcatbutton'] = false;
	}

	$rules['aselectable'] = $post['type'] == 'S' && !as_user_permit_error($rules['isbyuser'] ? null : 'permit_select_a', null, $userlevel, true, $userfields);

	$rules['flagbutton'] = as_opt('flagging_of_posts') && !$rules['isbyuser'] && !$post['hidden'] && !$post['queued']
		&& !@$post['userflag'] && !in_array($permiterror_flag, $button_errors);
	$rules['flagtohide'] = $rules['flagbutton'] && !$permiterror_flag && ($post['flagcount'] + 1) >= as_opt('flagging_hide_after');
	$rules['unflaggable'] = @$post['userflag'] && !$post['hidden'];
	$rules['clearflaggable'] = $post['flagcount'] >= (@$post['userflag'] ? 2 : 1) && !$permiterror_hide_show;

	// Other actions only show the button if it's immediately possible

	$notclosedbyother = !($rules['closed'] && isset($post['closedbyid']) && !$rules['authorlast']);
	$nothiddenbyother = !($post['hidden'] && !$rules['authorlast']);

	$rules['closeable'] = as_opt('allow_close_songs') && $post['type'] == 'S' && !$rules['closed'] && $permiterror_close_open === false;
	// cannot reopen a song if it's been hidden, or if it was closed by someone else and you don't have global closing permissions
	$rules['reopenable'] = $rules['closed'] && $permiterror_close_open === false && !$post['hidden']
		&& ($notclosedbyother || !as_user_permit_error('permit_close_q', null, $userlevel, true, $userfields));

	$rules['moderatable'] = $post['queued'] && !$permiterror_moderate;
	// cannot hide a song if it was closed by someone else and you don't have global hiding permissions
	$rules['hideable'] = !$post['hidden'] && ($rules['isbyuser'] || !$post['queued']) && !$permiterror_hide_show_self
		&& ($notclosedbyother || !$permiterror_hide_show);
	// means post can be reshown immediately without checking whether it needs moderation
	$rules['reshowimmed'] = $post['hidden'] && !$permiterror_hide_show;
	// cannot reshow a song if it was hidden by someone else, or if it has flags - unless you have global hide/show permissions
	$rules['reshowable'] = $post['hidden'] && (!$permiterror_hide_show_self) &&
		($rules['reshowimmed'] || ($nothiddenbyother && !$post['flagcount']));

	$rules['deleteable'] = $post['hidden'] && !as_user_permit_error('permit_delete_hidden', null, $userlevel, true, $userfields);
	$rules['claimable'] = !isset($post['userid']) && isset($userid) && strlen(@$post['cookieid']) && (strcmp(@$post['cookieid'], $cookieid) == 0)
		&& !($post['basetype'] == 'S' ? $permiterror_post_q : ($post['basetype'] == 'R' ? $permiterror_post_a : $permiterror_post_c));
	$rules['followable'] = $post['type'] == 'R' ? as_opt('follow_on_as') : false;

	// Check for claims that could break rules about self reviewing and multiple reviews

	if ($rules['claimable'] && $post['basetype'] == 'R') {
		if (!as_opt('allow_self_review') && isset($parentpost) && as_post_is_by_user($parentpost, $userid, $cookieid))
			$rules['claimable'] = false;

		if (isset($siblingposts) && !as_opt('allow_multi_reviews')) {
			foreach ($siblingposts as $siblingpost) {
				if ($siblingpost['parentid'] == $post['parentid'] && $siblingpost['basetype'] == 'R' && as_post_is_by_user($siblingpost, $userid, $cookieid))
					$rules['claimable'] = false;
			}
		}
	}

	// Now make any changes based on the child posts

	if (isset($childposts)) {
		foreach ($childposts as $childpost) {
			if ($childpost['parentid'] == $post['postid']) {
				// this post has comments
				$rules['deleteable'] = false;

				if ($childpost['basetype'] == 'R' && as_post_is_by_user($childpost, $userid, $cookieid)) {
					if (!as_opt('allow_multi_reviews'))
						$rules['reviewbutton'] = false;

					if (!as_opt('allow_self_review'))
						$rules['claimable'] = false;
				}
			}

			if ($childpost['closedbyid'] == $post['postid']) {
				// other songs are closed as duplicates of this one
				$rules['deleteable'] = false;
			}
		}
	}

	// Return the resulting rules

	return $rules;
}


/**
 * Return the $as_content['q_view'] element for $song as viewed by the current user. If this song is a
 * follow-on, pass the song for this song's parent review in $parentsong, otherwise null. If the song
 * is closed, pass the post used to close this song in $closepost, otherwise null. $usershtml should be an array
 * which maps userids to HTML user representations, including the song's author and (if present) last editor. If a
 * form has been explicitly requested for the page, set $formrequested to true - this will hide the buttons.
 * @param $song
 * @param $parentsong
 * @param $closepost
 * @param $usershtml
 * @param $formrequested
 * @return array
 */
function as_page_q_song_view($song, $parentsong, $closepost, $usershtml, $formrequested)
{
	require_once AS_INCLUDE_DIR . 'app/posts.php';

	$songid = $song['postid'];
	$userid = as_get_logged_in_userid();
	$cookieid = as_cookie_get();

	$htmloptions = as_post_html_options($song, null, true);
	$htmloptions['reviewsview'] = false; // review count is displayed separately so don't show it here
	$htmloptions['avatarsize'] = as_opt('avatar_q_page_q_size');
	$htmloptions['q_request'] = as_q_request($song['postid'], $song['title']);
	$q_view = as_post_html_fields($song, $userid, $cookieid, $usershtml, null, $htmloptions);


	$q_view['main_form_tags'] = 'method="post" action="' . as_self_html() . '"';
	$q_view['thumbing_form_hidden'] = array('code' => as_get_form_security_code('thumb'));
	$q_view['buttons_form_hidden'] = array('code' => as_get_form_security_code('buttons-' . $songid), 'as_click' => '');


	// Buttons for operating on the song

	if (!$formrequested) { // don't show if another form is currently being shown on page
		$clicksuffix = ' onclick="as_show_waiting_after(this, false);"'; // add to operations that write to database
		$buttons = array();

		if ($song['editbutton']) {
			$buttons['edit'] = array(
				'tags' => 'name="q_doedit"',
				'label' => as_lang_html('song/edit_button'),
				'popup' => as_lang_html('song/edit_q_popup'),
			);
		}

		$hascategories = as_using_categories();

		if ($song['retagcatbutton']) {
			$buttons['retagcat'] = array(
				'tags' => 'name="q_doedit"',
				'label' => as_lang_html($hascategories ? 'song/recat_button' : 'song/retag_button'),
				'popup' => as_lang_html($hascategories
					? (as_using_tags() ? 'song/retag_cat_popup' : 'song/recat_popup')
					: 'song/retag_popup'
				),
			);
		}

		if ($song['flagbutton']) {
			$buttons['flag'] = array(
				'tags' => 'name="q_doflag"' . $clicksuffix,
				'label' => as_lang_html($song['flagtohide'] ? 'song/flag_hide_button' : 'song/flag_button'),
				'popup' => as_lang_html('song/flag_q_popup'),
			);
		}

		if ($song['unflaggable']) {
			$buttons['unflag'] = array(
				'tags' => 'name="q_dounflag"' . $clicksuffix,
				'label' => as_lang_html('song/unflag_button'),
				'popup' => as_lang_html('song/unflag_popup'),
			);
		}

		if ($song['clearflaggable']) {
			$buttons['clearflags'] = array(
				'tags' => 'name="q_doclearflags"' . $clicksuffix,
				'label' => as_lang_html('song/clear_flags_button'),
				'popup' => as_lang_html('song/clear_flags_popup'),
			);
		}

		if ($song['closeable']) {
			$buttons['close'] = array(
				'tags' => 'name="q_doclose"',
				'label' => as_lang_html('song/close_button'),
				'popup' => as_lang_html('song/close_q_popup'),
			);
		}

		if ($song['reopenable']) {
			$buttons['reopen'] = array(
				'tags' => 'name="q_doreopen"' . $clicksuffix,
				'label' => as_lang_html('song/reopen_button'),
				'popup' => as_lang_html('song/reopen_q_popup'),
			);
		}

		if ($song['moderatable']) {
			$buttons['approve'] = array(
				'tags' => 'name="q_doapprove"' . $clicksuffix,
				'label' => as_lang_html('song/approve_button'),
				'popup' => as_lang_html('song/approve_q_popup'),
			);

			$buttons['reject'] = array(
				'tags' => 'name="q_doreject"' . $clicksuffix,
				'label' => as_lang_html('song/reject_button'),
				'popup' => as_lang_html('song/reject_q_popup'),
			);
		}

		if ($song['hideable']) {
			$buttons['hide'] = array(
				'tags' => 'name="q_dohide"' . $clicksuffix,
				'label' => as_lang_html('song/hide_button'),
				'popup' => as_lang_html('song/hide_q_popup'),
			);
		}

		if ($song['reshowable']) {
			$buttons['reshow'] = array(
				'tags' => 'name="q_doreshow"' . $clicksuffix,
				'label' => as_lang_html('song/reshow_button'),
				'popup' => as_lang_html('song/reshow_q_popup'),
			);
		}

		if ($song['deleteable']) {
			$buttons['delete'] = array(
				'tags' => 'name="q_dodelete"' . $clicksuffix,
				'label' => as_lang_html('song/delete_button'),
				'popup' => as_lang_html('song/delete_q_popup'),
			);
		}

		if ($song['claimable']) {
			$buttons['claim'] = array(
				'tags' => 'name="q_doclaim"' . $clicksuffix,
				'label' => as_lang_html('song/claim_button'),
				'popup' => as_lang_html('song/claim_q_popup'),
			);
		}

		if ($song['reviewbutton']) {
			// don't show if shown by default
			$buttons['review'] = array(
				'tags' => 'name="q_doreview" id="q_doreview" onclick="return as_toggle_element(\'anew\')"',
				'label' => as_lang_html('song/review_button'),
				'popup' => as_lang_html('song/review_q_popup'),
			);
		}

		if ($song['commentbutton']) {
			$buttons['comment'] = array(
				'tags' => 'name="q_docomment" onclick="return as_toggle_element(\'c' . $songid . '\')"',
				'label' => as_lang_html('song/comment_button'),
				'popup' => as_lang_html('song/comment_q_popup'),
			);
		}

		$q_view['form'] = array(
			'style' => 'light',
			'buttons' => $buttons,
		);
	}


	// Information about the song of the review that this song follows on from (or a song directly)

	if (isset($parentsong)) {
		$q_view['follows'] = array(
			'label' => as_lang_html(($song['parentid'] == $parentsong['postid']) ? 'song/follows_q' : 'song/follows_a'),
			'title' => as_html(as_block_words_replace($parentsong['title'], as_get_block_words_preg())),
			'url' => as_q_path_html($parentsong['postid'], $parentsong['title'], false,
				($song['parentid'] == $parentsong['postid']) ? 'S' : 'R', $song['parentid']),
		);
	}


	// Information about the song that this song is a duplicate of (if appropriate)

	if (isset($closepost) || as_post_is_closed($song)) {
		if ($closepost['basetype'] == 'S') {
			if ($closepost['hidden']) {
				// don't show link for hidden songs
				$q_view['closed'] = array(
					'state' => as_lang_html('main/closed'),
					'label' => as_lang_html('main/closed'),
					'content' => '',
				);
			} else {
				$q_view['closed'] = array(
					'state' => as_lang_html('main/closed'),
					'label' => as_lang_html('song/closed_as_duplicate'),
					'content' => as_html(as_block_words_replace($closepost['title'], as_get_block_words_preg())),
					'url' => as_q_path_html($closepost['postid'], $closepost['title']),
				);
			}

		} elseif ($closepost['type'] == 'NOTE') {
			$viewer = as_load_viewer($closepost['content'], $closepost['format']);

			$q_view['closed'] = array(
				'state' => as_lang_html('main/closed'),
				'label' => as_lang_html('song/closed_with_note'),
				'content' => $viewer->get_html($closepost['content'], $closepost['format'], array(
					'blockwordspreg' => as_get_block_words_preg(),
				)),
			);
		} else { // If closed by a selected review due to the do_close_on_select setting being enabled
			$q_view['closed'] = array(
				'state' => as_lang_html('main/closed'),
				'label' => as_lang_html('main/closed'),
				'content' => '',
			);
		}
	}


	// Extra value display

	if (strlen(@$song['extra']) && as_opt('extra_field_active') && as_opt('extra_field_display')) {
		$q_view['extra'] = array(
			'label' => as_html(as_opt('extra_field_label')),
			'content' => as_html(as_block_words_replace($song['extra'], as_get_block_words_preg())),
		);
	}


	return $q_view;
}


/**
 * Returns an element to add to $as_content['a_list']['as'] for $review as viewed by $userid and $cookieid. Pass the
 * review's $song and whether it $isselected. $usershtml should be an array which maps userids to HTML user
 * representations, including the review's author and (if present) last editor. If a form has been explicitly requested
 * for the page, set $formrequested to true - this will hide the buttons.
 * @param $song
 * @param $review
 * @param $isselected
 * @param $usershtml
 * @param $formrequested
 * @return array
 */
function as_page_q_review_view($song, $review, $isselected, $usershtml, $formrequested)
{
	$reviewid = $review['postid'];
	$userid = as_get_logged_in_userid();
	$cookieid = as_cookie_get();

	$htmloptions = as_post_html_options($review, null, true);
	$htmloptions['isselected'] = $isselected;
	$htmloptions['avatarsize'] = as_opt('avatar_q_page_a_size');
	$htmloptions['q_request'] = as_q_request($song['postid'], $song['title']);
	$a_view = as_post_html_fields($review, $userid, $cookieid, $usershtml, null, $htmloptions);

	if ($review['queued'])
		$a_view['error'] = $review['isbyuser'] ? as_lang_html('song/a_your_waiting_approval') : as_lang_html('song/a_waiting_your_approval');

	$a_view['main_form_tags'] = 'method="post" action="' . as_self_html() . '"';
	$a_view['thumbing_form_hidden'] = array('code' => as_get_form_security_code('thumb'));
	$a_view['buttons_form_hidden'] = array('code' => as_get_form_security_code('buttons-' . $reviewid), 'as_click' => '');


	// Selection/unselect buttons and others for operating on the review

	if (!$formrequested) { // don't show if another form is currently being shown on page
		$prefix = 'a' . as_html($reviewid) . '_';
		$clicksuffix = ' onclick="return as_review_click(' . as_js($reviewid) . ', ' . as_js($song['postid']) . ', this);"';

		if ($song['aselectable'] && !$review['hidden'] && !$review['queued']) {
			if ($isselected)
				$a_view['unselect_tags'] = 'title="' . as_lang_html('song/unselect_popup') . '" name="' . $prefix . 'dounselect"' . $clicksuffix;
			else
				$a_view['select_tags'] = 'title="' . as_lang_html('song/select_popup') . '" name="' . $prefix . 'doselect"' . $clicksuffix;
		}

		$buttons = array();

		if ($review['editbutton']) {
			$buttons['edit'] = array(
				'tags' => 'name="' . $prefix . 'doedit"',
				'label' => as_lang_html('song/edit_button'),
				'popup' => as_lang_html('song/edit_a_popup'),
			);
		}

		if ($review['flagbutton']) {
			$buttons['flag'] = array(
				'tags' => 'name="' . $prefix . 'doflag"' . $clicksuffix,
				'label' => as_lang_html($review['flagtohide'] ? 'song/flag_hide_button' : 'song/flag_button'),
				'popup' => as_lang_html('song/flag_a_popup'),
			);
		}

		if ($review['unflaggable']) {
			$buttons['unflag'] = array(
				'tags' => 'name="' . $prefix . 'dounflag"' . $clicksuffix,
				'label' => as_lang_html('song/unflag_button'),
				'popup' => as_lang_html('song/unflag_popup'),
			);
		}

		if ($review['clearflaggable']) {
			$buttons['clearflags'] = array(
				'tags' => 'name="' . $prefix . 'doclearflags"' . $clicksuffix,
				'label' => as_lang_html('song/clear_flags_button'),
				'popup' => as_lang_html('song/clear_flags_popup'),
			);
		}

		if ($review['moderatable']) {
			$buttons['approve'] = array(
				'tags' => 'name="' . $prefix . 'doapprove"' . $clicksuffix,
				'label' => as_lang_html('song/approve_button'),
				'popup' => as_lang_html('song/approve_a_popup'),
			);

			$buttons['reject'] = array(
				'tags' => 'name="' . $prefix . 'doreject"' . $clicksuffix,
				'label' => as_lang_html('song/reject_button'),
				'popup' => as_lang_html('song/reject_a_popup'),
			);
		}

		if ($review['hideable']) {
			$buttons['hide'] = array(
				'tags' => 'name="' . $prefix . 'dohide"' . $clicksuffix,
				'label' => as_lang_html('song/hide_button'),
				'popup' => as_lang_html('song/hide_a_popup'),
			);
		}

		if ($review['reshowable']) {
			$buttons['reshow'] = array(
				'tags' => 'name="' . $prefix . 'doreshow"' . $clicksuffix,
				'label' => as_lang_html('song/reshow_button'),
				'popup' => as_lang_html('song/reshow_a_popup'),
			);
		}

		if ($review['deleteable']) {
			$buttons['delete'] = array(
				'tags' => 'name="' . $prefix . 'dodelete"' . $clicksuffix,
				'label' => as_lang_html('song/delete_button'),
				'popup' => as_lang_html('song/delete_a_popup'),
			);
		}

		if ($review['claimable']) {
			$buttons['claim'] = array(
				'tags' => 'name="' . $prefix . 'doclaim"' . $clicksuffix,
				'label' => as_lang_html('song/claim_button'),
				'popup' => as_lang_html('song/claim_a_popup'),
			);
		}

		if ($review['followable']) {
			$buttons['follow'] = array(
				'tags' => 'name="' . $prefix . 'dofollow"',
				'label' => as_lang_html('song/follow_button'),
				'popup' => as_lang_html('song/follow_a_popup'),
			);
		}

		if ($review['commentbutton']) {
			$buttons['comment'] = array(
				'tags' => 'name="' . $prefix . 'docomment" onclick="return as_toggle_element(\'c' . $reviewid . '\')"',
				'label' => as_lang_html('song/comment_button'),
				'popup' => as_lang_html('song/comment_a_popup'),
			);
		}

		$a_view['form'] = array(
			'style' => 'light',
			'buttons' => $buttons,
		);
	}

	return $a_view;
}


/**
 * Returns an element to add to the appropriate $as_content[...]['c_list']['cs'] array for $comment as viewed by the
 * current user. Pass the comment's $parent post and antecedent $song. $usershtml should be an array which maps
 * userids to HTML user representations, including the comments's author and (if present) last editor. If a form has
 * been explicitly requested for the page, set $formrequested to true - this will hide the buttons.
 * @param $song
 * @param $parent
 * @param $comment
 * @param $usershtml
 * @param $formrequested
 * @return array
 */
function as_page_q_comment_view($song, $parent, $comment, $usershtml, $formrequested)
{
	$commentid = $comment['postid'];
	$songid = ($parent['basetype'] == 'S') ? $parent['postid'] : $parent['parentid'];
	$reviewid = ($parent['basetype'] == 'S') ? null : $parent['postid'];
	$userid = as_get_logged_in_userid();
	$cookieid = as_cookie_get();

	$htmloptions = as_post_html_options($comment, null, true);
	$htmloptions['avatarsize'] = as_opt('avatar_q_page_c_size');
	$htmloptions['q_request'] = as_q_request($song['postid'], $song['title']);
	$c_view = as_post_html_fields($comment, $userid, $cookieid, $usershtml, null, $htmloptions);

	if ($comment['queued'])
		$c_view['error'] = $comment['isbyuser'] ? as_lang_html('song/c_your_waiting_approval') : as_lang_html('song/c_waiting_your_approval');

	$c_view['main_form_tags'] = 'method="post" action="' . as_self_html() . '"';
	$c_view['thumbing_form_hidden'] = array('code' => as_get_form_security_code('thumb'));
	$c_view['buttons_form_hidden'] = array('code' => as_get_form_security_code('buttons-' . $parent['postid']), 'as_click' => '');


	// Buttons for operating on this comment

	if (!$formrequested) { // don't show if another form is currently being shown on page
		$prefix = 'c' . as_html($commentid) . '_';
		$clicksuffix = ' onclick="return as_comment_click(' . as_js($commentid) . ', ' . as_js($songid) . ', ' . as_js($parent['postid']) . ', this);"';

		$buttons = array();

		if ($comment['editbutton']) {
			$buttons['edit'] = array(
				'tags' => 'name="' . $prefix . 'doedit"',
				'label' => as_lang_html('song/edit_button'),
				'popup' => as_lang_html('song/edit_c_popup'),
			);
		}

		if ($comment['flagbutton']) {
			$buttons['flag'] = array(
				'tags' => 'name="' . $prefix . 'doflag"' . $clicksuffix,
				'label' => as_lang_html($comment['flagtohide'] ? 'song/flag_hide_button' : 'song/flag_button'),
				'popup' => as_lang_html('song/flag_c_popup'),
			);
		}

		if ($comment['unflaggable']) {
			$buttons['unflag'] = array(
				'tags' => 'name="' . $prefix . 'dounflag"' . $clicksuffix,
				'label' => as_lang_html('song/unflag_button'),
				'popup' => as_lang_html('song/unflag_popup'),
			);
		}

		if ($comment['clearflaggable']) {
			$buttons['clearflags'] = array(
				'tags' => 'name="' . $prefix . 'doclearflags"' . $clicksuffix,
				'label' => as_lang_html('song/clear_flags_button'),
				'popup' => as_lang_html('song/clear_flags_popup'),
			);
		}

		if ($comment['moderatable']) {
			$buttons['approve'] = array(
				'tags' => 'name="' . $prefix . 'doapprove"' . $clicksuffix,
				'label' => as_lang_html('song/approve_button'),
				'popup' => as_lang_html('song/approve_c_popup'),
			);

			$buttons['reject'] = array(
				'tags' => 'name="' . $prefix . 'doreject"' . $clicksuffix,
				'label' => as_lang_html('song/reject_button'),
				'popup' => as_lang_html('song/reject_c_popup'),
			);
		}

		if ($comment['hideable']) {
			$buttons['hide'] = array(
				'tags' => 'name="' . $prefix . 'dohide"' . $clicksuffix,
				'label' => as_lang_html('song/hide_button'),
				'popup' => as_lang_html('song/hide_c_popup'),
			);
		}

		if ($comment['reshowable']) {
			$buttons['reshow'] = array(
				'tags' => 'name="' . $prefix . 'doreshow"' . $clicksuffix,
				'label' => as_lang_html('song/reshow_button'),
				'popup' => as_lang_html('song/reshow_c_popup'),
			);
		}

		if ($comment['deleteable']) {
			$buttons['delete'] = array(
				'tags' => 'name="' . $prefix . 'dodelete"' . $clicksuffix,
				'label' => as_lang_html('song/delete_button'),
				'popup' => as_lang_html('song/delete_c_popup'),
			);
		}

		if ($comment['claimable']) {
			$buttons['claim'] = array(
				'tags' => 'name="' . $prefix . 'doclaim"' . $clicksuffix,
				'label' => as_lang_html('song/claim_button'),
				'popup' => as_lang_html('song/claim_c_popup'),
			);
		}

		if ($parent['commentbutton'] && as_opt('show_c_reply_buttons') && $comment['type'] == 'C') {
			$buttons['comment'] = array(
				'tags' => 'name="' . (($parent['basetype'] == 'S') ? 'q' : ('a' . as_html($parent['postid']))) .
					'_docomment" onclick="return as_toggle_element(\'c' . as_html($parent['postid']) . '\')"',
				'label' => as_lang_html('song/reply_button'),
				'popup' => as_lang_html('song/reply_c_popup'),
			);
		}

		$c_view['form'] = array(
			'style' => 'light',
			'buttons' => $buttons,
		);
	}

	return $c_view;
}


/**
 * Return an array for $as_content[...]['c_list'] to display all of the comments and follow-on songs in
 * $commentsfollows which belong to post $parent with antecedent $song, as viewed by the current user. If
 * $alwaysfull then all comments will be included, otherwise the list may be shortened with a 'show previous x
 * comments' link. $usershtml should be an array which maps userids to HTML user representations, including all
 * comments' and follow on songs' authors and (if present) last editors. If a form has been explicitly requested
 * for the page, set $formrequested to true and pass the postid of the post for the form in $formpostid - this will
 * hide the buttons and remove the $formpostid comment from the list.
 * @param $song
 * @param $parent
 * @param $commentsfollows
 * @param $alwaysfull
 * @param $usershtml
 * @param $formrequested
 * @param $formpostid
 * @return array
 */
function as_page_q_comment_follow_list($song, $parent, $commentsfollows, $alwaysfull, $usershtml, $formrequested, $formpostid)
{
	$parentid = $parent['postid'];
	$userid = as_get_logged_in_userid();
	$cookieid = as_cookie_get();

	$commentlist = array(
		'tags' => 'id="c' . as_html($parentid) . '_list"',
		'cs' => array(),
	);

	$showcomments = array();

	// $commentsfollows contains ALL comments on the song and all reviews, so here we filter the comments viewable for this context
	foreach ($commentsfollows as $commentfollowid => $commentfollow) {
		$showcomment = $commentfollow['parentid'] == $parentid && $commentfollow['viewable'] && $commentfollowid != $formpostid;
		// show hidden follow-on songs only if the parent is hidden
		if ($showcomment && $commentfollow['basetype'] == 'S' && $commentfollow['hidden']) {
			$showcomment = $parent['hidden'];
		}
		// show songs closed as duplicate of this one, only if this song is hidden
		$showduplicate = $song['hidden'] && $commentfollow['closedbyid'] == $parentid;

		if ($showcomment || $showduplicate) {
			$showcomments[$commentfollowid] = $commentfollow;
		}
	}

	$countshowcomments = count($showcomments);

	if (!$alwaysfull && $countshowcomments > as_opt('show_fewer_cs_from'))
		$skipfirst = $countshowcomments - as_opt('show_fewer_cs_count');
	else
		$skipfirst = 0;

	if ($skipfirst == $countshowcomments) { // showing none
		if ($skipfirst == 1)
			$expandtitle = as_lang_html('song/show_1_comment');
		else
			$expandtitle = as_lang_html_sub('song/show_x_comments', $skipfirst);

	} else {
		if ($skipfirst == 1)
			$expandtitle = as_lang_html('song/show_1_previous_comment');
		else
			$expandtitle = as_lang_html_sub('song/show_x_previous_comments', $skipfirst);
	}

	if ($skipfirst > 0) {
		$commentlist['cs'][$parentid] = array(
			'url' => as_html('?state=showcomments-' . $parentid . '&show=' . $parentid . '#' . urlencode(as_anchor($parent['basetype'], $parentid))),

			'expand_tags' => 'onclick="return as_show_comments(' . as_js($song['postid']) . ', ' . as_js($parentid) . ', this);"',

			'title' => $expandtitle,
		);
	}

	foreach ($showcomments as $commentfollowid => $commentfollow) {
		if ($skipfirst > 0) {
			$skipfirst--;
		} elseif ($commentfollow['basetype'] == 'C') {
			$commentlist['cs'][$commentfollowid] = as_page_q_comment_view($song, $parent, $commentfollow, $usershtml, $formrequested);
		} elseif ($commentfollow['basetype'] == 'S') {
			$htmloptions = as_post_html_options($commentfollow);
			$htmloptions['avatarsize'] = as_opt('avatar_q_page_c_size');
			$htmloptions['thumbview'] = false;

			$commentlist['cs'][$commentfollowid] = as_post_html_fields($commentfollow, $userid, $cookieid, $usershtml, null, $htmloptions);
		}
	}

	if (!count($commentlist['cs']))
		$commentlist['hidden'] = true;

	return $commentlist;
}


/**
 * Return a $as_content form for adding an review to $song. Pass an HTML element id to use for the form in $formid
 * and the result of as_user_captcha_reason() in $captchareason. Pass previous inputs from a submitted version of this
 * form in the array $in and resulting errors in $errors. If $loadnow is true, the form will be loaded immediately. Set
 * $formrequested to true if the user explicitly requested it, as opposed being shown automatically.
 * @param $as_content
 * @param $formid
 * @param $captchareason
 * @param $song
 * @param $in
 * @param $errors
 * @param $loadnow
 * @param $formrequested
 * @return array
 */
function as_page_q_add_a_form(&$as_content, $formid, $captchareason, $song, $in, $errors, $loadnow, $formrequested)
{
	// The 'approve', 'signin', 'confirm', 'limit', 'userblock', 'ipblock' permission errors are reported to the user here
	// The other option ('level') prevents the review button being shown, in as_page_q_post_rules(...)

	switch (as_user_post_permit_error('permit_post_a', $song, AS_LIMIT_REVIEWS)) {
		case 'signin':
			$form = array(
				'title' => as_insert_signin_links(as_lang_html('song/review_must_signin'), as_request()),
			);
			break;

		case 'confirm':
			$form = array(
				'title' => as_insert_signin_links(as_lang_html('song/review_must_confirm'), as_request()),
			);
			break;

		case 'approve':
			$form = array(
				'title' => strtr(as_lang_html('song/review_must_be_approved'), array(
					'^1' => '<a href="' . as_path_html('account') . '">',
					'^2' => '</a>',
				)),
			);
			break;

		case 'limit':
			$form = array(
				'title' => as_lang_html('song/review_limit'),
			);
			break;

		default:
			$form = array(
				'title' => as_lang_html('users/no_permission'),
			);
			break;

		case false:
			$editorname = isset($in['editor']) ? $in['editor'] : as_opt('editor_for_as');
			$editor = as_load_editor(@$in['content'], @$in['format'], $editorname);

			if (method_exists($editor, 'update_script'))
				$updatescript = $editor->update_script('a_content');
			else
				$updatescript = '';

			$custom = as_opt('show_custom_review') ? trim(as_opt('custom_review')) : '';

			$form = array(
				'tags' => 'method="post" action="' . as_self_html() . '" name="a_form"',

				'title' => as_lang_html('song/your_review_title'),

				'fields' => array(
					'custom' => array(
						'type' => 'custom',
						'note' => $custom,
					),

					'content' => array_merge(
						as_editor_load_field($editor, $as_content, @$in['content'], @$in['format'], 'a_content', 12, $formrequested, $loadnow),
						array(
							'error' => as_html(@$errors['content']),
						)
					),
				),

				'buttons' => array(
					'review' => array(
						'tags' => 'onclick="' . $updatescript . ' return as_submit_review(' . as_js($song['postid']) . ', this);"',
						'label' => as_lang_html('song/add_review_button'),
					),
				),

				'hidden' => array(
					'a_editor' => as_html($editorname),
					'a_doadd' => '1',
					'code' => as_get_form_security_code('review-' . $song['postid']),
				),
			);

			if (!strlen($custom))
				unset($form['fields']['custom']);

			if ($formrequested || !$loadnow)
				$form['buttons']['cancel'] = array(
					'tags' => 'name="docancel"',
					'label' => as_lang_html('main/cancel_button'),
				);

			if (!as_is_logged_in() && as_opt('allow_anonymous_naming'))
				as_set_up_name_field($as_content, $form['fields'], @$in['name'], 'a_');

			as_set_up_notify_fields($as_content, $form['fields'], 'R', as_get_logged_in_email(),
				isset($in['notify']) ? $in['notify'] : as_opt('notify_users_default'), @$in['email'], @$errors['email'], 'a_');

			$onloads = array();

			if ($captchareason) {
				$captchaloadscript = as_set_up_captcha_field($as_content, $form['fields'], $errors, as_captcha_reason_note($captchareason));

				if (strlen($captchaloadscript))
					$onloads[] = 'document.getElementById(' . as_js($formid) . ').as_show = function() { ' . $captchaloadscript . ' };';
			}

			if (!$loadnow) {
				if (method_exists($editor, 'load_script'))
					$onloads[] = 'document.getElementById(' . as_js($formid) . ').as_load = function() { ' . $editor->load_script('a_content') . ' };';

				$form['buttons']['cancel']['tags'] .= ' onclick="return as_toggle_element();"';
			}

			if (!$formrequested) {
				if (method_exists($editor, 'focus_script'))
					$onloads[] = 'document.getElementById(' . as_js($formid) . ').as_focus = function() { ' . $editor->focus_script('a_content') . ' };';
			}

			if (count($onloads)) {
				$as_content['script_onloads'][] = $onloads;
			}

			break;
	}

	$form['id'] = $formid;
	$form['collapse'] = !$loadnow;
	$form['style'] = 'tall';

	return $form;
}


/**
 * Returns a $as_content form for adding a comment to post $parent which is part of $song. Pass an HTML element id
 * to use for the form in $formid and the result of as_user_captcha_reason() in $captchareason. Pass previous inputs
 * from a submitted version of this form in the array $in and resulting errors in $errors. If $loadfocusnow is true,
 * the form will be loaded and focused immediately.
 * @param $as_content
 * @param $song
 * @param $parent
 * @param $formid
 * @param $captchareason
 * @param $in
 * @param $errors
 * @param $loadfocusnow
 * @return array
 */
function as_page_q_add_c_form(&$as_content, $song, $parent, $formid, $captchareason, $in, $errors, $loadfocusnow)
{
	// The 'approve', 'signin', 'confirm', 'userblock', 'ipblock' permission errors are reported to the user here
	// The other option ('level') prevents the comment button being shown, in as_page_q_post_rules(...)

	switch (as_user_post_permit_error('permit_post_c', $parent, AS_LIMIT_COMMENTS)) {
		case 'signin':
			$form = array(
				'title' => as_insert_signin_links(as_lang_html('song/comment_must_signin'), as_request()),
			);
			break;

		case 'confirm':
			$form = array(
				'title' => as_insert_signin_links(as_lang_html('song/comment_must_confirm'), as_request()),
			);
			break;

		case 'approve':
			$form = array(
				'title' => strtr(as_lang_html('song/comment_must_be_approved'), array(
					'^1' => '<a href="' . as_path_html('account') . '">',
					'^2' => '</a>',
				)),
			);
			break;

		case 'limit':
			$form = array(
				'title' => as_lang_html('song/comment_limit'),
			);
			break;

		default:
			$form = array(
				'title' => as_lang_html('users/no_permission'),
			);
			break;

		case false:
			$prefix = 'c' . $parent['postid'] . '_';

			$editorname = isset($in['editor']) ? $in['editor'] : as_opt('editor_for_cs');
			$editor = as_load_editor(@$in['content'], @$in['format'], $editorname);

			if (method_exists($editor, 'update_script'))
				$updatescript = $editor->update_script($prefix . 'content');
			else
				$updatescript = '';

			$custom = as_opt('show_custom_comment') ? trim(as_opt('custom_comment')) : '';

			$form = array(
				'tags' => 'method="post" action="' . as_self_html() . '" name="c_form_' . as_html($parent['postid']) . '"',

				'title' => as_lang_html(($song['postid'] == $parent['postid']) ? 'song/your_comment_q' : 'song/your_comment_a'),

				'fields' => array(
					'custom' => array(
						'type' => 'custom',
						'note' => $custom,
					),

					'content' => array_merge(
						as_editor_load_field($editor, $as_content, @$in['content'], @$in['format'], $prefix . 'content', 4, $loadfocusnow, $loadfocusnow),
						array(
							'error' => as_html(@$errors['content']),
						)
					),
				),

				'buttons' => array(
					'comment' => array(
						'tags' => 'onclick="' . $updatescript . ' return as_submit_comment(' . as_js($song['postid']) . ', ' . as_js($parent['postid']) . ', this);"',
						'label' => as_lang_html('song/add_comment_button'),
					),

					'cancel' => array(
						'tags' => 'name="docancel"',
						'label' => as_lang_html('main/cancel_button'),
					),
				),

				'hidden' => array(
					$prefix . 'editor' => as_html($editorname),
					$prefix . 'doadd' => '1',
					$prefix . 'code' => as_get_form_security_code('comment-' . $parent['postid']),
				),
			);

			if (!strlen($custom))
				unset($form['fields']['custom']);

			if (!as_is_logged_in() && as_opt('allow_anonymous_naming'))
				as_set_up_name_field($as_content, $form['fields'], @$in['name'], $prefix);

			as_set_up_notify_fields($as_content, $form['fields'], 'C', as_get_logged_in_email(),
				isset($in['notify']) ? $in['notify'] : as_opt('notify_users_default'), $in['email'], @$errors['email'], $prefix);

			$onloads = array();

			if ($captchareason) {
				$captchaloadscript = as_set_up_captcha_field($as_content, $form['fields'], $errors, as_captcha_reason_note($captchareason));

				if (strlen($captchaloadscript))
					$onloads[] = 'document.getElementById(' . as_js($formid) . ').as_show = function() { ' . $captchaloadscript . ' };';
			}

			if (!$loadfocusnow) {
				if (method_exists($editor, 'load_script'))
					$onloads[] = 'document.getElementById(' . as_js($formid) . ').as_load = function() { ' . $editor->load_script($prefix . 'content') . ' };';
				if (method_exists($editor, 'focus_script'))
					$onloads[] = 'document.getElementById(' . as_js($formid) . ').as_focus = function() { ' . $editor->focus_script($prefix . 'content') . ' };';

				$form['buttons']['cancel']['tags'] .= ' onclick="return as_toggle_element()"';
			}

			if (count($onloads)) {
				$as_content['script_onloads'][] = $onloads;
			}

			break;
	}

	$form['id'] = $formid;
	$form['collapse'] = !$loadfocusnow;
	$form['style'] = 'tall';

	return $form;
}
