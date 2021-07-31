<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/opensearch-support/as-opensearch-layer.php
	Description: Theme layer class for OpenSearch plugin


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
	public function head_links()
	{
		as_html_theme_base::head_links();

		$this->output('<link rel="search" type="application/opensearchdescription+xml" title="' . as_html(as_opt('site_title')) . '" href="' . as_path_html('opensearch.xml') . '"/>');
	}
}
