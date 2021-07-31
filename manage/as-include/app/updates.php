<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Definitions relating to favorites and updates in the database tables


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

if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}


// Character codes for the different types of entity that can be followed (entitytype columns)

define('AS_ENTITY_SONG', 'S');
define('AS_ENTITY_USER', 'U');
define('AS_ENTITY_TAG', 'T');
define('AS_ENTITY_CATEGORY', 'C');
define('AS_ENTITY_NONE', '-');


// Character codes for the different types of updates on a post (updatetype columns)

define('AS_UPDATE_CATEGORY', 'R'); // songs only, category changed
define('AS_UPDATE_CLOSED', 'C'); // songs only, closed or reopened
define('AS_UPDATE_CONTENT', 'E'); // title or content edited
define('AS_UPDATE_PARENT', 'M'); // e.g. comment moved when converting its parent review to a comment
define('AS_UPDATE_SELECTED', 'S'); // reviews only, removed if unselected
define('AS_UPDATE_TAGS', 'T'); // songs only
define('AS_UPDATE_TYPE', 'Y'); // e.g. review to comment
define('AS_UPDATE_VISIBLE', 'H'); // hidden or reshown


// Character codes for types of update that only appear in the streams tables, not on the posts themselves

define('AS_UPDATE_FOLLOWS', 'F'); // if a new song was posted related to one of its reviews, or for a comment that follows another
define('AS_UPDATE_C_FOR_Q', 'U'); // if comment created was on a song of the user whose stream this appears in
define('AS_UPDATE_C_FOR_A', 'N'); // if comment created was on an review of the user whose stream this appears in
