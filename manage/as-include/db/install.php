<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database-level functions for installation and upgrading


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

define('AS_DB_VERSION_CURRENT', 67);


/**
 * Return the column type for user ids after verifying it is one of the legal options
 */
function as_db_user_column_type_verify()
{
	$coltype = strtoupper(as_get_mysql_user_column_type());

	switch ($coltype) {
		case 'SMALLINT':
		case 'MEDIUMINT':
		case 'INT':
		case 'BIGINT':
		case 'SMALLINT UNSIGNED':
		case 'MEDIUMINT UNSIGNED':
		case 'INT UNSIGNED':
		case 'BIGINT UNSIGNED':
			// these are all OK
			break;

		default:
			if (!preg_match('/VARCHAR\([0-9]+\)/', $coltype))
				as_fatal_error('Specified user column type is not one of allowed values - please read documentation');
			break;
	}

	return $coltype;
}


/**
 * Return an array of table definitions. For each element of the array, the key is the table name (without prefix)
 * and the value is an array of column definitions, [column name] => [definition]. The column name is omitted for indexes.
 */
function as_db_table_definitions()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'db/maxima.php';
	require_once AS_INCLUDE_DIR . 'app/users.php';

	/*
		Important note on character encoding in database and PHP connection to MySQL

		[this note is no longer relevant since we *do* explicitly set the connection character set since APS 1.5 - see as-db.php
	*/

	/*
		Other notes on the definitions below

		* In MySQL versions prior to 5.0.3, VARCHAR(x) columns will be silently converted to TEXT where x>255

		* See box at top of /as-include/app/recalc.php for a list of redundant (non-normal) information in the database

		* Starting in version 1.2, we explicitly name keys and foreign key constraints, instead of allowing MySQL
		  to name these by default. Our chosen names match the default names that MySQL would have assigned, and
		  indeed *did* assign for people who installed an earlier version of APS. By naming them explicitly, we're
		  on more solid ground for possible future changes to indexes and foreign keys in the schema.

		* There are other foreign key constraints that it would be valid to add, but that would not serve much
		  purpose in terms of preventing inconsistent data being retrieved, and would just slow down some queries.

		* We name some columns here in a not entirely intuitive way. The reason is to match the names of columns in
		  other tables which are of a similar nature. This will save time and space when combining several SELECT
		  queries together via a UNION in as_db_multi_select() - see comments in as-db.php for more information.
	*/

	$useridcoltype = as_db_user_column_type_verify();

	$tables = array(
		'users' => array(
			'userid' => $useridcoltype . ' NOT NULL AUTO_INCREMENT',
			'created' => 'DATETIME NOT NULL',
			'createip' => 'VARBINARY(16) NOT NULL', // INET6_ATON of IP address when created
			'firstname' => 'VARCHAR(' . AS_DB_MAX_HANDLE_LENGTH . ') NOT NULL', // firstname
			'lastname' => 'VARCHAR(' . AS_DB_MAX_HANDLE_LENGTH . ') NOT NULL', // lastname
			'country' => 'VARCHAR(' . AS_DB_MAX_HANDLE_LENGTH . ') NOT NULL', // country
			'mobile' => 'VARCHAR(' . AS_DB_MAX_HANDLE_LENGTH . ') NOT NULL', // mobile
			'gender' => 'INT UNSIGNED', // city
			'city' => 'INT UNSIGNED', // city
			'church' => 'INT UNSIGNED', // church
			'email' => 'VARCHAR(' . AS_DB_MAX_EMAIL_LENGTH . ') NOT NULL',
			'handle' => 'VARCHAR(' . AS_DB_MAX_HANDLE_LENGTH . ') NOT NULL', // username
			'avatarblobid' => 'BIGINT UNSIGNED', // blobid of stored avatar
			'avatarwidth' => 'SMALLINT UNSIGNED', // pixel width of stored avatar
			'avatarheight' => 'SMALLINT UNSIGNED', // pixel height of stored avatar
			'passsalt' => 'BINARY(16)', // salt used to calculate passcheck - null if no password set for direct signin
			'passcheck' => 'BINARY(20)', // checksum from password and passsalt - null if no password set for direct signin
			'passhash' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL', // password_hash
			'level' => 'TINYINT UNSIGNED NOT NULL', // basic, editor, admin, etc...
			'signedin' => 'DATETIME NOT NULL', // time of last signin
			'signinip' => 'VARBINARY(16) NOT NULL', // INET6_ATON of IP address of last signin
			'written' => 'DATETIME', // time of last write action done by user
			'writeip' => 'VARBINARY(16)', // INET6_ATON of IP address of last write action done by user
			'emailcode' => 'CHAR(8) CHARACTER SET ascii NOT NULL DEFAULT \'\'', // for email confirmation or password reset
			'sessioncode' => 'CHAR(8) CHARACTER SET ascii NOT NULL DEFAULT \'\'', // for comparing against session cookie in browser
			'sessionsource' => 'VARCHAR (16) CHARACTER SET ascii DEFAULT \'\'', // e.g. facebook, openid, etc...
			'flags' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', // see constants at top of /as-include/app/users.php
			'wallposts' => 'MEDIUMINT NOT NULL DEFAULT 0', // cached count of wall posts
			'PRIMARY KEY (userid)',
			'KEY mobile (mobile)',
			'KEY email (email)',
			'KEY handle (handle)',
			'KEY level (level)',
			'KEY created (created, level, flags)',
		),

		'cities' => array(
			'cityid' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'title' => 'VARCHAR(' . AS_DB_MAX_CAT_PAGE_TITLE_LENGTH . ') NOT NULL', // category name
			'country' => 'VARCHAR(' . AS_DB_MAX_CAT_PAGE_TAGS_LENGTH . ') NOT NULL', // slug (url fragment) used to identify category
			'content' => 'VARCHAR(' . AS_DB_MAX_CAT_CONTENT_LENGTH . ') NOT NULL DEFAULT \'\'', // description of category
			'ccount' => 'INT UNSIGNED NOT NULL DEFAULT 0',
			'PRIMARY KEY (cityid)',
		),

		'churches' => array(
			'churchid' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'title' => 'VARCHAR(' . AS_DB_MAX_CAT_PAGE_TITLE_LENGTH . ') NOT NULL', // category name
			'city' => 'INT UNSIGNED NOT NULL DEFAULT 0',
			'content' => 'VARCHAR(' . AS_DB_MAX_CAT_CONTENT_LENGTH . ') NOT NULL DEFAULT \'\'', // description of category
			'ucount' => 'INT UNSIGNED NOT NULL DEFAULT 0',
			'PRIMARY KEY (churchid)',
		),

		'usersignins' => array(
			'userid' => $useridcoltype . ' NOT NULL',
			'source' => 'VARCHAR (16) CHARACTER SET ascii NOT NULL', // e.g. facebook, openid, etc...
			'identifier' => 'VARBINARY (1024) NOT NULL', // depends on source, e.g. Facebook uid or OpenID url
			'identifiermd5' => 'BINARY (16) NOT NULL', // used to reduce size of index on identifier
			'KEY source (source, identifiermd5)',
			'KEY userid (userid)',
		),

		'userlevels' => array(
			'userid' => $useridcoltype . ' NOT NULL', // the user who has this level
			'entitytype' => 'CHAR(1) CHARACTER SET ascii NOT NULL', // see /as-include/app/updates.php
			'entityid' => 'INT UNSIGNED NOT NULL', // relevant postid / userid / tag wordid / categoryid
			'level' => 'TINYINT UNSIGNED', // if not NULL, special permission level for that user and that entity
			'UNIQUE userid (userid, entitytype, entityid)',
			'KEY entitytype (entitytype, entityid)',
		),

		'userprofile' => array(
			'userid' => $useridcoltype . ' NOT NULL',
			'title' => 'VARCHAR(' . AS_DB_MAX_PROFILE_TITLE_LENGTH . ') NOT NULL', // profile field name
			'content' => 'VARCHAR(' . AS_DB_MAX_PROFILE_CONTENT_LENGTH . ') NOT NULL', // profile field value
			'UNIQUE userid (userid,title)',
		),

		'userfields' => array(
			'fieldid' => 'SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT',
			'title' => 'VARCHAR(' . AS_DB_MAX_PROFILE_TITLE_LENGTH . ') NOT NULL', // to match title column in userprofile table
			'content' => 'VARCHAR(' . AS_DB_MAX_PROFILE_TITLE_LENGTH . ')', // label for display on user profile pages - NULL means use default
			'position' => 'SMALLINT UNSIGNED NOT NULL',
			'flags' => 'TINYINT UNSIGNED NOT NULL', // AS_FIELD_FLAGS_* at top of /as-include/app/users.php
			'permit' => 'TINYINT UNSIGNED', // minimum user level required to view (uses AS_PERMIT_* constants), null means no restriction
			'PRIMARY KEY (fieldid)',
		),

		'messages' => array(
			'messageid' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'type' => "ENUM('PUBLIC', 'PRIVATE') NOT NULL DEFAULT 'PRIVATE'",
			'fromuserid' => $useridcoltype,
			'touserid' => $useridcoltype,
			'fromhidden' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
			'tohidden' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
			'content' => 'VARCHAR(' . AS_DB_MAX_CONTENT_LENGTH . ') NOT NULL',
			'format' => 'VARCHAR(' . AS_DB_MAX_FORMAT_LENGTH . ') CHARACTER SET ascii NOT NULL',
			'created' => 'DATETIME NOT NULL',
			'PRIMARY KEY (messageid)',
			'KEY type (type, fromuserid, touserid, created)',
			'KEY touserid (touserid, type, created)',
			'KEY fromhidden (fromhidden)',
			'KEY tohidden (tohidden)',
		),

		'userfavorites' => array(
			'userid' => $useridcoltype . ' NOT NULL', // the user who favorited the entity
			'entitytype' => 'CHAR(1) CHARACTER SET ascii NOT NULL', // see /as-include/app/updates.php
			'entityid' => 'INT UNSIGNED NOT NULL', // favorited postid / userid / tag wordid / categoryid
			'nouserevents' => 'TINYINT UNSIGNED NOT NULL', // do we skip writing events to the user stream?
			'PRIMARY KEY (userid, entitytype, entityid)',
			'KEY userid (userid, nouserevents)',
			'KEY entitytype (entitytype, entityid, nouserevents)',
		),

		'usernotices' => array(
			'noticeid' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'userid' => $useridcoltype . ' NOT NULL', // the user to whom the notice is directed
			'content' => 'VARCHAR(' . AS_DB_MAX_CONTENT_LENGTH . ') NOT NULL',
			'format' => 'VARCHAR(' . AS_DB_MAX_FORMAT_LENGTH . ') CHARACTER SET ascii NOT NULL',
			'tags' => 'VARCHAR(' . AS_DB_MAX_CAT_PAGE_TAGS_LENGTH . ')', // any additional information for a plugin to access
			'created' => 'DATETIME NOT NULL',
			'PRIMARY KEY (noticeid)',
			'KEY userid (userid, created)',
		),

		'userevents' => array(
			'userid' => $useridcoltype . ' NOT NULL', // the user to be informed about this event in their updates
			'entitytype' => 'CHAR(1) CHARACTER SET ascii NOT NULL', // see /as-include/app/updates.php
			'entityid' => 'INT UNSIGNED NOT NULL', // favorited source of event - see userfavorites table - 0 means not from a favorite
			'songid' => 'INT UNSIGNED NOT NULL', // the affected song
			'lastpostid' => 'INT UNSIGNED NOT NULL', // what part of song was affected
			'updatetype' => 'CHAR(1) CHARACTER SET ascii', // what was done to this part - see /as-include/app/updates.php
			'lastuserid' => $useridcoltype, // which user (if any) did this action
			'updated' => 'DATETIME NOT NULL', // when the event happened
			'KEY userid (userid, updated)', // for truncation
			'KEY songid (songid, userid)', // to limit number of events per song per stream
		),

		'sharedevents' => array(
			'entitytype' => 'CHAR(1) CHARACTER SET ascii NOT NULL', // see /as-include/app/updates.php
			'entityid' => 'INT UNSIGNED NOT NULL', // see userfavorites table
			'songid' => 'INT UNSIGNED NOT NULL', // see userevents table
			'lastpostid' => 'INT UNSIGNED NOT NULL', // see userevents table
			'updatetype' => 'CHAR(1) CHARACTER SET ascii', // see userevents table
			'lastuserid' => $useridcoltype, // see userevents table
			'updated' => 'DATETIME NOT NULL', // see userevents table
			'KEY entitytype (entitytype, entityid, updated)', // for truncation
			'KEY songid (songid, entitytype, entityid)', // to limit number of events per song per stream
		),

		'cookies' => array(
			'cookieid' => 'BIGINT UNSIGNED NOT NULL',
			'created' => 'DATETIME NOT NULL',
			'createip' => 'VARBINARY(16) NOT NULL', // INET6_ATON of IP address when cookie created
			'written' => 'DATETIME', // time of last write action done by anon user with cookie
			'writeip' => 'VARBINARY(16)', // INET6_ATON of IP address of last write action done by anon user with cookie
			'PRIMARY KEY (cookieid)',
		),

		'categories' => array(
			'categoryid' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'parentid' => 'INT UNSIGNED',
			'title' => 'VARCHAR(' . AS_DB_MAX_CAT_PAGE_TITLE_LENGTH . ') NOT NULL', // category name
			'tags' => 'VARCHAR(' . AS_DB_MAX_CAT_PAGE_TAGS_LENGTH . ') NOT NULL', // slug (url fragment) used to identify category
			'content' => 'VARCHAR(' . AS_DB_MAX_CAT_CONTENT_LENGTH . ') NOT NULL DEFAULT \'\'', // description of category
			'qcount' => 'INT UNSIGNED NOT NULL DEFAULT 0',
			'enabled' => 'INT UNSIGNED NOT NULL DEFAULT 0',
			'position' => 'SMALLINT UNSIGNED NOT NULL',
			// full slug path for category, with forward slash separators, in reverse order to make index from effective
			'backpath' => 'VARCHAR(' . (AS_CATEGORY_DEPTH * (AS_DB_MAX_CAT_PAGE_TAGS_LENGTH + 1)) . ') NOT NULL DEFAULT \'\'',
			'PRIMARY KEY (categoryid)',
			'UNIQUE parentid (parentid, tags)',
			'UNIQUE parentid_2 (parentid, position)',
			'KEY backpath (backpath(' . AS_DB_MAX_CAT_PAGE_TAGS_LENGTH . '))',
		),

		'pages' => array(
			'pageid' => 'SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT',
			'title' => 'VARCHAR(' . AS_DB_MAX_CAT_PAGE_TITLE_LENGTH . ') NOT NULL', // title for navigation
			'nav' => 'CHAR(1) CHARACTER SET ascii NOT NULL', // which navigation does it go in (M=main, F=footer, B=before main, O=opposite main, other=none)
			'position' => 'SMALLINT UNSIGNED NOT NULL', // global ordering, which allows links to be ordered within each nav area
			'flags' => 'TINYINT UNSIGNED NOT NULL', // local or external, open in new window?
			'permit' => 'TINYINT UNSIGNED', // is there a minimum user level required for it (uses AS_PERMIT_* constants), null means no restriction
			'tags' => 'VARCHAR(' . AS_DB_MAX_CAT_PAGE_TAGS_LENGTH . ') NOT NULL', // slug (url fragment) for page, or url for external pages
			'heading' => 'VARCHAR(' . AS_DB_MAX_TITLE_LENGTH . ')', // for display within <h1> tags
			'content' => 'MEDIUMTEXT', // remainder of page HTML
			'PRIMARY KEY (pageid)',
			'KEY tags (tags)',
			'UNIQUE `position` (position)',
		),

		'widgets' => array(
			'widgetid' => 'SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT',
			// full region: FT=very top of page, FH=below nav area, FL=above footer, FB = very bottom of page
			// side region: ST=top of side, SH=below sidebar, SL=below categories, SB=very bottom of side
			// main region: MT=top of main, MH=below page title, ML=above links, MB=very bottom of main region
			'place' => 'CHAR(2) CHARACTER SET ascii NOT NULL',
			'position' => 'SMALLINT UNSIGNED NOT NULL', // global ordering, which allows widgets to be ordered within each place
			'tags' => 'VARCHAR(' . AS_DB_MAX_WIDGET_TAGS_LENGTH . ') CHARACTER SET ascii NOT NULL', // comma-separated list of templates to display on
			'title' => 'VARCHAR(' . AS_DB_MAX_WIDGET_TITLE_LENGTH . ') NOT NULL', // name of widget module that should be displayed
			'PRIMARY KEY (widgetid)',
			'UNIQUE `position` (position)',
		),

		'posts' => array(
			'postid' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'type' => "ENUM('S', 'R', 'C', 'S_HIDDEN', 'R_HIDDEN', 'C_HIDDEN', 'S_QUEUED', 'R_QUEUED', 'C_QUEUED', 'NOTE') NOT NULL",
			'parentid' => 'INT UNSIGNED', // for follow on songs, all reviews and comments
			'categoryid' => 'INT UNSIGNED', // this is the canonical final category id
			'catidpath1' => 'INT UNSIGNED', // the catidpath* columns are calculated from categoryid, for the full hierarchy of that category
			'catidpath2' => 'INT UNSIGNED', // note that AS_CATEGORY_DEPTH=4
			'catidpath3' => 'INT UNSIGNED',
			'acount' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', // number of reviews (for songs)
			'amaxthumb' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', // highest netthumbs of child reviews (for songs)
			'selchildid' => 'INT UNSIGNED', // selected review (for songs)
			// if closed due to being a duplicate, this is the postid of that other song
			// if closed for another reason, that reason should be added as a comment on the song, and this field is the comment's id
			'closedbyid' => 'INT UNSIGNED', // not null means song is closed
			'userid' => $useridcoltype, // which user wrote it
			'cookieid' => 'BIGINT UNSIGNED', // which cookie wrote it, if an anonymous post
			'createip' => 'VARBINARY(16)', // INET6_ATON of IP address used to create the post
			'lastuserid' => $useridcoltype, // which user last modified it
			'lastip' => 'VARBINARY(16)', // INET6_ATON of IP address which last modified the post
			'thumbsup' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
			'thumbsdown' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
			'netthumbs' => 'SMALLINT NOT NULL DEFAULT 0',
			'lastviewip' => 'VARBINARY(16)', // INET6_ATON of IP address which last viewed the post
			'views' => 'INT UNSIGNED NOT NULL DEFAULT 0',
			'hotness' => 'FLOAT',
			'flagcount' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
			'format' => 'VARCHAR(' . AS_DB_MAX_FORMAT_LENGTH . ') CHARACTER SET ascii NOT NULL DEFAULT \'\'', // format of content, e.g. 'html'
			'created' => 'DATETIME NOT NULL',
			'updated' => 'DATETIME', // time of last update
			'updatetype' => 'CHAR(1) CHARACTER SET ascii', // see /as-include/app/updates.php
			'number' => 'INT UNSIGNED NOT NULL DEFAULT 0',
			'title' => 'VARCHAR(' . AS_DB_MAX_TITLE_LENGTH . ')',
			'songkey' => 'VARCHAR(10)',
			'content' => 'VARCHAR(' . AS_DB_MAX_CONTENT_LENGTH . ')',
			'alias' => 'VARCHAR(' . AS_DB_MAX_TITLE_LENGTH . ')',
			'tags' => 'VARCHAR(' . AS_DB_MAX_TAGS_LENGTH . ')', // string of tags separated by commas
			'name' => 'VARCHAR(' . AS_DB_MAX_NAME_LENGTH . ')', // name of author if post anonymonus
			'notify' => 'VARCHAR(' . AS_DB_MAX_EMAIL_LENGTH . ')', // email address, or @ to get from user, or NULL for none
			'PRIMARY KEY (postid)',
			'KEY type (type, created)', // for getting recent songs, reviews, comments
			'KEY type_2 (type, acount, created)', // for getting unreviewed songs
			'KEY type_4 (type, netthumbs, created)', // for getting posts with the most thumbs
			'KEY type_5 (type, views, created)', // for getting songs with the most views
			'KEY type_6 (type, hotness)', // for getting 'hot' songs
			'KEY type_7 (type, amaxthumb, created)', // for getting songs with no upthumbd reviews
			'KEY parentid (parentid, type)', // for getting a song's reviews, any post's comments and follow-on songs
			'KEY userid (userid, type, created)', // for recent songs, reviews or comments by a user
			'KEY selchildid (selchildid, type, created)', // for counting how many of a user's reviews have been selected, unselected qs
			'KEY closedbyid (closedbyid)', // for the foreign key constraint
			'KEY catidpath1 (catidpath1, type, created)', // for getting song, reviews or comments in a specific level category
			'KEY catidpath2 (catidpath2, type, created)', // note that AS_CATEGORY_DEPTH=4
			'KEY catidpath3 (catidpath3, type, created)',
			'KEY categoryid (categoryid, type, created)', // this can also be used for searching the equivalent of catidpath4
			'KEY createip (createip, created)', // for getting posts created by a specific IP address
			'KEY updated (updated, type)', // for getting recent edits across all categories
			'KEY flagcount (flagcount, created, type)', // for getting posts with the most flags
			'KEY catidpath1_2 (catidpath1, updated, type)', // for getting recent edits in a specific level category
			'KEY catidpath2_2 (catidpath2, updated, type)', // note that AS_CATEGORY_DEPTH=4
			'KEY catidpath3_2 (catidpath3, updated, type)',
			'KEY categoryid_2 (categoryid, updated, type)',
			'KEY lastuserid (lastuserid, updated, type)', // for getting posts edited by a specific user
			'KEY lastip (lastip, updated, type)', // for getting posts edited by a specific IP address
			'CONSTRAINT ^posts_ibfk_2 FOREIGN KEY (parentid) REFERENCES ^posts(postid)', // ^posts_ibfk_1 is set later on userid
			'CONSTRAINT ^posts_ibfk_3 FOREIGN KEY (categoryid) REFERENCES ^categories(categoryid) ON DELETE SET NULL',
			'CONSTRAINT ^posts_ibfk_4 FOREIGN KEY (closedbyid) REFERENCES ^posts(postid)',
		),

		'blobs' => array(
			'blobid' => 'BIGINT UNSIGNED NOT NULL',
			'format' => 'VARCHAR(' . AS_DB_MAX_FORMAT_LENGTH . ') CHARACTER SET ascii NOT NULL', // format e.g. 'jpeg', 'gif', 'png'
			'content' => 'MEDIUMBLOB', // null means it's stored on disk in AS_BLOBS_DIRECTORY
			'filename' => 'VARCHAR(' . AS_DB_MAX_BLOB_FILE_NAME_LENGTH . ')', // name of source file (if appropriate)
			'userid' => $useridcoltype, // which user created it
			'cookieid' => 'BIGINT UNSIGNED', // which cookie created it
			'createip' => 'VARBINARY(16)', // INET6_ATON of IP address that created it
			'created' => 'DATETIME', // when it was created
			'PRIMARY KEY (blobid)',
		),

		'words' => array(
			'wordid' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'word' => 'VARCHAR(' . AS_DB_MAX_WORD_LENGTH . ') NOT NULL',
			'titlecount' => 'INT UNSIGNED NOT NULL DEFAULT 0', // only counts one per post
			'contentcount' => 'INT UNSIGNED NOT NULL DEFAULT 0', // only counts one per post
			'tagwordcount' => 'INT UNSIGNED NOT NULL DEFAULT 0', // for words in tags - only counts one per post
			'tagcount' => 'INT UNSIGNED NOT NULL DEFAULT 0', // for tags as a whole - only counts one per post (though no duplicate tags anyway)
			'PRIMARY KEY (wordid)',
			'KEY word (word)',
			'KEY tagcount (tagcount)', // for sorting by most popular tags
		),

		'titlewords' => array(
			'postid' => 'INT UNSIGNED NOT NULL',
			'wordid' => 'INT UNSIGNED NOT NULL',
			'KEY postid (postid)',
			'KEY wordid (wordid)',
			'CONSTRAINT ^titlewords_ibfk_1 FOREIGN KEY (postid) REFERENCES ^posts(postid) ON DELETE CASCADE',
			'CONSTRAINT ^titlewords_ibfk_2 FOREIGN KEY (wordid) REFERENCES ^words(wordid)',
		),

		'contentwords' => array(
			'postid' => 'INT UNSIGNED NOT NULL',
			'wordid' => 'INT UNSIGNED NOT NULL',
			'count' => 'TINYINT UNSIGNED NOT NULL', // how many times word appears in the post - anything over 255 can be ignored
			'type' => "ENUM('S', 'R', 'C', 'NOTE') NOT NULL", // the post's type (copied here for quick searching)
			'songid' => 'INT UNSIGNED NOT NULL', // the id of the post's antecedent parent (here for quick searching)
			'KEY postid (postid)',
			'KEY wordid (wordid)',
			'CONSTRAINT ^contentwords_ibfk_1 FOREIGN KEY (postid) REFERENCES ^posts(postid) ON DELETE CASCADE',
			'CONSTRAINT ^contentwords_ibfk_2 FOREIGN KEY (wordid) REFERENCES ^words(wordid)',
		),

		'tagwords' => array(
			'postid' => 'INT UNSIGNED NOT NULL',
			'wordid' => 'INT UNSIGNED NOT NULL',
			'KEY postid (postid)',
			'KEY wordid (wordid)',
			'CONSTRAINT ^tagwords_ibfk_1 FOREIGN KEY (postid) REFERENCES ^posts(postid) ON DELETE CASCADE',
			'CONSTRAINT ^tagwords_ibfk_2 FOREIGN KEY (wordid) REFERENCES ^words(wordid)',
		),

		'posttags' => array(
			'postid' => 'INT UNSIGNED NOT NULL',
			'wordid' => 'INT UNSIGNED NOT NULL',
			'postcreated' => 'DATETIME NOT NULL', // created time of post (copied here for tag page's list of recent songs)
			'KEY postid (postid)',
			'KEY wordid (wordid, postcreated)',
			'CONSTRAINT ^posttags_ibfk_1 FOREIGN KEY (postid) REFERENCES ^posts(postid) ON DELETE CASCADE',
			'CONSTRAINT ^posttags_ibfk_2 FOREIGN KEY (wordid) REFERENCES ^words(wordid)',
		),

		'userthumbs' => array(
			'postid' => 'INT UNSIGNED NOT NULL',
			'userid' => $useridcoltype . ' NOT NULL',
			'thumb' => 'TINYINT NOT NULL', // -1, 0 or 1
			'flag' => 'TINYINT NOT NULL', // 0 or 1
			'thumbcreated' => 'DATETIME', // time of first thumb
			'thumbupdated' => 'DATETIME', // time of last thumb change
			'UNIQUE userid (userid, postid)',
			'KEY postid (postid)',
			'KEY thumbd (thumbcreated, thumbupdated)',
			'CONSTRAINT ^userthumbs_ibfk_1 FOREIGN KEY (postid) REFERENCES ^posts(postid) ON DELETE CASCADE',
		),

		// many userpoints columns could be unsigned but MySQL appears to mess up points calculations that go negative as a result

		'userpoints' => array(
			'userid' => $useridcoltype . ' NOT NULL',
			'points' => 'INT NOT NULL DEFAULT 0', // user's points as displayed, after final multiple
			'qposts' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of songs by user (excluding hidden/queued)
			'aposts' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of reviews by user (excluding hidden/queued)
			'cposts' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of comments by user (excluding hidden/queued)
			'aselects' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of songs by user where they've selected an review
			'aselecteds' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of reviews by user that have been selected as the best
			'qthumbsup' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of songs the user has thumbd up
			'qthumbsdown' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of songs the user has thumbd down
			'athumbsup' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of reviews the user has thumbd up
			'athumbsdown' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of reviews the user has thumbd down
			'cthumbsup' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of comments the user has thumbd up
			'cthumbsdown' => 'MEDIUMINT NOT NULL DEFAULT 0', // number of comments the user has thumbd down
			'qthumbds' => 'INT NOT NULL DEFAULT 0', // points from thumbs on this user's songs (applying per-song limits), before final multiple
			'athumbds' => 'INT NOT NULL DEFAULT 0', // points from thumbs on this user's reviews (applying per-review limits), before final multiple
			'cthumbds' => 'INT NOT NULL DEFAULT 0', // points from thumbs on this user's comments (applying per-comment limits), before final multiple
			'upthumbds' => 'INT NOT NULL DEFAULT 0', // number of up thumbs received on this user's songs or reviews
			'downthumbds' => 'INT NOT NULL DEFAULT 0', // number of down thumbs received on this user's songs or reviews
			'bonus' => 'INT NOT NULL DEFAULT 0', // bonus assigned by administrator to a user
			'PRIMARY KEY (userid)',
			'KEY points (points)',
		),

		'userlimits' => array(
			'userid' => $useridcoltype . ' NOT NULL',
			'action' => 'CHAR(1) CHARACTER SET ascii NOT NULL', // see constants at top of /as-include/app/limits.php
			'period' => 'INT UNSIGNED NOT NULL', // integer representing hour of last action
			'count' => 'SMALLINT UNSIGNED NOT NULL', // how many of this action has been performed within that hour
			'UNIQUE userid (userid, action)',
		),

		// most columns in iplimits have the same meaning as those in userlimits

		'iplimits' => array(
			'ip' => 'VARBINARY(16) NOT NULL', // INET6_ATON of IP address
			'action' => 'CHAR(1) CHARACTER SET ascii NOT NULL',
			'period' => 'INT UNSIGNED NOT NULL',
			'count' => 'SMALLINT UNSIGNED NOT NULL',
			'UNIQUE ip (ip, action)',
		),

		'options' => array(
			'title' => 'VARCHAR(' . AS_DB_MAX_OPTION_TITLE_LENGTH . ') NOT NULL', // name of option
			'content' => 'VARCHAR(' . AS_DB_MAX_CONTENT_LENGTH . ') NOT NULL', // value of option
			'PRIMARY KEY (title)',
		),

		'cache' => array(
			'type' => 'CHAR(8) CHARACTER SET ascii NOT NULL', // e.g. 'avXXX' for avatar sized to XXX pixels square
			'cacheid' => 'BIGINT UNSIGNED DEFAULT 0', // optional further identifier, e.g. blobid on which cache entry is based
			'content' => 'MEDIUMBLOB NOT NULL',
			'created' => 'DATETIME NOT NULL',
			'lastread' => 'DATETIME NOT NULL',
			'PRIMARY KEY (type, cacheid)',
			'KEY (lastread)',
		),

		'usermetas' => array(
			'userid' => $useridcoltype . ' NOT NULL',
			'title' => 'VARCHAR(' . AS_DB_MAX_META_TITLE_LENGTH . ') NOT NULL',
			'content' => 'VARCHAR(' . AS_DB_MAX_META_CONTENT_LENGTH . ') NOT NULL',
			'PRIMARY KEY (userid, title)',
		),

		'postmetas' => array(
			'postid' => 'INT UNSIGNED NOT NULL',
			'title' => 'VARCHAR(' . AS_DB_MAX_META_TITLE_LENGTH . ') NOT NULL',
			'content' => 'VARCHAR(' . AS_DB_MAX_META_CONTENT_LENGTH . ') NOT NULL',
			'PRIMARY KEY (postid, title)',
			'CONSTRAINT ^postmetas_ibfk_1 FOREIGN KEY (postid) REFERENCES ^posts(postid) ON DELETE CASCADE',
		),

		'categorymetas' => array(
			'categoryid' => 'INT UNSIGNED NOT NULL',
			'title' => 'VARCHAR(' . AS_DB_MAX_META_TITLE_LENGTH . ') NOT NULL',
			'content' => 'VARCHAR(' . AS_DB_MAX_META_CONTENT_LENGTH . ') NOT NULL',
			'PRIMARY KEY (categoryid, title)',
			'CONSTRAINT ^categorymetas_ibfk_1 FOREIGN KEY (categoryid) REFERENCES ^categories(categoryid) ON DELETE CASCADE',
		),

		'tagmetas' => array(
			'tag' => 'VARCHAR(' . AS_DB_MAX_WORD_LENGTH . ') NOT NULL',
			'title' => 'VARCHAR(' . AS_DB_MAX_META_TITLE_LENGTH . ') NOT NULL',
			'content' => 'VARCHAR(' . AS_DB_MAX_META_CONTENT_LENGTH . ') NOT NULL',
			'PRIMARY KEY (tag, title)',
		),

	);

	if (AS_FINAL_EXTERNAL_USERS) {
		unset($tables['users']);
		unset($tables['usersignins']);
		unset($tables['userprofile']);
		unset($tables['userfields']);
		unset($tables['messages']);

	} else {
		$userforeignkey = 'FOREIGN KEY (userid) REFERENCES ^users(userid)';

		$tables['usersignins'][] = 'CONSTRAINT ^usersignins_ibfk_1 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['userprofile'][] = 'CONSTRAINT ^userprofile_ibfk_1 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['posts'][] = 'CONSTRAINT ^posts_ibfk_1 ' . $userforeignkey . ' ON DELETE SET NULL';
		$tables['userthumbs'][] = 'CONSTRAINT ^userthumbs_ibfk_2 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['userlimits'][] = 'CONSTRAINT ^userlimits_ibfk_1 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['userfavorites'][] = 'CONSTRAINT ^userfavorites_ibfk_1 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['usernotices'][] = 'CONSTRAINT ^usernotices_ibfk_1 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['userevents'][] = 'CONSTRAINT ^userevents_ibfk_1 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['userlevels'][] = 'CONSTRAINT ^userlevels_ibfk_1 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['usermetas'][] = 'CONSTRAINT ^usermetas_ibfk_1 ' . $userforeignkey . ' ON DELETE CASCADE';
		$tables['messages'][] = 'CONSTRAINT ^messages_ibfk_1 FOREIGN KEY (fromuserid) REFERENCES ^users(userid) ON DELETE SET NULL';
		$tables['messages'][] = 'CONSTRAINT ^messages_ibfk_2 FOREIGN KEY (touserid) REFERENCES ^users(userid) ON DELETE SET NULL';
	}

	return $tables;
}


