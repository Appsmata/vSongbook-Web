<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/opensearch-support/as-opensearch-page.php
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

class as_opensearch_xml
{
	public function match_request($request)
	{
		return ($request == 'opensearch.xml');
	}

	public function process_request($request)
	{
		@ini_set('display_errors', 0); // we don't want to show PHP errors inside XML

		$titlexml = as_xml(as_opt('site_title'));
		$template = str_replace('_searchTerms_placeholder_', '{searchTerms}', as_path_absolute('search', array('q' => '_searchTerms_placeholder_')));

		header('Content-type: text/xml; charset=utf-8');

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/" xmlns:moz="http://www.mozilla.org/2006/browser/search/">' . "\n";

		echo "\t<ShortName>" . $titlexml . "</ShortName>\n";
		echo "\t<Description>" . as_xml(as_lang('main/search_button')) . ' ' . $titlexml . "</Description>\n";
		echo "\t" . '<Url type="text/html" method="get" template="' . as_xml($template) . '"/>' . "\n";
		echo "\t<InputEncoding>UTF-8</InputEncoding>\n";

		echo '</OpenSearchDescription>' . "\n";

		return null;
	}
}
