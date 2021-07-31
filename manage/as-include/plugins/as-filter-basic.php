<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Basic module for validating form inputs


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

require_once AS_INCLUDE_DIR.'db/maxima.php';
require_once AS_INCLUDE_DIR.'util/string.php';

class as_filter_basic
{
	public function filter_email(&$email, $olduser)
	{
		if (!strlen($email)) {
			return as_lang('users/email_required');
		}
		if (!as_email_validate($email)) {
			return as_lang('users/email_invalid');
		}
		if (as_strlen($email) > AS_DB_MAX_EMAIL_LENGTH) {
			return as_lang_sub('main/max_length_x', AS_DB_MAX_EMAIL_LENGTH);
		}
	}

	public function filter_mobile(&$mobile, $olduser)
	{
		if (!strlen($mobile)) {
			return as_lang('users/mobile_empty');
		}
	}

	public function filter_handle(&$handle, $olduser)
	{
		if (!strlen($handle)) {
			return as_lang('users/handle_empty');
		}
		if (in_array($handle, array('.', '..'))) {
			return as_lang_sub('users/handle_has_bad', '. ..');
		}
		if (preg_match('/[\\@\\+\\/]/', $handle)) {
			return as_lang_sub('users/handle_has_bad', '@ + /');
		}
		if (as_strlen($handle) > AS_DB_MAX_HANDLE_LENGTH) {
			return as_lang_sub('main/max_length_x', AS_DB_MAX_HANDLE_LENGTH);
		}
		// check for banned usernames (e.g. "anonymous")
		$wordspreg = as_block_words_to_preg(as_opt('block_bad_usernames'));
		$blocked = as_block_words_match_all($handle, $wordspreg);
		if (!empty($blocked)) {
			return as_lang('users/handle_blocked');
		}
	}

	public function filter_song(&$song, &$errors, $oldsong)
	{
		if ($oldsong === null) {
			// a new post requires these fields be set
			$song['title'] = isset($song['title']) ? $song['title'] : '';
			$song['content'] = isset($song['content']) ? $song['content'] : '';
			$song['text'] = isset($song['text']) ? $song['text'] : '';
		}

		$qminlength = as_opt('min_len_q_title');
		$qmaxlength = max($qminlength, min(as_opt('max_len_q_title'), AS_DB_MAX_TITLE_LENGTH));
		$this->validate_field_length($errors, $song, 'title', $qminlength, $qmaxlength);

		$this->validate_field_length($errors, $song, 'content', 0, AS_DB_MAX_CONTENT_LENGTH); // for storage
		$this->validate_field_length($errors, $song, 'text', as_opt('min_len_q_content'), null); // for display
		// ensure content error is shown
		if (isset($errors['text'])) {
			$errors['content'] = $errors['text'];
		}

		if (isset($song['tags'])) {
			$counttags = count($song['tags']);
			$maxtags = as_opt('max_num_q_tags');
			$mintags = min(as_opt('min_num_q_tags'), $maxtags);

			if ($counttags < $mintags) {
				$errors['tags'] = as_lang_sub('song/min_tags_x', $mintags);
			} elseif ($counttags > $maxtags) {
				$errors['tags'] = as_lang_sub('song/max_tags_x', $maxtags);
			} else {
				$tagstring = as_tags_to_tagstring($song['tags']);
				if (as_strlen($tagstring) > AS_DB_MAX_TAGS_LENGTH) { // for storage
					$errors['tags'] = as_lang_sub('main/max_length_x', AS_DB_MAX_TAGS_LENGTH);
				}
			}
		}

		$this->validate_post_email($errors, $song);
	}

	public function filter_review(&$review, &$errors, $song, $oldreview)
	{
		$this->validate_field_length($errors, $review, 'content', 0, AS_DB_MAX_CONTENT_LENGTH); // for storage
		$this->validate_field_length($errors, $review, 'text', as_opt('min_len_a_content'), null, 'content'); // for display
		$this->validate_post_email($errors, $review);
	}

	public function filter_comment(&$comment, &$errors, $song, $parent, $oldcomment)
	{
		$this->validate_field_length($errors, $comment, 'content', 0, AS_DB_MAX_CONTENT_LENGTH); // for storage
		$this->validate_field_length($errors, $comment, 'text', as_opt('min_len_c_content'), null, 'content'); // for display
		$this->validate_post_email($errors, $comment);
	}

	public function filter_profile(&$profile, &$errors, $user, $oldprofile)
	{
		foreach (array_keys($profile) as $field) {
			// ensure fields are not NULL
			$profile[$field] = (string)$profile[$field];
			$this->validate_field_length($errors, $profile, $field, 0, AS_DB_MAX_CONTENT_LENGTH);
		}
	}


	// The definitions below are not part of a standard filter module, but just used within this one

	/**
	 * Add textual element $field to $errors if length of $input is not between $minlength and $maxlength.
	 *
	 * @deprecated This function is no longer used and will removed in the future.
	 */
	public function validate_length(&$errors, $field, $input, $minlength, $maxlength)
	{
		$length = isset($input) ? as_strlen($input) : 0;

		if ($length < $minlength)
			$errors[$field] = ($minlength == 1) ? as_lang('main/field_required') : as_lang_sub('main/min_length_x', $minlength);
		elseif (isset($maxlength) && ($length > $maxlength))
			$errors[$field] = as_lang_sub('main/max_length_x', $maxlength);
	}

	/**
	 * Check that a field meets the length requirements. If we're editing the post we can ignore missing fields.
	 *
	 * @param array $errors Array of errors, with keys matching $post
	 * @param array $post The post containing the field we want to validate
	 * @param string $key The element of $post to validate
	 * @param int $minlength
	 * @param int $maxlength
	 */
	private function validate_field_length(&$errors, &$post, $key, $minlength, $maxlength, $errorKey = null)
	{
		if (!$errorKey) {
			$errorKey = $key;
		}

		// skip the field if key not set (for example, 'title' when recategorizing songs)
		if (array_key_exists($key, $post)) {
			$length = as_strlen($post[$key]);

			if ($length < $minlength) {
				$errors[$errorKey] = $minlength == 1 ? as_lang('main/field_required') : as_lang_sub('main/min_length_x', $minlength);
			} elseif (isset($maxlength) && ($length > $maxlength)) {
				$errors[$errorKey] = as_lang_sub('main/max_length_x', $maxlength);
			}
		}
	}

	/**
	 * Wrapper function for validating a post's email address.
	 */
	private function validate_post_email(&$errors, $post)
	{
		if (@$post['notify'] && strlen(@$post['email'])) {
			$error = $this->filter_email($post['email'], null);
			if (isset($error)) {
				$errors['email'] = $error;
			}
		}
	}
}
