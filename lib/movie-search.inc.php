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
	f_headerdisplay(get_movie_string('lib_movie_search'));
	echo "<h1><a href=\"movie-search.php\">".get_movie_string('lib_movie_search')."</a></h1>";
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
			//Search movie from the TMDb API
			//$strtmdbapisearchmovieurl="3/search/movie?query=".urlencode($strsearch)."&include_adult=true&language=".$strlanguagecountry."&page=".$lngpage;
			$strtmdbapisearchmovieurl="3/search/movie?query=".urlencode($strsearch)."&include_adult=true&language=".$struilang."&page=".$lngpage;
			if ($intdebug) {
				echo "<br />\$strtmdbapisearchmovieurl=$strtmdbapisearchmovieurl";
			}
			$strapisearchmovie=f_tmdbapirequest($strtmdbapisearchmovieurl);
			$jsonsearchmovie=json_decode($strapisearchmovie,true);
			if ($intdebug) {
				echo "<br />";
				print_r($jsonsearchmovie);
			}
			//Split search movie data from the TMDb API to local variables
			$lngmoviestatuscode=0;
			if (isset($jsonsearchmovie['status_code'])) {
				$lngmoviestatuscode=f_stringtolong($jsonsearchmovie['status_code']);
			}
			$lngtotalresults=f_stringtolong($jsonsearchmovie['total_results']);
			$lngpagecount=f_stringtolong($jsonsearchmovie['total_pages']);
			if ($lngtotalresults>0) {
				if ($lngpage==1) {
					//Result first page 
					echo "<h2>".get_movie_string('lib_search_result')." ".$lngtotalresults." ".get_movie_string('lib_movie_item')."</h2>";
					echo "<table>";
				}
				foreach ($jsonsearchmovie["results"] as $onekey => $onecontent) {
					$lngmovieid=$onecontent['id'];
					$strmovietitle=$onecontent['title'];
					$strmovieposterpath=trim($onecontent['poster_path']);
					$strmovieoverview=$onecontent['overview'];
					$strmoviedatrelease=trim($onecontent['release_date']);
					$strmoviereleaseyear=substr($strmoviedatrelease,0,4);
					$strmovietitleyear=$strmovietitle." (".$strmoviereleaseyear.")";
					$strmoviegenres="";
					$strmovieidimdb=f_descfromcode("T_WC_TMDB_MOVIE","ID_MOVIE","ID_IMDB",$lngmovieid);
					$strmovieoriginaltitle=f_descfromcode("T_WC_TMDB_MOVIE","ID_MOVIE","ORIGINAL_TITLE",$lngmovieid);
					f_imdbratingvotes($strmovieidimdb,$dblaveragerating,$lngimdbvotes);
					$lngdisplayorder++;
					f_movielistdisplay($lngmovieid,$strmovietitle,$strmovieoriginaltitle,"",$strmoviedatrelease,$strmovieidimdb,$dblaveragerating,$strmovieoverview,$strmovieposterpath,$strmoviegenres,3,0,"float",$lngimdbvotes);
				}
				$lngpage++;
				if ($lngpage>$lngpagecount) {
					//End of list
					$intencore=false;
					echo "</table>";
				}
			} else {
				//No result
				echo "<h2>".get_movie_string('lib_search_result')." ".get_movie_string('lib_movie_item_none')."</h2>";
				$intencore=false;
			}
		}
		if ($interror) {
			$strmessage=$jsonsearchmovie['status_message'];
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	} else {
		//search URL parameter not set
		/*
		$strmessage="movieid URL parameter not set";
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		*/
	}
	//Hook page.beforefooter: blocks of the plugins at the end of the page
	echo f_pluginhtml("page.beforefooter",array("page"=>"movie-search"));
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
