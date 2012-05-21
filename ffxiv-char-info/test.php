<?php
include_once('apiv3.php');

// Call API
$API = new LodestoneAPI();

// Search for: Demonic Pagan on Durandal
$name = "Demonic Pagan";
$server = "Durandal";
$Result = $API->SearchCharacter($name, $server);

if ($Result[0])
{
	$API->v3GetProfileData();
	$API->v3GetHistory(0);
	$API->v3GetAvatars();
}

// Basic character data
echo $API->player_name;
echo $API->player_server;
echo $API->player_avatar;

// Race
echo $API->player_profile['Race'];
 
// Active Class
echo $API->player_profile['Active'];
 
// Nation
echo $API->player_profile['Nation'];
 
// Conjurer Level + EXP
echo $API->player_skills['Conjurer']['LEVEL'];
echo $API->player_skills['Conjurer']['EXP'];
 
// Grand Company Icon + Rank
echo $API->player_gcrank['Icon'];
echo $API->player_gcrank['Rank'];


// Spit out Array Info
echo "<pre>";
echo print_r($API);
echo "</pre>";

?>