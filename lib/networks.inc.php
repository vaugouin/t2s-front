<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	//Read lists data from the MySQL database
	//Display list data
	$lngdisplayorder=0;
	f_headerdisplay(get_movie_string('lib_all_networks'));
	$strsqlnetwork="SELECT * FROM ".$strsqlns."TMDB_NETWORK ";
	$strsqlnetwork.="ORDER BY ID_NETWORK ";
	$strsqlnetwork.="LIMIT 1000 ";
	$resultnetwork=f_sqlselect($strsqlnetwork);
	if ($resultnetwork) {
		while ($rstempnetwork=f_sqlresultfetcharray($resultnetwork)) {
			$lngdisplayorder++;
			if ($lngdisplayorder==1) {
				$lnglistcount=f_sqlresultrowcount($resultnetwork);
				echo "<h1>".get_movie_string('lib_all_networks').": ".$lnglistcount." ".get_movie_string('lib_network_item')."</h1>";
			}
			$lngnetworkid=f_stringtolong($rstempnetwork['ID_NETWORK']);
			$strnetworkname=trim($rstempnetwork['NAME']);
			$strnetworkorigcountry=trim($rstempnetwork['ORIGIN_COUNTRY']);
			$strnetworktimcreditscompleted=$rstempnetwork['TIM_CREDITS_COMPLETED'];
			/*
			if ($struilang!="en") {
				//$strlistshortnamelang=trim(f_descfromcode($strsqlns."TMDB_LIST_LANG","id","SHORT_NAME",f_longtosql($lngnetworkid)));
				$strsql="SELECT SHORT_NAME FROM ".$strsqlns."TMDB_LIST_LANG WHERE DELETED = 0 AND id = ".f_longtosql($lngnetworkid)." AND LANG = ".f_stringtosql($struilang);
				$strlistshortnamelang=trim(f_fieldfromquery($strsql));
			}
			if ($strlistshortnamelang!="") {
				$strlistshortname=$strlistshortnamelang;
			}
			*/
			$strnetworkurl="network.php?networkid=".$lngnetworkid;
			$strnetworkbadge="";
			//Hook index.row.badge: marks added by the plugins beside the name
			$strnetworkbadge=f_pluginhtml("index.row.badge",array("type"=>"network","id"=>$lngnetworkid));
			
			echo "<h2><a href=\"".$strnetworkurl."\">".$lngdisplayorder.": ".$strnetworkname." ".$strnetworkbadge."</a></h2>";
			if ($strnetworkheadquarters!="" || $strnetworkorigcountry!="") {
				echo "<p>";
				if ($strnetworkorigcountry!="") {
					echo $strnetworkorigcountry." ";
				}
				echo "</p>";
			}
			/*
			if ($lngnetworkparentid>0) {
				echo "<p><a href=\"".$strnetworkparenturl."\">".$strnetworkparentname."</a></p>";
			}
			*/
		}
		f_sqlfreeresult($resultnetwork);
	}
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
