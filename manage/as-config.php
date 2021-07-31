<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-config-example.php
	Description: After renaming, use this to set up database details and other stuff


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

/*
	======================================================================
	  THE 4 DEFINITIONS BELOW ARE REQUIRED AND MUST BE SET BEFORE USING!
	======================================================================

	For AS_MYSQL_HOSTNAME, try '127.0.0.1' or 'localhost' if MySQL is on the same server.

	For persistent connections, set the AS_PERSISTENT_CONN_DB at the bottom of this file; do NOT
	prepend the hostname with 'p:'.

	To use a non-default port, add the following line to the list of defines, with the appropriate port number:
	define('AS_MYSQL_PORT', '3306');
*/

	define('AS_MYSQL_HOSTNAME', '127.0.0.1');
	define('AS_MYSQL_USERNAME', 'appsmata_sing');
	define('AS_MYSQL_PASSWORD', 'Am2zealous');
	define('AS_MYSQL_DATABASE', 'appsmata_sing');

/*
	Ultra-concise installation instructions:

	1. Create a MySQL database.
	2. Create a MySQL user with full permissions for that database.
	3. Rename this file to as-config.php.
	4. Set the above four definitions and save.
	5. Place all the vSongBook files on your server.
	6. Open the appropriate URL, and follow the instructions.

	More detailed installation instructions here: http://github.com/vsongbook
*/

/*
	======================================================================
	 OPTIONAL CONSTANT DEFINITIONS, INCLUDING SUPPORT FOR SINGLE SIGN-ON
	======================================================================

	AS_MYSQL_TABLE_PREFIX will be added to table names, to allow multiple datasets in a single
	MySQL database, or to include the vSongBook tables in an existing MySQL database.
*/

	define('AS_MYSQL_TABLE_PREFIX', 'as_');

/*
	If you wish, you can define AS_MYSQL_USERS_PREFIX separately from AS_MYSQL_TABLE_PREFIX.
	If so, tables containing information about user accounts (not including users' activity and points)
	get the prefix of AS_MYSQL_TABLE_PREFIX. This allows multiple APS sites to have shared signins
	and users, but separate posts and activity.

	If you have installed song2review with default "as_" prefix and want to setup a second
	installation, you define the AS_MYSQL_USERS_PREFIX as "as_" so this new installation
	can access the same database as the first installation.

	define('AS_MYSQL_USERS_PREFIX', 'sharedusers_');
*/

/*
	If you wish, you can define AS_BLOBS_DIRECTORY to store BLOBs (binary large objects) such
	as avatars and uploaded files on disk, rather than in the database. If so this directory
	must be writable by the web server process - on Unix/Linux use chown/chmod as appropriate.
	Note than if multiple APS sites are using AS_MYSQL_USERS_PREFIX to share users, they must
	also have the same value for AS_BLOBS_DIRECTORY.

	If there are already some BLOBs stored in the database from previous uploads, click the
	'Move BLOBs to disk' button in the 'Stats' section of the admin panel to move them to disk.

	define('AS_BLOBS_DIRECTORY', '/path/to/writable_blobs_directory/');
*/

/*
	If you wish to use file-based caching, you must define AS_CACHE_DIRECTORY to store the cache
	files. The directory must be writable by the web server. For maximum security it's STRONGLY
	recommended to place the folder outside of the web root (so they can never be accessed via a
	web browser).

	define('AS_CACHE_DIRECTORY', '/path/to/writable_cache_directory/');
*/

/*
	If you wish, you can define AS_COOKIE_DOMAIN so that any cookies created by APS are assigned
	to a specific domain name, instead of the full domain name of the request by default. This is
	useful if you're running multiple APS sites on subdomains with a shared user base.

	define('AS_COOKIE_DOMAIN', '.example.com'); // be sure to keep the leading period
*/

/*
	If you wish, you can define an array $AS_CONST_PATH_MAP to modify the URLs used in your APS site.
	The key of each array element should be the standard part of the path, e.g. 'songs',
	and the value should be the replacement for that standard part, e.g. 'topics'. If you edit this
	file in UTF-8 encoding you can also use non-ASCII characters in these URLs.

	$AS_CONST_PATH_MAP = array(
		'songs' => 'topics',
		'categories' => 'sections',
		'users' => 'contributors',
		'user' => 'contributor',
	);
*/

