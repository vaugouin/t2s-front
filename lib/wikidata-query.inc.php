<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$strsearch=trim(f_request2("search"));
//echo "<br \>\$strsearch='$strsearch'";
$strsearch = str_replace("&\#039;","'",$strsearch);
$stritemid = "";

if ($intdbavailable) {
	//Database is available
	//List record found in the database
	$strpropertyid="";
	$strpropertylabel="";
	$stritemid="";
	$stritemlabel="";
	$strcond="";
	$lngitemcount=0;
	if ($strsearch!="") {
		$strsep=" ";
		$arrsearch=explode($strsep,$strsearch);
		//$lngtagcount=count($arrsearch);
		foreach ($arrsearch as $strelem) {
			if (strtoupper(substr($strelem,0,1))=="P") {
				$strpropertyid=$strelem;
				$strpropertylabel=f_descfromcode($strsqlns."WIKIDATA_PROPERTY","ID_PROPERTY","LABEL",f_stringtosql($strpropertyid));
				$strpropertydesc=f_descfromcode($strsqlns."WIKIDATA_PROPERTY","ID_PROPERTY","DESCRIPTION",f_stringtosql($strpropertyid));
				if ($strcond!="") {
					$strcond.=" AND ";
				}
				$strcond.=" V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY = ".f_stringtosql($strpropertyid)." ";
			}
			if (strtoupper(substr($strelem,0,1))=="Q") {
				$stritemid=$strelem;
				$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_ITEM_V1","ID_WIKIDATA","LABEL",f_stringtosql($stritemid)));
				$stritemdesc=trim(f_descfromcode($strsqlns."WIKIDATA_ITEM_V1","ID_WIKIDATA","DESCRIPTION",f_stringtosql($stritemid)));
				$stritemimage=trim(f_descfromcode($strsqlns."WIKIDATA_ITEM_V1","ID_WIKIDATA","WIKIPEDIA_IMAGE_PATH",f_stringtosql($stritemid)));
				if ($stritemlabel=="") {
					$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_PERSON_V1","ID_WIKIDATA","NAME",f_stringtosql($stritemid)));
					//$stritemdesc=trim(f_descfromcode($strsqlns."WIKIDATA_PERSON_V1","ID_WIKIDATA","DESCRIPTION",f_stringtosql($stritemid)));
					$stritemimage=trim(f_descfromcode($strsqlns."WIKIDATA_PERSON_V1","ID_WIKIDATA","WIKIPEDIA_PROFILE_PATH",f_stringtosql($stritemid)));
				}
				if ($stritemlabel=="") {
					$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_MOVIE_V1","ID_WIKIDATA","TITLE",f_stringtosql($stritemid)));
					//$stritemdesc=trim(f_descfromcode($strsqlns."WIKIDATA_MOVIE_V1","ID_WIKIDATA","DESCRIPTION",f_stringtosql($stritemid)));
					$stritemimage=trim(f_descfromcode($strsqlns."WIKIDATA_MOVIE_V1","ID_WIKIDATA","WIKIPEDIA_POSTER_PATH",f_stringtosql($stritemid)));
				}
				if ($stritemlabel=="") {
					$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_SERIE_V1","ID_WIKIDATA","TITLE",f_stringtosql($stritemid)));
					//$stritemdesc=trim(f_descfromcode($strsqlns."WIKIDATA_SERIE_V1","ID_WIKIDATA","DESCRIPTION",f_stringtosql($stritemid)));
					$stritemimage=trim(f_descfromcode($strsqlns."WIKIDATA_SERIE_V1","ID_WIKIDATA","WIKIPEDIA_POSTER_PATH",f_stringtosql($stritemid)));
				}
				if ($strcond!="") {
					$strcond.=" AND ";
				}
				$strcond.=" V_WIKIDATA_ITEM_PROPERTY.ID_ITEM = ".f_stringtosql($stritemid)." ";
			}
		}
	}
	$lngmoviecount=0;
	if ($strpropertyid!="") {
		if ($stritemid!="") {
			$strsqllist="SELECT COUNT(V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA) AS COMPTE FROM V_WIKIDATA_ITEM_PROPERTY";
			if ($strcond!="") {
				$strsqllist.=" WHERE ".$strcond;
			}
			//echo "<br \>\$strsqllist='$strsqllist'";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngmoviecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			$lngitemcount=1;
		} else {
			$strsqllist="SELECT COUNT(V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA) AS COMPTE FROM V_WIKIDATA_ITEM_PROPERTY";
			if ($strcond!="") {
				$strsqllist.=" WHERE ".$strcond;
			}
			//echo "<br \>\$strsqllist='$strsqllist'";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngmoviecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			$strsqlitem="SELECT V_WIKIDATA_ITEM_PROPERTY.ID_ITEM FROM V_WIKIDATA_ITEM_PROPERTY WHERE V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY = ".f_stringtosql($strpropertyid)." GROUP BY V_WIKIDATA_ITEM_PROPERTY.ID_ITEM HAVING COUNT(V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA) > 1 ";
			$strsqlitemcount="SELECT COUNT(*) AS COMPTE FROM (".$strsqlitem.") AS TMP";
			$resultitem=f_sqlselect($strsqlitemcount);
			if ($resultitem) {
				if ($rstempitem=f_sqlresultfetcharray($resultitem)) {
					$lngitemcount=$rstempitem["COMPTE"];
				}
				f_sqlfreeresult($resultitem);
			}
		}
	}
	//Display list data
	$strlistname="Wikidata query";
	f_headerdisplay($strpropertylabel.": ".$stritemlabel." - ".$strlistname);
	echo "<h1><a href=\"wikidata-query.php\">Wikidata query</a>: ".$lngmoviecount." movies/series/persons, ".$lngitemcount." \"".$strpropertylabel."\" items</h1>";
	if ($strpropertyid!="") {
		//$strpropertyurl="property.php?propertyid=".$strpropertyid;
		$strpropertyurl="wikidata-query.php?search=".$strpropertyid;
		echo "<p><a href=\"".$strpropertyurl."\">".$strpropertylabel."</a> ";
		echo "(<a href=\"https://www.wikidata.org/wiki/Property:".$strpropertyid."\" target=\"_blank\" rel=\"noreferrer\">".$strpropertyid."</a>)";
		echo ": ";
		if ($stritemid!="") {
			echo $stritemlabel;
			$stritemwikidataurl="https://www.wikidata.org/wiki/".$stritemid;
			echo " (<a href=\"".$stritemwikidataurl."\" target=\"_blank\" rel=\"noreferrer\">";
			echo $stritemid;
			echo "</a>)";
		}
		echo "</p>";
	} else {
		if ($stritemid!="") {
			echo "<p>".$stritemlabel."</p>";
		}
	}
	if ($strpropertydesc!="") {
		echo "<p>".$strpropertydesc."</p>";
	}
	if ($stritemdesc!="") {
		echo "<p>".$stritemdesc."</p>";
	}
	if ($stritemimage!="") {
		$stritemimage500=f_getwikimediaimageurlbywidth($stritemimage,500);
		echo "<img src=\"".$stritemimage."\" alt=\"".$stritemlabel."\" title=\"".$stritemlabel."\" width=\"500\" style=\"background-color: white;\" />";
	}
	echo "<form method=\"GET\" name=\"frmmain\">";
	echo "<strong>".get_movie_string('lib_search_prompt')."</strong>&nbsp;";
	echo "<input type=\"text\" name=\"search\" id=\"search\" value=\"".$strsearch."\" class=\"responsive-input\" placeholder=\"" . get_movie_string("lib_t2s_search_placeholder") . "\" maxlength=\"500\">";
	echo "<br />";
	echo "<input type=\"submit\" name=\"val\" value=\"".get_movie_string('lib_search_button')."\" >";
	echo "</form>";
	//if ($strsearch!="") {
		if ($stritemid!="") {
			$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$stritemid,", ");
			if ($strwikidataeverything!="") {
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				echo $strwikidataeverything;
			}
			echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
			$strwikipediacontent=f_wikipediallsections($struilang,$stritemid,$strsep=", ");
			if ($strwikipediacontent!="") {
				echo $strwikipediacontent;
			}
		}
		if ($strpropertyid=="" && $stritemid=="") {
			//List all properties
			$strsqlproperty="SELECT DISTINCT V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY FROM V_WIKIDATA_ITEM_PROPERTY ";
			//echo "<br />\$strsqlproperty='$strsqlproperty'";
			$resultproperty=f_sqlselect($strsqlproperty);
			if ($resultproperty) {
				while ($rstempproperty=f_sqlresultfetcharray($resultproperty)) {
					$strpropertyid=$rstempproperty["ID_PROPERTY"];
					$strpropertylabel=f_descfromcode($strsqlns."WIKIDATA_PROPERTY","ID_PROPERTY","LABEL",f_stringtosql($strpropertyid));
					echo "<a href=\"wikidata-query.php?search=".$strpropertyid."\">".$strpropertyid." - ".$strpropertylabel."</a><br />";
				}
				f_sqlfreeresult($resultproperty);
			}
			$stritemid="";
			$stritemlabel="";
			$lngmoviecount=0;
		}
		if ($strpropertyid!="" && $stritemid=="") {
			//List all items for this property 
			$strsqlitem="SELECT DISTINCT V_WIKIDATA_ITEM_PROPERTY.ID_ITEM FROM V_WIKIDATA_ITEM_PROPERTY WHERE V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY = ".f_stringtosql($strpropertyid)." ORDER BY V_WIKIDATA_ITEM_PROPERTY.ID_ITEM ASC ";
			//echo "<br />\$strsqlitem='$strsqlitem'";
			$resultitem=f_sqlselect($strsqlitem);
			if ($resultitem) {
				while ($rstempitem=f_sqlresultfetcharray($resultitem)) {
					$stritemid=$rstempitem["ID_ITEM"];
					$strsqllist="SELECT COUNT(V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA) AS COMPTE FROM V_WIKIDATA_ITEM_PROPERTY";
					$strcond="";
					$strcond.=" V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY = ".f_stringtosql($strpropertyid)." ";
					$strcond.=" AND V_WIKIDATA_ITEM_PROPERTY.ID_ITEM = ".f_stringtosql($stritemid)." ";
					if ($strcond!="") {
						$strsqllist.=" WHERE ".$strcond;
					}
					$resultlist=f_sqlselect($strsqllist);
					if ($resultlist) {
						if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
							$lngmoviecount=$rstemplist["COMPTE"];
						}
						f_sqlfreeresult($resultlist);
					}
					if ($lngmoviecount>1) {
						$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_ITEM_V1","ID_WIKIDATA","LABEL",f_stringtosql($stritemid)));
						if ($stritemlabel=="") {
							$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_PERSON_V1","ID_WIKIDATA","NAME",f_stringtosql($stritemid)));
						}
						if ($stritemlabel=="") {
							$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_MOVIE_V1","ID_WIKIDATA","TITLE",f_stringtosql($stritemid)));
						}
						echo "<a href=\"wikidata-query.php?search=".$strpropertyid." ".$stritemid."\">".$stritemid." - ".$stritemlabel.": ".$lngmoviecount." item".($lngmoviecount==1 ? "" : "s")."</a><br />";
					}
				}
				f_sqlfreeresult($resultitem);
			}
			$stritemid="";
			$stritemlabel="";
			$lngmoviecount=0;
		}
		if ($strpropertyid!="" && $stritemid!="") {
			$strsqlmovielist="";
			$strsqlmovielist.="SELECT DISTINCT T_WC_TMDB_MOVIE.ID_MOVIE, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.TITLE, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.ORIGINAL_TITLE, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.DAT_RELEASE, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.ID_IMDB, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.OVERVIEW, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.POSTER_PATH, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.GENRES, ";
			if ($struilang!="en") {
				$strsqlmovielist.="T_WC_TMDB_MOVIE_LANG.TITLE AS TITLE_LANG, ";
				$strsqlmovielist.="T_WC_TMDB_MOVIE_LANG.OVERVIEW AS OVERVIEW_LANG, ";
				$strsqlmovielist.="T_WC_TMDB_MOVIE_LANG.POSTER_PATH AS POSTER_PATH_LANG, ";
			}
			$strsqlmovielist.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
			$strsqlmovielist.="FROM T_WC_TMDB_MOVIE ";
			//$strsqlmovielist.="INNER JOIN T_WC_WIKIDATA_MOVIE ON T_WC_TMDB_MOVIE.ID_WIKIDATA = T_WC_WIKIDATA_MOVIE.ID_WIKIDATA ";
			$strsqlmovielist.="INNER JOIN V_WIKIDATA_ITEM_PROPERTY ON T_WC_TMDB_MOVIE.ID_WIKIDATA = V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA ";
			$strsqlmovielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlmovielist.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE ";
			}
			if ($strcond!="") {
				$strsqlmovielist.="WHERE ".$strcond;
			}
			if ($struilang!="en") {
				$strsqlmovielist.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			if ($intdebug) {
				echo "<br />".$strsqlmovielist;
			}
			//$strsqlmovielist.="ORDER BY T_WC_WIKIDATA_MOVIE.ID_CRITERION ASC ";
			
			$intlistmoviefound=false;
			$lngdisplayorder=0;
			echo "<h2>".get_movie_string('lib_movies_section')."</h2>";
			echo "<table>";
			$resultmovielist=f_sqlselect($strsqlmovielist);
			if ($resultmovielist) {
				while ($rstempmovielist=f_sqlresultfetcharray($resultmovielist)) {
					$intlistmoviefound=true;
					
					$strmovietitle=$rstempmovielist['TITLE'];
					$strmovieoriginaltitle=$rstempmovielist['ORIGINAL_TITLE'];
					$strmoviereleasedate=$rstempmovielist['DAT_RELEASE'];
					$lngmovieid=f_stringtolong($rstempmovielist['ID_MOVIE']);
					$strmovieidimdb=trim($rstempmovielist['ID_IMDB']);
					$strmovieoverview=trim($rstempmovielist['OVERVIEW']);
					$strmovieposterpath=$rstempmovielist['POSTER_PATH'];
					$strmoviegenres=$rstempmovielist['GENRES'];
					$dblaveragerating=$rstempmovielist['averageRating'];
					$lngimdbvotes=$rstempmovielist['numVotes'];
					if ($struilang!="en") {
						$strmovietitlelang="";
						$strmovieoverviewlang="";
						$strmovieposterpathlang="";
						$strmovietitlelang=$rstempmovielist['TITLE_LANG'];
						$strmovieoverviewlang=trim($rstempmovielist['OVERVIEW_LANG']);
						$strmovieposterpathlang=$rstempmovielist['POSTER_PATH_LANG'];
						if ($strmovietitlelang!="") {
							$strmovietitle=$strmovietitlelang;
						}
						if ($strmovieoverviewlang!="") {
							$strmovieoverview=$strmovieoverviewlang;
						}
						if ($strmovieposterpathlang!="") {
							$strmovieposterpath=$strmovieposterpathlang;
						}
					}
					$lngdisplayorder++;
					f_movielistdisplay($lngmovieid,$strmovietitle,$strmovieoriginaltitle,"",$strmoviereleasedate,$strmovieidimdb,$dblaveragerating,$strmovieoverview,$strmovieposterpath,$strmoviegenres,3,0,"float",$lngimdbvotes);
				}
				f_sqlfreeresult($resultmovielist);
			}
			echo "</table>";

			$strsqlserielist="";
			$strsqlserielist.="SELECT DISTINCT T_WC_TMDB_SERIE.ID_SERIE, ";
			$strsqlserielist.="T_WC_TMDB_SERIE.TITLE, ";
			$strsqlserielist.="T_WC_TMDB_SERIE.ORIGINAL_TITLE, ";
			$strsqlserielist.="T_WC_TMDB_SERIE.DAT_FIRST_AIR, ";
			$strsqlserielist.="T_WC_TMDB_SERIE.DAT_LAST_AIR, ";
			$strsqlserielist.="T_WC_TMDB_SERIE.ID_IMDB, ";
			$strsqlserielist.="T_WC_TMDB_SERIE.OVERVIEW, ";
			$strsqlserielist.="T_WC_TMDB_SERIE.POSTER_PATH, ";
			$strsqlserielist.="T_WC_TMDB_SERIE.GENRES, ";
			if ($struilang!="en") {
				$strsqlserielist.="T_WC_TMDB_SERIE_LANG.TITLE AS TITLE_LANG, ";
				$strsqlserielist.="T_WC_TMDB_SERIE_LANG.OVERVIEW AS OVERVIEW_LANG, ";
				$strsqlserielist.="T_WC_TMDB_SERIE_LANG.POSTER_PATH AS POSTER_PATH_LANG, ";
			}
			$strsqlserielist.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
			$strsqlserielist.="FROM T_WC_TMDB_SERIE ";
			//$strsqlserielist.="INNER JOIN T_WC_WIKIDATA_SERIE ON T_WC_TMDB_SERIE.ID_WIKIDATA = T_WC_WIKIDATA_SERIE.ID_WIKIDATA ";
			$strsqlserielist.="INNER JOIN V_WIKIDATA_ITEM_PROPERTY ON T_WC_TMDB_SERIE.ID_WIKIDATA = V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA ";
			$strsqlserielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlserielist.="LEFT JOIN T_WC_TMDB_SERIE_LANG ON T_WC_TMDB_SERIE.ID_SERIE = T_WC_TMDB_SERIE_LANG.ID_SERIE ";
			}
			if ($strcond!="") {
				$strsqlserielist.="WHERE ".$strcond;
			}
			if ($struilang!="en") {
				$strsqlserielist.="AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			if ($intdebug) {
				echo "<br />".$strsqlserielist;
			}
			
			$intlistseriefound=false;
			$lngdisplayorder=0;
			echo "<h2>".get_movie_string('lib_series_section')."</h2>";
			echo "<table>";
			$resultserielist=f_sqlselect($strsqlserielist);
			if ($resultserielist) {
				while ($rstempserielist=f_sqlresultfetcharray($resultserielist)) {
					$intlistseriefound=true;
					
					$strserietitle=$rstempserielist['TITLE'];
					$strserieoriginaltitle=$rstempserielist['ORIGINAL_TITLE'];
					$strseriereleasedate=$rstempserielist['DAT_FIRST_AIR'];
					$strserielastairdate=$rstempserielist['DAT_LAST_AIR'];
					$lngserieid=f_stringtolong($rstempserielist['ID_SERIE']);
					$strserieidimdb=trim($rstempserielist['ID_IMDB']);
					$strserieoverview=trim($rstempserielist['OVERVIEW']);
					$strserieposterpath=$rstempserielist['POSTER_PATH'];
					$strseriegenres=$rstempserielist['GENRES'];
					$dblaveragerating=$rstempserielist['averageRating'];
					$lngimdbvotes=$rstempserielist['numVotes'];
					if ($struilang!="en") {
						$strserietitlelang="";
						$strserieoverviewlang="";
						$strserieposterpathlang="";
						$strserietitlelang=$rstempserielist['TITLE_LANG'];
						$strserieoverviewlang=trim($rstempserielist['OVERVIEW_LANG']);
						$strserieposterpathlang=$rstempserielist['POSTER_PATH_LANG'];
						if ($strserietitlelang!="") {
							$strserietitle=$strserietitlelang;
						}
						if ($strserieoverviewlang!="") {
							$strserieoverview=$strserieoverviewlang;
						}
						if ($strserieposterpathlang!="") {
							$strserieposterpath=$strserieposterpathlang;
						}
					}
					$lngdisplayorder++;
					f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,"",$strseriereleasedate,$strserielastairdate,$strserieidimdb,$dblaveragerating,$strserieoverview,$strserieposterpath,$strseriegenres,3,0,"float",$lngimdbvotes);
				}
				f_sqlfreeresult($resultserielist);
			}
			echo "</table>";

			$strsqlmpersonlist="";
			$strsqlmpersonlist.="SELECT DISTINCT T_WC_TMDB_PERSON.ID_PERSON, ";
			$strsqlmpersonlist.="T_WC_TMDB_PERSON.NAME, ";
			$strsqlmpersonlist.="T_WC_TMDB_PERSON.BIRTHDAY, ";
			$strsqlmpersonlist.="T_WC_TMDB_PERSON.DEATHDAY, ";
			$strsqlmpersonlist.="T_WC_TMDB_PERSON.ID_IMDB, ";
			$strsqlmpersonlist.="T_WC_TMDB_PERSON.BIOGRAPHY, ";
			$strsqlmpersonlist.="T_WC_TMDB_PERSON.PROFILE_PATH, ";
			$strsqlmpersonlist.="T_WC_TMDB_PERSON.ID_WIKIDATA ";
			$strsqlmpersonlist.="FROM T_WC_TMDB_PERSON ";
			$strsqlmpersonlist.="INNER JOIN T_WC_WIKIDATA_PERSON_V1 ON T_WC_TMDB_PERSON.ID_WIKIDATA = T_WC_WIKIDATA_PERSON_V1.ID_WIKIDATA ";
			$strsqlmpersonlist.="INNER JOIN V_WIKIDATA_ITEM_PROPERTY ON T_WC_TMDB_PERSON.ID_WIKIDATA = V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA ";
			if ($strcond!="") {
				$strsqlmpersonlist.="WHERE ".$strcond;
			}
			//$strsqlmovielist.="ORDER BY T_WC_WIKIDATA_MOVIE.ID_CRITERION ASC ";
			
			$intlistpersonfound=false;
			$lngdisplayorder=0;
			echo "<h2>".get_movie_string('lib_persons_section')."</h2>";
			echo "<table>";
			$resultpersonlist=f_sqlselect($strsqlmpersonlist);
			if ($resultpersonlist) {
				while ($rstemppersonlist=f_sqlresultfetcharray($resultpersonlist)) {
					$intlistpersonfound=true;
					
					$strpersonname=$rstemppersonlist['NAME'];
					$strpersonbiography=trim($rstemppersonlist['BIOGRAPHY']);
					$lngpersonid=f_stringtolong($rstemppersonlist['ID_PERSON']);
					$strpersonidimdb=trim($rstemppersonlist['ID_IMDB']);
					$strpersonbirthday=$rstemppersonlist['BIRTHDAY'];
					$strpersondeathday=$rstemppersonlist['DEATHDAY'];
					$strpersonprofilepath=$rstemppersonlist['PROFILE_PATH'];
					$strwikidataid=trim($rstemppersonlist['ID_WIKIDATA']);
					if ($strpersonbiography=="") {
						$strsqlbio="SELECT CONTENT FROM T_WC_WIKIPEDIA_PAGE_LANG_SECTION WHERE ID_WIKIDATA = ".f_stringtosql($strwikidataid)." AND LANG = ".f_stringtosql($struilang)." AND TITLE = 'Intro' ";
						$strpersonbiography=f_fieldfromquery($strsqlbio);
					}
					$lngdisplayorder++;
					f_personlistdisplay($lngpersonid,$strpersonname,$strpersonbiography,$strpersonbirthday,$strpersondeathday,$strpersonidimdb,$strpersonprofilepath,3);
				}
				f_sqlfreeresult($resultpersonlist);
			}
			echo "</table>";
		}
	//}
	//Hook page.beforefooter: blocks of the plugins at the end of the page
	echo f_pluginhtml("page.beforefooter",array("page"=>"wikidata-query"));
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
