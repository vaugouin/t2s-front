<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$strsearch=trim(f_request2("search"));
$strsearch = str_replace("&\#039;","'",$strsearch);

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_company_search'));
	echo "<h1><a href=\"company-search.php\">".get_movie_string('lib_company_search')."</a></h1>";
	echo "<form method=\"GET\" name=\"frmmain\">";
	echo "<strong>".get_movie_string('lib_search_prompt')."</strong>&nbsp;";
	echo "<input type=\"text\" name=\"search\" id=\"search\" value=\"".$strsearch."\" class=\"responsive-input\" placeholder=\"" . get_movie_string("lib_t2s_search_placeholder") . "\" maxlength=\"500\">";
	echo "<br />";
	echo "<input type=\"submit\" name=\"val\" value=\"".get_movie_string('lib_search_button')."\" >";
	echo "</form>";
	if ($strsearch!="") {
		$strcompanystable=$strsqlns."TMDB_COMPANY";
		$strsqllist="SELECT * FROM ".$strcompanystable." WHERE NAME LIKE ".f_stringtosql("%".$strsearch."%")." ORDER BY NAME ";
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			while ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$lngcompanyid=$rstemplist['ID_COMPANY'];
				$strcompanyname=trim($rstemplist['NAME']);
				$strcompanyurl="company.php?companyid=".$lngcompanyid;
				echo "<br /><a href=\"".$strcompanyurl."\">".$strcompanyname."</a>";
			}
			f_sqlfreeresult($resultlist);
		}
	} else {
		//search URL parameter not set
	}
	
	echo "<h1>".get_movie_string('lib_search_examples')."</h1>";
	echo f_searchlink("Disney");
	echo f_searchlink("Troma");
	
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