/*
	Set AS_EXTERNAL_USERS to true to use your user identification code in as-external/as-external-users.php
	This allows you to integrate with your existing user database and management system. For more details,
	consult the online documentation on installing vSongBook with single sign-on.

	The constants AS_EXTERNAL_LANG and AS_EXTERNAL_EMAILER are deprecated from APS 1.5 since the same
	effect can now be achieved in plugins by using function overrides.
*/

	define('AS_EXTERNAL_USERS', false);

/*
	Out-of-the-box WordPress 3.x integration - to integrate with your WordPress site and user
	database, define AS_WORDPRESS_INTEGRATE_PATH as the full path to the WordPress directory
	containing wp-load.php. You do not need to set the AS_MYSQL_* constants above since these
	will be taken from WordPress automatically. See online documentation for more details.

	define('AS_WORDPRESS_INTEGRATE_PATH', '/PATH/TO/WORDPRESS');
*/

/*
	Out-of-the-box Joomla! 3.x integration - to integrate with your Joomla! site, define
	AS_JOOMLA_INTEGRATE_PATH. as the full path to the Joomla! directory. If your APS
	site is a subdirectory of your main Joomla site (recommended), you can specify
	dirname(__DIR__) rather than the full path.
	With this set, you do not need to set the AS_MYSQL_* constants above since these
	will be taken from Joomla automatically. See online documentation for more details.

	define('AS_JOOMLA_INTEGRATE_PATH', dirname(__DIR__));
*/

/*
	Some settings to help optimize your vSongBook site's performance.

	If AS_HTML_COMPRESSION is true, HTML web pages will be output using Gzip compression, which
	will increase the performance of your site (if the user's browser indicates this is supported).
	This is best done at the server level if possible, but many hosts don't provide server access.

	AS_MAX_LIMIT_START is the maximum start parameter that can be requested, for paging through
	long lists of songs, etc... As the start parameter gets higher, queries tend to get
	slower, since MySQL must examine more information. Very high start numbers are usually only
	requested by search engine robots anyway.

	If a word is used AS_IGNORED_WORDS_FREQ times or more in a particular way, it is ignored
	when searching or finding related songs. This saves time by ignoring words which are so
	common that they are probably not worth matching on.

	Set AS_ALLOW_UNINDEXED_QUERIES to true if you don't mind running some database queries which
	are not indexed efficiently. For example, this will enable browsing unreviewed songs per
	category. If your database becomes large, these queries could become costly.

	Set AS_OPTIMIZE_DISTANT_DB to false if your web server and MySQL are running on the same box.
	When viewing a page on your site, this will use many simple MySQL queries instead of fewer
	complex ones, which makes sense since there is no latency for localhost access.
	Otherwise, set it to true if your web server and MySQL are far enough apart to create
	significant latency. This will minimize the number of database queries as much as is possible,
	even at the cost of significant additional processing at each end.

	The option AS_OPTIMIZE_LOCAL_DB is no longer used, since AS_OPTIMIZE_DISTANT_DB covers our uses.

	Set AS_PERSISTENT_CONN_DB to true to use persistent database connections. Requires PHP 5.3.
	Only use this if you are absolutely sure it is a good idea under your setup - generally it is
	not. For more information: http://www.php.net/manual/en/features.persistent-connections.php

	Set AS_DEBUG_PERFORMANCE to true to show detailed performance profiling information at the
	bottom of every vSongBook page.
*/

	define('AS_HTML_COMPRESSION', false);
	define('AS_MAX_LIMIT_START', 19999);
	define('AS_IGNORED_WORDS_FREQ', 10000);
	define('AS_ALLOW_UNINDEXED_QUERIES', false);
	define('AS_OPTIMIZE_DISTANT_DB', false);
	define('AS_PERSISTENT_CONN_DB', false);
	define('AS_DEBUG_PERFORMANCE', false);

/*
	And lastly... if you want to, you can predefine any constant from as-db-maxima.php in this
	file to override the default setting. Just make sure you know what you're doing!
*/