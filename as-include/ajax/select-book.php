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

$inbook = as_post_text('bookid');

$userid = as_get_logged_in_userid();
$songlist = as_db_select_with_pending( as_db_posts_select($userid, $inbook) );

echo "AS_AJAX_RESPONSE\n1\n";

$htmlresult = '';
foreach ($songlist as $sk => $song)
{
    $htmlresult .= '<div class="songlist-item d-flex flex-row w-100 p-2 border-bottom" onclick="generateMessageArea(this, '.$song['postid'].')">
        <div class="w-100">
            <div class="title">'.$song['number'].'. '.$song['title'].'</div>
            <div class="small last-message">'.$song['content'].'</div>
        </div>
    </div>';
}

echo $htmlresult;