/**
 * Return array with all values from $array as keys
 * @param $array
 * @return array
 */
function as_array_to_keys($array)
{
	return empty($array) ? array() : array_combine($array, array_fill(0, count($array), true));
}


/**
 * Return a list of tables missing from the database, [table name] => [column/index definitions]
 * @param $definitions
 * @return array
 */
function as_db_missing_tables($definitions)
{
	$keydbtables = as_array_to_keys(as_db_list_tables(true));

	$missing = array();

	foreach ($definitions as $rawname => $definition)
		if (!isset($keydbtables[as_db_add_table_prefix($rawname)]))
			$missing[$rawname] = $definition;

	return $missing;
}


/**
 * Return a list of columns missing from $table in the database, given the full definition set in $definition
 * @param $table
 * @param $definition
 * @return array
 */
function as_db_missing_columns($table, $definition)
{
	$keycolumns = as_array_to_keys(as_db_read_all_values(as_db_query_sub('SHOW COLUMNS FROM ^' . $table)));

	$missing = array();

	foreach ($definition as $colname => $coldefn)
		if (!is_int($colname) && !isset($keycolumns[$colname]))
			$missing[$colname] = $coldefn;

	return $missing;
}


/**
 * Return the current version of the APS database, to determine need for DB upgrades
 */
