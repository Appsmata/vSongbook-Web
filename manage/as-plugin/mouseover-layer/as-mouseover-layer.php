<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/mouseover-layer/as-mouseover-layer.php
	Description: Theme layer class for mouseover layer plugin


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
	public function s_list($s_list)
	{
		if (!empty($s_list['qs']) && as_opt('mouseover_content_on')) { // first check it is not an empty list and the feature is turned on
			// Collect the song ids of all items in the song list (so we can do this in one DB query)

			$postids = array();
			foreach ($s_list['qs'] as $song) {
				if (isset($song['raw']['postid']))
					$postids[] = $song['raw']['postid'];
			}

			if (!empty($postids)) {
				// Retrieve the content for these songs from the database
				$maxlength = as_opt('mouseover_content_max_len');
				$result = as_db_query_sub('SELECT postid, content, format FROM ^posts WHERE postid IN (#)', $postids);
				$postinfo = as_db_read_all_assoc($result, 'postid');

				// Get the regular expression fragment to use for blocked words and the maximum length of content to show

				$blockwordspreg = as_get_block_words_preg();

				// Now add the popup to the title for each song

				foreach ($s_list['qs'] as $index => $song) {
					if (isset($postinfo[$song['raw']['postid']])) {
						$thispost = $postinfo[$song['raw']['postid']];
						$text = as_viewer_text($thispost['content'], $thispost['format'], array('blockwordspreg' => $blockwordspreg));
						$text = preg_replace('/\s+/', ' ', $text);  // Remove duplicated blanks, new line characters, tabs, etc
						$text = as_shorten_string_line($text, $maxlength);
						$title = isset($song['title']) ? $song['title'] : '';
						$s_list['qs'][$index]['title'] = $this->getHtmlTitle(as_html($text), $title);
					}
				}
			}
		}

		parent::s_list($s_list); // call back through to the default function
	}

	/**
	 * Returns the needed HTML to display the tip. Depending on the theme in use, this might need to be
	 * tuned in order for the tip to be displayed properly
	 *
	 * @access private
	 * @param string $mouseOverText Text of the tip
	 * @param string $songTitle Song title
	 * @return string HTML needed to display the tip and the song title
	 */
	private function getHtmlTitle($mouseOverText, $songTitle)
	{
		return sprintf('<span title="%s">%s</span>', $mouseOverText, $songTitle);
	}
}
