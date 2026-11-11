<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$strsearch=trim(f_request2("search"));
//echo "<br \>\$strsearch='$strsearch'";
$strsearch = str_replace("&\#039;","'",$strsearch);

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_serie_search'));
	echo "<h1><a href=\"serie-search.php\">".get_movie_string('lib_serie_search')."</a></h1>";
	echo "<form method=\"GET\" name=\"frmmain\">";
	echo "<strong>".get_movie_string('lib_search_prompt')."</strong>&nbsp;";
	echo "<input type=\"text\" name=\"search\" id=\"search\" value=\"".$strsearch."\" class=\"responsive-input\" placeholder=\"" . get_movie_string("lib_t2s_search_placeholder") . "\" maxlength=\"500\">";
	echo "<br />";
	echo "<input type=\"submit\" name=\"val\" value=\"".get_movie_string('lib_search_button')."\" >";
	echo "</form>";
	if ($strsearch!="") {
		//We begin with the result first page
		$lngpage=1;
		//We assume there is some content for this result to enter into the loop
		$intencore=true;
		//We assume there is no error
		$interror=false;
		$lngdisplayorder=0;
		while ($intencore) {
			//Search serie from the TMDb API
			//$strtmdbapisearchserieurl="3/search/serie?query=".urlencode($strsearch)."&include_adult=true&language=".$strlanguagecountry."&page=".$lngpage;
			$strtmdbapisearchserieurl="3/search/tv?query=".urlencode($strsearch)."&include_adult=true&language=".$struilang."&page=".$lngpage;
			if ($intdebug) {
				echo "<br />\$strtmdbapisearchserieurl=$strtmdbapisearchserieurl";
			}
			$strapisearchserie=f_tmdbapirequest($strtmdbapisearchserieurl);
			$jsonsearchserie=json_decode($strapisearchserie,true);
			if ($intdebug) {
				echo "<br />";
				print_r($jsonsearchserie);
			}
			//Split search serie data from the TMDb API to local variables
			$lngseriestatuscode=0;
			if (isset($jsonsearchserie['status_code'])) {
				$lngseriestatuscode=f_stringtolong($jsonsearchserie['status_code']);
			}
			$lngtotalresults=f_stringtolong($jsonsearchserie['total_results']);
			$lngpagecount=f_stringtolong($jsonsearchserie['total_pages']);
			if ($lngtotalresults>0) {
				if ($lngpage==1) {
					//Result first page 
					echo "<h2>".get_movie_string('lib_search_result')." ".$lngtotalresults." ".$strlibserieitem."</h2>";
					echo "<table>";
				}
				foreach ($jsonsearchserie["results"] as $onekey => $onecontent) {
					$lngserieid=$onecontent['id'];
					$strserietitle=$onecontent['name'];
					$strserieposterpath=trim($onecontent['poster_path']);
					$strserieoverview=$onecontent['overview'];
					$strseriedatfirstair=trim($onecontent['first_air_date']);
					$strseriedatlastair=trim($onecontent['last_air_date']);
					$strseriereleaseyear=substr($strseriedatfirstair,0,4);
					$strserietitleyear=$strserietitle." (".$strseriereleaseyear.")";
					$strseriegenres="";
					$strserieidimdb=f_descfromcode("T_WC_TMDB_SERIE","ID_SERIE","ID_IMDB",$lngserieid);
					$strserieoriginaltitle=f_descfromcode("T_WC_TMDB_SERIE","ID_SERIE","ORIGINAL_TITLE",$lngserieid);
					f_imdbratingvotes($strserieidimdb,$dblaveragerating,$lngimdbvotes);
					$lngdisplayorder++;
					f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,"",$strseriedatfirstair,$strseriedatlastair,$strserieidimdb,$dblaveragerating,$strserieoverview,$strserieposterpath,$strseriegenres,3,0,"float",$lngimdbvotes);
				}
				$lngpage++;
				if ($lngpage>$lngpagecount) {
					//End of list
					$intencore=false;
					echo "</table>";
				}
			} else {
				//No result
				echo "<h2>".get_movie_string('lib_search_result')." ".$strlibserieitemnone."</h2>";
				$intencore=false;
			}
		}
		if ($interror) {
			$strmessage=$jsonsearchserie['status_message'];
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	} else {
		//search URL parameter not set
		/*
		$strmessage="serieid URL parameter not set";
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		*/
	}
	//Hook page.beforefooter: blocks of the plugins at the end of the page
	echo f_pluginhtml("page.beforefooter",array("page"=>"serie-search"));
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
