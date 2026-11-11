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
	f_headerdisplay(get_movie_string('lib_all_lists'));
	$strsqllist="SELECT * FROM ".$strsqlns."TMDB_LIST WHERE DELETED = 0 ";
	if (!$intprivate) {
		$strsqllist.=" AND USE_FOR_TAGGING >= 1 ";
	}
	$strsqllist.="ORDER BY NAME ";
	$resultlist=f_sqlselect($strsqllist);
	if ($resultlist) {
		while ($rstemplist=f_sqlresultfetcharray($resultlist)) {
			$lngdisplayorder++;
			if ($lngdisplayorder==1) {
				$lnglistcount=f_sqlresultrowcount($resultlist);
				echo "<h1>".get_movie_string('lib_all_lists').": ".$lnglistcount." ".get_movie_string('lib_list_item')."</h1>";
			}
			$lnglistid=f_stringtolong($rstemplist['ID_LIST']);
			$strlistdescription=trim($rstemplist['DESCRIPTION']);
			$strlistposterpath=$rstemplist['POSTER_PATH'];
			$strlistname=trim($rstemplist['NAME']);
			$strlistshortname=trim($rstemplist['SHORT_NAME']);
			$strlisttimcreditscompleted=$rstemplist['TIM_CREDITS_COMPLETED'];
			$intlistusefortagging=f_stringtolong($rstemplist['USE_FOR_TAGGING']);
			$strlistcreatedby=trim($rstemplist['CREATED_BY']);
			$strlistshortnamelang="";
			if ($struilang!="en") {
				//$strlistshortnamelang=trim(f_descfromcode($strsqlns."TMDB_LIST_LANG","ID_LIST","SHORT_NAME",f_longtosql($lnglistid)));
				$strsql="SELECT SHORT_NAME FROM ".$strsqlns."TMDB_LIST_LANG WHERE DELETED = 0 AND ID_LIST = ".f_longtosql($lnglistid)." AND LANG = ".f_stringtosql($struilang);
				$strlistshortnamelang=trim(f_fieldfromquery($strsql));
			}
			if ($strlistshortnamelang!="") {
				$strlistshortname=$strlistshortnamelang;
			}
			if ($strlistcreatedby!="") {
				$strlistcreatedbyurl="https://www.themoviedb.org/u/".$strlistcreatedby."/lists";
				$strlistcreatedbydisplay=" - <a href=\"".$strlistcreatedbyurl."\" target=\"_blank\" rel=\"noreferrer\">".$strlistcreatedby."</a>";
			} else {
				$strlistcreatedbydisplay="";
			}
			$strlisturl="list.php?listid=".$lnglistid;
			if ($intlistusefortagging >= 1) {
				$strlistusefortagging="";
				for ($inti=1;$inti<=$intlistusefortagging;$inti++) {
					$strlistusefortagging.=" <span alt=\"Used for tagging\" title=\"Used for tagging\">👍🏻</span>";
				}
			} else {
				$strlistusefortagging="";
			}
			$strlistbadge="";
			//Hook index.row.badge: marks added by the plugins beside the name
			$strlistbadge=f_pluginhtml("index.row.badge",array("type"=>"list","id"=>$lnglistid));
			
			echo "<h2><a href=\"".$strlisturl."\">".$lnglistid.": ".$strlistname.$strlistusefortagging.$strlistbadge."</a>".$strlistcreatedbydisplay."</h2>";
			if ($strlistdescription!="") {
				echo "<p>";
				if ($strlistshortname!="") {
					echo "<strong>".$strlistshortname."</strong>";
					if ($strlistdescription!="") {
						echo ": ";
					}
				}
				echo $strlistdescription;
				echo "</p>";
			}
		}
		f_sqlfreeresult($resultlist);
	}
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
