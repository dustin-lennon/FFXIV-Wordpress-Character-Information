<?
/*
-------------------------------------------------------------------------------------------------------------

		Lodestone API v3
		v3.2.7
		
		----------------
		
		Notes:	
			Multi-Character search result not supported and results in false found.
			Use " Show($API); " to display full object data.
			Speed times are measured in seconds.
			"Var = NULL" means the variable is optional and its default value has been preset to NULL.
		
		----------------

		Initializing Method:
			LodestoneAPI(CICUID = NULL, Name = NULL, Server = NULL)
				CICUID 	- 	The characters CICUID
				Name	- 	Name of the character
				Server	- 	Server the character is on
				* This presets the "$API->player_url" variable with the correct location
		
		Public Methods:
			SetDefault(CICUID)
			SetInfo(Name, Server)
			Test()
			
			SearchCharacter(Name, Server)
				- Returns TRUE/FALSE if found or not, along with any error message IF FALSE.
			
			SearchCharacterURL(Name, Server, URL)
				- Similar to "SearchCharacter" but requires a URL path.
				
			v3GetProfileData()
				- Sets the class up with data regarding the searched character 
				* REQUIRES player_cicuid to be defined
				  
			v3GetHistory($MaxPages = NULL)
				- Pulls the history, the Max Page is the page to start on, built mainly
				  for new features in XIVPads to reduce the amount of CURL requests
				- Everything will go into player_achievements
				* REQUIRES player_cicuid to be defined
				
			v3GetAvatars()
				- Pulls all the avatars from the characters top profile section
				* REQUIRES player_cicuid to be defined
				
			GetBiography()
				- Fetches biography data
				* REQUIRES player_cicuid to be defined
				
			GetLinkshellData(LSID)
				- Fetches linkshell datadata base on its passed LSID (GCID)
				  
			GetLinkshellMembers()
				- Fetches all members, includes Name + CICUID + Ranking Status (master/leader/member)
				* Requires LSID set via "GetLinkshellData"	
		
*/
#------------------------------------------------------------------------------------------------------------
# Defines for this class (English)
define("CHARACTER_SEARCH_FOUND",		 	"%character_name - %character_server found!");
define("CHARACTER_SEARCH_NOT_FOUND",	 	"%character_name - %character_server could not be found.");
define("CHARACTER_SEARCH_FOUND_MANY",	 	"%character_name - %character_server returned multiple results, please contact the site developer.");
define("CHARACTER_SEARCH_INVALID",	 	 	"%character_name is invalid, please include: First and Last name of your character.");
define("CHARACTER_DATA_FAIL", 			 	"Character Data could not be found, maybe Lodestone is on maintenance?");
define("CHARACTER_ACHIVEMENTS_NOT_FOUND",	"Character Data could not be found, maybe Lodestone is on maintenance?");
define("CHARACTER_NO_LONGER_EXISTS",		"Character no longer exists.");

#------------------------------------------------------------------------------------------------------------
# Class to get data from Lodestone
class LodestoneAPI
{	
	# Config
	private $language = "en";
	private $search_name;
	public $profile_data_pulled;
	
	# URLS
	private $url_lodestone 		= "http://lodestone.finalfantasyxiv.com/";							// Main Lodestone address
	private $url_character 		= "rc/character/status?cicuid=[CICUID]";							// Character profile data
	private $url_top 			= "rc/character/top?cicuid=[CICUID]";								// Top: Biography data
	private $url_history 		= "rc/character/playlog?num=100&p=[PAGE]&cicuid=[CICUID]";			// History/Achivements data
	private $url_lsmembers		= "rc/group/members?gcid=[GCID]";									// Linkshell Members data
	private $url_search 		= "rc/search/search?tgt=77&q=[NAME]&cms=&cw=[SERVER]";				// Search Address
	
	# Offsets
	private $offset_history_data = 96;
	private $offset_search_first_slot = 106;
	private $offset_profile_class = 13;
	private $offset_profile_race = 9;
	private $server_offset = 2;
	
	# Character Data
	public $player_cicuid;
	public $player_alive;
	public $player_url;
	public $player_name;
	public $player_server;
	public $player_avatar;
	public $player_biography;
	public $player_gcrank;
	public $player_historypages;
	public $player_historytotal;
	public $player_historytally;
	public $player_historyamount;
	
	# Character Arrays							#	- Elements and their order:
	public $player_profile 		= array();		//	Race+Gender, Active, Nameday, Guardian, City, Company Icon+Rank
	public $player_attributes 	= array();		//	Strength, Vitality, Dexterity, Intelligence, Mind, Piety
	public $player_elementals 	= array();		//	Fire, Water, Lightning, Wind, Earth, Ice
	public $player_skills 		= array();		// 	Holds skills level + experience
	public $player_learned 		= array();		// 	Holds Chocobo, Salute, Airship statuses.
	public $player_achievements = array();		//	Holds Achievement data
	public $player_avatars		= array();
	
	# Linkshell
	public $linkshell_name = "";
	public $linkshell_server = "";
	public $linkshell_gcid = "";
	public $linkshell_emblum = "";
	public $linkshell_members_count = 0;
	public $linkshell_members = array(); 		// Multidimention-ish
	
	# Core Initial Source Code
	public $SourceCode;
	
	# Servers
	public $servers = array('Ridill', 'Masamune', 'Durandal', 'Aegis', 'Gungnir', 'Sargatanas', 
					 'Balmung', 'Hyperion', 'Excalibur', 'Ragnarok');
							
	public $server_offsets = array('Durandal' => 2,
								   'Hyperion' => 3,
								   'Masamune' => 4,
								   'Gungnir' => 5,
								   'Aegis' => 7,
								   'Sargatanas' => 10,
								   'Balmung' => 11,
								   'Ridill' => 12,
								   'Excalibur' => 16,
								   'Ragnarok' => 20);
	
	#===========================================================================================================================#
	# Initializers																												#
	#===========================================================================================================================#
		
	// Start					 
	public function LodestoneAPI($CICUID = NULL, $Name = NULL, $Server = NULL)
	{
		if (!empty($CICUID) && is_numeric($CICUID))
		{
			$this->player_url = $this->url_lodestone . str_replace("[CICUID]", $CICUID, $this->url_character);
			$this->player_cicuid = $CICUID;	
			$this->player_name = $Name;
			$this->player_server = $Server;
		}
	}
	
	// Set CICUID
	public function SetDefault($CICUID)
	{
		$this->player_url = $this->url_lodestone . $cicuid;
		$this->player_cicuid = $cicuid;
	}
	
	// Set Name/Server
	public function SetInfo($Name, $Server)
	{
		$this->player_name = $Name;
		$this->player_server = $Server;
	}	
	
	// Test
	public function Test()
	{
		Show('Running under: '. $this->player_cicuid);	
	}
	
	#===========================================================================================================================#
	# v3 API Methods																											#
	#===========================================================================================================================#
	
