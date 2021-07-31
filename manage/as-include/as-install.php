<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: User interface for installing, upgrading and fixing the database


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
	header('Location: ../');
	exit;
}

require_once AS_INCLUDE_DIR.'db/install.php';

as_report_process_stage('init_install');


// Define database failure handler for install process, if not defined already (file could be included more than once)

if (!function_exists('as_install_db_fail_handler')) {
	/**
	 * Handler function for database failures during the installation process
	 * @param $type
	 * @param int $errno
	 * @param string $error
	 * @param string $query
	 */
	function as_install_db_fail_handler($type, $errno = null, $error = null, $query = null)
	{
		global $pass_failure_from_install;

		$pass_failure_type = $type;
		$pass_failure_errno = $errno;
		$pass_failure_error = $error;
		$pass_failure_query = $query;
		$pass_failure_from_install = true;

		require AS_INCLUDE_DIR.'as-install.php';

		as_exit('error');
	}
}


if (ob_get_level() > 0) {
	// clears any current theme output to prevent broken design
	ob_end_clean();
}

$pgtitle = $success = $errorhtml = $suggest = '';
$buttons = array();
$fields = array();
$fielderrors = array();
$hidden = array();

// Process user handling higher up to avoid 'headers already sent' warning

if (!isset($pass_failure_type) && as_clicked('super')) {
	require_once AS_INCLUDE_DIR.'db/admin.php';
	require_once AS_INCLUDE_DIR.'db/users.php';
	require_once AS_INCLUDE_DIR.'app/users-edit.php';

	if (as_db_count_users() == 0) { // prevent creating multiple accounts
		$infirstname = as_post_text('as_firstname');
		$inlastname = as_post_text('as_lastname');
		$inemail = as_post_text('as_email');
		$inpassword = as_post_text('as_password');
		$inhandle = as_post_text('as_handle');

		$fielderrors = array_merge(
			as_handle_email_filter($inhandle, $inemail),
			as_password_validate($inpassword)
		);

		if (empty($fielderrors)) {
			require_once AS_INCLUDE_DIR.'app/users.php';

			$userid = as_create_new_user($infirstname, $inlastname, null, null, null, null, null, $inhandle, $inemail, $inpassword, AS_USER_LEVEL_SUPER);
			//$userid = as_create_new_user($infirstname, $inlastname, $incountry, $inmobile, $ingender, $incity, $inchurch, $inhandle, $inemail, $inpassword, AS_USER_LEVEL_SUPER);
			as_set_logged_in_user($userid, $inhandle);

			as_set_option('feedback_email', $inemail);
			
			$pgtitle = 'Congratulations - Your vSongBook site is ready to go!';
			$success .= "You are logged in as the super administrator and can start changing settings.\n\nThank you for installing vSongBook.";
		}
	}
}

if (as_clicked('connectdb')) {
	$database = as_post_text('as_database');
	$username = as_post_text('as_username');
	$password = as_post_text('as_password');
			
	$filename = AS_BASE_DIR . 'as-config.php';
	$lines = file($filename, FILE_IGNORE_NEW_LINES );
	$lines[37] = "	define('AS_MYSQL_USERNAME', '".$username."');";
	$lines[38] = "	define('AS_MYSQL_PASSWORD', '".$password."');";
	$lines[39] = "	define('AS_MYSQL_DATABASE', '".$database."');";
	file_put_contents($filename, implode("\n", $lines));
	header("location: index.php");
}


// Output start of HTML early, so we can see a nicely-formatted list of database queries when upgrading

