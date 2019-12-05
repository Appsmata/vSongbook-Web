<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Theme layer class for viewing thumbers and flaggers


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

class as_html_theme_layer extends as_html_theme_base
{
	private $as_thumbers_flaggers_queue = array();
	private $as_thumbers_flaggers_cache = array();


	// Collect up all required postids for the entire page to save DB queries - common case where whole page output

	public function main()
	{
		foreach ($this->content as $key => $part) {
			if (strpos($key, 's_list') === 0) {
				if (isset($part['qs']))
					$this->queue_raw_posts_thumbers_flaggers($part['qs']);

			} elseif (strpos($key, 'q_view') === 0) {
				$this->queue_post_thumbers_flaggers($part['raw']);
				$this->queue_raw_posts_thumbers_flaggers($part['c_list']['cs']);

			} elseif (strpos($key, 'a_list') === 0) {
				if (!empty($part)) {
					$this->queue_raw_posts_thumbers_flaggers($part['as']);

					foreach ($part['as'] as $a_item) {
						if (isset($a_item['c_list']['cs']))
							$this->queue_raw_posts_thumbers_flaggers($a_item['c_list']['cs']);
					}
				}
			}
		}

		parent::main();
	}


	// Other functions which also collect up required postids for lists to save DB queries - helps with widget output and Ajax calls

	public function s_list_items($q_items)
	{
		$this->queue_raw_posts_thumbers_flaggers($q_items);

		parent::s_list_items($q_items);
	}

	public function a_list_items($a_items)
	{
		$this->queue_raw_posts_thumbers_flaggers($a_items);

		parent::a_list_items($a_items);
	}

	public function c_list_items($c_items)
	{
		$this->queue_raw_posts_thumbers_flaggers($c_items);

		parent::c_list_items($c_items);
	}


	// Actual output of the thumbers and flaggers

	public function thumb_count($post)
	{
		$postid = isset($post['thumb_opostid']) && $post['thumb_opostid'] ? $post['raw']['opostid'] : $post['raw']['postid'];
		$thumbersflaggers = $this->get_post_thumbers_flaggers($post['raw'], $postid);

		if (isset($thumbersflaggers)) {
			$uphandles = array();
			$downhandles = array();

			foreach ($thumbersflaggers as $thumbrflagger) {
				if ($thumbrflagger['thumb'] != 0) {
					$newflagger = as_html($thumbrflagger['handle']);
					if ($thumbrflagger['thumb'] > 0)
						$uphandles[] = $newflagger;
					else  // if ($thumbrflagger['thumb'] < 0)
						$downhandles[] = $newflagger;
				}
			}

			$tooltip = trim(
				(empty($uphandles) ? '' : '&uarr; ' . implode(', ', $uphandles)) . "\n\n" .
				(empty($downhandles) ? '' : '&darr; ' . implode(', ', $downhandles))
			);

			$post['thumb_count_tags'] = sprintf('%s title="%s"', isset($post['thumb_count_tags']) ? $post['thumb_count_tags'] : '', $tooltip);
		}

		parent::thumb_count($post);
	}

	public function post_meta_flags($post, $class)
	{
		if (isset($post['raw']['opostid']))
			$postid = $post['raw']['opostid'];
		elseif (isset($post['raw']['postid']))
			$postid = $post['raw']['postid'];

		$flaggers = array();

		if (isset($postid)) {
			$thumbersflaggers = $this->get_post_thumbers_flaggers($post, $postid);

			if (isset($thumbersflaggers)) {
				foreach ($thumbersflaggers as $thumbrflagger) {
					if ($thumbrflagger['flag'] > 0)
						$flaggers[] = as_html($thumbrflagger['handle']);
				}
			}
		}

		if (!empty($flaggers))
			$this->output('<span title="&#9873; ' . implode(', ', $flaggers) . '">');

		parent::post_meta_flags($post, $class);

		if (!empty($flaggers))
			$this->output('</span>');
	}


	// Utility functions for this layer

	private function queue_post_thumbers_flaggers($post)
	{
		if (!as_user_post_permit_error('permit_view_thumbers_flaggers', $post)) {
			$postkeys = array('postid', 'opostid');
			foreach ($postkeys as $key) {
				if (isset($post[$key]) && !isset($this->as_thumbers_flaggers_cache[$post[$key]]))
					$this->as_thumbers_flaggers_queue[$post[$key]] = true;
			}
		}
	}

	private function queue_raw_posts_thumbers_flaggers($posts)
	{
		if (is_array($posts)) {
			foreach ($posts as $post) {
				if (isset($post['raw']))
					$this->queue_post_thumbers_flaggers($post['raw']);
			}
		}
	}

	private function retrieve_queued_thumbers_flaggers()
	{
		if (count($this->as_thumbers_flaggers_queue)) {
			require_once AS_INCLUDE_DIR . 'db/thumbs.php';

			$postids = array_keys($this->as_thumbers_flaggers_queue);

			foreach ($postids as $postid) {
				$this->as_thumbers_flaggers_cache[$postid] = array();
			}

			$newthumbersflaggers = as_db_userthumbflag_posts_get($postids);

			if (AS_FINAL_EXTERNAL_USERS) {
				$keyuserids = array();
				foreach ($newthumbersflaggers as $thumbrflagger) {
					$keyuserids[$thumbrflagger['userid']] = true;
				}

				$useridhandles = as_get_public_from_userids(array_keys($keyuserids));
				foreach ($newthumbersflaggers as $index => $thumbrflagger) {
					$newthumbersflaggers[$index]['handle'] = isset($useridhandles[$thumbrflagger['userid']]) ? $useridhandles[$thumbrflagger['userid']] : null;
				}
			}

			foreach ($newthumbersflaggers as $thumbrflagger) {
				$this->as_thumbers_flaggers_cache[$thumbrflagger['postid']][] = $thumbrflagger;
			}

			$this->as_thumbers_flaggers_queue = array();
		}
	}

	private function get_post_thumbers_flaggers($post, $postid)
	{
		require_once AS_INCLUDE_DIR . 'util/sort.php';

		if (!isset($this->as_thumbers_flaggers_cache[$postid])) {
			$this->queue_post_thumbers_flaggers($post);
			$this->retrieve_queued_thumbers_flaggers();
		}

		$thumbersflaggers = isset($this->as_thumbers_flaggers_cache[$postid]) ? $this->as_thumbers_flaggers_cache[$postid] : null;

		if (isset($thumbersflaggers))
			as_sort_by($thumbersflaggers, 'handle');

		return $thumbersflaggers;
	}
}