	// Offsets, making it a bit easier to update. 
	private $Offsets = array("PROFILE" 				=> 49920,
							 "PROFILE_LENGTH"		=> 8000,
							 
							 // Character offsets, related to "DataArray", some require additional layers so they have been split
							 // into an multidmention array, each number represents 1 explode layer.
							 // Profile 1
							 "CHAR_AVATAR"			=> array(50,5),
							 "CHAR_RACE"			=> array(55,12),
							 "CHAR_ACTIVE"			=> array(59,2),
							 
							 // Profile 2
							 "CHAR_BIRTH"			=> array(2,5),
							 "CHAR_GUARDIAN"		=> array(2,11),
							 "CHAR_NATION"			=> array(3,5),
							 "CHAR_COMPANY_ICON"	=> array(6,1),
							 "CHAR_COMPANY_RANK"	=> array(7,3),
							 "CHAR_HPMPTP"			=> array(12,array("HP" => 6,
							 										  "MP" => 10,
																	  "TP" => 14)),
																	  
							 "CHAR_PHYSICAL"		=> array(18,array("STR" => 6,
							 										  "VIT" => 12,
																	  "DEX" => 18,
																	  "INT" => 24,
																	  "MND" => 30,
																	  "PTY" => 36)),
																	  
							 "CHAR_ELEMENTAL"		=> array("FIR" => array(26,3),
							 								 "WAT" => array(28,3),
															 "THU" => array(30,3),
															 "EAR" => array(32,3),
															 "WND" => array(34,3),
															 "ICE" => array(36,3)),
							 
							 "CHAR_SKILLS_DOW"		=> array(28,array("Gla" => array(7,10),
							 									  	  "Pug" => array(15,18),
																  	  "Mar" => array(25,28),
																  	  "Lan" => array(33,36),
																  	  "Arc" => array(43,46))),
																  
							 "CHAR_SKILLS_DOM"		=> array(32,array("Con" => array(7,10),
							 									      "Tha" => array(15,18))),
																  
							 "CHAR_SKILLS_DOH"		=> array(36,array("Woo" => array(7,10),
							 									  	  "Smi" => array(15,18),
																  	  "Arm" => array(25,28),
																  	  "Gol" => array(33,36),
																  	  "Lea" => array(43,46),
																  	  "Clo" => array(51,54),
																  	  "Alc" => array(61,64),
																  	  "Cul" => array(69,72))),
																  
							 "CHAR_SKILLS_DOL"		=> array(40,array("Min" => array(7,10), 
							 									  	  "Bot" => array(15,18),
																  	  "Fis" => array(25,28))),
																  
							 "HISTORY_DISPLAY_TOTAL" 	=> array(59,3),
							 "HISTORY_ACHIEVE_LINE"		=> array(65,90),
							 "HISTORY_ACHIEVE_DATA"		=> array(4,12,15),
							 "AVATAR_ALL_LOCATIONS"		=> array(1198,40,15)
							 );
							 
	// Terms used for History type detection
	// [1] Term to look for [2] Type [3] Replace
	private $Terms = array(	array('annals of history by besting', 				'NM',			' Defeated!'),
							array('within the Thousand Maws of Toto-Rak!', 		'Dungeon',		' Defeated!'),
							array('within Dzemael Darkhold!', 					'Dungeon',		' Defeated!'),
							array('Maelstrom quest', 							'Company', 		' Complete!'),
							array('Order of the Twin Adder quest', 				'Company',		' Complete!'),
							array('Immortal Flames quest', 						'Company',		' Complete!'),
							array('Ronan Kognan several deaspected crystals', 	'Misc',			' Completed!'),
							array('Exceptional Bravery', 						'Faction',		' Complete!'),
							array('Enemies Defeated', 							'Tally'),
							array('Levequests Completed', 						'Tally'),
							array('Reached', 									'Camps',		' Reached!'),
							array('completion of the quest', 					'Quest',		' Complete!'),
							array('put forth the necessary guild marks', 		'Guildskill',	' Acquired!'),
							array('Gil Earned from a Levequest!', 				'Tally'),
							array('Chocobo whistle obtained!', 					'Misc'),
							array('Learned a Grand Company salute!', 			'Misc'),
							array('Took passage on an airship!', 				'Misc'),
							array('Had a nightmare!',							'Misc'),
							array('is now a level',								'Levels',		' Achieved!'),
							array('Achievement',								'Achievement',	' Earned!')
						    );
							 
	// Method to search a character on the lodestone. Returns array
	public function SearchCharacter($Name, $Server)
	{
		$Name_Test = explode(" ", $Name);
		
			#	Show($Name_Test);
		
		if (strlen($Name_Test[0]) < 3 || strlen($Name_Test[1]) < 3)
			return array(false, strtr(CHARACTER_SEARCH_INVALID, array('%character_name' => $Name, '%character_server' => $Server)));
			
			#	Show(array($Name, $Server));
		
		// Set Name:
		$this->player_name = ucwords(strtolower($Name));
		$this->player_server = $this->server_offsets[$Server];
		
			#	Show(array($this->player_name, $this->player_server));
		
		// Generate Search Name String	
		$name_to_search = str_replace(" ", "+", $this->player_name);
		
		// Generate Search String
		$search_url = str_replace('[NAME]', "\"". $name_to_search ."\"", $this->url_search);
		$search_url = str_replace('[SERVER]', $this->player_server, $search_url);
		$search_url = $this->url_lodestone . $search_url;
		
			#	Show($search_url);
		
		// Get Source and put it into array by each line
		$source_array = explode("\n", $this->GetSource($search_url));
		
			#	Show($source_array);
			
		// Results
		if (count($source_array) < 20)
		{
			// Non Found
			return array(false, strtr(CHARACTER_SEARCH_NOT_FOUND, array('%character_name' => $Name, '%character_server' => $Server)));
		}
		else if (count($source_array) > 250)
		{
			// Multiple Found
			return array(false, strtr(CHARACTER_SEARCH_FOUND_MANY, array('%character_name' => $Name, '%character_server' => $Server)));	
		}
		else
		{
			// 1 Found, Get Source of the line with the CICUID
			$source = $source_array[$this->offset_search_first_slot];
			
			// Unset the rest its useless
			unset($source_array);
			
				#	Show($source);
			
			// Get first occurence of "SearchedCharacter" ($name) in source, then move back to the start of the CICUID, then keep 
			// that position + the next 7 characters, which will make up the cicuid.
			$position = strpos($source, $Name, true);
			$startpos = $position - 19;
			$found_cicuid = substr($source, $startpos, 12);
			$found_cicuid_stripped = preg_replace('/[^0-9]/i','',$found_cicuid); 
			
			unset($source);
			
				#	Show($found_cicuid);
			
			// return URL
			$this->player_cicuid = trim($found_cicuid_stripped);
			$this->player_url = $this->url_lodestone . str_ireplace("[CICUID]", $this->player_cicuid, $this->url_character);
			
			// Return
			return array(true, strtr(CHARACTER_SEARCH_FOUND, array('%character_name' => $Name, '%character_server' => $Server)));
		}
	}
	
	// Method to search a character on the lodestonevia url. Returns array
	public function SearchCharacterURL($Name, $url, $Server)
	{
		$Name_Test = explode(" ", $Name);
		
		if (strlen($Name_Test[0]) < 3 || strlen($Name_Test[1]) < 3)
			return array(false, strtr(CHARACTER_SEARCH_INVALID, array('%character_name' => $Name, '%character_server' => $Server)));
			
		$url_data = explode("=", $url);
		$this->player_cicuid = trim($url_data[1]);
		$this->player_server = $Server;
		$this->player_name = ucwords(strtolower($Name));
		$this->player_url = $this->url_lodestone . str_ireplace("[CICUID]", $this->player_cicuid, $this->url_character);
		
		if (!is_numeric($this->player_cicuid))
			return array(false, strtr(CHARACTER_SEARCH_INVALID, array('%character_name' => $Name, '%character_server' => $Server)));
			
		if (empty($this->player_name) || empty($this->player_cicuid))
			return array(false, strtr(CHARACTER_SEARCH_INVALID, array('%character_name' => $Name, '%character_server' => $Server)));
		
		return array(true, strtr(CHARACTER_SEARCH_FOUND, array('%character_name' => $Name, '%character_server' => $Server)));
	}
	
