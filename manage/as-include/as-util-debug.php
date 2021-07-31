<?php
/**
 * @deprecated This file is deprecated from APS 1.7; use APS_Util_Usage class (APS/Util/Usage.php) instead.
 *
 * The functions in this file are maintained for backwards compatibility, but simply call through to the
 * new class where applicable.
 */

if (!defined('AS_VERSION')) {
	header('Location: ../');
	exit;
}

if (defined('AS_DEBUG_PERFORMANCE') && AS_DEBUG_PERFORMANCE) {
	trigger_error('Included file ' . basename(__FILE__) . ' is deprecated');
}

function as_usage_init()
{
	// should already be initialised in as-base.php
	global $as_usage;
	if (empty($as_usage))
		$as_usage = new APS_Util_Usage;
}

function as_usage_get()
{
	global $as_usage;
	return $as_usage->getCurrent();
}

function as_usage_delta($oldusage, $newusage)
{
	// equivalent function is now private
	return array();
}

function as_usage_mark($stage)
{
	global $as_usage;
	return $as_usage->mark($stage);
}

function as_usage_line($stage, $usage, $totalusage)
{
	// equivalent function is now private
	return '';
}

function as_usage_output()
{
	global $as_usage;
	return $as_usage->output();
}
