<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$lngkeywordid=f_stringtolong(f_request2("keywordid"));
	$lngrows=f_stringtolong(f_request2("rows"));
	$intkeywordfound=false;
	
	if ($lngkeywordid>0) {
		//Read keyword data from the MySQL database
		$strkeywordstable=$strsqlns."TMDB_KEYWORD";
		$strsqllist="SELECT * FROM ".$strkeywordstable." WHERE ID_KEYWORD = ".f_longtosql($lngkeywordid)." ";
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$intkeywordfound=true;
				$strkeywordname=$rstemplist['NAME'];
				$strkeywordtimcreditscompleted=$rstemplist['TIM_CREDITS_COMPLETED'];
				$strkeywordtimupdated=$rstemplist['TIM_UPDATED'];
				$strkeywordclassifiercategory=$rstemplist['CLASSIFIER_CATEGORY'];
				$intkeywordusedfort2stopic=f_stringtolong($rstemplist['USED_FOR_T2S_TOPIC']);
				$strkeywordenname="";
			}
			f_sqlfreeresult($resultlist);
		}
		if ($struilang!="en") {
			$strkeywordstable=$strsqlns."TMDB_KEYWORD_LANG";
		}
		if ($struilang!="en") {
			$strsqllist="SELECT * FROM ".$strsqlns."TMDB_KEYWORD WHERE ID_KEYWORD = ".f_longtosql($lngkeywordid)." ";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$strkeywordenname=$rstemplist['NAME'];
				}
				f_sqlfreeresult($resultlist);
			}
		}
		
		if ($intkeywordfound) {
			//Keyword record found in the database
			$lngmoviecount=0;
			$strsqllist="SELECT COUNT(T_WC_TMDB_MOVIE_KEYWORD.ID_MOVIE) AS COMPTE FROM T_WC_TMDB_MOVIE_KEYWORD
INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_KEYWORD.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE
WHERE T_WC_TMDB_MOVIE_KEYWORD.ID_KEYWORD = ".f_longtosql($lngkeywordid)."
";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngmoviecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			$lngseriecount=0;
			$strsqllist="SELECT COUNT(DISTINCT T_WC_TMDB_SERIE_KEYWORD.ID_SERIE) AS COMPTE FROM T_WC_TMDB_SERIE_KEYWORD ";
			$strsqllist.="INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_KEYWORD.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE ";
			$strsqllist.="WHERE T_WC_TMDB_SERIE_KEYWORD.ID_KEYWORD = ".f_longtosql($lngkeywordid)." ";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngseriecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			//Display keyword data
			f_headerdisplay($strkeywordname);
			echo "<h1>".get_movie_string('lib_keyword_name')." : ".$strkeywordname." (".$strkeywordenname.")".": ".$lngmoviecount." ".get_movie_string('lib_movie_item').", ".$lngseriecount." ".get_movie_string('lib_serie_item')."</h1>";
			echo "<p>".get_movie_string('lib_person_record_updated').": ".$strkeywordtimcreditscompleted;
			//Hook entity.actions: action links of the plugins on the record status line
			echo f_pluginhtml("entity.actions",array("type"=>"keyword","id"=>$lngkeywordid,"slot"=>"record"));
			echo "</p>";
			//Hook entity.status: lines of the plugins under the record status
			echo f_pluginhtml("entity.status",array("type"=>"keyword","id"=>$lngkeywordid));
			echo "<p>".get_movie_string('lib_keyword_used_for_topic');
			//Hook entity.actions: action links of the plugins on the tagging line
			echo f_pluginhtml("entity.actions",array("type"=>"keyword","id"=>$lngkeywordid,"slot"=>"tagging"));
			echo "</p>";
			if ($strkeywordclassifiercategory!="") {
				echo "<p>".get_movie_string('lib_classifier_category').": ".$strkeywordclassifiercategory."</p>";
			}
			if ($intkeywordusedfort2stopic) {
				echo "<p>".get_movie_string('lib_used_for_t2s_yes')."</p>";
			} else {
				echo "<p>".get_movie_string('lib_used_for_t2s_no')."</p>";
			}
			
			
			$strsqlmovielist="";
			$strsqlmovielist.="SELECT T_WC_TMDB_MOVIE_KEYWORD.ID_MOVIE, ";
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
			$strsqlmovielist.="FROM T_WC_TMDB_MOVIE_KEYWORD ";
			$strsqlmovielist.="INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_KEYWORD.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE ";
			$strsqlmovielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlmovielist.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE_KEYWORD.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE ";
			}
			$strsqlmovielist.="WHERE T_WC_TMDB_MOVIE_KEYWORD.ID_KEYWORD = ".f_longtosql($lngkeywordid)." ";
			if ($struilang!="en") {
				$strsqlmovielist.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlmovielist.="ORDER BY T_WC_TMDB_MOVIE.POPULARITY DESC ";
			if ($lngrows>0) {
				$strsqlmovielist.="LIMIT ".f_longtosql($lngrows)." ";
			}
			
			$intlistmoviefound=false;
			$lngdisplayorder=0;
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

			// ── Series ────────────────────────────────────────────────────────────
			echo "<h2>".get_movie_string('lib_series_section')."</h2>";
			$strsqlserielist="";
			$strsqlserielist.="SELECT T_WC_TMDB_SERIE_KEYWORD.ID_SERIE, ";
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
			$strsqlserielist.="FROM T_WC_TMDB_SERIE_KEYWORD ";
			$strsqlserielist.="INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_KEYWORD.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE ";
			$strsqlserielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlserielist.="LEFT JOIN T_WC_TMDB_SERIE_LANG ON T_WC_TMDB_SERIE_KEYWORD.ID_SERIE = T_WC_TMDB_SERIE_LANG.ID_SERIE ";
			}
			$strsqlserielist.="WHERE T_WC_TMDB_SERIE_KEYWORD.ID_KEYWORD = ".f_longtosql($lngkeywordid)." ";
			if ($struilang!="en") {
				$strsqlserielist.="AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlserielist.="ORDER BY T_WC_TMDB_SERIE.POPULARITY DESC ";
			if ($lngrows>0) {
				$strsqlserielist.="LIMIT ".f_longtosql($lngrows)." ";
			}
			$lngdisplayorder=0;
			echo "<table>";
			$resultserielist=f_sqlselect($strsqlserielist);
			if ($resultserielist) {
				while ($rstempserielist=f_sqlresultfetcharray($resultserielist)) {
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
						$strserietitlelang=$rstempserielist['TITLE_LANG'];
						$strserieoverviewlang=trim($rstempserielist['OVERVIEW_LANG']);
						$strserieposterpathlang=$rstempserielist['POSTER_PATH_LANG'];
						if ($strserietitlelang!="") { $strserietitle=$strserietitlelang; }
						if ($strserieoverviewlang!="") { $strserieoverview=$strserieoverviewlang; }
						if ($strserieposterpathlang!="") { $strserieposterpath=$strserieposterpathlang; }
					}
					$lngdisplayorder++;
					f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,"",$strseriereleasedate,$strserielastairdate,$strserieidimdb,$dblaveragerating,$strserieoverview,$strserieposterpath,$strseriegenres,3,0,"float",$lngimdbvotes);
				}
				f_sqlfreeresult($resultserielist);
			}
			echo "</table>";

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"keyword-mm"));
			f_footerdisplay();
		} else {
			//Keyword record NOT found in the database
			$strmessage=get_movie_string('lib_keyword_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	}  else {
		//keywordid URL parameter not set
		$strmessage=get_movie_string('lib_keywordid_not_set');
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
	}
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
