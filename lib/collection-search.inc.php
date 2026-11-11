<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$strsearch=trim(f_request2("search"));
$strsearch = str_replace("&\#039;","'",$strsearch);

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_collection_search'));
	echo "<h1><a href=\"collection-search.php\">".get_movie_string('lib_collection_search')."</a></h1>";
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
			//Search collection from the TMDb API
			//$strtmdbapisearchcollectionurl="3/search/collection?query=".urlencode($strsearch)."&include_adult=true&language=".$strlanguagecountry."&page=1";
			$strtmdbapisearchcollectionurl="3/search/collection?query=".urlencode($strsearch)."&include_adult=true&language=".$struilang."&page=1";
			if ($intdebug) {
				echo "<br />\$strtmdbapisearchcollectionurl=$strtmdbapisearchcollectionurl";
			}
			$strapisearchcollection=f_tmdbapirequest($strtmdbapisearchcollectionurl);
			$jsonsearchcollection=json_decode($strapisearchcollection,true);
			if ($intdebug) {
				echo "<br />";
				print_r($jsonsearchcollection);
			}
			//Split search collection data from the TMDb API to local variables
			$lngcollectionstatuscode=0;
			if (isset($jsonsearchcollection['status_code'])) {
				$lngcollectionstatuscode=f_stringtolong($jsonsearchcollection['status_code']);
			}
			$lngtotalresults=f_stringtolong($jsonsearchcollection['total_results']);
			$lngpagecount=f_stringtolong($jsonsearchcollection['total_pages']);
			if ($lngtotalresults>0) {
				if ($lngpage==1) {
					//Result first page 
					echo "<h2>".get_movie_string('lib_search_result')." ".$lngtotalresults." ".get_movie_string('lib_collection_item')."</h2>";
					echo "<table>";
				}
				foreach ($jsonsearchcollection["results"] as $onekey => $onecontent) {
					$lngcollectionid=$onecontent['id'];
					$strcollectionname=$onecontent['name'];
					$strcollectionposterpath=trim($onecontent['poster_path']);
					$strcollectionoverview=$onecontent['overview'];
					$lngdisplayorder++;
					
					$strcollectionurl="collection.php?collectionid=".$lngcollectionid;
					
					//echo "<br />".$strcollectionposterpath;
					//echo "<br />".$strcollectionname;
					//echo "<br /><strong><a href=\"".$strcollectionurl."\">$strcollectionname</a></strong>";
					
					if ($strcollectionposterpath=="") {
						$strcollectionposterpath="";
					}
					
					echo "<tr>";
					echo "<td valign=\"top\"><a href=\"".$strcollectionurl."\"><img src=\"".$strtmdbcollectionimagespathbaseurl.$strcollectionposterpath."\" alt=\"".$strcollectionname."\" title=\"".$strcollectionname."\" width=\"65\" height=\"98\" style=\"background-color: white;\" /></a></td>";
					echo "<td valign=\"top\"><strong><a href=\"".$strcollectionurl."\">$strcollectionname</a></strong>";
					//echo "<br />$strcollectiondatrelease";
					echo "<br />$strcollectionoverview";
					echo "</tr>";
				}
				$lngpage++;
				if ($lngpage>$lngpagecount) {
					//End of list
					$intencore=false;
					echo "</table>";
				}
			} else {
				//No result
				echo "<h2>".get_movie_string('lib_search_result')." ".get_movie_string('lib_collection_item_none')."</h2>";
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
		$strmessage=get_movie_string('lib_collectionid_not_set');
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		*/
	}
	
	echo "<h1>".get_movie_string('lib_search_examples')."</h1>";
	echo f_searchlink("James Bond");
	echo f_searchlink("Le Seigneur des Anneaux");
	
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
