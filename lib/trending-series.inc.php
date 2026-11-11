<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_trending_series'));
	echo "<h1>".get_movie_string('lib_trending_series')."</h1>";
	if (1) {
		//We begin with the result first page
		$lngpage=1;
		//We assume there is some content for this result to enter into the loop
		$intencore=true;
		//We assume there is no error
		$interror=false;
		$lngdisplayorder=0;
		//Track serie IDs already displayed so the TMDb API returning the same serie on several pages does not produce duplicates
		$arrseenserieids=array();
		while ($intencore) {
			//Search series from the TMDb API
			$strtmdbapisearchserieurl="3/trending/tv/day?language=".$struilang."&page=".$lngpage;
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
					echo "<table>";
				}
				foreach ($jsonsearchserie["results"] as $onekey => $onecontent) {
					$lngserieid=$onecontent['id'];
					//Skip a serie already displayed on an earlier page (TMDb trending can repeat entries across pages)
					if (isset($arrseenserieids[$lngserieid])) {
						continue;
					}
					$arrseenserieids[$lngserieid]=true;
					$strserietitle=$onecontent['name'];
					$strserieoriginaltitle=$onecontent['original_name'];
					$strserieposterpath=trim($onecontent['poster_path']);
					$strserieoverview=$onecontent['overview'];
					$strseriedatfirstair=trim($onecontent['first_air_date']);
					$strseriedatlastair=trim($onecontent['last_air_date']);
					$strseriegenres="";
					$strserieidimdb=f_descfromcode("T_WC_TMDB_SERIE","ID_SERIE","ID_IMDB",$lngserieid);
					f_imdbratingvotes($strserieidimdb,$dblaveragerating,$lngimdbvotes);

					$lngdisplayorder++;
					f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,"",$strseriedatfirstair,$strseriedatlastair,$strserieidimdb,$dblaveragerating,$strserieoverview,$strserieposterpath,$strseriegenres,3,0,"float",$lngimdbvotes);
				}
				$lngpage++;
				if ($lngpage>10) {
					//End of list
					$intencore=false;
					echo "</table>";
				}
			} else {
				//No result
				$intencore=false;
			}
		}
		if ($interror) {
			$strmessage=$jsonsearchserie['status_message'];
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	}
	//Hook page.beforefooter: blocks of the plugins at the end of the page
	echo f_pluginhtml("page.beforefooter",array("page"=>"trending-series"));
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
