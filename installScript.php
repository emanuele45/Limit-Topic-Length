<?php 

/**
 * Limit Topics Length (LTL)
 *
 * @package LTL
 * @author emanuele
 * @copyright 2011 emanuele
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.0
 */

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');

if (!isset($modSettings['max_length_for_topics']))
	updateSettings(array('max_length_for_topics' => 1500));

db_extend('packages');
$smcFunc['db_add_column'] (
			'{db_prefix}topics', 
			array(
			      'name' => 'id_new_topic',
			      'type' => 'mediumint',
			      'default' => '0'
			),
			array(),
			'ignore'
		);
$smcFunc['db_add_column'] (
			'{db_prefix}topics', 
			array(
			      'name' => 'id_old_topic',
			      'type' => 'mediumint',
			      'default' => '0'
			),
			array(),
			'ignore'
		);
$smcFunc['db_add_column'] (
			'{db_prefix}topics', 
			array(
			      'name' => 'num_topics',
			      'type' => 'tinyint',
			      'default' => '0'
			),
			array(),
			'ignore'
		);

?>