	// Get the character data
	public function v3GetProfileData()
	{				
		# Get the source and put each line into array
		$SourceCode = $this->GetSource($this->player_url);
		//Show($this->player_url);
		$SourceArray = explode("\n", $SourceCode);
		$SourceArray = array_slice($SourceArray, 8, 8);
		
		# Saing for Cache Test
		$SourceCache = implode("\n", $SourceArray);
		$this->SourceCode = html_entity_decode($SourceCache);
		
		# Check if the character still exists or not.
		$SourceSize = count($SourceArray);
		//Show($SourceArray);
		//Show($SourceSize);
		if ($SourceSize == 1)
		{
			$this->profile_data_pulled = false;
			return array(false, CHARACTER_NO_LONGER_EXISTS);	
		}
		else
		{
			//Show($SourceArray);
			//Show(strpos($SourceArray[0], 'class="menu-lv1"><a class="menu-btLv1" id="btMenuFansites" href="/pl/community/index.html" target=""></a></'));
			#===================================================================================================================#
			# 1		Profile part 1																								#
			#===================================================================================================================#
			$SourceCode 		= substr($SourceArray[0], $this->Offsets["PROFILE"], $this->Offsets["PROFILE_LENGTH"]);
			//Show($SourceCode);
			/*-------------------------------------------------------------------------------------------------------------------
				If Offset Lost:
				$Find 		= 'azone-contents';
				$Position 	= stripos($SourceCode, $Find);
			-------------------------------------------------------------------------------------------------------------------*/
			
			// Set Array to work with.
			// AVATAR, RACE, ACTIVE CLASS
			$DataArray 			= explode("div", $SourceCode);
			
			//Show($this->Offsets["CHAR_AVATAR"]);
			
			//Show($DataArray);
			// Avatar = 51
			
			$DataSize			= count($DataArray);
			$DataSizeOffset 	= $DataSize - 60;
				
			
			//Show($DataSize);	
			//Show($DataSizeOffset);
			//Show($DataArray);	
			$NameAndServer		= $DataArray[76];
			$NameAndServer		= explode('&quot;', $NameAndServer);
			if (count($NameAndServer) > 2)
			{
				$NameAndServer		= $NameAndServer[4];
				$NameAndServer		= trim(str_ireplace(array("&gt;", "/", "&lt;a", "&lt;", ")"), NULL, $NameAndServer));
				$NameAndServer		= explode("(", $NameAndServer);
			}
			else
			{
				$NameAndServer		= $DataArray[27];
				$NameAndServer		= explode('&quot;', $NameAndServer);
				$NameAndServer		= $NameAndServer[4];
				$NameAndServer		= trim(str_ireplace(array("&gt;", "/", "&lt;a", "&lt;", ")"), NULL, $NameAndServer));
				$NameAndServer		= explode("(", $NameAndServer);
			}
			
			// if server empty, name ad "div" in it
			if (empty($NameAndServer[1]))
			{
				$NameAndServer		= $DataArray[76] .'div'. $DataArray[77];
				$NameAndServer		= explode('&quot;', $NameAndServer);
				$NameAndServer		= $NameAndServer[4];
				$NameAndServer		= trim(str_ireplace(array("&gt;", "/", "&lt;a", "&lt;", ")"), NULL, $NameAndServer));
				$NameAndServer		= explode("(", $NameAndServer);
			}
			
			$this->player_name		= trim($NameAndServer[0]);
			$this->player_server	= trim($NameAndServer[1]);
			
			//Show($NameAndServer);
				
			// Get Avatar
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_AVATAR"][0] + $DataSizeOffset];
			$Search 			= stripos(trim($SourceCode), "static2");
			if ($Search)
			{
				$Array 				= explode("&quot;", $SourceCode);
				$__Avatar			= $Array[$this->Offsets["CHAR_AVATAR"][1]];
				$__Avatar			= str_ireplace("div", NULL, $__Avatar);
			}
			else
			{
				$SourceCode 		= $DataArray[135];
				$Array 				= explode("&quot;", $SourceCode);
				$P1				 	= $Array[5];
				$SourceCode 		= $DataArray[136];
				$Array 				= explode("&quot;", $SourceCode);
				$P2				 	= $Array[0];
				$__Avatar			= $P1 .'div'. $P2;
			}
			
			
			if (empty($__Avatar) || stripos($__Avatar, "static") === false)
			{
				//Show($DataArray);
				$SourceCode 		= $DataArray[145];
				$Array 				= explode("&quot;", $SourceCode);
				$P1				 	= $Array[5];
				$SourceCode 		= $DataArray[146];
				$Array 				= explode("&quot;", $SourceCode);
				$P2				 	= $Array[0];
				$__Avatar			= $P1 .'div'. $P2;
			}
			
			$__Avatar			= str_ireplace(array("&lt;", "&gt;"), NULL, $__Avatar);
			
			// Get Race
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_RACE"][0] + $DataSizeOffset];
			$Array 				= explode("&quot;", $SourceCode);
			
			//Show($DataArray);
			$__Race				= $Array[$this->Offsets["CHAR_RACE"][1]];
			$__Race				= $this->Clean($__Race);
			$RaceData			= explode("/", $__Race);
			$__Race 			= array("Type" 		=> $RaceData[0], 
										"Gender" 	=> str_ireplace(array("maletr", "femaletr", "FeMale"), array("Male", "Female", "Female"), $RaceData[1]));
			unset($RaceData);

			// Get Active Class
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_ACTIVE"][0] + $DataSizeOffset];
			$Array 				= explode("&quot;", $SourceCode);
			$__ActiveClass		= $Array[$this->Offsets["CHAR_ACTIVE"][1]];
			$__ActiveClass		= $this->Clean($__ActiveClass);
			
			#===================================================================================================================#
			# 2		Profile part 2																								#
			#===================================================================================================================#
			$SourceCode 		= $SourceArray[1] . $SourceArray[2] . $SourceArray[3] . $SourceArray[4] . $SourceArray[5] . $SourceArray[6];
			// Set Array to work with.
			// NAMESDAY, GUARDIAN, CITY, (if) COMPANY RANK 
			$DataArray 			= explode("div", $SourceCode);
			
			// Get Birth Date
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_BIRTH"][0]];
			$Array 				= explode("&gt;", $SourceCode);
			$__Birthdate		= $Array[$this->Offsets["CHAR_BIRTH"][1]];
			$__Birthdate		= $this->Clean($__Birthdate);
			
			// Get Guardian (Same line as Birthdate)
			$__Guardian			= $Array[$this->Offsets["CHAR_GUARDIAN"][1]];
			$__Guardian			= $this->Clean($__Guardian);
			
			// Get Nation
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_NATION"][0]];
			$Array 				= explode("&gt;", $SourceCode);
			$__Nation			= $Array[$this->Offsets["CHAR_NATION"][1]];
			$__Nation			= $this->Clean($__Nation);
			
			// We need to work out the size because some will have a company rank, others may not.
			// If there is a company we need to adjust the shift value.
			// Get Grand Company
			$DataSize			= count($DataArray);
			$Shift				= 0;
			if ($DataSize == 41)
			{
				// Increase Shift
				$Shift = 4;
				
				// Get Grand Company Icon
				$SourceCode 		= $DataArray[$this->Offsets["CHAR_COMPANY_ICON"][0]];
				$Array 				= explode("(", $SourceCode);
				$__GCIcon			= $Array[$this->Offsets["CHAR_COMPANY_ICON"][1]];
				$__GCIcon			= $this->Clean($__GCIcon);
				
				// Get Grand Company Rank
				$SourceCode 		= $DataArray[$this->Offsets["CHAR_COMPANY_RANK"][0]];
				$Array 				= explode("&gt;", $SourceCode);
				$__GCRank			= $Array[$this->Offsets["CHAR_COMPANY_RANK"][1]];
				$__GCRank			= $this->Clean($__GCRank);							
			}
			
			// Here it is shifted, we getting HP/MP/TP and Physical Attributes and Elemental(Fire)
			// HP/MP/TP
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_HPMPTP"][0] + $Shift];
			$Array 				= explode("&gt;", $SourceCode);
			$__PHealth			= $Array[$this->Offsets["CHAR_HPMPTP"][1]['HP']];
			$__PHealth			= $this->Clean($__PHealth);
			$__PMana			= $Array[$this->Offsets["CHAR_HPMPTP"][1]['MP']];
			$__PMana			= $this->Clean($__PMana);
			$__PTactical		= $Array[$this->Offsets["CHAR_HPMPTP"][1]['TP']];
			$__PTactical		= $this->Clean($__PTactical);
			
			// Attributes: Physical
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_PHYSICAL"][0] + $Shift];
			$Array 				= explode("&gt;", $SourceCode);
			$__AStr				= $Array[$this->Offsets["CHAR_PHYSICAL"][1]['STR']];
			$__AStr				= $this->Clean($__AStr);
			$__AVit				= $Array[$this->Offsets["CHAR_PHYSICAL"][1]['VIT']];
			$__AVit				= $this->Clean($__AVit);
			$__ADex				= $Array[$this->Offsets["CHAR_PHYSICAL"][1]['DEX']];
			$__ADex				= $this->Clean($__ADex);
			$__AInt				= $Array[$this->Offsets["CHAR_PHYSICAL"][1]['INT']];
			$__AInt				= $this->Clean($__AInt);
			$__AMnd				= $Array[$this->Offsets["CHAR_PHYSICAL"][1]['MND']];
			$__AMnd				= $this->Clean($__AMnd);
			$__APty				= $Array[$this->Offsets["CHAR_PHYSICAL"][1]['PTY']];
			$__APty				= $this->Clean($__APty);
			
			// Attributes: Elemental
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_ELEMENTAL"]["FIR"][0] + $Shift];
			$Array 				= explode("&gt;", $SourceCode);
			$__AFir				= $Array[$this->Offsets["CHAR_ELEMENTAL"]["FIR"][1]];
			$__AFir				= $this->Clean($__AFir);
			
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_ELEMENTAL"]["WAT"][0] + $Shift];
			$Array 				= explode("&gt;", $SourceCode);
			$__AWat				= $Array[$this->Offsets["CHAR_ELEMENTAL"]["WAT"][1]];
			$__AWat				= $this->Clean($__AWat);
			
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_ELEMENTAL"]["THU"][0] + $Shift];
			$Array 				= explode("&gt;", $SourceCode);
			$__AThu				= $Array[$this->Offsets["CHAR_ELEMENTAL"]["THU"][1]];
			$__AThu				= $this->Clean($__AThu);
			
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_ELEMENTAL"]["EAR"][0] + $Shift];
			$Array 				= explode("&gt;", $SourceCode);
			$__AEar				= $Array[$this->Offsets["CHAR_ELEMENTAL"]["EAR"][1]];
			$__AEar				= $this->Clean($__AEar);
			
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_ELEMENTAL"]["WND"][0] + $Shift];
			$Array 				= explode("&gt;", $SourceCode);
			$__AWnd				= $Array[$this->Offsets["CHAR_ELEMENTAL"]["WND"][1]];
			$__AWnd				= $this->Clean($__AWnd);
			
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_ELEMENTAL"]["ICE"][0] + $Shift];
			$Array 				= explode("&gt;", $SourceCode);
			$__AIce				= $Array[$this->Offsets["CHAR_ELEMENTAL"]["ICE"][1]];
			$__AIce				= $this->Clean($__AIce);
			
			#===================================================================================================================#
			# 3		Skills / Experience																							#
			#===================================================================================================================#
			$SourceCode 		= $SourceArray[7];
			// Set Array to work with.
			// Levels + Experience
			$DataArray 			= explode("div", $SourceCode);
			
			// Diciplines of WAR
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_SKILLS_DOW"][0]];
			$Array 				= explode("&gt;", $SourceCode);
			
				# Gladiator
				$__SGlaLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Gla"][0]];
				$__SGlaLevel		= $this->Clean($__SGlaLevel);
				$__SGlaExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Gla"][1]];
				$__SGlaExperience	= $this->Clean($__SGlaExperience);
			
				# Pugilist
				$__SPugLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Pug"][0]];
				$__SPugLevel		= $this->Clean($__SPugLevel);
				$__SPugExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Pug"][1]];
				$__SPugExperience	= $this->Clean($__SPugExperience);
				
				# Maraudar
				$__SMarLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Mar"][0]];
				$__SMarLevel		= $this->Clean($__SMarLevel);
				$__SMarExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Mar"][1]];
				$__SMarExperience	= $this->Clean($__SMarExperience);
				
				# Lancer
				$__SLanLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Lan"][0]];
				$__SLanLevel		= $this->Clean($__SLanLevel);
				$__SLanExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Lan"][1]];
				$__SLanExperience	= $this->Clean($__SLanExperience);
				
				# Archer
				$__SArcLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Arc"][0]];
				$__SArcLevel		= $this->Clean($__SArcLevel);
				$__SArcExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOW"][1]["Arc"][1]];
				$__SArcExperience	= $this->Clean($__SArcExperience);
			
			// Diciplines of MAGIC
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_SKILLS_DOM"][0]];
			$Array 				= explode("&gt;", $SourceCode);
			
				# Conjury
				$__SConLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOM"][1]["Con"][0]];
				$__SConLevel		= $this->Clean($__SConLevel);
				$__SConExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOM"][1]["Con"][1]];
				$__SConExperience	= $this->Clean($__SConExperience);
			
				# Thaumaturgy
				$__SThaLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOM"][1]["Tha"][0]];
				$__SThaLevel		= $this->Clean($__SThaLevel);
				$__SThaExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOM"][1]["Tha"][1]];
				$__SThaExperience	= $this->Clean($__SThaExperience);
			
			// Diciplines of HAND
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_SKILLS_DOH"][0]];
			$Array 				= explode("&gt;", $SourceCode);
			
				# Woodworking
				$__SWooLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Woo"][0]];
				$__SWooLevel		= $this->Clean($__SWooLevel);
				$__SWooExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Woo"][1]];
				$__SWooExperience	= $this->Clean($__SWooExperience);
			
				# Smithing
				$__SSmiLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Smi"][0]];
				$__SSmiLevel		= $this->Clean($__SSmiLevel);
				$__SSmiExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Smi"][1]];
				$__SSmiExperience	= $this->Clean($__SSmiExperience);
				
				# Armourer
				$__SArmLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Arm"][0]];
				$__SArmLevel		= $this->Clean($__SArmLevel);
				$__SArmExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Arm"][1]];
				$__SArmExperience	= $this->Clean($__SArmExperience);
				
				# Goldsmithing
				$__SGolLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Gol"][0]];
				$__SGolLevel		= $this->Clean($__SGolLevel);
				$__SGolExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Gol"][1]];
				$__SGolExperience	= $this->Clean($__SGolExperience);
				
				# Leatherworker
				$__SLeaLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Lea"][0]];
				$__SLeaLevel		= $this->Clean($__SLeaLevel);
				$__SLeaExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Lea"][1]];
				$__SLeaExperience	= $this->Clean($__SLeaExperience);
				
				# Clothcraft
				$__SCloLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Clo"][0]];
				$__SCloLevel		= $this->Clean($__SCloLevel);
				$__SCloExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Clo"][1]];
				$__SCloExperience	= $this->Clean($__SCloExperience);
				
				# Alchmist
				$__SAlcLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Alc"][0]];
				$__SAlcLevel		= $this->Clean($__SAlcLevel);
				$__SAlcExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Alc"][1]];
				$__SAlcExperience	= $this->Clean($__SAlcExperience);
				
				# Culinarian
				$__SCulLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Cul"][0]];
				$__SCulLevel		= $this->Clean($__SCulLevel);
				$__SCulExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOH"][1]["Cul"][1]];
				$__SCulExperience	= $this->Clean($__SCulExperience);
				
			// Diciplines of LAND
			$SourceCode 		= $DataArray[$this->Offsets["CHAR_SKILLS_DOL"][0]];
			$Array 				= explode("&gt;", $SourceCode);
			
				# Miner
				$__SMinLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOL"][1]["Min"][0]];
				$__SMinLevel		= $this->Clean($__SMinLevel);
				$__SMinExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOL"][1]["Min"][1]];
				$__SMinExperience	= $this->Clean($__SMinExperience);
			
				# Botany
				$__SBotLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOL"][1]["Bot"][0]];
				$__SBotLevel		= $this->Clean($__SBotLevel);
				$__SBotExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOL"][1]["Bot"][1]];
				$__SBotExperience	= $this->Clean($__SBotExperience);
				
				# Fisher
				$__SFisLevel		= $Array[$this->Offsets["CHAR_SKILLS_DOL"][1]["Fis"][0]];
				$__SFisLevel		= $this->Clean($__SFisLevel);
				$__SFisExperience	= $Array[$this->Offsets["CHAR_SKILLS_DOL"][1]["Fis"][1]];
				$__SFisExperience	= $this->Clean($__SFisExperience);	
			
			#===================================================================================================================#
			# -		Finished!																									#
			#===================================================================================================================#
			// Clean Memory
			unset($SourceCode);
			unset($SourceArray);
			unset($DataArray);
			
			// Setup multi-dimention arrays and then implode them
			$Profile = array('Race' 		=> $__Race['Type'] .'|'. $__Race['Gender'],
							 'Active' 		=> $__ActiveClass,
							 'Birthdate' 	=> $__Birthdate,
							 'Guardian' 	=> $__Guardian,
							 'Nation' 		=> $__Nation,
							 'HP' 			=> $__PHealth,
							 'MP' 			=> $__PMana,
							 'TP' 			=> $__PTactical);
			
			$Attributes =  array('STR' 		=> $__AStr,
								 'VIT' 		=> $__AVit,
								 'DEX' 		=> $__ADex,
								 'INT' 		=> $__AInt,
								 'MND' 		=> $__AMnd,
								 'PTY' 		=> $__APty);
								 
			$Elementals =  array('FIRE' 	=> $__AFir,
								 'WATER' 	=> $__AWat,
								 'LIGHTNING'=> $__AThu,
								 'WIND' 	=> $__AWnd,
								 'EARTH' 	=> $__AEar,
								 'ICE' 		=> $__AIce);
			
			// Skills written as named in game.
			$Skills 	=  array('Gladiator' 		=> array('LEVEL' => $__SGlaLevel, 'EXP' => $__SGlaExperience),
								 'Pugilist' 		=> array('LEVEL' => $__SPugLevel, 'EXP' => $__SPugExperience),
								 'Marauder'			=> array('LEVEL' => $__SMarLevel, 'EXP' => $__SMarExperience),
								 'Lancer' 			=> array('LEVEL' => $__SLanLevel, 'EXP' => $__SLanExperience),
								 'Archer' 			=> array('LEVEL' => $__SArcLevel, 'EXP' => $__SArcExperience),
								 
								 'Conjurer' 		=> array('LEVEL' => $__SConLevel, 'EXP' => $__SConExperience),
								 'Thaumaturge' 		=> array('LEVEL' => $__SThaLevel, 'EXP' => $__SThaExperience),
								 
								 'Carpenter'		=> array('LEVEL' => $__SWooLevel, 'EXP' => $__SWooExperience),
								 'Blacksmith' 		=> array('LEVEL' => $__SSmiLevel, 'EXP' => $__SSmiExperience),
								 'Armorer' 			=> array('LEVEL' => $__SArmLevel, 'EXP' => $__SArmExperience),
								 'Goldsmith' 		=> array('LEVEL' => $__SGolLevel, 'EXP' => $__SGolExperience),
								 'Tanner'			=> array('LEVEL' => $__SLeaLevel, 'EXP' => $__SLeaExperience),
								 'Weaver' 			=> array('LEVEL' => $__SCloLevel, 'EXP' => $__SCloExperience),
								 'Alchemist'		=> array('LEVEL' => $__SAlcLevel, 'EXP' => $__SAlcExperience),
								 'Culinarian'		=> array('LEVEL' => $__SCulLevel, 'EXP' => $__SCulExperience),
								 
								 'Miner' 			=> array('LEVEL' => $__SMinLevel, 'EXP' => $__SMinExperience),
								 'Botanist' 		=> array('LEVEL' => $__SBotLevel, 'EXP' => $__SBotExperience),
								 'Fisher' 			=> array('LEVEL' => $__SFisLevel, 'EXP' => $__SFisExperience));
			
			// Grand Companies!
			$GrandCompany = array('Icon' => $__GCIcon, 'Rank' => $__GCRank);
			
			// Fill object arrays
			$this->player_avatar		= $__Avatar;
			$this->player_gcrank		= $GrandCompany;
			$this->player_profile 		= $Profile;
			$this->player_attributes 	= $Attributes;
			$this->player_elementals 	= $Elementals;
			$this->player_skills 		= $Skills;
			$this->player_alive			= true;
			
			//Show($Skills);
			return array(true,NULL);
		}
	}
	