function as_db_get_db_version()
{
	$definitions = as_db_table_definitions();

	if (count(as_db_missing_columns('options', $definitions['options'])) == 0) {
		$version = (int)as_db_read_one_value(as_db_query_sub("SELECT content FROM ^options WHERE title='db_version'"), true);

		if ($version > 0)
			return $version;
	}

	return null;
}


/**
 * Set the current version in the database
 * @param $version
 */
function as_db_set_db_version($version)
{
	require_once AS_INCLUDE_DIR . 'db/options.php';

	as_db_set_option('db_version', $version);
}


/**
 * Return a string describing what is wrong with the database, or false if everything is just fine
 */
function as_db_check_tables()
{
	as_db_query_raw('UNLOCK TABLES'); // we could be inside a lock tables block

	$version = as_db_read_one_value(as_db_query_raw('SELECT VERSION()'));

	if (((float)$version) < 4.1)
		as_fatal_error('MySQL version 4.1 or later is required - you appear to be running MySQL ' . $version);

	$definitions = as_db_table_definitions();
	$missing = as_db_missing_tables($definitions);

	if (count($missing) == count($definitions))
		return 'none';

	else {
		if (!isset($missing['options'])) {
			$version = as_db_get_db_version();

			if (isset($version) && ($version < AS_DB_VERSION_CURRENT))
				return 'old-version';
		}

		if (count($missing)) {
			if (defined('AS_MYSQL_USERS_PREFIX')) { // special case if two installations sharing users
				$datacount = 0;
				$datamissing = 0;

				foreach ($definitions as $rawname => $definition) {
					if (as_db_add_table_prefix($rawname) == (AS_MYSQL_TABLE_PREFIX . $rawname)) {
						$datacount++;

						if (isset($missing[$rawname]))
							$datamissing++;
					}
				}

				if ($datacount == $datamissing && $datamissing == count($missing))
					return 'non-users-missing';
			}

			return 'table-missing';

		} else
			foreach ($definitions as $table => $definition)
				if (count(as_db_missing_columns($table, $definition)))
					return 'column-missing';
	}

	return false;
}


