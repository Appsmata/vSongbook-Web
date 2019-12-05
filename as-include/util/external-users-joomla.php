<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: External user functions for basic Joomla integration


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


function as_get_mysql_user_column_type()
{
	return "INT";
}

function as_get_signin_links($relative_url_prefix, $redirect_back_to_url)
{
	$jhelper = new as_joomla_helper();
	$config_urls = $jhelper->trigger_get_urls_event();

	return array(
		'signin' => $config_urls['signin'],
		'signup' => $config_urls['reg'],
		'signout' => $config_urls['signout']
	);
}

function as_get_logged_in_user()
{
	$jhelper = new as_joomla_helper();
	$user = $jhelper->get_user();
	$config_urls = $jhelper->trigger_get_urls_event();

	if ($user && !$user->guest) {
		$access = $jhelper->trigger_access_event($user);
		$level = AS_USER_LEVEL_BASIC;

		if ($access['post']) {
			$level = AS_USER_LEVEL_APPROVED;
		}
		if ($access['edit']) {
			$level = AS_USER_LEVEL_EDITOR;
		}
		if ($access['mod']) {
			$level = AS_USER_LEVEL_MODERATOR;
		}
		if ($access['admin']) {
			$level = AS_USER_LEVEL_ADMIN;
		}
		if ($access['super'] || $user->get('isRoot')) {
			$level = AS_USER_LEVEL_SUPER;
		}

		$teamGroup = $jhelper->trigger_team_group_event($user);

		$as_user = array(
			'userid' => $user->id,
			'publicusername' => $user->username,
			'email' => $user->email,
			'level' => $level,
		);

		if ($user->block) {
			$as_user['blocked'] = true;
		}

		return $as_user;
	}

	return null;
}

function as_get_user_email($userid)
{
	$jhelper = new as_joomla_helper();
	$user = $jhelper->get_user($userid);

	if ($user) {
		return $user->email;
	}

	return null;
}

function as_get_userids_from_public($publicusernames)
{
	$output = array();
	if (count($publicusernames)) {
		$jhelper = new as_joomla_helper();
		foreach ($publicusernames as $username) {
			$output[$username] = $jhelper->get_userid($username);
		}
	}
	return $output;
}

function as_get_public_from_userids($userids)
{
	$output = array();
	if (count($userids)) {
		$jhelper = new as_joomla_helper();
		foreach ($userids as $userID) {
			$user = $jhelper->get_user($userID);
			$output[$userID] = $user->username;
		}
	}
	return $output;
}

function as_get_logged_in_user_html($logged_in_user, $relative_url_prefix)
{
	$publicusername = $logged_in_user['publicusername'];
	return '<a href="' . as_path_html('user/' . $publicusername) . '" class="as-user-link">' . htmlspecialchars($publicusername) . '</a>';
}

function as_get_users_html($userids, $should_include_link, $relative_url_prefix)
{
	$useridtopublic = as_get_public_from_userids($userids);
	$usershtml = array();

	foreach ($userids as $userid) {
		$publicusername = $useridtopublic[$userid];
		$usershtml[$userid] = htmlspecialchars($publicusername);

		if ($should_include_link) {
			$usershtml[$userid] = '<a href="' . as_path_html('user/' . $publicusername) . '" class="as-user-link">' . $usershtml[$userid] . '</a>';
		}
	}

	return $usershtml;
}

function as_avatar_html_from_userid($userid, $size, $padding)
{
	$jhelper = new as_joomla_helper();
	$avatarURL = $jhelper->trigger_get_avatar_event($userid, $size);

	$avatarHTML = $avatarURL ? "<img src='{$avatarURL}' class='as-avatar-image' alt=''/>" : '';
	if ($padding) {
		// If $padding is true, the HTML you return should render to a square of $size x $size pixels, even if the avatar is not square.
		$avatarHTML = "<span style='display:inline-block; width:{$size}px; height:{$size}px; overflow:hidden;'>{$avatarHTML}</span>";
	}
	return $avatarHTML;
}

function as_user_report_action($userid, $action)
{
	$jhelper = new as_joomla_helper();
	$jhelper->trigger_log_event($userid, $action);
}


/**
 * Link to Joomla app.
 */
class as_joomla_helper
{
	private $app;

	public function __construct()
	{
		$this->find_joomla_path();
		$this->load_joomla_app();
	}

	private function find_joomla_path()
	{
		// JPATH_BASE must be defined for Joomla to work
		if (!defined('JPATH_BASE')) {
			define('JPATH_BASE', AS_FINAL_JOOMLA_INTEGRATE_PATH);
		}
	}