	// Get the history (achievements) data
	public function v3GetHistory($MaxPages = NULL)
	{
		/*
		----------------------------------------------------------------------------------------------------------
		One of the new advancements of this function is how it pulls data. Before in v2 it the function would pull
		every single page, every single time. A user with several pages ment an "Update" would take a long time.
		
		Now though it will store your last recorded pages and only update what it needs. 
		
		Examples:
		
			Detects:		 5 Pages
			Already Read:	 3 Pages
			
			All we need to do is read Page: 5, 4 and 3. We read 3 again because there may be new achievements on it
			that we had not read before. (If we last read it and it was 1-40 full, we need the other 60!)
			
		It always pulls the first page and works out the number of pages from there and generates a starting amount 
		of achievement data. Luckily achievement data is on 1 line.
		
		It then loops through pages, pulling new lines and appending it onto the static variable. After this has
		being processed it can then explode up that line and work through the achievements.
		
		>> Each unique achievement has the term " contents-headline"
		----------------------------------------------------------------------------------------------------------
		*/
		// Generate a URL
		$URL = $this->url_lodestone . str_replace(array('[PAGE]', '[CICUID]'), array(1, $this->player_cicuid), $this->url_history);
		//Show($URL);
		//Show($this->player_name);
		
		// Get Source
		$SourceCode 		= $this->GetSource($URL);
		$SourceArray 		= explode("\n", $SourceCode);	
		
		// We want to know how many achievements this user has, this way we can work out the number of pages.
		$SourceCode 		= $SourceArray[$this->Offsets['HISTORY_DISPLAY_TOTAL'][0]];
		$Array 				= explode(" ", $SourceCode);
		$__AchieveTotal 	= $Array[$this->Offsets['HISTORY_DISPLAY_TOTAL'][1]];
		$__AchieveTotal		= str_replace(array("div", ","), "", $this->Clean($__AchieveTotal));
		$this->player_historyamount = $__AchieveTotal;
		
		// Work out number of pages
		$__Pages 			= ($__AchieveTotal / 100);
		$__Pages			= explode(".", $__Pages);
		
		if(!empty($__Pages[1]))
		{
			$__Pages			= $__Pages[0] + 1;
		}else
		{
			$__Pages			= $__Pages[0];
		}
		
		//Show('__Pages : '. $__Pages);
		// Set new pulled pages value
		$this->player_historypages = $__Pages;
		
		// Now we work out how many new pages there are compared to our previous one.
		$NewPages			= $__Pages - $MaxPages;
		//Show('NewPages : '. $NewPages);
		
				
		// We always pull the first page, so get the data for it.
		// How this works is, the offset difference is largely dependant on the number of pages, so if its only 1 page
		// we pull 1 offset, if its 2+ we pull another at a base value which then shifts based on the number of pages.
		if ($__Pages == 1)
			$Ai = $this->Offsets['HISTORY_ACHIEVE_LINE'][0];
		else
		{	
			$SeveralPages 	= true;
			$Ai = $this->Offsets['HISTORY_ACHIEVE_LINE'][1] + $__Pages;
		}
		
		// Get Sourcecode of the achievements, we break them by " contents-headline" which is every unique achievement
		//Show($SourceArray);
		//Show($Ai);
		$SourceCode 		= $SourceArray[$Ai];
		//Show($SourceCode);
		
		$AILength = ($NewPages - 10);
		if ($AILength < 0)
			$AILength = 0;
			
		//Show($AILength);
		
		//Show('NewPages : '. $NewPages);
		if (stripos($SourceCode, "common-pager") !== false)
			$SourceCode 		= $SourceArray[($Ai-$AILength)];
			
		if (strlen($SourceCode) < 200)
			$SourceCode 		= $SourceArray[($Ai-$AILength)];
		
		if (stripos($SourceCode, "common-pager") !== false)
			$SourceCode 		= $SourceArray[($Ai-1)];
			
		//Show(($Ai-2));
		//Show($SourceCode);
		//Show($SourceArray);
		//Show($Ai);
		// Now we just pull information based on if there is new pages
		if ($SeveralPages)
		{
			// Start from Second Page
			$i = 1;
			
			// Loop until we hit all pages up to the last known value
			$PagesToPull	= $NewPages + 1;
			//Show('PagesToPull : '. $PagesToPull);
			
			// Increase AI to make room for new prev button
			$Ai = $Ai+2;
			
			echo '<script>UpdateStatusProgress(42, "Parsing Achievements (Pages To Parse: '. ($PagesToPull-1) .')...");</script>';flush();
			if ($PagesToPull == 0)
				$PercentagePerPage = round(50/1);
			else
				$PercentagePerPage = round(50/$PagesToPull);
				
			$Percentage = 42;
			
			while($i < $PagesToPull)
			{
				//Show('i '. $i);
				
				// Generate new URL
				$URL = $this->url_lodestone . str_replace(array('[PAGE]', '[CICUID]'), array($i+1, $this->player_cicuid), $this->url_history);	
				//Show('Fetching > '. $URL);
				
				// Pull new source code
				$NewSourceCode 		= $this->GetSource($URL);
				$NewSourceArray 	= explode("\n", $NewSourceCode);
				////Show($Ai);
				$Percentage = $Percentage + $PercentagePerPage;
				echo '<script>UpdateStatusProgress('. $Percentage .', "Parsing Achievements ('. $i .'/'. ($PagesToPull-1) .')...");</script>';flush();
				
				// Reduce AI if we are on last page.
				//Show(($i+1) .' = '. $__Pages);
				if (($i+1) == $__Pages)
				{
					$Break = true;
					$Ai = $Ai-2;
				}
					
				//Show($Ai);
				// Pull pages achievements
				$NewSourceCode 		= $NewSourceArray[$Ai];
				
				// Check
				if (stripos($NewSourceCode, "common-pager") !== false)
					$NewSourceCode 	= $NewSourceArray[($Ai-$AILength)];
				
				// Check length, we only want long strings
				//Show("LENGTH");
				//Show(($Ai-$AILength));
				//Show($NewSourceCode);
				if (strlen($NewSourceCode) < 200 && $PagesToPull < 10)
				{
					//echo 'Attempt 2';
					$NewSourceCode 	= $NewSourceArray[($Ai-$AILength - 1)];
				}

				if (empty($NewSourceCode))
					$NewSourceCode 	= $NewSourceArray[($Ai-$AILength)];
					
				//Show("NEW");
				//Show($NewSourceArray);
				//Show($Ai .' '. $AILength);
				//Show($NewSourceCode);
				
				if (empty($NewSourceCode))
				{
					//echo 'Breaking...';
					$Break = true;
					$NewSourceCode 	= $NewSourceArray[($Ai-$AILength)];
				}
				
				
					
				// Append on achievements to 1st pages achievements
				$SourceCode 		.= $NewSourceCode;

				// Increment
				$i++;
				
				if ($Break)
				{
					$PageBrokeOn = ($i+1);
					break;
				}
			}
		}
		
		$Array				= explode(" contents-headline", $SourceCode);
		//Show($Array);
		unset($Array[0]);
		//Show($Array);
		//Show($PageBrokeOn);
		$this->player_historypages = $PageBrokeOn;
		
		// Setup new blank multi-dimention array for achievements
		$Achievements 		= array();
		
		// Default Tally Values
		$EnemiesKilled 		= 0;
		$LocalLeves 		= 0;
		$RegionalLeves 		= 0;
		$GilFromLeve 		= 0;
		
		// Go through each line detecting data
		foreach($Array as $Achievement)
		{
			$ArrayData 		= explode("div&gt;", $Achievement);
			//Show($ArrayData);
			
			// Set Title
			$__Title 		= $ArrayData[$this->Offsets['HISTORY_ACHIEVE_DATA'][0]];
			$__Title 		= $this->Clean($__Title);
			$__Title 		= preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $__Title);
			$__Title 		= str_replace(",", "", $__Title);
			
			// Set Details
			$__Details 		= $ArrayData[$this->Offsets['HISTORY_ACHIEVE_DATA'][1]];
			$__Details 		= $this->Clean($__Details);
			$__Details 		= preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $__Details);
			$__Details 		= str_replace(",", "", $__Details);
			
