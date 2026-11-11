<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$strsearch=trim(f_request2("search"));
$strsearch = str_replace("&\#039;","'",$strsearch);

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_list_search'));
	echo "<h1><a href=\"list-search.php\">".get_movie_string('lib_list_search')."</a></h1>";
	echo "<form method=\"GET\" name=\"frmmain\">";
	echo "<strong>".get_movie_string('lib_search_prompt')."</strong>&nbsp;";
	echo "<input type=\"text\" name=\"search\" id=\"search\" value=\"".$strsearch."\" class=\"responsive-input\" placeholder=\"" . get_movie_string("lib_t2s_search_placeholder") . "\" maxlength=\"500\">";
	echo "<br />";
	echo "<input type=\"submit\" name=\"val\" value=\"".get_movie_string('lib_search_button')."\" >";
	echo "</form>";
	if ($strsearch!="") {
		$strsqllist="";
		$strsqllist.="SELECT T_WC_TMDB_LIST.ID_LIST, T_WC_TMDB_LIST.NAME, T_WC_TMDB_LIST.DESCRIPTION, T_WC_TMDB_LIST.SHORT_NAME, T_WC_TMDB_LIST.USE_FOR_TAGGING ";
		if ($struilang!="en") {
			$strsqllist.=", T_WC_TMDB_LIST_LANG.SHORT_NAME AS SHORT_NAME_LANG ";
		}
		$strsqllist.="FROM T_WC_TMDB_LIST ";
		if ($struilang!="en") {
			$strsqllist.="LEFT JOIN T_WC_TMDB_LIST_LANG ON T_WC_TMDB_LIST.ID_LIST = T_WC_TMDB_LIST_LANG.ID_LIST ";
		}
		$strsqllist.="WHERE (T_WC_TMDB_LIST.NAME LIKE ".f_stringtosql("%".$strsearch."%")." ";
		$strsqllist.="OR T_WC_TMDB_LIST.DESCRIPTION LIKE ".f_stringtosql("%".$strsearch."%")." ";
		$strsqllist.="OR T_WC_TMDB_LIST.SHORT_NAME LIKE ".f_stringtosql("%".$strsearch."%")." ";
		if ($struilang!="en") {
			$strsqllist.="OR T_WC_TMDB_LIST_LANG.SHORT_NAME LIKE ".f_stringtosql("%".$strsearch."%")." ";
		}
		$strsqllist.=") ";
		if ($struilang!="en") {
			$strsqllist.="AND (T_WC_TMDB_LIST_LANG.LANG = ".f_stringtosql($struilang)." OR T_WC_TMDB_LIST_LANG.LANG IS NULL) ";
		}
		$strsqllist.="ORDER BY T_WC_TMDB_LIST.NAME ";
		if ($intdebug) {
			echo "<br />".$strsqllist;
		}
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			while ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$lnglistid=$rstemplist['ID_LIST'];
				$strlistname=trim($rstemplist['NAME']);
				$strlistdescription=trim($rstemplist['DESCRIPTION']);
				$strlistshortname=trim($rstemplist['SHORT_NAME']);
				$strlistshortnamelang="";
				if ($struilang!="en") {
					$strlistshortnamelang=trim($rstemplist['SHORT_NAME_LANG']);
				}
				$intlistusefortagging=f_stringtolong($rstemplist['USE_FOR_TAGGING']);
				if ($intlistusefortagging) {
					$strlistusefortagging=" <span alt=\"Used for tagging\" title=\"Used for tagging\">👍🏻</span>";
				} else {
					$strlistusefortagging="";
				}
				if ($strlistshortnamelang!="") {
					$strlistshortname=$strlistshortnamelang;
				}
				$strlistbadge="";
				//Hook index.row.badge: marks added by the plugins beside the name
				$strlistbadge=f_pluginhtml("index.row.badge",array("type"=>"list","id"=>$lnglistid));
				$strlisturl="list.php?listid=".$lnglistid;
				echo "<br /><a href=\"".$strlisturl."\">".$strlistname.$strlistusefortagging.$strlistbadge."</a>";
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
	} else {
		//search URL parameter not set
	}
	
	echo "<h1>".get_movie_string('lib_search_examples')."</h1>";
	echo f_searchlink("Nouvelle vague");
	echo f_searchlink("Oscars");
	echo f_searchlink("Cannes");
	echo f_searchlink("César");
	echo f_searchlink("Pixar");
	
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
