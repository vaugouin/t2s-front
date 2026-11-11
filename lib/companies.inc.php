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
	f_headerdisplay(get_movie_string('lib_all_companies'));
	$strsqlcompany="SELECT * FROM ".$strsqlns."TMDB_COMPANY ";
	$strsqlcompany.="ORDER BY ID_COMPANY ";
	$strsqlcompany.="LIMIT 1000 ";
	$resultcompany=f_sqlselect($strsqlcompany);
	if ($resultcompany) {
		while ($rstempcompany=f_sqlresultfetcharray($resultcompany)) {
			$lngdisplayorder++;
			if ($lngdisplayorder==1) {
				$lnglistcount=f_sqlresultrowcount($resultcompany);
				echo "<h1>".get_movie_string('lib_all_companies').": ".$lnglistcount." ".get_movie_string('lib_company_item')."</h1>";
			}
			$lngcompanyid=f_stringtolong($rstempcompany['ID_COMPANY']);
			$strcompanyname=trim($rstempcompany['NAME']);
			$strcompanydesc=trim($rstempcompany['DESCRIPTION']);
			$strcompanyheadquarters=trim($rstempcompany['HEADQUARTERS']);
			$strcompanyorigcountry=trim($rstempcompany['ORIGIN_COUNTRY']);
			$lngcompanyparentid=f_stringtolong($rstempcompany['ID_PARENT']);
			$strcompanytimcreditscompleted=$rstempcompany['TIM_CREDITS_COMPLETED'];
			/*
			if ($struilang!="en") {
				//$strlistshortnamelang=trim(f_descfromcode($strsqlns."TMDB_LIST_LANG","id","SHORT_NAME",f_longtosql($lngcompanyid)));
				$strsql="SELECT SHORT_NAME FROM ".$strsqlns."TMDB_LIST_LANG WHERE DELETED = 0 AND id = ".f_longtosql($lngcompanyid)." AND LANG = ".f_stringtosql($struilang);
				$strlistshortnamelang=trim(f_fieldfromquery($strsql));
			}
			if ($strlistshortnamelang!="") {
				$strlistshortname=$strlistshortnamelang;
			}
			*/
			$strcompanyurl="company.php?companyid=".$lngcompanyid;
			$strcompanybadge="";
			//Hook index.row.badge: marks added by the plugins beside the name
			$strcompanybadge=f_pluginhtml("index.row.badge",array("type"=>"company","id"=>$lngcompanyid));
			
			echo "<h2><a href=\"".$strcompanyurl."\">".$lngdisplayorder.": ".$strcompanyname." ".$strcompanybadge."</a></h2>";
			if ($strcompanydesc!="") {
				echo "<p>".$strcompanydesc."</p>";
			}
			if ($strcompanyheadquarters!="" || $strcompanyorigcountry!="") {
				echo "<p>";
				if ($strcompanyheadquarters!="") {
					echo $strcompanyheadquarters." ";
				}
				if ($strcompanyorigcountry!="") {
					echo $strcompanyorigcountry." ";
				}
				echo "</p>";
			}
			/*
			if ($lngcompanyparentid>0) {
				echo "<p><a href=\"".$strcompanyparenturl."\">".$strcompanyparentname."</a></p>";
			}
			*/
		}
		f_sqlfreeresult($resultcompany);
	}
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
