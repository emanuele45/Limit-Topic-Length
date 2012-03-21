<?php 

/**
 * Limit Topics Length (LTL)
 *
 * @package LTL
 * @author emanuele
 * @copyright 2011 emanuele, Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.0
 */

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');
  
global $hooks, $mod_name;
$hooks = array(
	'integrate_pre_include' => '$sourcedir/Subs-LimitTopicLength.php',
	'integrate_general_mod_settings' => 'limit_topic_length_add_modsettings',
);
$mod_name = 'Integration Hooks Report';

// ---------------------------------------------------------------------------------------------------------------------
define('SMF_INTEGRATION_SETTINGS', serialize(array(
	'integrate_menu_buttons' => 'install_menu_button',)));

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	exit('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

if (SMF == 'SSI')
{
	// Let's start the main job
	install_mod();
	// and then let's throw out the template! :P
	obExit(null, null, true);
}
else
{
	setup_hooks();
}

function install_mod ()
{
	global $context, $mod_name;

	$context['mod_name'] = $mod_name;
	$context['sub_template'] = 'install_script';
	$context['page_title_html_safe'] = 'Install script of the mod: ' . $mod_name;
	if (isset($_GET['action']))
		$context['uninstalling'] = $_GET['action'] == 'uninstall' ? true : false;
	$context['html_headers'] .= '
	<style type="text/css">
    .buttonlist ul {
      margin:0 auto;
			display:table;
		}
	</style>';

	// Sorry, only logged in admins...
	isAllowedTo('admin_forum');

	if (isset($context['uninstalling']))
		setup_hooks();
}

function setup_hooks ()
{
	global $context, $hooks;

	$integration_function = empty($context['uninstalling']) ? 'add_integration_function' : 'remove_integration_function';
	foreach ($hooks as $hook => $function)
		$integration_function($hook, $function);

	$context['installation_done'] = true;
}

function install_menu_button (&$buttons)
{
	global $boardurl, $context;

	$context['sub_template'] = 'install_script';
	$context['current_action'] = 'install';

	$buttons['install'] = array(
		'title' => 'Installation script',
		'show' => allowedTo('admin_forum'),
		'href' => $boardurl . '/install.php',
		'active_button' => true,
		'sub_buttons' => array(
		),
	);
}

function template_install_script ()
{
	global $boardurl, $context;

	echo '
	<div class="tborder login"">
		<div class="cat_bar">
			<h3 class="catbg">
				Welcome to the install script of the mod: ' . $context['mod_name'] . '
			</h3>
		</div>
		<span class="upperframe"><span></span></span>
		<div class="roundframe centertext">';
	if (!isset($context['installation_done']))
		echo '
			<strong>Please select the action you want to perform:</strong>
			<div class="buttonlist">
				<ul>
					<li>
						<a class="active" href="' . $boardurl . '/install.php?action=install">
							<span>Install</span>
						</a>
					</li>
					<li>
						<a class="active" href="' . $boardurl . '/install.php?action=uninstall">
							<span>Uninstall</span>
						</a>
					</li>
				</ul>
			</div>';
	else
		echo '<strong>Database adaptation successful!</strong>';

	echo '
		</div>
		<span class="lowerframe"><span></span></span>
	</div>';
}
?>