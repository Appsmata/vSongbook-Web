<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Database-level access to user points and statistics


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


/**
 * Returns an array of option names required to perform calculations in userpoints table
 */
function as_db_points_option_names()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	return array(
		'points_post_q', 'points_select_a', 'points_per_q_thumbd_up', 'points_per_q_thumbd_down', 'points_q_thumbd_max_gain', 'points_q_thumbd_max_loss',
		'points_post_a', 'points_a_selected', 'points_per_a_thumbd_up', 'points_per_a_thumbd_down', 'points_a_thumbd_max_gain', 'points_a_thumbd_max_loss',
		'points_per_c_thumbd_up', 'points_per_c_thumbd_down', 'points_c_thumbd_max_gain', 'points_c_thumbd_max_loss',
		'points_thumb_up_q', 'points_thumb_down_q', 'points_thumb_up_a', 'points_thumb_down_a',

		'points_multiple', 'points_base',
	);
}


/**
 * Returns an array containing all the calculation formulae for the userpoints table. Each element of this
 * array is for one column - the key contains the column name, and the value is a further array of two elements.
 * The element 'formula' contains the SQL fragment that calculates the columns value for one or more users,
 * where the ~ symbol within the fragment is substituted for a constraint on which users we are interested in.
 * The element 'multiple' specifies what to multiply each column by to create the final sum in the points column.
 */
function as_db_points_calculations()
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	require_once AS_INCLUDE_DIR . 'app/options.php';

	$options = as_get_options(as_db_points_option_names());

	return array(
		'qposts' => array(
			'multiple' => $options['points_multiple'] * $options['points_post_q'],
			'formula' => "COUNT(*) AS qposts FROM ^posts AS userid_src WHERE userid~ AND type='S'",
		),

		'aposts' => array(
			'multiple' => $options['points_multiple'] * $options['points_post_a'],
			'formula' => "COUNT(*) AS aposts FROM ^posts AS userid_src WHERE userid~ AND type='R'",
		),

		'cposts' => array(
			'multiple' => 0,
			'formula' => "COUNT(*) AS cposts FROM ^posts AS userid_src WHERE userid~ AND type='C'",
		),

		'aselects' => array(
			'multiple' => $options['points_multiple'] * $options['points_select_a'],
			'formula' => "COUNT(*) AS aselects FROM ^posts AS userid_src WHERE userid~ AND type='S' AND selchildid IS NOT NULL",
		),

		'aselecteds' => array(
			'multiple' => $options['points_multiple'] * $options['points_a_selected'],
			'formula' => "COUNT(*) AS aselecteds FROM ^posts AS userid_src JOIN ^posts AS songs ON songs.selchildid=userid_src.postid WHERE userid_src.userid~ AND userid_src.type='R' AND NOT (songs.userid<=>userid_src.userid)",
		),

		'qthumbsup' => array(
			'multiple' => $options['points_multiple'] * $options['points_thumb_up_q'],
			'formula' => "COUNT(*) AS qthumbsup FROM ^userthumbs AS userid_src JOIN ^posts ON userid_src.postid=^posts.postid WHERE userid_src.userid~ AND LEFT(^posts.type, 1)='S' AND userid_src.thumb>0",
		),

		'qthumbsdown' => array(
			'multiple' => $options['points_multiple'] * $options['points_thumb_down_q'],
			'formula' => "COUNT(*) AS qthumbsdown FROM ^userthumbs AS userid_src JOIN ^posts ON userid_src.postid=^posts.postid WHERE userid_src.userid~ AND LEFT(^posts.type, 1)='S' AND userid_src.thumb<0",
		),

		'athumbsup' => array(
			'multiple' => $options['points_multiple'] * $options['points_thumb_up_a'],
			'formula' => "COUNT(*) AS athumbsup FROM ^userthumbs AS userid_src JOIN ^posts ON userid_src.postid=^posts.postid WHERE userid_src.userid~ AND LEFT(^posts.type, 1)='R' AND userid_src.thumb>0",
		),

		'athumbsdown' => array(
			'multiple' => $options['points_multiple'] * $options['points_thumb_down_a'],
			'formula' => "COUNT(*) AS athumbsdown FROM ^userthumbs AS userid_src JOIN ^posts ON userid_src.postid=^posts.postid WHERE userid_src.userid~ AND LEFT(^posts.type, 1)='R' AND userid_src.thumb<0",
		),

		'cthumbsup' => array(
			'multiple' => 0,
			'formula' => "COUNT(*) AS cthumbsup FROM ^userthumbs AS userid_src JOIN ^posts ON userid_src.postid=^posts.postid WHERE userid_src.userid~ AND LEFT(^posts.type, 1)='C' AND userid_src.thumb>0",
		),

		'cthumbsdown' => array(
			'multiple' => 0,
			'formula' => "COUNT(*) AS cthumbsdown FROM ^userthumbs AS userid_src JOIN ^posts ON userid_src.postid=^posts.postid WHERE userid_src.userid~ AND LEFT(^posts.type, 1)='C' AND userid_src.thumb<0",
		),

		'qthumbds' => array(
			'multiple' => $options['points_multiple'],
			'formula' => "COALESCE(SUM(" .
				"LEAST(" . ((int)$options['points_per_q_thumbd_up']) . "*thumbsup," . ((int)$options['points_q_thumbd_max_gain']) . ")" .
				"-" .
				"LEAST(" . ((int)$options['points_per_q_thumbd_down']) . "*thumbsdown," . ((int)$options['points_q_thumbd_max_loss']) . ")" .
				"), 0) AS qthumbds FROM ^posts AS userid_src WHERE LEFT(type, 1)='S' AND userid~",
		),

		'athumbds' => array(
			'multiple' => $options['points_multiple'],
			'formula' => "COALESCE(SUM(" .
				"LEAST(" . ((int)$options['points_per_a_thumbd_up']) . "*thumbsup," . ((int)$options['points_a_thumbd_max_gain']) . ")" .
				"-" .
				"LEAST(" . ((int)$options['points_per_a_thumbd_down']) . "*thumbsdown," . ((int)$options['points_a_thumbd_max_loss']) . ")" .
				"), 0) AS athumbds FROM ^posts AS userid_src WHERE LEFT(type, 1)='R' AND userid~",
		),

		'cthumbds' => array(
			'multiple' => $options['points_multiple'],
			'formula' => "COALESCE(SUM(" .
				"LEAST(" . ((int)$options['points_per_c_thumbd_up']) . "*thumbsup," . ((int)$options['points_c_thumbd_max_gain']) . ")" .
				"-" .
				"LEAST(" . ((int)$options['points_per_c_thumbd_down']) . "*thumbsdown," . ((int)$options['points_c_thumbd_max_loss']) . ")" .
				"), 0) AS cthumbds FROM ^posts AS userid_src WHERE LEFT(type, 1)='C' AND userid~",
		),

		'upthumbds' => array(
			'multiple' => 0,
			'formula' => "COALESCE(SUM(thumbsup), 0) AS upthumbds FROM ^posts AS userid_src WHERE userid~",
		),

		'downthumbds' => array(
			'multiple' => 0,
			'formula' => "COALESCE(SUM(thumbsdown), 0) AS downthumbds FROM ^posts AS userid_src WHERE userid~",
		),
	);
}


