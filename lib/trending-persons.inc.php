<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_trending_persons'));
	echo "<h1>".get_movie_string('lib_trending_persons')."</h1>";
	if (1) {
		//We begin with the result first page
		$lngpage=1;
		//We assume there is some content for this result to enter into the loop
		$intencore=true;
		//We assume there is no error
		$interror=false;
		$lngdisplayorder=0;
		//Track person IDs already displayed so the TMDb API returning the same person on several pages does not produce duplicates
		$arrseenpersonids=array();
		while ($intencore) {
			//Search persons from the TMDb API
			$strtmdbapisearchpersonurl="3/trending/person/day?language=".$struilang."&page=".$lngpage;
			if ($intdebug) {
				echo "<br />\$strtmdbapisearchpersonurl=$strtmdbapisearchpersonurl";
			}
			$strapisearchperson=f_tmdbapirequest($strtmdbapisearchpersonurl);
			$jsonsearchperson=json_decode($strapisearchperson,true);
			if ($intdebug) {
				echo "<br />";
				print_r($jsonsearchperson);
			}
			//Split search person data from the TMDb API to local variables
			$lngpersonstatuscode=0;
			if (isset($jsonsearchperson['status_code'])) {
				$lngpersonstatuscode=f_stringtolong($jsonsearchperson['status_code']);
			}
			$lngtotalresults=f_stringtolong($jsonsearchperson['total_results']);
			$lngpagecount=f_stringtolong($jsonsearchperson['total_pages']);
			if ($lngtotalresults>0) {
				if ($lngpage==1) {
					//Result first page
					echo "<table>";
				}
				foreach ($jsonsearchperson["results"] as $onekey => $onecontent) {
					$lngpersonid=$onecontent['id'];
					//Skip a person already displayed on an earlier page (TMDb trending can repeat entries across pages)
					if (isset($arrseenpersonids[$lngpersonid])) {
						continue;
					}
					$arrseenpersonids[$lngpersonid]=true;
					$strpersonname=$onecontent['name'];
					$strpersonprofilepath=trim($onecontent['profile_path']);
					$strpersonidimdb=f_descfromcode("T_WC_TMDB_PERSON","ID_PERSON","ID_IMDB",$lngpersonid);
					$strpersonbiography=f_descfromcode("T_WC_TMDB_PERSON","ID_PERSON","BIOGRAPHY",$lngpersonid);
					$strpersonbirthday=f_descfromcode("T_WC_TMDB_PERSON","ID_PERSON","BIRTHDAY",$lngpersonid);
					$strpersondeathday=f_descfromcode("T_WC_TMDB_PERSON","ID_PERSON","DEATHDAY",$lngpersonid);

					$lngdisplayorder++;
					f_personlistdisplay($lngpersonid,$strpersonname,$strpersonbiography,$strpersonbirthday,$strpersondeathday,$strpersonidimdb,$strpersonprofilepath,3);
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
			$strmessage=$jsonsearchperson['status_message'];
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	}
	//Hook page.beforefooter: blocks of the plugins at the end of the page
	echo f_pluginhtml("page.beforefooter",array("page"=>"trending-persons"));
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
