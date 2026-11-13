<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");  
include_once(__DIR__."/social-ids.inc.php");
include_once(__DIR__."/work-properties-format.inc.php");
include_once(__DIR__."/work-properties-sql.inc.php");
include_once(__DIR__."/watch-country.inc.php");
include_once(__DIR__."/movie-sections.inc.php");

if ($intdbavailable) {
	//Database is available
	$strwatchcountry=f_watchcountryresolve();
	$arrwatchcountryoptions=f_watchcountryoptions($struilang);
	$lngserieid=f_stringtolong(f_request2("serieid"));
	$strimdbid=substr(trim(f_request2("imdbid")),0,15);
	$intseriefound=false;
	
	if ($lngserieid==0 && $strimdbid!="") {
		$lngserieid=f_stringtolong(f_descfromcode($strsqlns."TMDB_SERIE","ID_IMDB","ID_SERIE",f_stringtosql($strimdbid),"",""));
		if ($lngserieid==0) {
			//Serie record NOT found in the database
			$strmessage=get_movie_string('lib_serie_not_found_db');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
			exit;
		}
	}
	if ($lngserieid>0) {
		//Read serie data from the MySQL database
		$strsqlserie="SELECT * FROM ".$strsqlns."TMDB_SERIE WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND DELETED = 0 AND TIM_CREDITS_COMPLETED IS NOT NULL ";
		$resultserie=f_sqlselect($strsqlserie);
		if ($resultserie) {
			if ($rstempserie=f_sqlresultfetcharray($resultserie)) {
				$intseriefound=true;
				$arrseriesocialids=f_socialidsfromrow($rstempserie);
				$strserieidimdb=$rstempserie["ID_IMDB"];
				$strserieoverview=$rstempserie['OVERVIEW'];
				$strseriedatfirstair=$rstempserie['DAT_FIRST_AIR'];
				$strseriefirstairyear=substr($strseriedatfirstair,0,4);
				$strseriedatlastair=$rstempserie['DAT_LAST_AIR'];
				$strserielastairyear=substr($strseriedatlastair,0,4);
				//$intserievideo=f_stringtolong($rstempserie['VIDEO']);
				$strserieposterpath=$rstempserie['POSTER_PATH'];
				$strseriehomepage=$rstempserie['HOMEPAGE_URL'];
				$strserietitle=$rstempserie['TITLE'];
				$strserietagline=$rstempserie['TAGLINE'];
				$dblserievoteaverage=f_stringtodouble($rstempserie['VOTE_AVERAGE']);
				$strserieoriginallanguage=$rstempserie['ORIGINAL_LANGUAGE'];
				$intserieadult=f_stringtolong($rstempserie['ADULT']);
				$strserieoriginaltitle=$rstempserie['ORIGINAL_TITLE'];
				$strserietimcreditscompleted=$rstempserie['TIM_CREDITS_COMPLETED'];
				$strseriewatchproviderscompleted=trim((string)($rstempserie['TIM_WATCH_PROVIDERS_COMPLETED'] ?? ""));
				$strseriebackdroppath=$rstempserie['BACKDROP_PATH'];
				$dblseriepopularity=f_stringtodouble($rstempserie['POPULARITY']);
				$strseriegenres=$rstempserie['GENRES'];
				$dblvoteaverage=f_stringtodouble($rstempserie['VOTE_AVERAGE']);
				$lngvotecount=f_stringtolong($rstempserie['VOTE_COUNT']);
				$strserieidwikidata=$rstempserie["ID_WIKIDATA"];
				$lngnumberofepisodes=f_stringtolong($rstempserie['NUMBER_OF_EPISODES']);
				$lngnumberofseasons=f_stringtolong($rstempserie['NUMBER_OF_SEASONS']);
				$strserietype=$rstempserie['SERIE_TYPE'];
				if ($strserieidimdb!="") {
					$strserieimdburl="https://www.imdb.com/title/".$strserieidimdb."/";
				}
				$strserietmdburl="https://www.themoviedb.org/tv/".$lngserieid;
				if ($strserieidwikidata!="") {
					$strseriewikidataurl="https://www.wikidata.org/wiki/".$strserieidwikidata;
				}
			}
			f_sqlfreeresult($resultserie);
		}
		if ($struilang!="en") {
			//Read translated serie data from the MySQL database
			$strsqlserie="SELECT * FROM ".$strsqlns."TMDB_SERIE_LANG WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
			$resultserie=f_sqlselect($strsqlserie);
			if ($resultserie) {
				if ($rstempserie=f_sqlresultfetcharray($resultserie)) {
					$strserieoverview=$rstempserie['OVERVIEW'];
					$strserieposterpath=$rstempserie['POSTER_PATH'];
					$strserietitle=$rstempserie['TITLE'];
					$strserietagline=$rstempserie['TAGLINE'];
					$strseriebackdroppath=$rstempserie['BACKDROP_PATH'];
				}
				f_sqlfreeresult($resultserie);
			}
		} else {

		}
		
		if ($strserieidwikidata!="") {
			$strserieinstanceof="";
			//Read serie wikidata data from the MySQL database
			$strsqlserie="SELECT * FROM ".$strsqlns."WIKIDATA_SERIE_V1 WHERE ID_WIKIDATA = ".f_stringtosql($strserieidwikidata)." AND DELETED = 0 ";
			$resultserie=f_sqlselect($strsqlserie);
			if ($resultserie) {
				if ($rstempserie=f_sqlresultfetcharray($resultserie)) {
					//$lngserieidcriterion=f_stringtolong($rstempserie['ID_CRITERION']);
					//$lngserieidcriterionspine=f_stringtolong($rstempserie['ID_CRITERION_SPINE']);
					$strserieinstanceof=trim($rstempserie['INSTANCE_OF']);
					$strseriealiases=trim($rstempserie['ALIASES']);
					//if ($lngserieidcriterion>0) {
					//	$strcriterioncollectionurl="https://www.criterion.com/films/".$lngserieidcriterion;
					//}
				}
				f_sqlfreeresult($resultserie);
			}
			
			$strseriewikidatainstanceof="";
			// Le libelle de la classe se lit en V2, repli V1.
			// La requete remplacee n avait aucun repli vers l anglais : la bascule en ajoute
			// un, donc cette page peut afficher un type la ou elle rendait du vide.
			$strseriewikidatainstanceof=f_getwikidatalabel($strserieinstanceof,$struilang);
			/*
			$strseriewikidatagenres=f_wikidataitemproperties($struilang,$strserieidwikidata,"P136", ", ");
			$strseriewikidatacolors=f_wikidataitemproperties($struilang,$strserieidwikidata,"P462", ", ");
			$strseriewikidatadescbysrc=f_wikidataitemproperties($struilang,$strserieidwikidata,"P1343", ", ");
			$strseriewikidatapartof=f_wikidataitemproperties($struilang,$strserieidwikidata,"P361", ", ");
			*/
			// Le bloc Wikidata est rendu plus bas, une fois connues les lignes "Filmed in" / "Set in"
			// qu'il ne doit pas repeter.
			$strseriewikidataeverything="";
			//P166,P1411
		}
		
		if ($intseriefound) {
			//Serie record found in the database
			//Display serie data
			$strserietitleyear=$strserietitle." (".$strseriefirstairyear."-".$strserielastairyear.")";
			//Social preview: the poster is the visual shown when the page URL is shared
			$arrsocialserie=array();
			$arrsocialserie['description']=$strserieoverview;
			if ($strserieposterpath!="") {
				$arrsocialserie['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strserieposterpath);
				//TMDb posters are 2:3, so the w342 rendition is 342x513
				$arrsocialserie['imagewidth']=342;
				$arrsocialserie['imageheight']=513;
				$arrsocialserie['imagealt']=$strserietitleyear;
			}
			f_headerdisplay($strserietitleyear,"",$arrsocialserie);
			$strhttpreferer="";
			$lngaisearch=true;
			if (isset($_SERVER["HTTP_REFERER"])) {
				$strhttpreferer=$_SERVER["HTTP_REFERER"];
				$lngpos=strpos($strhttpreferer,"/serie-search.php");
				if ($lngpos!==false) {
					//echo "<br />/serie-search.php FOUND";
					$lngaisearch=false;
				} else {
					//echo "<br />/serie-search.php NOT FOUND";
					$lngaisearch=true;
				}
			}
			echo "<h1>";
			echo $strserietitleyear;
			//Hook entity.h1: badges and links added by the plugins after the title
			echo f_pluginhtml("entity.h1",array("type"=>"serie","id"=>$lngserieid,"title"=>$strserietitle,"originaltitle"=>$strserieoriginaltitle,"year"=>$strseriefirstairyear,"imdbid"=>$strserieidimdb));
			echo "</h1>";
			if ($strserietagline!="") {
				echo "<a name=\"tagline\"></a><h2>".$strserietagline."</h2>";
			}
			if ($strserieposterpath!="") {
				echo "<p>";
				echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strserieposterpath."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".$strtmdbimagespathbaseurl.$strserieposterpath."\" alt=\"".$strserietitleyear."\" title=\"".$strserietitleyear."\" style=\"background-color: white;\" />";
				echo "</a>";
				echo "</p>";
			}
			echo "<p>";
			echo "<a href=\"images.php?serieid=".$lngserieid."\">";
			echo get_movie_string('lib_serie_all_images');
			echo "</a>";
			echo "</p>";
			//Link to the YouTube video gallery for this serie
			echo "<p>";
			echo "<a href=\"videos.php?serieid=".$lngserieid."\">";
			echo get_movie_string('lib_serie_all_videos');
			echo "</a>";
			echo "</p>";
			
			f_moviesectionreset(array("info","overview","cast","crew"));
			ob_start();
			echo f_moviesectionopen("info","🪪",get_movie_string('lib_serie_section_tmdb'),1);
			if ($strserieoriginaltitle!=$strserietitle) {
				echo "<p><strong>".get_movie_string('lib_serie_original_title').":</strong> ".$strserieoriginaltitle."</p>";
			}
			
			if ($strseriedatfirstair!="") {
				echo "<p><strong>".get_movie_string('lib_serie_first_air_date').":</strong> ".$strseriedatfirstair."</p>";
			}
			if ($strseriedatlastair!="") {
				echo "<p><strong>".get_movie_string('lib_serie_last_air_date').":</strong> ".$strseriedatlastair."</p>";
			}
			if ($lngnumberofseasons>0) {
				echo "<p><strong>".get_movie_string('lib_serie_number_of_seasons').":</strong> ".$lngnumberofseasons."</p>";
			}
			if ($lngnumberofepisodes>0) {
				echo "<p><strong>".get_movie_string('lib_serie_number_of_episodes').":</strong> ".$lngnumberofepisodes."</p>";
			}
			//Each season's IMDb rating is the rollup written by tmdb-movie-preprocess Process 28,
			//read here exactly as the API's `seasons` array reads it: the
			//TMDb row stays the source and the T2S row is LEFT JOINed beside it, so a season with
			//no rollup yet still appears in the list, simply without a rating. See the season page
			//for why a derived figure is mirrored rather than recomputed.
			$strsqlserieseasons="SELECT s.ID_SEASON, s.SEASON_NUMBER, s.TITLE, s.DAT_AIR, s.EPISODE_COUNT, ";
			$strsqlserieseasons.="t2s.IMDB_RATING, ";
			$strsqlserieseasons.="(SELECT COUNT(*) FROM ".$strsqlns."T2S_EPISODE e ";
			$strsqlserieseasons.="WHERE e.ID_SEASON = s.ID_SEASON AND e.IMDB_RATING IS NOT NULL) AS IMDB_RATED_EPISODES ";
			$strsqlserieseasons.="FROM ".$strsqlns."TMDB_SEASON s ";
			$strsqlserieseasons.="LEFT JOIN ".$strsqlns."T2S_SEASON t2s ON t2s.ID_SEASON = s.ID_SEASON ";
			$strsqlserieseasons.="WHERE s.ID_SERIE = ".f_longtosql($lngserieid)." AND s.DELETED = 0 ";
			$strsqlserieseasons.="ORDER BY s.SEASON_NUMBER, s.DISPLAY_ORDER, s.ID_SEASON ";
			$strserieseasons="";
			$resultserieseasons=f_sqlselect($strsqlserieseasons);
			if ($resultserieseasons) {
				while ($rstempseason=f_sqlresultfetcharray($resultserieseasons)) {
					$lngseasonid=f_stringtolong($rstempseason['ID_SEASON']);
					$lngseasonnumber=f_stringtolong($rstempseason['SEASON_NUMBER']);
					$strseasontitle=$rstempseason['TITLE'];
					$strseasondatair=$rstempseason['DAT_AIR'];
					$lngseasonepisodecount=f_stringtolong($rstempseason['EPISODE_COUNT']);
					if ($struilang!="en") {
						$strsqlseasonlang="SELECT TITLE FROM ".$strsqlns."TMDB_SEASON_LANG WHERE ID_SEASON = ".f_longtosql($lngseasonid)." AND ID_SERIE = ".f_longtosql($lngserieid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
						$strseasontitlelang=trim(f_fieldfromquery($strsqlseasonlang));
						if ($strseasontitlelang!="") {
							$strseasontitle=$strseasontitlelang;
						}
					}
					$strseasonlabel=get_movie_string('lib_season_season')." ".$lngseasonnumber;
					if ($strseasontitle!="") {
						$strseasonlabel.=" - ".$strseasontitle;
					}
					if ($strseasondatair!="" || $lngseasonepisodecount>0) {
						$strseasonlabel.=" (";
						if ($strseasondatair!="") {
							$strseasonlabel.=$strseasondatair;
						}
						if ($lngseasonepisodecount>0) {
							if ($strseasondatair!="") {
								$strseasonlabel.=", ";
							}
							$strseasonlabel.=$lngseasonepisodecount." ".strtolower(get_movie_string('lib_season_total_episode_count'));
						}
						$strseasonlabel.=')';
					}
					if ($strserieseasons!="") {
						$strserieseasons.="<br />";
					}
					$strserieseasons.="<a href=\"season.php?serieid=".$lngserieid."&season=".$lngseasonnumber."\">".$strseasonlabel."</a>";
					//Outside the link: the rating belongs to the season, not to the act of opening it
					$strseasonimdbrating=f_imdbratingdisplay($rstempseason['IMDB_RATING'],0,$rstempseason['IMDB_RATED_EPISODES']);
					if ($strseasonimdbrating!="") {
						$strserieseasons.=" <strong>".get_movie_string('lib_serie_imdb_rating').":</strong> ".$strseasonimdbrating;
					}
				}
				f_sqlfreeresult($resultserieseasons);
			}
			if ($strserieseasons!="") {
				echo "<p><strong>".get_movie_string('lib_serie_seasons').":</strong><br />".$strserieseasons."</p>";
			}
			echo "<p><strong>".get_movie_string('lib_serie_type').":</strong> ";
			if ($strserietype!="") {
				echo $strserietype;
			}
			echo "</p>";
			
			echo "<p><strong>".get_movie_string('lib_serie_popularity').":</strong> ".$dblseriepopularity."</p>";
			//echo "<p><strong>".get_movie_string('lib_serie_votecount').":</strong> ".$lngvotecount."</p>";
			//echo "<p><strong>".get_movie_string('lib_serie_vote_average').":</strong> ".$dblvoteaverage."</p>";
			if ($strserieidimdb!="") {
				//The vote count now rides inside the value, "8.5 (1543210 votes)", the same way the
				//episode and season pages carry what their figure rests on. One query for both
				//fields rather than two f_descfromcode() lookups on the same row.
				$dblimdbrating=0;
				$lngimdbvotes=0;
				$strsqlserieimdb="SELECT averageRating, numVotes FROM ".$strsqlns."IMDB_MOVIE_RATING_IMPORT WHERE tconst = ".f_stringtosql($strserieidimdb);
				$resultserieimdb=f_sqlselect($strsqlserieimdb);
				if ($resultserieimdb) {
					if ($rstempserieimdb=f_sqlresultfetcharray($resultserieimdb)) {
						$dblimdbrating=f_stringtodouble((string)$rstempserieimdb['averageRating']);
						$lngimdbvotes=f_stringtolong((string)$rstempserieimdb['numVotes']);
					}
					f_sqlfreeresult($resultserieimdb);
				}
				$strserieimdbrating=f_imdbratingdisplay($dblimdbrating,$lngimdbvotes);
				if ($strserieimdbrating!="") {
					echo "<p><strong>".get_movie_string('lib_serie_imdb_rating').":</strong> ".$strserieimdbrating."</p>";
				}
				f_omdbdisplay($strserieidimdb);
				f_workfinancedisplay($lngserieid,"serie");
			}
			if ($strseriegenres!="" && $strseriegenres!="|") {
				switch ($struilang) {
				case "fr":
					$strseriegenres=f_tmdbgenrestranslatefr($strseriegenres);
					break;
				}
				if (substr($strseriegenres,0,1)=="|") {
					$strseriegenres=substr($strseriegenres,1);
				}
				if (substr($strseriegenres,strlen($strseriegenres)-1,1)=="|") {
					$strseriegenres=substr($strseriegenres,0,strlen($strseriegenres)-1);
				}
				$strseriegenresdisp=str_replace("|",", ",$strseriegenres);
			} else {
				$strseriegenresdisp="";
			}
			echo "<p><strong>".get_movie_string('lib_serie_genres').":</strong> ".$strseriegenresdisp."</p>";
			
			if ($intserieadult) {
				$strserieadult=get_movie_string('lib_yes');
			} else {
				$strserieadult=get_movie_string('lib_no');
			}
			echo "<p><strong>".get_movie_string('lib_serie_is_adult').":</strong> ".$strserieadult;
			echo "</p>";
			
			$strpropertyurl="property.php?propertyid=P31";
			
			$stroriglangurl="origlang.php?origlang=".$strserieoriginallanguage;
			echo "<p><strong>".get_movie_string('lib_serie_original_language').":</strong> ";
			//echo "<a href=\"".$stroriglangurl."\">";
			echo f_tmdboriginallanguagetranslate($strserieoriginallanguage,$struilang);
			//echo "</a>";
			echo "</p>";
			
			// Display spoken languages
			$strsqlserielanguages = "SELECT T_WC_TMDB_SERIE_SPOKEN_LANGUAGE.SPOKEN_LANGUAGE ";
			$strsqlserielanguages .= "FROM T_WC_TMDB_SERIE_SPOKEN_LANGUAGE ";
			$strsqlserielanguages .= "WHERE T_WC_TMDB_SERIE_SPOKEN_LANGUAGE.ID_SERIE = " . f_longtosql($lngserieid) . " AND T_WC_TMDB_SERIE_SPOKEN_LANGUAGE.DELETED = 0 ";
			$strsqlserielanguages .= "ORDER BY T_WC_TMDB_SERIE_SPOKEN_LANGUAGE.DISPLAY_ORDER ";

			$strserielanguages = "";
			if ($intdebug) {
				echo "<br />" . $strsqlserielanguages;
			}
			$resultserielanguages = f_sqlselect($strsqlserielanguages);
			if ($resultserielanguages) {
				while ($rstempserielanguages = f_sqlresultfetcharray($resultserielanguages)) {
					$strserielanguagecode = $rstempserielanguages["SPOKEN_LANGUAGE"];
					$strserielanguage = f_tmdboriginallanguagetranslate($strserielanguagecode, $struilang);
					$strserielanguageurl = "origlang.php?origlang=" . $strserielanguagecode;
					
					if ($strserielanguages != "") {
						$strserielanguages .= ", ";
					}
					//$strserielanguages .= "<a href=\"" . $strserielanguageurl . "\">";
					$strserielanguages .= $strserielanguage;
					//$strserielanguages .= "</a>";
				}
				f_sqlfreeresult($resultserielanguages);
			}
			echo "<p><strong>" . get_movie_string('lib_serie_spoken_languages') . ":</strong> " . $strserielanguages . "</p>";
			
			if ($struilang!="en") {
				$strkeywordstable=$strsqlns."TMDB_KEYWORD_LANG";
			} else {
				$strkeywordstable=$strsqlns."TMDB_KEYWORD";
			}
			$strsqlseriekeywords="SELECT ".$strkeywordstable.".ID_KEYWORD, ".$strkeywordstable.".NAME ";
			$strsqlseriekeywords.="FROM T_WC_TMDB_SERIE_KEYWORD INNER JOIN ".$strkeywordstable." ON T_WC_TMDB_SERIE_KEYWORD.ID_KEYWORD = ".$strkeywordstable.".ID_KEYWORD WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND ".$strkeywordstable.".DELETED = 0 ";
			if ($struilang!="en") {
				$strsqlseriekeywords.=" AND ".$strkeywordstable.".LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlseriekeywords.="ORDER BY T_WC_TMDB_SERIE_KEYWORD.DISPLAY_ORDER ";
			$strseriekeywords="";
			$resultseriekeywords=f_sqlselect($strsqlseriekeywords);
			if ($resultseriekeywords) {
				while ($rstempseriekeywords=f_sqlresultfetcharray($resultseriekeywords)) {
					$strseriekeywordid=$rstempseriekeywords["ID_KEYWORD"];
					$strseriekeyword=$rstempseriekeywords["NAME"];
					$strseriekeywordurl="keyword.php?keywordid=".$strseriekeywordid;
					if ($strseriekeywords!="") {
						$strseriekeywords.=", ";
					}
					$strseriekeywords.="<a href=\"".$strseriekeywordurl."\">".$strseriekeyword."</a>";
				}
				f_sqlfreeresult($resultseriekeywords);
			}
			echo "<p><strong>".get_movie_string('lib_serie_keywords').":</strong> ".$strseriekeywords."</p>";
			
			//Handling lists
			$strsqlserielists="SELECT ".$strsqlns."TMDB_LIST.ID_LIST, ".$strsqlns."TMDB_LIST.NAME, ".$strsqlns."TMDB_LIST.SHORT_NAME ";
			/*
			if ($struilang!="en") {
				$strsqlserielists.=", ".$strsqlns."TMDB_LIST_LANG.SHORT_NAME AS SHORT_NAME_LANG, ".$strsqlns."TMDB_LIST_LANG.LANG ";
			}
			*/
			$strsqlserielists.="FROM ".$strsqlns."TMDB_LIST ";
			$strsqlserielists.="INNER JOIN ".$strsqlns."TMDB_SERIE_LIST ON ".$strsqlns."TMDB_SERIE_LIST.ID_LIST = ".$strsqlns."TMDB_LIST.ID_LIST ";
			/*
			if ($struilang!="en") {
				$strsqlserielists.="LEFT JOIN ".$strsqlns."TMDB_LIST_LANG ON ".$strsqlns."TMDB_LIST.ID_LIST = ".$strsqlns."TMDB_LIST_LANG.ID_LIST ";
			}
			*/
			$strsqlserielists.="WHERE ".$strsqlns."TMDB_SERIE_LIST.ID_SERIE = ".f_longtosql($lngserieid)." AND ".$strsqlns."TMDB_LIST.DELETED = 0 ";
			$strsqlserielists.="AND ".$strsqlns."TMDB_LIST.USE_FOR_TAGGING >= 1 ";
			/*
			//Disabling the next condition because the list may not have a record for the required non en language
			if ($struilang!="en") {
				$strsqlserielists.="AND ".$strsqlns."TMDB_LIST_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			*/
			//$strsqlserielists.="ORDER BY DISPLAY_ORDER";
			if ($intdebug) {
				echo "<br />\$strsqlserielists=".$strsqlserielists;
			}
			$strserielists="";
			$resultserielists=f_sqlselect($strsqlserielists);
			if ($resultserielists) {
				while ($rstempserielists=f_sqlresultfetcharray($resultserielists)) {
					$strserielistid=$rstempserielists["ID_LIST"];
					$strserielistname=trim($rstempserielists["NAME"]);
					$strserielistshortname=trim($rstempserielists["SHORT_NAME"]);
					//$strserielistshortnamelang=trim($rstempserielists["SHORT_NAME_LANG"]);
					if ($strserielistshortname!="") {
						$strserielistname=$strserielistshortname;
					}
					if ($struilang!="en") {
						//Is there a short name for the current language? 
						$strsql="SELECT SHORT_NAME FROM ".$strsqlns."TMDB_LIST_LANG WHERE DELETED = 0 AND ID_LIST = ".f_longtosql($strserielistid)." AND LANG = ".f_stringtosql($struilang);
						$strserielistshortnamelang=trim(f_fieldfromquery($strsql));
						if ($strserielistshortnamelang!="") {
							$strserielistname=$strserielistshortnamelang;
						}
					}
					$strserielisturl="list.php?listid=".$strserielistid;
					//if ($strserielists!="") {
						$strserielists.="<br />";
					//}
					$strserielists.="<a href=\"".$strserielisturl."\">".$strserielistname."</a>";
				}
				f_sqlfreeresult($resultserielists);
			}
			echo "<p><strong>".get_movie_string('lib_serie_lists').":</strong> ".$strserielists."</p>";

			// Link to the T2S collection(s) this series belongs to (t2scollection.php),
			// mirroring movie.php. A T2S collection is cross-type (films + series, e.g. Star Trek).
			$strt2scollections="";
			$strsqlt2scollections="SELECT T_WC_T2S_COLLECTION.ID_T2S_COLLECTION, T_WC_T2S_COLLECTION.COLLECTION_NAME, T_WC_T2S_COLLECTION.COLLECTION_NAME_FR ";
			$strsqlt2scollections.="FROM T_WC_T2S_SERIE_COLLECTION ";
			$strsqlt2scollections.="INNER JOIN T_WC_T2S_COLLECTION ON T_WC_T2S_SERIE_COLLECTION.ID_T2S_COLLECTION = T_WC_T2S_COLLECTION.ID_T2S_COLLECTION ";
			$strsqlt2scollections.="WHERE T_WC_T2S_SERIE_COLLECTION.ID_SERIE = ".f_longtosql($lngserieid)." AND T_WC_T2S_SERIE_COLLECTION.DELETED = 0 AND T_WC_T2S_COLLECTION.DELETED = 0 ";
			$strsqlt2scollections.="ORDER BY T_WC_T2S_SERIE_COLLECTION.DISPLAY_ORDER, T_WC_T2S_COLLECTION.COLLECTION_NAME ";
			$resultt2scollections=f_sqlselect($strsqlt2scollections);
			if ($resultt2scollections) {
				while ($rstempcoll=f_sqlresultfetcharray($resultt2scollections)) {
					$strt2scollectionid=$rstempcoll["ID_T2S_COLLECTION"];
					$strt2scollectionname=trim($rstempcoll[($struilang!="en" && trim((string)$rstempcoll["COLLECTION_NAME_FR"])!="")?"COLLECTION_NAME_FR":"COLLECTION_NAME"]);
					if ($strt2scollectionname=="") {
						continue;
					}
					if ($strt2scollections!="") {
						$strt2scollections.=", ";
					}
					$strt2scollections.="<a href=\"t2scollection.php?collectionid=".$strt2scollectionid."\">".$strt2scollectionname."</a>";
				}
				f_sqlfreeresult($resultt2scollections);
			}
			if ($strt2scollections!="") {
				echo "<p><strong>".get_movie_string('lib_movie_collections').":</strong> ".$strt2scollections."</p>";
			}

			$strt2slists="";
			$strsqlt2slists="SELECT T_WC_T2S_LIST.ID_T2S_LIST, T_WC_T2S_LIST.LIST_NAME, T_WC_T2S_LIST.LIST_NAME_FR ";
			$strsqlt2slists.="FROM T_WC_T2S_SERIE_LIST ";
			$strsqlt2slists.="INNER JOIN T_WC_T2S_LIST ON T_WC_T2S_SERIE_LIST.ID_T2S_LIST = T_WC_T2S_LIST.ID_T2S_LIST ";
			$strsqlt2slists.="WHERE T_WC_T2S_SERIE_LIST.ID_SERIE = ".f_longtosql($lngserieid)." AND T_WC_T2S_SERIE_LIST.DELETED = 0 AND T_WC_T2S_LIST.DELETED = 0 ";
			$strsqlt2slists.="ORDER BY T_WC_T2S_SERIE_LIST.DISPLAY_ORDER, T_WC_T2S_LIST.LIST_NAME ";
			$resultt2slists=f_sqlselect($strsqlt2slists);
			if ($resultt2slists) {
				while ($rstemplist=f_sqlresultfetcharray($resultt2slists)) {
					$strt2slistid=$rstemplist["ID_T2S_LIST"];
					$strt2slistname=trim($rstemplist[($struilang!="en" && trim((string)$rstemplist["LIST_NAME_FR"])!="")?"LIST_NAME_FR":"LIST_NAME"]);
					if ($strt2slistname=="") {
						continue;
					}
					if ($strt2slists!="") {
						$strt2slists.=", ";
					}
					$strt2slists.="<a href=\"t2slist.php?listid=".$strt2slistid."\">".$strt2slistname."</a>";
				}
				f_sqlfreeresult($resultt2slists);
			}
			if ($strt2slists!="") {
				echo "<p><strong>".get_movie_string('lib_serie_lists').":</strong> ".$strt2slists."</p>";
			}

			$strt2smovements="";
			$strsqlt2smovements="SELECT T_WC_T2S_MOVEMENT.ID_MOVEMENT, T_WC_T2S_MOVEMENT.MOVEMENT_NAME, T_WC_T2S_MOVEMENT.MOVEMENT_NAME_FR ";
			$strsqlt2smovements.="FROM T_WC_T2S_SERIE_MOVEMENT ";
			$strsqlt2smovements.="INNER JOIN T_WC_T2S_MOVEMENT ON T_WC_T2S_SERIE_MOVEMENT.ID_MOVEMENT = T_WC_T2S_MOVEMENT.ID_MOVEMENT ";
			$strsqlt2smovements.="WHERE T_WC_T2S_SERIE_MOVEMENT.ID_SERIE = ".f_longtosql($lngserieid)." AND T_WC_T2S_SERIE_MOVEMENT.DELETED = 0 AND T_WC_T2S_MOVEMENT.DELETED = 0 ";
			$strsqlt2smovements.="ORDER BY T_WC_T2S_SERIE_MOVEMENT.DISPLAY_ORDER, T_WC_T2S_MOVEMENT.MOVEMENT_NAME ";
			$resultt2smovements=f_sqlselect($strsqlt2smovements);
			if ($resultt2smovements) {
				while ($rstempmovement=f_sqlresultfetcharray($resultt2smovements)) {
					$strt2smovementid=$rstempmovement["ID_MOVEMENT"];
					$strt2smovementname=trim($rstempmovement[($struilang!="en" && trim((string)$rstempmovement["MOVEMENT_NAME_FR"])!="")?"MOVEMENT_NAME_FR":"MOVEMENT_NAME"]);
					if ($strt2smovementname=="") {
						continue;
					}
					if ($strt2smovements!="") {
						$strt2smovements.=", ";
					}
					$strt2smovements.="<a href=\"movement.php?movementid=".$strt2smovementid."\">".$strt2smovementname."</a>";
				}
				f_sqlfreeresult($resultt2smovements);
			}
			if ($strt2smovements!="") {
				echo "<p><strong>".get_movie_string('lib_serie_movements').":</strong> ".$strt2smovements."</p>";
			}

			$strt2stopics="";
			$strsqlt2stopics="SELECT T_WC_T2S_TOPIC.ID_TOPIC, T_WC_T2S_TOPIC.TOPIC_NAME, T_WC_T2S_TOPIC.TOPIC_NAME_FR, T_WC_T2S_TOPIC.TOPIC_SOURCE, T_WC_T2S_TOPIC.ID_RECORD ";
			$strsqlt2stopics.="FROM T_WC_T2S_SERIE_TOPIC ";
			$strsqlt2stopics.="INNER JOIN T_WC_T2S_TOPIC ON T_WC_T2S_SERIE_TOPIC.ID_TOPIC = T_WC_T2S_TOPIC.ID_TOPIC ";
			$strsqlt2stopics.="WHERE T_WC_T2S_SERIE_TOPIC.ID_SERIE = ".f_longtosql($lngserieid)." AND T_WC_T2S_SERIE_TOPIC.DELETED = 0 AND T_WC_T2S_TOPIC.DELETED = 0 ";
			$strsqlt2stopics.="ORDER BY T_WC_T2S_SERIE_TOPIC.DISPLAY_ORDER, T_WC_T2S_TOPIC.TOPIC_NAME ";
			$resultt2stopics=f_sqlselect($strsqlt2stopics);
			if ($resultt2stopics) {
				while ($rstemptopic=f_sqlresultfetcharray($resultt2stopics)) {
					$lngtopicid=f_stringtolong($rstemptopic["ID_TOPIC"]);
					$strt2stopicname=trim($rstemptopic[($struilang!="en" && trim((string)$rstemptopic["TOPIC_NAME_FR"])!="")?"TOPIC_NAME_FR":"TOPIC_NAME"]);
					$strt2stopicurl=($lngtopicid>0?"t2stopic.php?topicid=".$lngtopicid:"");
					if ($strt2stopicname=="") {
						continue;
					}
					if ($strt2stopics!="") {
						$strt2stopics.=", ";
					}
					if ($strt2stopicurl!="") {
						$strt2stopics.="<a href=\"".$strt2stopicurl."\">".$strt2stopicname."</a>";
					} else {
						$strt2stopics.=$strt2stopicname;
					}
				}
				f_sqlfreeresult($resultt2stopics);
			}
			if ($strt2stopics!="") {
				echo "<p><strong>".get_movie_string('lib_serie_topics').":</strong> ".$strt2stopics."</p>";
			}

			$strt2sawards="";
			// One link row per win, so an award won several times is grouped into one line.
			$strsqlt2sawards="SELECT T_WC_T2S_AWARD.ID_AWARD, T_WC_T2S_AWARD.AWARD_NAME, T_WC_T2S_AWARD.AWARD_NAME_FR, MIN(T_WC_T2S_SERIE_AWARD.DISPLAY_ORDER) AS MIN_DISPLAY_ORDER ";
			$strsqlt2sawards.="FROM T_WC_T2S_SERIE_AWARD ";
			$strsqlt2sawards.="INNER JOIN T_WC_T2S_AWARD ON T_WC_T2S_SERIE_AWARD.ID_AWARD = T_WC_T2S_AWARD.ID_AWARD ";
			$strsqlt2sawards.="WHERE T_WC_T2S_SERIE_AWARD.ID_SERIE = ".f_longtosql($lngserieid)." AND T_WC_T2S_SERIE_AWARD.DELETED = 0 AND T_WC_T2S_AWARD.DELETED = 0 ";
			$strsqlt2sawards.="GROUP BY T_WC_T2S_AWARD.ID_AWARD, T_WC_T2S_AWARD.AWARD_NAME, T_WC_T2S_AWARD.AWARD_NAME_FR ";
			$strsqlt2sawards.="ORDER BY MIN_DISPLAY_ORDER, T_WC_T2S_AWARD.AWARD_NAME ";
			$resultt2sawards=f_sqlselect($strsqlt2sawards);
			if ($resultt2sawards) {
				while ($rstempaward=f_sqlresultfetcharray($resultt2sawards)) {
					$strt2sawardid=$rstempaward["ID_AWARD"];
					$strt2sawardname=trim($rstempaward[($struilang!="en" && trim((string)$rstempaward["AWARD_NAME_FR"])!="")?"AWARD_NAME_FR":"AWARD_NAME"]);
					if ($strt2sawardname=="") {
						continue;
					}
					if ($strt2sawards!="") {
						$strt2sawards.=", ";
					}
					$strt2sawards.="<a href=\"award.php?awardid=".$strt2sawardid."\">".$strt2sawardname."</a>";
				}
				f_sqlfreeresult($resultt2sawards);
			}
			if ($strt2sawards!="") {
				echo "<p><strong>".get_movie_string('lib_serie_awards').":</strong> ".$strt2sawards."</p>";
			}

			$strt2snominations="";
			$strsqlt2snominations="SELECT T_WC_T2S_NOMINATION.ID_NOMINATION, T_WC_T2S_NOMINATION.NOMINATION_NAME, T_WC_T2S_NOMINATION.NOMINATION_NAME_FR ";
			$strsqlt2snominations.="FROM T_WC_T2S_SERIE_NOMINATION ";
			$strsqlt2snominations.="INNER JOIN T_WC_T2S_NOMINATION ON T_WC_T2S_SERIE_NOMINATION.ID_NOMINATION = T_WC_T2S_NOMINATION.ID_NOMINATION ";
			$strsqlt2snominations.="WHERE T_WC_T2S_SERIE_NOMINATION.ID_SERIE = ".f_longtosql($lngserieid)." AND T_WC_T2S_SERIE_NOMINATION.DELETED = 0 AND T_WC_T2S_NOMINATION.DELETED = 0 ";
			$strsqlt2snominations.="ORDER BY T_WC_T2S_SERIE_NOMINATION.DISPLAY_ORDER, T_WC_T2S_NOMINATION.NOMINATION_NAME ";
			$resultt2snominations=f_sqlselect($strsqlt2snominations);
			if ($resultt2snominations) {
				while ($rstempnomination=f_sqlresultfetcharray($resultt2snominations)) {
					$strt2snominationid=$rstempnomination["ID_NOMINATION"];
					$strt2snominationname=trim($rstempnomination[($struilang!="en" && trim((string)$rstempnomination["NOMINATION_NAME_FR"])!="")?"NOMINATION_NAME_FR":"NOMINATION_NAME"]);
					if ($strt2snominationname=="") {
						continue;
					}
					if ($strt2snominations!="") {
						$strt2snominations.=", ";
					}
					$strt2snominations.="<a href=\"nomination.php?nominationid=".$strt2snominationid."\">".$strt2snominationname."</a>";
				}
				f_sqlfreeresult($resultt2snominations);
			}
			if ($strt2snominations!="") {
				echo "<p><strong>".get_movie_string('lib_serie_nominations').":</strong> ".$strt2snominations."</p>";
			}
			// Le lien inverse vers les lieux. LOCATION_ROLE est porte par la
			// jointure et non par le lieu : Paris reste le meme Paris qu on y ait tourne ou que
			// l action s y deroule. Les deux listes restent donc separees, les fusionner ferait
			// passer un decor de tournage pour un lieu de recit.
			$arrt2slocations=array('filming' => "", 'narrative' => "");
			$strsqlt2slocations="SELECT T_WC_T2S_SERIE_LOCATION.LOCATION_ROLE, T_WC_T2S_LOCATION.ID_LOCATION, T_WC_T2S_LOCATION.LOCATION_NAME ";
			$strsqlt2slocations.="FROM T_WC_T2S_SERIE_LOCATION ";
			$strsqlt2slocations.="INNER JOIN T_WC_T2S_LOCATION ON T_WC_T2S_SERIE_LOCATION.ID_LOCATION = T_WC_T2S_LOCATION.ID_LOCATION ";
			$strsqlt2slocations.="WHERE T_WC_T2S_SERIE_LOCATION.ID_SERIE = ".f_longtosql($lngserieid)." AND COALESCE(T_WC_T2S_SERIE_LOCATION.DELETED,0) = 0 AND COALESCE(T_WC_T2S_LOCATION.DELETED,0) = 0 ";
			$strsqlt2slocations.="ORDER BY T_WC_T2S_SERIE_LOCATION.DISPLAY_ORDER, T_WC_T2S_LOCATION.LOCATION_NAME ";
			$resultt2slocations=f_sqlselect($strsqlt2slocations);
			if ($resultt2slocations) {
				while ($rstemplocation=f_sqlresultfetcharray($resultt2slocations)) {
					$strt2slocationrole=trim((string)$rstemplocation["LOCATION_ROLE"]);
					if (!isset($arrt2slocations[$strt2slocationrole])) {
						continue;
					}
					$lngt2slocationid=f_stringtolong($rstemplocation["ID_LOCATION"]);
					// LOCATION_NAME seul, sans bascule sur $struilang, contrairement aux sections
					// soeurs : AGENTS.md acte que LOCATION_NAME_FR n est lu nulle part tant que
					// Le pretraitement n a pas corrige des libelles francais qui sont des
					// codes de reseau, la gare du Nord y etant nommee Paris-Nord. La fiche lieu suit
					// la meme regle : le jour ou elle bascule, ce lien inverse bascule avec elle.
					$strt2slocationname=trim((string)$rstemplocation["LOCATION_NAME"]);
					// Au 2026-09-14, 226 lieux vivants n ont aucun libelle anglais et leur fiche
					// s ouvrirait sans titre : un lieu qu on ne sait pas nommer n est pas liable.
					if ($strt2slocationname=="" || $lngt2slocationid<=0) {
						continue;
					}
					if ($arrt2slocations[$strt2slocationrole]!="") {
						$arrt2slocations[$strt2slocationrole].=", ";
					}
					$arrt2slocations[$strt2slocationrole].="<a href=\"location.php?locationid=".$lngt2slocationid."\">".f_htmlspecialchars($strt2slocationname)."</a>";
				}
				f_sqlfreeresult($resultt2slocations);
			}
			if ($arrt2slocations['filming']!="") {
				echo "<p><strong>".get_movie_string('lib_serie_locations_filming').":</strong> ".$arrt2slocations['filming']."</p>";
			}
			if ($arrt2slocations['narrative']!="") {
				echo "<p><strong>".get_movie_string('lib_serie_locations_narrative').":</strong> ".$arrt2slocations['narrative']."</p>";
			}
			// Le lien inverse vers les oeuvres sources, "D'apres". Une serie peut
			// en avoir plusieurs. DELETED filtre sur la jointure et sur l'oeuvre, comme pour les lieux.
			$strt2ssourceworks="";
			$strsqlt2ssourceworks="SELECT T_WC_T2S_SOURCE_WORK.ID_SOURCE_WORK, T_WC_T2S_SOURCE_WORK.SOURCE_WORK_NAME, T_WC_T2S_SOURCE_WORK.SOURCE_WORK_NAME_FR, T_WC_T2S_SOURCE_WORK.ID_MOVIE, T_WC_T2S_SOURCE_WORK.ID_SERIE ";
			$strsqlt2ssourceworks.="FROM T_WC_T2S_SERIE_SOURCE_WORK ";
			$strsqlt2ssourceworks.="INNER JOIN T_WC_T2S_SOURCE_WORK ON T_WC_T2S_SERIE_SOURCE_WORK.ID_SOURCE_WORK = T_WC_T2S_SOURCE_WORK.ID_SOURCE_WORK ";
			$strsqlt2ssourceworks.="WHERE T_WC_T2S_SERIE_SOURCE_WORK.ID_SERIE = ".f_longtosql($lngserieid)." AND COALESCE(T_WC_T2S_SERIE_SOURCE_WORK.DELETED,0) = 0 AND COALESCE(T_WC_T2S_SOURCE_WORK.DELETED,0) = 0 ";
			$strsqlt2ssourceworks.="ORDER BY T_WC_T2S_SERIE_SOURCE_WORK.DISPLAY_ORDER, T_WC_T2S_SOURCE_WORK.SOURCE_WORK_YEAR, T_WC_T2S_SOURCE_WORK.SOURCE_WORK_NAME ";
			$resultt2ssourceworks=f_sqlselect($strsqlt2ssourceworks);
			if ($resultt2ssourceworks) {
				while ($rstempsourcework=f_sqlresultfetcharray($resultt2ssourceworks)) {
					$lngt2ssourceworkid=f_stringtolong($rstempsourcework["ID_SOURCE_WORK"]);
					// Le nom suit $struilang, comme la fiche sourcework.php vers laquelle il pointe.
					$strt2ssourceworkname=trim((string)$rstempsourcework[($struilang!="en" && trim((string)$rstempsourcework["SOURCE_WORK_NAME_FR"])!="")?"SOURCE_WORK_NAME_FR":"SOURCE_WORK_NAME"]);
					if ($strt2ssourceworkname=="") {
						$strt2ssourceworkname=trim((string)$rstempsourcework["SOURCE_WORK_NAME_FR"]);
					}
					if ($strt2ssourceworkname=="" || $lngt2ssourceworkid<=0) {
						continue;
					}
					if ($strt2ssourceworks!="") {
						$strt2ssourceworks.=", ";
					}
					// Quand l'oeuvre source EST un film ou une serie T2S, on pointe droit sur sa fiche
					// riche ; sinon sur la fiche sourcework.php.
					$lngt2ssourceworkmovieid=f_stringtolong($rstempsourcework["ID_MOVIE"] ?? 0);
					$lngt2ssourceworkserieid=f_stringtolong($rstempsourcework["ID_SERIE"] ?? 0);
					if ($lngt2ssourceworkmovieid>0) {
						$strt2ssourceworkhref="movie.php?movieid=".$lngt2ssourceworkmovieid;
					} elseif ($lngt2ssourceworkserieid>0) {
						$strt2ssourceworkhref="serie.php?serieid=".$lngt2ssourceworkserieid;
					} else {
						$strt2ssourceworkhref="sourcework.php?sourceworkid=".$lngt2ssourceworkid;
					}
					$strt2ssourceworks.="<a href=\"".$strt2ssourceworkhref."\">".f_htmlspecialchars($strt2ssourceworkname)."</a>";
				}
				f_sqlfreeresult($resultt2ssourceworks);
			}
			if ($strt2ssourceworks!="") {
				echo "<p><strong>".get_movie_string('lib_serie_based_on').":</strong> ".$strt2ssourceworks."</p>";
			}

			
			
			$strsqlseriecompanies="SELECT T_WC_TMDB_COMPANY.ID_COMPANY, T_WC_TMDB_COMPANY.NAME ";
			$strsqlseriecompanies.="FROM T_WC_TMDB_SERIE_COMPANY ";
			$strsqlseriecompanies.="INNER JOIN T_WC_TMDB_COMPANY ON T_WC_TMDB_SERIE_COMPANY.ID_COMPANY = T_WC_TMDB_COMPANY.ID_COMPANY ";
			$strsqlseriecompanies.="WHERE T_WC_TMDB_SERIE_COMPANY.ID_SERIE = ".f_longtosql($lngserieid)." AND T_WC_TMDB_SERIE_COMPANY.DELETED = 0 AND T_WC_TMDB_COMPANY.DELETED = 0 ";
			$strsqlseriecompanies.="ORDER BY T_WC_TMDB_SERIE_COMPANY.DISPLAY_ORDER ";
			$strseriecompanies="";
			if ($intdebug) {
				echo "<br />".$strsqlseriecompanies;
			}
			$resultseriecompanies=f_sqlselect($strsqlseriecompanies);
			if ($resultseriecompanies) {
				while ($rstempseriecompanies=f_sqlresultfetcharray($resultseriecompanies)) {
					$strseriecompanyid=$rstempseriecompanies["ID_COMPANY"];
					$strseriecompany=$rstempseriecompanies["NAME"];
					$strseriecompanyurl="company.php?companyid=".$strseriecompanyid;
					if ($strseriecompanies!="") {
						$strseriecompanies.=", ";
					}
					$strseriecompanies.="<a href=\"".$strseriecompanyurl."\">".$strseriecompany."</a>";
				}
				f_sqlfreeresult($resultseriecompanies);
			}
			echo "<p><strong>".get_movie_string('lib_serie_companies').":</strong> ".$strseriecompanies."</p>";

			// Display countries
			$strsqlseriecountries = "SELECT T_WC_TMDB_SERIE_COUNTRY.COUNTRY_CODE ";
			$strsqlseriecountries .= "FROM T_WC_TMDB_SERIE_COUNTRY ";
			$strsqlseriecountries .= "WHERE T_WC_TMDB_SERIE_COUNTRY.ID_SERIE = " . f_longtosql($lngserieid) . " AND T_WC_TMDB_SERIE_COUNTRY.DELETED = 0 ";
			$strsqlseriecountries .= "ORDER BY T_WC_TMDB_SERIE_COUNTRY.ID_ROW ";

			$strseriecountries = "";
			if ($intdebug) {
				echo "<br />" . $strsqlseriecountries;
			}
			$resultseriecountries = f_sqlselect($strsqlseriecountries);
			if ($resultseriecountries) {
				while ($rstempseriecountries = f_sqlresultfetcharray($resultseriecountries)) {
					$strseriecountrycode = $rstempseriecountries["COUNTRY_CODE"];
					$strseriecountry = f_countrycodetranslatefrommap($strseriecountrycode, $arrwatchcountryoptions, $struilang);
					$strseriecountryurl = "country.php?country=" . $strseriecountrycode;
					
					if ($strseriecountries != "") {
						$strseriecountries .= ", ";
					}
					$strseriecountries .= "<a href=\"" . $strseriecountryurl . "\">" . $strseriecountry . "</a>";
				}
				f_sqlfreeresult($resultseriecountries);
			}
			echo "<p><strong>" . get_movie_string('lib_serie_countries') . ":</strong> " . $strseriecountries . "</p>";

			echo "<p>";
			echo "<strong>".get_movie_string('lib_person_record_updated').":</strong> ".$strserietimcreditscompleted;
			echo "</p>";
			echo f_moviesectionopen("watch","▶️",get_movie_string('lib_watch_providers_section'));
			echo f_watchcountryselectorhtml(
				$strwatchcountry,
				$arrwatchcountryoptions,
				array("serieid"=>$lngserieid),
				"serie-watch-country",
				get_movie_string('lib_watch_country'),
				get_movie_string('lib_watch_country_choose'),
				get_movie_string('lib_watch_country_apply')
			);
			if (f_workpropertysnapshotstate($strseriewatchproviderscompleted, 0)==="unknown") {
				echo "<p>".get_movie_string('lib_watch_providers_unknown')."</p>";
			} elseif ($strwatchcountry=="") {
				echo "<p>".get_movie_string('lib_watch_providers_select_country')."</p>";
			} else {
				$arrseriewatchproviders=array();
				$strseriewatchproviderlink="";
				$strsqlseriewatchproviders=f_workpropertywatchproviderssql(
					$strsqlns, "serie", $lngserieid, $strwatchcountry);
				$resultseriewatchproviders=f_sqlselect($strsqlseriewatchproviders);
				if ($resultseriewatchproviders) {
					while ($rstempseriewatchprovider=f_sqlresultfetcharray($resultseriewatchproviders)) {
						$arrseriewatchproviders[]=$rstempseriewatchprovider;
						if ($strseriewatchproviderlink=="" && trim((string)$rstempseriewatchprovider['TMDB_LINK'])!="") {
							$strseriewatchproviderlink=trim((string)$rstempseriewatchprovider['TMDB_LINK']);
						}
					}
					f_sqlfreeresult($resultseriewatchproviders);
				}
				echo "<p><small>".get_movie_string('lib_watch_providers_collected').": ".
					f_workpropertyescape($strseriewatchproviderscompleted)."</small></p>";
				if (f_workpropertysnapshotstate($strseriewatchproviderscompleted, count($arrseriewatchproviders))==="empty") {
					echo "<p>".get_movie_string('lib_watch_providers_empty')."</p>";
				} else {
					$arrwatchmodelabels=array(
						"flatrate"=>get_movie_string('lib_watch_mode_flatrate'),
						"free"=>get_movie_string('lib_watch_mode_free'),
						"ads"=>get_movie_string('lib_watch_mode_ads'),
						"rent"=>get_movie_string('lib_watch_mode_rent'),
						"buy"=>get_movie_string('lib_watch_mode_buy')
					);
						echo f_workpropertywatchprovidershtml(
							$arrseriewatchproviders,
							$arrwatchmodelabels,
							$strtmdbimagespathbaseurl,
							get_movie_string('lib_watch_provider'),
							$strwatchcountry
						);
					if ($strseriewatchproviderlink!="") {
						echo "<p><a href=\"".f_htmlattr($strseriewatchproviderlink).
							"\" target=\"_blank\" rel=\"noreferrer\">".
							get_movie_string('lib_watch_providers_tmdb_link')."</a></p>";
					}
				}
				echo "<p><small>".get_movie_string('lib_watch_providers_attribution')."</small></p>";
			}

			echo f_moviesectionopen("ids","🔗",($struilang!="en"?"Identifiants externes":"External IDs"));
			echo "<p><strong>".get_movie_string('lib_serie_id_tmdb').":</strong> ";
			if ($lngserieid>0) {
				echo "<a href=\"".$strserietmdburl."\" target=\"_blank\" rel=\"noreferrer\">";
			}
			echo $lngserieid;
			if ($lngserieid>0) {
				echo "</a>";
			}
			if ($lngserieid>0) {
				echo " <a href=\"".$strserietmdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/tmdb-icon.webp\" width=\"103\" height=\"16\" alt=\"TMDb\" title=\"TMDb\"></a>";
			}
			echo "</p>";
			
			echo "<p><strong>".get_movie_string('lib_serie_id_imdb').":</strong> ";
			if ($strserieidimdb!="") {
				echo "<a href=\"".$strserieimdburl."\" target=\"_blank\" rel=\"noreferrer\">";
			}
			echo $strserieidimdb;
			if ($strserieidimdb!="") {
				echo "</a>";
			}
			if ($strserieidimdb!="") {
				echo " <a href=\"".$strserieimdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
			}
			echo "</p>";
			echo "<p><strong>".get_movie_string('lib_serie_id_wikidata').":</strong> ";
			if ($strserieidwikidata!="") {
				echo "<a href=\"".$strseriewikidataurl."\" target=\"_blank\" rel=\"noreferrer\">";
			}
			echo $strserieidwikidata;
			if ($strserieidwikidata!="") {
				echo "</a>";
			}
			if ($strserieidwikidata!="") {
				echo " <a href=\"".$strseriewikidataurl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a>";
			}
			echo "</p>";
			echo f_wikipediaidline($struilang,$strserieidwikidata);
			echo f_socialidshtml($arrseriesocialids ?? array(),f_socialidslabels());
			//Hook entity.ids: identifier lines added by the plugins
			echo f_pluginhtml("entity.ids",array("type"=>"serie","id"=>$lngserieid,"imdbid"=>$strserieidimdb,"wikidataid"=>$strserieidwikidata));
			//Hook entity.afterids: lines added by the plugins under the identifiers
			echo f_pluginhtml("entity.afterids",array("type"=>"serie","id"=>$lngserieid,"imdbid"=>$strserieidimdb));
			
			echo f_moviesectionopen("overview","📝",get_movie_string('lib_serie_overview'),1);
			echo "<p>".str_replace("\r","<br />",str_replace("\n","<br />",$strserieoverview))."</p>";
			

			echo f_wikidatamediasection_v2($struilang,$strserieidwikidata);

			echo f_moviesectionopen("wikidata","🧬",get_movie_string('lib_serie_section_wikidata'));
			echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_serie_wikidata_type')."</a>:</strong> ".$strseriewikidatainstanceof."</p>";
			if ($strseriealiases!="") {
				echo "<p><strong>".get_movie_string('lib_serie_wikidata_aliases').":</strong> ".$strseriealiases."</p>";
			}
			/*
			echo "<p><strong>".get_movie_string('lib_wikidata_genres').":</strong> ".$strseriewikidatagenres."</p>";
			echo "<p><strong>".get_movie_string('lib_wikidata_colors').":</strong> ".$strseriewikidatacolors."</p>";
			echo "<p><strong>".get_movie_string('lib_wikidata_desc_by_source').":</strong> ".$strseriewikidatadescbysrc."</p>";
			echo "<p><strong>".get_movie_string('lib_wikidata_part_of').":</strong> ".$strseriewikidatapartof."</p>";
			*/
			if ($strserieidwikidata!="") {
				$strseriewikidataeverything=f_wikidataallpropertiesv2($struilang,$strserieidwikidata,", ",f_t2slocationwikidataexcluded(isset($arrt2slocations) ? $arrt2slocations : array()));
			}
			echo $strseriewikidataeverything;
			
			echo f_moviesectionopen("wikipedia","📖",get_movie_string('lib_serie_section_wikipedia'));
			$strseriewikipediacontent=f_wikipediallsections($struilang,$strserieidwikidata,$strsep=", ");
			echo $strseriewikipediacontent;

			//Hook entity.sections: sections added by the plugins after the core sections
			echo f_pluginhtml("entity.sections",array("type"=>"serie","id"=>$lngserieid,"title"=>$strserietitle,"originaltitle"=>$strserieoriginaltitle,"year"=>$strseriefirstairyear,"imdbid"=>$strserieidimdb));
			
			for ($intcredittype=1;$intcredittype<=2;$intcredittype++) {
				switch ($intcredittype) {
				case 1:
					//Cast
					$strtitle=get_movie_string('lib_serie_cast');
					$strseriecreditcredittype="cast";
					$strsqlpersonserie="
SELECT T_WC_TMDB_PERSON_SERIE.ID_PERSON, 
T_WC_TMDB_PERSON_SERIE.CAST_CHARACTER, 
T_WC_TMDB_PERSON.NAME, 
T_WC_TMDB_PERSON.PROFILE_PATH, 
T_WC_TMDB_PERSON.ID_IMDB 
FROM T_WC_TMDB_PERSON_SERIE
INNER JOIN T_WC_TMDB_PERSON ON T_WC_TMDB_PERSON_SERIE.ID_PERSON = T_WC_TMDB_PERSON.ID_PERSON
WHERE T_WC_TMDB_PERSON_SERIE.ID_SERIE = ".f_longtosql($lngserieid)." 
AND T_WC_TMDB_PERSON_SERIE.CREDIT_TYPE = ".f_stringtosql($strseriecreditcredittype)." 
ORDER BY T_WC_TMDB_PERSON_SERIE.DISPLAY_ORDER 
";
					break;
				case 2:
					//Crew
					$strtitle=get_movie_string('lib_serie_crew');
					$strseriecreditcredittype="crew";
					$strsqlpersonserie="
SELECT T_WC_TMDB_PERSON_SERIE.ID_PERSON, 
T_WC_TMDB_PERSON_SERIE.CREW_DEPARTMENT, 
T_WC_TMDB_PERSON_SERIE.CREW_JOB, 
T_WC_TMDB_PERSON.NAME, 
T_WC_TMDB_PERSON.PROFILE_PATH, 
T_WC_TMDB_PERSON.ID_IMDB 
FROM T_WC_TMDB_PERSON_SERIE
INNER JOIN T_WC_TMDB_PERSON ON T_WC_TMDB_PERSON_SERIE.ID_PERSON = T_WC_TMDB_PERSON.ID_PERSON
WHERE T_WC_TMDB_PERSON_SERIE.ID_SERIE = ".f_longtosql($lngserieid)." 
AND T_WC_TMDB_PERSON_SERIE.CREDIT_TYPE = ".f_stringtosql($strseriecreditcredittype)." 
ORDER BY T_WC_TMDB_PERSON_SERIE.DISPLAY_ORDER 
";
					break;
				}
				echo f_moviesectionopen($strseriecreditcredittype,($intcredittype==1?"🎭":"🛠️"),$strtitle);
				echo "<table>";
				echo "<tr>";
				//Remembering previous person id so we can manage several credits for the same person
				$lngpersonidold=0;
				$intpersonseriefound=false;
				$lngrowindex=0;
				$resultpersonserie=f_sqlselect($strsqlpersonserie);
				if ($resultpersonserie) {
					while ($rstemppersonserie=f_sqlresultfetcharray($resultpersonserie)) {
						$intpersonseriefound=true;
						
						$strpersoncreditname=$rstemppersonserie['NAME'];
						$strpersonprofilepath=$rstemppersonserie['PROFILE_PATH'];
						$strpersonprofilefullpath=f_getpersonprofilepicture($strpersonprofilepath,1);
						//$strpersoncreditreleasedate=$rstemppersonserie['DAT_FIRST_AIR'];
						//$strpersoncreditreleaseyear=substr($strpersoncreditreleasedate,0,4);
						$lngpersonid=f_stringtolong($rstemppersonserie['ID_PERSON']);
						$strpersonidimdb=$rstemppersonserie['ID_IMDB'];
						$strpersonurl="person.php?personid=".$lngpersonid;
						
						switch ($intcredittype) {
						case 1:
							//Cast
							$strpersoncreditcharacter=$rstemppersonserie['CAST_CHARACTER'];
							$strcharacterurl="character.php?character=".$strpersoncreditcharacter;
							$strcharacterwithlink="<a href=\"".$strcharacterurl."\">".get_movie_string('lib_serie_character').": ".$strpersoncreditcharacter."</a>";
							$strpersoncreditdepartment="";
							$strpersoncreditjob="";
							if ($lngpersonidold==$lngpersonid) {
								//Same person with different credits
								if ($intprivate) {
									echo ", ".$strcharacterwithlink;
								} else {
									echo ", ".get_movie_string('lib_serie_character').": ".$strpersoncreditcharacter;
								}
							} else {
								//New person
								if ($lngrowindex>0) {
									//Not the first person so we end the previous table row
									echo "</td>";
									echo "</tr>";
									echo "<tr>";
								}
								$lngrowindex++;
								echo "<td valign=\"top\">";
								if ($strpersonprofilefullpath!="") {
									echo "<a href=\"person.php?personid=".$lngpersonid."\">";
									echo f_getpersoncreditimagehtml($strpersonprofilepath,$strpersoncreditname)."<br />";
									echo "</a>";
								}
								echo "</td>";
								echo "<td valign=\"top\">";
								echo "<a href=\"".$strpersonurl."\">".$strpersoncreditname."</a>";
								if ($intprivate) {
									echo " - ".$strcharacterwithlink;
								} else {
									echo " - ".get_movie_string('lib_serie_character').": ".$strpersoncreditcharacter;
								}
							}
							break;
						case 2:
							//Crew
							$strpersoncreditcharacter="";
							$strpersoncreditdepartment=$rstemppersonserie['CREW_DEPARTMENT'];
							$strpersoncreditjob=$rstemppersonserie['CREW_JOB'];
							if ($lngpersonidold==$lngpersonid) {
								//Same person with different credits
								echo ", ".get_movie_string('lib_serie_department').": ".$strpersoncreditdepartment.", ".get_movie_string('lib_serie_job').": ".$strpersoncreditjob;
							} else {
								//New person
								if ($lngrowindex>0) {
									//Not the first person so we end the previous table row
									echo "</td>";
									echo "</tr>";
									echo "<tr>";
								}
								$lngrowindex++;
								echo "<td valign=\"top\">";
								if ($strpersonprofilefullpath!="") {
									echo "<a href=\"person.php?personid=".$lngpersonid."\">";
									echo f_getpersoncreditimagehtml($strpersonprofilepath,$strpersoncreditname)."<br />";
									echo "</a>";
								}
								echo "</td>";
								echo "<td valign=\"top\">";
								echo "<a href=\"".$strpersonurl."\">".$strpersoncreditname."</a>";
								echo " - ".get_movie_string('lib_serie_department').": ".$strpersoncreditdepartment.", ".get_movie_string('lib_serie_job').": ".$strpersoncreditjob;
							}
							break;
						}
						$lngpersonidold=$lngpersonid;
					}
					f_sqlfreeresult($resultpersonserie);
				}
				echo "</td>";
				echo "</tr>";
				echo "</table>";
			}
			echo f_moviesectionclose();

			//Previous / next member in the T2S collection (cross-type: film or series)
			ob_start();
			f_t2scollectionprevnextdisplay($lngserieid,"serie");
			echo f_moviesectionwrap("collection","🧩",ob_get_clean(),($struilang!="en"?"Dans la collection":"In the collection"));
			//Grounded TMDb neighbour sets (similar + recommendations)
			ob_start();
			f_serietmdbneighboursdisplay($lngserieid,"T_WC_TMDB_SERIE_SIMILAR","ID_SERIE_SIMILAR","lib_serie_tmdb_similar");
			echo f_moviesectionwrap("tmdb-similar","🎯",ob_get_clean());
			ob_start();
			f_serietmdbneighboursdisplay($lngserieid,"T_WC_TMDB_SERIE_RECOMMENDATION","ID_SERIE_RECOMMENDED","lib_serie_tmdb_recommendations");
			echo f_moviesectionwrap("tmdb-reco","👍",ob_get_clean());
			//When this series is itself a source work (ID_SERIE on T_WC_T2S_SOURCE_WORK), the works
			//adapted from it, films then series. Replaces the sourcework.php sheet, now redirected
			//here. Kept before the page.beforefooter hook, which runs once every card is rendered.
			ob_start();
			f_worksadaptedfromsection("serie",$lngserieid);
			echo f_moviesectionwrap("adapted","📚",ob_get_clean(),($struilang!="en"?"Œuvres adaptées de cette série":"Works adapted from this series"));
			//Hook page.beforefooter: blocks of the plugins at the end of the page. Must stay after the
			//sections above: a plugin may sum up here what its listeners met on every card of the page.
			echo f_pluginhtml("page.beforefooter",array("page"=>"serie"));
			if ($strseriebackdroppath!="") {
				echo "<p><a href=\"".$strtmdbimagesoriginalpathbaseurl.$strseriebackdroppath."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"".$strtmdbimagespathbaseurl.$strseriebackdroppath."\" alt=\"".$strserietitleyear."\" title=\"".$strserietitleyear."\" style=\"background-color: white;\" /></a></p>";
			}
			// Noyau deja emis hors buffer ; on prepend la barre d'ancres au corps bufferise.
			$strfichebody=ob_get_clean();
			echo f_movieanchorbar();
			echo $strfichebody;
			f_footerdisplay();
		} else {
			//Serie record NOT found in the database
			$strmessage=get_movie_string('lib_serie_not_found_db');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
			
			//So we redirect to the serie-load.php script to read data from the TMDb API 
			//$strredirectscript="serie-load.php?serieid=".$lngserieid;
			//header("Location: ".$strredirectscript);
		}
	}  else {
		//serieid URL parameter not set
		$strmessage=get_movie_string('lib_serie_id_not_set');
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