/**
 * Install any missing database tables and/or columns and automatically set version as latest.
 * This is not suitable for use if the database needs upgrading.
 */
function as_db_install_tables()
{
	$definitions = as_db_table_definitions();

	$missingtables = as_db_missing_tables($definitions);

	foreach ($missingtables as $rawname => $definition) {
		as_db_query_sub(as_db_create_table_sql($rawname, $definition));

		if ($rawname == 'userfields')
			as_db_query_sub(as_db_default_userfields_sql());
	}

	foreach ($definitions as $table => $definition) {
		$missingcolumns = as_db_missing_columns($table, $definition);

		foreach ($missingcolumns as $colname => $coldefn)
			as_db_query_sub('ALTER TABLE ^' . $table . ' ADD COLUMN ' . $colname . ' ' . $coldefn);
	}

	as_db_set_db_version(AS_DB_VERSION_CURRENT);
}


/**
 * Return the SQL command to create a table with $rawname and $definition obtained from as_db_table_definitions()
 * @param $rawname
 * @param $definition
 * @return string
 */
function as_db_create_table_sql($rawname, $definition)
{
	$querycols = '';
	foreach ($definition as $colname => $coldef)
		if (isset($coldef))
			$querycols .= (strlen($querycols) ? ', ' : '') . (is_int($colname) ? $coldef : ($colname . ' ' . $coldef));

	return 'CREATE TABLE ^' . $rawname . ' (' . $querycols . ') ENGINE=InnoDB CHARSET=utf8';
}


/**
 * Return the SQL to create the default entries in the userfields table (before 1.3 these were hard-coded in PHP)
 */
function as_db_default_userfields_sql()
{
	require_once AS_INCLUDE_DIR . 'app/options.php';

	$profileFields = array(
		array(
			'title' => 'name',
			'position' => 1,
			'flags' => 0,
			'permit' => AS_PERMIT_ALL,
		),
		array(
			'title' => 'location',
			'position' => 2,
			'flags' => 0,
			'permit' => AS_PERMIT_ALL,
		),
		array(
			'title' => 'website',
			'position' => 3,
			'flags' => AS_FIELD_FLAGS_LINK_URL,
			'permit' => AS_PERMIT_ALL,
		),
		array(
			'title' => 'about',
			'position' => 4,
			'flags' => AS_FIELD_FLAGS_MULTI_LINE,
			'permit' => AS_PERMIT_ALL,
		),
	);

	$sql = 'INSERT INTO ^userfields (title, position, flags, permit) VALUES'; // content column will be NULL, meaning use default from lang files

	foreach ($profileFields as $field) {
		$sql .= sprintf('("%s", %d, %d, %d), ', as_db_escape_string($field['title']), $field['position'], $field['flags'], $field['permit']);
	}

	$sql = substr($sql, 0, -2);

	return $sql;
}


/**
 * Upgrade the database schema to the latest version, outputting progress to the browser
 */
