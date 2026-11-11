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
	$lngmovieid=f_stringtolong(f_request2("movieid"));
	$strimdbid=substr(trim(f_request2("imdbid")),0,15);
	$intmoviefound=false;
	
	if ($lngmovieid==0 && $strimdbid!="") {
		$lngmovieid=f_stringtolong(f_descfromcode($strsqlns."TMDB_MOVIE","ID_IMDB","ID_MOVIE",f_stringtosql($strimdbid),"",""));
		if ($lngmovieid==0) {
			//Movie record NOT found in the database
			$strmessage=get_movie_string('lib_movie_not_found_db');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
			exit;
		}
	}
	if ($lngmovieid>0) {
		//Read movie data from the MySQL database
		$strsqlmovie="SELECT * FROM ".$strsqlns."TMDB_MOVIE WHERE ID_MOVIE = ".f_longtosql($lngmovieid)." AND DELETED = 0 AND TIM_CREDITS_COMPLETED IS NOT NULL ";
		$resultmovie=f_sqlselect($strsqlmovie);
		if ($resultmovie) {
			if ($rstempmovie=f_sqlresultfetcharray($resultmovie)) {
				$intmoviefound=true;
				$arrmoviesocialids=f_socialidsfromrow($rstempmovie);
				$strmovieidimdb=$rstempmovie["ID_IMDB"];
				$strmovieoverview=$rstempmovie['OVERVIEW'];
				$strmoviedatrelease=$rstempmovie['DAT_RELEASE'];
				$strmoviereleaseyear=substr($strmoviedatrelease,0,4);
				//$intmovievideo=f_stringtolong($rstempmovie['VIDEO']);
				$strmovieposterpath=$rstempmovie['POSTER_PATH'];
				$strmoviehomepage=$rstempmovie['HOMEPAGE_URL'];
				$strmovietitle=$rstempmovie['TITLE'];
				$strmovietagline=$rstempmovie['TAGLINE'];
				$dblmovievoteaverage=f_stringtodouble($rstempmovie['VOTE_AVERAGE']);
				$strmovieoriginallanguage=$rstempmovie['ORIGINAL_LANGUAGE'];
				$intmovieadult=f_stringtolong($rstempmovie['ADULT']);
				$strmovieoriginaltitle=$rstempmovie['ORIGINAL_TITLE'];
				$strmovietimcreditscompleted=$rstempmovie['TIM_CREDITS_COMPLETED'];
				$strmoviereleasedatescompleted=trim((string)($rstempmovie['TIM_RELEASE_DATES_COMPLETED'] ?? ""));
				$strmoviewatchproviderscompleted=trim((string)($rstempmovie['TIM_WATCH_PROVIDERS_COMPLETED'] ?? ""));
				$strmoviebackdroppath=$rstempmovie['BACKDROP_PATH'];
				$dblmoviepopularity=f_stringtodouble($rstempmovie['POPULARITY']);
				$lngcollectionid=f_stringtolong($rstempmovie['ID_COLLECTION']);
				$strmoviegenres=$rstempmovie['GENRES'];
				$dblmoviebudget=f_stringtodouble($rstempmovie['BUDGET']);
				$dblmovierevenue=f_stringtodouble($rstempmovie['REVENUE']);
				$lngmovieruntime=f_stringtolong($rstempmovie['RUNTIME']);
				$dblvoteaverage=f_stringtodouble($rstempmovie['VOTE_AVERAGE']);
				$lngvotecount=f_stringtolong($rstempmovie['VOTE_COUNT']);
				$strmovieidwikidata=$rstempmovie["ID_WIKIDATA"];
				$strmovieformatline=$rstempmovie["WIKIPEDIA_FORMAT_LINE"];
				$strdatmovieformatline=$rstempmovie["DAT_WIKIPEDIA_FORMAT_LINE"];
				$intmovieismovie=f_stringtolong($rstempmovie['IS_MOVIE']);
				$intmovieisshortfilm=f_stringtolong($rstempmovie['IS_SHORT_FILM']);
				// Technical specifications
				$intmovieiscolor=f_stringtolong($rstempmovie['IS_COLOR']);
				$intmovieisblackandwhite=f_stringtolong($rstempmovie['IS_BLACK_AND_WHITE']);
				$intmovieissilent=f_stringtolong($rstempmovie['IS_SILENT']);
				$strmoviecolortechnology=trim($rstempmovie['COLOR_TECHNOLOGY']);
				$strmoviefilmtechnology=trim($rstempmovie['FILM_TECHNOLOGY']);
				$strmoviefilmformat=trim($rstempmovie['FILM_FORMAT']);
				$strmoviesoundsystem=trim($rstempmovie['SOUND_SYSTEM']);
				$strmoviesoundtechnology=trim($rstempmovie['SOUND_TECHNOLOGY']);
				if ($strmovieidimdb!="") {
					$strmovieimdburl="https://www.imdb.com/title/".$strmovieidimdb."/";
				}
				$strmovietmdburl="https://www.themoviedb.org/movie/".$lngmovieid;
				if ($strmovieidwikidata!="") {
					$strmoviewikidataurl="https://www.wikidata.org/wiki/".$strmovieidwikidata;
				}
			}
			f_sqlfreeresult($resultmovie);
		}
		if ($struilang!="en") {
			//Read translated movie data from the MySQL database
			$strsqlmovie="SELECT * FROM ".$strsqlns."TMDB_MOVIE_LANG WHERE ID_MOVIE = ".f_longtosql($lngmovieid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
			$resultmovie=f_sqlselect($strsqlmovie);
			if ($resultmovie) {
				if ($rstempmovie=f_sqlresultfetcharray($resultmovie)) {
					$strmovieoverview=$rstempmovie['OVERVIEW'];
					$strmovieposterpath=$rstempmovie['POSTER_PATH'];
					$strmovietitle=$rstempmovie['TITLE'];
					$strmovietagline=$rstempmovie['TAGLINE'];
					$strmoviebackdroppath=$rstempmovie['BACKDROP_PATH'];
					//$strmovieformatline=$rstempmovie["WIKIPEDIA_FORMAT_LINE"];
				}
				f_sqlfreeresult($resultmovie);
			}
		} else {
			/*
			if ($strmovieformatline=="") {
				$strsql="SELECT WIKIPEDIA_FORMAT_LINE FROM ".$strsqlns."TMDB_MOVIE_LANG WHERE ID_MOVIE = ".f_longtosql($lngmovieid)." AND LANG = 'fr' AND DELETED = 0 ";
				$strmovieformatline=f_fieldfromquery($strsql);
			}
			*/
		}
		
		//Read movie meta data from the MySQL database
		$strmoviedirectors="";
		$strmoviewriters="";
		$strmoviecasttop="";
		$strmoviecrewtop="";
		$strmovietags="";
		$strmovietextdoc="";
		$strmovietextsbert="";
		$lngmovieidcriterion=0;
		$lngmovieidcriterionspine=0;
		$strcriterioncollectionurl="";
		$strmoviewikidatainstanceof="";
		$strmoviealiases="";
		$strmoviewikidataeverything="";
		if ($strmovieidwikidata!="") {
			$strmovieinstanceof="";
			//Read movie wikidata data from the MySQL database
			$strsqlmovie="SELECT * FROM ".$strsqlns."WIKIDATA_MOVIE_V1 WHERE ID_WIKIDATA = ".f_stringtosql($strmovieidwikidata)." AND DELETED = 0 ";
			$resultmovie=f_sqlselect($strsqlmovie);
			if ($resultmovie) {
				if ($rstempmovie=f_sqlresultfetcharray($resultmovie)) {
					$lngmovieidcriterion=f_stringtolong($rstempmovie['ID_CRITERION']);
					$lngmovieidcriterionspine=f_stringtolong($rstempmovie['ID_CRITERION_SPINE']);
					$strmovieinstanceof=trim($rstempmovie['INSTANCE_OF']);
					$strmoviealiases=trim($rstempmovie['ALIASES']);
					if ($lngmovieidcriterion>0) {
						$strcriterioncollectionurl="https://www.criterion.com/films/".$lngmovieidcriterion;
					}
				}
				f_sqlfreeresult($resultmovie);
			}
			
			// Le libelle de la classe se lit en V2, repli V1.
			// Corrige au passage un defaut latent : le trim(f_fieldfromquery($strsql)) etait
			// HORS du if, si bien qu un film sans INSTANCE_OF rejouait la derniere requete
			// affectee a $strsql et rangeait son resultat dans le type Wikidata de la page.
			$strmoviewikidatainstanceof=f_getwikidatalabel($strmovieinstanceof,$struilang);
			// Le bloc Wikidata est rendu plus bas, une fois connues les lignes "Filmed in" / "Set in"
			// qu'il ne doit pas repeter.
			//P166,P1411
		}
		
		if ($intmoviefound) {
			//Movie record found in the database
			//Display movie data
			$strmovietitleyear=$strmovietitle." (".$strmoviereleaseyear.")";
			//Social preview: the poster is the visual shown when the page URL is shared (WhatsApp, Facebook, X, Slack...)
			$arrsocialmovie=array("type" => "video.movie");
			$arrsocialmovie['description']=($strmovieoverview!="" ? $strmovieoverview : $strmovietagline);
			if ($strmovieposterpath!="") {
				$arrsocialmovie['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strmovieposterpath);
				//TMDb posters are 2:3, so the w342 rendition is 342x513
				$arrsocialmovie['imagewidth']=342;
				$arrsocialmovie['imageheight']=513;
				$arrsocialmovie['imagealt']=$strmovietitleyear;
			}
			f_headerdisplay($strmovietitleyear,"",$arrsocialmovie);
			echo "<h1>";
			echo $strmovietitleyear;
			//Hook entity.h1: badges and links added by the plugins after the title
			echo f_pluginhtml("entity.h1",array("type"=>"movie","id"=>$lngmovieid,"title"=>$strmovietitle,"originaltitle"=>$strmovieoriginaltitle,"year"=>$strmoviereleaseyear,"imdbid"=>$strmovieidimdb));
			echo "</h1>";
			if ($strmovietagline!="") {
				echo "<a name=\"tagline\"></a><h2>".$strmovietagline."</h2>";
			}
			if ($strmovieposterpath!="") {
				echo "<p>";
				echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strmovieposterpath."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".$strtmdbimagespathbaseurl.$strmovieposterpath."\" alt=\"".$strmovietitleyear."\" title=\"".$strmovietitleyear."\" style=\"background-color: white;\" />";
				echo "</a>";
				echo "</p>";
			}
			echo "<p>";
			echo "<a href=\"images.php?movieid=".$lngmovieid."\">";
			echo get_movie_string('lib_movie_all_images');
			echo "</a>";
			echo "</p>";
			//Link to the YouTube video gallery for this movie
			echo "<p>";
			echo "<a href=\"videos.php?movieid=".$lngmovieid."\">";
			echo get_movie_string('lib_movie_all_videos');
			echo "</a>";
			echo "</p>";
			//Backdrops with their AI caption, in the estimated order of the film
			echo "<p>";
			echo "<a href=\"movie-captions.php?movieid=".$lngmovieid."\">";
			echo get_movie_string('lib_movie_captioned_screenshots');
			echo "</a>";
			echo "</p>";

			f_moviesectionreset(array("info","overview","cast","crew"));
			ob_start();
			echo f_moviesectionopen("info","🪪",get_movie_string('lib_movie_section_tmdb'),1);
			if ($strmovieoriginaltitle!=$strmovietitle) {
				echo "<p><strong>".get_movie_string('lib_movie_original_title').":</strong> ".$strmovieoriginaltitle."</p>";
			}
			
			// Display if this is a short film or a feature film
			echo "<p><strong>".get_movie_string('lib_movie_film_type').":</strong> ";
			if ($intmovieisshortfilm == 1) {
				echo get_movie_string('lib_movie_is_short_film');
			} else {
				echo get_movie_string('lib_movie_is_feature_film');
			}
			echo "</p>";
			if ($strmoviedatrelease!="") {
				echo "<p><strong>".get_movie_string('lib_movie_release_date').":</strong> ".$strmoviedatrelease."</p>";
			}
			echo "<p><strong>".get_movie_string('lib_movie_type').":</strong> ";
			if ($intmovieismovie == 1) {
				echo get_movie_string('lib_movie_is_movie');
			} else {
				echo get_movie_string('lib_movie_is_documentary');
			}
			echo "</p>";
			if ($lngmovieruntime>0) {
				echo "<p><strong>".get_movie_string('lib_movie_runtime').":</strong> ".$lngmovieruntime."&nbsp;".get_movie_string('lib_movie_minutes')."</p>";
			}
			
			// Display technical specifications
			// Color information
			if ($intmovieiscolor == 1 || $intmovieisblackandwhite == 1) {
				echo "<p><strong>";
				if ($intmovieiscolor == 1 && $intmovieisblackandwhite == 1) {
					echo get_movie_string('lib_movie_is_color')." & ".get_movie_string('lib_movie_is_black_and_white');
				} elseif ($intmovieiscolor == 1) {
					echo get_movie_string('lib_movie_is_color');
				} else {
					echo get_movie_string('lib_movie_is_black_and_white');
				}
				echo "</strong></p>";
			}
			
			// Color technology
			if ($strmoviecolortechnology != "") {
				echo "<p><strong>".get_movie_string('lib_movie_color_technology').":</strong> ".$strmoviecolortechnology."</p>";
			}
			
			// Film technology
			if ($strmoviefilmtechnology != "") {
				echo "<p><strong>".get_movie_string('lib_movie_film_technology').":</strong> ".$strmoviefilmtechnology."</p>";
			}
			
			// Film format
			if ($strmoviefilmformat != "") {
				echo "<p><strong>".get_movie_string('lib_movie_film_format').":</strong> ".$strmoviefilmformat."</p>";
			}
			
			// Sound system
			if ($strmoviesoundsystem != "") {
				echo "<p><strong>".get_movie_string('lib_movie_sound_system').":</strong> ".$strmoviesoundsystem."</p>";
			}
			
			// Sound technology
			if ($strmoviesoundtechnology != "") {
				echo "<p><strong>".get_movie_string('lib_movie_sound_technology').":</strong> ".$strmoviesoundtechnology."</p>";
			}
			
			// Silent film status
			if ($intmovieissilent == 1) {
				echo "<p><strong>".get_movie_string('lib_movie_is_silent').":</strong> ".get_movie_string('lib_yes')."</p>";
			} elseif ($intmovieissilent == 0) {
				echo "<p><strong>".get_movie_string('lib_movie_is_silent').":</strong> ".get_movie_string('lib_no')."</p>";
			}

			$strt2stechnicals="";
			$strsqlt2stechnicals="SELECT T_WC_T2S_TECHNICAL.ID_TECHNICAL, T_WC_T2S_TECHNICAL.WIKIDATA_LABEL, T_WC_T2S_TECHNICAL.DESCRIPTION, T_WC_T2S_TECHNICAL.DESCRIPTION_FR ";
			$strsqlt2stechnicals.="FROM T_WC_T2S_MOVIE_TECHNICAL ";
			$strsqlt2stechnicals.="INNER JOIN T_WC_T2S_TECHNICAL ON T_WC_T2S_MOVIE_TECHNICAL.ID_TECHNICAL = T_WC_T2S_TECHNICAL.ID_TECHNICAL ";
			$strsqlt2stechnicals.="WHERE T_WC_T2S_MOVIE_TECHNICAL.ID_MOVIE = ".f_longtosql($lngmovieid)." AND T_WC_T2S_MOVIE_TECHNICAL.DELETED = 0 AND T_WC_T2S_TECHNICAL.DELETED = 0 ";
			$strsqlt2stechnicals.="ORDER BY T_WC_T2S_MOVIE_TECHNICAL.DISPLAY_ORDER, T_WC_T2S_TECHNICAL.WIKIDATA_LABEL, T_WC_T2S_TECHNICAL.DESCRIPTION ";
			$resultt2stechnicals=f_sqlselect($strsqlt2stechnicals);
			if ($resultt2stechnicals) {
				while ($rstemptechnical=f_sqlresultfetcharray($resultt2stechnicals)) {
					$lngt2stechnicalid=f_stringtolong($rstemptechnical["ID_TECHNICAL"]);
					$strt2stechnicallabel=trim((string)$rstemptechnical["WIKIDATA_LABEL"]);
					$strt2stechnicaldescription=trim((string)$rstemptechnical["DESCRIPTION"]);
					$strt2stechnicaldescriptionfr=trim((string)$rstemptechnical["DESCRIPTION_FR"]);
					$strt2stechnicalname=$strt2stechnicallabel;
					if ($strt2stechnicalname=="") {
						$strt2stechnicalname=$strt2stechnicaldescription;
					}
					if ($struilang!="en" && $strt2stechnicaldescriptionfr!="") {
						$strt2stechnicalname=$strt2stechnicaldescriptionfr;
					}
					if ($strt2stechnicalname=="") {
						continue;
					}
					if ($strt2stechnicals!="") {
						$strt2stechnicals.=", ";
					}
					$strt2stechnicals.="<a href=\"technical.php?technicalid=".$lngt2stechnicalid."\">".$strt2stechnicalname."</a>";
				}
				f_sqlfreeresult($resultt2stechnicals);
			}
			if ($strt2stechnicals!="") {
				echo "<p><strong>".get_movie_string('lib_movie_technical').":</strong> ".$strt2stechnicals."</p>";
			}

			echo "<p><strong>".get_movie_string('lib_movie_format').":</strong> ".$strmovieformatline."</p>";
			echo "<p>".$strdatmovieformatline."</p>";
			
			echo "<p><strong>".get_movie_string('lib_movie_popularity').":</strong> ".$dblmoviepopularity."</p>";
			//echo "<p><strong>".get_movie_string('lib_movie_votecount').":</strong> ".$lngvotecount."</p>";
			//echo "<p><strong>".get_movie_string('lib_movie_vote_average').":</strong> ".$dblvoteaverage."</p>";
			if ($strmovieidimdb!="") {
				//The vote count now rides inside the value, "8.5 (1543210 votes)", the same way the
				//serie, season and episode pages carry what their figure rests on. One query for both
				//fields rather than two f_descfromcode() lookups on the same row.
				$dblimdbrating=0;
				$lngimdbvotes=0;
				$strsqlmovieimdb="SELECT averageRating, numVotes FROM ".$strsqlns."IMDB_MOVIE_RATING_IMPORT WHERE tconst = ".f_stringtosql($strmovieidimdb);
				$resultmovieimdb=f_sqlselect($strsqlmovieimdb);
				if ($resultmovieimdb) {
					if ($rstempmovieimdb=f_sqlresultfetcharray($resultmovieimdb)) {
						$dblimdbrating=f_stringtodouble((string)$rstempmovieimdb['averageRating']);
						$lngimdbvotes=f_stringtolong((string)$rstempmovieimdb['numVotes']);
					}
					f_sqlfreeresult($resultmovieimdb);
				}
				$strmovieimdbrating=f_imdbratingdisplay($dblimdbrating,$lngimdbvotes);
				if ($strmovieimdbrating!="") {
					echo "<p><strong>".get_movie_string('lib_movie_imdb_rating').":</strong> ".$strmovieimdbrating."</p>";
				}
				f_omdbdisplay($strmovieidimdb);
			}
			if ($dblmoviebudget>0) {
				//$strmoviebudget=sprintf($strlibamountformat, $dblmoviebudget);
				$strmoviebudget=f_moneyformat($dblmoviebudget);
				echo "<p><strong>".get_movie_string('lib_movie_budget').":</strong> ".$strmoviebudget."</p>";
			}
			if ($dblmovierevenue>0) {
				//$strmovierevenue=sprintf($strlibamountformat, $dblmovierevenue);
				$strmovierevenue=f_moneyformat($dblmovierevenue);
				echo "<p><strong>".get_movie_string('lib_movie_revenue').":</strong> ".$strmovierevenue."</p>";
			}
			f_workfinancedisplay($lngmovieid,"movie");
			if ($lngcollectionid>0) {
				$strcollectionname=f_descfromcode("T_WC_TMDB_COLLECTION","ID_COLLECTION","NAME",$lngcollectionid);
				if ($strcollectionname=="") {
					$strcollectionname=$lngcollectionid;
				}
				/*
				$strcollectionurl="collection.php?collectionid=".$lngcollectionid;
				echo "<p><strong>".get_movie_string('lib_movie_collection').":</strong> <a href=\"".$strcollectionurl."\">".$strcollectionname."</a></p>";
				*/
			}
			if ($strmoviegenres!="" && $strmoviegenres!="|") {
				switch ($struilang) {
				case "fr":
					$strmoviegenres=f_tmdbgenrestranslatefr($strmoviegenres);
					break;
				}
				if (substr($strmoviegenres,0,1)=="|") {
					$strmoviegenres=substr($strmoviegenres,1);
				}
				if (substr($strmoviegenres,strlen($strmoviegenres)-1,1)=="|") {
					$strmoviegenres=substr($strmoviegenres,0,strlen($strmoviegenres)-1);
				}
				$strmoviegenresdisp=str_replace("|",", ",$strmoviegenres);
			} else {
				$strmoviegenresdisp="";
			}
			echo "<p><strong>".get_movie_string('lib_movie_genres').":</strong> ".$strmoviegenresdisp."</p>";
			
			if ($intmovieadult) {
				$strmovieadult=get_movie_string('lib_yes');
			} else {
				$strmovieadult=get_movie_string('lib_no');
			}
			echo "<p><strong>".get_movie_string('lib_movie_is_adult').":</strong> ".$strmovieadult;
			echo "</p>";
			
			$strpropertyurl="property.php?propertyid=P31";
			
			$stroriglangurl="origlang.php?origlang=".$strmovieoriginallanguage;
			echo "<p><strong>".get_movie_string('lib_movie_original_language').":</strong> ";
			//echo "<a href=\"".$stroriglangurl."\">";
			echo f_tmdboriginallanguagetranslate($strmovieoriginallanguage,$struilang);
			//echo "</a>";
			echo "</p>";
			
			// Display spoken languages
			$strsqlmovielanguages = "SELECT T_WC_TMDB_MOVIE_SPOKEN_LANGUAGE.SPOKEN_LANGUAGE ";
			$strsqlmovielanguages .= "FROM T_WC_TMDB_MOVIE_SPOKEN_LANGUAGE ";
			$strsqlmovielanguages .= "WHERE T_WC_TMDB_MOVIE_SPOKEN_LANGUAGE.ID_MOVIE = " . f_longtosql($lngmovieid) . " AND T_WC_TMDB_MOVIE_SPOKEN_LANGUAGE.DELETED = 0 ";
			$strsqlmovielanguages .= "ORDER BY T_WC_TMDB_MOVIE_SPOKEN_LANGUAGE.DISPLAY_ORDER ";

			$strmovielanguages = "";
			if ($intdebug) {
				echo "<br />" . $strsqlmovielanguages;
			}
			$resultmovielanguages = f_sqlselect($strsqlmovielanguages);
			if ($resultmovielanguages) {
				while ($rstempmovielanguages = f_sqlresultfetcharray($resultmovielanguages)) {
					$strmovielanguagecode = $rstempmovielanguages["SPOKEN_LANGUAGE"];
					$strmovielanguage = f_tmdboriginallanguagetranslate($strmovielanguagecode, $struilang);
					$strmovielanguageurl = "origlang.php?origlang=" . $strmovielanguagecode;
					
					if ($strmovielanguages != "") {
						$strmovielanguages .= ", ";
					}
					//$strmovielanguages .= "<a href=\"" . $strmovielanguageurl . "\">";
					$strmovielanguages .= $strmovielanguage;
					//$strmovielanguages .= "</a>";
				}
				f_sqlfreeresult($resultmovielanguages);
			}
			echo "<p><strong>" . get_movie_string('lib_movie_spoken_languages') . ":</strong> " . $strmovielanguages . "</p>";
			
			if ($struilang!="en") {
				$strkeywordstable=$strsqlns."TMDB_KEYWORD_LANG";
			} else {
				$strkeywordstable=$strsqlns."TMDB_KEYWORD";
			}
			$strsqlmoviekeywords="SELECT ".$strkeywordstable.".ID_KEYWORD, ".$strkeywordstable.".NAME ";
			$strsqlmoviekeywords.="FROM T_WC_TMDB_MOVIE_KEYWORD INNER JOIN ".$strkeywordstable." ON T_WC_TMDB_MOVIE_KEYWORD.ID_KEYWORD = ".$strkeywordstable.".ID_KEYWORD WHERE ID_MOVIE = ".f_longtosql($lngmovieid)." AND ".$strkeywordstable.".DELETED = 0 ";
			if ($struilang!="en") {
				$strsqlmoviekeywords.=" AND ".$strkeywordstable.".LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlmoviekeywords.="ORDER BY T_WC_TMDB_MOVIE_KEYWORD.DISPLAY_ORDER ";
			$strmoviekeywords="";
			$resultmoviekeywords=f_sqlselect($strsqlmoviekeywords);
			if ($resultmoviekeywords) {
				while ($rstempmoviekeywords=f_sqlresultfetcharray($resultmoviekeywords)) {
					$strmoviekeywordid=$rstempmoviekeywords["ID_KEYWORD"];
					$strmoviekeyword=$rstempmoviekeywords["NAME"];
					$strmoviekeywordurl="keyword.php?keywordid=".$strmoviekeywordid;
					if ($strmoviekeywords!="") {
						$strmoviekeywords.=", ";
					}
					$strmoviekeywords.="<a href=\"".$strmoviekeywordurl."\">".$strmoviekeyword."</a>";
				}
				f_sqlfreeresult($resultmoviekeywords);
			}
			echo "<p><strong>".get_movie_string('lib_movie_keywords').":</strong> ".$strmoviekeywords."</p>";
			
			//Handling lists
			$strsqlmovielists="SELECT ".$strsqlns."TMDB_LIST.ID_LIST, ".$strsqlns."TMDB_LIST.NAME, ".$strsqlns."TMDB_LIST.SHORT_NAME ";
			/*
			if ($struilang!="en") {
				$strsqlmovielists.=", ".$strsqlns."TMDB_LIST_LANG.SHORT_NAME AS SHORT_NAME_LANG, ".$strsqlns."TMDB_LIST_LANG.LANG ";
			}
			*/
			$strsqlmovielists.="FROM ".$strsqlns."TMDB_LIST ";
			$strsqlmovielists.="INNER JOIN ".$strsqlns."TMDB_MOVIE_LIST ON ".$strsqlns."TMDB_MOVIE_LIST.ID_LIST = ".$strsqlns."TMDB_LIST.ID_LIST ";
			/*
			if ($struilang!="en") {
				$strsqlmovielists.="LEFT JOIN ".$strsqlns."TMDB_LIST_LANG ON ".$strsqlns."TMDB_LIST.ID_LIST = ".$strsqlns."TMDB_LIST_LANG.ID_LIST ";
			}
			*/
			$strsqlmovielists.="WHERE ".$strsqlns."TMDB_MOVIE_LIST.ID_MOVIE = ".f_longtosql($lngmovieid)." AND ".$strsqlns."TMDB_LIST.DELETED = 0 ";
			$strsqlmovielists.="AND ".$strsqlns."TMDB_LIST.USE_FOR_TAGGING >= 1 ";
			/*
			//Disabling the next condition because the list may not have a record for the required non en language
			if ($struilang!="en") {
				$strsqlmovielists.="AND ".$strsqlns."TMDB_LIST_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			*/
			//$strsqlmovielists.="ORDER BY DISPLAY_ORDER";
			if ($intdebug) {
				echo "<br />\$strsqlmovielists=".$strsqlmovielists;
			}
			/*
			$strmovielists="";
			$resultmovielists=f_sqlselect($strsqlmovielists);
			if ($resultmovielists) {
				while ($rstempmovielists=f_sqlresultfetcharray($resultmovielists)) {
					$strmovielistid=$rstempmovielists["ID_LIST"];
					$strmovielistname=trim($rstempmovielists["NAME"]);
					$strmovielistshortname=trim($rstempmovielists["SHORT_NAME"]);
					//$strmovielistshortnamelang=trim($rstempmovielists["SHORT_NAME_LANG"]);
					if ($strmovielistshortname!="") {
						$strmovielistname=$strmovielistshortname;
					}
					if ($struilang!="en") {
						//Is there a short name for the current language? 
						$strsql="SELECT SHORT_NAME FROM ".$strsqlns."TMDB_LIST_LANG WHERE DELETED = 0 AND ID_LIST = ".f_longtosql($strmovielistid)." AND LANG = ".f_stringtosql($struilang);
						$strmovielistshortnamelang=trim(f_fieldfromquery($strsql));
						if ($strmovielistshortnamelang!="") {
							$strmovielistname=$strmovielistshortnamelang;
						}
					}
					$strmovielisturl="list.php?listid=".$strmovielistid;
					//if ($strmovielists!="") {
						$strmovielists.="<br />";
					//}
					$strmovielists.="<a href=\"".$strmovielisturl."\">".$strmovielistname."</a>";
				}
				f_sqlfreeresult($resultmovielists);
			}
			echo "<p><strong>".get_movie_string('lib_movie_lists').":</strong> ".$strmovielists."</p>";
			*/
			
			$strt2scollections="";
			$strsqlt2scollections="SELECT T_WC_T2S_COLLECTION.ID_T2S_COLLECTION, T_WC_T2S_COLLECTION.COLLECTION_NAME, T_WC_T2S_COLLECTION.COLLECTION_NAME_FR ";
			$strsqlt2scollections.="FROM T_WC_T2S_MOVIE_COLLECTION ";
			$strsqlt2scollections.="INNER JOIN T_WC_T2S_COLLECTION ON T_WC_T2S_MOVIE_COLLECTION.ID_T2S_COLLECTION = T_WC_T2S_COLLECTION.ID_T2S_COLLECTION ";
			$strsqlt2scollections.="WHERE T_WC_T2S_MOVIE_COLLECTION.ID_MOVIE = ".f_longtosql($lngmovieid)." AND T_WC_T2S_MOVIE_COLLECTION.DELETED = 0 AND T_WC_T2S_COLLECTION.DELETED = 0 ";
			$strsqlt2scollections.="ORDER BY T_WC_T2S_MOVIE_COLLECTION.DISPLAY_ORDER, T_WC_T2S_COLLECTION.COLLECTION_NAME ";
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
			$strsqlt2slists.="FROM T_WC_T2S_MOVIE_LIST ";
			$strsqlt2slists.="INNER JOIN T_WC_T2S_LIST ON T_WC_T2S_MOVIE_LIST.ID_T2S_LIST = T_WC_T2S_LIST.ID_T2S_LIST ";
			$strsqlt2slists.="WHERE T_WC_T2S_MOVIE_LIST.ID_MOVIE = ".f_longtosql($lngmovieid)." AND T_WC_T2S_MOVIE_LIST.DELETED = 0 AND T_WC_T2S_LIST.DELETED = 0 ";
			$strsqlt2slists.="ORDER BY T_WC_T2S_MOVIE_LIST.DISPLAY_ORDER, T_WC_T2S_LIST.LIST_NAME ";
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
				echo "<p><strong>".get_movie_string('lib_movie_lists').":</strong> ".$strt2slists."</p>";
			}

			$strt2smovements="";
			$strsqlt2smovements="SELECT T_WC_T2S_MOVEMENT.ID_MOVEMENT, T_WC_T2S_MOVEMENT.MOVEMENT_NAME, T_WC_T2S_MOVEMENT.MOVEMENT_NAME_FR ";
			$strsqlt2smovements.="FROM T_WC_T2S_MOVIE_MOVEMENT ";
			$strsqlt2smovements.="INNER JOIN T_WC_T2S_MOVEMENT ON T_WC_T2S_MOVIE_MOVEMENT.ID_MOVEMENT = T_WC_T2S_MOVEMENT.ID_MOVEMENT ";
			$strsqlt2smovements.="WHERE T_WC_T2S_MOVIE_MOVEMENT.ID_MOVIE = ".f_longtosql($lngmovieid)." AND T_WC_T2S_MOVIE_MOVEMENT.DELETED = 0 AND T_WC_T2S_MOVEMENT.DELETED = 0 ";
			$strsqlt2smovements.="ORDER BY T_WC_T2S_MOVIE_MOVEMENT.DISPLAY_ORDER, T_WC_T2S_MOVEMENT.MOVEMENT_NAME ";
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
				echo "<p><strong>".get_movie_string('lib_movie_movements').":</strong> ".$strt2smovements."</p>";
			}

			$strt2stopics="";
			$strsqlt2stopics="SELECT T_WC_T2S_TOPIC.ID_TOPIC, T_WC_T2S_TOPIC.TOPIC_NAME, T_WC_T2S_TOPIC.TOPIC_NAME_FR, T_WC_T2S_TOPIC.TOPIC_SOURCE, T_WC_T2S_TOPIC.ID_RECORD ";
			$strsqlt2stopics.="FROM T_WC_T2S_MOVIE_TOPIC ";
			$strsqlt2stopics.="INNER JOIN T_WC_T2S_TOPIC ON T_WC_T2S_MOVIE_TOPIC.ID_TOPIC = T_WC_T2S_TOPIC.ID_TOPIC ";
			$strsqlt2stopics.="WHERE T_WC_T2S_MOVIE_TOPIC.ID_MOVIE = ".f_longtosql($lngmovieid)." AND T_WC_T2S_MOVIE_TOPIC.DELETED = 0 AND T_WC_T2S_TOPIC.DELETED = 0 ";
			$strsqlt2stopics.="ORDER BY T_WC_T2S_MOVIE_TOPIC.DISPLAY_ORDER, T_WC_T2S_TOPIC.TOPIC_NAME ";
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
				echo "<p><strong>".get_movie_string('lib_movie_topics').":</strong> ".$strt2stopics."</p>";
			}

			$strt2sawards="";
			// One link row per win, so an award won several times is grouped into one line.
			$strsqlt2sawards="SELECT T_WC_T2S_AWARD.ID_AWARD, T_WC_T2S_AWARD.AWARD_NAME, T_WC_T2S_AWARD.AWARD_NAME_FR, MIN(T_WC_T2S_MOVIE_AWARD.DISPLAY_ORDER) AS MIN_DISPLAY_ORDER ";
			$strsqlt2sawards.="FROM T_WC_T2S_MOVIE_AWARD ";
			$strsqlt2sawards.="INNER JOIN T_WC_T2S_AWARD ON T_WC_T2S_MOVIE_AWARD.ID_AWARD = T_WC_T2S_AWARD.ID_AWARD ";
			$strsqlt2sawards.="WHERE T_WC_T2S_MOVIE_AWARD.ID_MOVIE = ".f_longtosql($lngmovieid)." AND T_WC_T2S_MOVIE_AWARD.DELETED = 0 AND T_WC_T2S_AWARD.DELETED = 0 ";
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
				echo "<p><strong>".get_movie_string('lib_movie_awards').":</strong> ".$strt2sawards."</p>";
			}

			$strt2snominations="";
			$strsqlt2snominations="SELECT T_WC_T2S_NOMINATION.ID_NOMINATION, T_WC_T2S_NOMINATION.NOMINATION_NAME, T_WC_T2S_NOMINATION.NOMINATION_NAME_FR ";
			$strsqlt2snominations.="FROM T_WC_T2S_MOVIE_NOMINATION ";
			$strsqlt2snominations.="INNER JOIN T_WC_T2S_NOMINATION ON T_WC_T2S_MOVIE_NOMINATION.ID_NOMINATION = T_WC_T2S_NOMINATION.ID_NOMINATION ";
			$strsqlt2snominations.="WHERE T_WC_T2S_MOVIE_NOMINATION.ID_MOVIE = ".f_longtosql($lngmovieid)." AND T_WC_T2S_MOVIE_NOMINATION.DELETED = 0 AND T_WC_T2S_NOMINATION.DELETED = 0 ";
			$strsqlt2snominations.="ORDER BY T_WC_T2S_MOVIE_NOMINATION.DISPLAY_ORDER, T_WC_T2S_NOMINATION.NOMINATION_NAME ";
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
				echo "<p><strong>".get_movie_string('lib_movie_nominations').":</strong> ".$strt2snominations."</p>";
			}
			// Le lien inverse vers les lieux. LOCATION_ROLE est porte par la
			// jointure et non par le lieu : Paris reste le meme Paris qu on y ait tourne ou que
			// l action s y deroule. Les deux listes restent donc separees, les fusionner ferait
			// passer un decor de tournage pour un lieu de recit.
			$arrt2slocations=array('filming' => "", 'narrative' => "");
			$strsqlt2slocations="SELECT T_WC_T2S_MOVIE_LOCATION.LOCATION_ROLE, T_WC_T2S_LOCATION.ID_LOCATION, T_WC_T2S_LOCATION.LOCATION_NAME ";
			$strsqlt2slocations.="FROM T_WC_T2S_MOVIE_LOCATION ";
			$strsqlt2slocations.="INNER JOIN T_WC_T2S_LOCATION ON T_WC_T2S_MOVIE_LOCATION.ID_LOCATION = T_WC_T2S_LOCATION.ID_LOCATION ";
			$strsqlt2slocations.="WHERE T_WC_T2S_MOVIE_LOCATION.ID_MOVIE = ".f_longtosql($lngmovieid)." AND COALESCE(T_WC_T2S_MOVIE_LOCATION.DELETED,0) = 0 AND COALESCE(T_WC_T2S_LOCATION.DELETED,0) = 0 ";
			$strsqlt2slocations.="ORDER BY T_WC_T2S_MOVIE_LOCATION.DISPLAY_ORDER, T_WC_T2S_LOCATION.LOCATION_NAME ";
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
				echo "<p><strong>".get_movie_string('lib_movie_locations_filming').":</strong> ".$arrt2slocations['filming']."</p>";
			}
			if ($arrt2slocations['narrative']!="") {
				echo "<p><strong>".get_movie_string('lib_movie_locations_narrative').":</strong> ".$arrt2slocations['narrative']."</p>";
			}
			// Le lien inverse vers les oeuvres sources, "D'apres". Un film peut en
			// avoir plusieurs (Scarface 1983 : un roman ET le film de 1932). DELETED filtre sur la
			// jointure et sur l'oeuvre, comme pour les lieux.
			$strt2ssourceworks="";
			$strsqlt2ssourceworks="SELECT T_WC_T2S_SOURCE_WORK.ID_SOURCE_WORK, T_WC_T2S_SOURCE_WORK.SOURCE_WORK_NAME, T_WC_T2S_SOURCE_WORK.SOURCE_WORK_NAME_FR, T_WC_T2S_SOURCE_WORK.ID_MOVIE, T_WC_T2S_SOURCE_WORK.ID_SERIE ";
			$strsqlt2ssourceworks.="FROM T_WC_T2S_MOVIE_SOURCE_WORK ";
			$strsqlt2ssourceworks.="INNER JOIN T_WC_T2S_SOURCE_WORK ON T_WC_T2S_MOVIE_SOURCE_WORK.ID_SOURCE_WORK = T_WC_T2S_SOURCE_WORK.ID_SOURCE_WORK ";
			$strsqlt2ssourceworks.="WHERE T_WC_T2S_MOVIE_SOURCE_WORK.ID_MOVIE = ".f_longtosql($lngmovieid)." AND COALESCE(T_WC_T2S_MOVIE_SOURCE_WORK.DELETED,0) = 0 AND COALESCE(T_WC_T2S_SOURCE_WORK.DELETED,0) = 0 ";
			$strsqlt2ssourceworks.="ORDER BY T_WC_T2S_MOVIE_SOURCE_WORK.DISPLAY_ORDER, T_WC_T2S_SOURCE_WORK.SOURCE_WORK_YEAR, T_WC_T2S_SOURCE_WORK.SOURCE_WORK_NAME ";
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
					// Quand l'oeuvre source EST un film ou une serie T2S (Yojimbo pour A Fistful of
					// Dollars), on pointe droit sur sa fiche riche ; sinon sur la fiche sourcework.php.
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
				echo "<p><strong>".get_movie_string('lib_movie_based_on').":</strong> ".$strt2ssourceworks."</p>";
			}


			//Hook entity.info: lines added by the plugins in the information block
			echo f_pluginhtml("entity.info",array("type"=>"movie","id"=>$lngmovieid,"title"=>$strmovietitle,"titleyear"=>$strmovietitleyear));
			
			$strsqlmoviecompanies="SELECT T_WC_TMDB_COMPANY.ID_COMPANY, T_WC_TMDB_COMPANY.NAME ";
			$strsqlmoviecompanies.="FROM T_WC_TMDB_MOVIE_COMPANY ";
			$strsqlmoviecompanies.="INNER JOIN T_WC_TMDB_COMPANY ON T_WC_TMDB_MOVIE_COMPANY.ID_COMPANY = T_WC_TMDB_COMPANY.ID_COMPANY ";
			$strsqlmoviecompanies.="WHERE T_WC_TMDB_MOVIE_COMPANY.ID_MOVIE = ".f_longtosql($lngmovieid)." AND T_WC_TMDB_MOVIE_COMPANY.DELETED = 0 AND T_WC_TMDB_COMPANY.DELETED = 0 ";
			$strsqlmoviecompanies.="ORDER BY T_WC_TMDB_MOVIE_COMPANY.DISPLAY_ORDER ";
			$strmoviecompanies="";
			if ($intdebug) {
				echo "<br />".$strsqlmoviecompanies;
			}
			$resultmoviecompanies=f_sqlselect($strsqlmoviecompanies);
			if ($resultmoviecompanies) {
				while ($rstempmoviecompanies=f_sqlresultfetcharray($resultmoviecompanies)) {
					$strmoviecompanyid=$rstempmoviecompanies["ID_COMPANY"];
					$strmoviecompany=$rstempmoviecompanies["NAME"];
					$strmoviecompanyurl="company.php?companyid=".$strmoviecompanyid;
					if ($strmoviecompanies!="") {
						$strmoviecompanies.=", ";
					}
					$strmoviecompanies.="<a href=\"".$strmoviecompanyurl."\">".$strmoviecompany."</a>";
				}
				f_sqlfreeresult($resultmoviecompanies);
			}
			echo "<p><strong>".get_movie_string('lib_movie_companies').":</strong> ".$strmoviecompanies."</p>";

			// Display countries
			$strsqlmoviecountries = "SELECT T_WC_TMDB_MOVIE_PRODUCTION_COUNTRY.COUNTRY_CODE ";
			$strsqlmoviecountries .= "FROM T_WC_TMDB_MOVIE_PRODUCTION_COUNTRY ";
			$strsqlmoviecountries .= "WHERE T_WC_TMDB_MOVIE_PRODUCTION_COUNTRY.ID_MOVIE = " . f_longtosql($lngmovieid) . " AND T_WC_TMDB_MOVIE_PRODUCTION_COUNTRY.DELETED = 0 ";
			$strsqlmoviecountries .= "ORDER BY T_WC_TMDB_MOVIE_PRODUCTION_COUNTRY.ID_ROW ";

			$strmoviecountries = "";
			if ($intdebug) {
				echo "<br />" . $strsqlmoviecountries;
			}
			$resultmoviecountries = f_sqlselect($strsqlmoviecountries);
			if ($resultmoviecountries) {
				while ($rstempmoviecountries = f_sqlresultfetcharray($resultmoviecountries)) {
					$strmoviecountrycode = $rstempmoviecountries["COUNTRY_CODE"];
					$strmoviecountry = f_countrycodetranslatefrommap($strmoviecountrycode, $arrwatchcountryoptions, $struilang);
					$strmoviecountryurl = "country.php?country=" . $strmoviecountrycode;
					
					if ($strmoviecountries != "") {
						$strmoviecountries .= ", ";
					}
					$strmoviecountries .= "<a href=\"" . $strmoviecountryurl . "\">" . $strmoviecountry . "</a>";
				}
				f_sqlfreeresult($resultmoviecountries);
			}
			echo "<p><strong>" . get_movie_string('lib_movie_countries') . ":</strong> " . $strmoviecountries . "</p>";

			echo "<p>";
			echo "<strong>".get_movie_string('lib_person_record_updated').":</strong> ".$strmovietimcreditscompleted;
			echo "</p>";

			echo f_moviesectionopen("watch","▶️",get_movie_string('lib_watch_providers_section'));
			echo f_watchcountryselectorhtml(
				$strwatchcountry,
				$arrwatchcountryoptions,
				array("movieid"=>$lngmovieid),
				"movie-watch-country",
				get_movie_string('lib_watch_country'),
				get_movie_string('lib_watch_country_choose'),
				get_movie_string('lib_watch_country_apply')
			);
			if (f_workpropertysnapshotstate($strmoviewatchproviderscompleted, 0)==="unknown") {
				echo "<p>".get_movie_string('lib_watch_providers_unknown')."</p>";
			} elseif ($strwatchcountry=="") {
				echo "<p>".get_movie_string('lib_watch_providers_select_country')."</p>";
			} else {
				$arrmoviewatchproviders=array();
				$strmoviewatchproviderlink="";
				$strsqlmoviewatchproviders=f_workpropertywatchproviderssql(
					$strsqlns, "movie", $lngmovieid, $strwatchcountry);
				$resultmoviewatchproviders=f_sqlselect($strsqlmoviewatchproviders);
				if ($resultmoviewatchproviders) {
					while ($rstempmoviewatchprovider=f_sqlresultfetcharray($resultmoviewatchproviders)) {
						$arrmoviewatchproviders[]=$rstempmoviewatchprovider;
						if ($strmoviewatchproviderlink=="" && trim((string)$rstempmoviewatchprovider['TMDB_LINK'])!="") {
							$strmoviewatchproviderlink=trim((string)$rstempmoviewatchprovider['TMDB_LINK']);
						}
					}
					f_sqlfreeresult($resultmoviewatchproviders);
				}
				echo "<p><small>".get_movie_string('lib_watch_providers_collected').": ".
					f_workpropertyescape($strmoviewatchproviderscompleted)."</small></p>";
				if (f_workpropertysnapshotstate($strmoviewatchproviderscompleted, count($arrmoviewatchproviders))==="empty") {
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
							$arrmoviewatchproviders,
							$arrwatchmodelabels,
							$strtmdbimagespathbaseurl,
							get_movie_string('lib_watch_provider'),
							$strwatchcountry
						);
					if ($strmoviewatchproviderlink!="") {
						echo "<p><a href=\"".f_htmlattr($strmoviewatchproviderlink).
							"\" target=\"_blank\" rel=\"noreferrer\">".
							get_movie_string('lib_watch_providers_tmdb_link')."</a></p>";
					}
				}
				echo "<p><small>".get_movie_string('lib_watch_providers_attribution')."</small></p>";
			}

			echo f_moviesectionopen("release","📅",get_movie_string('lib_release_dates_section'));
			if (f_workpropertysnapshotstate($strmoviereleasedatescompleted, 0)==="unknown") {
				echo "<p>".get_movie_string('lib_release_dates_unknown')."</p>";
			} else {
				$arrmoviereleasedates=array();
				$strsqlmoviereleasedates=f_workpropertymoviereleasedatessql(
					$strsqlns, $lngmovieid, $strwatchcountry);
				$resultmoviereleasedates=f_sqlselect($strsqlmoviereleasedates);
				if ($resultmoviereleasedates) {
					while ($rstempmoviereleasedate=f_sqlresultfetcharray($resultmoviereleasedates)) {
						$arrmoviereleasedates[]=$rstempmoviereleasedate;
					}
					f_sqlfreeresult($resultmoviereleasedates);
				}
				echo "<p><small>".get_movie_string('lib_release_dates_collected').": ".
					f_workpropertyescape($strmoviereleasedatescompleted)."</small></p>";
				echo "<p><small>".get_movie_string('lib_release_dates_disclaimer')."</small></p>";
				if (f_workpropertysnapshotstate($strmoviereleasedatescompleted, count($arrmoviereleasedates))==="empty") {
					echo "<p>".get_movie_string('lib_release_dates_empty')."</p>";
				} else {
					$arrreleasecountrylabels=array();
					foreach ($arrmoviereleasedates as $arrmoviereleasedate) {
						$strreleasecountry=f_workpropertycountrycode($arrmoviereleasedate['COUNTRY_CODE']);
						if ($strreleasecountry!="" && !isset($arrreleasecountrylabels[$strreleasecountry])) {
							$arrreleasecountrylabels[$strreleasecountry]=f_countrycodetranslatefrommap(
								$strreleasecountry, $arrwatchcountryoptions, $struilang);
						}
					}
					$arrreleasetypelabels=array(
						1=>get_movie_string('lib_release_type_1'),
						2=>get_movie_string('lib_release_type_2'),
						3=>get_movie_string('lib_release_type_3'),
						4=>get_movie_string('lib_release_type_4'),
						5=>get_movie_string('lib_release_type_5'),
						6=>get_movie_string('lib_release_type_6')
					);
					$arrreleaselabels=array(
						"preferred"=>get_movie_string('lib_release_dates_preferred'),
						"unknown_type"=>get_movie_string('lib_release_type_unknown'),
						"no_date"=>get_movie_string('lib_release_no_date'),
						"certification"=>get_movie_string('lib_release_certification'),
						"language"=>get_movie_string('lib_release_language'),
						"note"=>get_movie_string('lib_release_note'),
						"descriptors"=>get_movie_string('lib_release_descriptors')
					);
					echo f_workpropertyreleasedateshtml(
						$arrmoviereleasedates,
						$arrreleasecountrylabels,
						$arrreleasetypelabels,
						$strwatchcountry,
						$arrreleaselabels
					);
				}
			}

			echo f_moviesectionopen("ids","🔗",($struilang!="en"?"Identifiants externes":"External IDs"));
			echo "<p><strong>".get_movie_string('lib_movie_id_tmdb').":</strong> ";
			if ($lngmovieid>0) {
				echo "<a href=\"".$strmovietmdburl."\" target=\"_blank\" rel=\"noreferrer\">";
			}
			echo $lngmovieid;
			if ($lngmovieid>0) {
				echo "</a>";
			}
			if ($lngmovieid>0) {
				echo " <a href=\"".$strmovietmdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/tmdb-icon.webp\" width=\"103\" height=\"16\" alt=\"TMDb\" title=\"TMDb\"></a>";
			}
			echo "</p>";
			
			echo "<p><strong>".get_movie_string('lib_movie_id_imdb').":</strong> ";
			if ($strmovieidimdb!="") {
				echo "<a href=\"".$strmovieimdburl."\" target=\"_blank\" rel=\"noreferrer\">";
			}
			echo $strmovieidimdb;
			if ($strmovieidimdb!="") {
				echo "</a>";
			}
			if ($strmovieidimdb!="") {
				echo " <a href=\"".$strmovieimdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
			}
			echo "</p>";
			echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> ";
			if ($strmovieidwikidata!="") {
				echo "<a href=\"".$strmoviewikidataurl."\" target=\"_blank\" rel=\"noreferrer\">";
			}
			echo $strmovieidwikidata;
			if ($strmovieidwikidata!="") {
				echo "</a>";
			}
			if ($strmovieidwikidata!="") {
				echo " <a href=\"".$strmoviewikidataurl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a>";
			}
			echo "</p>";
			echo f_wikipediaidline($struilang,$strmovieidwikidata);
			echo f_socialidshtml($arrmoviesocialids ?? array(),f_socialidslabels());
			//Hook entity.ids: identifier lines added by the plugins
			echo f_pluginhtml("entity.ids",array("type"=>"movie","id"=>$lngmovieid,"imdbid"=>$strmovieidimdb,"wikidataid"=>$strmovieidwikidata));
			//Filter page.url: a plugin that provides the page answers its URL; no link without one
			$strcriterionpageurl=f_pluginfilter("page.url","",array("page"=>"criterion"));
			echo "<p><strong>".get_movie_string('lib_movie_id_criterion')." ".($strcriterionpageurl!="" ? "<a href=\"".f_htmlattr($strcriterionpageurl)."\" target=\"_blank\">" : "").get_movie_string('lib_movie_criterion_collection').($strcriterionpageurl!="" ? "</a>" : "").":</strong> ";
			if ($lngmovieidcriterion>0) {
				echo "<a href=\"".$strcriterioncollectionurl."\" target=\"_blank\" rel=\"noreferrer\">";
			}
			echo $lngmovieidcriterion;
			if ($lngmovieidcriterion>0) {
				echo "</a>";
			}
			if ($lngmovieidcriterion>0) {
				echo " <a href=\"".$strcriterioncollectionurl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/The_Criterion_Collection_Logo.svg.png\" width=\"16\" height=\"16\" alt=\"Criterion Collection\" title=\"Criterion Collection\" style=\"background-color: white;\" ></a>";
			}
			if ($lngmovieidcriterionspine>0) {
				echo " ";
				echo "<a href=\"".$strcriterioncollectionurl."\" target=\"_blank\" rel=\"noreferrer\">";
				echo get_movie_string('lib_movie_spine')." ".$lngmovieidcriterionspine."";
				echo "</a>";
			}
			echo "</p>";
			
			//Hook entity.afterids: lines added by the plugins under the identifiers
			echo f_pluginhtml("entity.afterids",array("type"=>"movie","id"=>$lngmovieid,"imdbid"=>$strmovieidimdb));
			
			echo f_moviesectionopen("overview","📝",get_movie_string('lib_movie_overview'),1);
			echo "<p>".str_replace("\r","<br />",str_replace("\n","<br />",$strmovieoverview))."</p>";

			echo f_wikidatamediasection_v2($struilang,$strmovieidwikidata);

			echo f_moviesectionopen("wikidata","🧬",get_movie_string('lib_movie_section_wikidata'));
			echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_movie_wikidata_type')."</a>:</strong> ".$strmoviewikidatainstanceof."</p>";
			if ($strmoviealiases!="") {
				echo "<p><strong>".get_movie_string('lib_movie_wikidata_aliases').":</strong> ".$strmoviealiases."</p>";
			}
			/*
			echo "<p><strong>".get_movie_string('lib_wikidata_genres').":</strong> ".$strmoviewikidatagenres."</p>";
			echo "<p><strong>".get_movie_string('lib_wikidata_colors').":</strong> ".$strmoviewikidatacolors."</p>";
			echo "<p><strong>".get_movie_string('lib_wikidata_desc_by_source').":</strong> ".$strmoviewikidatadescbysrc."</p>";
			echo "<p><strong>".get_movie_string('lib_wikidata_part_of').":</strong> ".$strmoviewikidatapartof."</p>";
			*/
			if ($strmovieidwikidata!="") {
				$strmoviewikidataeverything=f_wikidataallpropertiesv2($struilang,$strmovieidwikidata,", ",f_t2slocationwikidataexcluded(isset($arrt2slocations) ? $arrt2slocations : array()));
			}
			echo $strmoviewikidataeverything;
			
			echo f_moviesectionopen("wikipedia","📖",get_movie_string('lib_movie_section_wikipedia'));
			$strmoviewikipediacontent=f_wikipediallsections($struilang,$strmovieidwikidata,$strsep=", ");
			echo $strmoviewikipediacontent;
			
			//Hook entity.sections: sections added by the plugins after the core sections
			echo f_pluginhtml("entity.sections",array("type"=>"movie","id"=>$lngmovieid,"title"=>$strmovietitle,"originaltitle"=>$strmovieoriginaltitle,"year"=>$strmoviereleaseyear,"imdbid"=>$strmovieidimdb));
			
			for ($intcredittype=1;$intcredittype<=2;$intcredittype++) {
				switch ($intcredittype) {
				case 1:
					//Cast
					$strtitle=get_movie_string('lib_movie_cast');
					$strmoviecreditcredittype="cast";
					$strsqlpersonmovie="
SELECT T_WC_TMDB_PERSON_MOVIE.ID_PERSON, 
T_WC_TMDB_PERSON_MOVIE.CAST_CHARACTER, 
T_WC_TMDB_PERSON.NAME, 
T_WC_TMDB_PERSON.PROFILE_PATH, 
T_WC_TMDB_PERSON.ID_IMDB 
FROM T_WC_TMDB_PERSON_MOVIE
INNER JOIN T_WC_TMDB_PERSON ON T_WC_TMDB_PERSON_MOVIE.ID_PERSON = T_WC_TMDB_PERSON.ID_PERSON
WHERE T_WC_TMDB_PERSON_MOVIE.ID_MOVIE = ".f_longtosql($lngmovieid)." 
AND T_WC_TMDB_PERSON_MOVIE.CREDIT_TYPE = ".f_stringtosql($strmoviecreditcredittype)." 
ORDER BY T_WC_TMDB_PERSON_MOVIE.DISPLAY_ORDER 
";
					break;
				case 2:
					//Crew
					$strtitle=get_movie_string('lib_movie_crew');
					$strmoviecreditcredittype="crew";
					$strsqlpersonmovie="
SELECT T_WC_TMDB_PERSON_MOVIE.ID_PERSON, 
T_WC_TMDB_PERSON_MOVIE.CREW_DEPARTMENT, 
T_WC_TMDB_PERSON_MOVIE.CREW_JOB, 
T_WC_TMDB_PERSON.NAME, 
T_WC_TMDB_PERSON.PROFILE_PATH, 
T_WC_TMDB_PERSON.ID_IMDB 
FROM T_WC_TMDB_PERSON_MOVIE
INNER JOIN T_WC_TMDB_PERSON ON T_WC_TMDB_PERSON_MOVIE.ID_PERSON = T_WC_TMDB_PERSON.ID_PERSON
WHERE T_WC_TMDB_PERSON_MOVIE.ID_MOVIE = ".f_longtosql($lngmovieid)." 
AND T_WC_TMDB_PERSON_MOVIE.CREDIT_TYPE = ".f_stringtosql($strmoviecreditcredittype)." 
ORDER BY T_WC_TMDB_PERSON_MOVIE.DISPLAY_ORDER 
";
					break;
				}
				echo f_moviesectionopen($strmoviecreditcredittype,($intcredittype==1?"🎭":"🛠️"),$strtitle);
				echo "<table>";
				echo "<tr>";
				//Remembering previous person id so we can manage several credits for the same person
				$lngpersonidold=0;
				$intpersonmoviefound=false;
				$lngrowindex=0;
				$resultpersonmovie=f_sqlselect($strsqlpersonmovie);
				if ($resultpersonmovie) {
					while ($rstemppersonmovie=f_sqlresultfetcharray($resultpersonmovie)) {
						$intpersonmoviefound=true;
						
						$strpersoncreditname=$rstemppersonmovie['NAME'];
						$strpersonprofilepath=$rstemppersonmovie['PROFILE_PATH'];
						$strpersonprofilefullpath=f_getpersonprofilepicture($strpersonprofilepath,1);
						//$strpersoncreditreleasedate=$rstemppersonmovie['DAT_RELEASE'];
						//$strpersoncreditreleaseyear=substr($strpersoncreditreleasedate,0,4);
						$lngpersonid=f_stringtolong($rstemppersonmovie['ID_PERSON']);
						$strpersonidimdb=$rstemppersonmovie['ID_IMDB'];
						$strpersonurl="person.php?personid=".$lngpersonid;
						
						switch ($intcredittype) {
						case 1:
							//Cast
							$strpersoncreditcharacter=$rstemppersonmovie['CAST_CHARACTER'];
							$strcharacterurl="character.php?character=".$strpersoncreditcharacter;
							$strcharacterwithlink="<a href=\"".$strcharacterurl."\">".get_movie_string('lib_movie_character').": ".$strpersoncreditcharacter."</a>";
							$strpersoncreditdepartment="";
							$strpersoncreditjob="";
							if ($lngpersonidold==$lngpersonid) {
								//Same person with different credits
								if ($intprivate) {
									echo ", ".$strcharacterwithlink;
								} else {
									echo ", ".get_movie_string('lib_movie_character').": ".$strpersoncreditcharacter;
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
									echo "<img src=\"".$strpersonprofilefullpath."\" alt=\"".$strpersoncreditname."\" title=\"".$strpersoncreditname."\" style=\"background-color: white;\" /><br />";
									echo "</a>";
								}
								echo "</td>";
								echo "<td valign=\"top\">";
								echo "<a href=\"".$strpersonurl."\">".$strpersoncreditname."</a>";
								if ($intprivate) {
									echo " - ".$strcharacterwithlink;
								} else {
									echo " - ".get_movie_string('lib_movie_character').": ".$strpersoncreditcharacter;
								}
							}
							break;
						case 2:
							//Crew
							$strpersoncreditcharacter="";
							$strpersoncreditdepartment=$rstemppersonmovie['CREW_DEPARTMENT'];
							$strpersoncreditjob=$rstemppersonmovie['CREW_JOB'];
							if ($lngpersonidold==$lngpersonid) {
								//Same person with different credits
								echo ", ".get_movie_string('lib_movie_department').": ".$strpersoncreditdepartment.", ".get_movie_string('lib_movie_job').": ".$strpersoncreditjob;
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
									echo "<img src=\"".$strpersonprofilefullpath."\" alt=\"".$strpersoncreditname."\" title=\"".$strpersoncreditname."\" style=\"background-color: white;\" /><br />";
									echo "</a>";
								}
								echo "</td>";
								echo "<td valign=\"top\">";
								echo "<a href=\"".$strpersonurl."\">".$strpersoncreditname."</a>";
								echo " - ".get_movie_string('lib_movie_department').": ".$strpersoncreditdepartment.", ".get_movie_string('lib_movie_job').": ".$strpersoncreditjob;
							}
							break;
						}
						$lngpersonidold=$lngpersonid;
					}
					f_sqlfreeresult($resultpersonmovie);
				}
				echo "</td>";
				echo "</tr>";
				echo "</table>";
			}
			
			echo f_moviesectionclose();

			//Credits read on screen by open-credits: raw OCR, in private and public mode alike
			include_once(__DIR__."/open-credits.inc.php");
			echo f_moviesectionwrap("open-credits","✍️",f_ocsection($lngmovieid));
			
			//Previous / next member in the T2S collection (cross-type: film or series)
			//Sortie capturee puis repliee en accordeon (deux titres possibles : precedent + suivant).
			ob_start();
			f_t2scollectionprevnextdisplay($lngmovieid,"movie");
			echo f_moviesectionwrap("collection","🧩",ob_get_clean(),($struilang!="en"?"Dans la collection":"In the collection"));
			//Grounded TMDb neighbour sets (similar + recommendations)
			ob_start();
			f_movietmdbneighboursdisplay($lngmovieid,"T_WC_TMDB_MOVIE_SIMILAR","ID_MOVIE_SIMILAR","lib_movie_tmdb_similar");
			echo f_moviesectionwrap("tmdb-similar","🎯",ob_get_clean());
			ob_start();
			f_movietmdbneighboursdisplay($lngmovieid,"T_WC_TMDB_MOVIE_RECOMMENDATION","ID_MOVIE_RECOMMENDED","lib_movie_tmdb_recommendations");
			echo f_moviesectionwrap("tmdb-reco","👍",ob_get_clean());
			//When this film is itself a source work (ID_MOVIE on T_WC_T2S_SOURCE_WORK), the works
			//adapted from it, films then series. Replaces the sourcework.php sheet, now redirected
			//here. Kept before the page.beforefooter hook, which runs once every card is rendered.
			//Sortie capturee puis repliee (deux titres possibles : films adaptes + series adaptees).
			ob_start();
			f_worksadaptedfromsection("movie",$lngmovieid);
			echo f_moviesectionwrap("adapted","📚",ob_get_clean(),($struilang!="en"?"Œuvres adaptées de ce film":"Works adapted from this film"));
			//Hook page.beforefooter: blocks of the plugins at the end of the page. Must stay after the
			//sections above: a plugin may sum up here what its listeners met on every card of the page.
			echo f_pluginhtml("page.beforefooter",array("page"=>"movie"));
			if ($strmoviebackdroppath!="") {
				echo "<p><a href=\"".$strtmdbimagesoriginalpathbaseurl.$strmoviebackdroppath."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"".$strtmdbimagespathbaseurl.$strmoviebackdroppath."\" alt=\"".$strmovietitleyear."\" title=\"".$strmovietitleyear."\" style=\"background-color: white;\" /></a></p>";
			}
			// Noyau deja emis ci-dessus (hors buffer). On recupere le corps bufferise des
			// sections, on prepend la barre d'ancres construite au fil des sections ouvertes,
			// puis on rend le tout avant le footer.
			$strfichebody=ob_get_clean();
			echo f_movieanchorbar();
			echo $strfichebody;
			f_footerdisplay();
		} else {
			//Movie record NOT found in the database
			$strmessage=get_movie_string('lib_movie_not_found_db');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";

			//So we redirect to the movie-load.php script to read data from the TMDb API
			//$strredirectscript="movie-load.php?movieid=".$lngmovieid;
			//header("Location: ".$strredirectscript);
		}
	}  else {
		//movieid URL parameter not set
		$strmessage=get_movie_string('lib_movie_id_not_set');
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
