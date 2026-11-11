<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/movie-sections.inc.php");

if ($intdbavailable) {
	$lngserieid=f_stringtolong(f_request2("serieid"));
	$lngseasonnumber=f_stringtolong(f_request2("season"));
	$intseasonfound=false;
	$strseasontitle="";
	$strseasonoverview="";
	$strseasondatair="";
	$strseasonposterpath="";
	$lngseasonid=0;
	$lngseasonepisodecount=0;
	$dblseasonvoteaverage=0;
	$dblseasonimdbrating=0;
	$lngseasonimdbratedepisodes=0;
	$strseasonidimdb="";
	$strseasonidwikidata="";
	$lngseasonidtvdb=0;
	$lngseasondisplayorder=0;
	$strseasontimcreditscompleted="";
	$strserieidwikidata="";
	$strserieidimdb="";
	$strserietitle="";
	$strserieoriginaltitle="";
	$strseriedatfirstair="";
	$strseriefirstairyear="";
	$strseriedatlastair="";
	$strserielastairyear="";
	$strserieposterpath="";

	if ($lngserieid<=0 || $lngseasonnumber<0) {
		$strmessage=get_movie_string('lib_season_not_found_db');
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		exit;
	}

	$strsqlserie="SELECT * FROM ".$strsqlns."TMDB_SERIE WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND DELETED = 0 ";
	$resultserie=f_sqlselect($strsqlserie);
	if ($resultserie) {
		if ($rstempserie=f_sqlresultfetcharray($resultserie)) {
			$strserietitle=$rstempserie['TITLE'];
			$strserieoriginaltitle=$rstempserie['ORIGINAL_TITLE'];
			$strseriedatfirstair=$rstempserie['DAT_FIRST_AIR'];
			$strseriefirstairyear=substr($strseriedatfirstair,0,4);
			$strseriedatlastair=$rstempserie['DAT_LAST_AIR'];
			$strserielastairyear=substr($strseriedatlastair,0,4);
			$strserieposterpath=$rstempserie['POSTER_PATH'];
			$strserieidwikidata=$rstempserie['ID_WIKIDATA'];
			$strserieidimdb=$rstempserie['ID_IMDB'];
		}
		f_sqlfreeresult($resultserie);
	}

	if ($struilang!="en") {
		$strsqlserielang="SELECT * FROM ".$strsqlns."TMDB_SERIE_LANG WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
		$resultserielang=f_sqlselect($strsqlserielang);
		if ($resultserielang) {
			if ($rstempserielang=f_sqlresultfetcharray($resultserielang)) {
				if (trim((string)$rstempserielang['TITLE'])!="") {
					$strserietitle=$rstempserielang['TITLE'];
				}
			}
			f_sqlfreeresult($resultserielang);
		}
	}

	$strsqlseason="SELECT * FROM ".$strsqlns."TMDB_SEASON WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND SEASON_NUMBER = ".f_longtosql($lngseasonnumber)." AND DELETED = 0 ";
	$resultseason=f_sqlselect($strsqlseason);
	if ($resultseason) {
		if ($rstempseason=f_sqlresultfetcharray($resultseason)) {
			$intseasonfound=true;
			$lngseasonid=f_stringtolong($rstempseason['ID_SEASON']);
			$strseasontitle=$rstempseason['TITLE'];
			$strseasonoverview=$rstempseason['OVERVIEW'];
			$strseasondatair=$rstempseason['DAT_AIR'];
			$strseasonposterpath=$rstempseason['POSTER_PATH'];
			$lngseasonepisodecount=f_stringtolong($rstempseason['EPISODE_COUNT']);
			$dblseasonvoteaverage=f_stringtodouble($rstempseason['VOTE_AVERAGE']);
			$strseasonidimdb=$rstempseason['ID_IMDB'];
			$strseasonidwikidata=$rstempseason['ID_WIKIDATA'];
			$lngseasonidtvdb=f_stringtolong($rstempseason['ID_TVDB']);
			$lngseasondisplayorder=f_stringtolong($rstempseason['DISPLAY_ORDER']);
			$strseasontimcreditscompleted=$rstempseason['TIM_CREDITS_COMPLETED'];
		}
		f_sqlfreeresult($resultseason);
	}

	if (!$intseasonfound) {
		$strmessage=get_movie_string('lib_season_not_found_db');
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		exit;
	}

	if ($struilang!="en") {
		$strsqlseasonlang="SELECT * FROM ".$strsqlns."TMDB_SEASON_LANG WHERE ID_SEASON = ".f_longtosql($lngseasonid)." AND ID_SERIE = ".f_longtosql($lngserieid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
		$resultseasonlang=f_sqlselect($strsqlseasonlang);
		if ($resultseasonlang) {
			if ($rstempseasonlang=f_sqlresultfetcharray($resultseasonlang)) {
				if (trim((string)$rstempseasonlang['TITLE'])!="") {
					$strseasontitle=$rstempseasonlang['TITLE'];
				}
				if (trim((string)$rstempseasonlang['OVERVIEW'])!="") {
					$strseasonoverview=$rstempseasonlang['OVERVIEW'];
				}
			}
			f_sqlfreeresult($resultseasonlang);
		}
	}

	//IMDb rates titles and episodes, never seasons, so a season rating has to be DERIVED: the
	//plain mean of its rated episodes, rolled up by tmdb-movie-preprocess Process 28
	// into T_WC_T2S_SEASON. Read from that column rather than
	//recomputed here, so this page, the API and voice-agent all quote
	//ONE number for a season: a figure that is derived rather than sourced must have a single
	//definition, or two surfaces disagree about the same season and neither is wrong.
	//Same query shape as the API's /series/{id}/seasons/{n}: T_WC_TMDB_SEASON stays the row
	//source and the IMDb pair is looked up beside it, so a season outside the T2S scope still
	//renders, simply without a rating.
	//Two consequences of mirroring rather than computing, both accepted: the figure is one
	//preprocess run behind the IMDb crawl, and it is absent for any serie outside the T2S scope.
	$strsqlseasonimdb="SELECT t2s.IMDB_RATING, ";
	//The denominator counts episodes carrying a rating, which is what the API counts. Note it is
	//a hair looser than the population Process 28 averages, which also requires IMDB_VOTES > 0:
	//the ~1% of IMDb ratings that arrive without a vote count are counted here but not averaged.
	$strsqlseasonimdb.="(SELECT COUNT(*) FROM ".$strsqlns."T2S_EPISODE e ";
	$strsqlseasonimdb.="WHERE e.ID_SEASON = t2s.ID_SEASON AND e.IMDB_RATING IS NOT NULL) AS IMDB_RATED_EPISODES ";
	$strsqlseasonimdb.="FROM ".$strsqlns."T2S_SEASON t2s ";
	$strsqlseasonimdb.="WHERE t2s.ID_SEASON = ".f_longtosql($lngseasonid);
	$resultseasonimdb=f_sqlselect($strsqlseasonimdb);
	if ($resultseasonimdb) {
		if ($rstempseasonimdb=f_sqlresultfetcharray($resultseasonimdb)) {
			//NULL until the rollup has run, which is exactly the "nothing rated yet" case
			$dblseasonimdbrating=f_stringtodouble((string)$rstempseasonimdb['IMDB_RATING']);
			$lngseasonimdbratedepisodes=f_stringtolong((string)$rstempseasonimdb['IMDB_RATED_EPISODES']);
		}
		f_sqlfreeresult($resultseasonimdb);
	}

	$strseasoninstanceof="";
	$strseasonwikidatainstanceof="";
	$strseasonwikidataeverything="";
	if ($strseasonidwikidata!="") {
		$strsqlseasonwd="SELECT INSTANCE_OF FROM ".$strsqlns."WIKIDATA_SEASON_V1 WHERE ID_WIKIDATA = ".f_stringtosql($strseasonidwikidata)." AND DELETED = 0 ";
		$resultseasonwd=f_sqlselect($strsqlseasonwd);
		if ($resultseasonwd) {
			if ($rstempseasonwd=f_sqlresultfetcharray($resultseasonwd)) {
				$strseasoninstanceof=trim((string)$rstempseasonwd['INSTANCE_OF']);
			}
			f_sqlfreeresult($resultseasonwd);
		}
		// Le libelle de la classe se lit en V2, repli V1.
		// La requete remplacee n avait aucun repli vers l anglais : la bascule en ajoute
		// un, donc cette page peut afficher un type la ou elle rendait du vide.
		$strseasonwikidatainstanceof=f_getwikidatalabel($strseasoninstanceof,$struilang);
		$strseasonwikidataeverything=f_wikidataallpropertiesv2($struilang,$strseasonidwikidata,", ","");
		//P166,P1411
	}

	$strseasondisplaytitle=get_movie_string('lib_season_season')." ".$lngseasonnumber;
	if ($strseasontitle!="" && $strseasontitle!=$strseasondisplaytitle) {
		$strseasondisplaytitle.=" - ".$strseasontitle;
	}
	$strserieshorttitle=$strserietitle;
	if ($strserieshorttitle=="") {
		$strserieshorttitle=get_movie_string('lib_tv_shows_section');
	}
	$strseasontmdburl="https://www.themoviedb.org/tv/".$lngserieid."/season/".$lngseasonnumber;
	if ($strseasonidimdb!="") {
		$strseasonimdburl="https://www.imdb.com/title/".$strseasonidimdb."/";
	}
	if ($strseasonidwikidata!="") {
		$strseasonwikidataurl="https://www.wikidata.org/wiki/".$strseasonidwikidata;
	}
	$strpagetitle=$strserieshorttitle." - ".$strseasondisplaytitle;
	//Social preview: the season poster is the visual shown when the page URL is shared
	$arrsocialseason=array();
	$arrsocialseason['description']=$strseasonoverview;
	if ($strseasonposterpath!="") {
		$arrsocialseason['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strseasonposterpath);
		//TMDb posters are 2:3, so the w342 rendition is 342x513
		$arrsocialseason['imagewidth']=342;
		$arrsocialseason['imageheight']=513;
		$arrsocialseason['imagealt']=$strpagetitle;
	}
	f_headerdisplay($strpagetitle,"",$arrsocialseason);

	echo "<h1>".$strpagetitle;
	//Hook entity.h1: badges and links added by the plugins after the title
	echo f_pluginhtml("entity.h1",array("type"=>"season","id"=>$lngseasonid,"serieid"=>$lngserieid));
	echo "</h1>";
	if ($strseasonposterpath!="") {
		echo "<p>";
		echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strseasonposterpath."\" target=\"_blank\" rel=\"noreferrer\">";
		echo "<img src=\"".$strtmdbimagespathbaseurl.$strseasonposterpath."\" alt=\"".$strpagetitle."\" title=\"".$strpagetitle."\" style=\"background-color: white;\" />";
		echo "</a>";
		echo "</p>";
	}

	$strserieurl="serie.php?serieid=".$lngserieid;
	f_moviesectionreset(array("info","overview","cast","crew"));
	ob_start();
	echo f_moviesectionopen("info","🪪",get_movie_string('lib_serie_section_tmdb'),1);
	echo "<p><strong>".get_movie_string('lib_season_parent_serie').":</strong> <a href=\"".$strserieurl."\">".$strserietitle."</a></p>";
	echo "<p><strong>".get_movie_string('lib_season_season_number').":</strong> ".$lngseasonnumber."</p>";
	if ($strseasontitle!="") {
		echo "<p><strong>".get_movie_string('lib_season_title').":</strong> ".$strseasontitle."</p>";
	}
	if ($strseasondatair!="") {
		echo "<p><strong>".get_movie_string('lib_season_air_date').":</strong> ".$strseasondatair."</p>";
	}
	if ($lngseasonepisodecount>0) {
		echo "<p><strong>".get_movie_string('lib_season_episode_count').":</strong> ".$lngseasonepisodecount."</p>";
	}
	//IMDb only, same reasoning as the episode page. The parenthesised episode count is the
	//honest denominator: it says what the mean rests on and that the figure is derived, and it
	//is not a vote total because summing episode votes would count the same viewers once per
	//episode. Nothing is printed when no episode of the season carries a rating.
	//echo "<p><strong>".get_movie_string('lib_season_vote_average').":</strong> ".$dblseasonvoteaverage."</p>";
	$strseasonimdbrating=f_imdbratingdisplay($dblseasonimdbrating,0,$lngseasonimdbratedepisodes);
	if ($strseasonimdbrating!="") {
		echo "<p><strong>".get_movie_string('lib_season_imdb_rating').":</strong> ".$strseasonimdbrating."</p>";
	}
	if ($lngseasondisplayorder>0) {
		echo "<p><strong>".get_movie_string('lib_season_display_order').":</strong> ".$lngseasondisplayorder."</p>";
	}
	echo "<p><strong>".get_movie_string('lib_serie_id_tmdb').":</strong> ";
	if ($lngseasonid>0) {
		echo "<a href=\"".$strseasontmdburl."\" target=\"_blank\" rel=\"noreferrer\">";
	}
	echo $lngseasonid;
	if ($lngseasonid>0) {
		echo "</a>";
		echo " <a href=\"".$strseasontmdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/tmdb-icon.webp\" width=\"103\" height=\"16\" alt=\"TMDb\" title=\"TMDb\"></a>";
	}
	echo "</p>";
	echo "<p><strong>".get_movie_string('lib_serie_id_imdb').":</strong> ";
	if ($strseasonidimdb!="") {
		echo "<a href=\"".$strseasonimdburl."\" target=\"_blank\" rel=\"noreferrer\">";
	}
	echo $strseasonidimdb;
	if ($strseasonidimdb!="") {
		echo "</a>";
		echo " <a href=\"".$strseasonimdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
	}
	echo "</p>";
	echo "<p><strong>".get_movie_string('lib_serie_id_wikidata').":</strong> ";
	if ($strseasonidwikidata!="") {
		echo "<a href=\"".$strseasonwikidataurl."\" target=\"_blank\" rel=\"noreferrer\">";
	}
	echo $strseasonidwikidata;
	if ($strseasonidwikidata!="") {
		echo "</a>";
		echo " <a href=\"".$strseasonwikidataurl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a>";
	}
	echo "</p>";
	if ($lngseasonidtvdb>0) {
		echo "<p><strong>".get_movie_string('lib_season_id_tvdb').":</strong> ".$lngseasonidtvdb."</p>";
	}

	if ($strseasontimcreditscompleted!="") {
		echo "<p><strong>".get_movie_string('lib_person_record_updated').":</strong> ".$strseasontimcreditscompleted."</p>";
	}
	echo f_moviesectionopen("overview","📝",get_movie_string('lib_season_overview'),1);
	echo "<p>".str_replace("\r","<br />",str_replace("\n","<br />",$strseasonoverview))."</p>";

	if ($strseasonidwikidata!="") {
		$strpropertyurl="property.php?propertyid=P31";
		echo f_moviesectionopen("wikidata","🧬",get_movie_string('lib_serie_section_wikidata'));
		echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_serie_wikidata_type')."</a>:</strong> ".$strseasonwikidatainstanceof."</p>";
		echo $strseasonwikidataeverything;

		echo f_moviesectionopen("wikipedia","📖",get_movie_string('lib_serie_section_wikipedia'));
		$strseasonwikipediacontent=f_wikipediallsections($struilang,$strseasonidwikidata,$strsep=", ");
		echo $strseasonwikipediacontent;
	}

	echo f_moviesectionopen("episodes","📺",get_movie_string('lib_season_episodes'),1);
	//LEFT JOIN, not INNER: an episode with no IMDb id, or one aired too recently to be rated,
	//must still appear in the list. It simply shows no rating line, which is the information.
	$strsqlepisodes="SELECT e.ID_EPISODE, e.EPISODE_NUMBER, e.TITLE, e.DAT_AIR, e.RUNTIME, e.STILL_PATH, e.OVERVIEW, ";
	$strsqlepisodes.="imdb.averageRating AS IMDB_RATING, imdb.numVotes AS IMDB_VOTES ";
	$strsqlepisodes.="FROM ".$strsqlns."TMDB_EPISODE e ";
	$strsqlepisodes.="LEFT JOIN ".$strsqlns."IMDB_MOVIE_RATING_IMPORT imdb ON e.ID_IMDB = imdb.tconst AND e.ID_IMDB <> '' ";
	$strsqlepisodes.="WHERE e.ID_SERIE = ".f_longtosql($lngserieid)." AND e.ID_SEASON = ".f_longtosql($lngseasonid)." AND e.DELETED = 0 ";
	$strsqlepisodes.="ORDER BY e.EPISODE_NUMBER, e.DISPLAY_ORDER, e.ID_EPISODE ";
	$resultepisodes=f_sqlselect($strsqlepisodes);
	if ($resultepisodes) {
		echo "<table>";
		while ($rstempepisode=f_sqlresultfetcharray($resultepisodes)) {
			$lngepisodeid=f_stringtolong($rstempepisode['ID_EPISODE']);
			$lngepisodenumber=f_stringtolong($rstempepisode['EPISODE_NUMBER']);
			$strepisodetitle=$rstempepisode['TITLE'];
			$strepisodedatair=$rstempepisode['DAT_AIR'];
			$lngepisoderuntime=f_stringtolong($rstempepisode['RUNTIME']);
			$strepisodestillpath=$rstempepisode['STILL_PATH'];
			$strepisodeoverview=$rstempepisode['OVERVIEW'];
			$strepisodeurl="episode.php?serieid=".$lngserieid."&season=".$lngseasonnumber."&episode=".$lngepisodenumber;
			echo "<tr>";
			echo "<td valign=\"top\">";
			if ($strepisodestillpath!="") {
				echo "<a href=\"".$strepisodeurl."\"><img src=\"".$strtmdbimagespathbaseurl.$strepisodestillpath."\" alt=\"".$strepisodetitle."\" title=\"".$strepisodetitle."\" style=\"background-color: white; max-width: 342px;\" /></a>";
			}
			echo "</td>";
			echo "<td valign=\"top\">";
			echo "<a href=\"".$strepisodeurl."\">".get_movie_string('lib_episode_episode')." ".$lngepisodenumber;
			if ($strepisodetitle!="") {
				echo ": ".$strepisodetitle;
			}
			echo "</a>";
			if ($strepisodedatair!="") {
				echo "<br /><strong>".get_movie_string('lib_episode_air_date').":</strong> ".$strepisodedatair;
			}
			if ($lngepisoderuntime>0) {
				echo "<br /><strong>".get_movie_string('lib_episode_runtime').":</strong> ".$lngepisoderuntime." ".get_movie_string('lib_serie_minutes');
			}
			//Hook season.episode.row: what the plugins add under an episode of the table
			echo f_pluginhtml("season.episode.row",array("seasonid"=>$lngseasonid,"episodeid"=>$lngepisodeid));
			$strepisodeimdbrating=f_imdbratingdisplay($rstempepisode['IMDB_RATING'],$rstempepisode['IMDB_VOTES']);
			if ($strepisodeimdbrating!="") {
				echo "<br /><strong>".get_movie_string('lib_episode_imdb_rating').":</strong> ".$strepisodeimdbrating;
			}
			if ($strepisodeoverview!="") {
				echo "<br />".str_replace("\r","<br />",str_replace("\n","<br />",$strepisodeoverview));
			}
			echo "</td>";
			echo "</tr>";
		}
		echo "</table>";
		f_sqlfreeresult($resultepisodes);
	}

	for ($intcredittype=1;$intcredittype<=2;$intcredittype++) {
		switch ($intcredittype) {
		case 1:
			$strtitle=get_movie_string('lib_season_cast');
			$strcredittype="cast";
			$strsqlpersonseason="
SELECT T_WC_TMDB_PERSON_SEASON.ID_PERSON,
T_WC_TMDB_PERSON_SEASON.CAST_CHARACTER,
T_WC_TMDB_PERSON_SEASON.TOTAL_EPISODE_COUNT,
T_WC_TMDB_PERSON.NAME,
T_WC_TMDB_PERSON.PROFILE_PATH,
T_WC_TMDB_PERSON.ID_IMDB
FROM T_WC_TMDB_PERSON_SEASON
INNER JOIN T_WC_TMDB_PERSON ON T_WC_TMDB_PERSON_SEASON.ID_PERSON = T_WC_TMDB_PERSON.ID_PERSON
WHERE T_WC_TMDB_PERSON_SEASON.ID_SERIE = ".f_longtosql($lngserieid)."
AND T_WC_TMDB_PERSON_SEASON.ID_SEASON = ".f_longtosql($lngseasonid)."
AND T_WC_TMDB_PERSON_SEASON.CREDIT_TYPE = ".f_stringtosql($strcredittype)."
ORDER BY T_WC_TMDB_PERSON_SEASON.DISPLAY_ORDER, T_WC_TMDB_PERSON_SEASON.ID_PERSON
";
			break;
		case 2:
			$strtitle=get_movie_string('lib_season_crew');
			$strcredittype="crew";
			$strsqlpersonseason="
SELECT T_WC_TMDB_PERSON_SEASON.ID_PERSON,
T_WC_TMDB_PERSON_SEASON.CREW_DEPARTMENT,
T_WC_TMDB_PERSON_SEASON.CREW_JOB,
T_WC_TMDB_PERSON_SEASON.TOTAL_EPISODE_COUNT,
T_WC_TMDB_PERSON.NAME,
T_WC_TMDB_PERSON.PROFILE_PATH,
T_WC_TMDB_PERSON.ID_IMDB
FROM T_WC_TMDB_PERSON_SEASON
INNER JOIN T_WC_TMDB_PERSON ON T_WC_TMDB_PERSON_SEASON.ID_PERSON = T_WC_TMDB_PERSON.ID_PERSON
WHERE T_WC_TMDB_PERSON_SEASON.ID_SERIE = ".f_longtosql($lngserieid)."
AND T_WC_TMDB_PERSON_SEASON.ID_SEASON = ".f_longtosql($lngseasonid)."
AND T_WC_TMDB_PERSON_SEASON.CREDIT_TYPE = ".f_stringtosql($strcredittype)."
ORDER BY T_WC_TMDB_PERSON_SEASON.DISPLAY_ORDER, T_WC_TMDB_PERSON_SEASON.ID_PERSON
";
			break;
		}
		echo f_moviesectionopen($strcredittype,($intcredittype==1?"🎭":"🛠️"),$strtitle);
		echo "<table>";
		echo "<tr>";
		$lngpersonidold=0;
		$lngrowindex=0;
		$resultpersonseason=f_sqlselect($strsqlpersonseason);
		if ($resultpersonseason) {
			while ($rstemppersonseason=f_sqlresultfetcharray($resultpersonseason)) {
				$strpersoncreditname=$rstemppersonseason['NAME'];
				$strpersonprofilepath=$rstemppersonseason['PROFILE_PATH'];
				$strpersonprofilefullpath=f_getpersonprofilepicture($strpersonprofilepath,1);
				$lngpersonid=f_stringtolong($rstemppersonseason['ID_PERSON']);
				$strpersonurl="person.php?personid=".$lngpersonid;
				$lngtotalepisodecount=f_stringtolong($rstemppersonseason['TOTAL_EPISODE_COUNT']);
				switch ($intcredittype) {
				case 1:
					$strpersoncreditcharacter=$rstemppersonseason['CAST_CHARACTER'];
					$strcharacterurl="character.php?character=".$strpersoncreditcharacter;
					$strcharacterwithlink="<a href=\"".$strcharacterurl."\">".get_movie_string('lib_serie_character').": ".$strpersoncreditcharacter."</a>";
					$strcredittext=($intprivate?$strcharacterwithlink:get_movie_string('lib_serie_character').": ".$strpersoncreditcharacter);
					if ($lngtotalepisodecount>0) {
						$strcredittext.=" (".get_movie_string('lib_season_total_episode_count').": ".$lngtotalepisodecount.")";
					}
					break;
				default:
					$strpersoncreditdepartment=$rstemppersonseason['CREW_DEPARTMENT'];
					$strpersoncreditjob=$rstemppersonseason['CREW_JOB'];
					$strcredittext=get_movie_string('lib_serie_department').": ".$strpersoncreditdepartment.", ".get_movie_string('lib_serie_job').": ".$strpersoncreditjob;
					if ($lngtotalepisodecount>0) {
						$strcredittext.=" (".get_movie_string('lib_season_total_episode_count').": ".$lngtotalepisodecount.")";
					}
					break;
				}
				if ($lngpersonidold==$lngpersonid) {
					echo ", ".$strcredittext;
				} else {
					if ($lngrowindex>0) {
						echo "</td></tr><tr>";
					}
					$lngrowindex++;
					echo "<td valign=\"top\">";
					if ($strpersonprofilefullpath!="") {
						echo "<a href=\"".$strpersonurl."\"><img src=\"".$strpersonprofilefullpath."\" alt=\"".$strpersoncreditname."\" title=\"".$strpersoncreditname."\" style=\"background-color: white;\" /><br /></a>";
					}
					echo "</td><td valign=\"top\"><a href=\"".$strpersonurl."\">".$strpersoncreditname."</a> - ".$strcredittext;
				}
				$lngpersonidold=$lngpersonid;
			}
			f_sqlfreeresult($resultpersonseason);
		}
		echo "</td>";
		echo "</tr>";
		echo "</table>";
	}

	//Hook entity.sections: sections added by the plugins after the core sections
	echo f_pluginhtml("entity.sections",array("type"=>"season","id"=>$lngseasonid,"serieid"=>$lngserieid));

	echo f_moviesectionclose();
	// Noyau deja emis hors buffer ; on prepend la barre d'ancres au corps bufferise.
	$strfichebody=ob_get_clean();
	echo f_movieanchorbar();
	echo $strfichebody;
	f_footerdisplay();
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
