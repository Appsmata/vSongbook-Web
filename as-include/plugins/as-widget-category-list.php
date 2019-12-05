<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Widget module class for activity count plugin


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

class as_category_list
{
	private $themeobject;

	public function allow_template($template)
	{
		return true;
	}

	public function allow_region($region)
	{
		return $region == 'side';
	}

	public function output_widget($region, $place, $themeobject, $template, $request, $as_content)
	{
		$this->themeobject = $themeobject;

		if (isset($as_content['navigation']['cat'])) {
			$nav = $as_content['navigation']['cat'];
		} else {
			$selectspec = as_db_category_nav_selectspec(null, true, false, true);
			$selectspec['caching'] = array(
				'key' => 'as_db_category_nav_selectspec:default:full',
				'ttl' => as_opt('caching_catwidget_time'),
			);
			$navcategories = as_db_single_select($selectspec);
			$nav = as_category_navigation($navcategories);
		}

		$this->themeobject->output('<h2>' . as_lang_html('main/nav_categories') . '</h2>');
		$this->themeobject->set_context('nav_type', 'cat');
		$this->themeobject->nav_list($nav, 'nav-cat', 1);
		$this->themeobject->nav_clear('cat');
		$this->themeobject->clear_context('nav_type');
	}
}