			// Set Date
			$__Date 		= $ArrayData[$this->Offsets['HISTORY_ACHIEVE_DATA'][2]];
			$__Date 		= $this->Clean($__Date);
			
			// Work out Type
			$__Type = NULL;
			foreach($this->Terms as $Types)
			{
				if (stripos($__Title, $Types[0]) !== false || stripos($__Details, $Types[0]) !== false)
				{
					$__Title = str_replace($Types[2], '', $__Title);
					$__Type = $Types[1];
					break;
				}
			}
			
			if (empty($__Type))
				$__Type = 'Other';
			
			
			// Place in array
			$Achievements[] = array("TITLE" 	=> $__Title,
									"DETAILS" 	=> $__Details,
									"DATE" 		=> $__Date,
									"TYPE"		=> $__Type);
				
			// Work out if tally					
			if ($__Type == 'Tally')
			{
				// If Enemies
				if (stripos($__Title, 'Enemies'))
				{	
					$Amount = str_ireplace(' Enemies Defeated!', '', $__Title);
					if ($Amount > $EnemiesKilled)
						$EnemiesKilled = $Amount;
				}
				// If Regional Levequest
				if (stripos($__Title, 'Regional Levequests'))
				{	
					$Amount = str_ireplace(' Regional Levequests Completed!', '', $__Title);
					if ($Amount > $RegionalLeves)
						$RegionalLeves = $Amount;
				}
				// If Local
				if (stripos($__Title, 'Local Levequests'))
				{	
					$Amount = str_ireplace(' Local Levequests Completed!', '', $__Title);
					if ($Amount > $LocalLeves)
						$LocalLeves = $Amount;
				}
				// If Gil
				if (stripos($__Title, 'Gil Earned'))
				{	
					$Amount = str_ireplace(' Gil Earned from a Levequest!', '', $__Title);
					if ($Amount > $GilFromLeve)
						$GilFromLeve = $Amount;
				}

			}
		}
		
		# Finished!
		
		$this->player_historytotal = count($Achievements);
		$this->player_achievements = $Achievements;
		$this->player_historytally = array("ENEMIES" 	=> $EnemiesKilled,
										   "LOCAL" 		=> $LocalLeves,
										   "REGIONAL" 	=> $RegionalLeves,
										   "GILS" 		=> $GilFromLeve);
		//Show($this->player_historytally);							   
		//Show('Achievements Pulled: '. count($Achievements));
		//Show($Achievements);
		$this->player_historypages = $this->player_historypages - 1;
		
		return true;
	}
	
	// Get the Avatars History
	public function v3GetAvatars()
	{
		//Show($this->player_url);
		
		$url = $this->url_lodestone . str_replace("[CICUID]", $this->player_cicuid, $this->url_top);
		//show($url);
		
		// Arrange source
		$SourceCode 		= $this->GetSource($url);
		$SourceArray 		= explode("\n", $SourceCode);
		$SourceCode 		= $SourceArray[8];
		
		// Break by dive
		$DataArray 			= explode("div", $SourceCode);
		//Show($DataArray);
		$DataArray			= array_slice($DataArray, $this->Offsets[AVATAR_ALL_LOCATIONS][0], (count($DataArray)-1));

		// Go through all possibilities
		$Avatars = array();
		
		
		
		foreach($DataArray as $Possibility)
		{
			// No more avatar after biographi
			if (stripos($Possibility, "Biography") !== false)
				break;
				
			if (strlen($Possibility) > 80)
			{
				$String = explode("&quot;", $Possibility);
				$Avatar = $String[$this->Offsets[AVATAR_ALL_LOCATIONS][2]];
				if (strlen($Avatar) > 5)
					$Avatars[] = $Avatar;
			}
		}
		unset($Avatars[0]);
		$Avatars = array_reverse($Avatars);
		//Show($Avatars);
		$this->player_avatars = $Avatars;
		
	}
	
	#===========================================================================================================================#
	# v2 API Methods																											#
	#===========================================================================================================================#

	// Get Biography
	public function GetBiography()
	{		
		#--------------------------------------------------------------------------------------------------
        /*    This section concentrates on getting the pages.
         */
		// Set URL
		$url = $this->url_lodestone . str_replace("[CICUID]", $this->player_cicuid, $this->url_top);
		
		// Get Data
		$source_array = explode("\n", $this->GetSource($url));
		
		// We want the 8th element
		$source_block = $source_array[8];
		
		// Break this up by tag: 
		$data_array = explode("&lt;table", $source_block);
		
		// Section = 2
		$section = $data_array[2];
		
		// Explode to get start of biography
		$section_array = explode("table1TD1&quot;&gt;", $section);
		
		//Show($section_array);
		
		// Section = 1
		$section = $section_array[1];
		
		// Explode final time to get end of biography
		$section_array = explode("&lt;/td", $section);
		
		// Get Bio Section
		$bio = $section_array[0];
		
		// Remove any odd tags
		$bio = str_replace("&lt;wbr/&gt;", "", $bio);
		
		// Set Biography
		$this->player_biography = trim($bio);	
	}
	
	// Method to get Linkshell Data
	public function GetLinkshellData($LSID)
	{
		// Generate url
		$url = $this->url_lodestone . str_replace("[GCID]", $LSID, $this->url_lsmembers);
		
		//Show($url);

		// Get Digging
		$source_array = explode("\n", $this->GetSource($url));
		$source_block = $source_array[8];
		$source_array = explode("contents", $source_block);
		
		// Get Emblum
		$temp_block = $source_array[1];
		$temp_block_array = explode(")", $temp_block);
		$temp_block = $temp_block_array[0];
		$temp_block_array = explode("(", $temp_block);
		
		// Set Emblum
		$this->linkshell_emblum = $temp_block_array[1];

		$source_block = $source_array[1];
		$source_block = str_replace("&lt;wbr/&gt;", "", $source_block);
		$source_array = explode("&lt;", $source_block);
		$source_block = $source_array[12];
		$source_array = explode("&gt;", $source_block);
		
		// Set name and server (undigged)	
		$Linkshell_Name_And_Server = $source_array[1];
		//show($Linkshell_Name_And_Server);
		
		
		// Set temp
		$temp_url = $source_array[0];
		$temp_url = str_replace("a href=", "", $temp_url);
		$temp_url = str_replace("&quot;", "", $temp_url);
		$temp_url = str_replace("id=groupmenu-groupname", "", $temp_url);
		$temp_url = str_replace("/rc", "rc", $temp_url);
	
		// Set linkshell url
		$Linkshell_URL = $this->url_lodestone . $temp_url;
		
		//Show($Linkshell_URL);
		
		// Set temp
		$temp_name = explode("(", $Linkshell_Name_And_Server);
		
		// Set name + server
		$Linkshell_Name = str_replace("&amp;nbsp;", "", $temp_name[0]);
		$Linkshell_Server = substr($temp_name[1], 0, -1);
		
		// Set temp
		$temp_gcid = explode("=", $Linkshell_URL);
		$Linkshell_gcid = $temp_gcid[1];
		
		// Set class strings
		$this->linkshell_name = $Linkshell_Name;
		$this->linkshell_server = $Linkshell_Server;
		$this->linkshell_gcid = $Linkshell_gcid;
	}
	
	// Method to get Linkshell Members
	public function GetLinkshellMembers()
	{
		$url = str_replace("[GCID]", $this->linkshell_gcid, $this->url_lodestone . $this->url_lsmembers);
		//echo $url;
		$source_array = explode("\n", $this->GetSource($url));
		//Show($url);
		//Show($source_array);
		// Members start at offset 66
		$start = 62;
		$source_array = array_slice($source_array, $start, count($source_array));
		
		// Go through each member to find "common-pager", this is the ending point for members
		$i = 0;
		foreach ($source_array as $members_block)
		{
			$pos = strpos($members_block, "common-pager");
			if ($pos !== false)
			{
				// Found!
				break;	
			}
			$i++;
		}
		
		// Keep members up until found position -1
		$source_array = array_slice($source_array, 0, ($i-1));
		//$this->OutputArray($source_array);
		
		// Instantly from this we know the member size so get that
		$this->linkshell_members_count = count($source_array);
		
		// Now we need to start stripping each members data
		$i = 0; // Incase we need it
		foreach ($source_array as $members_block)
		{
			// Each member is a table, but table adjustments can shift thigns, so we're going to do original digging.
			$data = explode("&lt;td", $members_block);
			//$this->OutputArray($data) .'<br><br>';
			//Show($data);
			
			// Get involving blocks, we only really want their name and weather they are leader or not, as we will be getting rest from the pads.
			if ($i == 0)
			{
				$block_name = $data[2];
				$block_status = $data[3];
			}
			else
			{
				$block_name = $data[3];
				$block_status = $data[4];
			}
				
			// Get Name : offset 2
			$block_name = explode("&gt;", $block_name);
			$member_name = str_replace("&lt;/a", "", $block_name[3]);
			
			//Show($member_name);
			
			if (strpos($member_name, '--') !== false)
				continue;
			
			// Get CICUID : offset 2:1
			$member_cicuid_temp = str_replace("&lt;a href=&quot;/rc/character/top?cicuid=", "", $block_name[2]);
			$member_cicuid = str_replace("&quot; class=&quot;&quot; style=&quot;&quot;", "", $member_cicuid_temp);
			
			// Get Status : offset 2
			$block_status = explode("&gt;", $block_status);
			$block_status = str_replace("&lt;/div", "", $block_status[2]);
			
			//$this->OutputArray($block_name);
			
			// Push member details to members array
			array_push($this->linkshell_members, $member_cicuid ."|". $member_name ."|". $block_status);			
			$i++;
		}
	}
							 
	#===========================================================================================================================#
	# Core																														#
	#===========================================================================================================================#
	
	// Method to get source of the url
	private function GetSource($url)
	{
		//Show('CURL: '. $url);
		
		$options = array(
            CURLOPT_RETURNTRANSFER => true,         // return web page
            CURLOPT_HEADER         => false,        // return headers
            CURLOPT_FOLLOWLOCATION => true,         // follow redirects
            CURLOPT_ENCODING       => "",     		// handle all encodings
            CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows; U; Windows NT 6.1; fr; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12",     // who am i
            CURLOPT_AUTOREFERER    => true,         // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 60,           // timeout on connects
            CURLOPT_TIMEOUT        => 60,           // timeout on response
            CURLOPT_MAXREDIRS      => 5,            // stop after 10 redirects
            CURLOPT_HTTPHEADER     => array('Content-type: text/html; charset=utf-8', 'Accept-Language: '. $this->language)
		);
		
		$ch = curl_init($url . '&r='. mt_rand(0,99999));	
		curl_setopt_array($ch, $options);	
		$source = curl_exec($ch);
		curl_close($ch);
		return htmlentities($source);
	}
	
	public function TestParse()
	{
		$Source = $this->GetSource('http://lodestone.finalfantasyxiv.com/rc/character/status?cicuid=1633645');
		echo html_entity_decode($Source);
	}
	
	// Cleans up strings by removing html/symbols/etc
	private function Clean($String)
	{
		// Order long > Short to avoid breaks, keep HTML together so that it removes accordingly. HTML > WORDING
		$Replacing = array("&lt;wbr/&gt;",
						   "&lt;/b",
						   "&lt;/",
						   "&amp;nbsp;",
						   "&lt;td&gt;",
						   "&lt;/td&gt;",
						   "&lt;tr&gt;",
						   "&lt;/tr&gt;",
						   "&lt;div&gt;",
						   "&lt;/div&gt;",
						   "&lt;",
						   "&gt;",
						   ")&quot;/",
						   "&amp;#39;",
						   
						   "/td", "td",
						   ")&quot;",
						   "'",
						   "	",
						   "&acirc;", "€œ", "&aelig;",
						   "table class=&quot;contents-table1&quot; cellpadding=&quot;0&quot; cellspacing=&quot;1&quot; style=&quot;margin-bottom:0&quot; class=&quot;contents-table11&quot;",
						   "trtable");
						   
						   
		return str_ireplace($Replacing, "", $String);
	}

}
/*
#------------------------------------------------------------------------------------------------------------
// Prints out variables with pre format and using print_r(), identical to php.net example outputs.
function Show($Variable)
{
	echo '<pre>';
	print_r($Variable);
	echo '</pre>';
}

// Method to calculate script execution time. 
function Timer() 
{ 
	list ($msec, $sec) = explode(' ', microtime()); 
	$microtime = (float)$msec + (float)$sec; 
	return $microtime; 
}
#------------------------------------------------------------------------------------------------------------
# Buffer
ob_start();

$MICRO_START = Timer();

# Testing
$TestData 	=	array(
					array("CICUID" 	=> 3049897,
						  "Name" 	=> "Katrine Youko",			//	0
						  "Server" 	=> "Sargatanas"),
						  
					array("CICUID" 	=> 8309896,
						  "Name" 	=> "Rin Zhu",				//	1
						  "Server" 	=> "Besaid"),
						  
					array("CICUID" 	=> 4565845,
						  "Name" 	=> "Eva Eva",				//	2
						  "Server" 	=> "Istory"),
						  
					array("CICUID" 	=> 7571353,
						  "Name" 	=> "Viion Virtue",			//	3
						  "Server" 	=> "Besaid"),
	  
					array("CICUID" 	=> 1234567,
						  "Name" 	=> "",						//	4 (dead)
						  "Server" 	=> ""),
					 );
					 
$TestNum 	= $_GET['num'];
if (empty($TestNum))
	$TestNum = 0;
					 
#Initializer
$API = new LodestoneAPI($TestData[$TestNum]['CICUID'], 
						$TestData[$TestNum]['Name'], 
						$TestData[$TestNum]['Server']);

# Get Data
//$Result = $API->v3GetProfileData();
//$API->SearchCharacterURL('Katrine Youko', 'http://lodestone.finalfantasyxiv.com/rc/character/top?cicuid=13584304', 'Sargatanas'); 
$Result = $API->v3GetProfileData();
//Show($Result);
//if (!$Result[0])
//	Show($Result[1]);
//$API->v3GetHistory(0);
//$API->v3GetAvatars();
//Show($API->player_historytally);
//Show("Achievements on Character: ". $API->player_historyamount);
//Show("Pages Parsed: " . $API->player_historypages);
//Show("Achievements Parsed: " . $API->player_historytotal);
//echo '<hr />';
//Show($API->player_achievements);
//Show(count($API->player_avatars));

//$API->GetLinkshellData(1989304);
//$API->GetLinkshellMembers();
//Show($API->linkshell_members);
echo '<hr />';
Show($API);

#------------------------------------------------------------------------------------------------------------
# End
ob_end_flush();

# Time
$MICRO_STOP = Timer(); 
$MICRO_DIFFERENCE = round($MICRO_STOP - $MICRO_START, 9);

echo '<hr>';
Show('Speed: '. round($MICRO_DIFFERENCE, 2) .' seconds.');
*/
?>