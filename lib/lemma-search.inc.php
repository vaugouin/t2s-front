<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$strsearch=trim(f_request2("search"));
$strsearch = str_replace("&\#039;","'",$strsearch);

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_lemma_search'));
	echo "<h1><a href=\"lemma-search.php\">".get_movie_string('lib_lemma_search')."</a></h1>";
	echo "<form method=\"GET\" name=\"frmmain\">";
	echo "<strong>".get_movie_string('lib_search_prompt')."</strong>&nbsp;";
	echo "<input type=\"text\" name=\"search\" id=\"search\" value=\"".$strsearch."\" class=\"responsive-input\" placeholder=\"" . get_movie_string("lib_t2s_search_placeholder") . "\" maxlength=\"500\">";
	echo "<br />";
	echo "<input type=\"submit\" name=\"val\" value=\"".get_movie_string('lib_search_button')."\" >";
	echo "</form>";
	if ($strsearch!="") {
		$strlemmastable=$strsqlns."TMDB_SPACY_LEMME";
		$strsqllist="SELECT * FROM ".$strlemmastable." WHERE NAME LIKE ".f_stringtosql("%".$strsearch."%")." AND LANG = ".f_stringtosql($struilang)." ORDER BY NAME ";
		//echo "<br />".$strsqllist;
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			while ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$lnglemmaid=$rstemplist['ID_LEMME'];
				$strlemmaname=$rstemplist['NAME'];
				$strlemmalabel=$rstemplist['LABEL'];
				$strlemmaurl="lemma.php?lemmaid=".$lnglemmaid;
				echo "<br /><a href=\"".$strlemmaurl."\">".$strlemmaname."</a> : ".$strlemmalabel." (".f_spacylemmalibforlabel($strlemmalabel).")";
			}
			f_sqlfreeresult($resultlist);
		}
	} else {
		//search URL parameter not set
	}
	
	echo "<h1>".get_movie_string('lib_search_examples')."</h1>";
	echo f_searchlink("Enquêter");
	echo f_searchlink("Paris");
	echo f_searchlink("Maigret");
	echo f_searchlink("Jaloux");
	echo f_searchlink("Vengeance");
	
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
