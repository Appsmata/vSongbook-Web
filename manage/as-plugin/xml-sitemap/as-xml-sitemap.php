<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/xml-sitemap/as-xml-sitemap.php
	Description: Page module class for XML sitemap plugin


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

class as_xml_sitemap
{
	public function option_default($option)
	{
		switch ($option) {
			case 'xml_sitemap_show_songs':
			case 'xml_sitemap_show_users':
			case 'xml_sitemap_show_tag_qs':
			case 'xml_sitemap_show_category_qs':
			case 'xml_sitemap_show_categories':
				return true;
		}
	}


	public function admin_form()
	{
		require_once AS_INCLUDE_DIR . 'util/sort.php';

		$saved = false;

		if (as_clicked('xml_sitemap_save_button')) {
			as_opt('xml_sitemap_show_songs', (int)as_post_text('xml_sitemap_show_songs_field'));

			if (!AS_FINAL_EXTERNAL_USERS)
				as_opt('xml_sitemap_show_users', (int)as_post_text('xml_sitemap_show_users_field'));

			if (as_using_tags())
				as_opt('xml_sitemap_show_tag_qs', (int)as_post_text('xml_sitemap_show_tag_qs_field'));

			if (as_using_categories()) {
				as_opt('xml_sitemap_show_category_qs', (int)as_post_text('xml_sitemap_show_category_qs_field'));
				as_opt('xml_sitemap_show_categories', (int)as_post_text('xml_sitemap_show_categories_field'));
			}

			$saved = true;
		}

		$form = array(
			'ok' => $saved ? 'XML sitemap settings saved' : null,

			'fields' => array(
				'songs' => array(
					'label' => 'Include song pages',
					'type' => 'checkbox',
					'value' => (int)as_opt('xml_sitemap_show_songs'),
					'tags' => 'name="xml_sitemap_show_songs_field"',
				),
			),

			'buttons' => array(
				array(
					'label' => 'Save Changes',
					'tags' => 'name="xml_sitemap_save_button"',
				),
			),
		);

		if (!AS_FINAL_EXTERNAL_USERS) {
			$form['fields']['users'] = array(
				'label' => 'Include user pages',
				'type' => 'checkbox',
				'value' => (int)as_opt('xml_sitemap_show_users'),
				'tags' => 'name="xml_sitemap_show_users_field"',
			);
		}

		if (as_using_tags()) {
			$form['fields']['tagqs'] = array(
				'label' => 'Include song list for each tag',
				'type' => 'checkbox',
				'value' => (int)as_opt('xml_sitemap_show_tag_qs'),
				'tags' => 'name="xml_sitemap_show_tag_qs_field"',
			);
		}

		if (as_using_categories()) {
			$form['fields']['categoryqs'] = array(
				'label' => 'Include song list for each category',
				'type' => 'checkbox',
				'value' => (int)as_opt('xml_sitemap_show_category_qs'),
				'tags' => 'name="xml_sitemap_show_category_qs_field"',
			);

			$form['fields']['categories'] = array(
				'label' => 'Include category browser',
				'type' => 'checkbox',
				'value' => (int)as_opt('xml_sitemap_show_categories'),
				'tags' => 'name="xml_sitemap_show_categories_field"',
			);
		}

		return $form;
	}


	public function suggest_requests()
	{
		return array(
			array(
				'title' => 'XML Sitemap',
				'request' => 'sitemap.xml',
				'nav' => null, // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
			),
		);
	}


	public function match_request($request)
	{
		return ($request == 'sitemap.xml');
	}