/**
 * Update the userpoints table in the database for $userid and $columns, plus the summary points column.
 * Set $columns to true for all, empty for none, an array for several, or a single value for one.
 * This dynamically builds some fairly crazy looking SQL, but it works, and saves repeat calculations.
 * @param $userid
 * @param $columns
 * @return mixed
 */
function as_db_points_update_ifuser($userid, $columns)
{
	if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

	if (as_should_update_counts() && isset($userid)) {
		require_once AS_INCLUDE_DIR . 'app/options.php';
		require_once AS_INCLUDE_DIR . 'app/cookies.php';

		$calculations = as_db_points_calculations();

		if ($columns === true) {
			$keycolumns = $calculations;
		} elseif (empty($columns)) {
			$keycolumns = array();
		} elseif (is_array($columns)) {
			$keycolumns = array_flip($columns);
		} else {
			$keycolumns = array($columns => true);
		}

		$insertfields = 'userid, ';
		$insertvalues = '$, ';
		$insertpoints = (int)as_opt('points_base');

		$updates = '';
		$updatepoints = $insertpoints;

		foreach ($calculations as $field => $calculation) {
			$multiple = (int)$calculation['multiple'];

			if (isset($keycolumns[$field])) {
				$insertfields .= $field . ', ';
				$insertvalues .= '@_' . $field . ':=(SELECT ' . $calculation['formula'] . '), ';
				$updates .= $field . '=@_' . $field . ', ';
				$insertpoints .= '+(' . (int)$multiple . '*@_' . $field . ')';
			}

			$updatepoints .= '+(' . $multiple . '*' . (isset($keycolumns[$field]) ? '@_' : '') . $field . ')';
		}

		$query = 'INSERT INTO ^userpoints (' . $insertfields . 'points) VALUES (' . $insertvalues . $insertpoints . ') ' .
			'ON DUPLICATE KEY UPDATE ' . $updates . 'points=' . $updatepoints . '+bonus';

		// build like this so that a #, $ or ^ character in the $userid (if external integration) isn't substituted
		as_db_query_raw(str_replace('~', "='" . as_db_escape_string($userid) . "'", as_db_apply_sub($query, array($userid))));

		if (as_db_insert_on_duplicate_inserted()) {
			as_db_userpointscount_update();
		}
	}
}


/**
 * Set the number of explicit bonus points for $userid to $bonus
 * @param $userid
 * @param $bonus
 */
function as_db_points_set_bonus($userid, $bonus)
{
	as_db_query_sub(
		"INSERT INTO ^userpoints (userid, bonus) VALUES ($, #) ON DUPLICATE KEY UPDATE bonus=#",
		$userid, $bonus, $bonus
	);
}


/**
 * Update the cached count in the database of the number of rows in the userpoints table
 */
function as_db_userpointscount_update()
{
	if (as_should_update_counts()) {
		as_db_query_sub(
			"INSERT INTO ^options (title, content) " .
			"SELECT 'cache_userpointscount', COUNT(*) FROM ^userpoints " .
			"ON DUPLICATE KEY UPDATE content = VALUES(content)"
		);
	}
}
