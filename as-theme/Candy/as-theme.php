<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/appsmata/

	File: as-theme/Candy/as-theme.php
	Description: Override base theme class for Candy theme


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://github.com/appsmata/license.php
*/

class as_html_theme extends as_html_theme_base
{
	// use new ranking layout
	protected $ranking_block_layout = true;
	protected $theme = 'candy';

	public function nav_user_search() // reverse the usual order
	{
		$this->search();
		$this->nav('user');
	}
}