	public function process_request($request)
	{
		@ini_set('display_errors', 0); // we don't want to show PHP errors inside XML

		header('Content-type: text/xml; charset=utf-8');

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";


		// Song pages

		if (as_opt('xml_sitemap_show_songs')) {
			$hotstats = as_db_read_one_assoc(as_db_query_sub(
				"SELECT MIN(hotness) AS base, MAX(hotness)-MIN(hotness) AS spread FROM ^posts WHERE type='S'"
			));

			$nextpostid = 0;

			while (1) {
				$songs = as_db_read_all_assoc(as_db_query_sub(
					"SELECT postid, title, hotness FROM ^posts WHERE postid>=# AND type='S' ORDER BY postid LIMIT 100",
					$nextpostid
				));

				if (!count($songs))
					break;

				foreach ($songs as $song) {
					$this->sitemap_output(as_q_request($song['postid'], $song['title']),
						0.1 + 0.9 * ($song['hotness'] - $hotstats['base']) / (1 + $hotstats['spread']));
					$nextpostid = max($nextpostid, $song['postid'] + 1);
				}
			}
		}


		// User pages

		if (!AS_FINAL_EXTERNAL_USERS && as_opt('xml_sitemap_show_users')) {
			$nextuserid = 0;

			while (1) {
				$users = as_db_read_all_assoc(as_db_query_sub(
					"SELECT userid, handle FROM ^users WHERE userid>=# ORDER BY userid LIMIT 100",
					$nextuserid
				));

				if (!count($users))
					break;

				foreach ($users as $user) {
					$this->sitemap_output('user/' . $user['handle'], 0.25);
					$nextuserid = max($nextuserid, $user['userid'] + 1);
				}
			}
		}


		// Tag pages

		if (as_using_tags() && as_opt('xml_sitemap_show_tag_qs')) {
			$nextwordid = 0;

			while (1) {
				$tagwords = as_db_read_all_assoc(as_db_query_sub(
					"SELECT wordid, word, tagcount FROM ^words WHERE wordid>=# AND tagcount>0 ORDER BY wordid LIMIT 100",
					$nextwordid
				));

				if (!count($tagwords))
					break;

				foreach ($tagwords as $tagword) {
					$this->sitemap_output('tag/' . $tagword['word'], 0.5 / (1 + (1 / $tagword['tagcount']))); // priority between 0.25 and 0.5 depending on tag frequency
					$nextwordid = max($nextwordid, $tagword['wordid'] + 1);
				}
			}
		}


		// Song list for each category

		if (as_using_categories() && as_opt('xml_sitemap_show_category_qs')) {
			$nextcategoryid = 0;

			while (1) {
				$categories = as_db_read_all_assoc(as_db_query_sub(
					"SELECT categoryid, backpath FROM ^categories WHERE categoryid>=# AND qcount>0 ORDER BY categoryid LIMIT 2",
					$nextcategoryid
				));

				if (!count($categories))
					break;

				foreach ($categories as $category) {
					$this->sitemap_output('songs/' . implode('/', array_reverse(explode('/', $category['backpath']))), 0.5);
					$nextcategoryid = max($nextcategoryid, $category['categoryid'] + 1);
				}
			}
		}


		// Pages in category browser

		if (as_using_categories() && as_opt('xml_sitemap_show_categories')) {
			$this->sitemap_output('categories', 0.5);

			$nextcategoryid = 0;

			while (1) { // only find categories with a child
				$categories = as_db_read_all_assoc(as_db_query_sub(
					"SELECT parent.categoryid, parent.backpath FROM ^categories AS parent " .
					"JOIN ^categories AS child ON child.parentid=parent.categoryid WHERE parent.categoryid>=# GROUP BY parent.categoryid LIMIT 100",
					$nextcategoryid
				));

				if (!count($categories))
					break;

				foreach ($categories as $category) {
					$this->sitemap_output('categories/' . implode('/', array_reverse(explode('/', $category['backpath']))), 0.5);
					$nextcategoryid = max($nextcategoryid, $category['categoryid'] + 1);
				}
			}
		}

		echo "</urlset>\n";

		return null;
	}


	private function sitemap_output($request, $priority)
	{
		echo "\t<url>\n" .
			"\t\t<loc>" . as_xml(as_path($request, null, as_opt('site_url'))) . "</loc>\n" .
			"\t\t<priority>" . max(0, min(1.0, $priority)) . "</priority>\n" .
			"\t</url>\n";
	}
}
