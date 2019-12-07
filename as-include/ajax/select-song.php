<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax single clicks on comments


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.appsmata.org/license.php
*/

require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'util/string.php';
require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'app/format.php';

$songid = as_post_text('selectedsong');

$userid = as_get_logged_in_userid();
$song = as_db_select_with_pending( as_db_full_post_selectspec($userid, $songid) );
$songconts = explode("\n\n", $song['content']);

echo "AS_AJAX_RESPONSE\n1\n";

$htmlresult = '';
$htmlresult .= $song['number'].'# '.$song['title'].'xxx';
foreach ($songconts as $lyrics)
{
	$htmlresult .= '<div class="p-1 my-1 mx-3 rounded bg-white shadow-sm message-item">';
	$htmlresult .= '<div class="d-flex flex-row">';
	$htmlresult .= '<div class="body m-1 mr-2">';
	$htmlresult .= nl2br( $lyrics );
	$htmlresult .= '</div></div></div>';
}
//$this->output('<div class="p-1 my-1 mx-3 rounded bg-white shadow-sm message-item">
//<div class="d-flex flex-row"><div class="body m-1 mr-2">'..'</div></div></div>');
echo $htmlresult;