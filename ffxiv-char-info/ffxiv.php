<?php
/*
Plugin Name: FFXI Character Stats for Wordpress
Description: Add FFXIV Character Information to your site.
Version: 1.0
Author: Demonicpagan
Author URI: http://ffxiv.stelth2000inc.com


Copyright 2007-2012  Demonicpagan  (email : demonicpagan@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// define <!-- FFXIV_port --> and <!-- FFXIV_land -->
define('WP_STR_SHOW_FFXIV_NFO_PORT', '/<!-- FFXIV_port(\((([0-9],|[0-9]|,)*?)\))? -->/i');
define('WP_STR_SHOW_FFXIV_NFO_LAND', '/<!-- FFXIV_land(\((([0-9],|[0-9]|,)*?)\))? -->/i');

require('wplib/utils_formbuilder.inc.php');
require('wplib/utils_sql.inc.php');
require('wplib/utils_tablebuilder.inc.php');

include_once('api.php');

function FFXIV_install()
{
	global $wpdb;

	// Create Default Settings
	if (!get_option('FFXIV_setting_name'))
		update_option('FFXIV_setting_name', 'Some name');

	if (!get_option('FFXIV_setting_server'))
		update_option('FFXIV_setting_server', 'Durandal');

	$wpdb->show_errors();
}

// Admin section
// -------------
add_action('admin_menu', 'FFXIV_menu');

function FFXIV_menu()
{
	add_menu_page('FFXIV Character Information', 'FFXIV Config', 8, __FILE__, 'FFXIV_Conf');
}

function FFXIV_Conf()
{
?>
	<div class="wrap">
	<div id="icon-options-general" class="icon32">
	<br />
	</div>
	<h2>Final Fantasy XIV Character Configuration</h2>
<?php
	// Get all the options from the database for the form
	$setting_char_name = get_option('FFXIV_setting_name');
	$setting_char_server = get_option('FFXIV_setting_server');
	$setting_avatar_size = get_option('FFXIV_setting_asize');

	// Check if updated data.
	if(isset($_POST) && isset($_POST['update']))
	{
		$setting_char_name = trim($_POST['FFXIV_setting_name']);
		$setting_char_server = trim($_POST['FFXIV_setting_server']);
		$setting_avatar_size = trim($_POST['FFXIV_setting_asize']);

		update_option('FFXIV_setting_name', $setting_char_name);
		update_option('FFXIV_setting_server', $setting_char_server);
		update_option('FFXIV_setting_asize', $setting_avatar_size);
	}

	$asizes = array("Small" => 50, "Medium" => 64, "Large" => 96);

	// Build the form
	$form = new FormBuilder();

	$formElem = new FormElement('FFXIV_setting_name', 'FFXIV Character Name');
	$formElem->value = $setting_char_name;
	$formElem->description = "Some Name";
	$form->addFormElement($formElem);

	$formElem = new FormElement('FFXIV_setting_server', 'FFXIV Character Server');
	$formElem->value = $setting_char_server;
	$formElem->description = "Sargatanas";
	$form->addFormElement($formElem);

	$formElem = new FormElement('FFXIV_setting_asize', 'FFXIV Avatar Size');
	$formElem->value = $setting_avatar_size;
	$formElem->description = "Size of Avatar";
	$formElem->setTypeAsRadioButtons($asizes);
	$form->addFormElement($formElem);

	echo $form->toString();
}

function ffxiv_replace_string($searchstr, $replacestr, $haystack) {

	// Faster, but in PHP5.
	if (function_exists("str_ireplace")) {
		return str_ireplace($searchstr, $replacestr, $haystack);
	}
	// Slower but handles PHP4
	else { 
		return preg_replace("/$searchstr/i", $replacestr, $haystack);
	}
}

// Display character information
// ------------------------------
// <!-- FFXIV_land -->
function FFXIV_Character_show_land($oldcontent)
{
	// Ensure we don't lose the original page
	$newcontent = $oldcontent;

	// Detect if we need to render the information by looking for the 
	// special string <!-- FFXIV_land -->
	if (preg_match(WP_STR_SHOW_FFXIV_NFO_LAND, $oldcontent, $matches))
	{
		// Turn DB stuff into HTML
		$content = FFXIV_Render_Land();

		// Now replace search string with formatted information
		$newcontent = ffxiv_replace_string($matches[0], $content, $oldcontent);
	}
	return $newcontent;
}

add_filter('widget_text', 'FFXIV_Character_show_port');

// <!-- FFXIV_port -->
function FFXIV_Character_show_port($oldcontent)
{
	// Ensure we don't lose the original page
	$newcontent = $oldcontent;

	// Detect if we need to render the information by looking for the 
	// special string <!-- FFXIV_port -->
	if (preg_match(WP_STR_SHOW_FFXIV_NFO_PORT, $oldcontent, $matches))
	{
		// Turn DB stuff into HTML
		$content = FFXIV_Render_Port();

		// Now replace search string with formatted information
		$newcontent = ffxiv_replace_string($matches[0], $content, $oldcontent);
	}
	return $newcontent;
}

add_filter('widget_text', 'FFXIV_Character_show_port');

// Retrieval section
// ------------------
function FFXIV_Get()
{
	$name = get_option('FFXIV_setting_name');
	$server = get_option('FFXIV_setting_server');
	$size = get_option('FFXIV_setting_asize');

	// Initialize a LodestoneAPI Object
	$API = new LodestoneAPI();

	// Parse the character
	$character = $API->get($name, $server);

	$ffxiv = array(
		"ID"		=> $character->getID(),
		"Cname"		=> $character->getName(),
		"Lodestone"	=> $character->getLodestone(),
		"Server" 	=> $character->getServer(),
		"Avatar"	=> $character->getAvatar($size),
		"Portrait"	=> $character->getPortrait(),
		"Race"		=> $character->getRace(),
		"City"		=> $character->getCity(),
		"NDay"		=> $character->getNameday(),
		"Guardian"	=> $character->getGuardian(),
		"Clan"		=> $character->getClan(),
		"GCName"	=> $character->getCompanyName(),
		"GCRank"	=> $character->getCompanyRank(),
		"Biography"	=> $character->getBiography(),
		"AClass"	=> $character->getActiveClass(),
		"ALevel"	=> $character->getActiveLevel(),
	);
}