	private function load_joomla_app()
	{
		// This will define the _JEXEC constant that will allow us to access the rest of the Joomla framework
		if (!defined('_JEXEC')) {
			define('_JEXEC', 1);
		}

		require_once(JPATH_BASE . '/includes/defines.php');
		require_once(JPATH_BASE . '/includes/framework.php');
		// Instantiate the application.
		$this->app = JFactory::getApplication('site');
		// Initialise the application.
		$this->app->initialise();
	}

	public function get_app()
	{
		return $this->app;
	}

	public function get_user($userid = null)
	{
		return JFactory::getUser($userid);
	}

	public function get_userid($username)
	{
		return JUserHelper::getUserId($username);
	}

	public function trigger_access_event($user)
	{
		return $this->trigger_joomla_event('onQnaAccess', array($user));
	}

	public function trigger_team_group_event($user)
	{
		return $this->trigger_joomla_event('onTeamGroup', array($user));
	}

	public function trigger_get_urls_event()
	{
		return $this->trigger_joomla_event('onGetURLs', array());
	}

	public function trigger_get_avatar_event($userid, $size)
	{
		return $this->trigger_joomla_event('onGetAvatar', array($userid, $size));
	}

	public function trigger_log_event($userid, $action)
	{
		return $this->trigger_joomla_event('onWriteLog', array($userid, $action), false);
	}

	private function trigger_joomla_event($event, $args = array(), $expectResponse = true)
	{
		JPluginHelper::importPlugin('aps');
		$dispatcher = JEventDispatcher::getInstance();
		$results = $dispatcher->trigger($event, $args);

		if ($expectResponse && (!is_array($results) || count($results) < 1)) {
			// no APS plugins installed in Joomla, so we'll have to resort to defaults
			$results = $this->default_response($event, $args);
		}
		return array_pop($results);
	}

	private function default_response($event, $args)
	{
		return array(as_joomla_default_integration::$event($args));
	}
}

/**
 * Implements the same methods as a Joomla plugin would implement, but called locally within APS.
 * This is intended as a set of default actions in case no Joomla plugin has been installed. It's
 * recommended to install the Joomla QAIntegration plugin for additional user-access control.
 */
class as_joomla_default_integration
{
	/**
	 * If you're relying on the defaults, you must make sure that your Joomla instance has the following pages configured.
	 */
	public static function onGetURLs()
	{
		$signin = 'index.php?option=com_users&view=signin';
		$signout = 'index.php?option=com_users&tpost=user.signout&' . JSession::getFormToken() . '=1&return=' . urlencode(base64_encode('index.php'));
		$reg = 'index.php?option=com_users&view=registration';

		return array(
			// undo Joomla's escaping of characters since APS also escapes
			'signin' => htmlspecialchars_decode(JRoute::_($signin)),
			'signout' => htmlspecialchars_decode(JRoute::_($signout)),
			'reg' => htmlspecialchars_decode(JRoute::_($reg)),
			'denied' => htmlspecialchars_decode(JRoute::_('index.php')),
		);
	}

	/**
	 * Return the access levels available to the user. A proper Joomla plugin would allow you to fine tune this in as much
	 * detail as you needed, but this default method can only look at the core Joomla system permissions and try to map
	 * those to the APS perms. Not ideal; enough to get started, but recommend switching to the Joomla plugin if possible.
	 */
	public static function onQnaAccess(array $args)
	{
		list($user) = $args;

		return array(
			'view' => true,
			'post' => !($user->guest || $user->block),
			'edit' => $user->authorise('core.edit'),
			'mod' => $user->authorise('core.edit.state'),
			'admin' => $user->authorise('core.manage'),
			'super' => $user->authorise('core.admin') || $user->get('isRoot'),
		);
	}

	/**
	 * Return the group name (if any) that was responsible for granting the user access to the given view level.
	 * For this default method, we just won't return anything.
	 */
	public static function onTeamGroup($args)
	{
		list($user) = $args;
		return null;
	}

	/**
	 * This method would be used to post Joomla to supply an avatar for a user.
	 * For this default method, we just won't do anything.
	 */
	public static function onGetAvatar($args)
	{
		list($userid, $size) = $args;
		return null;
	}

	/**
	 * This method would be used to notify Joomla of a APS action, eg so it could write a log entry.
	 * For this default method, we just won't do anything.
	 */
	public static function onWriteLog($args)
	{
		list($userid, $action) = $args;
		return null;
	}
}
