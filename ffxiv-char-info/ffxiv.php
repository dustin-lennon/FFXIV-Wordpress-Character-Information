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

define('WP_STR_SHOW_FFXIV_NFO_PORT', '/<!-- FFXIV_port(\((([0-9],|[0-9]|,)*?)\))? -->/i');
define('WP_STR_SHOW_FFXIV_NFO_LAND', '/<!-- FFXIV_land(\((([0-9],|[0-9]|,)*?)\))? -->/i');

require('wplib/utils_formbuilder.inc.php');
require('wplib/utils_sql.inc.php');
require('wplib/utils_tablebuilder.inc.php');

include_once('apiv3.php');

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
	$setting_char_linkshell = get_option('FFXIV_setting_linkshell');

	// Check if updated data.
	if(isset($_POST) && isset($_POST['update']))
	{
		$setting_char_name = trim($_POST['FFXIV_setting_name']);
		$setting_char_server = trim($_POST['FFXIV_setting_server']);
		$setting_char_linkshell = trim($_POST['FFXIV_setting_linkshell']);

		update_option('FFXIV_setting_name', $setting_char_name);
		update_option('FFXIV_setting_server', $setting_char_server);
		update_option('FFXIV_setting_linkshell', $setting_char_linkshell);
	}

	// Build the form
	$form = new FormBuilder();

	$formElem = new FormElement('FFXIV_setting_name', 'FFXIV Character Name');
	$formElem->value = $setting_char_name;
	$formElem->description = "Some Name";
	$form->addFormElement($formElem);

	$formElem = new FormElement('FFXIV_setting_server', 'FFXIV Character Server');
	$formElem->value = $setting_char_server;
	$formElem->description = "Durandal";
	$form->addFormElement($formElem);

	$formElem = new FormElement('FFXIV_setting_linkshell', 'FFXIV Character Linkshell ID');
	$formElem->value = $setting_char_linkshell;
	$formElem->description = "This will be grabbed from <a href='http://xivpads.com/?-Linkshells' target='_blank'>http://xivpads.com/?-Linkshells</a> and doing a search and getting the resulting http://xivpads.com/?ls/<lsid>";
	$form->addFormElement($formElem);

	echo $form->toString();
}

function FFXIV_install()
{
	global $wpdb;

	// Create Default Settings
	if (!get_option('FFXIV_setting_name'))
		update_option('FFXIV_setting_name', 'Some name');

	if (!get_option('FFXIV_setting_server'))
		update_option('FFXIV_setting_server', 'Durandal');

	if (!get_option('FFXIV_setting_linkshell'))
		update_option('FFXIV_setting_linkshell', 'Linkshell ID from XIVPads.com');

	$wpdb->show_errors();
}

// Retrieval section
// ------------------
function FFXIVPads_Get()
{
	global $cname,$cserver,$cavatar,$lsname,$lsemb;

	$name = get_option('FFXIV_setting_name');
	$server = get_option('FFXIV_setting_server');
	$lsid = get_option('FFXIV_setting_linkshell');

	// Call the XIVPads API
	$API = new LodestoneAPI();

	// Pull character information
	$Result = $API->SearchCharacter($name, $server);

	// Set data
	if ($Result[0])
	{
		$API->v3GetProfile();
		$API->v3GetHistory(0);
		$API->v3GetAvatars();
		$API->GetLinkshellData($lsid);
	}

	$cname = $API->player_name;
	$cserver = $API->player_server;
	$cavatar = $API->player_avatar;
	$lsname = $API->linkshell_name;
	$lsemb = $API->linkshell_emblem;
}

// Display character information
// ------------------------------
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

add_filter('the_content', 'FFXIV_Character_show_land');

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

function FFXIV_Render_Port()
{
	FFXIVPads_Get();
	$content = "<h2><small>TEST TEXT</small></h2>";
	//$content = "<h2><small>".$cname."</small></h2>";
	return $content;
}

function FFXIV_Render_Land()
{

}


?>