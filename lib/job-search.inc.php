<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$strsearch=trim(f_request2("search"));
$strsearch = str_replace("&\#039;","'",$strsearch);

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_job_search'));
	echo "<h1><a href=\"job-search.php\">".get_movie_string('lib_job_search')."</a></h1>";
	echo "<form method=\"GET\" name=\"frmmain\">";
	echo "<strong>".get_movie_string('lib_search_prompt')."</strong>&nbsp;";
	echo "<input type=\"text\" name=\"search\" id=\"search\" value=\"".$strsearch."\" class=\"responsive-input\" placeholder=\"" . get_movie_string("lib_t2s_search_placeholder") . "\" maxlength=\"500\">";
	echo "<br />";
	echo "<input type=\"submit\" name=\"val\" value=\"".get_movie_string('lib_search_button')."\" >";
	echo "</form>";
	if ($strsearch!="") {
		$strjobstable=$strsqlns."TMDB_PERSON_MOVIE";
		$strsqllist="SELECT DISTINCT CREW_JOB FROM ".$strjobstable." WHERE CREW_JOB LIKE ".f_stringtosql("%".$strsearch."%")." ORDER BY CREW_JOB ";
		//echo "<br />".$strsqllist;
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			while ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				//$lngjobid=$rstemplist['id'];
				$strjobname=$rstemplist['CREW_JOB'];
				$strjoburl="job.php?job=".urlencode($strjobname);
				echo "<br /><a href=\"".$strjoburl."\">".$strjobname."</a>";
			}
			f_sqlfreeresult($resultlist);
		}
	} else {
		//search URL parameter not set
	}
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