?><!DOCTYPE html>
<html>
	<head>
		<title>vSongBook!</title>
		<style>
			body { font-family: arial,sans-serif; font-size:0px; margin: 0; padding: 0;  background: url(<?php echo as_path_to_root() ?>as-media/as-bg.jpg) fixed; background-size: 100%; color: #000; } h1{font-size:30px;} .selecta, input[type="text"],input[type="email"],input[type="password"],textarea{font-size:18px; padding:5px;width:100%; color:#000; } table{ width:98%;} input[type="submit"]{ color:#000; padding:5px 20px; font-size:25px; margin: 10px; } img { border: 0; } .outer_one { } .inner_one { } .inner_other { margin-top:10px; padding:20px;  } #content { margin: 0 auto;	width: 800px; } .title-section-error { background-color: rgba(256,0,0, 0.5); border-radius: 3px; border: 1px solid #f00; color: #fff; font-weight: bold; padding: 12px ;} .title-section-success { background-color: rgba(0,256,0, 0.5);  border-radius: 3px; border: 1px solid #0f0; color: #fff; font-weight: bold; padding: 12px ;} #debug { margin-top: 50px; }.main-section-error { font-size:20px;}.main-section-success { font-size:20px;} .content-section-error { background:rgba(256,256,256, 0.5); margin-top: 10px; font-size:20px; padding: 10px; border-radius: 3px; border: 1px solid #fff; }.content-section-success { background:rgba(256,256,256, 0.5); margin-top: 10px; font-size:20px; border-radius: 3px; border: 1px solid #000; padding: 10px; } .content-section-footer{background: rgba(0,0,0, 0.5); color: #fff; border-radius: 3px; border: 1px solid #000;}
		</style>
	</head>
	<body>
		<div id="content">
<?php
if (isset($pass_failure_type)) {
	// this page was requested due to query failure, via the fail handler
	switch ($pass_failure_type) {
		case 'connect':
			$pgtitle .= 'You need to connect to the Database!';
			$errorhtml .= 'Could not establish database connection. Please enter the correct details.';
			$fields = array(
				'as_database' => array( 'label' => 'Database Name:', 'type' => 'text', 'tags' => 'required' ),
				'as_username' => array( 'label' => 'Database Username:', 'type' => 'text', 'tags' => 'required' ),
				'as_password' => array( 'label' => 'Database Password:', 'type' => 'password' ),
			);
			$buttons = array('connectdb' => 'Connect to the Database');
			break;
			
		case 'select':
			$pgtitle .= 'Database switching Failed';
			$errorhtml .= 'Could not switch to the vSongBook database. Please check the database name in the config file, and if necessary create the database in MySQL and grant appropriate user privileges.';
			break;

		case 'query':
			global $pass_failure_from_install;

			if (@$pass_failure_from_install) {
				$pgtitle .= 'Installation Query Failed';
				$errorhtml .= "vSongBook was unable to perform the installation query below. Please check the user in the config file has CREATE and ALTER permissions:\n\n".as_html($pass_failure_query."\n\nError ".$pass_failure_errno.": ".$pass_failure_error."\n\n");
			}
			else {
				$pgtitle = 'A Database Query Failed';
				$errorhtml .= "An vSongBook database query failed when generating this page.\n\nA full description of the failure is available in the web server's error log file.";
			}
			break;
	}
}
else {
	// this page was requested by user GET/POST, so handle any incoming clicks on buttons

	if (as_clicked('create')) {
		as_db_install_tables();
		$pgtitle = 'Your vSongBook database has been created';

		if (AS_FINAL_EXTERNAL_USERS) {
			if (defined('AS_FINAL_WORDPRESS_INTEGRATE_PATH')) {
				require_once AS_INCLUDE_DIR.'db/admin.php';
				require_once AS_INCLUDE_DIR.'app/format.php';

				// create link back to WordPress home page
				as_db_page_move(as_db_page_create(get_option('blogname'), AS_PAGE_FLAGS_EXTERNAL, get_option('home'), null, null, null), 'O', 1);

				$success .= 'Your vSongBook database has been created and integrated with your WordPress site.';

			}
			elseif (defined('AS_FINAL_JOOMLA_INTEGRATE_PATH')) {
				require_once AS_INCLUDE_DIR.'db/admin.php';
				require_once AS_INCLUDE_DIR.'app/format.php';
				$jconfig = new JConfig();

				// create link back to Joomla! home page (Joomla doesn't have a 'home' config setting we can use like WP does, so we'll just assume that the Joomla home is the parent of the APS site. If it isn't, the user can fix the link for themselves later)
				as_db_page_move(as_db_page_create($jconfig->sitename, AS_PAGE_FLAGS_EXTERNAL, '../', null, null, null), 'O', 1);
				$success .= 'Your vSongBook database has been created and integrated with your Joomla! site.';
			}
			else {
				$success .= 'Your vSongBook database has been created for external user identity management. Please read the online documentation to complete integration.';
			}
		}
		else {
			$success .= 'Your vSongBook database has been created.';
		}
	}

	if (as_clicked('nonuser')) {
		as_db_install_tables();
		$pgtitle = 'The Database Operation has been successful';
		$success .= 'The additional vSongBook database tables have been created.';
	}

	if (as_clicked('upgrade')) {
		as_db_upgrade_tables();
		$pgtitle = 'Your vSongBook database has been updated';
		$success .= 'Your vSongBook database has been updated.';
	}

	if (as_clicked('repair')) {
		as_db_install_tables();
		$pgtitle = 'Your vSongBook database has been repaired';
		$success .= 'The vSongBook database tables have been repaired.';
	}

	as_initialize_postdb_plugins();
	if (as_clicked('module')) {
		$moduletype = as_post_text('moduletype');
		$modulename = as_post_text('modulename');

		$module = as_load_module($moduletype, $modulename);

		$queries = $module->init_queries(as_db_list_tables());

		if (!empty($queries)) {
			if (!is_array($queries))
				$queries = array($queries);

			foreach ($queries as $query)
				as_db_upgrade_query($query);
		}

		$pgtitle = $modulename.' '.$moduletype.' module initialized';
		$success .= 'The '.$modulename.' '.$moduletype.' module has completed database initialization.';
	}

}

if (as_db_connection(false) !== null && !@$pass_failure_from_install) {
	$check = as_db_check_tables(); // see where the database is at

	switch ($check) {
		case 'none':
			if (@$pass_failure_errno == 1146) // don't show error if we're in installation process
				$errorhtml = '';
			$pgtitle = 'Welcome to vSongBook';
			$errorhtml .= 'Welcome to vSongBook. It\'s time to set up your database!';

			if (AS_FINAL_EXTERNAL_USERS) {
				if (defined('AS_FINAL_WORDPRESS_INTEGRATE_PATH')) {
					$errorhtml .= "\n\nWhen you click below, your vSongBook site will be set up to integrate with the users of your WordPress site <a href=\"".as_html(get_option('home'))."\" target=\"_blank\">".as_html(get_option('blogname'))."</a>. Please consult the online documentation for more information.";
				}
				elseif (defined('AS_FINAL_JOOMLA_INTEGRATE_PATH')) {
					$jconfig = new JConfig();
					$errorhtml .= "\n\nWhen you click below, your vSongBook site will be set up to integrate with the users of your Joomla! site <a href=\"../\" target=\"_blank\">".$jconfig->sitename."</a>. It's also recommended to install the Joomla BEIntegration plugin for additional user-access control. Please consult the online documentation for more information.";
				}
				else {
					$errorhtml .= "\n\nWhen you click below, your vSongBook site will be set up to integrate with your existing user database and management. Members will be referenced with database column type ".as_html(as_get_mysql_user_column_type()).". Please consult the online documentation for more information.";
				}

				$buttons = array('create' => 'Set up the Database');
			}
			else {
				$errorhtml .= "\n\nWhen you click below, your vSongBook database will be set up to manage user identities and signins internally.\n\nIf you want to offer a single sign-on for an existing user base or website, please consult the online documentation before proceeding.";
				$buttons = array('create' => 'Set up the Database including User Management');
			}
			break;

		case 'old-version':
			$pgtitle = 'Need to Upgrade vSongBook Database';
			// don't show error if we need to upgrade
			if (!@$pass_failure_from_install)
				$errorhtml = '';

			// don't show error before this
			$errorhtml .= 'Your vSongBook database needs to be upgraded for this version of the software.';
			$buttons = array('upgrade' => 'Upgrade the Database');
			break;

		case 'non-users-missing':		
			$pgtitle = 'Non-Members Missing on your vSongBook';
			$errorhtml = 'This vSongBook site is sharing its users with another APS site, but it needs some additional database tables for its own content. Please click below to create them.';
			$buttons = array('nonuser' => 'Set up the Tables');
			break;

		case 'table-missing':
			$pgtitle = 'Your vSongBook Database is missing some Tables';
			$errorhtml .= 'One or more tables are missing from your vSongBook database.';
			$buttons = array('repair' => 'Repair the Database');
			break;

		case 'column-missing':
			$pgtitle = 'Database Missing Columns';
			$errorhtml .= 'One or more vSongBook database tables are missing a column.';
			$buttons = array('repair' => 'Repair the Database');
			break;

		default:
			require_once AS_INCLUDE_DIR.'db/admin.php';

			if (!AS_FINAL_EXTERNAL_USERS && as_db_count_users() == 0) {
				$pgtitle = 'Set up a Super Admin';
				$errorhtml .= "There are currently no users in the vSongBook database.\n\nPlease enter your details below to create the super administrator:";
				$fields = array(
					'as_firstname' => array('label' => 'First Name:', 'type' => 'text' ),
					'as_lastname' => array('label' => 'Last Name:', 'type' => 'text' ),
					'as_email' => array('label' => 'Email address:', 'type' => 'email' ),
					'as_handle' => array('label' => 'Username:', 'type' => 'text' ),
					'as_password' => array('label' => 'Password:', 'type' => 'password' ),
					//'passcon' => array('label' => 'Confirm Password:', 'type' => 'password' ),
				);
				$buttons = array('super' => 'Set up the Super Administrator');
			}
			else {
				$tables = as_db_list_tables();

				$moduletypes = as_list_module_types();

				foreach ($moduletypes as $moduletype) {
					$modules = as_load_modules_with($moduletype, 'init_queries');

					foreach ($modules as $modulename => $module) {
						$queries = $module->init_queries($tables);
						if (!empty($queries)) {
							// also allows single query to be returned
							$errorhtml = strtr(as_lang_html('admin/module_x_database_init'), array(
								'^1' => as_html($modulename),
								'^2' => as_html($moduletype),
								'^3' => '',
								'^4' => '',
							));

							$buttons = array('module' => 'Initialize the Database');

							$hidden['moduletype'] = $moduletype;
							$hidden['modulename'] = $modulename;
							break;
						}
					}
				}
			}
			break;
	}
}

if (empty($errorhtml)) {
	if (empty($success)) {
		$pgtitle = 'Database has been checked';
		$success = 'Your vSongBook database has been checked with no problems.';
	}
	$suggest = '<a href="'.as_path_html('admin', null, null, AS_URL_FORMAT_SAFEST).'">Go to admin center</a>';
}

if (strlen($errorhtml)) {
	echo '<div class="main-section-error rounded">
			<div class="title-section-error rounded_i">'."\n\t\t	
				<h1>".$pgtitle."</h1>\n\t\t
			</div>\n\t\t";
	echo '<form method="post" action="'.as_path_html('install', null, null, AS_URL_FORMAT_SAFEST).'">'."\n\t\t";
	echo '<div class="content-section-error">'."\n\t\t";
	echo '<p class="msg-error">'.nl2br(as_html($errorhtml))."</p>\n\t\t";
} 
elseif (strlen($success)) {
	echo '<div class="main-section-success rounded">
			<div class="title-section-success rounded_i">'."\n\t\t	
				<h1>".$pgtitle."</h1>\n\t\t
			</div>\n\t\t";
	echo '<form method="post" action="'.as_path_html('install', null, null, AS_URL_FORMAT_SAFEST).'">'."\n\t\t";
	echo '<div class="content-section-success">'."\n\t\t";
	echo '<p class="msg-success">'.nl2br(as_html($success))."</p>\n\t\t";
}

if (strlen($suggest)) echo '<p>'.$suggest.'</p>'."\n\t\t";

// Very simple general form display logic (we don't use theme since it depends on tons of DB options)

if (count($fields)) {
	echo "\n\t<hr/>\n\t".'<table style="text-align:left;">'."\n\t\t";
	
	foreach($fields as $name => $field) {
		echo '<tr><th>'.as_html($field['label']).'</th><td>'."\n\t\t";
		if (array_key_exists('tags', $field)) $tags =  ' ' . as_html($field['tags']);
		else $tags = '';
		switch ( $field['type'] ) {		
			case  'select'	:	{
					echo '<select class="selecta" name="'.as_html($name).'"'.$tags.'>';
					foreach ($field['options'] as $option) 
						echo '<option value="'.$option.'">'.$option.'</option>'."\n\t\t\t";
					echo "</select></td>\n\t\t";
				}
				break;
			case 'radio' 	:	{
					$i = 0; 
					foreach ($field['options'] as $option) {
						echo '<label class="required input_radio">',
						'<input type="'.as_html($field['type']).'" name="'.as_html($name).
						'" value="'.$field['values'][$i].'"'.$tags.'/> '.$option.' </label>';
						$i++;
					}
					echo "</td>\n\t\t";
				}
				break;
			default:
				echo '<input type="'.as_html($field['type']).'" name="'.as_html($name).'"'.$tags.'/></td>'."\n\t\t";
		}
		
		if (isset($fielderrors[$name]))
			echo '<td class="msg-error"><small>'.as_html($fielderrors[$name]).'</small></td>'."\n\t\t";
		else
			echo "<td></td>\n\t\t";
		echo "</tr>\n\t\t";
	}
	echo '</table>';
}

foreach ($buttons as $name => $value)
	echo '<div align="right"><input type="submit" name="'.as_html($name).'" value="'.as_html($value).'"/></div>'."\n\t\t";

foreach ($hidden as $name => $value)
	echo '<input type="hidden" name="'.as_html($name).'" value="'.as_html($value).'"/>'."\n\t\t";

as_db_disconnect();

?>
			<br><br></div>

		</form>
		<div class="content-section-footer inner_other">
			<center>
				<p>Copyright &copy; vSongBook by AppSmata Sol. <?php echo date('Y') ?></p>
			</center>
		</div>
	</body>
</html>
