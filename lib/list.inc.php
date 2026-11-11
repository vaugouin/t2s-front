<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$lnglistid=f_stringtolong(f_request2("listid"));
	$intlistfound=false;
	
	if ($lnglistid>0) {
		//Read list data from the MySQL database
		$strsqllist="SELECT * FROM ".$strsqlns."TMDB_LIST WHERE ID_LIST = ".f_longtosql($lnglistid)." AND DELETED = 0 AND TIM_CREDITS_COMPLETED IS NOT NULL ";
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$intlistfound=true;
				$strlistdescription=$rstemplist['DESCRIPTION'];
				$strlistposterpath=$rstemplist['POSTER_PATH'];
				$strlistname=$rstemplist['NAME'];
				$strlisttimcreditscompleted=$rstemplist['TIM_CREDITS_COMPLETED'];
				$intlistusefortagging=f_stringtolong($rstemplist['USE_FOR_TAGGING']);
				$strlistshortname=trim($rstemplist['SHORT_NAME']);
				$strlistcreatedby=trim($rstemplist['CREATED_BY']);
				if ($strlistcreatedby==$strtmdbcurrentusername) {
					$intlisttype=f_stringtolong($rstemplist['ID_LIST_TYPE']);
					$strlisttypedesc=f_descfromcode("T_WC_TMDB_LIST_TYPE","ID_LIST_TYPE","DESCRIPTION",$intlisttype);
					$strlistwikidatapropertyid=$rstemplist['ID_PROPERTY'];
					$strlistwikidataitemid=$rstemplist['ID_ITEM'];
				}
			}
			f_sqlfreeresult($resultlist);
		}
		if ($struilang!="en") {
			//Read translated list data from the MySQL database
			$strsqllist="SELECT * FROM ".$strsqlns."TMDB_LIST_LANG WHERE ID_LIST = ".f_longtosql($lnglistid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
			//echo "<br />".$strsqllist;
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$strlistshortname=trim($rstemplist['SHORT_NAME']);
				}
				f_sqlfreeresult($resultmovie);
			}
		}
		
		if ($intlistfound) {
			//List record found in the database
			$lngmoviecount=0;
			$lngseriecount=0;
			$strmovieimdblinksall="";
			$strsqllist="SELECT COUNT(T_WC_TMDB_MOVIE_LIST.ID_MOVIE) AS COMPTE FROM T_WC_TMDB_MOVIE_LIST
	INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_LIST.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE
	WHERE T_WC_TMDB_MOVIE_LIST.ID_LIST = ".f_longtosql($lnglistid)." 
	";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngmoviecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			$strsqllist="SELECT COUNT(T_WC_TMDB_SERIE_LIST.ID_SERIE) AS COMPTE FROM T_WC_TMDB_SERIE_LIST
INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_LIST.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE
WHERE T_WC_TMDB_SERIE_LIST.ID_LIST = ".f_longtosql($lnglistid)." 
";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngseriecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			$lngitemcount=$lngmoviecount+$lngseriecount;
			$strsqlmovieimdblinks="SELECT T_WC_TMDB_MOVIE.ID_IMDB ";
			$strsqlmovieimdblinks.="FROM T_WC_TMDB_MOVIE_LIST ";
			$strsqlmovieimdblinks.="INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_LIST.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE ";
			$strsqlmovieimdblinks.="WHERE T_WC_TMDB_MOVIE_LIST.ID_LIST = ".f_longtosql($lnglistid)." ";
			$strsqlmovieimdblinks.="ORDER BY T_WC_TMDB_MOVIE_LIST.DISPLAY_ORDER ";
			$resultmovieimdblinks=f_sqlselect($strsqlmovieimdblinks);
			if ($resultmovieimdblinks) {
				while ($rstempmovieimdblinks=f_sqlresultfetcharray($resultmovieimdblinks)) {
					$strmovieidimdblink=trim($rstempmovieimdblinks['ID_IMDB']);
					if ($strmovieidimdblink!="") {
						$strmovieimdblinksall.="https://www.imdb.com/title/".$strmovieidimdblink."/\r\n";
					}
				}
				f_sqlfreeresult($resultmovieimdblinks);
			}
			//Display list data
			//Social preview: the poster is the visual shown when the page URL is shared
			$arrsociallist=array();
			$arrsociallist['description']=$strlistdescription;
			if ($strlistposterpath!="") {
				$arrsociallist['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strlistposterpath);
				//TMDb posters are 2:3, so the w342 rendition is 342x513
				$arrsociallist['imagewidth']=342;
				$arrsociallist['imageheight']=513;
				$arrsociallist['imagealt']=$strlistname;
			}
			f_headerdisplay($strlistname,"",$arrsociallist);
			echo "<h1>".$strlistname.": ".$lngitemcount." ".get_movie_string('lib_movie_item')."</h1>";
			if ($strlistshortname!="") {
				echo "<p><strong>".get_movie_string('lib_short_name')."</strong>: ".$strlistshortname."</p>";
			}
			if ($strlistposterpath!="") {
				echo "<p>";
				echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strlistposterpath."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".$strtmdbimagespathbaseurl.$strlistposterpath."\" alt=\"".$strlistname."\" title=\"".$strlistname."\" style=\"background-color: white;\" />";
				echo "</a>";
				echo "</p>";
			}
			if ($strlistcreatedby!="") {
				$strlistcreatedbyurl="https://www.themoviedb.org/u/".$strlistcreatedby."/lists";
				$strlistcreatedbydisplay=" <a href=\"".$strlistcreatedbyurl."\" target=\"_blank\" rel=\"noreferrer\">".$strlistcreatedby."</a>";
				echo "<p>".get_movie_string('lib_created_by').$strlistcreatedbydisplay."</p>";
				if ($strlistcreatedby==$strtmdbcurrentusername) {
					echo "<p>".get_movie_string('lib_list_type')." - ".$strlisttypedesc."</p>";
					if ($strlistwikidatapropertyid!="") {
						echo "<p>".get_movie_string('lib_wikidata_property_id').": ".$strlistwikidatapropertyid."</p>";
					}
					if ($strlistwikidataitemid!="") {
						echo "<p>".get_movie_string('lib_wikidata_item_id').": ".$strlistwikidataitemid."</p>";
					}
					if ($strlistwikidataitemid!="") {
						echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
						$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strlistwikidataitemid,", ");
						echo $strwikidataeverything;
						echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
						$strpersonwikipediacontent=f_wikipediallsections($struilang,$strlistwikidataitemid,$strsep=", ");
						echo $strpersonwikipediacontent;
					}
					//Hook entity.admin: maintenance blocks of the plugins
					echo f_pluginhtml("entity.admin",array("type"=>"list","id"=>$lnglistid,"createdby"=>$strlistcreatedby));
				}
			}
			echo "<p>".get_movie_string('lib_list_used_for_tagging').": ".$intlistusefortagging;
			//Hook entity.actions: action links of the plugins on the tagging line
			echo f_pluginhtml("entity.actions",array("type"=>"list","id"=>$lnglistid,"slot"=>"tagging"));
			echo "</p>";
			
			echo "<p>".get_movie_string('lib_person_record_updated').": ".$strlisttimcreditscompleted;
			//Hook entity.actions: action links of the plugins on the record status line
			echo f_pluginhtml("entity.actions",array("type"=>"list","id"=>$lnglistid,"slot"=>"refresh"));
			//Hook entity.actions: action links of the plugins on the record status line
			echo f_pluginhtml("entity.actions",array("type"=>"list","id"=>$lnglistid,"slot"=>"record"));
			echo "</p>";
			//Hook entity.status: lines of the plugins under the record status
			echo f_pluginhtml("entity.status",array("type"=>"list","id"=>$lnglistid));
			echo "<textarea id=\"movieimdblinks\" name=\"movieimdblinks\" cols=\"80\" rows=\"10\" class=\"responsive-input\" maxlength=\"10000000\" wrap=\"\">";
			echo $strmovieimdblinksall;
			echo "</textarea>";
			if ($strlistdescription!="") {
				echo "<h2>".get_movie_string('lib_description_section')."</h2>";
				echo "<p>".str_replace("\r","<br />",str_replace("\n","<br />",$strlistdescription))."</p>";
			}
			
			$strsqlmovielist="";
			$strsqlmovielist.="SELECT T_WC_TMDB_MOVIE_LIST.ID_MOVIE, ";
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
			$strsqlmovielist.="FROM T_WC_TMDB_MOVIE_LIST ";
			$strsqlmovielist.="INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_LIST.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE ";
			$strsqlmovielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlmovielist.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE_LIST.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE ";
			}
			$strsqlmovielist.="WHERE T_WC_TMDB_MOVIE_LIST.ID_LIST = ".f_longtosql($lnglistid)." ";
			if ($struilang!="en") {
				$strsqlmovielist.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlmovielist.="ORDER BY T_WC_TMDB_MOVIE_LIST.DISPLAY_ORDER ";
			
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
					//$intdebug=1;
					f_movielistdisplay($lngmovieid,$strmovietitle,$strmovieoriginaltitle,"",$strmoviereleasedate,$strmovieidimdb,$dblaveragerating,$strmovieoverview,$strmovieposterpath,$strmoviegenres,3,0,"float",$lngimdbvotes);
					//$intdebug=0;
				}
				f_sqlfreeresult($resultmovielist);
			}
			echo "</table>";
			
			$strsqlserielist="";
			$strsqlserielist.="SELECT T_WC_TMDB_SERIE_LIST.ID_SERIE, ";
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
			$strsqlserielist.="FROM T_WC_TMDB_SERIE_LIST ";
			$strsqlserielist.="INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_LIST.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE ";
			$strsqlserielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlserielist.="LEFT JOIN T_WC_TMDB_SERIE_LANG ON T_WC_TMDB_SERIE_LIST.ID_SERIE = T_WC_TMDB_SERIE_LANG.ID_SERIE ";
			}
			$strsqlserielist.="WHERE T_WC_TMDB_SERIE_LIST.ID_LIST = ".f_longtosql($lnglistid)." ";
			if ($struilang!="en") {
				$strsqlserielist.="AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlserielist.="ORDER BY T_WC_TMDB_SERIE_LIST.DISPLAY_ORDER ";
			
			$intlistseriefound=false;
			$lngdisplayorder=0;
			echo "<h2>".get_movie_string('lib_tv_shows_section')."</h2>";
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
			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"list"));
			f_footerdisplay();
		} else {
			//List record NOT found in the database
			/*
			$strmessage="List record NOT found in the database";
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
			*/
			//Filter page.url: a plugin that can load the list from the TMDb API answers the URL of
			//its page (private mode only); without one the visitor gets the message
			$strredirectscript=f_pluginfilter("page.url","",array("page"=>"list-load","params"=>array("listid"=>$lnglistid)));
			if ($strredirectscript!="") {
				header("Location: ".$strredirectscript);
			} else {
				$strmessage=get_movie_string('lib_list_not_found_db');
				f_headerdisplay($strmessage);
				echo "<h1>".$strmessage."</h1>";
				f_footerdisplay();
			}
		}
	}  else {
		//listid URL parameter not set
		$strmessage=get_movie_string('lib_listid_not_set');
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
