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
	$formElem->description = "This will be grabbed from http://xivpads.com/?-Linkshells and doing a search and getting the resulting http://xivpads.com/?ls/<lsid>";
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
include_once('apiv3.php');

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
$cavater = $API->player_avatar;

?>