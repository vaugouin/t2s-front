<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$strsearch=trim(f_request2("search"));
$strsearch = str_replace("&\#039;","'",$strsearch);
$strfilter=trim(f_request2("filter"));
$strfilter = str_replace("&\#039;","'",$strfilter);

if ($intdbavailable) {
	//Database is available
	f_headerdisplay(get_movie_string('lib_person_search'));
	echo "<h1><a href=\"person-search.php\">".get_movie_string('lib_person_search')."</a></h1>";
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
			//Search person from the TMDb API
			//$strtmdbapisearchpersonurl="3/search/person?query=".urlencode($strsearch)."&include_adult=true&language=".$strlanguagecountry."&page=".$lngpage;
			$strtmdbapisearchpersonurl="3/search/person?query=".urlencode($strsearch)."&include_adult=true&language=".$struilang."&page=".$lngpage;
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
					echo "<h2>".get_movie_string('lib_search_result')." ".$lngtotalresults." ".get_movie_string('lib_person_item')."</h2>";
					echo "<table>";
				}
				foreach ($jsonsearchperson["results"] as $onekey => $onecontent) {
					$lngpersonid=$onecontent['id'];
					$strpersonname=$onecontent['name'];
					$strpersonprofilepath=trim($onecontent['profile_path']);
					$strpersonknownfordepartment=$onecontent['known_for_department'];
					$lngdisplayorder++;
					
					$strpersonurl="person.php?personid=".$lngpersonid;
					
					$strpersonknowformovies="";
					foreach ($onecontent['known_for'] as $onesubkey => $onesubcontent) {
						$lngmovieid=$onesubcontent['id'];
						$strmovietitle=$onesubcontent['title'];
						$strmoviedatrelease=trim($onesubcontent['release_date']);
						$strmoviereleaseyear=substr($strmoviedatrelease,0,4);
						$strmovietitleyear=$strmovietitle." (".$strmoviereleaseyear.")";
						$strmovieurl="movie.php?movieid=".$lngmovieid;
						if ($strmovietitle!="") {
							if ($strpersonknowformovies!="") {
								$strpersonknowformovies.=", ";
							}
							$strpersonknowformovies.="<a href=\"".$strmovieurl."\">$strmovietitleyear</a>";
						}
					}
					
					//echo "<br />".$strpersonprofilepath;
					//echo "<br />".$strpersonname;
					//echo "<br /><strong><a href=\"".$strpersonurl."\">$strpersonname</a></strong>";
					
					if ($strpersonprofilepath=="") {
						$strpersonprofilepath="";
					}
					
					echo "<tr>";
					echo "<td valign=\"top\"><a href=\"".$strpersonurl."\"><img src=\"".f_getpersonprofilepicture($strpersonprofilepath,1)."\" alt=\"".$strpersonname."\" title=\"".$strpersonname."\" width=\"64\" height=\"64\" style=\"background-color: white;\" /></a></td>";
					echo "<td valign=\"top\"><strong><a href=\"".$strpersonurl."\">".$strpersonname."</a></strong>";
					//Hook index.row.badge: marks added by the plugins beside the name
					echo f_pluginhtml("index.row.badge",array("type"=>"person","id"=>$lngpersonid,"index"=>"search"));
					echo "<br />$strpersonknownfordepartment - $strpersonknowformovies</td>";
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
				echo "<h2>".get_movie_string('lib_search_result')." ".get_movie_string('lib_person_item_none')."</h2>";
				$intencore=false;
			}
		}
		if ($interror) {
			$strmessage=$jsonsearchperson['status_message'];
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	} else {
		//search URL parameter not set
		/*
		$strmessage="personid URL parameter not set";
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		*/
	}
	
	echo "<h1>".get_movie_string('lib_search_examples')."</h1>";
	echo f_searchlink("Justine Triet");
	echo f_searchlink("Pierre Niney");
	echo f_searchlink("Orson Welles");
	
	//Now display all VIP persons from the TMDB_PERSON_SEARCH
	echo "<h1>".get_movie_string('lib_filter')."</h1>";
	$lngdisplayorder=0;
	$strsqllist="";
	$strsqllist.="SELECT DISTINCT KNOWN_FOR_DEPARTMENT FROM T_WC_TMDB_PERSON WHERE KNOWN_FOR_DEPARTMENT IS NOT NULL ORDER BY KNOWN_FOR_DEPARTMENT ";
	$resultlist=f_sqlselect($strsqllist);
	if ($resultlist) {
		while ($rstemplist=f_sqlresultfetcharray($resultlist)) {
			$strpersonknownfordepartment=trim($rstemplist['KNOWN_FOR_DEPARTMENT']);
			if ($lngdisplayorder>0) {
				echo "<br />";
			}
			$strfilterurl="person-search.php?filter=".urlencode($strpersonknownfordepartment);
			if ($strfilterurl!="") {
				echo "<a href=\"".$strfilterurl."\">";
			}
			if ($strpersonknownfordepartment=="") {
				echo "&lt;None>";
			} else {
				echo $strpersonknownfordepartment;
			}
			if ($strfilterurl!="") {
				echo "</a>";
			}
			$lngdisplayorder++;
		}
		f_sqlfreeresult($resultlist);
	}
	
	echo "<h1>".get_movie_string('lib_vip')."</h1>";
	$lngdisplayorder=0;
	$strtable=$strsqlns."TMDB_PERSON_SEARCH";
	$strsqllist="";
	$strsqllist.="SELECT ".$strsqlns."TMDB_PERSON_SEARCH.ID_ROW, ".$strsqlns."TMDB_PERSON_SEARCH.ID_PERSON, ".$strsqlns."TMDB_PERSON_SEARCH.NAME, ";
	$strsqllist.="".$strsqlns."TMDB_PERSON.ID_WIKIDATA, ".$strsqlns."TMDB_PERSON.KNOWN_FOR_DEPARTMENT ";
	$strsqllist.="FROM ".$strtable." ";
	$strsqllist.="LEFT JOIN ".$strsqlns."TMDB_PERSON ON ".$strsqlns."TMDB_PERSON_SEARCH.ID_PERSON = ".$strsqlns."TMDB_PERSON.ID_PERSON ";
	if ($strfilter!="") {
		$strsqllist.="WHERE ".$strsqlns."TMDB_PERSON.KNOWN_FOR_DEPARTMENT = ".f_stringtosql($strfilter)." ";
	}
	$strsqllist.="ORDER BY ID_ROW ";
	//echo "<br />".$strsqllist;
	$resultlist=f_sqlselect($strsqllist);
	if ($resultlist) {
		while ($rstemplist=f_sqlresultfetcharray($resultlist)) {
			$lngrowid=f_stringtolong($rstemplist['ID_ROW']);
			$lngpersonid=f_stringtolong($rstemplist['ID_PERSON']);
			$strpersonname=trim($rstemplist['NAME']);
			$strpersonidwikidata=$rstemplist["ID_WIKIDATA"];
			$strpersonknownfordepartment=$rstemplist['KNOWN_FOR_DEPARTMENT'];
			if ($strpersonidwikidata!="") {
				$strpersonwikidataurl="https://www.wikidata.org/wiki/".$strpersonidwikidata;
			}
			if ($lngpersonid==0) {
				//Id not already known for this person
				$lngpage=1;
				$strtmdbapisearchpersonurl="3/search/person?query=".urlencode($strpersonname)."&include_adult=true&language=".$struilang."&page=".$lngpage;
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
					foreach ($jsonsearchperson["results"] as $onekey => $onecontent) {
						$lngpersonid=f_stringtolong($onecontent['id']);
						$strsqlupdate="UPDATE ".$strtable." SET ID_PERSON = ".f_longtosql($lngpersonid)." ";
						$strsqlupdatecond="WHERE ID_ROW = ".f_longtosql($lngrowid)." ";
						f_sqlupdate($strsqlupdate,$strsqlupdatecond,true);
						break;
					}
				}
			}
			
			if ($lngpersonid>0) {
				$strpersonurl="person.php?personid=".$lngpersonid;
			} else {
				$strpersonurl="";
			}
			if ($lngdisplayorder>0) {
				echo "<br />";
			}
			if ($strpersonurl!="") {
				echo "<a href=\"".$strpersonurl."\">";
			}
			echo $strpersonname;
			if ($strpersonurl!="") {
				echo "</a>";
			}
			if ($strpersonknownfordepartment!="") {
				echo " (".$strpersonknownfordepartment.")";
			}
			if ($strpersonidwikidata!="") {
				echo " <a href=\"".$strpersonwikidataurl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a>";
			}
			//Hook index.row.badge: marks added by the plugins beside the name
			echo f_pluginhtml("index.row.badge",array("type"=>"person","id"=>$lngpersonid,"index"=>"featured"));
			$lngdisplayorder++;
		}
		f_sqlfreeresult($resultlist);
	}
	
	//Hook page.beforefooter: blocks of the plugins at the end of the page
	echo f_pluginhtml("page.beforefooter",array("page"=>"person-search"));
	
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