function as_db_upgrade_tables()
{
	require_once AS_INCLUDE_DIR . 'app/recalc.php';

	$definitions = as_db_table_definitions();
	$keyrecalc = array();

	// Write-lock all APS tables before we start so no one can read or write anything

	$keydbtables = as_array_to_keys(as_db_list_tables(true));

	foreach ($definitions as $rawname => $definition)
		if (isset($keydbtables[as_db_add_table_prefix($rawname)]))
			$locks[] = '^' . $rawname . ' WRITE';

	$locktablesquery = 'LOCK TABLES ' . implode(', ', $locks);

	as_db_upgrade_query($locktablesquery);

	// Upgrade it step-by-step until it's up to date (do LOCK TABLES after ALTER TABLE because the lock can sometimes be lost)

	// message (used in sprintf) for skipping shared user tables
	$skipMessage = 'Skipping upgrading %s table since it was already upgraded by another APS site sharing it.';

	while (1) {
		$version = as_db_get_db_version();

		if ($version >= AS_DB_VERSION_CURRENT)
			break;

		$newversion = $version + 1;

		as_db_upgrade_progress(AS_DB_VERSION_CURRENT - $version . ' upgrade step/s remaining...');

		switch ($newversion) {
			// Up to here: Version 1.0 beta 1

			case 2:
				as_db_upgrade_query('ALTER TABLE ^posts DROP COLUMN thumbs, ADD COLUMN thumbsup ' . $definitions['posts']['thumbsup'] .
					' AFTER cookieid, ADD COLUMN thumbsdown ' . $definitions['posts']['thumbsdown'] . ' AFTER thumbsup');
				as_db_upgrade_query($locktablesquery);
				$keyrecalc['dorecountposts'] = true;
				break;

			case 3:
				as_db_upgrade_query('ALTER TABLE ^userpoints ADD COLUMN upthumbds ' . $definitions['userpoints']['upthumbds'] .
					' AFTER athumbds, ADD COLUMN downthumbds ' . $definitions['userpoints']['downthumbds'] . ' AFTER upthumbds');
				as_db_upgrade_query($locktablesquery);
				$keyrecalc['dorecalcpoints'] = true;
				break;

			case 4:
				as_db_upgrade_query('ALTER TABLE ^posts ADD COLUMN lastuserid ' . $definitions['posts']['lastuserid'] . ' AFTER cookieid, CHANGE COLUMN updated updated ' . $definitions['posts']['updated']);
				as_db_upgrade_query($locktablesquery);
				as_db_upgrade_query('UPDATE ^posts SET updated=NULL WHERE updated=0 OR updated=created');
				break;

			case 5:
				as_db_upgrade_query('ALTER TABLE ^contentwords ADD COLUMN type ' . $definitions['contentwords']['type'] . ' AFTER count, ADD COLUMN songid ' . $definitions['contentwords']['songid'] . ' AFTER type');
				as_db_upgrade_query($locktablesquery);
				$keyrecalc['doreindexcontent'] = true;
				break;

			// Up to here: Version 1.0 beta 2

			case 6:
				as_db_upgrade_query('ALTER TABLE ^userpoints ADD COLUMN cposts ' . $definitions['userpoints']['cposts'] . ' AFTER aposts');
				as_db_upgrade_query($locktablesquery);
				$keyrecalc['dorecalcpoints'] = true;
				break;

			case 7:
				if (!AS_FINAL_EXTERNAL_USERS) {
					as_db_upgrade_query('ALTER TABLE ^users ADD COLUMN sessioncode ' . $definitions['users']['sessioncode'] . ' AFTER writeip');
					as_db_upgrade_query($locktablesquery);
				}
				break;

			case 8:
				as_db_upgrade_query('ALTER TABLE ^posts ADD KEY (type, acount, created)');
				as_db_upgrade_query($locktablesquery);
				$keyrecalc['dorecountposts'] = true; // for unreviewed song count
				break;

			// Up to here: Version 1.0 beta 3, 1.0, 1.0.1 beta, 1.0.1

			case 9:
				if (!AS_FINAL_EXTERNAL_USERS) {
					as_db_upgrade_query('ALTER TABLE ^users CHANGE COLUMN resetcode emailcode ' . $definitions['users']['emailcode'] . ', ADD COLUMN flags ' . $definitions['users']['flags'] . ' AFTER sessioncode');
					as_db_upgrade_query($locktablesquery);
					as_db_upgrade_query('UPDATE ^users SET flags=1');
				}
				break;

			case 10:
				as_db_upgrade_query('UNLOCK TABLES');
				as_db_upgrade_query(as_db_create_table_sql('categories', array(
					'categoryid' => $definitions['categories']['categoryid'],
					'title' => $definitions['categories']['title'],
					'tags' => $definitions['categories']['tags'],
					'qcount' => $definitions['categories']['qcount'],
					'position' => $definitions['categories']['position'],
					'PRIMARY KEY (categoryid)',
					'UNIQUE `tags` (tags)',
					'UNIQUE `position` (position)',
				))); // hard-code list of columns and indexes to ensure we ignore any added at a later stage

				$locktablesquery .= ', ^categories WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 11:
				as_db_upgrade_query('ALTER TABLE ^posts ADD CONSTRAINT ^posts_ibfk_2 FOREIGN KEY (parentid) REFERENCES ^posts(postid), ADD COLUMN categoryid ' . $definitions['posts']['categoryid'] . ' AFTER parentid, ADD KEY categoryid (categoryid, type, created), ADD CONSTRAINT ^posts_ibfk_3 FOREIGN KEY (categoryid) REFERENCES ^categories(categoryid) ON DELETE SET NULL');
				// foreign key on parentid important now that deletion is possible
				as_db_upgrade_query($locktablesquery);
				break;

			case 12:
				as_db_upgrade_query('UNLOCK TABLES');
				as_db_upgrade_query(as_db_create_table_sql('pages', array(
					'pageid' => $definitions['pages']['pageid'],
					'title' => $definitions['pages']['title'],
					'nav' => $definitions['pages']['nav'],
					'position' => $definitions['pages']['position'],
					'flags' => $definitions['pages']['flags'],
					'tags' => $definitions['pages']['tags'],
					'heading' => $definitions['pages']['heading'],
					'content' => $definitions['pages']['content'],
					'PRIMARY KEY (pageid)',
					'UNIQUE `tags` (tags)',
					'UNIQUE `position` (position)',
				))); // hard-code list of columns and indexes to ensure we ignore any added at a later stage
				$locktablesquery .= ', ^pages WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 13:
				as_db_upgrade_query('ALTER TABLE ^posts ADD COLUMN createip ' . $definitions['posts']['createip'] . ' AFTER cookieid, ADD KEY createip (createip, created)');
				as_db_upgrade_query($locktablesquery);
				break;

			case 14:
				as_db_upgrade_query('ALTER TABLE ^userpoints DROP COLUMN qthumbs, DROP COLUMN athumbs, ADD COLUMN qthumbsup ' . $definitions['userpoints']['qthumbsup'] . ' AFTER aselecteds, ADD COLUMN qthumbsdown ' . $definitions['userpoints']['qthumbsdown'] . ' AFTER qthumbsup, ADD COLUMN athumbsup ' . $definitions['userpoints']['athumbsup'] . ' AFTER qthumbsdown, ADD COLUMN athumbsdown ' . $definitions['userpoints']['athumbsdown'] . ' AFTER athumbsup');
				as_db_upgrade_query($locktablesquery);
				$keyrecalc['dorecalcpoints'] = true;
				break;

			// Up to here: Version 1.2 beta 1

			case 15:
				if (!AS_FINAL_EXTERNAL_USERS)
					as_db_upgrade_table_columns($definitions, 'users', array('emailcode', 'sessioncode', 'flags'));

				as_db_upgrade_table_columns($definitions, 'posts', array('acount', 'thumbsup', 'thumbsdown', 'format'));
				as_db_upgrade_table_columns($definitions, 'categories', array('qcount'));
				as_db_upgrade_table_columns($definitions, 'words', array('titlecount', 'contentcount', 'tagcount'));
				as_db_upgrade_table_columns($definitions, 'userpoints', array('points', 'qposts', 'aposts', 'cposts',
					'aselects', 'aselecteds', 'qthumbsup', 'qthumbsdown', 'athumbsup', 'athumbsdown', 'qthumbds', 'athumbds', 'upthumbds', 'downthumbds'));
				as_db_upgrade_query($locktablesquery);
				break;

			// Up to here: Version 1.2 (release)

			case 16:
				as_db_upgrade_table_columns($definitions, 'posts', array('format'));
				as_db_upgrade_query($locktablesquery);
				$keyrecalc['doreindexcontent'] = true; // because of new treatment of apostrophes in words
				break;

			case 17:
				as_db_upgrade_query('ALTER TABLE ^posts ADD KEY updated (updated, type), ADD KEY categoryid_2 (categoryid, updated, type)');
				as_db_upgrade_query($locktablesquery);
				break;

			case 18:
				as_db_upgrade_query('ALTER TABLE ^posts ADD COLUMN lastip ' . $definitions['posts']['lastip'] . ' AFTER lastuserid, ADD KEY lastip (lastip, updated, type)');
				as_db_upgrade_query($locktablesquery);
				break;

			case 19:
				if (!AS_FINAL_EXTERNAL_USERS)
					as_db_upgrade_query('ALTER TABLE ^users ADD COLUMN avatarblobid ' . $definitions['users']['avatarblobid'] . ' AFTER handle, ADD COLUMN avatarwidth ' . $definitions['users']['avatarwidth'] . ' AFTER avatarblobid, ADD COLUMN avatarheight ' . $definitions['users']['avatarheight'] . ' AFTER avatarwidth');

				// hard-code list of columns and indexes to ensure we ignore any added at a later stage

				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('blobs', array(
					'blobid' => $definitions['blobs']['blobid'],
					'format' => $definitions['blobs']['format'],
					'content' => $definitions['blobs']['content'],
					'PRIMARY KEY (blobid)',
				)));

				as_db_upgrade_query(as_db_create_table_sql('cache', array(
					'type' => $definitions['cache']['type'],
					'cacheid' => $definitions['cache']['cacheid'],
					'content' => $definitions['cache']['content'],
					'created' => $definitions['cache']['created'],
					'lastread' => $definitions['cache']['lastread'],
					'PRIMARY KEY (type, cacheid)',
					'KEY (lastread)',
				))); // hard-code list of columns and indexes to ensure we ignore any added at a later stage

				$locktablesquery .= ', ^blobs WRITE, ^cache WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 20:
				if (!AS_FINAL_EXTERNAL_USERS) {
					as_db_upgrade_query('UNLOCK TABLES');

					as_db_upgrade_query(as_db_create_table_sql('usersignins', array(
						'userid' => $definitions['usersignins']['userid'],
						'source' => $definitions['usersignins']['source'],
						'identifier' => $definitions['usersignins']['identifier'],
						'identifiermd5' => $definitions['usersignins']['identifiermd5'],
						'KEY source (source, identifiermd5)',
						'KEY userid (userid)',
						'CONSTRAINT ^usersignins_ibfk_1 FOREIGN KEY (userid) REFERENCES ^users(userid) ON DELETE CASCADE',
					)));

					as_db_upgrade_query('ALTER TABLE ^users CHANGE COLUMN passsalt passsalt ' . $definitions['users']['passsalt'] . ', CHANGE COLUMN passcheck passcheck ' . $definitions['users']['passcheck']);

					$locktablesquery .= ', ^usersignins WRITE';
					as_db_upgrade_query($locktablesquery);
				}
				break;

			case 21:
				if (!AS_FINAL_EXTERNAL_USERS) {
					as_db_upgrade_query('UNLOCK TABLES');

					as_db_upgrade_query(as_db_create_table_sql('userfields', array(
						'fieldid' => $definitions['userfields']['fieldid'],
						'title' => $definitions['userfields']['title'],
						'content' => $definitions['userfields']['content'],
						'position' => $definitions['userfields']['position'],
						'flags' => $definitions['userfields']['flags'],
						'PRIMARY KEY (fieldid)',
					)));

					$locktablesquery .= ', ^userfields WRITE';
					as_db_upgrade_query($locktablesquery);

					as_db_upgrade_query(as_db_default_userfields_sql());
				}
				break;

			// Up to here: Version 1.3 beta 1

			case 22:
				if (!AS_FINAL_EXTERNAL_USERS) {
					as_db_upgrade_query('ALTER TABLE ^users ADD COLUMN sessionsource ' . $definitions['users']['sessionsource'] . ' AFTER sessioncode');
					as_db_upgrade_query($locktablesquery);
				}
				break;

			// Up to here: Version 1.3 beta 2 and release

			case 23:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('widgets', array(
					'widgetid' => $definitions['widgets']['widgetid'],
					'place' => $definitions['widgets']['place'],
					'position' => $definitions['widgets']['position'],
					'tags' => $definitions['widgets']['tags'],
					'title' => $definitions['widgets']['title'],
					'PRIMARY KEY (widgetid)',
					'UNIQUE `position` (position)',
				)));

				$locktablesquery .= ', ^widgets WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 24:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('tagwords', array(
					'postid' => $definitions['tagwords']['postid'],
					'wordid' => $definitions['tagwords']['wordid'],
					'KEY postid (postid)',
					'KEY wordid (wordid)',
					'CONSTRAINT ^tagwords_ibfk_1 FOREIGN KEY (postid) REFERENCES ^posts(postid) ON DELETE CASCADE',
					'CONSTRAINT ^tagwords_ibfk_2 FOREIGN KEY (wordid) REFERENCES ^words(wordid)',
				)));

				$locktablesquery .= ', ^tagwords WRITE';

				as_db_upgrade_query('ALTER TABLE ^words ADD COLUMN tagwordcount ' . $definitions['words']['tagwordcount'] . ' AFTER contentcount');
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['doreindexcontent'] = true;
				break;

			// Up to here: Version 1.4 developer preview

			case 25:
				$keycolumns = as_array_to_keys(as_db_read_all_values(as_db_query_sub('SHOW COLUMNS FROM ^blobs')));
				// might be using blobs table shared with another installation, so check if we need to upgrade

				if (isset($keycolumns['filename']))
					as_db_upgrade_progress('Skipping upgrading blobs table since it was already upgraded by another APS site sharing it.');

				else {
					as_db_upgrade_query('ALTER TABLE ^blobs ADD COLUMN filename ' . $definitions['blobs']['filename'] . ' AFTER content, ADD COLUMN userid ' . $definitions['blobs']['userid'] . ' AFTER filename, ADD COLUMN cookieid ' . $definitions['blobs']['cookieid'] . ' AFTER userid, ADD COLUMN createip ' . $definitions['blobs']['createip'] . ' AFTER cookieid, ADD COLUMN created ' . $definitions['blobs']['created'] . ' AFTER createip');
					as_db_upgrade_query($locktablesquery);
				}
				break;

			case 26:
				as_db_upgrade_query('ALTER TABLE ^userthumbs ADD COLUMN flag ' . $definitions['userthumbs']['flag'] . ' AFTER thumb');
				as_db_upgrade_query($locktablesquery);

				as_db_upgrade_query('ALTER TABLE ^posts ADD COLUMN flagcount ' . $definitions['posts']['flagcount'] . ' AFTER thumbsdown, ADD KEY type_3 (type, flagcount, created)');
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['dorecountposts'] = true;
				break;

			case 27:
				as_db_upgrade_query('ALTER TABLE ^posts ADD COLUMN netthumbs ' . $definitions['posts']['netthumbs'] . ' AFTER thumbsdown, ADD KEY type_4 (type, netthumbs, created)');
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['dorecountposts'] = true;
				break;

			case 28:
				as_db_upgrade_query('ALTER TABLE ^posts ADD COLUMN views ' . $definitions['posts']['views'] . ' AFTER netthumbs, ADD COLUMN hotness ' . $definitions['posts']['hotness'] . ' AFTER views, ADD KEY type_5 (type, views, created), ADD KEY type_6 (type, hotness)');
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['dorecountposts'] = true;
				break;

			case 29:
				as_db_upgrade_query('ALTER TABLE ^posts ADD COLUMN lastviewip ' . $definitions['posts']['lastviewip'] . ' AFTER netthumbs');
				as_db_upgrade_query($locktablesquery);
				break;

			case 30:
				as_db_upgrade_query('ALTER TABLE ^posts DROP FOREIGN KEY ^posts_ibfk_3'); // to allow category column types to be changed
				as_db_upgrade_query($locktablesquery);

				as_db_upgrade_query('ALTER TABLE ^posts DROP KEY categoryid, DROP KEY categoryid_2');
				as_db_upgrade_query($locktablesquery);

				as_db_upgrade_query('ALTER TABLE ^categories CHANGE COLUMN categoryid categoryid ' . $definitions['categories']['categoryid'] . ', ADD COLUMN parentid ' . $definitions['categories']['parentid'] . ' AFTER categoryid, ADD COLUMN backpath ' . $definitions['categories']['backpath'] . ' AFTER position, ADD COLUMN content ' . $definitions['categories']['content'] . ' AFTER tags, DROP INDEX tags, DROP INDEX position, ADD UNIQUE parentid (parentid, tags), ADD UNIQUE parentid_2 (parentid, position), ADD KEY backpath (backpath(' . AS_DB_MAX_CAT_PAGE_TAGS_LENGTH . '))');
				as_db_upgrade_query($locktablesquery);

				as_db_upgrade_query('ALTER TABLE ^posts CHANGE COLUMN categoryid categoryid ' . $definitions['posts']['categoryid'] . ', ADD COLUMN catidpath1 ' . $definitions['posts']['catidpath1'] . ' AFTER categoryid, ADD COLUMN catidpath2 ' . $definitions['posts']['catidpath2'] . ' AFTER catidpath1, ADD COLUMN catidpath3 ' . $definitions['posts']['catidpath3'] . ' AFTER catidpath2'); // AS_CATEGORY_DEPTH=4
				as_db_upgrade_query($locktablesquery);

				as_db_upgrade_query('ALTER TABLE ^posts ADD KEY catidpath1 (catidpath1, type, created), ADD KEY catidpath2 (catidpath2, type, created), ADD KEY catidpath3 (catidpath3, type, created), ADD KEY categoryid (categoryid, type, created), ADD KEY catidpath1_2 (catidpath1, updated, type), ADD KEY catidpath2_2 (catidpath2, updated, type), ADD KEY catidpath3_2 (catidpath3, updated, type), ADD KEY categoryid_2 (categoryid, updated, type)');
				as_db_upgrade_query($locktablesquery);

				as_db_upgrade_query('ALTER TABLE ^posts ADD CONSTRAINT ^posts_ibfk_3 FOREIGN KEY (categoryid) REFERENCES ^categories(categoryid) ON DELETE SET NULL');
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['dorecalccategories'] = true;
				break;

			// Up to here: Version 1.4 betas and release

			case 31:
				as_db_upgrade_query('ALTER TABLE ^posts CHANGE COLUMN type type ' . $definitions['posts']['type'] . ', ADD COLUMN updatetype ' . $definitions['posts']['updatetype'] . ' AFTER updated, ADD COLUMN closedbyid ' . $definitions['posts']['closedbyid'] . ' AFTER selchildid, ADD KEY closedbyid (closedbyid), ADD CONSTRAINT ^posts_ibfk_4 FOREIGN KEY (closedbyid) REFERENCES ^posts(postid)');
				as_db_upgrade_query($locktablesquery);
				break;

			case 32:
				as_db_upgrade_query("UPDATE ^posts SET updatetype=IF(INSTR(type, '_HIDDEN')>0, 'H', 'E') WHERE updated IS NOT NULL");
				break;

			case 33:
				as_db_upgrade_query('ALTER TABLE ^contentwords CHANGE COLUMN type type ' . $definitions['contentwords']['type']);
				as_db_upgrade_query($locktablesquery);
				break;

			case 34:
				if (!AS_FINAL_EXTERNAL_USERS) {
					$keytables = as_array_to_keys(as_db_read_all_values(as_db_query_sub('SHOW TABLES')));
					// might be using messages table shared with another installation, so check if we need to upgrade

					if (isset($keytables[as_db_add_table_prefix('messages')]))
						as_db_upgrade_progress('Skipping messages table since it was already added by another APS site sharing these users.');

					else {
						as_db_upgrade_query('UNLOCK TABLES');

						as_db_upgrade_query(as_db_create_table_sql('messages', array(
							'messageid' => $definitions['messages']['messageid'],
							'fromuserid' => $definitions['messages']['fromuserid'],
							'touserid' => $definitions['messages']['touserid'],
							'content' => $definitions['messages']['content'],
							'format' => $definitions['messages']['format'],
							'created' => $definitions['messages']['created'],
							'PRIMARY KEY (messageid)',
							'KEY fromuserid (fromuserid, touserid, created)',
						)));

						$locktablesquery .= ', ^messages WRITE';
						as_db_upgrade_query($locktablesquery);
					}
				}
				break;

			case 35:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('userfavorites', array(
					'userid' => $definitions['userfavorites']['userid'],
					'entitytype' => $definitions['userfavorites']['entitytype'],
					'entityid' => $definitions['userfavorites']['entityid'],
					'nouserevents' => $definitions['userfavorites']['nouserevents'],
					'PRIMARY KEY (userid, entitytype, entityid)',
					'KEY userid (userid, nouserevents)',
					'KEY entitytype (entitytype, entityid, nouserevents)',
					AS_FINAL_EXTERNAL_USERS ? null : 'CONSTRAINT ^userfavorites_ibfk_1 FOREIGN KEY (userid) REFERENCES ^users(userid) ON DELETE CASCADE',
				)));

				$locktablesquery .= ', ^userfavorites WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 36:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('userevents', array(
					'userid' => $definitions['userevents']['userid'],
					'entitytype' => $definitions['userevents']['entitytype'],
					'entityid' => $definitions['userevents']['entityid'],
					'songid' => $definitions['userevents']['songid'],
					'lastpostid' => $definitions['userevents']['lastpostid'],
					'updatetype' => $definitions['userevents']['updatetype'],
					'lastuserid' => $definitions['userevents']['lastuserid'],
					'updated' => $definitions['userevents']['updated'],
					'KEY userid (userid, updated)',
					'KEY songid (songid, userid)',
					AS_FINAL_EXTERNAL_USERS ? null : 'CONSTRAINT ^userevents_ibfk_1 FOREIGN KEY (userid) REFERENCES ^users(userid) ON DELETE CASCADE',
				)));

				$locktablesquery .= ', ^userevents WRITE';
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['dorefillevents'] = true;
				break;

			case 37:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('sharedevents', array(
					'entitytype' => $definitions['sharedevents']['entitytype'],
					'entityid' => $definitions['sharedevents']['entityid'],
					'songid' => $definitions['sharedevents']['songid'],
					'lastpostid' => $definitions['sharedevents']['lastpostid'],
					'updatetype' => $definitions['sharedevents']['updatetype'],
					'lastuserid' => $definitions['sharedevents']['lastuserid'],
					'updated' => $definitions['sharedevents']['updated'],
					'KEY entitytype (entitytype, entityid, updated)',
					'KEY songid (songid, entitytype, entityid)',
				)));

				$locktablesquery .= ', ^sharedevents WRITE';
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['dorefillevents'] = true;
				break;

			case 38:
				as_db_upgrade_query('ALTER TABLE ^posts ADD KEY lastuserid (lastuserid, updated, type)');
				as_db_upgrade_query($locktablesquery);
				break;

			case 39:
				as_db_upgrade_query('ALTER TABLE ^posts DROP KEY type_3, ADD KEY flagcount (flagcount, created, type)');
				as_db_upgrade_query($locktablesquery);
				break;

			case 40:
				as_db_upgrade_query('ALTER TABLE ^userpoints ADD COLUMN bonus ' . $definitions['userpoints']['bonus'] . ' AFTER downthumbds');
				as_db_upgrade_query($locktablesquery);
				break;

			case 41:
				as_db_upgrade_query('ALTER TABLE ^pages ADD COLUMN permit ' . $definitions['pages']['permit'] . ' AFTER flags');
				as_db_upgrade_query($locktablesquery);
				break;

			case 42:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('usermetas', array(
					'userid' => $definitions['usermetas']['userid'],
					'title' => $definitions['usermetas']['title'],
					'content' => $definitions['usermetas']['content'],
					'PRIMARY KEY (userid, title)',
					AS_FINAL_EXTERNAL_USERS ? null : 'CONSTRAINT ^usermetas_ibfk_1 FOREIGN KEY (userid) REFERENCES ^users(userid) ON DELETE CASCADE',
				)));

				$locktablesquery .= ', ^usermetas WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 43:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('postmetas', array(
					'postid' => $definitions['postmetas']['postid'],
					'title' => $definitions['postmetas']['title'],
					'content' => $definitions['postmetas']['content'],
					'PRIMARY KEY (postid, title)',
					'CONSTRAINT ^postmetas_ibfk_1 FOREIGN KEY (postid) REFERENCES ^posts(postid) ON DELETE CASCADE',
				)));

				$locktablesquery .= ', ^postmetas WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 44:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('categorymetas', array(
					'categoryid' => $definitions['categorymetas']['categoryid'],
					'title' => $definitions['categorymetas']['title'],
					'content' => $definitions['categorymetas']['content'],
					'PRIMARY KEY (categoryid, title)',
					'CONSTRAINT ^categorymetas_ibfk_1 FOREIGN KEY (categoryid) REFERENCES ^categories(categoryid) ON DELETE CASCADE',
				)));

				$locktablesquery .= ', ^categorymetas WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 45:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('tagmetas', array(
					'tag' => $definitions['tagmetas']['tag'],
					'title' => $definitions['tagmetas']['title'],
					'content' => $definitions['tagmetas']['content'],
					'PRIMARY KEY (tag, title)',
				)));

				$locktablesquery .= ', ^tagmetas WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			case 46:
				as_db_upgrade_query('ALTER TABLE ^posts DROP KEY selchildid, ADD KEY selchildid (selchildid, type, created), ADD COLUMN amaxthumb SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER acount, ADD KEY type_7 (type, amaxthumb, created)');
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['dorecountposts'] = true;
				break;

			case 47:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query(as_db_create_table_sql('usernotices', array(
					'noticeid' => $definitions['usernotices']['noticeid'],
					'userid' => $definitions['usernotices']['userid'],
					'content' => $definitions['usernotices']['content'],
					'format' => $definitions['usernotices']['format'],
					'tags' => $definitions['usernotices']['tags'],
					'created' => $definitions['usernotices']['created'],
					'PRIMARY KEY (noticeid)',
					'KEY userid (userid, created)',
					AS_FINAL_EXTERNAL_USERS ? null : 'CONSTRAINT ^usernotices_ibfk_1 FOREIGN KEY (userid) REFERENCES ^users(userid) ON DELETE CASCADE',
				)));

				$locktablesquery .= ', ^usernotices WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			// Up to here: Version 1.5.x

			case 48:
				if (!AS_FINAL_EXTERNAL_USERS) {
					$keycolumns = as_array_to_keys(as_db_read_all_values(as_db_query_sub('SHOW COLUMNS FROM ^messages')));
					// might be using messages table shared with another installation, so check if we need to upgrade

					if (isset($keycolumns['type']))
						as_db_upgrade_progress('Skipping upgrading messages table since it was already upgraded by another APS site sharing it.');

					else {
						as_db_upgrade_query('ALTER TABLE ^messages ADD COLUMN type ' . $definitions['messages']['type'] . ' AFTER messageid, DROP KEY fromuserid, ADD key type (type, fromuserid, touserid, created), ADD KEY touserid (touserid, type, created)');
						as_db_upgrade_query($locktablesquery);
					}
				}
				break;

			case 49:
				if (!AS_FINAL_EXTERNAL_USERS) {
					as_db_upgrade_query('ALTER TABLE ^users CHANGE COLUMN flags flags ' . $definitions['users']['flags']);
					as_db_upgrade_query($locktablesquery);
				}
				break;

			case 50:
				as_db_upgrade_query('ALTER TABLE ^posts ADD COLUMN name ' . $definitions['posts']['name'] . ' AFTER tags');
				as_db_upgrade_query($locktablesquery);
				break;

			case 51:
				if (!AS_FINAL_EXTERNAL_USERS) {
					// might be using userfields table shared with another installation, so check if we need to upgrade
					$keycolumns = as_array_to_keys(as_db_read_all_values(as_db_query_sub('SHOW COLUMNS FROM ^userfields')));

					if (isset($keycolumns['permit']))
						as_db_upgrade_progress('Skipping upgrading userfields table since it was already upgraded by another APS site sharing it.');

					else {
						as_db_upgrade_query('ALTER TABLE ^userfields ADD COLUMN permit ' . $definitions['userfields']['permit'] . ' AFTER flags');
						as_db_upgrade_query($locktablesquery);
					}
				}
				break;

			case 52:
				if (!AS_FINAL_EXTERNAL_USERS) {
					$keyindexes = as_array_to_keys(as_db_read_all_assoc(as_db_query_sub('SHOW INDEX FROM ^users'), null, 'Key_name'));

					if (isset($keyindexes['created']))
						as_db_upgrade_progress('Skipping upgrading users table since it was already upgraded by another APS site sharing it.');

					else {
						as_db_upgrade_query('ALTER TABLE ^users ADD KEY created (created, level, flags)');
						as_db_upgrade_query($locktablesquery);
					}
				}
				break;

			case 53:
				as_db_upgrade_query('ALTER TABLE ^blobs CHANGE COLUMN content content ' . $definitions['blobs']['content']);
				as_db_upgrade_query($locktablesquery);
				break;

			case 54:
				as_db_upgrade_query('UNLOCK TABLES');

				as_db_upgrade_query('SET FOREIGN_KEY_CHECKS=0'); // in case InnoDB not available

				as_db_upgrade_query(as_db_create_table_sql('userlevels', array(
					'userid' => $definitions['userlevels']['userid'],
					'entitytype' => $definitions['userlevels']['entitytype'],
					'entityid' => $definitions['userlevels']['entityid'],
					'level' => $definitions['userlevels']['level'],
					'UNIQUE userid (userid, entitytype, entityid)',
					'KEY entitytype (entitytype, entityid)',
					AS_FINAL_EXTERNAL_USERS ? null : 'CONSTRAINT ^userlevels_ibfk_1 FOREIGN KEY (userid) REFERENCES ^users(userid) ON DELETE CASCADE',
				)));

				$locktablesquery .= ', ^userlevels WRITE';
				as_db_upgrade_query($locktablesquery);
				break;

			// Up to here: Version 1.6 beta 1

			case 55:
				if (!AS_FINAL_EXTERNAL_USERS) {
					// might be using users table shared with another installation, so check if we need to upgrade
					$keycolumns = as_array_to_keys(as_db_read_all_values(as_db_query_sub('SHOW COLUMNS FROM ^users')));

					if (isset($keycolumns['wallposts']))
						as_db_upgrade_progress('Skipping upgrading users table since it was already upgraded by another APS site sharing it.');

					else {
						as_db_upgrade_query('ALTER TABLE ^users ADD COLUMN wallposts ' . $definitions['users']['wallposts'] . ' AFTER flags');
						as_db_upgrade_query($locktablesquery);
					}
				}
				break;

			// Up to here: Version 1.6 beta 2

			case 56:
				as_db_upgrade_query('ALTER TABLE ^pages DROP INDEX tags, ADD KEY tags (tags)');
				as_db_upgrade_query($locktablesquery);
				break;

			// Up to here: Version 1.6 (release)

			case 57:
				if (!AS_FINAL_EXTERNAL_USERS) {
					// might be using messages table shared with another installation, so check if we need to upgrade
					$keycolumns = as_array_to_keys(as_db_read_all_values(as_db_query_sub('SHOW COLUMNS FROM ^messages')));

					if (isset($keycolumns['fromhidden']))
						as_db_upgrade_progress('Skipping upgrading messages table since it was already upgraded by another APS site sharing it.');
					else {
						as_db_upgrade_query('ALTER TABLE ^messages ADD COLUMN fromhidden ' . $definitions['messages']['fromhidden'] . ' AFTER touserid');
						as_db_upgrade_query('ALTER TABLE ^messages ADD COLUMN tohidden ' . $definitions['messages']['tohidden'] . ' AFTER fromhidden');
						as_db_upgrade_query('ALTER TABLE ^messages ADD KEY fromhidden (fromhidden), ADD KEY tohidden (tohidden)');

						as_db_upgrade_query($locktablesquery);
					}
				}
				break;

			case 58:
				// note: need to use full table names here as aliases trigger error "Table 'x' was not locked with LOCK TABLES"
				as_db_upgrade_query('DELETE FROM ^userfavorites WHERE entitytype="U" AND userid=entityid');
				as_db_upgrade_query('DELETE ^userthumbs FROM ^userthumbs JOIN ^posts ON ^userthumbs.postid=^posts.postid AND ^userthumbs.userid=^posts.userid');
				as_db_upgrade_query($locktablesquery);

				$keyrecalc['dorecountposts'] = true;
				$keyrecalc['dorecalcpoints'] = true;
				break;

			// Up to here: Version 1.7

			case 59:
				// upgrade from alpha version removed
				break;

			// Up to here: Version 1.7.1

			case 60:
				// add new category widget - note title must match that from as_signup_core_modules()
				if (as_using_categories()) {
					$widgetid = as_db_widget_create('Categories', 'all');
					as_db_widget_move($widgetid, 'SL', 1);
				}
				break;

			case 61:
				// upgrade length of as_posts.content field to 12000
				$newlength = AS_DB_MAX_CONTENT_LENGTH;
				$query = 'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE table_schema=$ AND table_name=$ AND column_name="content"';
				$tablename = as_db_add_table_prefix('posts');
				$oldlength = as_db_read_one_value(as_db_query_sub($query, AS_FINAL_MYSQL_DATABASE, $tablename));

				if ($oldlength < $newlength) {
					as_db_upgrade_query('ALTER TABLE ^posts MODIFY content ' . $definitions['posts']['content']);
				}

				break;

			case 62:
				if (!AS_FINAL_EXTERNAL_USERS) {
					// might be using users table shared with another installation, so check if we need to upgrade
					$keycolumns = as_array_to_keys(as_db_read_all_values(as_db_query_sub('SHOW COLUMNS FROM ^users')));

					if (isset($keycolumns['passhash']))
						as_db_upgrade_progress(sprintf($skipMessage, 'users'));
					else {
						// add column to as_users to handle new bcrypt passwords
						as_db_upgrade_query('ALTER TABLE ^users ADD COLUMN passhash ' . $definitions['users']['passhash'] . ' AFTER passcheck');
						as_db_upgrade_query($locktablesquery);
					}
				}
				break;

			case 63:
				// check for shared cookies table
				$fieldDef = as_db_read_one_assoc(as_db_query_sub('SHOW COLUMNS FROM ^cookies WHERE Field="createip"'));
				if (strtolower($fieldDef['Type']) === 'varbinary(16)')
					as_db_upgrade_progress(sprintf($skipMessage, 'cookies'));
				else {
					as_db_upgrade_query('ALTER TABLE ^cookies MODIFY writeip ' . $definitions['cookies']['writeip'] . ', MODIFY createip ' . $definitions['cookies']['createip']);
					as_db_upgrade_query('UPDATE ^cookies SET writeip = UNHEX(HEX(CAST(writeip AS UNSIGNED))), createip = UNHEX(HEX(CAST(createip AS UNSIGNED)))');
				}

				as_db_upgrade_query('ALTER TABLE ^iplimits MODIFY ip ' . $definitions['iplimits']['ip']);
				as_db_upgrade_query('UPDATE ^iplimits SET ip = UNHEX(HEX(CAST(ip AS UNSIGNED)))');

				// check for shared blobs table
				$fieldDef = as_db_read_one_assoc(as_db_query_sub('SHOW COLUMNS FROM ^blobs WHERE Field="createip"'));
				if (strtolower($fieldDef['Type']) === 'varbinary(16)')
					as_db_upgrade_progress(sprintf($skipMessage, 'blobs'));
				else {
					as_db_upgrade_query('ALTER TABLE ^blobs MODIFY createip ' . $definitions['blobs']['createip']);
					as_db_upgrade_query('UPDATE ^blobs SET createip = UNHEX(HEX(CAST(createip AS UNSIGNED)))');
				}

				as_db_upgrade_query('ALTER TABLE ^posts MODIFY lastviewip ' . $definitions['posts']['lastviewip'] . ', MODIFY lastip ' . $definitions['posts']['lastip'] . ', MODIFY createip ' . $definitions['posts']['createip']);
				as_db_upgrade_query('UPDATE ^posts SET lastviewip = UNHEX(HEX(CAST(lastviewip AS UNSIGNED))), lastip = UNHEX(HEX(CAST(lastip AS UNSIGNED))), createip = UNHEX(HEX(CAST(createip AS UNSIGNED)))');

				if (!AS_FINAL_EXTERNAL_USERS) {
					// check for shared users table
					$fieldDef = as_db_read_one_assoc(as_db_query_sub('SHOW COLUMNS FROM ^users WHERE Field="createip"'));
					if (strtolower($fieldDef['Type']) === 'varbinary(16)')
						as_db_upgrade_progress(sprintf($skipMessage, 'users'));
					else {
						as_db_upgrade_query('ALTER TABLE ^users MODIFY createip ' . $definitions['users']['createip'] . ', MODIFY signinip ' . $definitions['users']['signinip'] . ', MODIFY writeip ' . $definitions['users']['writeip']);
						as_db_upgrade_query('UPDATE ^users SET createip = UNHEX(HEX(CAST(createip AS UNSIGNED))), signinip = UNHEX(HEX(CAST(signinip AS UNSIGNED))), writeip = UNHEX(HEX(CAST(writeip AS UNSIGNED)))');
					}
				}

				as_db_upgrade_query($locktablesquery);
				break;

			case 64:
				$pluginManager = new APS_Plugin_PluginManager();
				$allPlugins = $pluginManager->getFilesystemPlugins();
				$pluginManager->setEnabledPlugins($allPlugins);
				break;

			case 65:
				as_db_upgrade_query('ALTER TABLE ^userthumbs ADD COLUMN thumbcreated ' . $definitions['userthumbs']['thumbcreated'] . ' AFTER flag');
				as_db_upgrade_query('ALTER TABLE ^userthumbs ADD COLUMN thumbupdated ' . $definitions['userthumbs']['thumbupdated'] . ' AFTER thumbcreated');
				as_db_upgrade_query('ALTER TABLE ^userthumbs ADD KEY thumbd (thumbcreated, thumbupdated)');
				as_db_upgrade_query($locktablesquery);

				// for old thumbs, set a default date of when that post was made
				as_db_upgrade_query('UPDATE ^userthumbs, ^posts SET ^userthumbs.thumbcreated=^posts.created WHERE ^userthumbs.postid=^posts.postid AND (^userthumbs.thumb != 0 OR ^userthumbs.flag=0)');
				break;

			case 66:
				$newColumns = array(
					'ADD COLUMN cthumbsup ' . $definitions['userpoints']['cthumbsup'] . ' AFTER athumbsdown',
					'ADD COLUMN cthumbsdown ' . $definitions['userpoints']['cthumbsdown'] . ' AFTER cthumbsup',
					'ADD COLUMN cthumbds ' . $definitions['userpoints']['cthumbds'] . ' AFTER athumbds',
				);
				as_db_upgrade_query('ALTER TABLE ^userpoints ' . implode(', ', $newColumns));
				as_db_upgrade_query($locktablesquery);
				break;

			case 67:
				// ensure we don't have old userids lying around
				if (!AS_FINAL_EXTERNAL_USERS) {
					as_db_upgrade_query('ALTER TABLE ^messages MODIFY fromuserid ' . $definitions['messages']['fromuserid']);
					as_db_upgrade_query('ALTER TABLE ^messages MODIFY touserid ' . $definitions['messages']['touserid']);
					as_db_upgrade_query('UPDATE ^messages SET fromuserid=NULL WHERE fromuserid NOT IN (SELECT userid FROM ^users)');
					as_db_upgrade_query('UPDATE ^messages SET touserid=NULL WHERE touserid NOT IN (SELECT userid FROM ^users)');
					// set up foreign key on messages table
					as_db_upgrade_query('ALTER TABLE ^messages ADD CONSTRAINT ^messages_ibfk_1 FOREIGN KEY (fromuserid) REFERENCES ^users(userid) ON DELETE SET NULL');
					as_db_upgrade_query('ALTER TABLE ^messages ADD CONSTRAINT ^messages_ibfk_2 FOREIGN KEY (touserid) REFERENCES ^users(userid) ON DELETE SET NULL');
				}

				as_db_upgrade_query($locktablesquery);
				break;

			// Up to here: Version 1.8
		}

		as_db_set_db_version($newversion);

		if (as_db_get_db_version() != $newversion)
			as_fatal_error('Could not increment database version');
	}

	as_db_upgrade_query('UNLOCK TABLES');

	// Perform any necessary recalculations, as determined by upgrade steps

	foreach ($keyrecalc as $state => $dummy) {
		while ($state) {
			set_time_limit(60);

			$stoptime = time() + 2;

			while (as_recalc_perform_step($state) && (time() < $stoptime))
				;

			as_db_upgrade_progress(as_recalc_get_message($state));
		}
	}
}


/**
 * Reset the definitions of $columns in $table according to the $definitions array
 * @param $definitions
 * @param $table
 * @param $columns
 */
function as_db_upgrade_table_columns($definitions, $table, $columns)
{
	$sqlchanges = array();

	foreach ($columns as $column)
		$sqlchanges[] = 'CHANGE COLUMN ' . $column . ' ' . $column . ' ' . $definitions[$table][$column];

	as_db_upgrade_query('ALTER TABLE ^' . $table . ' ' . implode(', ', $sqlchanges));
}


/**
 * Perform upgrade $query and output progress to the browser
 * @param $query
 */
function as_db_upgrade_query($query)
{
	as_db_upgrade_progress('Running query: ' . as_db_apply_sub($query, array()) . ' ...');
	as_db_query_sub($query);
}


/**
 * Output $text to the browser (after converting to HTML) and do all we can to get it displayed
 * @param $text
 */
function as_db_upgrade_progress($text)
{
	echo as_html($text) . str_repeat('    ', 1024) . "<br><br>\n";
	flush();
}
